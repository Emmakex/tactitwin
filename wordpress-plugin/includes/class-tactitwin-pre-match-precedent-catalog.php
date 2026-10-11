<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Pre_Match_Precedent_Catalog {
    public static function build(
        array $library,
        array $scan,
        string $language = 'en',
        int $contexts_per_scenario = 3
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $contexts_per_scenario = max( 1, min( 10, $contexts_per_scenario ) );

        if ( 'context-matched-intervention-library' !== (string) ( $library['library_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'Pre-match precedent catalog requires a v1.7 intervention library.' );
        }
        if ( 'full-matchup-scan' !== (string) ( $scan['scenario_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'Pre-match precedent catalog requires a Full Matchup Scan.' );
        }

        $scenario_map = self::scenario_map( $scan );
        $grouped = array();
        foreach ( $scenario_map as $scenario_id => $scenario ) {
            $grouped[ $scenario_id ] = array();
        }

        foreach ( $library['groups'] ?? array() as $group ) {
            $dimensions = is_array( $group['dimensions'] ?? null ) ? $group['dimensions'] : array();
            $scenario_id = self::key( (string) ( $dimensions['scenario_id'] ?? '' ) );
            if ( '' === $scenario_id || ! isset( $scenario_map[ $scenario_id ] ) ) {
                continue;
            }

            $sample = is_array( $group['sample'] ?? null ) ? $group['sample'] : array();
            $grouped[ $scenario_id ][] = array(
                'group_key' => (string) ( $group['key'] ?? '' ),
                'scenario_id' => $scenario_id,
                'scenario_name' => (string) ( $scenario_map[ $scenario_id ]['scenario_name'] ?? $scenario_id ),
                'to_plan' => strtoupper( (string) ( $dimensions['to_plan'] ?? '' ) ),
                'to_mode' => (string) ( $dimensions['to_mode'] ?? '' ),
                'minute_band' => (string) ( $dimensions['minute_band'] ?? '' ),
                'score_state' => (string) ( $dimensions['score_state'] ?? '' ),
                'manpower_state' => (string) ( $dimensions['manpower_state'] ?? '' ),
                'sample' => $sample,
                'metrics' => is_array( $group['metrics'] ?? null ) ? $group['metrics'] : array(),
                'case_count' => count( $group['cases'] ?? array() ),
                'support_order_score' => self::support_order_score( $sample ),
            );
        }

        $scenarios = array();
        $context_group_count = 0;
        foreach ( $scenario_map as $scenario_id => $scenario ) {
            $contexts = $grouped[ $scenario_id ] ?? array();
            usort(
                $contexts,
                static function( array $a, array $b ): int {
                    $support = (float) ( $b['support_order_score'] ?? 0.0 ) <=> (float) ( $a['support_order_score'] ?? 0.0 );
                    if ( 0 !== $support ) {
                        return $support;
                    }
                    return strcmp( (string) ( $a['group_key'] ?? '' ), (string) ( $b['group_key'] ?? '' ) );
                }
            );

            $total_contexts = count( $contexts );
            $context_group_count += $total_contexts;
            $scenarios[] = array(
                'scenario_id' => $scenario_id,
                'scenario_name' => (string) ( $scenario['scenario_name'] ?? $scenario_id ),
                'current_scan' => array(
                    'rank' => (int) ( $scenario['rank'] ?? 0 ),
                    'risk_score' => round( (float) ( $scenario['risk_score'] ?? 0.0 ), 1 ),
                    'opportunity_score' => round( (float) ( $scenario['opportunity_score'] ?? 0.0 ), 1 ),
                    'relevance' => round( max(
                        (float) ( $scenario['risk_score'] ?? 0.0 ),
                        (float) ( $scenario['opportunity_score'] ?? 0.0 )
                    ), 1 ),
                ),
                'context_group_count' => $total_contexts,
                'returned_context_count' => min( $total_contexts, $contexts_per_scenario ),
                'contexts' => array_slice( $contexts, 0, $contexts_per_scenario ),
            );
        }

        usort(
            $scenarios,
            static function( array $a, array $b ): int {
                $a_rank = (int) ( $a['current_scan']['rank'] ?? PHP_INT_MAX );
                $b_rank = (int) ( $b['current_scan']['rank'] ?? PHP_INT_MAX );
                if ( $a_rank !== $b_rank ) {
                    return $a_rank <=> $b_rank;
                }
                return strcmp( (string) ( $a['scenario_id'] ?? '' ), (string) ( $b['scenario_id'] ?? '' ) );
            }
        );

        $with_history = count( array_filter(
            $scenarios,
            static fn( array $row ): bool => (int) ( $row['context_group_count'] ?? 0 ) > 0
        ) );

        return array(
            'catalog_id' => 'pre-match-precedent-catalog',
            'status' => $context_group_count > 0 ? 'available' : 'no-history',
            'language' => $language,
            'coverage' => array(
                'current_scenario_count' => count( $scenario_map ),
                'scenarios_with_history' => $with_history,
                'context_group_count' => $context_group_count,
                'contexts_per_scenario_limit' => $contexts_per_scenario,
            ),
            'scenarios' => $scenarios,
            'summary' => self::summary( $with_history, count( $scenario_map ), $context_group_count, $language ),
            'method' => array(
                'type' => 'scenario-indexed-context-preserving-pre-match-precedent-catalog',
                'context_dimensions_preserved' => array( 'destination plan', 'destination mode', 'minute band', 'score state', 'manpower state' ),
                'cross_context_metric_aggregation' => false,
                'reweights_tactical_scores' => false,
                'changes_recommendations' => false,
                'support_ordering_only' => true,
                'note' => self::text( 'method', $language ),
                'limitations' => array(
                    self::text( 'limit_causality', $language ),
                    self::text( 'limit_context', $language ),
                    self::text( 'limit_selection', $language ),
                ),
            ),
        );
    }

    private static function scenario_map( array $scan ): array {
        $map = array();
        foreach ( $scan['evidence']['scenario_ranking'] ?? array() as $row ) {
            $id = self::key( (string) ( $row['scenario_id'] ?? '' ) );
            if ( '' === $id ) {
                continue;
            }
            $map[ $id ] = array(
                'scenario_name' => (string) ( $row['scenario_name'] ?? $id ),
                'rank' => (int) ( $row['rank'] ?? 0 ),
                'risk_score' => (float) ( $row['risk_score'] ?? 0.0 ),
                'opportunity_score' => (float) ( $row['opportunity_score'] ?? 0.0 ),
            );
        }
        return $map;
    }

    private static function support_order_score( array $sample ): float {
        $maturity = array(
            'insufficient' => 0.0,
            'emerging' => 35.0,
            'developing' => 70.0,
            'established' => 100.0,
        );
        $maturity_score = (float) ( $maturity[ (string) ( $sample['maturity'] ?? 'insufficient' ) ] ?? 0.0 );
        $comparable = (int) ( $sample['context_comparable'] ?? 0 );
        $cleaner = (int) ( $sample['cleaner'] ?? 0 );
        $signal_count = 'cleaner' === (string) ( $sample['signal_basis'] ?? '' )
            ? $cleaner
            : $comparable;
        $volume = min( 100.0, max( 0, $signal_count ) * 16.67 );
        $cleaner_share = $comparable > 0 ? min( 100.0, ( $cleaner / $comparable ) * 100.0 ) : 0.0;
        return round( ( $maturity_score * 0.50 ) + ( $volume * 0.30 ) + ( $cleaner_share * 0.20 ), 1 );
    }

    private static function summary( int $with_history, int $scenario_count, int $groups, string $language ): array {
        if ( 0 === $groups ) {
            return array( self::text( 'summary_none', $language ) );
        }
        return array(
            sprintf( self::text( 'summary_scenarios', $language ), $with_history, $scenario_count ),
            sprintf( self::text( 'summary_groups', $language ), $groups ),
        );
    }

    private static function key( string $value ): string {
        $value = strtolower( trim( $value ) );
        $value = preg_replace( '/[^a-z0-9_-]+/', '-', $value );
        return trim( (string) $value, '-' );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'method' => 'v1.9 maps the v1.7 intervention library onto scenarios in the current Full Matchup Scan, but keeps every historical minute/score/manpower context separate. Context groups are ordered only to surface the better-supported evidence first.',
                'limit_causality' => 'Historical intervention groups remain observational and do not prove a coaching intervention caused the observed delta.',
                'limit_context' => 'Opponent quality, exact scoreline, personnel, fatigue and unobserved tactical structure can differ even inside the same stored context dimensions.',
                'limit_selection' => 'Interventions were selected by staff rather than randomized, so support ordering is not a treatment-effect ranking.',
                'summary_none' => 'No historical intervention contexts currently overlap the scenarios in this Match Plan.',
                'summary_scenarios' => '%d of %d current Match Plan scenarios have historical intervention context.',
                'summary_groups' => '%d historical context groups remain separated by minute, score and manpower state.',
            ),
            'es' => array(
                'method' => 'v1.9 relaciona la biblioteca de intervenciones v1.7 con los escenarios del Full Matchup Scan actual, pero mantiene separado cada contexto histórico de minuto, marcador y estado numérico. Los grupos se ordenan solo para mostrar primero la evidencia mejor respaldada.',
                'limit_causality' => 'Los grupos históricos siguen siendo observacionales y no demuestran que una intervención del cuerpo técnico causara el delta observado.',
                'limit_context' => 'La calidad del rival, marcador exacto, jugadores, fatiga y estructura táctica no observada pueden variar incluso dentro de las mismas dimensiones de contexto guardadas.',
                'limit_selection' => 'Las intervenciones fueron elegidas por el cuerpo técnico y no aleatorizadas; el orden de soporte no es un ranking de efecto causal.',
                'summary_none' => 'Actualmente no hay contextos históricos de intervención que coincidan con los escenarios de este Plan de Partido.',
                'summary_scenarios' => '%d de %d escenarios del Plan de Partido actual tienen contexto histórico de intervención.',
                'summary_groups' => '%d grupos históricos permanecen separados por minuto, marcador y estado numérico.',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
