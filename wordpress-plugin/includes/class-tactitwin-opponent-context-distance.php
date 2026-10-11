<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Opponent_Context_Distance {
    public static function compare( array $current, array $historical, string $language = 'en' ): array {
        $language = TactiTwin_I18n::normalize_language( $language );

        $current_id = (string) ( $current['team_source_id'] ?? '' );
        $historical_id = (string) ( $historical['team_source_id'] ?? '' );
        if ( '' === $current_id || '' === $historical_id ) {
            throw new InvalidArgumentException( 'Opponent context distance requires two team profiles with source identifiers.' );
        }

        $components = array(
            'pressing' => self::pressing( $current, $historical ),
            'defensive_block' => self::defensive_block( $current, $historical ),
            'attack_channels' => self::attack_channels( $current, $historical ),
            'transition_threat' => self::transition_threat( $current, $historical ),
            'progression_territory' => self::progression_territory( $current, $historical ),
        );
        $weights = array(
            'pressing' => 0.25,
            'defensive_block' => 0.15,
            'attack_channels' => 0.20,
            'transition_threat' => 0.20,
            'progression_territory' => 0.20,
        );

        $similarity = 0.0;
        foreach ( $weights as $key => $weight ) {
            $similarity += (float) ( $components[ $key ]['similarity_score'] ?? 0.0 ) * $weight;
        }
        $similarity = round( self::clamp( $similarity ), 1 );
        $distance = round( 100.0 - $similarity, 1 );
        $level = self::level( $similarity );
        $exact = $current_id === $historical_id;

        return array(
            'context_distance_id' => 'opponent-tactical-context-distance',
            'language' => $language,
            'current_opponent' => array(
                'source_id' => $current_id,
                'name' => (string) ( $current['team_name'] ?? $current_id ),
                'matches' => (int) ( $current['coverage']['matches'] ?? 0 ),
            ),
            'historical_opponent' => array(
                'source_id' => $historical_id,
                'name' => (string) ( $historical['team_name'] ?? $historical_id ),
                'matches' => (int) ( $historical['coverage']['matches'] ?? 0 ),
            ),
            'exact_opponent' => $exact,
            'similarity_score' => $similarity,
            'distance_score' => $distance,
            'similarity_level' => $level,
            'similarity_label' => self::text( 'level_' . str_replace( '-', '_', $level ), $language ),
            'components' => $components,
            'weights' => $weights,
            'coverage' => array(
                'current_matches' => (int) ( $current['coverage']['matches'] ?? 0 ),
                'historical_matches' => (int) ( $historical['coverage']['matches'] ?? 0 ),
                'effective_matches' => min(
                    (int) ( $current['coverage']['matches'] ?? 0 ),
                    (int) ( $historical['coverage']['matches'] ?? 0 )
                ),
            ),
            'method' => array(
                'type' => 'transparent-event-derived-opponent-context-distance',
                'exact_opponent_does_not_force_similarity' => true,
                'strength_model' => false,
                'embedding_model' => false,
                'changes_tactical_scores' => false,
                'changes_recommendations' => false,
                'changes_active_plan' => false,
                'note' => self::text( 'method', $language ),
                'limitations' => array(
                    self::text( 'limit_strength', $language ),
                    self::text( 'limit_tracking', $language ),
                    self::text( 'limit_sample', $language ),
                    self::text( 'limit_exact', $language ),
                ),
            ),
        );
    }

    private static function pressing( array $a, array $b ): array {
        return self::scalar_component(
            array(
                array( 'high_pressures_per_match', self::avg( $a, 'high_pressures_per_match' ), self::avg( $b, 'high_pressures_per_match' ), 18.0 ),
                array( 'high_recoveries_per_match', self::avg( $a, 'high_recoveries_per_match' ), self::avg( $b, 'high_recoveries_per_match' ), 4.0 ),
                array( 'high_pressure_share_pct', self::rate( $a, 'high_pressure_share_pct' ), self::rate( $b, 'high_pressure_share_pct' ), 50.0 ),
            )
        );
    }

    private static function defensive_block( array $a, array $b ): array {
        return self::scalar_component(
            array(
                array( 'deep_defensive_action_share_pct', self::rate( $a, 'deep_defensive_action_share_pct' ), self::rate( $b, 'deep_defensive_action_share_pct' ), 50.0 ),
                array( 'defensive_actions_per_match', self::avg( $a, 'defensive_actions_per_match' ), self::avg( $b, 'defensive_actions_per_match' ), 30.0 ),
            )
        );
    }

    private static function attack_channels( array $a, array $b ): array {
        $a_channels = is_array( $a['final_third_channel_share'] ?? null ) ? $a['final_third_channel_share'] : array();
        $b_channels = is_array( $b['final_third_channel_share'] ?? null ) ? $b['final_third_channel_share'] : array();
        $absolute = 0.0;
        $details = array();
        foreach ( array( 'left', 'central', 'right' ) as $channel ) {
            $av = (float) ( $a_channels[ $channel ] ?? 0.0 );
            $bv = (float) ( $b_channels[ $channel ] ?? 0.0 );
            $delta = round( $av - $bv, 1 );
            $absolute += abs( $delta );
            $details[] = array( 'feature'=>$channel, 'current'=>$av, 'historical'=>$bv, 'delta'=>$delta );
        }
        $distance = min( 100.0, $absolute / 2.0 );
        return array(
            'similarity_score' => round( 100.0 - $distance, 1 ),
            'distance_score' => round( $distance, 1 ),
            'features' => $details,
        );
    }

    private static function transition_threat( array $a, array $b ): array {
        return self::scalar_component(
            array(
                array( 'transition_progressions_created_per_match', self::avg( $a, 'transition_progressions_created_within_10s_per_match' ), self::avg( $b, 'transition_progressions_created_within_10s_per_match' ), 8.0 ),
                array( 'transition_shots_created_per_match', self::avg( $a, 'transition_shots_created_within_10s_per_match' ), self::avg( $b, 'transition_shots_created_within_10s_per_match' ), 3.0 ),
                array( 'transition_xg_created_per_match', self::avg( $a, 'transition_xg_created_within_10s_per_match' ), self::avg( $b, 'transition_xg_created_within_10s_per_match' ), 1.5 ),
            )
        );
    }

    private static function progression_territory( array $a, array $b ): array {
        return self::scalar_component(
            array(
                array( 'progressive_actions_per_match', self::avg( $a, 'progressive_actions_per_match' ), self::avg( $b, 'progressive_actions_per_match' ), 35.0 ),
                array( 'final_third_entries_per_match', self::avg( $a, 'final_third_entries_per_match' ), self::avg( $b, 'final_third_entries_per_match' ), 25.0 ),
                array( 'box_entries_per_match', self::avg( $a, 'box_entries_per_match' ), self::avg( $b, 'box_entries_per_match' ), 12.0 ),
                array( 'shots_per_match', self::avg( $a, 'shots_per_match' ), self::avg( $b, 'shots_per_match' ), 15.0 ),
            )
        );
    }

    private static function scalar_component( array $rows ): array {
        $features = array();
        $scores = array();
        foreach ( $rows as $row ) {
            [ $feature, $current, $historical, $scale ] = $row;
            $delta = (float) $current - (float) $historical;
            $similarity = self::scalar_similarity( (float) $current, (float) $historical, (float) $scale );
            $scores[] = $similarity;
            $features[] = array(
                'feature' => (string) $feature,
                'current' => round( (float) $current, 3 ),
                'historical' => round( (float) $historical, 3 ),
                'delta' => round( $delta, 3 ),
                'reference_scale' => (float) $scale,
                'similarity_score' => $similarity,
            );
        }
        $similarity = empty( $scores ) ? 0.0 : round( array_sum( $scores ) / count( $scores ), 1 );
        return array(
            'similarity_score' => $similarity,
            'distance_score' => round( 100.0 - $similarity, 1 ),
            'features' => $features,
        );
    }

    private static function scalar_similarity( float $a, float $b, float $scale ): float {
        if ( $scale <= 0.0 ) return 0.0;
        return round( 100.0 - min( 100.0, ( abs( $a - $b ) / $scale ) * 100.0 ), 1 );
    }

    private static function avg( array $profile, string $key ): float {
        return (float) ( $profile['averages'][ $key ] ?? 0.0 );
    }

    private static function rate( array $profile, string $key ): float {
        return (float) ( $profile['rates'][ $key ] ?? 0.0 );
    }

    private static function clamp( float $value ): float {
        return min( 100.0, max( 0.0, $value ) );
    }

    private static function level( float $similarity ): string {
        if ( $similarity >= 80.0 ) return 'very-high';
        if ( $similarity >= 65.0 ) return 'high';
        if ( $similarity >= 50.0 ) return 'medium';
        if ( $similarity >= 35.0 ) return 'low';
        return 'very-low';
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'level_very_high'=>'Very high','level_high'=>'High','level_medium'=>'Medium','level_low'=>'Low','level_very_low'=>'Very low',
                'method'=>'v1.11 compares two opponent profiles with transparent event-derived style features: pressing, defensive depth proxy, attack-channel distribution, transition threat and progression/territory. Similarity is a contextual evidence signal, not a prediction or team-strength rating.',
                'limit_strength'=>'This is not a team-strength rating. Two similarly styled opponents can differ greatly in player quality and competitive level.',
                'limit_tracking'=>'Event data cannot measure exact block height, compactness, line distances or all off-ball behaviour.',
                'limit_sample'=>'Similarity can be unstable when either opponent has a small or unrepresentative imported event sample.',
                'limit_exact'=>'Facing the same club does not force a similarity score of 100 because tactical style can change over time.',
            ),
            'es' => array(
                'level_very_high'=>'Muy alta','level_high'=>'Alta','level_medium'=>'Media','level_low'=>'Baja','level_very_low'=>'Muy baja',
                'method'=>'v1.11 compara dos perfiles de rival mediante variables de estilo transparentes derivadas de eventos: presión, proxy de profundidad defensiva, distribución por canales de ataque, amenaza en transición y progresión/territorio. La similitud es una señal contextual de evidencia, no una predicción ni un rating de fuerza.',
                'limit_strength'=>'No es un rating de fuerza. Dos rivales con estilo parecido pueden tener niveles de jugadores y competición muy diferentes.',
                'limit_tracking'=>'Los eventos no permiten medir altura exacta del bloque, compactación, distancias entre líneas ni todo el comportamiento sin balón.',
                'limit_sample'=>'La similitud puede ser inestable si cualquiera de los rivales tiene una muestra de eventos pequeña o poco representativa.',
                'limit_exact'=>'Jugar contra el mismo club no fuerza una similitud de 100 porque su estilo táctico puede cambiar con el tiempo.',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
