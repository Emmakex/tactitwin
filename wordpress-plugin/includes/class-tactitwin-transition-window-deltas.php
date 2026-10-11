<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Transition_Window_Deltas {
    private const DEFAULT_WINDOW_MINUTES = 10;

    public static function evaluate(
        array $session,
        array $snapshots,
        array $events,
        array $match_summary,
        string $language = 'en',
        int $window_minutes = self::DEFAULT_WINDOW_MINUTES
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $window_minutes = max( 5, min( 20, $window_minutes ) );
        $team_id = (string) ( $session['team_source_id'] ?? '' );
        $opponent_id = (string) ( $session['opponent_source_id'] ?? '' );
        $match_id = (string) ( $match_summary['match']['source_match_id'] ?? '' );

        if ( '' === $team_id || '' === $opponent_id || $team_id === $opponent_id ) {
            throw new InvalidArgumentException( 'Transition-window evidence requires a valid team/opponent pairing.' );
        }
        if ( '' === $match_id || empty( $snapshots ) || empty( $events ) ) {
            throw new InvalidArgumentException( 'Transition-window evidence requires linked match events and live-session snapshots.' );
        }
        if ( ! isset( $match_summary['teams'][ $team_id ], $match_summary['teams'][ $opponent_id ] ) ) {
            throw new InvalidArgumentException( 'The linked match does not contain the live-session team/opponent pairing.' );
        }

        $timeline = TactiTwin_Live_Timeline::summarize( $snapshots );
        $transitions = $timeline['transitions'] ?? array();
        $max_event_minute = self::max_event_minute( $events );
        $rows = array();

        foreach ( $transitions as $index => $transition ) {
            $minute = max( 0, (int) ( $transition['minute'] ?? 0 ) );
            $before_from = max( 0, $minute - $window_minutes );
            $before_to = $minute;
            $after_from = $minute;
            $after_to = min( $max_event_minute + 1, $minute + $window_minutes );

            $before_events = self::slice( $events, $before_from, $before_to );
            $after_events = self::slice( $events, $after_from, $after_to );
            $before_duration = max( 0, $before_to - $before_from );
            $after_duration = max( 0, $after_to - $after_from );
            $before = self::window_metrics( $before_events, $team_id, $opponent_id, $before_duration );
            $after = self::window_metrics( $after_events, $team_id, $opponent_id, $after_duration );
            $coverage = self::coverage(
                $before_duration,
                $after_duration,
                count( $before_events ),
                count( $after_events ),
                $window_minutes,
                $language
            );
            $deltas = self::deltas( $before['comparison'], $after['comparison'] );
            $focus = self::focus_metrics(
                (string) ( $transition['to_scenario'] ?? '' ),
                $deltas,
                $before['comparison'],
                $after['comparison'],
                $language
            );

            $rows[] = array(
                'transition_index' => $index + 1,
                'minute' => $minute,
                'from_plan' => strtoupper( (string) ( $transition['from_plan'] ?? '' ) ),
                'to_plan' => strtoupper( (string) ( $transition['to_plan'] ?? '' ) ),
                'from_mode' => (string) ( $transition['from_mode'] ?? '' ),
                'to_mode' => (string) ( $transition['to_mode'] ?? '' ),
                'from_scenario' => (string) ( $transition['from_scenario'] ?? '' ),
                'to_scenario' => (string) ( $transition['to_scenario'] ?? '' ),
                'snapshot_id' => (int) ( $transition['snapshot_id'] ?? 0 ),
                'window_minutes_requested' => $window_minutes,
                'before' => array(
                    'from_minute' => $before_from,
                    'to_minute' => $before_to,
                    'boundary' => '[from,to)',
                    'duration_minutes' => $before_duration,
                    'event_count' => count( $before_events ),
                    'metrics' => $before['comparison'],
                ),
                'after' => array(
                    'from_minute' => $after_from,
                    'to_minute' => $after_to,
                    'boundary' => '[from,to)',
                    'duration_minutes' => $after_duration,
                    'event_count' => count( $after_events ),
                    'metrics' => $after['comparison'],
                ),
                'coverage' => $coverage,
                'deltas' => $deltas,
                'focus' => $focus,
            );
        }

        return array(
            'evidence_id' => 'transition-window-deltas',
            'language' => $language,
            'source_match_id' => $match_id,
            'coverage' => array(
                'transition_count' => count( $rows ),
                'event_max_minute' => $max_event_minute,
                'default_window_minutes' => $window_minutes,
                'time_resolution' => 'minute',
            ),
            'transitions' => $rows,
            'summary' => self::summary( $rows, $language ),
            'method' => array(
                'type' => 'descriptive-symmetric-before-after-transition-windows',
                'note' => self::text( 'method', $language ),
                'boundary_rule' => self::text( 'boundary', $language ),
                'limitations' => array(
                    self::text( 'limit_causality', $language ),
                    self::text( 'limit_context', $language ),
                    self::text( 'limit_snapshot', $language ),
                    self::text( 'limit_transition_boundary', $language ),
                    self::text( 'limit_tracking', $language ),
                ),
            ),
        );
    }

    private static function slice( array $events, int $from, int $to ): array {
        return array_values(
            array_filter(
                $events,
                static function( array $event ) use ( $from, $to ): bool {
                    $minute = (int) ( $event['minute'] ?? 0 );
                    return $minute >= $from && $minute < $to;
                }
            )
        );
    }

    private static function window_metrics( array $events, string $team_id, string $opponent_id, int $duration ): array {
        $match = TactiTwin_Match_Metrics::calculate( $events );
        $transitions = TactiTwin_Transition_Metrics::calculate( $events );
        $team = self::merge( $match[ $team_id ] ?? array(), $transitions[ $team_id ] ?? array() );
        $opponent = self::merge( $match[ $opponent_id ] ?? array(), $transitions[ $opponent_id ] ?? array() );

        return array(
            'comparison' => array(
                'our_progressive_actions_per90' => self::per90( (int) ( $team['progressive_actions'] ?? 0 ), $duration ),
                'our_final_third_entries_per90' => self::per90( (int) ( $team['final_third_entries'] ?? 0 ), $duration ),
                'our_box_entries_per90' => self::per90( (int) ( $team['box_entries'] ?? 0 ), $duration ),
                'our_shots_per90' => self::per90( (int) ( $team['shots'] ?? 0 ), $duration ),
                'our_xg_per90' => self::rate90( (float) ( $team['xg'] ?? 0.0 ), $duration ),
                'our_own_third_turnovers_per90' => self::per90( (int) ( $team['own_third_turnovers'] ?? 0 ), $duration ),
                'our_build_up_escape_rate_pct' => round( (float) ( $team['build_up_escape_rate'] ?? 0.0 ), 1 ),
                'opponent_high_pressures_per90' => self::per90( (int) ( $opponent['high_pressures'] ?? 0 ), $duration ),
                'opponent_high_pressure_share_pct' => round( (float) ( $opponent['high_pressure_share_pct'] ?? 0.0 ), 1 ),
                'opponent_final_third_actions_per90' => self::per90( (int) ( $opponent['final_third_actions'] ?? 0 ), $duration ),
                'opponent_shots_per90' => self::per90( (int) ( $opponent['shots'] ?? 0 ), $duration ),
                'opponent_xg_per90' => self::rate90( (float) ( $opponent['xg'] ?? 0.0 ), $duration ),
                'transition_turnovers_per90' => self::per90( (int) ( $team['transition_turnovers'] ?? 0 ), $duration ),
                'transition_progressions_conceded_per90' => self::per90( (int) ( $team['opponent_progressions_within_10s'] ?? 0 ), $duration ),
                'transition_shots_conceded_per90' => self::per90( (int) ( $team['opponent_shots_within_10s'] ?? 0 ), $duration ),
                'transition_xg_conceded_per90' => self::rate90( (float) ( $team['xg_conceded_within_10s'] ?? 0.0 ), $duration ),
                'our_final_third_channel_share' => self::channels( $team['final_third_channel_share'] ?? array() ),
                'opponent_final_third_channel_share' => self::channels( $opponent['final_third_channel_share'] ?? array() ),
            ),
        );
    }

    private static function merge( array $match, array $transition ): array {
        unset( $transition['turnovers_by_zone'] );
        return array_merge( $match, $transition );
    }

    private static function deltas( array $before, array $after ): array {
        $out = array();
        foreach ( $after as $key => $value ) {
            if ( is_numeric( $value ) && isset( $before[ $key ] ) && is_numeric( $before[ $key ] ) ) {
                $out[ $key ] = round( (float) $value - (float) $before[ $key ], 2 );
            }
        }
        foreach ( array( 'our_final_third_channel_share', 'opponent_final_third_channel_share' ) as $channel_key ) {
            $out[ $channel_key ] = array();
            foreach ( array( 'left', 'central', 'right' ) as $channel ) {
                $out[ $channel_key ][ $channel ] = round(
                    (float) ( $after[ $channel_key ][ $channel ] ?? 0.0 ) - (float) ( $before[ $channel_key ][ $channel ] ?? 0.0 ),
                    1
                );
            }
        }
        return $out;
    }

    private static function focus_metrics( string $scenario, array $deltas, array $before, array $after, string $language ): array {
        $map = array(
            'build-up-vs-high-press' => array( 'our_own_third_turnovers_per90', 'our_build_up_escape_rate_pct', 'our_progressive_actions_per90', 'opponent_high_pressures_per90' ),
            'attack-vs-low-block' => array( 'our_box_entries_per90', 'our_shots_per90', 'our_xg_per90', 'our_final_third_entries_per90' ),
            'defensive-transition-after-loss' => array( 'transition_progressions_conceded_per90', 'transition_shots_conceded_per90', 'transition_xg_conceded_per90', 'transition_turnovers_per90' ),
            'opponent-side-bias' => array( 'opponent_final_third_actions_per90' ),
            'exploit-opponent-weak-side' => array( 'our_final_third_entries_per90', 'our_box_entries_per90' ),
        );
        $keys = $map[ $scenario ] ?? array( 'our_progressive_actions_per90', 'our_final_third_entries_per90', 'opponent_final_third_actions_per90' );
        $rows = array();
        foreach ( $keys as $key ) {
            $rows[] = array(
                'metric' => $key,
                'before' => $before[ $key ] ?? null,
                'after' => $after[ $key ] ?? null,
                'delta' => $deltas[ $key ] ?? null,
            );
        }
        return array(
            'scenario_id' => $scenario,
            'metrics' => $rows,
            'interpretation' => self::text( '' === $scenario ? 'focus_generic' : 'focus_scenario', $language ),
        );
    }

    private static function coverage( int $before_duration, int $after_duration, int $before_events, int $after_events, int $requested, string $language ): array {
        if ( $before_duration >= $requested && $after_duration >= $requested && $before_events >= 15 && $after_events >= 15 ) {
            $status = 'good';
        } elseif ( $before_duration >= 6 && $after_duration >= 6 && $before_events >= 8 && $after_events >= 8 ) {
            $status = 'caution';
        } else {
            $status = 'limited';
        }
        return array(
            'status' => $status,
            'status_label' => self::text( 'coverage_' . $status, $language ),
            'before_duration_minutes' => $before_duration,
            'after_duration_minutes' => $after_duration,
            'before_events' => $before_events,
            'after_events' => $after_events,
            'comparable' => 'limited' !== $status,
        );
    }

    private static function max_event_minute( array $events ): int {
        $max = 0;
        foreach ( $events as $event ) {
            $max = max( $max, (int) ( $event['minute'] ?? 0 ) );
        }
        return $max;
    }

    private static function per90( int $value, int $duration ): float {
        return $duration > 0 ? round( ( $value * 90.0 ) / $duration, 2 ) : 0.0;
    }

    private static function rate90( float $value, int $duration ): float {
        return $duration > 0 ? round( ( $value * 90.0 ) / $duration, 3 ) : 0.0;
    }

    private static function channels( array $value ): array {
        return array(
            'left' => round( (float) ( $value['left'] ?? 0.0 ), 1 ),
            'central' => round( (float) ( $value['central'] ?? 0.0 ), 1 ),
            'right' => round( (float) ( $value['right'] ?? 0.0 ), 1 ),
        );
    }

    private static function summary( array $rows, string $language ): array {
        $good = 0;
        $caution = 0;
        $limited = 0;
        foreach ( $rows as $row ) {
            $status = (string) ( $row['coverage']['status'] ?? 'limited' );
            if ( 'good' === $status ) ++$good;
            elseif ( 'caution' === $status ) ++$caution;
            else ++$limited;
        }
        return array(
            sprintf( self::text( 'summary_transitions', $language ), count( $rows ) ),
            sprintf( self::text( 'summary_coverage', $language ), $good, $caution, $limited ),
        );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'method' => 'Each recorded plan transition is surrounded by symmetric minute-level event windows. Metrics are normalized to per-90 rates where appropriate and reported as descriptive before/after deltas.',
                'boundary' => 'Before uses [M-window,M); after uses [M,M+window). Events stamped with the transition minute belong only to the after window.',
                'limit_causality' => 'A before/after change does not establish that the tactical intervention caused the change.',
                'limit_context' => 'Score, fatigue, substitutions, opponent changes and random match variation may differ between the two windows.',
                'limit_snapshot' => 'The transition minute is based on a staff snapshot and may lag the real-world tactical change.',
                'limit_transition_boundary' => 'A turnover/transition sequence crossing the intervention boundary can be split and undercounted.',
                'limit_tracking' => 'Metrics are event-derived and do not replace tracking-derived spatial measurements.',
                'coverage_good' => 'Good','coverage_caution' => 'Caution','coverage_limited' => 'Limited',
                'focus_scenario' => 'These metrics are selected because they are directly related to the scenario active after the transition. Their deltas are descriptive, not causal.',
                'focus_generic' => 'Generic progression and territorial metrics are shown because the new state has no scenario-specific focus.',
                'summary_transitions' => '%d recorded tactical transitions have before/after windows.',
                'summary_coverage' => 'Coverage: %d good, %d caution, %d limited comparisons.',
            ),
            'es' => array(
                'method' => 'Cada transición de plan registrada se rodea de ventanas simétricas de eventos por minuto. Las métricas se normalizan por 90 cuando corresponde y se muestran como deltas descriptivos antes/después.',
                'boundary' => 'Antes usa [M-ventana,M); después usa [M,M+ventana). Los eventos con el minuto exacto de transición pertenecen solo a la ventana posterior.',
                'limit_causality' => 'Un cambio antes/después no demuestra que la intervención táctica haya causado ese cambio.',
                'limit_context' => 'Marcador, fatiga, sustituciones, cambios del rival y variación aleatoria pueden ser diferentes entre ambas ventanas.',
                'limit_snapshot' => 'El minuto de transición procede de un snapshot del staff y puede ir retrasado respecto al cambio táctico real.',
                'limit_transition_boundary' => 'Una secuencia pérdida/transición que cruce el límite de intervención puede quedar dividida e infracontada.',
                'limit_tracking' => 'Las métricas derivan de eventos y no sustituyen mediciones espaciales de tracking.',
                'coverage_good' => 'Buena','coverage_caution' => 'Precaución','coverage_limited' => 'Limitada',
                'focus_scenario' => 'Estas métricas se seleccionan porque están directamente relacionadas con el escenario activo tras la transición. Sus deltas son descriptivos, no causales.',
                'focus_generic' => 'Se muestran métricas genéricas de progresión y territorio porque el nuevo estado no tiene un foco de escenario específico.',
                'summary_transitions' => '%d transiciones tácticas registradas tienen ventanas antes/después.',
                'summary_coverage' => 'Cobertura: %d comparaciones buenas, %d con precaución y %d limitadas.',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
