<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Contingency_Playbook {
    public static function compose(
        array $match_plan,
        array $context,
        array $matchup_scan,
        string $language = 'en'
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );

        if ( 'match-plan' !== (string) ( $match_plan['plan_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'A TactiTwin Match Plan is required.' );
        }
        if ( 'full-matchup-scan' !== (string) ( $matchup_scan['scenario_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'A Full Matchup Scan is required.' );
        }

        $hypothesis = $context['hypothesis'] ?? array();
        $state      = (string) ( $hypothesis['state'] ?? 'drawing' );
        $manpower   = (string) ( $hypothesis['manpower_state'] ?? 'equal' );
        $minute     = max( 1, min( 120, (int) ( $hypothesis['minute'] ?? 1 ) ) );

        $plan_a = self::plan_a( $match_plan, $language );
        $plan_b = self::plan_b( $match_plan, $matchup_scan, $language );
        $plan_c = self::plan_c( $context, $language );
        $coverage = self::coverage( $match_plan, $context, $matchup_scan, $language );
        $active = self::active_plan( $state, $manpower, $minute, $coverage, $language );
        $tree = self::decision_tree( $match_plan, $context, $plan_b, $language );

        return array(
            'playbook_id'   => 'contingency-playbook',
            'playbook_name' => self::text( 'name', $language ),
            'language'      => $language,
            'team'          => $match_plan['team'] ?? array(),
            'opponent'      => $match_plan['opponent'] ?? array(),
            'current_context' => array(
                'minute'         => $minute,
                'our_score'      => (int) ( $hypothesis['our_score'] ?? 0 ),
                'opponent_score' => (int) ( $hypothesis['opponent_score'] ?? 0 ),
                'score_state'    => $state,
                'score_state_label' => (string) ( $hypothesis['state_label'] ?? $state ),
                'our_players'    => (int) ( $hypothesis['our_players'] ?? 11 ),
                'opponent_players' => (int) ( $hypothesis['opponent_players'] ?? 11 ),
                'manpower_state' => $manpower,
                'manpower_state_label' => (string) ( $hypothesis['manpower_state_label'] ?? $manpower ),
            ),
            'coverage' => $coverage,
            'active_plan' => $active,
            'plans' => array(
                'plan_a' => $plan_a,
                'plan_b' => $plan_b,
                'plan_c' => $plan_c,
            ),
            'decision_tree' => $tree,
            'recommendations' => self::recommendations( $active, $plan_a, $plan_b, $plan_c, $coverage, $language ),
            'evidence' => array(
                'match_plan_source' => 'match-plan',
                'context_source'    => (string) ( $context['method']['type'] ?? 'game-state-context' ),
                'matchup_source'    => 'full-matchup-scan',
                'armed_plan_b_scenario' => (string) ( $plan_b['scenario_id'] ?? '' ),
            ),
            'method' => array(
                'type' => 'deterministic-contingency-playbook',
                'precedence' => array(
                    'manpower imbalance => Plan C',
                    'late score state => Plan C',
                    'observed opponent trigger => Plan B',
                    'otherwise => Plan A',
                ),
                'note' => self::text( 'method', $language ),
                'limitations' => array(
                    self::text( 'limit_live', $language ),
                    self::text( 'limit_tracking', $language ),
                    self::text( 'limit_outcome', $language ),
                ),
            ),
        );
    }

    private static function plan_a( array $match_plan, string $language ): array {
        $phases = $match_plan['phases'] ?? array();
        $priorities = array_slice( $match_plan['priorities'] ?? array(), 0, 3 );

        return array(
            'id' => 'A',
            'name' => self::text( 'plan_a', $language ),
            'mode' => 'baseline',
            'status' => 'available',
            'purpose' => self::text( 'plan_a_purpose', $language ),
            'instructions' => self::unique_items(
                array_merge(
                    $phases['opening_15_minutes'] ?? array(),
                    $phases['in_possession'] ?? array(),
                    $phases['out_of_possession'] ?? array(),
                    $phases['after_loss'] ?? array()
                ),
                8
            ),
            'priority_scenarios' => $priorities,
            'exit_conditions' => array(
                self::text( 'exit_press', $language ),
                self::text( 'exit_low_block', $language ),
                self::text( 'exit_transition', $language ),
                self::text( 'exit_context', $language ),
            ),
        );
    }

    private static function plan_b( array $match_plan, array $scan, string $language ): array {
        $allowed = array(
            'build-up-vs-high-press',
            'attack-vs-low-block',
            'defensive-transition-after-loss',
            'opponent-side-bias',
            'exploit-opponent-weak-side',
        );
        $best = null;
        $best_risk = -1.0;
        foreach ( $scan['subscenarios'] ?? array() as $scenario ) {
            $id = (string) ( $scenario['scenario_id'] ?? '' );
            if ( ! in_array( $id, $allowed, true ) ) {
                continue;
            }
            $risk = (float) ( $scenario['risk']['score'] ?? 0.0 );
            if ( $risk > $best_risk ) {
                $best = $scenario;
                $best_risk = $risk;
            }
        }

        if ( ! is_array( $best ) || $best_risk < 45.0 ) {
            return array(
                'id' => 'B',
                'name' => self::text( 'plan_b', $language ),
                'mode' => 'observe',
                'status' => 'dormant',
                'scenario_id' => null,
                'scenario_name' => null,
                'risk_score' => max( 0.0, round( $best_risk, 1 ) ),
                'trigger' => self::text( 'plan_b_monitor_trigger', $language ),
                'instructions' => array( self::text( 'plan_b_monitor', $language ) ),
                'activation_rule' => self::text( 'plan_b_activation_guardrail', $language ),
            );
        }

        $scenario_id = (string) $best['scenario_id'];
        $trigger = self::match_plan_trigger( $match_plan['decision_triggers'] ?? array(), $scenario_id );
        $recommendations = array_values( array_filter( array_map( 'strval', $best['recommendations'] ?? array() ) ) );

        return array(
            'id' => 'B',
            'name' => self::text( 'plan_b', $language ),
            'mode' => self::scenario_mode( $scenario_id ),
            'status' => 'armed',
            'scenario_id' => $scenario_id,
            'scenario_name' => (string) ( $best['scenario_name'] ?? $scenario_id ),
            'risk_score' => round( $best_risk, 1 ),
            'trigger' => '' !== $trigger ? $trigger : self::text( 'plan_b_generic_trigger', $language ),
            'instructions' => self::unique_items( $recommendations, 5 ),
            'activation_rule' => self::text( 'plan_b_activation_guardrail', $language ),
        );
    }

    private static function plan_c( array $context, string $language ): array {
        $hypothesis = $context['hypothesis'] ?? array();
        $state = (string) ( $hypothesis['state'] ?? 'drawing' );
        $manpower = (string) ( $hypothesis['manpower_state'] ?? 'equal' );
        $minute = (int) ( $hypothesis['minute'] ?? 1 );

        $mode = 'standby';
        $trigger = self::text( 'plan_c_standby_trigger', $language );
        if ( 'inferior' === $manpower ) {
            $mode = 'manpower_inferior';
            $trigger = self::text( 'plan_c_inferior_trigger', $language );
        } elseif ( 'superior' === $manpower ) {
            $mode = 'manpower_superior';
            $trigger = self::text( 'plan_c_superior_trigger', $language );
        } elseif ( 'trailing' === $state && $minute >= 60 ) {
            $mode = 'chase';
            $trigger = self::text( 'plan_c_chase_trigger', $language );
        } elseif ( 'leading' === $state && $minute >= 70 ) {
            $mode = 'protect';
            $trigger = self::text( 'plan_c_protect_trigger', $language );
        }

        return array(
            'id' => 'C',
            'name' => self::text( 'plan_c', $language ),
            'mode' => $mode,
            'status' => 'standby' === $mode ? 'standby' : 'active_condition_met',
            'trigger' => $trigger,
            'instructions' => self::unique_items( $context['recommendations'] ?? array(), 7 ),
            'decision_triggers' => $context['decision_triggers'] ?? array(),
            'coverage' => $context['coverage'] ?? array(),
        );
    }

    private static function active_plan( string $state, string $manpower, int $minute, array $coverage, string $language ): array {
        $id = 'A';
        $mode = 'baseline';
        $reason_key = 'active_a';

        if ( 'inferior' === $manpower ) {
            $id = 'C';
            $mode = 'manpower_inferior';
            $reason_key = 'active_c_manpower_inferior';
        } elseif ( 'superior' === $manpower ) {
            $id = 'C';
            $mode = 'manpower_superior';
            $reason_key = 'active_c_manpower_superior';
        } elseif ( 'trailing' === $state && $minute >= 60 ) {
            $id = 'C';
            $mode = 'chase';
            $reason_key = 'active_c_chase';
        } elseif ( 'leading' === $state && $minute >= 70 ) {
            $id = 'C';
            $mode = 'protect';
            $reason_key = 'active_c_protect';
        }

        return array(
            'id' => $id,
            'mode' => $mode,
            'label' => 'A' === $id ? self::text( 'plan_a', $language ) : self::text( 'plan_c', $language ),
            'reason' => self::text( $reason_key, $language ),
            'confidence' => (string) ( $coverage['status'] ?? 'low' ),
            'note' => 'A' === $id ? self::text( 'plan_b_not_live', $language ) : null,
        );
    }

    private static function decision_tree( array $match_plan, array $context, array $plan_b, string $language ): array {
        $rows = array(
            array(
                'order' => 1,
                'condition' => self::text( 'tree_manpower', $language ),
                'target_plan' => 'C',
                'mode' => 'manpower',
            ),
            array(
                'order' => 2,
                'condition' => self::text( 'tree_trailing', $language ),
                'target_plan' => 'C',
                'mode' => 'chase',
            ),
            array(
                'order' => 3,
                'condition' => self::text( 'tree_leading', $language ),
                'target_plan' => 'C',
                'mode' => 'protect',
            ),
        );

        if ( 'armed' === (string) ( $plan_b['status'] ?? '' ) ) {
            $rows[] = array(
                'order' => 4,
                'condition' => (string) ( $plan_b['trigger'] ?? '' ),
                'target_plan' => 'B',
                'mode' => (string) ( $plan_b['mode'] ?? 'adaptation' ),
                'guardrail' => self::text( 'tree_b_guardrail', $language ),
            );
        }

        foreach ( $match_plan['decision_triggers'] ?? array() as $trigger ) {
            $scenario_id = (string) ( $trigger['scenario_id'] ?? '' );
            if ( '' === $scenario_id || $scenario_id === (string) ( $plan_b['scenario_id'] ?? '' ) ) {
                continue;
            }
            $rows[] = array(
                'order' => count( $rows ) + 1,
                'condition' => (string) ( $trigger['trigger'] ?? '' ),
                'target_plan' => 'B',
                'mode' => self::scenario_mode( $scenario_id ),
                'action' => (string) ( $trigger['action'] ?? '' ),
                'guardrail' => self::text( 'tree_b_guardrail', $language ),
            );
        }

        $rows[] = array(
            'order' => count( $rows ) + 1,
            'condition' => self::text( 'tree_default', $language ),
            'target_plan' => 'A',
            'mode' => 'baseline',
        );

        return $rows;
    }

    private static function coverage( array $match_plan, array $context, array $scan, string $language ): array {
        $plan_status = (string) ( $match_plan['coverage']['status'] ?? 'low' );
        $scan_status = (string) ( $scan['coverage']['status'] ?? 'low' );
        $context_status = (string) ( $context['coverage']['status'] ?? 'low' );
        $manpower = (string) ( $context['hypothesis']['manpower_state'] ?? 'equal' );
        $manpower_status = (string) ( $context['coverage']['manpower']['status'] ?? 'low' );

        $statuses = array( $plan_status, $scan_status, $context_status );
        if ( 'equal' !== $manpower ) {
            $statuses[] = $manpower_status;
        }
        $status = self::lowest_status( $statuses );

        return array(
            'status' => $status,
            'status_label' => TactiTwin_I18n::level_label( $status, $language ),
            'match_plan_status' => $plan_status,
            'matchup_scan_status' => $scan_status,
            'score_state_status' => $context_status,
            'manpower_status' => $manpower_status,
            'warning' => 'low' === $status ? self::text( 'coverage_low', $language ) : null,
        );
    }

    private static function recommendations( array $active, array $a, array $b, array $c, array $coverage, string $language ): array {
        $items = array();
        $items[] = sprintf( self::text( 'recommend_active', $language ), (string) $active['label'], (string) $active['reason'] );
        if ( 'armed' === (string) ( $b['status'] ?? '' ) ) {
            $items[] = sprintf( self::text( 'recommend_b_armed', $language ), (string) ( $b['scenario_name'] ?? '' ), (string) ( $b['trigger'] ?? '' ) );
        }
        if ( 'C' === (string) ( $active['id'] ?? 'A' ) ) {
            $items[] = sprintf( self::text( 'recommend_c_mode', $language ), (string) ( $c['mode'] ?? '' ) );
        }
        if ( 'low' === (string) ( $coverage['status'] ?? 'low' ) ) {
            $items[] = self::text( 'coverage_low', $language );
        }
        return array_values( array_unique( array_filter( $items ) ) );
    }

    private static function match_plan_trigger( array $triggers, string $scenario_id ): string {
        foreach ( $triggers as $trigger ) {
            if ( $scenario_id === (string) ( $trigger['scenario_id'] ?? '' ) ) {
                return (string) ( $trigger['trigger'] ?? '' );
            }
        }
        return '';
    }

    private static function scenario_mode( string $scenario_id ): string {
        $map = array(
            'build-up-vs-high-press' => 'escape_high_press',
            'attack-vs-low-block' => 'break_low_block',
            'defensive-transition-after-loss' => 'secure_transition',
            'opponent-side-bias' => 'protect_dominant_side',
            'exploit-opponent-weak-side' => 'attack_weak_side',
        );
        return $map[ $scenario_id ] ?? 'adaptation';
    }

    private static function lowest_status( array $statuses ): string {
        if ( in_array( 'low', $statuses, true ) ) {
            return 'low';
        }
        if ( in_array( 'medium', $statuses, true ) ) {
            return 'medium';
        }
        return 'high';
    }

    private static function unique_items( array $items, int $limit ): array {
        $values = array_values( array_unique( array_filter( array_map( 'strval', $items ) ) ) );
        return array_slice( $values, 0, max( 1, $limit ) );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'name' => 'Contingency Playbook',
                'plan_a' => 'Plan A — Baseline match plan',
                'plan_b' => 'Plan B — Opponent adaptation',
                'plan_c' => 'Plan C — Score/manpower contingency',
                'plan_a_purpose' => 'Start from the prepared match plan while validating the opponent assumptions in the opening phase.',
                'exit_press' => 'Switch toward Plan B if live observation confirms an aggressive first pressing line or short-build-up lock.',
                'exit_low_block' => 'Switch toward Plan B if the opponent settles into a persistent compact/deep defensive block.',
                'exit_transition' => 'Switch toward Plan B if repeated losses create immediate opponent progressions or shot threats.',
                'exit_context' => 'Switch to Plan C when the score/time or manpower state reaches a predefined contingency.',
                'plan_b_monitor_trigger' => 'No medium/high opponent behaviour risk is strong enough to arm a specific adaptation before kick-off.',
                'plan_b_monitor' => 'Keep the normal match plan and use live observation to identify whether a high-press, low-block, side-bias or transition adaptation becomes necessary.',
                'plan_b_activation_guardrail' => 'Plan B is armed by pre-match evidence but activates only after the staff observes the trigger in the match.',
                'plan_b_generic_trigger' => 'Activate only when the opponent behaviour associated with this scenario is observed live.',
                'plan_c_standby_trigger' => 'No score/time or manpower contingency is active yet.',
                'plan_c_inferior_trigger' => 'We are in numerical inferiority; Plan C overrides normal clock thresholds.',
                'plan_c_superior_trigger' => 'We are in numerical superiority; Plan C overrides normal clock thresholds.',
                'plan_c_chase_trigger' => 'We are trailing from minute 60 onward.',
                'plan_c_protect_trigger' => 'We are leading from minute 70 onward.',
                'active_a' => 'No automatic score/manpower contingency is active. Plan B remains armed only for live opponent triggers.',
                'active_c_manpower_inferior' => 'Numerical inferiority has precedence over the normal match plan.',
                'active_c_manpower_superior' => 'Numerical superiority has precedence over the normal match plan.',
                'active_c_chase' => 'The late trailing state activates the chase contingency.',
                'active_c_protect' => 'The late leading state activates the protect/control contingency.',
                'plan_b_not_live' => 'Historical opponent evidence can arm Plan B but cannot prove that its trigger is happening live.',
                'tree_manpower' => 'IF player counts are unequal, activate Plan C immediately and use the relevant manpower mode.',
                'tree_trailing' => 'ELSE IF trailing at minute 60 or later, activate Plan C in chase mode.',
                'tree_leading' => 'ELSE IF leading at minute 70 or later, activate Plan C in protect/control mode.',
                'tree_b_guardrail' => 'This branch requires live staff observation; pre-match risk alone does not activate it.',
                'tree_default' => 'ELSE stay on Plan A and continue validating the prepared assumptions.',
                'coverage_low' => 'Playbook evidence coverage is low. Keep the plans available, but treat triggers as rehearsal/observation prompts rather than strong selection or tactical assertions.',
                'recommend_active' => 'Current automatic plan: %s. %s',
                'recommend_b_armed' => 'Plan B is armed for %s. Live trigger: %s',
                'recommend_c_mode' => 'Plan C is active in `%s` mode; prioritize its context-specific instructions.',
                'method' => 'The playbook composes existing TactiTwin matchup, match-plan and score/manpower evidence into deterministic Plan A/B/C branches. It distinguishes pre-match risk from live observation and never auto-activates an opponent-behaviour branch without a live trigger input.',
                'limit_live' => 'TactiTwin does not currently receive live opponent-behaviour observations, so Plan B can be armed but not automatically verified as active.',
                'limit_tracking' => 'Without tracking/video, the playbook cannot verify real-time block geometry, synchronized pressing or off-ball rotations.',
                'limit_outcome' => 'Plan activation is decision support, not a forecast that the plan will improve the match result.',
            ),
            'es' => array(
                'name' => 'Playbook de contingencias',
                'plan_a' => 'Plan A — Plan base de partido',
                'plan_b' => 'Plan B — Adaptación al rival',
                'plan_c' => 'Plan C — Contingencia de marcador/superioridad',
                'plan_a_purpose' => 'Comenzar desde el plan preparado y validar durante la fase inicial los supuestos sobre el rival.',
                'exit_press' => 'Orientarse al Plan B si la observación en vivo confirma una primera línea de presión agresiva o bloqueo de la salida corta.',
                'exit_low_block' => 'Orientarse al Plan B si el rival se instala de forma persistente en un bloque compacto/profundo.',
                'exit_transition' => 'Orientarse al Plan B si pérdidas repetidas generan progresiones o amenazas de tiro inmediatas del rival.',
                'exit_context' => 'Cambiar al Plan C cuando marcador/minuto o estado numérico alcance una contingencia definida.',
                'plan_b_monitor_trigger' => 'Ningún riesgo de comportamiento rival de prioridad media/alta es suficiente para armar una adaptación específica antes del partido.',
                'plan_b_monitor' => 'Mantener el plan normal y usar la observación en vivo para detectar si hace falta una adaptación a presión alta, bloque bajo, sesgo lateral o transición.',
                'plan_b_activation_guardrail' => 'La evidencia previa arma el Plan B, pero solo se activa cuando el cuerpo técnico observa el disparador durante el partido.',
                'plan_b_generic_trigger' => 'Activar solo cuando se observe en vivo el comportamiento rival asociado a este escenario.',
                'plan_c_standby_trigger' => 'Todavía no está activa ninguna contingencia de marcador/minuto o superioridad numérica.',
                'plan_c_inferior_trigger' => 'Estamos en inferioridad numérica; el Plan C prevalece sobre los umbrales normales de minuto.',
                'plan_c_superior_trigger' => 'Estamos en superioridad numérica; el Plan C prevalece sobre los umbrales normales de minuto.',
                'plan_c_chase_trigger' => 'Vamos perdiendo desde el minuto 60 en adelante.',
                'plan_c_protect_trigger' => 'Vamos ganando desde el minuto 70 en adelante.',
                'active_a' => 'No hay una contingencia automática de marcador/superioridad activa. El Plan B queda armado únicamente para disparadores rivales observados en vivo.',
                'active_c_manpower_inferior' => 'La inferioridad numérica tiene prioridad sobre el plan normal de partido.',
                'active_c_manpower_superior' => 'La superioridad numérica tiene prioridad sobre el plan normal de partido.',
                'active_c_chase' => 'El estado de derrota avanzada activa la contingencia de remontada.',
                'active_c_protect' => 'El estado de ventaja avanzada activa la contingencia de protección/control.',
                'plan_b_not_live' => 'La evidencia histórica del rival puede armar el Plan B, pero no demuestra que el disparador esté ocurriendo en vivo.',
                'tree_manpower' => 'SI el número de jugadores es desigual, activar inmediatamente el Plan C con el modo numérico correspondiente.',
                'tree_trailing' => 'SI NO, SI vamos perdiendo desde el minuto 60, activar Plan C en modo remontada.',
                'tree_leading' => 'SI NO, SI vamos ganando desde el minuto 70, activar Plan C en modo protección/control.',
                'tree_b_guardrail' => 'Esta rama requiere observación en vivo del cuerpo técnico; el riesgo prepartido por sí solo no la activa.',
                'tree_default' => 'SI NO, mantener el Plan A y continuar validando los supuestos preparados.',
                'coverage_low' => 'La cobertura de evidencia del playbook es baja. Mantén los planes disponibles, pero usa los disparadores como guías de ensayo/observación y no como afirmaciones tácticas fuertes.',
                'recommend_active' => 'Plan automático actual: %s. %s',
                'recommend_b_armed' => 'El Plan B queda armado para %s. Disparador en vivo: %s',
                'recommend_c_mode' => 'El Plan C está activo en modo `%s`; prioriza sus instrucciones específicas del contexto.',
                'method' => 'El playbook compone la evidencia existente de matchup, plan de partido y marcador/superioridad en ramas deterministas Plan A/B/C. Distingue el riesgo previo de la observación en vivo y nunca activa automáticamente una rama de comportamiento rival sin un disparador observado.',
                'limit_live' => 'TactiTwin todavía no recibe observaciones en vivo del comportamiento rival, por lo que el Plan B puede quedar armado pero no verificarse automáticamente como activo.',
                'limit_tracking' => 'Sin tracking/vídeo, el playbook no puede verificar geometría real del bloque, presión sincronizada ni rotaciones sin balón.',
                'limit_outcome' => 'La activación de un plan es apoyo a la decisión, no una predicción de que mejorará el resultado.',
            ),
        );

        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
