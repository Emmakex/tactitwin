<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Evidence_Suitability_Ranker {
    private const WEIGHTS = array(
        'scenario_relevance' => 0.45,
        'evidence_quality' => 0.35,
        'opponent_similarity' => 0.20,
    );

    public static function retrieval( array $retrieval, string $language = 'en', int $limit = 5 ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $limit = max( 1, min( 10, $limit ) );

        if ( 'historical-precedent-retrieval' !== (string) ( $retrieval['precedent_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'Evidence suitability ranking requires historical precedent retrieval.' );
        }

        $items = is_array( $retrieval['items'] ?? null ) ? $retrieval['items'] : array();
        foreach ( $items as $index => &$item ) {
            $item['original_retrieval_rank'] = $index + 1;
            $item['evidence_suitability'] = self::score(
                $item['scenario_relevance'] ?? null,
                $item['evidence_quality']['quality_score'] ?? null,
                $item['opponent_context_similarity']['similarity_score'] ?? null,
                $language
            );
        }
        unset( $item );

        usort( $items, array( self::class, 'compare_retrieval_items' ) );
        foreach ( $items as $index => &$item ) {
            $item['suitability_rank'] = $index + 1;
            $item['ranking_changed'] = (int) ( $item['original_retrieval_rank'] ?? 0 ) !== $index + 1;
        }
        unset( $item );

        $candidate_count = count( $items );
        $retrieval['items'] = array_slice( $items, 0, $limit );
        $retrieval['returned_group_count'] = count( $retrieval['items'] );
        $retrieval['suitability_summary'] = self::summary( $retrieval['items'], $candidate_count, $language );
        if ( ! isset( $retrieval['method'] ) || ! is_array( $retrieval['method'] ) ) {
            $retrieval['method'] = array();
        }
        $retrieval['method']['evidence_suitability_ranking'] = self::method( $language, 'exact-context-retrieval-shortlist' );
        return $retrieval;
    }

    public static function catalog( array $catalog, string $language = 'en', int $contexts_per_scenario = 3 ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $contexts_per_scenario = max( 1, min( 10, $contexts_per_scenario ) );

        if ( 'pre-match-precedent-catalog' !== (string) ( $catalog['catalog_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'Evidence suitability catalog ranking requires a pre-match precedent catalog.' );
        }

        $ranked_count = 0;
        if ( ! empty( $catalog['scenarios'] ) && is_array( $catalog['scenarios'] ) ) {
            foreach ( $catalog['scenarios'] as &$scenario ) {
                $relevance = $scenario['current_scan']['relevance'] ?? null;
                $contexts = is_array( $scenario['contexts'] ?? null ) ? $scenario['contexts'] : array();
                foreach ( $contexts as $index => &$context ) {
                    $context['original_support_rank'] = $index + 1;
                    $context['evidence_suitability'] = self::score(
                        $relevance,
                        $context['evidence_quality']['quality_score'] ?? null,
                        $context['opponent_context_similarity']['similarity_score'] ?? null,
                        $language
                    );
                }
                unset( $context );

                usort( $contexts, array( self::class, 'compare_catalog_contexts' ) );
                foreach ( $contexts as $index => &$context ) {
                    $context['suitability_rank'] = $index + 1;
                    $context['ranking_changed'] = (int) ( $context['original_support_rank'] ?? 0 ) !== $index + 1;
                }
                unset( $context );

                $ranked_count += count( $contexts );
                $scenario['returned_context_count'] = min( count( $contexts ), $contexts_per_scenario );
                $scenario['contexts'] = array_slice( $contexts, 0, $contexts_per_scenario );
            }
            unset( $scenario );
        }

        if ( ! isset( $catalog['coverage'] ) || ! is_array( $catalog['coverage'] ) ) {
            $catalog['coverage'] = array();
        }
        $catalog['coverage']['contexts_per_scenario_limit'] = $contexts_per_scenario;
        $catalog['suitability_summary'] = array(
            'ranked_context_candidates' => $ranked_count,
            'note' => self::text( 'catalog_summary', $language ),
        );
        if ( ! isset( $catalog['method'] ) || ! is_array( $catalog['method'] ) ) {
            $catalog['method'] = array();
        }
        $catalog['method']['evidence_suitability_ranking'] = self::method( $language, 'within-scenario-context-shortlist' );
        return $catalog;
    }

    public static function score( $scenario_relevance, $evidence_quality, $opponent_similarity, string $language = 'en' ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $values = array(
            'scenario_relevance' => self::numeric_or_null( $scenario_relevance ),
            'evidence_quality' => self::numeric_or_null( $evidence_quality ),
            'opponent_similarity' => self::numeric_or_null( $opponent_similarity ),
        );

        $weighted_sum = 0.0;
        $available_weight = 0.0;
        $components = array();
        $missing = array();
        foreach ( self::WEIGHTS as $key => $weight ) {
            $value = $values[ $key ];
            if ( null === $value ) {
                $missing[] = $key;
                $components[ $key ] = array(
                    'status' => 'unavailable',
                    'score' => null,
                    'base_weight' => $weight,
                );
                continue;
            }
            $value = self::clamp( $value );
            $weighted_sum += $value * $weight;
            $available_weight += $weight;
            $components[ $key ] = array(
                'status' => 'available',
                'score' => round( $value, 1 ),
                'base_weight' => $weight,
            );
        }

        $score = $available_weight > 0.0 ? round( $weighted_sum / $available_weight, 1 ) : null;
        foreach ( $components as $key => &$component ) {
            $component['effective_weight'] = 'available' === $component['status'] && $available_weight > 0.0
                ? round( (float) $component['base_weight'] / $available_weight, 4 )
                : 0.0;
        }
        unset( $component );

        return array(
            'suitability_score' => $score,
            'suitability_level' => null === $score ? 'unavailable' : self::level( $score ),
            'suitability_label' => null === $score
                ? self::text( 'unavailable', $language )
                : self::text( 'level_' . str_replace( '-', '_', self::level( $score ) ), $language ),
            'components' => $components,
            'base_weights' => self::WEIGHTS,
            'available_weight' => round( $available_weight, 2 ),
            'missing_components' => $missing,
            'method' => array(
                'type' => 'transparent-evidence-suitability-ranking',
                'exact_context_required_upstream' => true,
                'renormalizes_missing_components' => true,
                'changes_tactical_scores' => false,
                'changes_recommendations' => false,
                'changes_active_plan' => false,
                'changes_historical_metrics' => false,
                'note' => self::text( 'method', $language ),
            ),
        );
    }

    private static function compare_retrieval_items( array $a, array $b ): int {
        $suitability = self::score_value( $b ) <=> self::score_value( $a );
        if ( 0 !== $suitability ) {
            return $suitability;
        }
        $retrieval = (float) ( $b['retrieval_score'] ?? 0.0 ) <=> (float) ( $a['retrieval_score'] ?? 0.0 );
        if ( 0 !== $retrieval ) {
            return $retrieval;
        }
        $original = (int) ( $a['original_retrieval_rank'] ?? PHP_INT_MAX ) <=> (int) ( $b['original_retrieval_rank'] ?? PHP_INT_MAX );
        if ( 0 !== $original ) {
            return $original;
        }
        return strcmp( (string) ( $a['group_key'] ?? '' ), (string) ( $b['group_key'] ?? '' ) );
    }

    private static function compare_catalog_contexts( array $a, array $b ): int {
        $suitability = self::score_value( $b ) <=> self::score_value( $a );
        if ( 0 !== $suitability ) {
            return $suitability;
        }
        $support = (float) ( $b['support_order_score'] ?? 0.0 ) <=> (float) ( $a['support_order_score'] ?? 0.0 );
        if ( 0 !== $support ) {
            return $support;
        }
        $original = (int) ( $a['original_support_rank'] ?? PHP_INT_MAX ) <=> (int) ( $b['original_support_rank'] ?? PHP_INT_MAX );
        if ( 0 !== $original ) {
            return $original;
        }
        return strcmp( (string) ( $a['group_key'] ?? '' ), (string) ( $b['group_key'] ?? '' ) );
    }

    private static function score_value( array $item ): float {
        $score = $item['evidence_suitability']['suitability_score'] ?? null;
        return is_numeric( $score ) ? (float) $score : -1.0;
    }

    private static function summary( array $items, int $candidate_count, string $language ): array {
        $scores = array();
        foreach ( $items as $item ) {
            $score = $item['evidence_suitability']['suitability_score'] ?? null;
            if ( is_numeric( $score ) ) {
                $scores[] = (float) $score;
            }
        }
        return array(
            'candidate_count' => $candidate_count,
            'returned_count' => count( $items ),
            'mean_returned_suitability' => empty( $scores ) ? null : round( array_sum( $scores ) / count( $scores ), 1 ),
            'note' => self::text( 'retrieval_summary', $language ),
        );
    }

    private static function method( string $language, string $scope ): array {
        return array(
            'type' => 'transparent-evidence-suitability-ranking',
            'scope' => $scope,
            'base_weights' => self::WEIGHTS,
            'exact_context_only' => true,
            'fuzzy_context_fallback' => false,
            'candidate_pool_limit' => 10,
            'changes_tactical_scores' => false,
            'changes_recommendations' => false,
            'changes_active_plan' => false,
            'changes_historical_metrics' => false,
            'may_reorder_historical_evidence' => true,
            'note' => self::text( 'method', $language ),
        );
    }

    private static function numeric_or_null( $value ): ?float {
        return is_numeric( $value ) ? (float) $value : null;
    }

    private static function clamp( float $value ): float {
        return min( 100.0, max( 0.0, $value ) );
    }

    private static function level( float $score ): string {
        if ( $score >= 80.0 ) return 'very-high';
        if ( $score >= 65.0 ) return 'high';
        if ( $score >= 50.0 ) return 'medium';
        if ( $score >= 35.0 ) return 'low';
        return 'very-low';
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'level_very_high' => 'Very high', 'level_high' => 'High', 'level_medium' => 'Medium', 'level_low' => 'Low', 'level_very_low' => 'Very low', 'unavailable' => 'Unavailable',
                'method' => 'v1.12 ranks already exact-context historical evidence using current scenario relevance, v1.10 evidence quality and v1.11 opponent similarity. Missing components are excluded and remaining weights are renormalized. The ranking changes evidence order only, not tactical decisions.',
                'retrieval_summary' => 'Returned exact-context precedents are ordered by evidence suitability after quality and opponent-similarity enrichment.',
                'catalog_summary' => 'Historical contexts are suitability-ranked only within their current Match Plan scenario; scenario order remains unchanged.',
            ),
            'es' => array(
                'level_very_high' => 'Muy alta', 'level_high' => 'Alta', 'level_medium' => 'Media', 'level_low' => 'Baja', 'level_very_low' => 'Muy baja', 'unavailable' => 'No disponible',
                'method' => 'v1.12 ordena evidencia histórica que ya coincide en contexto exacto usando relevancia del escenario actual, calidad de evidencia v1.10 y similitud del rival v1.11. Los componentes ausentes se excluyen y los pesos restantes se renormalizan. El ranking solo cambia el orden de la evidencia, no las decisiones tácticas.',
                'retrieval_summary' => 'Los precedentes devueltos de contexto exacto se ordenan por idoneidad de evidencia después de enriquecer calidad y similitud del rival.',
                'catalog_summary' => 'Los contextos históricos se ordenan por idoneidad solo dentro de su escenario actual del Plan de Partido; el orden de escenarios no cambia.',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
