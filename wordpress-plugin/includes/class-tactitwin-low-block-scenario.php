<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Low_Block_Scenario {
    public static function evaluate( array $team_profile, array $opponent_profile, string $language = 'en' ): array {
        $language         = TactiTwin_I18n::normalize_language( $language );
        $team_matches     = (int) ( $team_profile['coverage']['matches'] ?? 0 );
        $opponent_matches = (int) ( $opponent_profile['coverage']['matches'] ?? 0 );
        $effective        = min( $team_matches, $opponent_matches );
        $coverage_status  = self::coverage_status( $effective );

        $team_avg             = $team_profile['averages'] ?? array();
        $team_rates           = $team_profile['rates'] ?? array();
        $team_channels        = self::channels( $team_profile['final_third_channel_share'] ?? array() );
        $opp_rates            = $opponent_profile['rates'] ?? array();
        $opp_avg              = $opponent_profile['averages'] ?? array();
        $opp_concessions      = $opponent_profile['opponent_context'] ?? array();
        $opp_channels_against = self::channels( $opponent_profile['final_third_channel_share_against'] ?? array() );

        $risk_components = array(
            'deep_block_proxy' => self::normalize(
                (float) ( $opp_rates['deep_defensive_action_share_pct'] ?? 0 ),
                45.0
            ),
            'box_access_denial' => self::inverse_normalize(
                (float) ( $opp_concessions['box_entries_per_match'] ?? 0 ),
                15.0
            ),
            'chance_suppression' => self::inverse_normalize(
                (float) ( $opp_concessions['xg_per_match'] ?? 0 ),
                1.50
            ),
            'shot_suppression' => self::inverse_normalize(
                (float) ( $opp_concessions['shots_per_match'] ?? 0 ),
                14.0
            ),
        );

        $risk_score = round(
            ( $risk_components['deep_block_proxy'] * 0.35 ) +
            ( $risk_components['box_access_denial'] * 0.25 ) +
            ( $risk_components['chance_suppression'] * 0.25 ) +
            ( $risk_components['shot_suppression'] * 0.15 ),
            1
        );

        $channel_balance = self::channel_balance( $team_channels );
        $opportunity_components = array(
            'box_access'      => self::normalize( (float) ( $team_avg['box_entries_per_match'] ?? 0 ), 15.0 ),
            'box_shot_share'  => self::clamp( (float) ( $team_rates['box_shot_share_pct'] ?? 0 ) ),
            'shot_quality'    => self::normalize( (float) ( $team_rates['xg_per_shot'] ?? 0 ), 0.14 ),
            'channel_balance' => $channel_balance,
        );

        $opportunity_score = round(
            ( $opportunity_components['box_access'] * 0.30 ) +
            ( $opportunity_components['box_shot_share'] * 0.25 ) +
            ( $opportunity_components['shot_quality'] * 0.25 ) +
            ( $opportunity_components['channel_balance'] * 0.20 ),
            1
        );

        $best_attack_channel     = self::max_channel( $team_channels );
        $best_concession_channel = self::max_channel( $opp_channels_against );

        return array(
            'scenario_id'   => 'attack-vs-low-block',
            'scenario_name' => self::text( 'name', $language ),
            'language'      => $language,
            'team' => array(
                'source_id' => (string) ( $team_profile['team_source_id'] ?? '' ),
                'name'      => (string) ( $team_profile['team_name'] ?? '' ),
            ),
            'opponent' => array(
                'source_id' => (string) ( $opponent_profile['team_source_id'] ?? '' ),
                'name'      => (string) ( $opponent_profile['team_name'] ?? '' ),
            ),
            'coverage' => array(
                'team_matches'      => $team_matches,
                'opponent_matches'  => $opponent_matches,
                'effective_matches' => $effective,
                'status'            => $coverage_status,
                'warning'           => 'low' === $coverage_status
                    ? TactiTwin_I18n::t( 'coverage.low', array(), $language )
                    : null,
            ),
            'risk' => array(
                'score'       => $risk_score,
                'level'       => self::level( $risk_score ),
                'level_label' => TactiTwin_I18n::level_label( self::level( $risk_score ), $language ),
                'components'  => $risk_components,
            ),
            'opportunity' => array(
                'score'       => $opportunity_score,
                'level'       => self::level( $opportunity_score ),
                'level_label' => TactiTwin_I18n::level_label( self::level( $opportunity_score ), $language ),
                'components'  => $opportunity_components,
            ),
            'evidence' => array(
                'opponent_deep_defensive_action_share_pct' => (float) ( $opp_rates['deep_defensive_action_share_pct'] ?? 0 ),
                'opponent_deep_defensive_actions_per_match'=> (float) ( $opp_avg['deep_defensive_actions_per_match'] ?? 0 ),
                'opponent_box_entries_conceded_per_match'   => (float) ( $opp_concessions['box_entries_per_match'] ?? 0 ),
                'opponent_shots_conceded_per_match'         => (float) ( $opp_concessions['shots_per_match'] ?? 0 ),
                'opponent_xg_conceded_per_match'            => (float) ( $opp_concessions['xg_per_match'] ?? 0 ),
                'our_final_third_entries_per_match'         => (float) ( $team_avg['final_third_entries_per_match'] ?? 0 ),
                'our_box_entries_per_match'                 => (float) ( $team_avg['box_entries_per_match'] ?? 0 ),
                'our_box_shot_share_pct'                    => (float) ( $team_rates['box_shot_share_pct'] ?? 0 ),
                'our_xg_per_shot'                           => (float) ( $team_rates['xg_per_shot'] ?? 0 ),
                'our_channel_balance_score'                 => $channel_balance,
                'our_dominant_attack_channel'               => $best_attack_channel,
                'opponent_most_conceded_channel'            => $best_concession_channel,
                'opponent_channel_share_conceded'           => $opp_channels_against,
            ),
            'recommendations' => self::recommendations(
                $risk_score,
                (float) ( $team_rates['box_shot_share_pct'] ?? 0 ),
                (float) ( $team_rates['xg_per_shot'] ?? 0 ),
                $channel_balance,
                $best_attack_channel,
                $best_concession_channel,
                $language
            ),
            'method' => array(
                'type' => 'event-data-low-block-proxy-heuristic',
                'note' => self::text( 'method', $language ),
                'tracking_required_for' => array( 'exact_defensive_line_height', 'inter-line_distance', 'team_compactness' ),
            ),
        );
    }

    private static function recommendations(
        float $risk,
        float $box_shot_share,
        float $xg_per_shot,
        float $channel_balance,
        string $dominant_channel,
        string $concession_channel,
        string $language
    ): array {
        $recommendations  = array();
        $dominant_label   = TactiTwin_I18n::channel_label( $dominant_channel, $language );
        $concession_label = TactiTwin_I18n::channel_label( $concession_channel, $language );

        if ( $risk >= 65.0 ) {
            $recommendations['primary'] = self::text( 'high_primary', $language );
        } elseif ( $risk >= 45.0 ) {
            $recommendations['primary'] = self::text( 'medium_primary', $language );
        } else {
            $recommendations['primary'] = self::text( 'low_primary', $language );
        }

        $recommendations['channel_plan'] = sprintf(
            self::text( 'channel_plan', $language ),
            $concession_label,
            $dominant_label
        );

        $recommendations['box_access'] = $box_shot_share < 60.0
            ? self::text( 'box_access_low', $language )
            : self::text( 'box_access_good', $language );

        $recommendations['shot_selection'] = $xg_per_shot < 0.10
            ? self::text( 'shot_quality_low', $language )
            : self::text( 'shot_quality_good', $language );

        $recommendations['circulation'] = $channel_balance < 65.0
            ? self::text( 'balance_low', $language )
            : self::text( 'balance_good', $language );

        $recommendations['rest_defence'] = self::text( 'rest_defence', $language );

        return $recommendations;
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'name' => 'Attack vs Low Block',
                'method' => 'This is an event-data low-block proxy based on where the opponent performs defensive actions and what opponents are allowed to produce. Exact defensive-line height, inter-line distances and compactness require tracking data.',
                'high_primary' => 'Expect a difficult settled attack. Stretch the block with maximum usable width, circulate patiently enough to move the defensive unit, and accelerate only when a half-space or box-access lane opens instead of forcing the first available cross.',
                'medium_primary' => 'Use controlled positional attack with frequent changes of orientation. Create a free player between the outside and inside defender before increasing tempo around the box.',
                'low_primary' => 'The current event sample does not show an extreme low-block suppression profile. Keep positional structure and attack the box without adding unnecessary circulation.',
                'channel_plan' => 'Test the opponent %s channel early because it is the route through which the imported sample concedes the largest share of final-third actions. Our current dominant route is %s, so use switches to avoid becoming predictable.',
                'box_access_low' => 'Too few of our shots come from inside the box. Prioritize cutbacks, third-man runs and receptions behind the midfield line before accepting low-value shots or early crosses.',
                'box_access_good' => 'Our box-shot share is healthy. Preserve the mechanisms that create close-range access and avoid replacing them with speculative shots when possession becomes prolonged.',
                'shot_quality_low' => 'Average xG per shot is low. Improve shot selection by creating one more pass, cutback or inside reception rather than shooting through a set block.',
                'shot_quality_good' => 'Shot quality is strong enough to support patience: keep moving the block until the same high-value zones become available rather than increasing shot volume from poor locations.',
                'balance_low' => 'Our final-third attack is channel-heavy. Deliberately rehearse switches and weak-side arrivals so the rival cannot overload the dominant corridor without consequence.',
                'balance_good' => 'Our channel distribution is reasonably balanced. Keep both sides active and use the central lane as a connector rather than allowing the block to predict the final route.',
                'rest_defence' => 'Because low-block attacks can create long possessions with many players ahead of the ball, preserve a stable rest-defence structure against the first clearance or counterattack pass.',
            ),
            'es' => array(
                'name' => 'Ataque vs bloque bajo',
                'method' => 'Este escenario utiliza un proxy de bloque bajo basado en datos de eventos: dónde realiza el rival sus acciones defensivas y qué producción permite a sus oponentes. La altura exacta de la línea defensiva, las distancias entre líneas y la compactación requieren datos de tracking.',
                'high_primary' => 'Prevé un ataque posicional exigente. Estira el bloque con la máxima amplitud útil, circula con paciencia para desplazar la estructura defensiva y acelera solo cuando se abra un intervalo en medio espacio o una vía de acceso al área, evitando forzar el primer centro disponible.',
                'medium_primary' => 'Utiliza un ataque posicional controlado con cambios frecuentes de orientación. Genera un jugador libre entre el defensor exterior y el interior antes de aumentar el ritmo alrededor del área.',
                'low_primary' => 'La muestra actual de eventos no refleja un perfil extremo de supresión en bloque bajo. Mantén la estructura posicional y ataca el área sin añadir circulación innecesaria.',
                'channel_plan' => 'Prueba pronto el canal %s del rival porque es la ruta por la que la muestra importada concede la mayor proporción de acciones en último tercio. Nuestra ruta dominante actual es la %s, por lo que conviene utilizar cambios de orientación para no resultar previsibles.',
                'box_access_low' => 'Una proporción insuficiente de nuestros tiros llega desde dentro del área. Prioriza pases atrás, movimientos de tercer hombre y recepciones a la espalda del centro del campo antes de aceptar tiros de poco valor o centros tempranos.',
                'box_access_good' => 'Nuestra proporción de tiros dentro del área es saludable. Conserva los mecanismos que generan accesos cercanos y evita sustituirlos por tiros especulativos cuando la posesión se prolongue.',
                'shot_quality_low' => 'El xG medio por tiro es bajo. Mejora la selección buscando un pase adicional, un pase atrás o una recepción interior antes de rematar contra un bloque ya organizado.',
                'shot_quality_good' => 'La calidad del tiro permite ser pacientes: sigue moviendo el bloque hasta que vuelvan a aparecer zonas de alto valor en lugar de aumentar el volumen desde posiciones peores.',
                'balance_low' => 'Nuestro ataque en último tercio está demasiado concentrado en un canal. Ensaya de forma deliberada cambios de orientación y llegadas por el lado débil para que el rival no pueda sobrecargar el corredor dominante sin coste.',
                'balance_good' => 'Nuestra distribución por canales es razonablemente equilibrada. Mantén activos ambos lados y utiliza el carril central como conector, evitando que el bloque pueda anticipar la ruta final.',
                'rest_defence' => 'Como los ataques contra bloque bajo pueden generar posesiones largas con muchos jugadores por delante del balón, conserva una estructura estable de defensa preventiva ante el primer despeje o pase de contraataque.',
            ),
        );

        $language = TactiTwin_I18n::normalize_language( $language );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }

    private static function channel_balance( array $channels ): float {
        if ( empty( $channels ) ) {
            return 0.0;
        }

        $max = max( $channels );
        $min = min( $channels );
        return self::clamp( 100.0 - ( ( $max - $min ) * 1.5 ) );
    }

    private static function channels( array $channels ): array {
        return array(
            'left'    => (float) ( $channels['left'] ?? 0 ),
            'central' => (float) ( $channels['central'] ?? 0 ),
            'right'   => (float) ( $channels['right'] ?? 0 ),
        );
    }

    private static function max_channel( array $channels ): string {
        if ( empty( $channels ) ) {
            return 'central';
        }
        arsort( $channels );
        return (string) array_key_first( $channels );
    }

    private static function inverse_normalize( float $value, float $reference ): float {
        return self::clamp( 100.0 - self::normalize( $value, $reference ) );
    }

    private static function normalize( float $value, float $reference ): float {
        return $reference > 0 ? self::clamp( ( $value / $reference ) * 100.0 ) : 0.0;
    }

    private static function clamp( float $value ): float {
        return max( 0.0, min( 100.0, round( $value, 1 ) ) );
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

    private static function coverage_status( int $matches ): string {
        if ( $matches >= 5 ) {
            return 'high';
        }
        if ( $matches >= 3 ) {
            return 'medium';
        }
        return 'low';
    }
}
