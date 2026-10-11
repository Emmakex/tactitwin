<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Time_Aligned_Evidence {
    private const SIGNALS = array(
        'build-up-vs-high-press' => 'high_press',
        'attack-vs-low-block' => 'low_block',
        'defensive-transition-after-loss' => 'transition_threat',
        'opponent-side-bias' => 'side_bias',
        'exploit-opponent-weak-side' => 'weak_side_open',
    );

    public static function evaluate(
        array $session,
        array $snapshots,
        array $events,
        array $match_summary,
        string $language = 'en'
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $team_id = (string) ( $session['team_source_id'] ?? '' );
        $opponent_id = (string) ( $session['opponent_source_id'] ?? '' );
        $match_id = (string) ( $match_summary['match']['source_match_id'] ?? '' );

        if ( '' === $team_id || '' === $opponent_id || $team_id === $opponent_id ) {
            throw new InvalidArgumentException( 'Time-aligned evidence requires a valid team/opponent pairing.' );
        }
        if ( '' === $match_id || empty( $events ) || empty( $snapshots ) ) {
            throw new InvalidArgumentException( 'Time-aligned evidence requires linked match events and live-session snapshots.' );
        }
        if ( ! isset( $match_summary['teams'][ $team_id ], $match_summary['teams'][ $opponent_id ] ) ) {
            throw new InvalidArgumentException( 'The linked match does not contain the live-session team/opponent pairing.' );
        }

        $timeline = TactiTwin_Live_Timeline::summarize( $snapshots );
        $segments = $timeline['segments'] ?? array();
        $first_playbook = self::first_playbook( $snapshots );
        $target_channel = self::weak_side_target( $first_playbook );
        $rows = array();

        foreach ( $segments as $index => $segment ) {
            $from = max( 0, (int) ( $segment['from_minute'] ?? 0 ) );
            $to = max( $from, (int) ( $segment['to_minute'] ?? $from ) );
            $is_last = $index === count( $segments ) - 1;
            $slice = self::slice_events( $events, $from, $to, $is_last );
            $bundle = self::metric_bundle( $slice, $team_id, $opponent_id );
            $duration = max( 0, $to - $from );
            $staff_observations = self::segment_observations( $snapshots, $segment['snapshot_ids'] ?? array() );
            $signals = self::signals( $bundle['team'], $bundle['opponent'], $duration, $target_channel, $staff_observations, $language );
            $active_scenario = (string) ( $segment['scenario_id'] ?? '' );

            $rows[] = array(
                'segment_index' => $index + 1,
                'plan' => strtoupper( (string) ( $segment['plan'] ?? 'A' ) ),
                'mode' => (string) ( $segment['mode'] ?? '' ),
                'scenario_id' => $active_scenario,
                'from_minute' => $from,
                'to_minute' => $to,
                'duration_minutes' => $duration,
                'event_boundary' => $is_last ? '[from,to]' : '[from,to)',
                'snapshot_ids' => array_values( array_map( 'intval', $segment['snapshot_ids'] ?? array() ) ),
                'event_count' => count( $slice ),
                'staff_observations' => $staff_observations,
                'signals' => $signals,
                'active_scenario_evidence' => self::find_signal( $signals, $active_scenario ),
                'metrics' => array(
                    'team' => self::compact_metrics( $bundle['team'] ),
                    'opponent' => self::compact_metrics( $bundle['opponent'] ),
                ),
            );
        }

        $first_minute = (int) ( $timeline['first_minute'] ?? 0 );
        $last_minute = (int) ( $timeline['last_minute'] ?? 0 );

        return array(
            'evidence_id' => 'time-aligned-segment-evidence',
            'language' => $language,
            'source_match_id' => $match_id,
            'coverage' => array(
                'events' => count( $events ),
                'segment_count' => count( $rows ),
                'first_snapshot_minute' => $first_minute,
                'last_snapshot_minute' => $last_minute,
                'unobserved_pre_session_minutes' => max( 0, $first_minute ),
                'time_aligned_to_snapshots' => true,
                'time_resolution' => 'minute',
            ),
            'segments' => $rows,
            'summary' => self::summary( $rows, $language ),
            'method' => array(
                'type' => 'descriptive-minute-aligned-event-segment-validation',
                'note' => self::text( 'method', $language ),
                'boundary_rule' => self::text( 'boundary', $language ),
                'limitations' => array(
                    self::text( 'limit_snapshots', $language ),
                    self::text( 'limit_minute', $language ),
                    self::text( 'limit_transition_boundary', $language ),
                    self::text( 'limit_tracking', $language ),
                    self::text( 'limit_causality', $language ),
                ),
            ),
        );
    }

    private static function slice_events( array $events, int $from, int $to, bool $is_last ): array {
        return array_values(
            array_filter(
                $events,
                static function( array $event ) use ( $from, $to, $is_last ): bool {
                    $minute = (int) ( $event['minute'] ?? 0 );
                    if ( $minute < $from ) {
                        return false;
                    }
                    return $is_last ? $minute <= $to : $minute < $to;
                }
            )
        );
    }

    private static function metric_bundle( array $events, string $team_id, string $opponent_id ): array {
        $match = TactiTwin_Match_Metrics::calculate( $events );
        $transitions = TactiTwin_Transition_Metrics::calculate( $events );
        return array(
            'team' => self::merge_metrics( $match[ $team_id ] ?? array(), $transitions[ $team_id ] ?? array(), $team_id ),
            'opponent' => self::merge_metrics( $match[ $opponent_id ] ?? array(), $transitions[ $opponent_id ] ?? array(), $opponent_id ),
        );
    }

    private static function merge_metrics( array $base, array $transition, string $team_id ): array {
        $zones = $transition['turnovers_by_zone'] ?? array();
        unset( $transition['turnovers_by_zone'] );
        return array_merge(
            array( 'team_source_id' => $team_id ),
            $base,
            $transition,
            array(
                'turnovers_own_third' => (int) ( $zones['own_third'] ?? 0 ),
                'turnovers_middle_third' => (int) ( $zones['middle_third'] ?? 0 ),
                'turnovers_final_third' => (int) ( $zones['final_third'] ?? 0 ),
            )
        );
    }

    private static function signals(
        array $team,
        array $opponent,
        int $duration,
        string $target_channel,
        array $staff,
        string $language
    ): array {
        return array(
            self::high_press( $opponent, $duration, $staff, $language ),
            self::low_block( $opponent, $duration, $staff, $language ),
            self::transition_threat( $team, $duration, $staff, $language ),
            self::side_bias( $opponent, $staff, $language ),
            self::weak_side( $team, $target_channel, $staff, $language ),
        );
    }

    private static function high_press( array $opponent, int $duration, array $staff, string $language ): array {
        $high = (int) ( $opponent['high_pressures'] ?? 0 );
        $total = (int) ( $opponent['pressures'] ?? 0 );
        $share = (float) ( $opponent['high_pressure_share_pct'] ?? 0.0 );
        $per90 = self::per90( $high, $duration );
        if ( $duration < 8 || $total < 3 ) {
            $status = 'insufficient';
        } elseif ( $per90 >= 18.0 && $share >= 25.0 ) {
            $status = 'supported';
        } elseif ( $per90 >= 12.0 || $share >= 18.0 ) {
            $status = 'partially-supported';
        } else {
            $status = 'not-supported';
        }
        return self::signal( 'high_press', 'build-up-vs-high-press', $status, $staff, array(
            'opponent_high_pressures'=>$high,
            'opponent_pressures'=>$total,
            'high_pressures_per90'=>$per90,
            'high_pressure_share_pct'=>round($share,1),
        ), 'duration >= 8 and pressures >= 3; supported when high_pressures_per90 >= 18 AND share >= 25', $language );
    }

    private static function low_block( array $opponent, int $duration, array $staff, string $language ): array {
        $deep = (int) ( $opponent['deep_defensive_actions'] ?? 0 );
        $total = (int) ( $opponent['defensive_actions'] ?? 0 );
        $share = (float) ( $opponent['deep_defensive_action_share_pct'] ?? 0.0 );
        $per90 = self::per90( $deep, $duration );
        if ( $duration < 8 || $total < 4 ) {
            $status = 'insufficient';
        } elseif ( $per90 >= 18.0 && $share >= 45.0 ) {
            $status = 'supported';
        } elseif ( $per90 >= 12.0 && $share >= 35.0 || $share >= 40.0 ) {
            $status = 'partially-supported';
        } else {
            $status = 'not-supported';
        }
        return self::signal( 'low_block', 'attack-vs-low-block', $status, $staff, array(
            'opponent_deep_defensive_actions'=>$deep,
            'opponent_defensive_actions'=>$total,
            'deep_defensive_actions_per90'=>$per90,
            'deep_defensive_action_share_pct'=>round($share,1),
        ), 'duration >= 8 and defensive_actions >= 4; supported when deep_actions_per90 >= 18 AND share >= 45', $language );
    }

    private static function transition_threat( array $team, int $duration, array $staff, string $language ): array {
        $turnovers = (int) ( $team['transition_turnovers'] ?? 0 );
        $progressions = (int) ( $team['opponent_progressions_within_10s'] ?? 0 );
        $shots = (int) ( $team['opponent_shots_within_10s'] ?? 0 );
        $xg = (float) ( $team['xg_conceded_within_10s'] ?? 0.0 );
        $progression_rate = (float) ( $team['transition_progression_rate'] ?? 0.0 );
        if ( $shots >= 2 || $xg >= 0.25 || $turnovers >= 3 && $progression_rate >= 35.0 ) {
            $status = 'supported';
        } elseif ( $shots >= 1 || $xg >= 0.10 || $turnovers >= 2 && $progressions >= 1 ) {
            $status = 'partially-supported';
        } elseif ( $duration < 8 || $turnovers < 3 ) {
            $status = 'insufficient';
        } else {
            $status = 'not-supported';
        }
        return self::signal( 'transition_threat', 'defensive-transition-after-loss', $status, $staff, array(
            'our_transition_turnovers'=>$turnovers,
            'opponent_progressions_within_10s'=>$progressions,
            'opponent_shots_within_10s'=>$shots,
            'xg_conceded_within_10s'=>round($xg,3),
            'transition_progression_rate_pct'=>round($progression_rate,1),
        ), 'supported when shots_after_loss >= 2 OR xG_after_loss >= 0.25 OR (turnovers >= 3 AND progression_rate >= 35)', $language );
    }

    private static function side_bias( array $opponent, array $staff, string $language ): array {
        $actions = (int) ( $opponent['final_third_actions'] ?? 0 );
        $shares = self::channels( $opponent['final_third_channel_share'] ?? array() );
        $dominant = $shares['left'] >= $shares['right'] ? 'left' : 'right';
        $gap = abs( $shares['left'] - $shares['right'] );
        if ( $actions < 3 ) {
            $status = 'insufficient';
        } elseif ( $actions >= 5 && $gap >= 20.0 && $shares[$dominant] >= 40.0 ) {
            $status = 'supported';
        } elseif ( $gap >= 12.0 ) {
            $status = 'partially-supported';
        } else {
            $status = 'not-supported';
        }
        return self::signal( 'side_bias', 'opponent-side-bias', $status, $staff, array(
            'opponent_final_third_actions'=>$actions,
            'left_share_pct'=>$shares['left'],
            'central_share_pct'=>$shares['central'],
            'right_share_pct'=>$shares['right'],
            'dominant_lateral_channel'=>$dominant,
            'lateral_share_gap_points'=>round($gap,1),
        ), 'supported when final_third_actions >= 5 AND lateral gap >= 20 AND dominant lateral share >= 40', $language );
    }

    private static function weak_side( array $team, string $target, array $staff, string $language ): array {
        $actions = (int) ( $team['final_third_actions'] ?? 0 );
        $shares = self::channels( $team['final_third_channel_share'] ?? array() );
        if ( '' === $target || ! isset( $shares[$target] ) || $actions < 3 ) {
            $status = 'insufficient';
        } elseif ( $actions >= 5 && $shares[$target] >= 35.0 ) {
            $status = 'supported';
        } elseif ( $shares[$target] >= 25.0 ) {
            $status = 'partially-supported';
        } else {
            $status = 'not-supported';
        }
        return self::signal( 'weak_side_open', 'exploit-opponent-weak-side', $status, $staff, array(
            'prepared_target_channel'=>'' === $target ? null : $target,
            'our_final_third_actions'=>$actions,
            'target_channel_share_pct'=>'' !== $target && isset($shares[$target]) ? $shares[$target] : null,
            'our_final_third_channel_share'=>$shares,
        ), 'requires persisted target channel; supported when final_third_actions >= 5 AND target share >= 35', $language );
    }

    private static function signal( string $observation, string $scenario, string $status, array $staff, array $indicators, string $threshold, string $language ): array {
        $observed = ! empty( $staff[$observation] );
        return array(
            'observation'=>$observation,
            'scenario_id'=>$scenario,
            'status'=>$status,
            'status_label'=>self::text('status_' . str_replace('-','_',$status), $language),
            'staff_observed_in_segment'=>$observed,
            'comparison_status'=>self::comparison_status($observed,$status),
            'indicators'=>$indicators,
            'threshold'=>$threshold,
        );
    }

    private static function segment_observations( array $snapshots, array $snapshot_ids ): array {
        $ids = array_fill_keys( array_map('intval',$snapshot_ids), true );
        $result = array_fill_keys( array('high_press','low_block','transition_threat','side_bias','weak_side_open'), false );
        foreach ( $snapshots as $snapshot ) {
            if ( ! isset( $ids[(int)($snapshot['id'] ?? 0)] ) ) {
                continue;
            }
            $obs = $snapshot['observations'] ?? array();
            if ( ! is_array($obs) ) {
                $obs = $snapshot['playbook']['live_observation']['observations'] ?? array();
            }
            foreach ( $result as $key => $_ ) {
                if ( ! empty($obs[$key]) ) {
                    $result[$key] = true;
                }
            }
        }
        return $result;
    }

    private static function find_signal( array $signals, string $scenario ): ?array {
        foreach ( $signals as $signal ) {
            if ( $scenario !== '' && (string)($signal['scenario_id'] ?? '') === $scenario ) {
                return $signal;
            }
        }
        return null;
    }

    private static function first_playbook( array $snapshots ): array {
        usort($snapshots, static function(array $a,array $b): int {
            $minute = (int)($a['minute'] ?? 0) <=> (int)($b['minute'] ?? 0);
            return 0 !== $minute ? $minute : (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
        });
        $playbook = $snapshots[0]['playbook'] ?? array();
        return is_array($playbook) ? $playbook : array();
    }

    private static function weak_side_target( array $playbook ): string {
        $value = (string)($playbook['evidence']['scenario_fingerprints']['exploit-opponent-weak-side']['target_channel'] ?? '');
        $value = strtolower(trim($value));
        return in_array($value,array('left','central','right'),true) ? $value : '';
    }

    private static function compact_metrics( array $m ): array {
        $keys = array('events','pressures','high_pressures','high_pressure_share_pct','defensive_actions','deep_defensive_actions','deep_defensive_action_share_pct','transition_turnovers','opponent_progressions_within_10s','opponent_shots_within_10s','xg_conceded_within_10s','transition_progression_rate','final_third_actions','final_third_channel_share','shots','xg');
        $out = array();
        foreach ($keys as $key) {
            if ( array_key_exists($key,$m) ) {
                $out[$key] = $m[$key];
            }
        }
        return $out;
    }

    private static function channels( array $channels ): array {
        return array('left'=>round((float)($channels['left'] ?? 0),1),'central'=>round((float)($channels['central'] ?? 0),1),'right'=>round((float)($channels['right'] ?? 0),1));
    }

    private static function per90( int $count, int $duration ): float {
        return $duration > 0 ? round(($count * 90.0) / $duration,1) : 0.0;
    }

    private static function comparison_status( bool $observed, string $status ): string {
        if ( $observed ) {
            if ( 'supported' === $status ) return 'observed-and-supported';
            if ( 'partially-supported' === $status ) return 'observed-and-partially-supported';
            if ( 'not-supported' === $status ) return 'observed-not-supported-in-segment';
            return 'observed-segment-evidence-insufficient';
        }
        if ( in_array($status,array('supported','partially-supported'),true) ) return 'segment-signal-not-recorded-by-staff';
        if ( 'insufficient' === $status ) return 'not-observed-segment-evidence-insufficient';
        return 'not-observed-no-segment-signal';
    }

    private static function summary( array $segments, string $language ): array {
        $b = 0; $b_supported = 0; $changes = 0;
        foreach ($segments as $segment) {
            if ( 'B' === (string)($segment['plan'] ?? '') ) {
                ++$b;
                $status = (string)($segment['active_scenario_evidence']['status'] ?? 'insufficient');
                if ( in_array($status,array('supported','partially-supported'),true) ) ++$b_supported;
            }
            foreach ($segment['signals'] ?? array() as $signal) {
                if ( 'segment-signal-not-recorded-by-staff' === (string)($signal['comparison_status'] ?? '') ) ++$changes;
            }
        }
        return array(
            sprintf(self::text('summary_b',$language),$b_supported,$b),
            sprintf(self::text('summary_unrecorded',$language),$changes),
        );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en'=>array(
                'status_supported'=>'Supported','status_partially_supported'=>'Partially supported','status_not_supported'=>'Not supported','status_insufficient'=>'Insufficient',
                'method'=>'Events are sliced into the same minute-level intervals reconstructed from Live Session snapshots, then each interval is evaluated with segment-specific sample gates and normalized rates.',
                'boundary'=>'Transition-minute events belong to the new segment. Non-final segments use [from,to); the final segment uses [from,to].',
                'limit_snapshots'=>'Segments represent intervals between staff submissions; they do not imply continuous staff observation between snapshots.',
                'limit_minute'=>'Live snapshots are minute-granular, so second-level event alignment is not available in this version.',
                'limit_transition_boundary'=>'A transition sequence crossing a segment boundary may be split and therefore undercounted inside either segment.',
                'limit_tracking'=>'These are event-derived proxies, not tracking-derived line height, compactness or player-location measurements.',
                'limit_causality'=>'Event alignment describes what happened during a plan interval; it does not prove that the plan caused the outcome.',
                'summary_b'=>'%d of %d Plan B segments have supported or partially supported evidence for their active scenario.',
                'summary_unrecorded'=>'%d segment-level event signals were not explicitly recorded by staff in the corresponding segment.',
            ),
            'es'=>array(
                'status_supported'=>'Respaldado','status_partially_supported'=>'Respaldado parcialmente','status_not_supported'=>'No respaldado','status_insufficient'=>'Insuficiente',
                'method'=>'Los eventos se recortan en los mismos intervalos por minuto reconstruidos desde los snapshots de Live Session y cada intervalo se evalúa con mínimos de muestra y tasas normalizadas específicas para segmentos.',
                'boundary'=>'Los eventos del minuto de transición pertenecen al nuevo segmento. Los segmentos no finales usan [desde,hasta); el último usa [desde,hasta].',
                'limit_snapshots'=>'Los segmentos representan intervalos entre envíos del cuerpo técnico; no implican observación continua entre snapshots.',
                'limit_minute'=>'Los snapshots en vivo tienen resolución por minuto, por lo que esta versión no alinea los eventos al segundo.',
                'limit_transition_boundary'=>'Una secuencia de transición que cruce el límite entre segmentos puede quedar dividida y, por tanto, infracontada en ambos tramos.',
                'limit_tracking'=>'Son proxies derivados de eventos, no mediciones de tracking sobre altura de línea, compactación o posición de jugadores.',
                'limit_causality'=>'La alineación temporal describe lo ocurrido durante un intervalo de plan; no demuestra que el plan haya causado el resultado.',
                'summary_b'=>'%d de %d segmentos Plan B tienen evidencia respaldada o parcialmente respaldada para su escenario activo.',
                'summary_unrecorded'=>'%d señales de eventos a nivel de segmento no fueron registradas explícitamente por el staff en el tramo correspondiente.',
            ),
        );
        return $catalog[$language][$key] ?? $catalog['en'][$key] ?? $key;
    }
}
