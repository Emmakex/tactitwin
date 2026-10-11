<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Post_Match_Review {
    public static function build( array $session, array $snapshots, string $language = 'en' ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        if ( empty( $snapshots ) ) {
            throw new InvalidArgumentException( 'A post-match review requires at least one live-session snapshot.' );
        }

        usort(
            $snapshots,
            static function( array $a, array $b ): int {
                $minute = (int) ( $a['minute'] ?? 0 ) <=> (int) ( $b['minute'] ?? 0 );
                return 0 !== $minute ? $minute : (int) ( $a['id'] ?? 0 ) <=> (int) ( $b['id'] ?? 0 );
            }
        );

        $timeline = TactiTwin_Live_Timeline::summarize( $snapshots );
        $first = $snapshots[0];
        $last  = $snapshots[ count( $snapshots ) - 1 ];
        $first_playbook = self::playbook( $first );

        $prepared = self::prepared( $first_playbook );
        $observed = self::observed( $snapshots );
        $activated = self::activated( $snapshots, $timeline );
        $alignment = self::alignment( $prepared, $observed, $activated, $language );
        $sampling = self::sampling( $session, $snapshots, $language );
        $notes = self::staff_notes( $snapshots );

        return array(
            'review_id' => 'post-match-review',
            'language' => $language,
            'session' => array(
                'session_uuid' => (string) ( $session['session_uuid'] ?? '' ),
                'status' => (string) ( $session['status'] ?? '' ),
                'title' => (string) ( $session['title'] ?? '' ),
                'source_match_id' => (string) ( $session['source_match_id'] ?? '' ),
                'team_source_id' => (string) ( $session['team_source_id'] ?? '' ),
                'opponent_source_id' => (string) ( $session['opponent_source_id'] ?? '' ),
                'started_at' => (string) ( $session['started_at'] ?? '' ),
                'closed_at' => $session['closed_at'] ?? null,
            ),
            'final_context' => array(
                'minute' => (int) ( $last['minute'] ?? 0 ),
                'our_score' => (int) ( $last['our_score'] ?? 0 ),
                'opponent_score' => (int) ( $last['opponent_score'] ?? 0 ),
                'our_players' => (int) ( $last['our_players'] ?? 11 ),
                'opponent_players' => (int) ( $last['opponent_players'] ?? 11 ),
                'active_plan' => (string) ( $last['active_plan'] ?? '' ),
                'active_mode' => (string) ( $last['active_mode'] ?? '' ),
            ),
            'prepared' => $prepared,
            'observed' => $observed,
            'activated' => $activated,
            'alignment' => $alignment,
            'timeline' => $timeline,
            'sampling' => $sampling,
            'staff_notes' => $notes,
            'summary' => self::summary( $prepared, $observed, $activated, $alignment, $sampling, $language ),
            'method' => array(
                'type' => 'descriptive-planned-observed-activated-review',
                'note' => self::text( 'method', $language ),
                'limitations' => array(
                    self::text( 'limit_effect', $language ),
                    self::text( 'limit_sampling', $language ),
                    self::text( 'limit_manual', $language ),
                ),
            ),
        );
    }

    private static function prepared( array $playbook ): array {
        $plan_a = $playbook['plans']['plan_a'] ?? array();
        $plan_b = $playbook['plans']['plan_b'] ?? array();
        $priorities = array();
        foreach ( $plan_a['priority_scenarios'] ?? array() as $row ) {
            $id = (string) ( $row['scenario_id'] ?? '' );
            if ( '' === $id ) {
                continue;
            }
            $priorities[] = array(
                'scenario_id' => $id,
                'scenario_name' => (string) ( $row['scenario_name'] ?? $id ),
                'priority_score' => isset( $row['priority_score'] ) ? round( (float) $row['priority_score'], 1 ) : null,
            );
        }

        return array(
            'plan_a_instruction_count' => count( $plan_a['instructions'] ?? array() ),
            'priority_scenarios' => $priorities,
            'primary_plan_b' => array(
                'status' => (string) ( $plan_b['status'] ?? 'unknown' ),
                'scenario_id' => (string) ( $plan_b['scenario_id'] ?? '' ),
                'scenario_name' => (string) ( $plan_b['scenario_name'] ?? '' ),
                'risk_score' => isset( $plan_b['risk_score'] ) ? round( (float) $plan_b['risk_score'], 1 ) : null,
                'mode' => (string) ( $plan_b['mode'] ?? '' ),
                'trigger' => (string) ( $plan_b['trigger'] ?? '' ),
            ),
        );
    }

    private static function observed( array $snapshots ): array {
        $scenario_map = TactiTwin_Live_Observation::supported_observations();
        $scenario_counts = array();
        $first_seen = array();
        $observation_counts = array_fill_keys( array_keys( $scenario_map ), 0 );

        foreach ( $snapshots as $snapshot ) {
            $minute = (int) ( $snapshot['minute'] ?? 0 );
            $observations = self::observations( $snapshot );
            foreach ( $scenario_map as $observation => $scenario_id ) {
                if ( empty( $observations[ $observation ] ) ) {
                    continue;
                }
                ++$observation_counts[ $observation ];
                $scenario_counts[ $scenario_id ] = ( $scenario_counts[ $scenario_id ] ?? 0 ) + 1;
                if ( ! isset( $first_seen[ $scenario_id ] ) ) {
                    $first_seen[ $scenario_id ] = $minute;
                }
            }
        }

        arsort( $scenario_counts );
        return array(
            'observation_snapshot_counts' => $observation_counts,
            'scenario_snapshot_counts' => $scenario_counts,
            'scenario_first_seen_minute' => $first_seen,
            'unique_scenarios' => array_keys( $scenario_counts ),
            'snapshots_with_any_observation' => self::snapshots_with_observation( $snapshots ),
        );
    }

    private static function activated( array $snapshots, array $timeline ): array {
        $plan_b_counts = array();
        $plan_b_first = array();
        $plan_c_modes = array();
        $first_plan_b_minute = null;
        $first_plan_c_minute = null;

        foreach ( $snapshots as $snapshot ) {
            $plan = strtoupper( (string) ( $snapshot['active_plan'] ?? '' ) );
            $minute = (int) ( $snapshot['minute'] ?? 0 );
            if ( 'B' === $plan ) {
                if ( null === $first_plan_b_minute ) {
                    $first_plan_b_minute = $minute;
                }
                $scenario = (string) ( $snapshot['selected_scenario'] ?? '' );
                if ( '' !== $scenario ) {
                    $plan_b_counts[ $scenario ] = ( $plan_b_counts[ $scenario ] ?? 0 ) + 1;
                    if ( ! isset( $plan_b_first[ $scenario ] ) ) {
                        $plan_b_first[ $scenario ] = $minute;
                    }
                }
            } elseif ( 'C' === $plan ) {
                if ( null === $first_plan_c_minute ) {
                    $first_plan_c_minute = $minute;
                }
                $mode = (string) ( $snapshot['active_mode'] ?? '' );
                if ( '' !== $mode ) {
                    $plan_c_modes[ $mode ] = ( $plan_c_modes[ $mode ] ?? 0 ) + 1;
                }
            }
        }

        arsort( $plan_b_counts );
        arsort( $plan_c_modes );
        return array(
            'plan_snapshot_counts' => $timeline['plan_snapshot_counts'] ?? array(),
            'plan_b_scenario_counts' => $plan_b_counts,
            'plan_b_first_activation_minute' => $plan_b_first,
            'first_plan_b_minute' => $first_plan_b_minute,
            'plan_c_mode_counts' => $plan_c_modes,
            'first_plan_c_minute' => $first_plan_c_minute,
            'transition_count' => count( $timeline['transitions'] ?? array() ),
            'transitions' => $timeline['transitions'] ?? array(),
        );
    }

    private static function alignment( array $prepared, array $observed, array $activated, string $language ): array {
        $primary = (string) ( $prepared['primary_plan_b']['scenario_id'] ?? '' );
        $observed_scenarios = $observed['unique_scenarios'] ?? array();
        $activated_scenarios = array_keys( $activated['plan_b_scenario_counts'] ?? array() );
        $priority_ids = array_values(
            array_filter(
                array_map(
                    static fn( array $row ): string => (string) ( $row['scenario_id'] ?? '' ),
                    $prepared['priority_scenarios'] ?? array()
                )
            )
        );
        if ( '' !== $primary && ! in_array( $primary, $priority_ids, true ) ) {
            $priority_ids[] = $primary;
        }

        $primary_observed = '' !== $primary && in_array( $primary, $observed_scenarios, true );
        $primary_activated = '' !== $primary && in_array( $primary, $activated_scenarios, true );
        $unprepared_observed = array_values( array_diff( $observed_scenarios, $priority_ids ) );
        $unprepared_activated = array_values( array_diff( $activated_scenarios, $priority_ids ) );

        if ( '' === $primary ) {
            $status = ! empty( $activated_scenarios ) ? 'adaptation-without-primary-plan-b' : 'no-primary-plan-b';
        } elseif ( $primary_activated ) {
            $status = 'prepared-and-activated';
        } elseif ( $primary_observed ) {
            $status = 'prepared-observed-not-activated';
        } elseif ( ! empty( $activated_scenarios ) ) {
            $status = 'different-adaptation-activated';
        } else {
            $status = 'prepared-not-observed';
        }

        return array(
            'status' => $status,
            'label' => self::text( 'alignment_' . str_replace( '-', '_', $status ), $language ),
            'primary_plan_b_observed' => $primary_observed,
            'primary_plan_b_activated' => $primary_activated,
            'unprepared_observed_scenarios' => $unprepared_observed,
            'unprepared_activated_scenarios' => $unprepared_activated,
            'plan_c_used' => (int) ( $activated['plan_snapshot_counts']['C'] ?? 0 ) > 0,
        );
    }

    private static function sampling( array $session, array $snapshots, string $language ): array {
        $minutes = array_map( static fn( array $row ): int => (int) ( $row['minute'] ?? 0 ), $snapshots );
        sort( $minutes );
        $max_gap = 0;
        for ( $i = 1, $count = count( $minutes ); $i < $count; ++$i ) {
            $max_gap = max( $max_gap, $minutes[$i] - $minutes[$i - 1] );
        }

        $warnings = array();
        if ( count( $snapshots ) < 3 ) {
            $warnings[] = self::text( 'sampling_few', $language );
        }
        if ( ! empty( $minutes ) && $minutes[0] > 20 ) {
            $warnings[] = self::text( 'sampling_late_start', $language );
        }
        if ( $max_gap > 30 ) {
            $warnings[] = self::text( 'sampling_gap', $language );
        }
        if ( 'closed' !== (string) ( $session['status'] ?? '' ) ) {
            $warnings[] = self::text( 'sampling_open', $language );
        }

        return array(
            'status' => empty( $warnings ) ? 'good' : ( count( $warnings ) >= 2 ? 'limited' : 'caution' ),
            'snapshot_count' => count( $snapshots ),
            'first_minute' => ! empty( $minutes ) ? $minutes[0] : null,
            'last_minute' => ! empty( $minutes ) ? $minutes[ count( $minutes ) - 1 ] : null,
            'max_snapshot_gap_minutes' => $max_gap,
            'warnings' => $warnings,
        );
    }

    private static function staff_notes( array $snapshots ): array {
        $notes = array();
        foreach ( $snapshots as $snapshot ) {
            $note = trim( (string) ( $snapshot['note'] ?? '' ) );
            if ( '' === $note ) {
                continue;
            }
            $notes[] = array(
                'minute' => (int) ( $snapshot['minute'] ?? 0 ),
                'note' => $note,
                'snapshot_id' => (int) ( $snapshot['id'] ?? 0 ),
            );
        }
        return $notes;
    }

    private static function summary( array $prepared, array $observed, array $activated, array $alignment, array $sampling, string $language ): array {
        $items = array();
        $primary_name = (string) ( $prepared['primary_plan_b']['scenario_name'] ?? '' );
        $primary_id = (string) ( $prepared['primary_plan_b']['scenario_id'] ?? '' );
        if ( '' !== $primary_id ) {
            $items[] = sprintf( self::text( 'summary_prepared_b', $language ), '' !== $primary_name ? $primary_name : $primary_id );
        } else {
            $items[] = self::text( 'summary_no_primary_b', $language );
        }
        $items[] = sprintf( self::text( 'summary_observed', $language ), count( $observed['unique_scenarios'] ?? array() ) );
        $items[] = sprintf( self::text( 'summary_transitions', $language ), (int) ( $activated['transition_count'] ?? 0 ) );
        $items[] = (string) $alignment['label'];
        if ( ! empty( $sampling['warnings'] ) ) {
            $items[] = self::text( 'summary_sampling', $language );
        }
        return array_values( array_unique( array_filter( $items ) ) );
    }

    private static function snapshots_with_observation( array $snapshots ): int {
        $count = 0;
        $supported = array_keys( TactiTwin_Live_Observation::supported_observations() );
        foreach ( $snapshots as $snapshot ) {
            $observations = self::observations( $snapshot );
            foreach ( $supported as $key ) {
                if ( ! empty( $observations[$key] ) ) {
                    ++$count;
                    break;
                }
            }
        }
        return $count;
    }

    private static function observations( array $snapshot ): array {
        if ( isset( $snapshot['observations'] ) && is_array( $snapshot['observations'] ) ) {
            return $snapshot['observations'];
        }
        $playbook = self::playbook( $snapshot );
        $observations = $playbook['live_observation']['observations'] ?? array();
        return is_array( $observations ) ? $observations : array();
    }

    private static function playbook( array $snapshot ): array {
        $playbook = $snapshot['playbook'] ?? array();
        if ( is_array( $playbook ) ) {
            return $playbook;
        }
        $decoded = json_decode( (string) $playbook, true );
        return is_array( $decoded ) ? $decoded : array();
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'alignment_prepared_and_activated' => 'The primary prepared Plan B behaviour was observed and the prepared adaptation was activated.',
                'alignment_prepared_observed_not_activated' => 'The primary prepared Plan B behaviour was observed but that adaptation was not activated in the stored snapshots.',
                'alignment_different_adaptation_activated' => 'The primary prepared Plan B behaviour was not observed; a different live adaptation was activated.',
                'alignment_prepared_not_observed' => 'The primary prepared Plan B behaviour was not observed in the stored session snapshots.',
                'alignment_adaptation_without_primary_plan_b' => 'No primary Plan B was armed pre-match, but a live adaptation was activated from staff observations.',
                'alignment_no_primary_plan_b' => 'No primary Plan B was armed and no live Plan B adaptation was activated.',
                'sampling_few' => 'The review has fewer than three snapshots, so the match-day timeline is sparse.',
                'sampling_late_start' => 'The first stored snapshot is after minute 20, so early-match behaviour may be missing.',
                'sampling_gap' => 'At least one gap between snapshots exceeds 30 minutes; intermediate tactical changes may be missing.',
                'sampling_open' => 'The live session is still open; this review should be treated as provisional.',
                'summary_prepared_b' => 'Primary prepared Plan B: %s.',
                'summary_no_primary_b' => 'No primary Plan B was armed before the stored session.',
                'summary_observed' => '%d distinct supported opponent behaviours were manually observed.',
                'summary_transitions' => '%d plan/mode/scenario transitions were stored.',
                'summary_sampling' => 'Timeline sampling has caveats; inspect the sampling warnings before drawing conclusions.',
                'method' => 'This review compares what was prepared, what staff explicitly observed and what TactiTwin activated in the stored session. It is descriptive and auditable, not a judgement of coaching quality.',
                'limit_effect' => 'The review does not estimate whether a tactical decision caused a better or worse match outcome.',
                'limit_sampling' => 'Unrecorded periods between staff snapshots can contain tactical changes that are absent from the timeline.',
                'limit_manual' => 'Observed behaviours come from manual staff input unless a future source explicitly identifies another observation source.',
            ),
            'es' => array(
                'alignment_prepared_and_activated' => 'El comportamiento principal preparado para Plan B fue observado y se activó la adaptación prevista.',
                'alignment_prepared_observed_not_activated' => 'El comportamiento principal preparado para Plan B fue observado, pero esa adaptación no se activó en los snapshots guardados.',
                'alignment_different_adaptation_activated' => 'No se observó el comportamiento principal preparado para Plan B; se activó una adaptación distinta a partir de la observación en vivo.',
                'alignment_prepared_not_observed' => 'El comportamiento principal preparado para Plan B no aparece observado en los snapshots guardados.',
                'alignment_adaptation_without_primary_plan_b' => 'No había un Plan B principal armado antes del partido, pero se activó una adaptación en vivo a partir de observaciones del cuerpo técnico.',
                'alignment_no_primary_plan_b' => 'No había un Plan B principal armado y no se activó ninguna adaptación Plan B en vivo.',
                'sampling_few' => 'El análisis tiene menos de tres snapshots, por lo que el timeline del partido es escaso.',
                'sampling_late_start' => 'El primer snapshot guardado es posterior al minuto 20; puede faltar comportamiento del inicio del partido.',
                'sampling_gap' => 'Al menos un intervalo entre snapshots supera los 30 minutos; pueden faltar cambios tácticos intermedios.',
                'sampling_open' => 'La sesión en vivo sigue abierta; este análisis debe considerarse provisional.',
                'summary_prepared_b' => 'Plan B principal preparado: %s.',
                'summary_no_primary_b' => 'No se armó un Plan B principal antes de la sesión guardada.',
                'summary_observed' => 'Se observaron manualmente %d comportamientos rivales distintos soportados.',
                'summary_transitions' => 'Se guardaron %d transiciones de plan/modo/escenario.',
                'summary_sampling' => 'El muestreo del timeline tiene limitaciones; revisa los avisos antes de extraer conclusiones.',
                'method' => 'Este análisis compara lo preparado, lo observado explícitamente por el cuerpo técnico y lo que TactiTwin activó en la sesión guardada. Es descriptivo y auditable, no un juicio sobre la calidad del entrenador.',
                'limit_effect' => 'El análisis no estima si una decisión táctica causó un mejor o peor resultado del partido.',
                'limit_sampling' => 'Los periodos no registrados entre snapshots pueden contener cambios tácticos ausentes del timeline.',
                'limit_manual' => 'Los comportamientos observados proceden de entrada manual del cuerpo técnico salvo que una fuente futura identifique explícitamente otro origen.',
            ),
        );
        return $catalog[$language][$key] ?? $catalog['en'][$key] ?? $key;
    }
}
