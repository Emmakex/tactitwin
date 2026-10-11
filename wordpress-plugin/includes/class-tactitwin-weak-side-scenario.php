<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Weak_Side_Scenario {
    public static function evaluate( array $team_profile, array $opponent_profile, string $language = 'en' ): array {
        $language         = TactiTwin_I18n::normalize_language( $language );
        $team_matches     = (int) ( $team_profile['coverage']['matches'] ?? 0 );
        $opponent_matches = (int) ( $opponent_profile['coverage']['matches'] ?? 0 );
        $effective        = min( $team_matches, $opponent_matches );
        $coverage_status  = self::coverage_status( $effective );

        $our_channels       = self::channels( $team_profile['final_third_channel_share'] ?? array() );
        $opponent_conceded  = self::channels( $opponent_profile['final_third_channel_share_against'] ?? array() );
        $our_dominant       = self::max_channel( $our_channels );
        $opponent_weak      = self::max_channel( $opponent_conceded );
        $channel_balance    = self::channel_balance( $our_channels );

        $matchup_scores = array();
        foreach ( array( 'left', 'central', 'right' ) as $channel ) {
            $matchup_scores[ $channel ] = round(
                ( $our_channels[ $channel ] * 0.45 ) +
                ( $opponent_conceded[ $channel ] * 0.55 ),
                1
            );
        }

        $best_matchup_channel = self::max_channel( $matchup_scores );
        $best_matchup_score   = (float) $matchup_scores[ $best_matchup_channel ];
        $current_score        = (float) $matchup_scores[ $our_dominant ];
        $switching_gain       = max( 0.0, round( $best_matchup_score - $current_score, 1 ) );
        $alignment            = $our_dominant === $opponent_weak;

        $risk_components = array(
            'attack_predictability' => self::clamp( 100.0 - $channel_balance ),
            'missed_weak_side'      => $alignment ? 0.0 : self::normalize( $switching_gain, 10.0 ),
            'dominant_channel_gap'  => $alignment
                ? 0.0
                : self::normalize(
                    max( 0.0, $opponent_conceded[ $opponent_weak ] - $opponent_conceded[ $our_dominant ] ),
                    20.0
                ),
        );

        $risk_score = round(
            ( $risk_components['attack_predictability'] * 0.35 ) +
            ( $risk_components['missed_weak_side'] * 0.40 ) +
            ( $risk_components['dominant_channel_gap'] * 0.25 ),
            1
        );

        $opportunity_components = array(
            'best_channel_matchup' => self::opportunity_from_matchup_score( $best_matchup_score ),
            'opponent_concession'  => self::normalize( $opponent_conceded[ $best_matchup_channel ], 50.0 ),
            'existing_capacity'    => self::normalize( $our_channels[ $best_matchup_channel ], 45.0 ),
        );

        $opportunity_score = round(
            ( $opportunity_components['best_channel_matchup'] * 0.45 ) +
            ( $opportunity_components['opponent_concession'] * 0.35 ) +
            ( $opportunity_components['existing_capacity'] * 0.20 ),
            1
        );

        return array(
            'scenario_id'   => 'exploit-opponent-weak-side',
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
                'channel'     => $best_matchup_channel,
            ),
            'evidence' => array(
                'our_final_third_channel_share'       => $our_channels,
                'opponent_channel_share_conceded'     => $opponent_conceded,
                'channel_matchup_scores'              => $matchup_scores,
                'our_dominant_channel'                => $our_dominant,
                'opponent_most_conceded_channel'      => $opponent_weak,
                'best_matchup_channel'                => $best_matchup_channel,
                'dominant_alignment'                  => $alignment,
                'channel_balance_score'               => $channel_balance,
                'potential_switching_gain_points'     => $switching_gain,
            ),
            'recommendations' => self::recommendations(
                $our_dominant,
                $opponent_weak,
                $best_matchup_channel,
                $alignment,
                $channel_balance,
                $switching_gain,
                $opponent_conceded[ $best_matchup_channel ],
                $language
            ),
            'method' => array(
                'type' => 'explainable-offensive-channel-matchup-heuristic',
                'note' => self::text( 'method', $language ),
            ),
        );
    }

    private static function recommendations(
        string $our_dominant,
        string $opponent_weak,
        string $best_matchup,
        bool $alignment,
        float $balance,
        float $switching_gain,
        float $concession_share,
        string $language
    ): array {
        $our_label  = TactiTwin_I18n::channel_label( $our_dominant, $language );
        $weak_label = TactiTwin_I18n::channel_label( $opponent_weak, $language );
        $best_label = TactiTwin_I18n::channel_label( $best_matchup, $language );

        $recommendations = array();
        $recommendations['primary'] = $alignment
            ? sprintf( self::text( 'primary_aligned', $language ), $best_label, $concession_share )
            : sprintf( self::text( 'primary_mismatch', $language ), $best_label, $concession_share, $our_label );

        $recommendations['creation_pattern'] = sprintf(
            self::text( 'creation_pattern', $language ),
            $best_label
        );

        if ( $switching_gain >= 5.0 ) {
            $recommendations['switch_trigger'] = sprintf(
                self::text( 'switch_gain', $language ),
                $switching_gain,
                $best_label
            );
        } else {
            $recommendations['switch_trigger'] = self::text( 'switch_small', $language );
        }

        $recommendations['balance'] = $balance < 65.0
            ? sprintf( self::text( 'balance_low', $language ), $our_label, $weak_label )
            : self::text( 'balance_good', $language );

        $recommendations['validation'] = self::text( 'validation', $language );

        return $recommendations;
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'name' => 'Exploit Opponent Weak Side',
                'method' => 'The model combines our observed final-third channel usage with the channels through which the opponent has conceded final-third actions. It identifies matchup opportunities, not a guaranteed tactical weakness.',
                'primary_aligned' => 'Our natural attack already aligns with the opponent vulnerability: prioritize the %s channel, where %.1f%% of their conceded final-third actions are observed.',
                'primary_mismatch' => 'Shift more attacks toward the %s channel, where %.1f%% of the opponent conceded final-third actions are observed, instead of defaulting to our current %s route.',
                'creation_pattern' => 'Build a repeatable access pattern into the %s channel: attract pressure on the opposite side, switch quickly, then combine with an underlap, overlap or third-man run before the block resets.',
                'switch_gain' => 'The matchup model estimates a %.1f-point channel advantage by moving toward the %s route. Use a clear switch trigger rather than circulating without purpose.',
                'switch_small' => 'The channel advantage is modest, so keep the weak-side route available without forcing possession there on every attack.',
                'balance_low' => 'Our attack is concentrated through the %s channel. Use the opponent %s concession profile to diversify the final action and reduce predictability.',
                'balance_good' => 'Our channel distribution is already balanced enough to manipulate the opponent without abandoning the strongest local matchup.',
                'validation' => 'Validate the channel in the opening phase of the match: if the opponent changes personnel, full-back height or cover behaviour, reduce the weight of the historical signal and update the plan.',
            ),
            'es' => array(
                'name' => 'Explotar el lado débil del rival',
                'method' => 'El modelo combina nuestro uso observado de los canales en último tercio con los canales por los que el rival ha concedido acciones en esa zona. Identifica oportunidades de emparejamiento, no una debilidad táctica garantizada.',
                'primary_aligned' => 'Nuestro ataque natural ya coincide con la vulnerabilidad del rival: prioriza el canal %s, por donde se observa el %.1f%% de sus acciones concedidas en último tercio.',
                'primary_mismatch' => 'Desplaza más ataques hacia el canal %s, por donde se observa el %.1f%% de las acciones de último tercio concedidas por el rival, en lugar de recurrir por defecto a nuestra ruta %s actual.',
                'creation_pattern' => 'Construye un patrón repetible de acceso al canal %s: atrae la presión en el lado contrario, cambia rápidamente de orientación y combina después con desdoblamiento interior, exterior o tercer hombre antes de que el bloque se reajuste.',
                'switch_gain' => 'El modelo de emparejamiento estima una ventaja de %.1f puntos al orientar el ataque hacia el canal %s. Utiliza un disparador claro de cambio de orientación en lugar de circular sin propósito.',
                'switch_small' => 'La ventaja entre canales es moderada, por lo que conviene mantener disponible la ruta débil sin forzar todas las posesiones hacia ese lado.',
                'balance_low' => 'Nuestro ataque está concentrado en el canal %s. Utiliza el perfil de concesiones del rival por el canal %s para diversificar la acción final y reducir la previsibilidad.',
                'balance_good' => 'Nuestra distribución por canales ya es suficientemente equilibrada para manipular al rival sin abandonar el emparejamiento local más favorable.',
                'validation' => 'Valida el canal durante la fase inicial del partido: si el rival cambia jugadores, altura del lateral o comportamiento de las coberturas, reduce el peso de la señal histórica y actualiza el plan.',
            ),
        );

        $language = TactiTwin_I18n::normalize_language( $language );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }

    private static function opportunity_from_matchup_score( float $score ): float {
        return self::clamp( ( ( $score - 25.0 ) / 25.0 ) * 100.0 );
    }

    private static function channel_balance( array $channels ): float {
        if ( empty( $channels ) ) {
            return 0.0;
        }
        return self::clamp( 100.0 - ( ( max( $channels ) - min( $channels ) ) * 1.5 ) );
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
