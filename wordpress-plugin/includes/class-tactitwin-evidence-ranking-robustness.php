<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Evidence_Ranking_Robustness {
    private const WEIGHT_PROFILES = array(
        'baseline' => array(
            'scenario_relevance' => 0.45,
            'evidence_quality' => 0.35,
            'opponent_similarity' => 0.20,
        ),
        'balanced' => array(
            'scenario_relevance' => 0.34,
            'evidence_quality' => 0.33,
            'opponent_similarity' => 0.33,
        ),
        'relevance_heavy' => array(
            'scenario_relevance' => 0.60,
            'evidence_quality' => 0.25,
            'opponent_similarity' => 0.15,
        ),
        'quality_heavy' => array(
            'scenario_relevance' => 0.30,
            'evidence_quality' => 0.50,
            'opponent_similarity' => 0.20,
        ),
        'similarity_heavy' => array(
            'scenario_relevance' => 0.35,
            'evidence_quality' => 0.25,
            'opponent_similarity' => 0.40,
        ),
    );

    public static function retrieval( array $retrieval, string $language = 'en', int $limit = 5 ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $limit = max( 1, min( 10, $limit ) );

        if ( 'historical-precedent-retrieval' !== (string) ( $retrieval['precedent_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'Evidence ranking robustness requires suitability-ranked historical precedent retrieval.' );
        }

        $items = is_array( $retrieval['items'] ?? null ) ? array_values( $retrieval['items'] ) : array();
        $analysis = self::analyze( $items, 'retrieval_score', $language );
        $items = self::attach_item_robustness( $items, $analysis['item_stats'] );

        $retrieval['ranking_robustness'] = $analysis['summary'];
        $retrieval['items'] = array_slice( $items, 0, $limit );
        $retrieval['returned_group_count'] = count( $retrieval['items'] );
        if ( ! isset( $retrieval['method'] ) || ! is_array( $retrieval['method'] ) ) {
            $retrieval['method'] = array();
        }
        $retrieval['method']['evidence_ranking_robustness'] = self::method( $language, 'exact-context-retrieval-shortlist' );
        return $retrieval;
    }

    public static function catalog( array $catalog, string $language = 'en', int $contexts_per_scenario = 3 ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $contexts_per_scenario = max( 1, min( 10, $contexts_per_scenario ) );

        if ( 'pre-match-precedent-catalog' !== (string) ( $catalog['catalog_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'Evidence ranking robustness requires a suitability-ranked pre-match precedent catalog.' );
        }

        $scenario_summaries = array();
        if ( ! empty( $catalog['scenarios'] ) && is_array( $catalog['scenarios'] ) ) {
            foreach ( $catalog['scenarios'] as &$scenario ) {
                $contexts = is_array( $scenario['contexts'] ?? null ) ? array_values( $scenario['contexts'] ) : array();
                $analysis = self::analyze( $contexts, 'support_order_score', $language );
                $contexts = self::attach_item_robustness( $contexts, $analysis['item_stats'] );
                $scenario['ranking_robustness'] = $analysis['summary'];
                $scenario['returned_context_count'] = min( count( $contexts ), $contexts_per_scenario );
                $scenario['contexts'] = array_slice( $contexts, 0, $contexts_per_scenario );
                $scenario_summaries[] = array(
                    'scenario_id' => (string) ( $scenario['scenario_id'] ?? '' ),
                    'candidate_count' => (int) ( $analysis['summary']['candidate_count'] ?? 0 ),
                    'top_stability_pct' => $analysis['summary']['top_stability_pct'] ?? null,
                    'robustness_level' => (string) ( $analysis['summary']['robustness_level'] ?? 'unavailable' ),
                );
            }
            unset( $scenario );
        }

        if ( ! isset( $catalog['coverage'] ) || ! is_array( $catalog['coverage'] ) ) {
            $catalog['coverage'] = array();
        }
        $catalog['coverage']['contexts_per_scenario_limit'] = $contexts_per_scenario;
        $catalog['ranking_robustness'] = array(
            'scenario_count' => count( $scenario_summaries ),
            'scenarios' => $scenario_summaries,
            'note' => self::text( 'catalog_summary', $language ),
        );
        if ( ! isset( $catalog['method'] ) || ! is_array( $catalog['method'] ) ) {
            $catalog['method'] = array();
        }
        $catalog['method']['evidence_ranking_robustness'] = self::method( $language, 'within-scenario-context-shortlist' );
        return $catalog;
    }

    private static function analyze( array $items, string $legacy_score_key, string $language ): array {
        $count = count( $items );
        if ( 0 === $count ) {
            return array(
                'summary' => self::empty_summary( $language ),
                'item_stats' => array(),
            );
        }

        $keys = array();
        foreach ( $items as $index => $item ) {
            $keys[ $index ] = self::item_key( $item, $index );
        }

        $profile_rankings = array();
        $rank_positions = array();
        foreach ( self::WEIGHT_PROFILES as $profile_id => $weights ) {
            $rows = array();
            foreach ( $items as $index => $item ) {
                $rows[] = array(
                    'key' => $keys[ $index ],
                    'index' => $index,
                    'score' => self::weighted_score( $item, $weights ),
                    'legacy_score' => (float) ( $item[ $legacy_score_key ] ?? 0.0 ),
                );
            }
            usort( $rows, array( self::class, 'compare_profile_rows' ) );
            foreach ( $rows as $rank_index => $row ) {
                $rank_positions[ $row['key'] ][ $profile_id ] = $rank_index + 1;
            }
            $profile_rankings[ $profile_id ] = array(
                'weights' => $weights,
                'top_key' => (string) ( $rows[0]['key'] ?? '' ),
                'top_score' => isset( $rows[0]['score'] ) ? round( (float) $rows[0]['score'], 1 ) : null,
                'runner_up_key' => (string) ( $rows[1]['key'] ?? '' ),
                'runner_up_score' => isset( $rows[1]['score'] ) ? round( (float) $rows[1]['score'], 1 ) : null,
            );
        }

        $baseline_top = (string) ( $profile_rankings['baseline']['top_key'] ?? '' );
        $top_matches = 0;
        $unique_tops = array();
        foreach ( $profile_rankings as $profile ) {
            $top_key = (string) ( $profile['top_key'] ?? '' );
            if ( '' !== $top_key ) {
                $unique_tops[ $top_key ] = true;
            }
            if ( '' !== $baseline_top && $top_key === $baseline_top ) {
                ++$top_matches;
            }
        }
        $profile_count = count( self::WEIGHT_PROFILES );
        $top_stability = $profile_count > 0 ? round( ( $top_matches / $profile_count ) * 100.0, 1 ) : null;

        $baseline_top_score = $profile_rankings['baseline']['top_score'] ?? null;
        $baseline_runner_up_score = $profile_rankings['baseline']['runner_up_score'] ?? null;
        $margin = is_numeric( $baseline_top_score ) && is_numeric( $baseline_runner_up_score )
            ? round( (float) $baseline_top_score - (float) $baseline_runner_up_score, 1 )
            : null;

        $stats = array();
        foreach ( $keys as $key ) {
            $positions = array_values( $rank_positions[ $key ] ?? array() );
            if ( empty( $positions ) ) {
                continue;
            }
            $top_count = count( array_filter( $positions, static fn( int $rank ): bool => 1 === $rank ) );
            $stats[ $key ] = array(
                'profile_count' => count( $positions ),
                'top_rank_count' => $top_count,
                'top_rank_frequency_pct' => round( ( $top_count / count( $positions ) ) * 100.0, 1 ),
                'best_rank' => min( $positions ),
                'worst_rank' => max( $positions ),
                'rank_spread' => max( $positions ) - min( $positions ),
                'mean_rank' => round( array_sum( $positions ) / count( $positions ), 2 ),
                'ranks_by_profile' => $rank_positions[ $key ],
            );
        }

        return array(
            'summary' => array(
                'status' => 'available',
                'candidate_count' => $count,
                'weight_profile_count' => $profile_count,
                'baseline_top_key' => $baseline_top,
                'baseline_top_stability_count' => $top_matches,
                'top_stability_pct' => $top_stability,
                'robustness_level' => self::robustness_level( (float) $top_stability, count( $unique_tops ), $count ),
                'unique_top_count' => count( $unique_tops ),
                'baseline_top_margin' => $margin,
                'profiles' => $profile_rankings,
                'note' => self::text( 'summary', $language ),
            ),
            'item_stats' => $stats,
        );
    }

    private static function attach_item_robustness( array $items, array $stats ): array {
        foreach ( $items as $index => &$item ) {
            $key = self::item_key( $item, $index );
            $item['ranking_robustness'] = $stats[ $key ] ?? array(
                'profile_count' => 0,
                'top_rank_count' => 0,
                'top_rank_frequency_pct' => 0.0,
                'best_rank' => null,
                'worst_rank' => null,
                'rank_spread' => null,
                'mean_rank' => null,
                'ranks_by_profile' => array(),
            );
        }
        unset( $item );
        return $items;
    }

    private static function weighted_score( array $item, array $weights ): float {
        $values = array(
            'scenario_relevance' => self::numeric_or_null( $item['scenario_relevance'] ?? null ),
            'evidence_quality' => self::numeric_or_null( $item['evidence_quality']['quality_score'] ?? null ),
            'opponent_similarity' => self::numeric_or_null( $item['opponent_context_similarity']['similarity_score'] ?? null ),
        );

        $sum = 0.0;
        $available_weight = 0.0;
        foreach ( $weights as $key => $weight ) {
            if ( null === $values[ $key ] ) {
                continue;
            }
            $sum += self::clamp( (float) $values[ $key ] ) * (float) $weight;
            $available_weight += (float) $weight;
        }
        return $available_weight > 0.0 ? round( $sum / $available_weight, 4 ) : -1.0;
    }

    private static function compare_profile_rows( array $a, array $b ): int {
        $score = (float) $b['score'] <=> (float) $a['score'];
        if ( 0 !== $score ) {
            return $score;
        }
        $legacy = (float) $b['legacy_score'] <=> (float) $a['legacy_score'];
        if ( 0 !== $legacy ) {
            return $legacy;
        }
        $original = (int) $a['index'] <=> (int) $b['index'];
        if ( 0 !== $original ) {
            return $original;
        }
        return strcmp( (string) $a['key'], (string) $b['key'] );
    }

    private static function item_key( array $item, int $index ): string {
        $key = trim( (string) ( $item['group_key'] ?? '' ) );
        return '' !== $key ? $key : 'candidate-' . $index;
    }

    private static function empty_summary( string $language ): array {
        return array(
            'status' => 'unavailable',
            'candidate_count' => 0,
            'weight_profile_count' => count( self::WEIGHT_PROFILES ),
            'baseline_top_key' => null,
            'baseline_top_stability_count' => 0,
            'top_stability_pct' => null,
            'robustness_level' => 'unavailable',
            'unique_top_count' => 0,
            'baseline_top_margin' => null,
            'profiles' => array(),
            'note' => self::text( 'none', $language ),
        );
    }

    private static function robustness_level( float $top_stability, int $unique_top_count, int $candidate_count ): string {
        if ( $candidate_count <= 1 ) return 'single-candidate';
        if ( $top_stability >= 100.0 && $unique_top_count <= 1 ) return 'very-stable';
        if ( $top_stability >= 80.0 ) return 'stable';
        if ( $top_stability >= 60.0 ) return 'moderate';
        return 'sensitive';
    }

    private static function method( string $language, string $scope ): array {
        return array(
            'type' => 'deterministic-evidence-ranking-sensitivity-analysis',
            'scope' => $scope,
            'weight_profiles' => self::WEIGHT_PROFILES,
            'baseline_order_is_preserved' => true,
            'changes_suitability_scores' => false,
            'changes_tactical_scores' => false,
            'changes_recommendations' => false,
            'changes_active_plan' => false,
            'changes_historical_metrics' => false,
            'note' => self::text( 'method', $language ),
        );
    }

    private static function numeric_or_null( $value ): ?float {
        return is_numeric( $value ) ? (float) $value : null;
    }

    private static function clamp( float $value ): float {
        return min( 100.0, max( 0.0, $value ) );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'method' => 'v1.13 tests whether the v1.12 evidence order remains stable across five transparent alternative weight profiles. It is a sensitivity diagnostic only: the baseline v1.12 order and all tactical decisions remain unchanged.',
                'summary' => 'Top stability reports how often the baseline first-ranked precedent remains first across the configured weight profiles.',
                'catalog_summary' => 'Ranking robustness is calculated separately inside each Match Plan scenario; scenario order is never changed.',
                'none' => 'No evidence candidates are available for ranking-robustness analysis.',
            ),
            'es' => array(
                'method' => 'v1.13 comprueba si el orden de evidencia v1.12 se mantiene estable con cinco perfiles alternativos y transparentes de pesos. Es solo un diagnóstico de sensibilidad: el orden base v1.12 y todas las decisiones tácticas permanecen sin cambios.',
                'summary' => 'La estabilidad del primero indica con qué frecuencia el precedente mejor posicionado en la base sigue siendo primero entre los perfiles de pesos configurados.',
                'catalog_summary' => 'La robustez del ranking se calcula por separado dentro de cada escenario del Plan de Partido; el orden de escenarios nunca cambia.',
                'none' => 'No hay candidatos de evidencia disponibles para analizar la robustez del ranking.',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
