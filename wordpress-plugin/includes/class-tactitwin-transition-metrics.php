<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Transition_Metrics {
    private const SHOT_WINDOW_SECONDS = 10;
    private const COUNTERPRESS_WINDOW_SECONDS = 8;

    public static function calculate( array $events ): array {
        $teams = array();

        foreach ( $events as $event ) {
            $team_id = (string) ( $event['team_source_id'] ?? '' );
            if ( '' !== $team_id && ! isset( $teams[ $team_id ] ) ) {
                $teams[ $team_id ] = self::empty_metrics( $team_id );
            }
        }

        $count = count( $events );
        for ( $i = 0; $i < $count; $i++ ) {
            $turnover = $events[ $i ];
            $team_id  = (string) ( $turnover['team_source_id'] ?? '' );

            if ( '' === $team_id || ! self::is_turnover( $turnover ) ) {
                continue;
            }

            if ( ! isset( $teams[ $team_id ] ) ) {
                $teams[ $team_id ] = self::empty_metrics( $team_id );
            }

            $metrics =& $teams[ $team_id ];
            ++$metrics['transition_turnovers'];
            ++$metrics['turnovers_by_zone'][ self::zone( $turnover['location_x'] ?? null ) ];

            $turnover_period = (int) ( $turnover['period'] ?? 0 );
            $turnover_time   = self::event_seconds( $turnover );
            $shot_seen       = false;
            $progression_seen = false;
            $recovery_seen   = false;

            for ( $j = $i + 1; $j < $count; $j++ ) {
                $next = $events[ $j ];
                if ( (int) ( $next['period'] ?? 0 ) !== $turnover_period ) {
                    break;
                }

                $delta = self::event_seconds( $next ) - $turnover_time;
                if ( $delta < 0 ) {
                    continue;
                }
                if ( $delta > self::SHOT_WINDOW_SECONDS ) {
                    break;
                }

                $next_team = (string) ( $next['team_source_id'] ?? '' );
                $type      = (string) ( $next['event_type_name'] ?? '' );

                if (
                    ! $recovery_seen &&
                    $delta <= self::COUNTERPRESS_WINDOW_SECONDS &&
                    $next_team === $team_id &&
                    self::is_recovery( $next )
                ) {
                    ++$metrics['counterpress_recoveries_within_8s'];
                    $recovery_seen = true;
                }

                if ( '' === $next_team || $next_team === $team_id ) {
                    continue;
                }

                if ( ! $progression_seen && self::is_progression( $next ) ) {
                    ++$metrics['opponent_progressions_within_10s'];
                    $progression_seen = true;
                }

                if ( ! $shot_seen && 'Shot' === $type ) {
                    ++$metrics['opponent_shots_within_10s'];
                    $metrics['xg_conceded_within_10s'] += (float) ( $next['xg'] ?? 0.0 );
                    $shot_seen = true;
                }
            }
        }

        foreach ( $teams as &$metrics ) {
            $turnovers = max( 1, $metrics['transition_turnovers'] );
            $metrics['dangerous_turnover_rate'] = round(
                ( $metrics['opponent_shots_within_10s'] / $turnovers ) * 100,
                1
            );
            $metrics['transition_progression_rate'] = round(
                ( $metrics['opponent_progressions_within_10s'] / $turnovers ) * 100,
                1
            );
            $metrics['counterpress_recovery_rate'] = round(
                ( $metrics['counterpress_recoveries_within_8s'] / $turnovers ) * 100,
                1
            );
            $metrics['xg_conceded_within_10s'] = round( $metrics['xg_conceded_within_10s'], 3 );
        }
        unset( $metrics );

        return $teams;
    }

    private static function is_turnover( array $event ): bool {
        $type    = (string) ( $event['event_type_name'] ?? '' );
        $outcome = (string) ( $event['outcome_name'] ?? '' );

        if ( 'Pass' === $type ) {
            return '' !== $outcome;
        }

        if ( 'Dribble' === $type ) {
            return '' !== $outcome && 'Complete' !== $outcome;
        }

        return in_array( $type, array( 'Dispossessed', 'Miscontrol' ), true );
    }

    private static function is_recovery( array $event ): bool {
        $type = (string) ( $event['event_type_name'] ?? '' );
        return in_array( $type, array( 'Ball Recovery', 'Interception' ), true );
    }

    private static function is_progression( array $event ): bool {
        $type = (string) ( $event['event_type_name'] ?? '' );
        if ( ! in_array( $type, array( 'Pass', 'Carry' ), true ) ) {
            return false;
        }

        if ( 'Pass' === $type && '' !== (string) ( $event['outcome_name'] ?? '' ) ) {
            return false;
        }

        $x1 = self::float_or_null( $event['location_x'] ?? null );
        $x2 = self::float_or_null( $event['end_location_x'] ?? null );
        if ( null === $x1 || null === $x2 ) {
            return false;
        }

        return ( $x2 - $x1 ) >= 15.0 || ( $x1 < 80.0 && $x2 >= 80.0 );
    }

    private static function event_seconds( array $event ): int {
        return ( (int) ( $event['minute'] ?? 0 ) * 60 ) + (int) ( $event['second'] ?? 0 );
    }

    private static function zone( $x ): string {
        $x = self::float_or_null( $x );
        if ( null === $x || $x < 40.0 ) {
            return 'own_third';
        }
        if ( $x < 80.0 ) {
            return 'middle_third';
        }
        return 'final_third';
    }

    private static function float_or_null( $value ): ?float {
        return null === $value || '' === $value ? null : (float) $value;
    }

    private static function empty_metrics( string $team_id ): array {
        return array(
            'team_source_id'                    => $team_id,
            'transition_turnovers'               => 0,
            'turnovers_by_zone'                  => array(
                'own_third'    => 0,
                'middle_third' => 0,
                'final_third'  => 0,
            ),
            'opponent_progressions_within_10s'   => 0,
            'opponent_shots_within_10s'          => 0,
            'xg_conceded_within_10s'             => 0.0,
            'counterpress_recoveries_within_8s'  => 0,
            'dangerous_turnover_rate'             => 0.0,
            'transition_progression_rate'         => 0.0,
            'counterpress_recovery_rate'          => 0.0,
        );
    }
}
