<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Context_What_If {
    public static function evaluate(
        array $team_profile,
        array $opponent_profile,
        array $scan,
        int $our_score,
        int $opponent_score,
        int $minute,
        string $language = 'en',
        int $our_players = 11,
        int $opponent_players = 11
    ): array {
        $language         = TactiTwin_I18n::normalize_language( $language );
        $our_players      = self::normalize_players( $our_players );
        $opponent_players = self::normalize_players( $opponent_players );

        $result = TactiTwin_Game_State_What_If::evaluate(
            $team_profile,
            $opponent_profile,
            $scan,
            $our_score,
            $opponent_score,
            $minute,
            $language
        );

        $state          = self::manpower_state( $our_players, $opponent_players );
        $opponent_state = self::reciprocal_state( $state );
        $our_history    = $team_profile['manpower_states'][ $state ] ?? self::empty_history();
        $their_history  = $opponent_profile['manpower_states'][ $opponent_state ] ?? self::empty_history();
        $coverage       = self::coverage( $our_history, $their_history, $language );

        $our_manpower_intensity   = self::threat_score( $our_history );
        $their_manpower_intensity = self::threat_score( $their_history );
        $applied                  = 'equal' !== $state && (float) $coverage['effective_minutes'] >= 45.0;

        $score_state_risk        = (float) ( $result['risk']['score'] ?? 0 );
        $score_state_opportunity = (float) ( $result['opportunity']['score'] ?? 0 );

        if ( $applied ) {
            $combined_risk        = round( ( $score_state_risk * 0.70 ) + ( $their_manpower_intensity * 0.30 ), 1 );
            $combined_opportunity = round( ( $score_state_opportunity * 0.70 ) + ( $our_manpower_intensity * 0.30 ), 1 );
            $result['risk']['score']              = $combined_risk;
            $result['risk']['level']              = self::level( $combined_risk );
            $result['risk']['level_label']        = TactiTwin_I18n::level_label( self::level( $combined_risk ), $language );
            $result['opportunity']['score']       = $combined_opportunity;
            $result['opportunity']['level']       = self::level( $combined_opportunity );
            $result['opportunity']['level_label'] = TactiTwin_I18n::level_label( self::level( $combined_opportunity ), $language );
        }

        $result['hypothesis']['our_players']             = $our_players;
        $result['hypothesis']['opponent_players']        = $opponent_players;
        $result['hypothesis']['manpower_state']          = $state;
        $result['hypothesis']['manpower_state_label']    = self::state_label( $state, $language );
        $result['hypothesis']['opponent_manpower_state'] = $opponent_state;
        $result['hypothesis']['opponent_manpower_state_label'] = self::state_label( $opponent_state, $language );
        $result['hypothesis']['headline'] = sprintf(
            self::text( 'headline', $language ),
            (int) ( $result['hypothesis']['minute'] ?? $minute ),
            (int) ( $result['hypothesis']['our_score'] ?? $our_score ),
            (int) ( $result['hypothesis']['opponent_score'] ?? $opponent_score ),
            $our_players,
            $opponent_players,
            (string) ( $result['hypothesis']['state_label'] ?? '' ),
            self::state_label( $state, $language )
        );

        $result['coverage']['manpower'] = $coverage;
        $result['historical_behavior']['our_manpower_state'] = self::public_history( $our_history );
        $result['historical_behavior']['opponent_reciprocal_manpower_state'] = self::public_history( $their_history );
        $result['historical_behavior']['our_manpower_intensity'] = $our_manpower_intensity;
        $result['historical_behavior']['opponent_manpower_intensity'] = $their_manpower_intensity;

        $result['recommendations'] = array_values(
            array_unique(
                array_filter(
                    array_merge(
                        $result['recommendations'] ?? array(),
                        self::manpower_recommendations( $state, $minute, $applied, $language )
                    )
                )
            )
        );
        $result['decision_triggers'] = array_merge(
            $result['decision_triggers'] ?? array(),
            self::manpower_triggers( $state, $coverage, $language )
        );

        $result['method']['type'] = 'historical-score-state-and-manpower-conditioned-what-if';
        $result['method']['manpower_adjustment'] = array(
            'state'                      => $state,
            'opponent_state'             => $opponent_state,
            'applied_to_scores'          => $applied,
            'minimum_effective_minutes'  => 45,
            'formula_when_applied'       => '0.70*score_state_intensity + 0.30*manpower_state_intensity',
            'score_state_risk'           => $score_state_risk,
            'manpower_risk'              => $their_manpower_intensity,
            'score_state_opportunity'    => $score_state_opportunity,
            'manpower_opportunity'       => $our_manpower_intensity,
            'note'                       => self::text( $applied ? 'method_applied' : 'method_not_applied', $language ),
        );
        $result['method']['limitations'][] = self::text( 'limit_personnel', $language );
        $result['method']['limitations'][] = self::text( 'limit_shape', $language );

        return $result;
    }

    private static function normalize_players( int $players ): int {
        return max( 7, min( 11, $players ) );
    }

    private static function manpower_state( int $ours, int $theirs ): string {
        if ( $ours > $theirs ) {
            return 'superior';
        }
        if ( $ours < $theirs ) {
            return 'inferior';
        }
        return 'equal';
    }

    private static function reciprocal_state( string $state ): string {
        if ( 'superior' === $state ) {
            return 'inferior';
        }
        if ( 'inferior' === $state ) {
            return 'superior';
        }
        return 'equal';
    }

    private static function coverage( array $our_history, array $their_history, string $language ): array {
        $our_minutes   = (float) ( $our_history['minutes'] ?? 0 );
        $their_minutes = (float) ( $their_history['minutes'] ?? 0 );
        $effective     = min( $our_minutes, $their_minutes );
        $status        = $effective >= 90.0 ? 'high' : ( $effective >= 45.0 ? 'medium' : 'low' );

        return array(
            'our_state_minutes'         => round( $our_minutes, 1 ),
            'opponent_state_minutes'    => round( $their_minutes, 1 ),
            'effective_minutes'         => round( $effective, 1 ),
            'our_matches_observed'      => (int) ( $our_history['matches_observed'] ?? 0 ),
            'opponent_matches_observed' => (int) ( $their_history['matches_observed'] ?? 0 ),
            'status'                    => $status,
            'status_label'              => TactiTwin_I18n::level_label( $status, $language ),
            'warning'                   => 'low' === $status ? self::text( 'coverage_low', $language ) : null,
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

    private static function manpower_recommendations( string $state, int $minute, bool $evidence_applied, string $language ): array {
        if ( 'inferior' === $state ) {
            $items = array(
                self::text( 'inferior_primary', $language ),
                self::text( 'inferior_press', $language ),
                self::text( 'inferior_outlet', $language ),
            );
            if ( $minute >= 70 ) {
                $items[] = self::text( 'inferior_late', $language );
            }
        } elseif ( 'superior' === $state ) {
            $items = array(
                self::text( 'superior_primary', $language ),
                self::text( 'superior_attack', $language ),
                self::text( 'superior_rest_defence', $language ),
            );
        } else {
            $items = array();
        }

        if ( 'equal' !== $state && ! $evidence_applied ) {
            $items[] = self::text( 'low_coverage_guardrail', $language );
        }

        return $items;
    }

    private static function manpower_triggers( string $state, array $coverage, string $language ): array {
        if ( 'equal' === $state ) {
            return array();
        }

        $items = array(
            array(
                'trigger' => self::text( 'trigger_red', $language ),
                'action'  => 'inferior' === $state
                    ? self::text( 'action_red_inferior', $language )
                    : self::text( 'action_red_superior', $language ),
            ),
        );

        if ( 'low' === (string) ( $coverage['status'] ?? 'low' ) ) {
            $items[] = array(
                'trigger' => self::text( 'trigger_low_coverage', $language ),
                'action'  => self::text( 'action_low_coverage', $language ),
            );
        }

        return $items;
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

    private static function state_label( string $state, string $language ): string {
        return self::text( 'state_' . $state, $language );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'headline' => 'Minute %d, score %d-%d, %dv%d: model the match with us %s and in numerical %s.',
                'state_superior' => 'superiority',
                'state_equal' => 'equality',
                'state_inferior' => 'inferiority',
                'coverage_low' => 'Low manpower-state exposure. Historical red-card evidence is shown but does not alter the headline intensity scores until both relevant samples reach 45 observed minutes.',
                'method_applied' => 'Historical numerical-state evidence has sufficient coverage and contributes 30% of the headline intensity indexes.',
                'method_not_applied' => 'Headline intensity remains based on score state because the match is numerically equal or manpower-state historical coverage is below the minimum threshold.',
                'limit_personnel' => 'The current manpower layer models player-count advantage/disadvantage, not which specific player was dismissed or substituted.',
                'limit_shape' => 'The formation and role redistribution after a dismissal are not inferred without lineup/tracking or explicit coach input.',
                'inferior_primary' => 'With a player disadvantage, protect central compactness first and reduce defensive actions that require long lateral recovery distances.',
                'inferior_press' => 'Press with explicit triggers rather than continuously: protect the inside lane, jump when the opponent receives closed or near the touchline, and avoid disconnected first-line pressure.',
                'inferior_outlet' => 'Keep at least one credible outlet after recovery so clearances can become controlled exits and the opponent cannot sustain permanent territorial pressure.',
                'inferior_late' => 'Late with ten players, choose deliberately between protecting the score and chasing it; do not combine an aggressive press with an unprotected last line.',
                'superior_primary' => 'Use the extra player to create width and a free receiver rather than forcing early central actions into a compact reduced block.',
                'superior_attack' => 'Move the opponent horizontally with circulation and switches, then attack the gap created by the delayed defensive shift with an overlap, underlap or third-man action.',
                'superior_rest_defence' => 'Keep counter-prevention discipline despite the numerical advantage; one uncontrolled loss can neutralize the value of the extra player.',
                'low_coverage_guardrail' => 'Because historical numerical-state coverage is limited, use the manpower recommendations as structural principles and validate the opponent response live before increasing their weight.',
                'trigger_red' => 'The match moves into a persistent numerical imbalance after a dismissal.',
                'action_red_inferior' => 'Rebuild the defensive reference points immediately: central protection, compact distances and one transition outlet before deciding the next pressing height.',
                'action_red_superior' => 'Stretch the reduced opponent block, circulate until a free player appears and keep enough cover behind the attack to prevent the counter.',
                'trigger_low_coverage' => 'Historical minutes in the relevant numerical states are below the evidence threshold.',
                'action_low_coverage' => 'Do not let the manpower intensity index override live observation; use the structural response and update the plan from what the opponent actually changes.',
            ),
            'es' => array(
                'headline' => 'Minuto %d, marcador %d-%d, %dv%d: modelamos el partido con nosotros %s y en %s numérica.',
                'state_superior' => 'superioridad',
                'state_equal' => 'igualdad',
                'state_inferior' => 'inferioridad',
                'coverage_low' => 'Cobertura histórica baja del estado numérico. La evidencia de expulsiones se muestra, pero no modifica los índices principales hasta que ambas muestras relevantes alcancen 45 minutos observados.',
                'method_applied' => 'La evidencia histórica del estado numérico tiene cobertura suficiente y aporta el 30% de los índices principales de intensidad.',
                'method_not_applied' => 'La intensidad principal continúa basada en el estado del marcador porque existe igualdad numérica o la cobertura histórica de superioridad/inferioridad está por debajo del mínimo.',
                'limit_personnel' => 'La capa numérica actual modela ventaja o desventaja por número de jugadores, no qué jugador concreto fue expulsado o sustituido.',
                'limit_shape' => 'La formación y la redistribución de roles después de una expulsión no se infieren sin alineaciones, tracking o una indicación explícita del entrenador.',
                'inferior_primary' => 'Con un jugador menos, protege primero la compactación central y reduce acciones defensivas que exijan recuperaciones laterales muy largas.',
                'inferior_press' => 'Presiona con disparadores explícitos y no de forma continua: protege el carril interior, salta cuando el rival recibe cerrado o cerca de banda y evita una primera línea desconectada.',
                'inferior_outlet' => 'Mantén al menos una salida creíble tras recuperar para convertir despejes en salidas controladas e impedir que el rival sostenga presión territorial permanente.',
                'inferior_late' => 'En tramo final con diez jugadores, decide de forma explícita entre proteger el marcador o buscarlo; no combines una presión agresiva con una última línea sin protección.',
                'superior_primary' => 'Utiliza el jugador extra para generar amplitud y un receptor libre en lugar de forzar acciones centrales tempranas contra un bloque reducido y compacto.',
                'superior_attack' => 'Mueve horizontalmente al rival con circulación y cambios de orientación y ataca después el espacio generado por la basculación tardía mediante overlap, underlap o tercer hombre.',
                'superior_rest_defence' => 'Mantén disciplina de defensa preventiva pese a la superioridad; una pérdida descontrolada puede neutralizar el valor del jugador extra.',
                'low_coverage_guardrail' => 'Como la cobertura histórica del estado numérico es limitada, utiliza estas recomendaciones como principios estructurales y valida en directo la respuesta rival antes de aumentar su peso.',
                'trigger_red' => 'El partido entra en un desequilibrio numérico persistente después de una expulsión.',
                'action_red_inferior' => 'Reconstruye inmediatamente las referencias defensivas: protección central, distancias compactas y una salida de transición antes de decidir la siguiente altura de presión.',
                'action_red_superior' => 'Estira el bloque reducido del rival, circula hasta encontrar al jugador libre y conserva suficiente cobertura detrás del ataque para evitar la contra.',
                'trigger_low_coverage' => 'Los minutos históricos en los estados numéricos relevantes están por debajo del umbral de evidencia.',
                'action_low_coverage' => 'No permitas que el índice de intensidad numérica sustituya la observación en directo; aplica la respuesta estructural y actualiza el plan según lo que realmente cambie el rival.',
            ),
        );

        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
