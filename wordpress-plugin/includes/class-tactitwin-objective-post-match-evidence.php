<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Objective_Post_Match_Evidence {
    public static function evaluate(
        array $session,
        array $review,
        array $snapshots,
        array $match_summary,
        string $language = 'en'
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $team_id = (string) ( $session['team_source_id'] ?? '' );
        $opponent_id = (string) ( $session['opponent_source_id'] ?? '' );
        $match_id = (string) ( $match_summary['match']['source_match_id'] ?? '' );

        if ( '' === $team_id || '' === $opponent_id || $team_id === $opponent_id ) {
            throw new InvalidArgumentException( 'Objective evidence requires a valid team/opponent pairing.' );
        }
        if ( '' === $match_id ) {
            throw new InvalidArgumentException( 'Objective evidence requires an imported match summary.' );
        }

        $teams = $match_summary['teams'] ?? array();
        if ( ! isset( $teams[ $team_id ], $teams[ $opponent_id ] ) ) {
            throw new InvalidArgumentException( 'The linked match does not contain the live-session team/opponent pairing.' );
        }

        $team = $teams[ $team_id ];
        $opponent = $teams[ $opponent_id ];
        $observed = $review['observed'] ?? array();
        $first_playbook = self::first_playbook( $snapshots );

        $signals = array(
            self::high_press( $opponent, $observed, $language ),
            self::low_block( $opponent, $observed, $language ),
            self::transition_threat( $team, $observed, $language ),
            self::side_bias( $opponent, $observed, $language ),
            self::weak_side_access( $team, $first_playbook, $observed, $language ),
        );

        $comparison_counts = array();
        foreach ( $signals as &$signal ) {
            $signal['comparison'] = self::comparison(
                ! empty( $signal['staff_observed'] ),
                (string) ( $signal['status'] ?? 'insufficient' ),
                $language
            );
            $key = (string) $signal['comparison']['status'];
            $comparison_counts[ $key ] = ( $comparison_counts[ $key ] ?? 0 ) + 1;
        }
        unset( $signal );

        $coverage = $match_summary['coverage'] ?? array();
        return array(
            'evidence_id' => 'objective-post-match-event-evidence',
            'language' => $language,
            'source_match_id' => $match_id,
            'source' => (string) ( $coverage['source'] ?? 'event-data' ),
            'coverage' => array(
                'status' => (string) ( $coverage['status'] ?? 'event-data' ),
                'events' => (int) ( $coverage['events'] ?? 0 ),
                'scope' => 'full-match',
                'time_aligned_to_snapshots' => false,
            ),
            'match' => $match_summary['match'] ?? array(),
            'team' => array(
                'source_id' => $team_id,
                'name' => (string) ( $team['team_name'] ?? $team_id ),
            ),
            'opponent' => array(
                'source_id' => $opponent_id,
                'name' => (string) ( $opponent['team_name'] ?? $opponent_id ),
            ),
            'signals' => $signals,
            'comparison_counts' => $comparison_counts,
            'summary' => self::summary( $signals, $language ),
            'method' => array(
                'type' => 'descriptive-full-match-event-proxy-validation',
                'note' => self::text( 'method', $language ),
                'status_meaning' => array(
                    'supported' => self::text( 'status_supported', $language ),
                    'partially-supported' => self::text( 'status_partial', $language ),
                    'not-supported' => self::text( 'status_not_supported', $language ),
                    'insufficient' => self::text( 'status_insufficient', $language ),
                ),
                'limitations' => array(
                    self::text( 'limit_not_tracking', $language ),
                    self::text( 'limit_full_match', $language ),
                    self::text( 'limit_thresholds', $language ),
                    self::text( 'limit_causality', $language ),
                ),
            ),
        );
    }

    private static function high_press( array $opponent, array $observed, string $language ): array {
        $high = (int) ( $opponent['high_pressures'] ?? 0 );
        $total = (int) ( $opponent['pressures'] ?? 0 );
        $share = (float) ( $opponent['high_pressure_share_pct'] ?? 0.0 );

        if ( $high >= 12 && $share >= 20.0 || $high >= 16 ) {
            $status = 'supported';
        } elseif ( $high >= 7 || $share >= 15.0 ) {
            $status = 'partially-supported';
        } else {
            $status = 'not-supported';
        }

        return self::signal(
            'high_press',
            'build-up-vs-high-press',
            $status,
            $observed,
            array(
                'opponent_high_pressures' => $high,
                'opponent_pressures' => $total,
                'opponent_high_pressure_share_pct' => round( $share, 1 ),
            ),
            array(
                'supported' => 'high_pressures >= 12 AND high_pressure_share_pct >= 20, OR high_pressures >= 16',
                'partial' => 'high_pressures >= 7 OR high_pressure_share_pct >= 15',
            ),
            self::text( 'interp_high_press_' . self::status_key( $status ), $language ),
            self::text( 'caveat_high_press', $language ),
            $language
        );
    }

    private static function low_block( array $opponent, array $observed, string $language ): array {
        $deep = (int) ( $opponent['deep_defensive_actions'] ?? 0 );
        $total = (int) ( $opponent['defensive_actions'] ?? 0 );
        $share = (float) ( $opponent['deep_defensive_action_share_pct'] ?? 0.0 );

        if ( $deep >= 12 && $share >= 45.0 ) {
            $status = 'supported';
        } elseif ( $deep >= 8 && $share >= 35.0 || $deep >= 15 ) {
            $status = 'partially-supported';
        } else {
            $status = 'not-supported';
        }

        return self::signal(
            'low_block',
            'attack-vs-low-block',
            $status,
            $observed,
            array(
                'opponent_deep_defensive_actions' => $deep,
                'opponent_defensive_actions' => $total,
                'opponent_deep_defensive_action_share_pct' => round( $share, 1 ),
            ),
            array(
                'supported' => 'deep_defensive_actions >= 12 AND deep_defensive_action_share_pct >= 45',
                'partial' => '(deep_defensive_actions >= 8 AND share >= 35) OR deep_defensive_actions >= 15',
            ),
            self::text( 'interp_low_block_' . self::status_key( $status ), $language ),
            self::text( 'caveat_low_block', $language ),
            $language
        );
    }

    private static function transition_threat( array $team, array $observed, string $language ): array {
        $turnovers = (int) ( $team['transition_turnovers'] ?? 0 );
        $progressions = (int) ( $team['opponent_progressions_within_10s'] ?? 0 );
        $shots = (int) ( $team['opponent_shots_within_10s'] ?? 0 );
        $xg = (float) ( $team['xg_conceded_within_10s'] ?? 0.0 );
        $progression_rate = (float) ( $team['transition_progression_rate'] ?? 0.0 );
        $dangerous_rate = (float) ( $team['dangerous_turnover_rate'] ?? 0.0 );

        if ( $shots >= 2 || $xg >= 0.30 || $progressions >= 4 && $progression_rate >= 25.0 ) {
            $status = 'supported';
        } elseif ( $shots >= 1 || $xg >= 0.10 || $progressions >= 2 ) {
            $status = 'partially-supported';
        } elseif ( $turnovers < 3 ) {
            $status = 'insufficient';
        } else {
            $status = 'not-supported';
        }

        return self::signal(
            'transition_threat',
            'defensive-transition-after-loss',
            $status,
            $observed,
            array(
                'our_transition_turnovers' => $turnovers,
                'opponent_progressions_within_10s' => $progressions,
                'opponent_shots_within_10s' => $shots,
                'xg_conceded_within_10s' => round( $xg, 3 ),
                'transition_progression_rate_pct' => round( $progression_rate, 1 ),
                'dangerous_turnover_rate_pct' => round( $dangerous_rate, 1 ),
            ),
            array(
                'supported' => 'shots_after_loss >= 2 OR xG_after_loss >= 0.30 OR (progressions >= 4 AND progression_rate >= 25)',
                'partial' => 'shots_after_loss >= 1 OR xG_after_loss >= 0.10 OR progressions >= 2',
                'insufficient' => 'transition_turnovers < 3 and no positive threat threshold reached',
            ),
            self::text( 'interp_transition_' . self::status_key( $status ), $language ),
            self::text( 'caveat_transition', $language ),
            $language
        );
    }

    private static function side_bias( array $opponent, array $observed, string $language ): array {
        $actions = (int) ( $opponent['final_third_actions'] ?? 0 );
        $shares = self::channels( $opponent['final_third_channel_share'] ?? array() );
        $dominant = $shares['left'] >= $shares['right'] ? 'left' : 'right';
        $other = 'left' === $dominant ? 'right' : 'left';
        $gap = abs( $shares['left'] - $shares['right'] );

        if ( $actions < 6 ) {
            $status = 'insufficient';
        } elseif ( $actions >= 12 && $gap >= 20.0 && $shares[ $dominant ] >= 38.0 ) {
            $status = 'supported';
        } elseif ( $actions >= 8 && $gap >= 12.0 ) {
            $status = 'partially-supported';
        } else {
            $status = 'not-supported';
        }

        return self::signal(
            'side_bias',
            'opponent-side-bias',
            $status,
            $observed,
            array(
                'opponent_final_third_actions' => $actions,
                'left_share_pct' => $shares['left'],
                'central_share_pct' => $shares['central'],
                'right_share_pct' => $shares['right'],
                'dominant_lateral_channel' => $dominant,
                'opposite_lateral_channel' => $other,
                'lateral_share_gap_points' => round( $gap, 1 ),
            ),
            array(
                'supported' => 'final_third_actions >= 12 AND lateral gap >= 20 points AND dominant lateral share >= 38',
                'partial' => 'final_third_actions >= 8 AND lateral gap >= 12 points',
                'insufficient' => 'final_third_actions < 6',
            ),
            self::text( 'interp_side_bias_' . self::status_key( $status ), $language ),
            self::text( 'caveat_side_bias', $language ),
            $language
        );
    }

    private static function weak_side_access( array $team, array $first_playbook, array $observed, string $language ): array {
        $target = self::prepared_weak_side_channel( $first_playbook );
        $actions = (int) ( $team['final_third_actions'] ?? 0 );
        $shares = self::channels( $team['final_third_channel_share'] ?? array() );

        if ( '' === $target || ! isset( $shares[ $target ] ) ) {
            $status = 'insufficient';
            $indicators = array(
                'prepared_target_channel' => null,
                'our_final_third_actions' => $actions,
                'our_final_third_channel_share' => $shares,
            );
            $interpretation = self::text( 'interp_weak_side_context', $language );
        } elseif ( $actions < 6 ) {
            $status = 'insufficient';
            $indicators = array(
                'prepared_target_channel' => $target,
                'our_final_third_actions' => $actions,
                'target_channel_share_pct' => $shares[ $target ],
                'our_final_third_channel_share' => $shares,
            );
            $interpretation = self::text( 'interp_weak_side_insufficient', $language );
        } elseif ( $shares[ $target ] >= 35.0 ) {
            $status = 'supported';
            $indicators = array(
                'prepared_target_channel' => $target,
                'our_final_third_actions' => $actions,
                'target_channel_share_pct' => $shares[ $target ],
                'our_final_third_channel_share' => $shares,
            );
            $interpretation = self::text( 'interp_weak_side_supported', $language );
        } elseif ( $shares[ $target ] >= 25.0 ) {
            $status = 'partially-supported';
            $indicators = array(
                'prepared_target_channel' => $target,
                'our_final_third_actions' => $actions,
                'target_channel_share_pct' => $shares[ $target ],
                'our_final_third_channel_share' => $shares,
            );
            $interpretation = self::text( 'interp_weak_side_partial', $language );
        } else {
            $status = 'not-supported';
            $indicators = array(
                'prepared_target_channel' => $target,
                'our_final_third_actions' => $actions,
                'target_channel_share_pct' => $shares[ $target ],
                'our_final_third_channel_share' => $shares,
            );
            $interpretation = self::text( 'interp_weak_side_not_supported', $language );
        }

        return self::signal(
            'weak_side_open',
            'exploit-opponent-weak-side',
            $status,
            $observed,
            $indicators,
            array(
                'supported' => 'prepared target channel share >= 35% of our final-third actions',
                'partial' => 'prepared target channel share >= 25%',
                'insufficient' => 'no persisted structured target channel, or our final_third_actions < 6',
            ),
            $interpretation,
            self::text( 'caveat_weak_side', $language ),
            $language
        );
    }

    private static function signal(
        string $observation,
        string $scenario_id,
        string $status,
        array $observed,
        array $indicators,
        array $thresholds,
        string $interpretation,
        string $caveat,
        string $language
    ): array {
        $counts = $observed['scenario_snapshot_counts'] ?? array();
        $first_seen = $observed['scenario_first_seen_minute'] ?? array();
        return array(
            'observation' => $observation,
            'scenario_id' => $scenario_id,
            'scenario_label' => self::text( 'scenario_' . $observation, $language ),
            'status' => $status,
            'status_label' => self::text( 'status_' . str_replace( '-', '_', $status ), $language ),
            'staff_observed' => (int) ( $counts[ $scenario_id ] ?? 0 ) > 0,
            'staff_observation_snapshots' => (int) ( $counts[ $scenario_id ] ?? 0 ),
            'staff_first_seen_minute' => isset( $first_seen[ $scenario_id ] ) ? (int) $first_seen[ $scenario_id ] : null,
            'indicators' => $indicators,
            'thresholds' => $thresholds,
            'interpretation' => $interpretation,
            'caveat' => $caveat,
        );
    }

    private static function comparison( bool $staff_observed, string $status, string $language ): array {
        if ( $staff_observed ) {
            if ( 'supported' === $status ) {
                $key = 'observed-and-supported';
            } elseif ( 'partially-supported' === $status ) {
                $key = 'observed-and-partially-supported';
            } elseif ( 'not-supported' === $status ) {
                $key = 'observed-not-supported-by-full-match-proxy';
            } else {
                $key = 'observed-evidence-insufficient';
            }
        } elseif ( in_array( $status, array( 'supported', 'partially-supported' ), true ) ) {
            $key = 'event-signal-not-recorded-by-staff';
        } elseif ( 'insufficient' === $status ) {
            $key = 'not-observed-evidence-insufficient';
        } else {
            $key = 'not-observed-no-event-signal';
        }

        return array(
            'status' => $key,
            'label' => self::text( 'comparison_' . str_replace( '-', '_', $key ), $language ),
        );
    }

    private static function prepared_weak_side_channel( array $playbook ): string {
        $fingerprints = $playbook['evidence']['scenario_fingerprints'] ?? array();
        if ( isset( $fingerprints['exploit-opponent-weak-side']['target_channel'] ) ) {
            return sanitize_key( (string) $fingerprints['exploit-opponent-weak-side']['target_channel'] );
        }
        return '';
    }

    private static function first_playbook( array $snapshots ): array {
        if ( empty( $snapshots ) ) {
            return array();
        }
        usort(
            $snapshots,
            static function( array $a, array $b ): int {
                $minute = (int) ( $a['minute'] ?? 0 ) <=> (int) ( $b['minute'] ?? 0 );
                return 0 !== $minute ? $minute : (int) ( $a['id'] ?? 0 ) <=> (int) ( $b['id'] ?? 0 );
            }
        );
        $playbook = $snapshots[0]['playbook'] ?? array();
        return is_array( $playbook ) ? $playbook : array();
    }

    private static function channels( array $channels ): array {
        return array(
            'left' => round( (float) ( $channels['left'] ?? 0.0 ), 1 ),
            'central' => round( (float) ( $channels['central'] ?? 0.0 ), 1 ),
            'right' => round( (float) ( $channels['right'] ?? 0.0 ), 1 ),
        );
    }

    private static function status_key( string $status ): string {
        return str_replace( '-', '_', $status );
    }

    private static function summary( array $signals, string $language ): array {
        $supported = 0;
        $partial = 0;
        $not_supported = 0;
        $insufficient = 0;
        $observed_supported = 0;
        foreach ( $signals as $signal ) {
            $status = (string) ( $signal['status'] ?? 'insufficient' );
            if ( 'supported' === $status ) {
                ++$supported;
            } elseif ( 'partially-supported' === $status ) {
                ++$partial;
            } elseif ( 'not-supported' === $status ) {
                ++$not_supported;
            } else {
                ++$insufficient;
            }
            if ( ! empty( $signal['staff_observed'] ) && in_array( $status, array( 'supported', 'partially-supported' ), true ) ) {
                ++$observed_supported;
            }
        }

        return array(
            sprintf( self::text( 'summary_supported', $language ), $supported, $partial ),
            sprintf( self::text( 'summary_observed_supported', $language ), $observed_supported ),
            sprintf( self::text( 'summary_not_supported', $language ), $not_supported ),
            sprintf( self::text( 'summary_insufficient', $language ), $insufficient ),
        );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'status_supported' => 'Supported by event proxy',
                'status_partially_supported' => 'Partially supported by event proxy',
                'status_not_supported' => 'Not supported by full-match event proxy',
                'status_insufficient' => 'Insufficient event/context evidence',
                'scenario_high_press' => 'Opponent high press',
                'scenario_low_block' => 'Opponent low/compact block',
                'scenario_transition_threat' => 'Opponent transition threat after our losses',
                'scenario_side_bias' => 'Opponent lateral bias',
                'scenario_weak_side_open' => 'Access to the prepared weak-side channel',
                'interp_high_press_supported' => 'The opponent recorded a substantial amount/share of pressure activity in the high zone, supporting the staff high-press observation at full-match level.',
                'interp_high_press_partially_supported' => 'There is some high-zone pressure activity, but the full-match event signal is not strong enough for the supported threshold.',
                'interp_high_press_not_supported' => 'The full-match pressure-event profile does not reach the heuristic high-press thresholds.',
                'interp_high_press_insufficient' => 'The event sample is insufficient to evaluate the high-press proxy.',
                'interp_low_block_supported' => 'A large share of opponent defensive actions occurred deep, which supports a low/compact-block interpretation at event level.',
                'interp_low_block_partially_supported' => 'The opponent defended deep often enough to provide a partial low-block signal, but not at the stronger threshold.',
                'interp_low_block_not_supported' => 'The full-match location of defensive actions does not reach the low-block proxy thresholds.',
                'interp_low_block_insufficient' => 'The event sample is insufficient to evaluate the low-block proxy.',
                'interp_transition_supported' => 'Our turnovers were followed by enough opponent progression, shots or xG within the defined short window to support a transition-threat signal.',
                'interp_transition_partially_supported' => 'Some immediate opponent threat followed our turnovers, but the full-match event signal is moderate.',
                'interp_transition_not_supported' => 'With enough recorded turnovers, the event window does not show a material immediate transition-threat signal.',
                'interp_transition_insufficient' => 'Too few relevant turnovers were recorded to judge this transition proxy reliably.',
                'interp_side_bias_supported' => 'The opponent showed a clear left/right imbalance in final-third actions at full-match level.',
                'interp_side_bias_partially_supported' => 'The opponent showed a moderate lateral imbalance in final-third actions.',
                'interp_side_bias_not_supported' => 'The opponent final-third distribution does not show a material left/right bias under the current thresholds.',
                'interp_side_bias_insufficient' => 'Too few opponent final-third actions were recorded to evaluate a lateral-bias proxy.',
                'interp_weak_side_context' => 'The stored session does not contain a structured pre-match weak-side target, so post-match access cannot be checked without reconstructing the past after the fact.',
                'interp_weak_side_insufficient' => 'A prepared target channel exists, but too few final-third actions were recorded to assess access.',
                'interp_weak_side_supported' => 'A substantial share of our final-third actions used the channel that was persisted as the pre-match weak-side target.',
                'interp_weak_side_partial' => 'We accessed the persisted target channel at a moderate rate during the match.',
                'interp_weak_side_not_supported' => 'Our final-third distribution shows limited use of the persisted pre-match target channel.',
                'caveat_high_press' => 'Pressure events and their locations are an event-data proxy; they do not measure team block height, pressing synchrony or every off-ball press.',
                'caveat_low_block' => 'Deep defensive-action share is a proxy for defensive territory, not a tracking-derived measurement of block height or compactness.',
                'caveat_transition' => 'Transition evidence uses fixed 8/10-second event windows after recorded turnovers and can miss off-ball or unrecorded dynamics.',
                'caveat_side_bias' => 'Final-third event distribution describes action location, not the full positional occupation of the opponent.',
                'caveat_weak_side' => 'Channel access uses our final-third action share; it does not prove that the opponent channel was structurally weak or that using it caused an advantage.',
                'comparison_observed_and_supported' => 'Staff observation and the full-match event proxy point in the same direction.',
                'comparison_observed_and_partially_supported' => 'Staff observation has some support from the full-match event proxy.',
                'comparison_observed_not_supported_by_full_match_proxy' => 'Staff recorded the behaviour, but the full-match event proxy does not reach its threshold. This is not proof that the live observation was wrong.',
                'comparison_observed_evidence_insufficient' => 'Staff recorded the behaviour, but the event/context evidence is insufficient for a post-match proxy check.',
                'comparison_event_signal_not_recorded_by_staff' => 'The full-match event proxy shows a signal that was not recorded in the sampled staff observations.',
                'comparison_not_observed_evidence_insufficient' => 'No staff observation is recorded and the event/context evidence is also insufficient.',
                'comparison_not_observed_no_event_signal' => 'No staff observation is recorded and the full-match event proxy does not show the signal.',
                'summary_supported' => '%d event proxies are supported and %d are partially supported.',
                'summary_observed_supported' => '%d staff-observed behaviours have supporting or partially supporting event evidence.',
                'summary_not_supported' => '%d proxies do not reach their full-match support thresholds.',
                'summary_insufficient' => '%d proxies remain insufficient because of event volume or missing persisted context.',
                'method' => 'This layer compares explicit staff observations with transparent full-match indicators derived from the imported event feed. It preserves both sources separately instead of replacing the live record.',
                'limit_not_tracking' => 'Event-derived evidence is not tracking data and must not be described as an objective measurement of full team shape, compactness or off-ball behaviour.',
                'limit_full_match' => 'v1.3 uses full-match aggregates, so it does not prove that an event signal occurred at the exact minute of a staff snapshot.',
                'limit_thresholds' => 'Thresholds are transparent product heuristics for audit support, not universal football laws or validated causal cut-offs.',
                'limit_causality' => 'Agreement between staff observations and event proxies does not establish that a tactical decision caused the match outcome.',
            ),
            'es' => array(
                'status_supported' => 'Respaldado por proxy de eventos',
                'status_partially_supported' => 'Respaldado parcialmente por proxy de eventos',
                'status_not_supported' => 'No respaldado por el proxy global del partido',
                'status_insufficient' => 'Evidencia de eventos/contexto insuficiente',
                'scenario_high_press' => 'Presión alta del rival',
                'scenario_low_block' => 'Bloque bajo/compacto del rival',
                'scenario_transition_threat' => 'Amenaza rival en transición tras nuestras pérdidas',
                'scenario_side_bias' => 'Sesgo lateral del rival',
                'scenario_weak_side_open' => 'Acceso al canal débil preparado',
                'interp_high_press_supported' => 'El rival registró una cantidad/proporción relevante de presiones en zona alta, respaldando a nivel global del partido la observación de presión alta.',
                'interp_high_press_partially_supported' => 'Existe actividad de presión en zona alta, pero la señal global del partido no alcanza el umbral fuerte.',
                'interp_high_press_not_supported' => 'El perfil global de eventos de presión no alcanza los umbrales heurísticos de presión alta.',
                'interp_high_press_insufficient' => 'La muestra de eventos es insuficiente para evaluar el proxy de presión alta.',
                'interp_low_block_supported' => 'Una proporción elevada de las acciones defensivas rivales se produjo en zona profunda, respaldando una lectura de bloque bajo/compacto a nivel de eventos.',
                'interp_low_block_partially_supported' => 'El rival defendió profundo con suficiente frecuencia para aportar una señal parcial de bloque bajo, pero no alcanza el umbral fuerte.',
                'interp_low_block_not_supported' => 'La localización global de acciones defensivas no alcanza los umbrales del proxy de bloque bajo.',
                'interp_low_block_insufficient' => 'La muestra de eventos es insuficiente para evaluar el proxy de bloque bajo.',
                'interp_transition_supported' => 'Nuestras pérdidas fueron seguidas por suficiente progresión, tiros o xG rival dentro de la ventana corta definida para respaldar una señal de amenaza en transición.',
                'interp_transition_partially_supported' => 'Se produjo cierta amenaza rival inmediata tras nuestras pérdidas, pero la señal global del partido es moderada.',
                'interp_transition_not_supported' => 'Con suficientes pérdidas registradas, la ventana de eventos no muestra una señal material de amenaza inmediata en transición.',
                'interp_transition_insufficient' => 'Hay demasiado pocas pérdidas relevantes registradas para evaluar este proxy de transición con fiabilidad.',
                'interp_side_bias_supported' => 'El rival mostró un desequilibrio claro izquierda/derecha en sus acciones de último tercio a nivel global del partido.',
                'interp_side_bias_partially_supported' => 'El rival mostró un desequilibrio lateral moderado en sus acciones de último tercio.',
                'interp_side_bias_not_supported' => 'La distribución rival en último tercio no muestra un sesgo izquierda/derecha material con los umbrales actuales.',
                'interp_side_bias_insufficient' => 'Hay demasiado pocas acciones rivales en último tercio para evaluar el proxy de sesgo lateral.',
                'interp_weak_side_context' => 'La sesión guardada no contiene un canal débil objetivo estructurado del prepartido, por lo que no podemos comprobar el acceso sin reconstruir el pasado a posteriori.',
                'interp_weak_side_insufficient' => 'Existe un canal objetivo preparado, pero hay demasiado pocas acciones en último tercio para evaluar el acceso.',
                'interp_weak_side_supported' => 'Una proporción relevante de nuestras acciones en último tercio utilizó el canal persistido como objetivo débil antes del partido.',
                'interp_weak_side_partial' => 'Accedimos al canal objetivo persistido con una frecuencia moderada durante el partido.',
                'interp_weak_side_not_supported' => 'Nuestra distribución en último tercio muestra un uso limitado del canal objetivo persistido antes del partido.',
                'caveat_high_press' => 'Los eventos de presión y su localización son un proxy de eventos; no miden altura global del bloque, sincronía de presión ni todas las presiones sin balón.',
                'caveat_low_block' => 'La proporción de acciones defensivas profundas es un proxy territorial, no una medición con tracking de altura o compactación del bloque.',
                'caveat_transition' => 'La evidencia de transición usa ventanas fijas de 8/10 segundos tras pérdidas registradas y puede omitir dinámicas sin balón o no registradas.',
                'caveat_side_bias' => 'La distribución de eventos en último tercio describe dónde ocurren acciones, no toda la ocupación posicional del rival.',
                'caveat_weak_side' => 'El acceso por canal usa nuestra proporción de acciones en último tercio; no demuestra que el canal rival fuese estructuralmente débil ni que usarlo causara una ventaja.',
                'comparison_observed_and_supported' => 'La observación del cuerpo técnico y el proxy global de eventos apuntan en la misma dirección.',
                'comparison_observed_and_partially_supported' => 'La observación del cuerpo técnico tiene respaldo parcial en el proxy global de eventos.',
                'comparison_observed_not_supported_by_full_match_proxy' => 'El cuerpo técnico registró el comportamiento, pero el proxy global del partido no alcanza su umbral. Esto no demuestra que la observación en vivo fuese errónea.',
                'comparison_observed_evidence_insufficient' => 'El cuerpo técnico registró el comportamiento, pero la evidencia de eventos/contexto es insuficiente para comprobarlo después.',
                'comparison_event_signal_not_recorded_by_staff' => 'El proxy global del partido muestra una señal que no consta en las observaciones muestreadas del cuerpo técnico.',
                'comparison_not_observed_evidence_insufficient' => 'No consta observación del cuerpo técnico y la evidencia de eventos/contexto también es insuficiente.',
                'comparison_not_observed_no_event_signal' => 'No consta observación del cuerpo técnico y el proxy global de eventos tampoco muestra la señal.',
                'summary_supported' => '%d proxies de eventos quedan respaldados y %d respaldados parcialmente.',
                'summary_observed_supported' => '%d comportamientos observados por el cuerpo técnico tienen respaldo total o parcial en eventos.',
                'summary_not_supported' => '%d proxies no alcanzan sus umbrales globales de respaldo.',
                'summary_insufficient' => '%d proxies siguen siendo insuficientes por volumen de eventos o falta de contexto persistido.',
                'method' => 'Esta capa compara observaciones explícitas del cuerpo técnico con indicadores transparentes derivados del feed de eventos del partido completo. Mantiene ambas fuentes separadas en lugar de sustituir el registro en vivo.',
                'limit_not_tracking' => 'La evidencia derivada de eventos no es tracking y no debe describirse como medición objetiva de toda la forma, compactación o comportamiento sin balón del equipo.',
                'limit_full_match' => 'v1.3 utiliza agregados del partido completo, por lo que no demuestra que una señal de eventos ocurriera exactamente en el minuto de un snapshot del cuerpo técnico.',
                'limit_thresholds' => 'Los umbrales son heurísticas transparentes de producto para apoyar la auditoría, no leyes universales del fútbol ni cortes causales validados.',
                'limit_causality' => 'La coincidencia entre observaciones del cuerpo técnico y proxies de eventos no establece que una decisión táctica causara el resultado del partido.',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
