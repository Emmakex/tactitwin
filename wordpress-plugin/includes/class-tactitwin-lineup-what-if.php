<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Lineup_What_If {
    public static function compare(
        array $baseline_players,
        array $candidate_players,
        string $baseline_formation,
        string $candidate_formation,
        string $language = 'en',
        array $match_context = array()
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $baseline_formation = TactiTwin_Formation_Model::normalize( $baseline_formation );
        $candidate_formation = TactiTwin_Formation_Model::normalize( $candidate_formation );
        $baseline_template = TactiTwin_Formation_Model::template( $baseline_formation );
        $candidate_template = TactiTwin_Formation_Model::template( $candidate_formation );

        self::validate_lineup( $baseline_players, $baseline_template['roles'], 'baseline' );
        self::validate_lineup( $candidate_players, $candidate_template['roles'], 'candidate' );

        $baseline = self::lineup_profile( $baseline_players, $baseline_template['roles'] );
        $candidate = self::lineup_profile( $candidate_players, $candidate_template['roles'] );
        $dimension_delta = self::dimension_delta( $baseline['dimensions'], $candidate['dimensions'] );
        $structure_delta = TactiTwin_Formation_Model::structural_delta( $baseline_formation, $candidate_formation );
        $changes = self::changes( $baseline_players, $candidate_players, $baseline_template['roles'], $candidate_template['roles'] );
        $coverage = self::coverage( $baseline_players, $candidate_players, $language );

        return array(
            'scenario_id'   => 'lineup-formation-what-if',
            'scenario_name' => self::text( 'name', $language ),
            'language'      => $language,
            'baseline' => array(
                'formation' => $baseline_formation,
                'traits'    => $baseline_template['traits'],
                'roles'     => self::public_slots( $baseline_players, $baseline_template['roles'] ),
                'profile'   => $baseline,
            ),
            'candidate' => array(
                'formation' => $candidate_formation,
                'traits'    => $candidate_template['traits'],
                'roles'     => self::public_slots( $candidate_players, $candidate_template['roles'] ),
                'profile'   => $candidate,
            ),
            'changes' => $changes,
            'delta' => array(
                'dimensions' => $dimension_delta,
                'structure'  => $structure_delta,
                'role_fit'   => round( (float) $candidate['role_fit']['average'] - (float) $baseline['role_fit']['average'], 1 ),
            ),
            'coverage' => $coverage,
            'recommendations' => self::recommendations(
                $dimension_delta,
                $structure_delta,
                $baseline,
                $candidate,
                $changes,
                $coverage,
                $language
            ),
            'match_context' => self::context_summary( $match_context ),
            'method' => array(
                'type' => 'recent-player-profile-plus-nominal-formation-comparison',
                'note' => self::text( 'method', $language ),
                'limitations' => array(
                    self::text( 'limit_causal', $language ),
                    self::text( 'limit_tracking', $language ),
                    self::text( 'limit_role', $language ),
                ),
            ),
        );
    }

    private static function validate_lineup( array $players, array $roles, string $label ): void {
        if ( count( $players ) !== count( $roles ) || 11 !== count( $players ) ) {
            throw new InvalidArgumentException( ucfirst( $label ) . ' lineup must contain exactly 11 players.' );
        }
        $ids = array();
        foreach ( $players as $player ) {
            $id = (string) ( $player['player_source_id'] ?? '' );
            if ( '' === $id ) {
                throw new InvalidArgumentException( ucfirst( $label ) . ' lineup contains a player without source id.' );
            }
            if ( isset( $ids[ $id ] ) ) {
                throw new InvalidArgumentException( ucfirst( $label ) . ' lineup contains duplicate players.' );
            }
            $ids[ $id ] = true;
        }
    }

    private static function lineup_profile( array $players, array $roles ): array {
        $sum = array_fill_keys(
            array( 'passes', 'carries', 'progressive_actions', 'final_third_entries', 'box_entries', 'shots', 'xg', 'pressures', 'recoveries', 'duels', 'turnovers' ),
            0.0
        );
        $completion = 0.0;
        $completion_n = 0;
        $outfield = 0;
        $role_scores = array();
        $low_fit = array();

        foreach ( $players as $index => $player ) {
            $role = (string) ( $roles[ $index ] ?? '' );
            $fit = TactiTwin_Formation_Model::role_compatibility( (string) ( $player['position_name'] ?? '' ), $role );
            $role_scores[] = (float) $fit['score'];
            if ( 'low' === $fit['level'] ) {
                $low_fit[] = array(
                    'player_source_id' => (string) ( $player['player_source_id'] ?? '' ),
                    'player_name'      => (string) ( $player['player_name'] ?? '' ),
                    'role'             => $role,
                    'historical_position' => (string) ( $player['position_name'] ?? '' ),
                    'score'            => (float) $fit['score'],
                );
            }

            if ( 'GK' === $role ) {
                continue;
            }
            ++$outfield;
            $per90 = $player['per_90'] ?? array();
            foreach ( array_keys( $sum ) as $key ) {
                $sum[ $key ] += (float) ( $per90[ $key ] ?? 0 );
            }
            if ( isset( $player['rates']['pass_completion_pct'] ) ) {
                $completion += (float) $player['rates']['pass_completion_pct'];
                ++$completion_n;
            }
        }

        $divisor = max( 1, $outfield );
        $averages = array();
        foreach ( $sum as $key => $value ) {
            $averages[ $key ] = round( $value / $divisor, 'xg' === $key ? 3 : 2 );
        }
        $pass_completion = $completion_n > 0 ? round( $completion / $completion_n, 1 ) : 0.0;

        $dimensions = array(
            'progression' => round( $averages['progressive_actions'] + ( 0.45 * $averages['final_third_entries'] ) + ( 0.15 * $averages['carries'] ), 2 ),
            'ball_security' => round( $pass_completion - ( 3.0 * $averages['turnovers'] ), 2 ),
            'attacking_threat' => round( ( 7.0 * $averages['xg'] ) + ( 0.45 * $averages['shots'] ) + ( 0.30 * $averages['box_entries'] ), 2 ),
            'pressing_activity' => round( $averages['pressures'], 2 ),
            'defensive_activity' => round( $averages['recoveries'] + ( 0.50 * $averages['duels'] ), 2 ),
        );

        return array(
            'outfield_average_per_90' => $averages,
            'pass_completion_pct'     => $pass_completion,
            'dimensions'              => $dimensions,
            'role_fit' => array(
                'average' => ! empty( $role_scores ) ? round( array_sum( $role_scores ) / count( $role_scores ), 1 ) : 0.0,
                'minimum' => ! empty( $role_scores ) ? round( min( $role_scores ), 1 ) : 0.0,
                'low_fit_slots' => $low_fit,
            ),
        );
    }

    private static function dimension_delta( array $baseline, array $candidate ): array {
        $result = array();
        foreach ( array_keys( $baseline ) as $key ) {
            $result[ $key ] = round( (float) ( $candidate[ $key ] ?? 0 ) - (float) ( $baseline[ $key ] ?? 0 ), 2 );
        }
        return $result;
    }

    private static function changes( array $baseline, array $candidate, array $baseline_roles, array $candidate_roles ): array {
        $base_by_id = array();
        $candidate_by_id = array();
        foreach ( $baseline as $i => $player ) {
            $base_by_id[ (string) $player['player_source_id'] ] = array(
                'player_name' => (string) ( $player['player_name'] ?? '' ),
                'role' => (string) ( $baseline_roles[ $i ] ?? '' ),
            );
        }
        foreach ( $candidate as $i => $player ) {
            $candidate_by_id[ (string) $player['player_source_id'] ] = array(
                'player_name' => (string) ( $player['player_name'] ?? '' ),
                'role' => (string) ( $candidate_roles[ $i ] ?? '' ),
            );
        }

        $outgoing = array();
        foreach ( array_diff_key( $base_by_id, $candidate_by_id ) as $id => $row ) {
            $outgoing[] = array( 'player_source_id' => $id, 'player_name' => $row['player_name'], 'role' => $row['role'] );
        }
        $incoming = array();
        foreach ( array_diff_key( $candidate_by_id, $base_by_id ) as $id => $row ) {
            $incoming[] = array( 'player_source_id' => $id, 'player_name' => $row['player_name'], 'role' => $row['role'] );
        }
        $role_changes = array();
        foreach ( array_intersect_key( $base_by_id, $candidate_by_id ) as $id => $row ) {
            $new_role = (string) $candidate_by_id[ $id ]['role'];
            if ( $new_role !== (string) $row['role'] ) {
                $role_changes[] = array(
                    'player_source_id' => $id,
                    'player_name'      => $row['player_name'],
                    'from_role'        => (string) $row['role'],
                    'to_role'          => $new_role,
                );
            }
        }

        return array(
            'outgoing' => $outgoing,
            'incoming' => $incoming,
            'role_changes' => $role_changes,
            'personnel_changes' => max( count( $outgoing ), count( $incoming ) ),
        );
    }

    private static function coverage( array $baseline, array $candidate, string $language ): array {
        $minutes = array();
        foreach ( array_merge( $baseline, $candidate ) as $player ) {
            $minutes[] = (float) ( $player['coverage']['minutes'] ?? 0 );
        }
        $minimum = ! empty( $minutes ) ? min( $minutes ) : 0.0;
        $average = ! empty( $minutes ) ? array_sum( $minutes ) / count( $minutes ) : 0.0;
        $status = $minimum >= 180.0 && $average >= 450.0 ? 'high' : ( $minimum >= 90.0 && $average >= 180.0 ? 'medium' : 'low' );

        return array(
            'minimum_player_minutes' => round( $minimum, 1 ),
            'average_player_minutes' => round( $average, 1 ),
            'status' => $status,
            'status_label' => TactiTwin_I18n::level_label( $status, $language ),
            'warning' => 'low' === $status ? self::text( 'coverage_low', $language ) : null,
        );
    }

    private static function public_slots( array $players, array $roles ): array {
        $result = array();
        foreach ( $players as $i => $player ) {
            $fit = TactiTwin_Formation_Model::role_compatibility( (string) ( $player['position_name'] ?? '' ), (string) ( $roles[ $i ] ?? '' ) );
            $result[] = array(
                'role' => (string) ( $roles[ $i ] ?? '' ),
                'player_source_id' => (string) ( $player['player_source_id'] ?? '' ),
                'player_name' => (string) ( $player['player_name'] ?? '' ),
                'historical_position' => (string) ( $player['position_name'] ?? '' ),
                'role_fit' => $fit,
                'coverage_minutes' => (float) ( $player['coverage']['minutes'] ?? 0 ),
            );
        }
        return $result;
    }

    private static function recommendations(
        array $dimensions,
        array $structure,
        array $baseline,
        array $candidate,
        array $changes,
        array $coverage,
        string $language
    ): array {
        $items = array();
        $positive = array_filter( $dimensions, static fn( $value ): bool => (float) $value > 0.75 );
        arsort( $positive );
        foreach ( array_slice( array_keys( $positive ), 0, 2 ) as $key ) {
            $items[] = sprintf( self::text( 'gain', $language ), self::dimension_label( $key, $language ) );
        }
        $negative = array_filter( $dimensions, static fn( $value ): bool => (float) $value < -0.75 );
        asort( $negative );
        foreach ( array_slice( array_keys( $negative ), 0, 2 ) as $key ) {
            $items[] = sprintf( self::text( 'tradeoff', $language ), self::dimension_label( $key, $language ) );
        }
        if ( (float) ( $structure['double_pivot'] ?? 0 ) >= 30.0 ) {
            $items[] = self::text( 'double_pivot_up', $language );
        } elseif ( (float) ( $structure['double_pivot'] ?? 0 ) <= -30.0 ) {
            $items[] = self::text( 'double_pivot_down', $language );
        }
        if ( (float) ( $structure['width'] ?? 0 ) >= 8.0 ) {
            $items[] = self::text( 'width_up', $language );
        }
        if ( (float) ( $structure['front_line'] ?? 0 ) >= 10.0 ) {
            $items[] = self::text( 'front_up', $language );
        }
        if ( (float) ( $structure['rest_defence'] ?? 0 ) <= -10.0 ) {
            $items[] = self::text( 'rest_defence_down', $language );
        }
        if ( (float) ( $candidate['role_fit']['average'] ?? 0 ) + 5.0 < (float) ( $baseline['role_fit']['average'] ?? 0 ) ) {
            $items[] = self::text( 'role_fit_down', $language );
        }
        if ( ! empty( $candidate['role_fit']['low_fit_slots'] ) ) {
            $items[] = self::text( 'low_fit_slots', $language );
        }
        if ( (int) ( $changes['personnel_changes'] ?? 0 ) >= 4 ) {
            $items[] = self::text( 'many_changes', $language );
        }
        if ( 'low' === (string) ( $coverage['status'] ?? 'low' ) ) {
            $items[] = self::text( 'low_sample', $language );
        }
        if ( empty( $items ) ) {
            $items[] = self::text( 'neutral', $language );
        }
        return array_values( array_unique( $items ) );
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

    private static function dimension_label( string $key, string $language ): string {
        return self::text( 'dimension_' . $key, $language );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'name' => 'Lineup & Formation What-if',
                'method' => 'The comparison combines recent player event profiles with a transparent nominal-formation model. Player metrics are descriptive and formation structure is rule-based; neither is a causal estimate of match outcome.',
                'limit_causal' => 'A higher lineup dimension does not mean the candidate XI will cause a corresponding increase in team performance or win probability.',
                'limit_tracking' => 'Formation structure is nominal. Without tracking/video, spacing, synchronization, off-ball occupation and real team distances are not observed.',
                'limit_role' => 'Historical player positions approximate role familiarity; a coach may deliberately assign a different role that event history does not capture.',
                'coverage_low' => 'At least one selected player has limited recent minutes. Treat lineup-level deltas as exploratory and validate them with video/coaching knowledge.',
                'gain' => 'Potential lineup gain: %s rises in the candidate XI recent-profile aggregate.',
                'tradeoff' => 'Potential lineup trade-off: %s falls in the candidate XI recent-profile aggregate.',
                'double_pivot_up' => 'The candidate structure adds a clearer double-pivot reference; use it to improve first-pass support and rest-defence occupation rather than simply placing two players on the same horizontal line.',
                'double_pivot_down' => 'The candidate structure removes a double-pivot reference; define who protects the first progression lane and who secures losses behind the ball.',
                'width_up' => 'The candidate formation has more nominal width. Coordinate the wide players so both do not attack the same height and expose the same transition lane.',
                'front_up' => 'The candidate structure commits more presence to the front line. Protect rest defence and the first counterpress zone before increasing directness.',
                'rest_defence_down' => 'The candidate formation has a weaker nominal rest-defence structure. Add explicit cover rules behind attacks and wide rotations.',
                'role_fit_down' => 'Average historical role familiarity falls meaningfully in the candidate XI. Treat the change as a role redesign, not only a personnel swap.',
                'low_fit_slots' => 'One or more candidate slots have low historical role compatibility. Review those assignments individually before using the aggregate comparison.',
                'many_changes' => 'The candidate changes four or more players. Interaction uncertainty is high; stage the tactical changes in training rather than interpreting the deltas as additive effects.',
                'low_sample' => 'Low recent-minute coverage reduces confidence in the lineup comparison.',
                'neutral' => 'The measured lineup profiles are close. Prefer the variant that best matches the opponent-specific plan and the coaching intent.',
                'dimension_progression' => 'progression',
                'dimension_ball_security' => 'ball security',
                'dimension_attacking_threat' => 'attacking threat',
                'dimension_pressing_activity' => 'pressing activity',
                'dimension_defensive_activity' => 'defensive activity',
            ),
            'es' => array(
                'name' => 'What-if de XI y formación',
                'method' => 'La comparación combina perfiles recientes de eventos de los jugadores con un modelo transparente de formación nominal. Las métricas de jugador son descriptivas y la estructura del dibujo se basa en reglas; ninguna de las dos es una estimación causal del resultado.',
                'limit_causal' => 'Una dimensión más alta del XI no significa que la variante vaya a causar un aumento equivalente del rendimiento colectivo o de la probabilidad de ganar.',
                'limit_tracking' => 'La estructura de la formación es nominal. Sin tracking o vídeo no observamos distancias reales, sincronización, ocupación sin balón ni espaciamiento colectivo.',
                'limit_role' => 'Las posiciones históricas aproximan familiaridad con el rol; el entrenador puede asignar deliberadamente un rol distinto que el histórico de eventos no refleja.',
                'coverage_low' => 'Al menos un jugador seleccionado tiene pocos minutos recientes. Trata los deltas del XI como exploratorios y valídalos con vídeo y conocimiento del cuerpo técnico.',
                'gain' => 'Ganancia potencial del XI: aumenta %s en el agregado de perfiles recientes de la variante.',
                'tradeoff' => 'Coste potencial del XI: disminuye %s en el agregado de perfiles recientes de la variante.',
                'double_pivot_up' => 'La variante añade una referencia más clara de doble pivote; úsala para mejorar apoyo a la primera salida y defensa preventiva, no simplemente para colocar dos jugadores en la misma altura.',
                'double_pivot_down' => 'La variante elimina una referencia de doble pivote; define quién protege el primer carril de progresión y quién asegura las pérdidas por detrás del balón.',
                'width_up' => 'La formación candidata ofrece más amplitud nominal. Coordina a los jugadores exteriores para que no ataquen la misma altura ni expongan el mismo carril de transición.',
                'front_up' => 'La estructura candidata compromete más presencia en primera línea. Protege la defensa preventiva y la primera zona de contrapresión antes de aumentar la verticalidad.',
                'rest_defence_down' => 'La formación candidata tiene una defensa preventiva nominalmente más débil. Añade reglas explícitas de cobertura detrás del ataque y de las rotaciones exteriores.',
                'role_fit_down' => 'La familiaridad histórica media con los roles cae de forma relevante en la variante. Trátalo como un rediseño de roles, no solo como cambios de nombres.',
                'low_fit_slots' => 'Uno o más puestos de la variante tienen baja compatibilidad histórica con el rol. Revisa esas asignaciones individualmente antes de usar el agregado.',
                'many_changes' => 'La variante cambia cuatro o más jugadores. La incertidumbre de interacción es alta; prueba los cambios tácticos en entrenamiento y no interpretes los deltas como efectos aditivos.',
                'low_sample' => 'La baja cobertura de minutos recientes reduce la confianza de la comparación del XI.',
                'neutral' => 'Los perfiles medidos de ambos XI son similares. Prioriza la variante que mejor encaje con el plan específico contra el rival y la intención del cuerpo técnico.',
                'dimension_progression' => 'la progresión',
                'dimension_ball_security' => 'la seguridad con balón',
                'dimension_attacking_threat' => 'la amenaza ofensiva',
                'dimension_pressing_activity' => 'la actividad de presión',
                'dimension_defensive_activity' => 'la actividad defensiva',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
