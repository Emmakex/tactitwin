<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Context_Aware_Transition_Review {
    public static function annotate(
        array $transition_deltas,
        array $snapshots,
        array $events,
        string $team_id,
        string $opponent_id,
        string $language = 'en'
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        if ( 'transition-window-deltas' !== (string) ( $transition_deltas['evidence_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'A TactiTwin transition-window delta result is required.' );
        }
        if ( '' === $team_id || '' === $opponent_id || $team_id === $opponent_id ) {
            throw new InvalidArgumentException( 'Context-aware transition review requires two different teams.' );
        }

        $snapshots = self::sorted_snapshots( $snapshots );
        $snapshot_index = self::snapshot_index( $snapshots );
        $rows = array();

        foreach ( $transition_deltas['transitions'] ?? array() as $transition ) {
            $snapshot_id = (int) ( $transition['snapshot_id'] ?? 0 );
            $current_position = $snapshot_index[ $snapshot_id ] ?? null;
            $current_snapshot = null !== $current_position ? ( $snapshots[ $current_position ] ?? null ) : null;
            $previous_snapshot = null !== $current_position && $current_position > 0 ? ( $snapshots[ $current_position - 1 ] ?? null ) : null;

            $before = $transition['before'] ?? array();
            $after = $transition['after'] ?? array();
            $before_events = self::slice( $events, (int) ( $before['from_minute'] ?? 0 ), (int) ( $before['to_minute'] ?? 0 ) );
            $after_events = self::slice( $events, (int) ( $after['from_minute'] ?? 0 ), (int) ( $after['to_minute'] ?? 0 ) );

            $recorded = self::snapshot_context( $previous_snapshot, $current_snapshot );
            $incidents = array(
                'before' => self::incidents( $before_events, $team_id, $opponent_id ),
                'after' => self::incidents( $after_events, $team_id, $opponent_id ),
            );
            $context = self::classify_context( $recorded, $incidents, $transition['coverage'] ?? array(), $language );

            $row = $transition;
            $row['context'] = array(
                'recorded_snapshot_change' => $recorded,
                'window_incidents' => $incidents,
                'status' => $context['status'],
                'status_label' => $context['status_label'],
                'interpretability' => $context['interpretability'],
                'interpretability_label' => $context['interpretability_label'],
                'context_comparable' => $context['context_comparable'],
                'warnings' => $context['warnings'],
            );
            $rows[] = $row;
        }

        return array(
            'review_id' => 'context-aware-transition-review',
            'language' => $language,
            'source_match_id' => (string) ( $transition_deltas['source_match_id'] ?? '' ),
            'coverage' => array(
                'transition_count' => count( $rows ),
                'base_window_minutes' => (int) ( $transition_deltas['coverage']['default_window_minutes'] ?? 10 ),
            ),
            'transitions' => $rows,
            'summary' => self::summary( $rows, $language ),
            'method' => array(
                'type' => 'descriptive-context-annotated-transition-window-review',
                'note' => self::text( 'method', $language ),
                'context_sources' => array(
                    'live-session snapshot score/manpower',
                    'normalized event goals',
                    'normalized substitution events',
                    'red/second-yellow cards from raw event payload',
                ),
                'limitations' => array(
                    self::text( 'limit_causality', $language ),
                    self::text( 'limit_snapshot', $language ),
                    self::text( 'limit_substitution', $language ),
                    self::text( 'limit_unobserved', $language ),
                ),
            ),
        );
    }

    private static function sorted_snapshots( array $snapshots ): array {
        usort(
            $snapshots,
            static function( array $a, array $b ): int {
                $minute = (int) ( $a['minute'] ?? 0 ) <=> (int) ( $b['minute'] ?? 0 );
                return 0 !== $minute ? $minute : (int) ( $a['id'] ?? 0 ) <=> (int) ( $b['id'] ?? 0 );
            }
        );
        return array_values( $snapshots );
    }

    private static function snapshot_index( array $snapshots ): array {
        $result = array();
        foreach ( $snapshots as $position => $snapshot ) {
            $id = (int) ( $snapshot['id'] ?? 0 );
            if ( $id > 0 ) {
                $result[ $id ] = $position;
            }
        }
        return $result;
    }

    private static function snapshot_context( ?array $before, ?array $after ): array {
        if ( null === $before || null === $after ) {
            return array(
                'available' => false,
                'score_changed' => null,
                'manpower_changed' => null,
                'before' => null,
                'after' => null,
            );
        }

        $before_context = self::compact_snapshot_context( $before );
        $after_context = self::compact_snapshot_context( $after );
        return array(
            'available' => true,
            'score_changed' => $before_context['our_score'] !== $after_context['our_score'] || $before_context['opponent_score'] !== $after_context['opponent_score'],
            'manpower_changed' => $before_context['our_players'] !== $after_context['our_players'] || $before_context['opponent_players'] !== $after_context['opponent_players'],
            'before' => $before_context,
            'after' => $after_context,
        );
    }

    private static function compact_snapshot_context( array $snapshot ): array {
        return array(
            'minute' => (int) ( $snapshot['minute'] ?? 0 ),
            'our_score' => (int) ( $snapshot['our_score'] ?? 0 ),
            'opponent_score' => (int) ( $snapshot['opponent_score'] ?? 0 ),
            'our_players' => (int) ( $snapshot['our_players'] ?? 11 ),
            'opponent_players' => (int) ( $snapshot['opponent_players'] ?? 11 ),
        );
    }

    private static function slice( array $events, int $from, int $to ): array {
        return array_values(
            array_filter(
                $events,
                static function( array $event ) use ( $from, $to ): bool {
                    $minute = (int) ( $event['minute'] ?? 0 );
                    return $minute >= $from && $minute < $to;
                }
            )
        );
    }

    private static function incidents( array $events, string $team_id, string $opponent_id ): array {
        $result = array(
            'our_goals' => 0,
            'opponent_goals' => 0,
            'our_substitutions' => 0,
            'opponent_substitutions' => 0,
            'our_dismissals' => 0,
            'opponent_dismissals' => 0,
            'other_team_incidents' => 0,
        );

        foreach ( $events as $event ) {
            $event_team = (string) ( $event['team_source_id'] ?? '' );
            $type = (string) ( $event['event_type_name'] ?? '' );
            $outcome = (string) ( $event['outcome_name'] ?? '' );
            $side = $event_team === $team_id ? 'our' : ( $event_team === $opponent_id ? 'opponent' : '' );

            if ( '' === $side ) {
                if ( self::is_context_incident( $event ) ) {
                    ++$result['other_team_incidents'];
                }
                continue;
            }

            if ( 'Shot' === $type && 'Goal' === $outcome ) {
                ++$result[ $side . '_goals' ];
            }
            if ( 'Substitution' === $type ) {
                ++$result[ $side . '_substitutions' ];
            }
            if ( self::is_dismissal( $event ) ) {
                ++$result[ $side . '_dismissals' ];
            }
        }

        $result['goals_total'] = $result['our_goals'] + $result['opponent_goals'];
        $result['substitutions_total'] = $result['our_substitutions'] + $result['opponent_substitutions'];
        $result['dismissals_total'] = $result['our_dismissals'] + $result['opponent_dismissals'];
        return $result;
    }

    private static function is_context_incident( array $event ): bool {
        return ( 'Shot' === (string) ( $event['event_type_name'] ?? '' ) && 'Goal' === (string) ( $event['outcome_name'] ?? '' ) )
            || 'Substitution' === (string) ( $event['event_type_name'] ?? '' )
            || self::is_dismissal( $event );
    }

    private static function is_dismissal( array $event ): bool {
        $raw = $event['raw_json'] ?? array();
        if ( ! is_array( $raw ) ) {
            $decoded = json_decode( (string) $raw, true );
            $raw = is_array( $decoded ) ? $decoded : array();
        }
        $card = (string) (
            $raw['foul_committed']['card']['name']
            ?? $raw['bad_behaviour']['card']['name']
            ?? ''
        );
        return in_array( $card, array( 'Red Card', 'Second Yellow' ), true );
    }

    private static function classify_context( array $recorded, array $incidents, array $coverage, string $language ): array {
        $before = $incidents['before'] ?? array();
        $after = $incidents['after'] ?? array();
        $goals = (int) ( $before['goals_total'] ?? 0 ) + (int) ( $after['goals_total'] ?? 0 );
        $dismissals = (int) ( $before['dismissals_total'] ?? 0 ) + (int) ( $after['dismissals_total'] ?? 0 );
        $substitutions = (int) ( $before['substitutions_total'] ?? 0 ) + (int) ( $after['substitutions_total'] ?? 0 );
        $score_changed = true === ( $recorded['score_changed'] ?? false );
        $manpower_changed = true === ( $recorded['manpower_changed'] ?? false );

        if ( $goals > 0 || $dismissals > 0 || $score_changed || $manpower_changed ) {
            $status = 'materially-changed';
        } elseif ( $substitutions > 0 ) {
            $status = 'changed';
        } else {
            $status = 'stable';
        }

        $base_comparable = true === ( $coverage['comparable'] ?? false );
        if ( ! $base_comparable ) {
            $interpretability = 'insufficient';
        } elseif ( 'materially-changed' === $status ) {
            $interpretability = 'highly-confounded';
        } elseif ( 'changed' === $status ) {
            $interpretability = 'confounded';
        } else {
            $interpretability = 'cleaner';
        }

        $warnings = array();
        if ( $goals > 0 || $score_changed ) {
            $warnings[] = self::text( 'warning_score', $language );
        }
        if ( $dismissals > 0 || $manpower_changed ) {
            $warnings[] = self::text( 'warning_manpower', $language );
        }
        if ( $substitutions > 0 ) {
            $warnings[] = self::text( 'warning_substitutions', $language );
        }
        if ( ! $base_comparable ) {
            $warnings[] = self::text( 'warning_coverage', $language );
        }
        if ( empty( $warnings ) ) {
            $warnings[] = self::text( 'warning_stable', $language );
        }

        return array(
            'status' => $status,
            'status_label' => self::text( 'context_' . str_replace( '-', '_', $status ), $language ),
            'interpretability' => $interpretability,
            'interpretability_label' => self::text( 'interpret_' . str_replace( '-', '_', $interpretability ), $language ),
            'context_comparable' => $base_comparable && 'materially-changed' !== $status,
            'warnings' => $warnings,
        );
    }

    private static function summary( array $rows, string $language ): array {
        $counts = array( 'stable'=>0, 'changed'=>0, 'materially-changed'=>0 );
        $interpretability = array( 'cleaner'=>0, 'confounded'=>0, 'highly-confounded'=>0, 'insufficient'=>0 );
        foreach ( $rows as $row ) {
            $status = (string) ( $row['context']['status'] ?? 'stable' );
            $interpret = (string) ( $row['context']['interpretability'] ?? 'insufficient' );
            if ( isset( $counts[ $status ] ) ) ++$counts[ $status ];
            if ( isset( $interpretability[ $interpret ] ) ) ++$interpretability[ $interpret ];
        }
        return array(
            sprintf( self::text( 'summary_context', $language ), $counts['stable'], $counts['changed'], $counts['materially-changed'] ),
            sprintf( self::text( 'summary_interpret', $language ), $interpretability['cleaner'], $interpretability['confounded'], $interpretability['highly-confounded'], $interpretability['insufficient'] ),
        );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'method' => 'v1.6 preserves the original before/after deltas and annotates each comparison with score, manpower and personnel changes observed in Live Session snapshots and normalized match events.',
                'limit_causality' => 'Context annotation reduces over-interpretation but still does not make before/after deltas causal.',
                'limit_snapshot' => 'Score/manpower snapshot changes are only known at staff submission times and may lag the real event.',
                'limit_substitution' => 'A substitution is treated as a context change, not as a measured positive or negative player effect.',
                'limit_unobserved' => 'Injuries, tactical instructions and off-ball structural changes may remain unobserved in event data.',
                'context_stable' => 'Stable context',
                'context_changed' => 'Personnel context changed',
                'context_materially_changed' => 'Material context changed',
                'interpret_cleaner' => 'Cleaner descriptive comparison',
                'interpret_confounded' => 'Confounded comparison',
                'interpret_highly_confounded' => 'Highly confounded comparison',
                'interpret_insufficient' => 'Insufficient comparison',
                'warning_score' => 'The score changed within/around the comparison windows, so match incentives may differ materially.',
                'warning_manpower' => 'A dismissal or recorded manpower change occurred, so the before/after windows are structurally different.',
                'warning_substitutions' => 'One or more substitutions occurred in the comparison windows; personnel effects are mixed with the tactical transition.',
                'warning_coverage' => 'The underlying v1.5 windows have limited coverage and should not be treated as comparable.',
                'warning_stable' => 'No goal, dismissal or substitution context change was detected in the compared windows.',
                'summary_context' => 'Context status: %d stable, %d personnel-changed, %d materially changed transitions.',
                'summary_interpret' => 'Interpretability: %d cleaner, %d confounded, %d highly confounded, %d insufficient comparisons.',
            ),
            'es' => array(
                'method' => 'v1.6 conserva los deltas antes/después originales y anota cada comparación con cambios de marcador, superioridad e integrantes detectados en snapshots de Live Session y eventos normalizados.',
                'limit_causality' => 'Anotar el contexto reduce la sobreinterpretación, pero no convierte los deltas antes/después en evidencia causal.',
                'limit_snapshot' => 'Los cambios de marcador/superioridad de snapshots solo se conocen en los momentos de envío del staff y pueden ir retrasados respecto al evento real.',
                'limit_substitution' => 'Una sustitución se trata como cambio de contexto, no como un efecto positivo o negativo medido del jugador.',
                'limit_unobserved' => 'Lesiones, instrucciones tácticas y cambios estructurales sin balón pueden seguir sin observarse en los eventos.',
                'context_stable' => 'Contexto estable',
                'context_changed' => 'Cambió el contexto de jugadores',
                'context_materially_changed' => 'Cambio material de contexto',
                'interpret_cleaner' => 'Comparación descriptiva más limpia',
                'interpret_confounded' => 'Comparación confundida',
                'interpret_highly_confounded' => 'Comparación muy confundida',
                'interpret_insufficient' => 'Comparación insuficiente',
                'warning_score' => 'El marcador cambió dentro/alrededor de las ventanas, por lo que los incentivos del partido pueden ser materialmente diferentes.',
                'warning_manpower' => 'Hubo una expulsión o cambio registrado de superioridad, por lo que las ventanas antes/después son estructuralmente diferentes.',
                'warning_substitutions' => 'Hubo una o más sustituciones en las ventanas; sus efectos de personal se mezclan con la transición táctica.',
                'warning_coverage' => 'Las ventanas v1.5 subyacentes tienen cobertura limitada y no deben tratarse como comparables.',
                'warning_stable' => 'No se detectaron cambios de contexto por gol, expulsión o sustitución en las ventanas comparadas.',
                'summary_context' => 'Estado de contexto: %d transiciones estables, %d con cambios de jugadores y %d con cambios materiales.',
                'summary_interpret' => 'Interpretabilidad: %d comparaciones más limpias, %d confundidas, %d muy confundidas y %d insuficientes.',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
