<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Personnel_What_If {
    public static function compare(
        array $player_out,
        array $player_in,
        int $minute = 60,
        string $language = 'en',
        array $match_context = array()
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $minute   = max( 1, min( 120, $minute ) );

        $deltas = self::deltas( $player_out, $player_in );
        $dimensions = array(
            'progression'        => self::progression_delta( $player_out, $player_in ),
            'ball_security'      => self::security_delta( $player_out, $player_in ),
            'threat'             => self::threat_delta( $player_out, $player_in ),
            'pressing'           => self::pressing_delta( $player_out, $player_in ),
            'defensive_activity' => self::defensive_delta( $player_out, $player_in ),
        );

        $effective_minutes = min(
            (float) ( $player_out['coverage']['minutes'] ?? 0 ),
            (float) ( $player_in['coverage']['minutes'] ?? 0 )
        );
        $confidence = self::confidence( $effective_minutes );
        $similarity = self::profile_similarity( $player_out, $player_in );

        return array(
            'scenario_id'   => 'personnel-what-if',
            'scenario_name' => self::text( 'name', $language ),
            'language'      => $language,
            'minute'        => $minute,
            'player_out'    => self::public_profile( $player_out ),
            'player_in'     => self::public_profile( $player_in ),
            'coverage'      => array(
                'effective_minutes' => round( $effective_minutes, 1 ),
                'confidence'        => $confidence,
                'confidence_label'  => TactiTwin_I18n::level_label( $confidence, $language ),
                'warning'           => 'low' === $confidence ? self::text( 'coverage_low', $language ) : null,
            ),
            'role' => array(
                'same_primary_position' => self::same_position( $player_out, $player_in ),
                'profile_similarity'    => $similarity,
                'meaning'               => self::text( 'similarity_meaning', $language ),
            ),
            'deltas'     => $deltas,
            'dimensions' => $dimensions,
            'recommendations' => self::recommendations(
                $player_out,
                $player_in,
                $deltas,
                $dimensions,
                $similarity,
                $minute,
                $confidence,
                $language
            ),
            'match_context' => self::context_summary( $match_context ),
            'method' => array(
                'type' => 'recent-event-profile-replacement-comparison',
                'note' => self::text( 'method', $language ),
                'limitations' => array(
                    self::text( 'limit_causal', $language ),
                    self::text( 'limit_off_ball', $language ),
                    self::text( 'limit_role', $language ),
                ),
            ),
        );
    }

    private static function deltas( array $out, array $in ): array {
        $out90 = $out['per_90'] ?? array();
        $in90  = $in['per_90'] ?? array();

        return array(
            'pass_completion_pp'          => round( (float) ( $in['rates']['pass_completion_pct'] ?? 0 ) - (float) ( $out['rates']['pass_completion_pct'] ?? 0 ), 1 ),
            'passes_per_90'                => self::delta( $out90, $in90, 'passes' ),
            'carries_per_90'               => self::delta( $out90, $in90, 'carries' ),
            'progressive_actions_per_90'   => self::delta( $out90, $in90, 'progressive_actions' ),
            'final_third_entries_per_90'   => self::delta( $out90, $in90, 'final_third_entries' ),
            'box_entries_per_90'           => self::delta( $out90, $in90, 'box_entries' ),
            'shots_per_90'                 => self::delta( $out90, $in90, 'shots' ),
            'xg_per_90'                    => self::delta( $out90, $in90, 'xg', 3 ),
            'pressures_per_90'             => self::delta( $out90, $in90, 'pressures' ),
            'recoveries_per_90'            => self::delta( $out90, $in90, 'recoveries' ),
            'duels_per_90'                 => self::delta( $out90, $in90, 'duels' ),
            'turnovers_per_90'             => self::delta( $out90, $in90, 'turnovers' ),
        );
    }

    private static function progression_delta( array $out, array $in ): float {
        $o = $out['per_90'] ?? array();
        $i = $in['per_90'] ?? array();
        return round(
            ( (float) ( $i['progressive_actions'] ?? 0 ) - (float) ( $o['progressive_actions'] ?? 0 ) ) * 0.55
            + ( (float) ( $i['final_third_entries'] ?? 0 ) - (float) ( $o['final_third_entries'] ?? 0 ) ) * 0.30
            + ( (float) ( $i['carries'] ?? 0 ) - (float) ( $o['carries'] ?? 0 ) ) * 0.15,
            2
        );
    }

    private static function security_delta( array $out, array $in ): float {
        $completion = ( (float) ( $in['rates']['pass_completion_pct'] ?? 0 ) - (float) ( $out['rates']['pass_completion_pct'] ?? 0 ) ) / 5.0;
        $turnovers  = (float) ( $out['per_90']['turnovers'] ?? 0 ) - (float) ( $in['per_90']['turnovers'] ?? 0 );
        return round( $completion + $turnovers, 2 );
    }

    private static function threat_delta( array $out, array $in ): float {
        $o = $out['per_90'] ?? array();
        $i = $in['per_90'] ?? array();
        return round(
            ( (float) ( $i['xg'] ?? 0 ) - (float) ( $o['xg'] ?? 0 ) ) * 8.0
            + ( (float) ( $i['shots'] ?? 0 ) - (float) ( $o['shots'] ?? 0 ) ) * 0.4
            + ( (float) ( $i['box_entries'] ?? 0 ) - (float) ( $o['box_entries'] ?? 0 ) ) * 0.35,
            2
        );
    }

    private static function pressing_delta( array $out, array $in ): float {
        return round( (float) ( $in['per_90']['pressures'] ?? 0 ) - (float) ( $out['per_90']['pressures'] ?? 0 ), 2 );
    }

    private static function defensive_delta( array $out, array $in ): float {
        return round(
            ( (float) ( $in['per_90']['recoveries'] ?? 0 ) - (float) ( $out['per_90']['recoveries'] ?? 0 ) )
            + 0.5 * ( (float) ( $in['per_90']['duels'] ?? 0 ) - (float) ( $out['per_90']['duels'] ?? 0 ) ),
            2
        );
    }

    private static function profile_similarity( array $out, array $in ): float {
        $vectors = array(
            array( 'progressive_actions', 10.0 ),
            array( 'final_third_entries', 8.0 ),
            array( 'box_entries', 6.0 ),
            array( 'xg', 0.8 ),
            array( 'pressures', 20.0 ),
            array( 'recoveries', 10.0 ),
            array( 'duels', 10.0 ),
            array( 'turnovers', 5.0 ),
        );
        $distance = 0.0;
        foreach ( $vectors as $vector ) {
            $key   = (string) $vector[0];
            $scale = (float) $vector[1];
            $a = (float) ( $out['per_90'][ $key ] ?? 0 );
            $b = (float) ( $in['per_90'][ $key ] ?? 0 );
            $distance += min( 1.0, abs( $a - $b ) / $scale );
        }
        $similarity = 100.0 * ( 1.0 - ( $distance / count( $vectors ) ) );
        if ( self::same_position( $out, $in ) ) {
            $similarity = min( 100.0, $similarity + 7.5 );
        }
        return round( max( 0.0, $similarity ), 1 );
    }

    private static function recommendations(
        array $out,
        array $in,
        array $deltas,
        array $dimensions,
        float $similarity,
        int $minute,
        string $confidence,
        string $language
    ): array {
        $items = array();
        $items[] = sprintf(
            self::text( 'swap', $language ),
            (string) ( $out['player_name'] ?? $out['player_source_id'] ?? '-' ),
            (string) ( $in['player_name'] ?? $in['player_source_id'] ?? '-' ),
            $minute
        );

        $positive = array_filter( $dimensions, static fn( $value ): bool => (float) $value > 1.0 );
        arsort( $positive );
        foreach ( array_slice( array_keys( $positive ), 0, 2 ) as $dimension ) {
            $items[] = sprintf( self::text( 'gain', $language ), self::dimension_label( $dimension, $language ) );
        }

        $negative = array_filter( $dimensions, static fn( $value ): bool => (float) $value < -1.0 );
        asort( $negative );
        foreach ( array_slice( array_keys( $negative ), 0, 2 ) as $dimension ) {
            $items[] = sprintf( self::text( 'tradeoff', $language ), self::dimension_label( $dimension, $language ) );
        }

        if ( (float) ( $deltas['turnovers_per_90'] ?? 0 ) > 1.0 ) {
            $items[] = self::text( 'turnover_risk', $language );
        }
        if ( $similarity < 65.0 ) {
            $items[] = self::text( 'role_change', $language );
        }
        if ( 'low' === $confidence ) {
            $items[] = self::text( 'low_sample', $language );
        }
        if ( count( $items ) === 1 ) {
            $items[] = self::text( 'neutral', $language );
        }

        return array_values( array_unique( $items ) );
    }

    private static function public_profile( array $profile ): array {
        return array(
            'player_source_id' => (string) ( $profile['player_source_id'] ?? '' ),
            'player_name'      => (string) ( $profile['player_name'] ?? '' ),
            'position_name'    => (string) ( $profile['position_name'] ?? '' ),
            'coverage'         => $profile['coverage'] ?? array(),
            'rates'            => $profile['rates'] ?? array(),
            'per_90'           => $profile['per_90'] ?? array(),
        );
    }

    private static function context_summary( array $context ): array {
        if ( empty( $context ) ) {
            return array();
        }
        return array(
            'hypothesis'  => $context['hypothesis'] ?? array(),
            'risk'        => $context['risk'] ?? array(),
            'opportunity' => $context['opportunity'] ?? array(),
            'scan_context'=> $context['scan_context'] ?? array(),
        );
    }

    private static function same_position( array $out, array $in ): bool {
        $a = strtolower( trim( (string) ( $out['position_name'] ?? '' ) ) );
        $b = strtolower( trim( (string) ( $in['position_name'] ?? '' ) ) );
        return '' !== $a && $a === $b;
    }

    private static function confidence( float $minutes ): string {
        if ( $minutes >= 450.0 ) {
            return 'high';
        }
        if ( $minutes >= 180.0 ) {
            return 'medium';
        }
        return 'low';
    }

    private static function delta( array $out, array $in, string $key, int $precision = 2 ): float {
        return round( (float) ( $in[ $key ] ?? 0 ) - (float) ( $out[ $key ] ?? 0 ), $precision );
    }

    private static function dimension_label( string $dimension, string $language ): string {
        return self::text( 'dimension_' . $dimension, $language );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'name' => 'Personnel What-if',
                'similarity_meaning' => 'Similarity compares recent event profiles and primary position. It is not a causal compatibility score.',
                'coverage_low' => 'One or both player profiles have fewer than 180 recent minutes. Treat the replacement comparison as exploratory.',
                'method' => 'The comparison uses each player’s recent on-pitch event profile normalized per 90 minutes and reports explicit deltas. It does not estimate a causal change in team win probability.',
                'limit_causal' => 'These are descriptive player-profile differences, not proof that replacing one player with the other causes the same team-level change.',
                'limit_off_ball' => 'Event data under-observes off-ball movement, marking, spacing and many defensive actions; tracking or video-derived positioning is required for those effects.',
                'limit_role' => 'A player used in a different tactical role may behave differently from the historical profile shown here.',
                'swap' => 'At minute %3$d, replacing %1$s with %2$s changes the recent event profile as follows.',
                'gain' => 'Potential gain: %s increases in the incoming player’s recent profile.',
                'tradeoff' => 'Trade-off: %s decreases versus the outgoing player’s recent profile.',
                'turnover_risk' => 'Ball-security caution: the incoming player records materially more dispossessions/miscontrols per 90 in the current sample.',
                'role_change' => 'This is not a like-for-like profile replacement. If used, update the surrounding roles instead of assuming the same tactical instructions transfer directly.',
                'low_sample' => 'Sample caution: keep the recommendation low-weight until more recent minutes are available or confirm it with video/coaching observation.',
                'neutral' => 'The recent profiles are relatively close on the measured dimensions; make the change primarily for context, freshness or a role-specific instruction rather than expecting a large statistical shift.',
                'dimension_progression' => 'progression',
                'dimension_ball_security' => 'ball security',
                'dimension_threat' => 'attacking threat',
                'dimension_pressing' => 'pressing activity',
                'dimension_defensive_activity' => 'defensive activity',
            ),
            'es' => array(
                'name' => 'What-if de jugadores',
                'similarity_meaning' => 'La similitud compara perfiles recientes de eventos y posición principal. No es una puntuación causal de compatibilidad.',
                'coverage_low' => 'Uno o ambos perfiles tienen menos de 180 minutos recientes. Trata la comparación de sustitución como exploratoria.',
                'method' => 'La comparación usa el perfil reciente de eventos de cada jugador mientras estuvo en campo, normalizado por 90 minutos, y muestra diferencias explícitas. No estima un cambio causal en la probabilidad de victoria.',
                'limit_causal' => 'Son diferencias descriptivas entre perfiles, no una demostración de que sustituir a un jugador por otro cause el mismo cambio a nivel de equipo.',
                'limit_off_ball' => 'Los datos de eventos observan de forma incompleta movimientos sin balón, marcajes, distancias y muchas acciones defensivas; para esos efectos necesitaremos tracking o posiciones derivadas de vídeo.',
                'limit_role' => 'Un jugador utilizado en un rol táctico diferente puede comportarse de forma distinta al perfil histórico mostrado.',
                'swap' => 'En el minuto %3$d, sustituir a %1$s por %2$s cambia el perfil reciente de eventos de la siguiente forma.',
                'gain' => 'Ganancia potencial: aumenta %s en el perfil reciente del jugador entrante.',
                'tradeoff' => 'Coste potencial: disminuye %s respecto al perfil reciente del jugador saliente.',
                'turnover_risk' => 'Precaución de seguridad con balón: el jugador entrante registra sensiblemente más pérdidas por desposesión/control defectuoso por 90 en la muestra actual.',
                'role_change' => 'No es un reemplazo de perfil equivalente. Si se realiza, adapta los roles alrededor en lugar de trasladar automáticamente las mismas instrucciones tácticas.',
                'low_sample' => 'Precaución por muestra: mantén poco peso en esta recomendación hasta disponer de más minutos recientes o confirmarla con vídeo/observación del cuerpo técnico.',
                'neutral' => 'Los perfiles recientes son relativamente próximos en las dimensiones medidas; realiza el cambio principalmente por contexto, frescura o una instrucción de rol específica, no esperando un gran desplazamiento estadístico.',
                'dimension_progression' => 'la progresión',
                'dimension_ball_security' => 'la seguridad con balón',
                'dimension_threat' => 'la amenaza ofensiva',
                'dimension_pressing' => 'la actividad de presión',
                'dimension_defensive_activity' => 'la actividad defensiva',
            ),
        );

        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
