<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Game_State_What_If {
    public static function evaluate(
        array $team_profile,
        array $opponent_profile,
        array $scan,
        int $our_score,
        int $opponent_score,
        int $minute,
        string $language = 'en'
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $minute   = max( 1, min( 120, $minute ) );
        $our_score = max( 0, $our_score );
        $opponent_score = max( 0, $opponent_score );

        $state          = self::state_from_score( $our_score, $opponent_score );
        $opponent_state = self::opponent_state( $state );
        $our_history    = $team_profile['game_states'][ $state ] ?? self::empty_history();
        $their_history  = $opponent_profile['game_states'][ $opponent_state ] ?? self::empty_history();

        $our_baseline   = self::baseline( $team_profile );
        $their_baseline = self::baseline( $opponent_profile );
        $our_ratios     = self::relative_intensity( $our_history, $our_baseline );
        $their_ratios   = self::relative_intensity( $their_history, $their_baseline );

        $risk_score        = self::threat_score( $their_history );
        $opportunity_score = self::threat_score( $our_history );
        $coverage          = self::coverage( $our_history, $their_history, $language );
        $top_risk          = self::top_scenario( $scan, 'risk' );
        $top_opportunity   = self::top_scenario( $scan, 'opportunity' );

        return array(
            'scenario_id'   => 'game-state-what-if',
            'scenario_name' => self::text( 'name', $language ),
            'language'      => $language,
            'team' => array(
                'id'   => (string) ( $team_profile['team_source_id'] ?? '' ),
                'name' => (string) ( $team_profile['team_name'] ?? '' ),
            ),
            'opponent' => array(
                'id'   => (string) ( $opponent_profile['team_source_id'] ?? '' ),
                'name' => (string) ( $opponent_profile['team_name'] ?? '' ),
            ),
            'hypothesis' => array(
                'minute'         => $minute,
                'our_score'      => $our_score,
                'opponent_score' => $opponent_score,
                'state'          => $state,
                'state_label'    => self::state_label( $state, $language ),
                'opponent_state' => $opponent_state,
                'opponent_state_label' => self::state_label( $opponent_state, $language ),
                'headline'       => sprintf(
                    self::text( 'headline', $language ),
                    $minute,
                    $our_score,
                    $opponent_score,
                    self::state_label( $state, $language )
                ),
            ),
            'coverage' => $coverage,
            'risk' => array(
                'score'       => $risk_score,
                'level'       => self::level( $risk_score ),
                'level_label' => TactiTwin_I18n::level_label( self::level( $risk_score ), $language ),
                'meaning'     => self::text( 'risk_meaning', $language ),
            ),
            'opportunity' => array(
                'score'       => $opportunity_score,
                'level'       => self::level( $opportunity_score ),
                'level_label' => TactiTwin_I18n::level_label( self::level( $opportunity_score ), $language ),
                'meaning'     => self::text( 'opportunity_meaning', $language ),
            ),
            'historical_behavior' => array(
                'our_state' => self::public_history( $our_history ),
                'opponent_reciprocal_state' => self::public_history( $their_history ),
                'our_state_vs_baseline' => $our_ratios,
                'opponent_state_vs_baseline' => $their_ratios,
            ),
            'recommendations' => self::recommendations(
                $state,
                $minute,
                $scan,
                $top_risk,
                $top_opportunity,
                $our_ratios,
                $their_ratios,
                $language
            ),
            'decision_triggers' => self::decision_triggers(
                $state,
                $minute,
                $our_ratios,
                $their_ratios,
                $language
            ),
            'scan_context' => array(
                'highest_risk_scenario' => self::scenario_ref( $top_risk, 'risk' ),
                'best_opportunity_scenario' => self::scenario_ref( $top_opportunity, 'opportunity' ),
            ),
            'method' => array(
                'type' => 'historical-score-state-conditioned-what-if',
                'note' => self::text( 'method', $language ),
                'limitations' => array(
                    self::text( 'limit_score', $language ),
                    self::text( 'limit_context', $language ),
                    self::text( 'limit_tracking', $language ),
                ),
            ),
        );
    }

    private static function recommendations(
        string $state,
        int $minute,
        array $scan,
        array $top_risk,
        array $top_opportunity,
        array $our_ratios,
        array $their_ratios,
        string $language
    ): array {
        $items = array();
        $best_opportunity_instruction = self::primary_recommendation( $top_opportunity );
        $top_risk_instruction         = self::primary_recommendation( $top_risk );

        if ( 'leading' === $state ) {
            $items['primary'] = self::text( 'leading_primary', $language );
            $items['possession'] = self::text( 'leading_possession', $language );
            $items['without_ball'] = self::text( 'leading_defence', $language );
            if ( $minute >= 70 ) {
                $items['late_game'] = self::text( 'leading_late', $language );
            }
        } elseif ( 'trailing' === $state ) {
            $items['primary'] = self::text( 'trailing_primary', $language );
            $items['possession'] = self::text( 'trailing_possession', $language );
            $items['without_ball'] = self::text( 'trailing_press', $language );
            if ( $minute >= 70 ) {
                $items['late_game'] = self::text( 'trailing_late', $language );
            }
        } else {
            $items['primary'] = self::text( 'drawing_primary', $language );
            $items['possession'] = self::text( 'drawing_possession', $language );
            $items['without_ball'] = self::text( 'drawing_defence', $language );
            if ( $minute >= 70 ) {
                $items['late_game'] = self::text( 'drawing_late', $language );
            }
        }

        if ( '' !== $best_opportunity_instruction ) {
            $items['best_opportunity'] = sprintf(
                self::text( 'apply_opportunity', $language ),
                (string) ( $top_opportunity['scenario_name'] ?? '-' ),
                $best_opportunity_instruction
            );
        }
        if ( '' !== $top_risk_instruction ) {
            $items['risk_guardrail'] = sprintf(
                self::text( 'risk_guardrail', $language ),
                (string) ( $top_risk['scenario_name'] ?? '-' ),
                $top_risk_instruction
            );
        }

        if ( (float) ( $their_ratios['high_pressures'] ?? 0 ) >= 1.2 ) {
            $items['press_adjustment'] = self::text( 'opponent_press_up', $language );
        }
        if ( (float) ( $our_ratios['xg'] ?? 1 ) < 0.8 && 'leading' !== $state ) {
            $items['chance_quality'] = self::text( 'our_xg_down', $language );
        }

        return array_values( array_unique( array_filter( $items ) ) );
    }

    private static function decision_triggers(
        string $state,
        int $minute,
        array $our_ratios,
        array $their_ratios,
        string $language
    ): array {
        $triggers = array();

        if ( (float) ( $their_ratios['high_pressures'] ?? 0 ) >= 1.2 ) {
            $triggers[] = array(
                'trigger' => self::text( 'trigger_press', $language ),
                'action'  => self::text( 'action_press', $language ),
            );
        }
        if ( (float) ( $their_ratios['box_entries'] ?? 0 ) >= 1.2 ) {
            $triggers[] = array(
                'trigger' => self::text( 'trigger_box', $language ),
                'action'  => self::text( 'action_box', $language ),
            );
        }
        if ( (float) ( $our_ratios['xg'] ?? 1 ) < 0.8 && 'leading' !== $state ) {
            $triggers[] = array(
                'trigger' => self::text( 'trigger_low_threat', $language ),
                'action'  => self::text( 'action_low_threat', $language ),
            );
        }
        if ( $minute >= 75 ) {
            $triggers[] = array(
                'trigger' => self::text( 'trigger_late', $language ),
                'action'  => 'leading' === $state
                    ? self::text( 'action_late_leading', $language )
                    : self::text( 'action_late_not_leading', $language ),
            );
        }

        if ( empty( $triggers ) ) {
            $triggers[] = array(
                'trigger' => self::text( 'trigger_default', $language ),
                'action'  => self::text( 'action_default', $language ),
            );
        }

        return $triggers;
    }

    private static function baseline( array $profile ): array {
        return array(
            'shots'               => (float) ( $profile['averages']['shots_per_match'] ?? 0 ),
            'xg'                  => (float) ( $profile['averages']['xg_per_match'] ?? 0 ),
            'progressive_actions' => (float) ( $profile['averages']['progressive_actions_per_match'] ?? 0 ),
            'final_third_entries' => (float) ( $profile['averages']['final_third_entries_per_match'] ?? 0 ),
            'box_entries'         => (float) ( $profile['averages']['box_entries_per_match'] ?? 0 ),
            'high_pressures'      => (float) ( $profile['averages']['high_pressures_per_match'] ?? 0 ),
        );
    }

    private static function relative_intensity( array $history, array $baseline ): array {
        $per90 = $history['per_90'] ?? array();
        $result = array();
        foreach ( array_keys( $baseline ) as $key ) {
            $base = (float) ( $baseline[ $key ] ?? 0 );
            $value = (float) ( $per90[ $key ] ?? 0 );
            $result[ $key ] = $base > 0.0 ? round( $value / $base, 2 ) : null;
        }
        return $result;
    }

    private static function threat_score( array $history ): float {
        $per90 = $history['per_90'] ?? array();
        $score =
            ( self::normalized( (float) ( $per90['xg'] ?? 0 ), 2.0 ) * 35.0 ) +
            ( self::normalized( (float) ( $per90['shots'] ?? 0 ), 18.0 ) * 15.0 ) +
            ( self::normalized( (float) ( $per90['box_entries'] ?? 0 ), 18.0 ) * 20.0 ) +
            ( self::normalized( (float) ( $per90['final_third_entries'] ?? 0 ), 35.0 ) * 15.0 ) +
            ( self::normalized( (float) ( $per90['high_pressures'] ?? 0 ), 25.0 ) * 15.0 );

        return round( min( 100.0, max( 0.0, $score ) ), 1 );
    }

    private static function coverage( array $our_history, array $their_history, string $language ): array {
        $our_minutes   = (float) ( $our_history['minutes'] ?? 0 );
        $their_minutes = (float) ( $their_history['minutes'] ?? 0 );
        $effective     = min( $our_minutes, $their_minutes );
        $status        = $effective >= 90.0 ? 'high' : ( $effective >= 45.0 ? 'medium' : 'low' );

        return array(
            'our_state_minutes'      => round( $our_minutes, 1 ),
            'opponent_state_minutes' => round( $their_minutes, 1 ),
            'effective_minutes'      => round( $effective, 1 ),
            'our_matches_observed'   => (int) ( $our_history['matches_observed'] ?? 0 ),
            'opponent_matches_observed' => (int) ( $their_history['matches_observed'] ?? 0 ),
            'status'                 => $status,
            'status_label'           => TactiTwin_I18n::level_label( $status, $language ),
            'warning'                => 'low' === $status ? self::text( 'coverage_low', $language ) : null,
        );
    }

    private static function public_history( array $history ): array {
        return array(
            'minutes'             => (float) ( $history['minutes'] ?? 0 ),
            'matches_observed'    => (int) ( $history['matches_observed'] ?? 0 ),
            'pass_completion_pct' => (float) ( $history['pass_completion_pct'] ?? 0 ),
            'per_90'              => $history['per_90'] ?? array(),
        );
    }

    private static function top_scenario( array $scan, string $field ): array {
        $best = array();
        $best_score = -1.0;
        foreach ( $scan['subscenarios'] ?? array() as $scenario ) {
            $score = (float) ( $scenario[ $field ]['score'] ?? 0 );
            if ( $score > $best_score ) {
                $best_score = $score;
                $best = $scenario;
            }
        }
        return $best;
    }

    private static function scenario_ref( array $scenario, string $field ): array {
        return array(
            'scenario_id'   => (string) ( $scenario['scenario_id'] ?? '' ),
            'scenario_name' => (string) ( $scenario['scenario_name'] ?? '' ),
            'score'         => (float) ( $scenario[ $field ]['score'] ?? 0 ),
        );
    }

    private static function primary_recommendation( array $scenario ): string {
        return (string) ( $scenario['recommendations']['primary'] ?? reset( $scenario['recommendations'] ) ?? '' );
    }

    private static function state_from_score( int $ours, int $theirs ): string {
        if ( $ours > $theirs ) {
            return 'leading';
        }
        if ( $ours < $theirs ) {
            return 'trailing';
        }
        return 'drawing';
    }

    private static function opponent_state( string $state ): string {
        if ( 'leading' === $state ) {
            return 'trailing';
        }
        if ( 'trailing' === $state ) {
            return 'leading';
        }
        return 'drawing';
    }

    private static function state_label( string $state, string $language ): string {
        return self::text( 'state_' . $state, $language );
    }

    private static function normalized( float $value, float $high_reference ): float {
        return $high_reference > 0.0 ? min( 1.0, max( 0.0, $value / $high_reference ) ) : 0.0;
    }

    private static function level( float $score ): string {
        if ( $score >= 65.0 ) {
            return 'high';
        }
        if ( $score >= 45.0 ) {
            return 'medium';
        }
        return 'low';
    }

    private static function empty_history(): array {
        return array(
            'minutes' => 0.0,
            'matches_observed' => 0,
            'pass_completion_pct' => 0.0,
            'per_90' => array(),
        );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'name' => 'Game State / What-if',
                'state_leading' => 'leading',
                'state_drawing' => 'drawing',
                'state_trailing' => 'trailing',
                'headline' => 'Minute %d, score %d-%d: model the match with us %s using historically observed score-state behaviour.',
                'risk_meaning' => 'Opponent attacking-intensity index in the reciprocal historical score state; this is not a goal-concession probability.',
                'opportunity_meaning' => 'Our attacking-intensity index in this historical score state; this is not a scoring probability.',
                'coverage_low' => 'Low score-state exposure. Treat this What-if as exploratory until both teams have at least 45 observed minutes in the relevant states.',
                'method' => 'The engine conditions recent event data on whether each team was leading, drawing or trailing, normalizes activity per 90 minutes and combines that evidence with the existing Full Matchup Scan.',
                'limit_score' => 'Historical conditioning currently uses score state (leading/drawing/trailing), not the exact historical scoreline such as 1-0 versus 2-0.',
                'limit_context' => 'Cards, substitutions, home/away split and exact opponent-specific game state are not yet conditioned in this version.',
                'limit_tracking' => 'Event data cannot measure exact line height, compactness or inter-line distances; those require tracking/video-derived positional data.',
                'leading_primary' => 'Protect the lead without collapsing automatically: keep a stable rest-defence structure, control central access and preserve at least one credible outlet so the opponent cannot attack continuously.',
                'leading_possession' => 'Use possession as a defensive tool, but avoid sterile circulation under pressure; progress when the prepared route is open and otherwise recycle with numerical security.',
                'leading_defence' => 'Defend the highest-risk opponent route from the scan while keeping the block connected enough to contest the second ball after clearances.',
                'leading_late' => 'Late-game priority: reduce simultaneous forward runs, protect the next transition and make the opponent restart attacks from less dangerous zones rather than defending the box for every remaining minute.',
                'drawing_primary' => 'Keep structural balance while actively targeting the best identified matchup; do not turn a level game into uncontrolled transition exchanges unless the risk-reward is deliberate.',
                'drawing_possession' => 'Increase attacks through the best opportunity channel while keeping enough rest defence to sustain pressure after the first action is defended.',
                'drawing_defence' => 'Preserve the defensive orientation from the top risk scenario and avoid giving the opponent its preferred progression route while searching for the winner.',
                'drawing_late' => 'Late-game priority: raise attacking intent selectively through the strongest matchup, but keep a clear stop-loss structure behind the ball for the first counterattack.',
                'trailing_primary' => 'Increase territorial pressure and final-third occupation, but do not remove all rest defence: the opponent is historically conditioned as leading and may accept lower possession to attack the spaces left behind.',
                'trailing_possession' => 'Accelerate access to the best attacking matchup, add presence between lines and in the box, and reduce low-value circulation that does not move the defensive block.',
                'trailing_press' => 'After loss, use a clear counterpress or recovery trigger instead of an undefined all-out press; deny the first forward pass before committing the next line.',
                'trailing_late' => 'Late-game priority: accept more attacking occupation, but keep at least one protected central rest-defence line so a single clearance does not end the attack or create an uncontested counter.',
                'apply_opportunity' => 'Apply the best current opportunity (%s): %s',
                'risk_guardrail' => 'Keep the main risk guardrail (%s): %s',
                'opponent_press_up' => 'The opponent historically increases high-pressure activity in this reciprocal score state; prepare the escape route before adding extra attacking numbers.',
                'our_xg_down' => 'Our historical xG production falls materially in this score state; prioritize chance quality and box access rather than simply increasing shot volume.',
                'trigger_press' => 'Opponent high-pressure activity is at least 20% above its overall baseline in this score state.',
                'action_press' => 'Use the prepared high-press escape and protect the next action after the first line is broken.',
                'trigger_box' => 'Opponent box-entry rate is at least 20% above its overall baseline in the reciprocal score state.',
                'action_box' => 'Protect central and cutback access first; do not allow game-state urgency to stretch the defensive block.',
                'trigger_low_threat' => 'Our xG/90 in this score state is at least 20% below our overall baseline.',
                'action_low_threat' => 'Shift attacks toward the best matchup and improve box occupation before accepting lower-quality shots.',
                'trigger_late' => 'The hypothetical match has reached minute 75 or later.',
                'action_late_leading' => 'Prioritize control, rest defence and field position while retaining a transition outlet.',
                'action_late_not_leading' => 'Increase attacking intent through the strongest matchup while keeping the first counterattack lane protected.',
                'trigger_default' => 'Live behaviour remains close to the historical score-state profile.',
                'action_default' => 'Maintain the state-specific plan and keep validating it against the next 5–10 minutes of live behaviour.',
            ),
            'es' => array(
                'name' => 'Estado del partido / What-if',
                'state_leading' => 'ganando',
                'state_drawing' => 'empatando',
                'state_trailing' => 'perdiendo',
                'headline' => 'Minuto %d, marcador %d-%d: modelamos el partido con nosotros %s utilizando el comportamiento histórico observado según el estado del marcador.',
                'risk_meaning' => 'Índice de intensidad ofensiva del rival en el estado histórico recíproco del marcador; no es una probabilidad de encajar gol.',
                'opportunity_meaning' => 'Índice de intensidad ofensiva propia en este estado histórico del marcador; no es una probabilidad de marcar.',
                'coverage_low' => 'Exposición baja a este estado del marcador. Trata este What-if como exploratorio hasta disponer de al menos 45 minutos observados para ambos equipos en los estados relevantes.',
                'method' => 'El motor condiciona los datos recientes de eventos según si cada equipo iba ganando, empatando o perdiendo, normaliza la actividad por 90 minutos y cruza esa evidencia con el Full Matchup Scan existente.',
                'limit_score' => 'El condicionamiento histórico actual utiliza el estado del marcador (ganando/empatando/perdiendo), no el marcador histórico exacto como 1-0 frente a 2-0.',
                'limit_context' => 'Tarjetas, sustituciones, local/visitante y el estado exacto frente a este rival todavía no se condicionan en esta versión.',
                'limit_tracking' => 'Los datos de eventos no permiten medir altura exacta de línea, compactación o distancias entre líneas; para ello necesitaremos tracking o posiciones derivadas de vídeo.',
                'leading_primary' => 'Protege la ventaja sin hundirte automáticamente: conserva una defensa preventiva estable, controla el acceso central y mantén al menos una salida creíble para impedir que el rival ataque de forma continua.',
                'leading_possession' => 'Utiliza la posesión también como herramienta defensiva, pero evita circular de forma estéril bajo presión; progresa cuando se abra la ruta preparada y, si no, recicla con seguridad numérica.',
                'leading_defence' => 'Defiende la ruta rival de mayor riesgo detectada por el análisis manteniendo el bloque suficientemente conectado para disputar la segunda jugada tras los despejes.',
                'leading_late' => 'Prioridad final: reduce incorporaciones simultáneas, protege la siguiente transición y obliga al rival a reiniciar desde zonas menos peligrosas en lugar de defender el área durante todos los minutos restantes.',
                'drawing_primary' => 'Mantén el equilibrio estructural mientras atacas activamente el mejor emparejamiento detectado; no conviertas un empate en un intercambio incontrolado de transiciones salvo que el riesgo sea deliberado.',
                'drawing_possession' => 'Aumenta los ataques por el mejor canal de oportunidad conservando suficiente defensa preventiva para sostener la presión si la primera acción es defendida.',
                'drawing_defence' => 'Mantén la orientación defensiva del escenario de mayor riesgo y evita conceder al rival su ruta de progresión preferida mientras buscamos el gol.',
                'drawing_late' => 'Prioridad final: aumenta la intención ofensiva de forma selectiva por el mejor emparejamiento, pero mantén una estructura clara detrás del balón para controlar la primera contra.',
                'trailing_primary' => 'Aumenta la presión territorial y la ocupación del último tercio, pero no elimines toda la defensa preventiva: el rival está condicionado como ganador y puede aceptar menos posesión para atacar los espacios que dejemos.',
                'trailing_possession' => 'Acelera el acceso al mejor emparejamiento ofensivo, añade presencia entre líneas y dentro del área y reduce la circulación de poco valor que no desplace el bloque defensivo.',
                'trailing_press' => 'Tras pérdida, utiliza un disparador claro de contrapresión o repliegue en vez de una presión total indefinida; niega el primer pase hacia delante antes de comprometer la siguiente línea.',
                'trailing_late' => 'Prioridad final: acepta mayor ocupación ofensiva, pero conserva al menos una línea central protegida de defensa preventiva para que un despeje no finalice el ataque ni genere una contra limpia.',
                'apply_opportunity' => 'Aplica la mejor oportunidad actual (%s): %s',
                'risk_guardrail' => 'Mantén la principal protección frente al riesgo (%s): %s',
                'opponent_press_up' => 'Históricamente el rival aumenta su actividad de presión alta en este estado recíproco; prepara la salida antes de añadir más jugadores al ataque.',
                'our_xg_down' => 'Nuestra producción histórica de xG cae de forma relevante en este estado; prioriza calidad de ocasión y acceso al área antes que aumentar simplemente el volumen de tiro.',
                'trigger_press' => 'La presión alta del rival está al menos un 20% por encima de su nivel general en este estado del marcador.',
                'action_press' => 'Utiliza la salida preparada contra presión alta y protege la siguiente acción después de superar la primera línea.',
                'trigger_box' => 'Las entradas del rival al área están al menos un 20% por encima de su nivel general en el estado recíproco.',
                'action_box' => 'Protege primero el acceso central y el pase atrás; no permitas que la urgencia del marcador estire el bloque defensivo.',
                'trigger_low_threat' => 'Nuestro xG/90 en este estado está al menos un 20% por debajo de nuestro nivel general.',
                'action_low_threat' => 'Desplaza el ataque hacia el mejor emparejamiento y mejora la ocupación del área antes de aceptar tiros de menor calidad.',
                'trigger_late' => 'El escenario hipotético ha alcanzado el minuto 75 o posterior.',
                'action_late_leading' => 'Prioriza control, defensa preventiva y territorio conservando una salida para la transición.',
                'action_late_not_leading' => 'Aumenta la intención ofensiva por el mejor emparejamiento manteniendo protegida la primera línea de contraataque.',
                'trigger_default' => 'El comportamiento en directo se mantiene próximo al perfil histórico de este estado del marcador.',
                'action_default' => 'Mantén el plan específico del estado y sigue validándolo con los siguientes 5–10 minutos de comportamiento real.',
            ),
        );

        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
