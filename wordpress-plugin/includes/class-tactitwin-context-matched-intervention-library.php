<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Context_Matched_Intervention_Library {
    public static function build(
        array $cases,
        string $team_id,
        string $language = 'en',
        string $scenario_filter = ''
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $team_id = trim( $team_id );
        $scenario_filter = self::key( $scenario_filter );
        if ( '' === $team_id ) {
            throw new InvalidArgumentException( 'An intervention library requires a team identifier.' );
        }

        $groups = array();
        $eligible = 0;
        $excluded = 0;

        foreach ( $cases as $case ) {
            $transition = $case['transition'] ?? array();
            if ( ! is_array( $transition ) ) {
                continue;
            }

            $scenario = self::key( (string) ( $transition['to_scenario'] ?? '' ) );
            if ( '' === $scenario ) {
                $scenario = 'generic-transition';
            }
            if ( '' !== $scenario_filter && $scenario_filter !== $scenario ) {
                continue;
            }

            $context = $transition['context'] ?? array();
            $interpretability = (string) ( $context['interpretability'] ?? 'insufficient' );
            $comparable = true === ( $context['context_comparable'] ?? false );
            $recorded = $context['recorded_snapshot_change'] ?? array();
            $snapshot_context = is_array( $recorded['after'] ?? null )
                ? $recorded['after']
                : ( is_array( $recorded['before'] ?? null ) ? $recorded['before'] : array() );

            $dimensions = array(
                'scenario_id' => $scenario,
                'to_plan' => strtoupper( (string) ( $transition['to_plan'] ?? '' ) ),
                'to_mode' => self::key( (string) ( $transition['to_mode'] ?? '' ) ),
                'minute_band' => self::minute_band( (int) ( $transition['minute'] ?? 0 ) ),
                'score_state' => self::score_state( $snapshot_context ),
                'manpower_state' => self::manpower_state( $snapshot_context ),
            );
            $key = implode( '|', array_values( $dimensions ) );

            if ( ! isset( $groups[ $key ] ) ) {
                $groups[ $key ] = array(
                    'key' => $key,
                    'dimensions' => $dimensions,
                    'cases' => array(),
                    'cleaner_cases' => array(),
                    'comparable_cases' => array(),
                    'excluded_cases' => array(),
                );
            }

            $normalized = self::normalize_case( $case, $transition, $interpretability, $comparable );
            $groups[ $key ]['cases'][] = $normalized;

            if ( 'cleaner' === $interpretability && $comparable ) {
                $groups[ $key ]['cleaner_cases'][] = $normalized;
                $groups[ $key ]['comparable_cases'][] = $normalized;
                ++$eligible;
            } elseif ( 'confounded' === $interpretability && $comparable ) {
                $groups[ $key ]['comparable_cases'][] = $normalized;
                ++$eligible;
            } else {
                $groups[ $key ]['excluded_cases'][] = $normalized;
                ++$excluded;
            }
        }

        $result_groups = array();
        foreach ( $groups as $group ) {
            $result_groups[] = self::finalize_group( $group, $language );
        }

        usort(
            $result_groups,
            static function( array $a, array $b ): int {
                $cleaner = (int) ( $b['sample']['cleaner'] ?? 0 ) <=> (int) ( $a['sample']['cleaner'] ?? 0 );
                if ( 0 !== $cleaner ) {
                    return $cleaner;
                }
                $comparable = (int) ( $b['sample']['context_comparable'] ?? 0 ) <=> (int) ( $a['sample']['context_comparable'] ?? 0 );
                if ( 0 !== $comparable ) {
                    return $comparable;
                }
                return strcmp( (string) ( $a['key'] ?? '' ), (string) ( $b['key'] ?? '' ) );
            }
        );

        return array(
            'library_id' => 'context-matched-intervention-library',
            'team_source_id' => $team_id,
            'language' => $language,
            'scenario_filter' => $scenario_filter,
            'coverage' => array(
                'group_count' => count( $result_groups ),
                'context_comparable_interventions' => $eligible,
                'excluded_interventions' => $excluded,
            ),
            'groups' => $result_groups,
            'summary' => self::summary( $result_groups, $eligible, $excluded, $language ),
            'method' => array(
                'type' => 'descriptive-context-matched-cross-match-intervention-library',
                'matching_dimensions' => array( 'scenario', 'destination plan', 'destination mode', 'minute band', 'score state', 'manpower state' ),
                'primary_sample' => 'cleaner v1.6 comparisons only',
                'fallback_sample' => 'cleaner + substitution-confounded comparisons when fewer than two cleaner cases exist',
                'excluded' => 'highly-confounded and insufficient comparisons are counted but excluded from aggregate deltas',
                'note' => self::text( 'method', $language ),
                'limitations' => array(
                    self::text( 'limit_causality', $language ),
                    self::text( 'limit_selection', $language ),
                    self::text( 'limit_context', $language ),
                    self::text( 'limit_sample', $language ),
                ),
            ),
        );
    }

    private static function normalize_case( array $case, array $transition, string $interpretability, bool $comparable ): array {
        return array(
            'session_uuid' => (string) ( $case['session_uuid'] ?? '' ),
            'source_match_id' => (string) ( $case['source_match_id'] ?? '' ),
            'opponent_source_id' => (string) ( $case['opponent_source_id'] ?? '' ),
            'minute' => (int) ( $transition['minute'] ?? 0 ),
            'from_plan' => strtoupper( (string) ( $transition['from_plan'] ?? '' ) ),
            'to_plan' => strtoupper( (string) ( $transition['to_plan'] ?? '' ) ),
            'to_mode' => (string) ( $transition['to_mode'] ?? '' ),
            'to_scenario' => (string) ( $transition['to_scenario'] ?? '' ),
            'interpretability' => $interpretability,
            'context_comparable' => $comparable,
            'focus_metrics' => is_array( $transition['focus']['metrics'] ?? null ) ? $transition['focus']['metrics'] : array(),
            'warnings' => is_array( $transition['context']['warnings'] ?? null ) ? $transition['context']['warnings'] : array(),
        );
    }

    private static function finalize_group( array $group, string $language ): array {
        $cleaner = $group['cleaner_cases'] ?? array();
        $comparable = $group['comparable_cases'] ?? array();
        $excluded = $group['excluded_cases'] ?? array();

        if ( count( $cleaner ) >= 2 ) {
            $basis = 'cleaner';
            $signal_cases = $cleaner;
        } elseif ( count( $comparable ) >= 2 ) {
            $basis = 'context-comparable';
            $signal_cases = $comparable;
        } else {
            $basis = 'insufficient';
            $signal_cases = $cleaner ?: $comparable;
        }

        $matches = array();
        foreach ( $group['cases'] ?? array() as $case ) {
            $match_id = (string) ( $case['source_match_id'] ?? '' );
            if ( '' !== $match_id ) {
                $matches[ $match_id ] = true;
            }
        }

        return array(
            'key' => (string) $group['key'],
            'dimensions' => $group['dimensions'],
            'sample' => array(
                'total' => count( $group['cases'] ?? array() ),
                'matches' => count( $matches ),
                'cleaner' => count( $cleaner ),
                'context_comparable' => count( $comparable ),
                'excluded' => count( $excluded ),
                'signal_basis' => $basis,
                'signal_basis_label' => self::text( 'basis_' . str_replace( '-', '_', $basis ), $language ),
                'maturity' => self::maturity( count( $signal_cases ) ),
                'maturity_label' => self::text( 'maturity_' . self::maturity( count( $signal_cases ) ), $language ),
            ),
            'metrics' => self::aggregate_metrics( $signal_cases ),
            'cases' => $group['cases'],
        );
    }

    private static function aggregate_metrics( array $cases ): array {
        $values = array();
        foreach ( $cases as $case ) {
            foreach ( $case['focus_metrics'] ?? array() as $metric ) {
                $key = (string) ( $metric['metric'] ?? '' );
                $delta = $metric['delta'] ?? null;
                if ( '' === $key || ! is_numeric( $delta ) ) {
                    continue;
                }
                $values[ $key ][] = (float) $delta;
            }
        }

        $result = array();
        foreach ( $values as $metric => $deltas ) {
            sort( $deltas, SORT_NUMERIC );
            $count = count( $deltas );
            $up = 0;
            $down = 0;
            $flat = 0;
            foreach ( $deltas as $delta ) {
                if ( $delta > 0.01 ) ++$up;
                elseif ( $delta < -0.01 ) ++$down;
                else ++$flat;
            }
            $result[] = array(
                'metric' => $metric,
                'observations' => $count,
                'median_delta' => self::median( $deltas ),
                'mean_delta' => 0 === $count ? null : round( array_sum( $deltas ) / $count, 2 ),
                'min_delta' => 0 === $count ? null : round( (float) min( $deltas ), 2 ),
                'max_delta' => 0 === $count ? null : round( (float) max( $deltas ), 2 ),
                'up' => $up,
                'down' => $down,
                'flat' => $flat,
                'up_pct' => self::pct( $up, $count ),
                'down_pct' => self::pct( $down, $count ),
                'direction' => self::direction( $up, $down, $count ),
            );
        }
        return $result;
    }

    private static function minute_band( int $minute ): string {
        if ( $minute <= 30 ) return '01-30';
        if ( $minute <= 60 ) return '31-60';
        if ( $minute <= 90 ) return '61-90';
        return '91-120';
    }

    private static function score_state( array $context ): string {
        if ( empty( $context ) ) return 'unknown';
        $ours = (int) ( $context['our_score'] ?? 0 );
        $theirs = (int) ( $context['opponent_score'] ?? 0 );
        if ( $ours > $theirs ) return 'leading';
        if ( $ours < $theirs ) return 'trailing';
        return 'drawing';
    }

    private static function manpower_state( array $context ): string {
        if ( empty( $context ) ) return 'unknown';
        $ours = (int) ( $context['our_players'] ?? 11 );
        $theirs = (int) ( $context['opponent_players'] ?? 11 );
        if ( $ours > $theirs ) return 'superior';
        if ( $ours < $theirs ) return 'inferior';
        return 'equal';
    }

    private static function maturity( int $count ): string {
        if ( $count >= 6 ) return 'established';
        if ( $count >= 4 ) return 'developing';
        if ( $count >= 2 ) return 'emerging';
        return 'insufficient';
    }

    private static function median( array $values ): ?float {
        $count = count( $values );
        if ( 0 === $count ) return null;
        $middle = intdiv( $count, 2 );
        if ( 1 === $count % 2 ) return round( (float) $values[ $middle ], 2 );
        return round( ( (float) $values[ $middle - 1 ] + (float) $values[ $middle ] ) / 2.0, 2 );
    }

    private static function pct( int $value, int $total ): float {
        return $total > 0 ? round( ( $value / $total ) * 100.0, 1 ) : 0.0;
    }

    private static function direction( int $up, int $down, int $count ): string {
        if ( $count < 2 ) return 'insufficient';
        if ( $up / $count >= 0.6 ) return 'mostly-up';
        if ( $down / $count >= 0.6 ) return 'mostly-down';
        return 'mixed';
    }

    private static function key( string $value ): string {
        $value = strtolower( trim( $value ) );
        $value = preg_replace( '/[^a-z0-9_-]+/', '-', $value );
        $value = preg_replace( '/-+/', '-', (string) $value );
        return trim( (string) $value, '-' );
    }

    private static function summary( array $groups, int $eligible, int $excluded, string $language ): array {
        $mature = 0;
        foreach ( $groups as $group ) {
            if ( in_array( (string) ( $group['sample']['maturity'] ?? '' ), array( 'developing', 'established' ), true ) ) {
                ++$mature;
            }
        }
        return array(
            sprintf( self::text( 'summary_groups', $language ), count( $groups ), $eligible, $excluded ),
            sprintf( self::text( 'summary_mature', $language ), $mature ),
        );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'method' => 'v1.7 groups historical tactical transitions only when scenario, destination plan/mode, minute band, score state and manpower state match. Aggregate deltas remain descriptive associations, not causal effects.',
                'limit_causality' => 'Repeated association across matched cases does not prove that the intervention caused the observed change.',
                'limit_selection' => 'Coaches choose when to intervene, so historical intervention cases are not randomized.',
                'limit_context' => 'Opponent quality, fatigue, injuries, substitutions and unobserved tactical changes can still differ inside a matched group.',
                'limit_sample' => 'Small groups are unstable; maturity labels describe sample volume only, not statistical certainty.',
                'basis_cleaner' => 'Cleaner comparisons only',
                'basis_context_comparable' => 'Cleaner + personnel-confounded comparisons',
                'basis_insufficient' => 'Insufficient matched sample',
                'maturity_established' => 'Established sample',
                'maturity_developing' => 'Developing sample',
                'maturity_emerging' => 'Emerging sample',
                'maturity_insufficient' => 'Insufficient sample',
                'summary_groups' => '%d context-matched groups built from %d comparable interventions; %d interventions were excluded from aggregate deltas.',
                'summary_mature' => '%d groups currently have at least a developing sample (4+ signal cases).',
            ),
            'es' => array(
                'method' => 'v1.7 agrupa transiciones tácticas históricas solo cuando coinciden escenario, plan/modo destino, franja temporal, estado del marcador y estado numérico. Los deltas agregados siguen siendo asociaciones descriptivas, no efectos causales.',
                'limit_causality' => 'Una asociación repetida entre casos comparables no demuestra que la intervención haya causado el cambio observado.',
                'limit_selection' => 'El cuerpo técnico decide cuándo intervenir, por lo que los casos históricos no están aleatorizados.',
                'limit_context' => 'La calidad del rival, fatiga, lesiones, sustituciones y cambios tácticos no observados todavía pueden diferir dentro de un grupo.',
                'limit_sample' => 'Los grupos pequeños son inestables; la madurez describe volumen de muestra, no certeza estadística.',
                'basis_cleaner' => 'Solo comparaciones más limpias',
                'basis_context_comparable' => 'Comparaciones limpias + condicionadas por sustituciones',
                'basis_insufficient' => 'Muestra comparable insuficiente',
                'maturity_established' => 'Muestra consolidada',
                'maturity_developing' => 'Muestra en desarrollo',
                'maturity_emerging' => 'Muestra emergente',
                'maturity_insufficient' => 'Muestra insuficiente',
                'summary_groups' => '%d grupos de contexto comparable construidos con %d intervenciones comparables; %d intervenciones quedaron fuera de los deltas agregados.',
                'summary_mature' => '%d grupos tienen actualmente al menos una muestra en desarrollo (4+ casos de señal).',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
