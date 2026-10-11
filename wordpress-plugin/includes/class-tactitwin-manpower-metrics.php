<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Manpower_Metrics {
    public const STATES = array( 'superior', 'equal', 'inferior' );

    public static function calculate( array $events, array $team_ids = array() ): array {
        $team_ids = self::team_ids( $events, $team_ids );
        if ( count( $team_ids ) < 2 ) {
            return array();
        }

        $players   = array_fill_keys( $team_ids, 11 );
        $dismissed = array();
        $results   = array();
        foreach ( $team_ids as $team_id ) {
            $results[ $team_id ] = self::empty_team_states( $team_id );
        }

        $previous_clock = 0.0;
        $max_period     = 2;

        foreach ( $events as $event ) {
            $period = (int) ( $event['period'] ?? 1 );
            if ( $period >= 5 ) {
                break;
            }
            $max_period = max( $max_period, $period );

            $clock = max( $previous_clock, self::clock_seconds( $event ) );
            self::allocate_time( $results, $players, $team_ids, $clock - $previous_clock );

            $team_id = (string) ( $event['team_source_id'] ?? '' );
            if ( isset( $results[ $team_id ] ) ) {
                $state = self::state_for( $team_id, $players, $team_ids );
                self::record_event( $results[ $team_id ][ $state ], $event );
            }

            if ( self::is_dismissal( $event ) && isset( $players[ $team_id ] ) ) {
                $player_id = (string) ( $event['player_source_id'] ?? '' );
                $key = '' !== $player_id ? $team_id . ':' . $player_id : $team_id . ':' . (string) ( $event['event_index'] ?? $clock );
                if ( ! isset( $dismissed[ $key ] ) ) {
                    $dismissed[ $key ] = true;
                    $players[ $team_id ] = max( 7, $players[ $team_id ] - 1 );
                }
            }

            $previous_clock = $clock;
        }

        $nominal_end = $max_period >= 4 ? 120.0 * 60.0 : 90.0 * 60.0;
        $match_end   = max( $nominal_end, $previous_clock );
        self::allocate_time( $results, $players, $team_ids, $match_end - $previous_clock );

        foreach ( $results as &$team_states ) {
            foreach ( self::STATES as $state ) {
                $team_states[ $state ] = self::finalize( $team_states[ $state ] );
            }
        }
        unset( $team_states );

        return $results;
    }

    private static function team_ids( array $events, array $team_ids ): array {
        $ids = array_values( array_filter( array_map( 'strval', $team_ids ) ) );
        if ( count( $ids ) >= 2 ) {
            return array_values( array_unique( $ids ) );
        }
        foreach ( $events as $event ) {
            $team_id = (string) ( $event['team_source_id'] ?? '' );
            if ( '' !== $team_id ) {
                $ids[] = $team_id;
            }
        }
        return array_values( array_unique( $ids ) );
    }

    private static function empty_team_states( string $team_id ): array {
        $states = array( 'team_source_id' => $team_id );
        foreach ( self::STATES as $state ) {
            $states[ $state ] = self::empty_state();
        }
        return $states;
    }

    private static function empty_state(): array {
        return array(
            'seconds'             => 0.0,
            'minutes'             => 0.0,
            'events'              => 0,
            'passes'              => 0,
            'completed_passes'    => 0,
            'pass_completion_pct' => 0.0,
            'shots'               => 0,
            'xg'                  => 0.0,
            'progressive_actions' => 0,
            'final_third_entries' => 0,
            'box_entries'         => 0,
            'pressures'           => 0,
            'high_pressures'      => 0,
        );
    }

    private static function allocate_time( array &$results, array $players, array $team_ids, float $seconds ): void {
        if ( $seconds <= 0.0 ) {
            return;
        }
        foreach ( $team_ids as $team_id ) {
            if ( ! isset( $results[ $team_id ] ) ) {
                continue;
            }
            $state = self::state_for( $team_id, $players, $team_ids );
            $results[ $team_id ][ $state ]['seconds'] += $seconds;
        }
    }

    private static function state_for( string $team_id, array $players, array $team_ids ): string {
        $opponent_id = self::opponent_id( $team_id, $team_ids );
        $ours   = (int) ( $players[ $team_id ] ?? 11 );
        $theirs = (int) ( $players[ $opponent_id ] ?? 11 );
        if ( $ours > $theirs ) {
            return 'superior';
        }
        if ( $ours < $theirs ) {
            return 'inferior';
        }
        return 'equal';
    }

    private static function opponent_id( string $team_id, array $team_ids ): string {
        foreach ( $team_ids as $candidate ) {
            if ( (string) $candidate !== $team_id ) {
                return (string) $candidate;
            }
        }
        return '';
    }

    private static function record_event( array &$bucket, array $event ): void {
        ++$bucket['events'];
        $type    = (string) ( $event['event_type_name'] ?? '' );
        $outcome = (string) ( $event['outcome_name'] ?? '' );
        $x1      = self::float_or_null( $event['location_x'] ?? null );
        $y1      = self::float_or_null( $event['location_y'] ?? null );
        $x2      = self::float_or_null( $event['end_location_x'] ?? null );
        $y2      = self::float_or_null( $event['end_location_y'] ?? null );

        if ( 'Pass' === $type ) {
            ++$bucket['passes'];
            if ( '' === $outcome ) {
                ++$bucket['completed_passes'];
            }
        } elseif ( 'Shot' === $type ) {
            ++$bucket['shots'];
            $bucket['xg'] += (float) ( $event['xg'] ?? 0.0 );
        } elseif ( 'Pressure' === $type ) {
            ++$bucket['pressures'];
            if ( null !== $x1 && $x1 >= 80.0 ) {
                ++$bucket['high_pressures'];
            }
        }

        if ( in_array( $type, array( 'Pass', 'Carry' ), true ) && null !== $x1 && null !== $x2 ) {
            if ( $x1 < 80.0 && $x2 >= 80.0 ) {
                ++$bucket['final_third_entries'];
            }
            if ( self::outside_box( $x1, $y1 ) && self::inside_box( $x2, $y2 ) ) {
                ++$bucket['box_entries'];
            }
            if ( ( $x2 - $x1 ) >= 15.0 ) {
                ++$bucket['progressive_actions'];
            }
        }
    }

    private static function is_dismissal( array $event ): bool {
        $raw = $event['raw_json'] ?? '';
        if ( is_array( $raw ) ) {
            $row = $raw;
        } else {
            $row = json_decode( (string) $raw, true );
        }
        if ( ! is_array( $row ) ) {
            return false;
        }

        $card = (string) (
            $row['foul_committed']['card']['name']
            ?? $row['bad_behaviour']['card']['name']
            ?? ''
        );

        return in_array( $card, array( 'Red Card', 'Second Yellow' ), true );
    }

    private static function finalize( array $bucket ): array {
        $seconds = max( 0.0, (float) $bucket['seconds'] );
        $minutes = $seconds / 60.0;
        $factor  = $minutes > 0.0 ? 90.0 / $minutes : 0.0;

        $bucket['seconds'] = round( $seconds, 1 );
        $bucket['minutes'] = round( $minutes, 2 );
        $bucket['xg']      = round( (float) $bucket['xg'], 3 );
        $bucket['pass_completion_pct'] = self::percentage( (int) $bucket['completed_passes'], (int) $bucket['passes'] );
        $bucket['per_90'] = array(
            'shots'               => round( $bucket['shots'] * $factor, 2 ),
            'xg'                  => round( $bucket['xg'] * $factor, 3 ),
            'progressive_actions' => round( $bucket['progressive_actions'] * $factor, 2 ),
            'final_third_entries' => round( $bucket['final_third_entries'] * $factor, 2 ),
            'box_entries'         => round( $bucket['box_entries'] * $factor, 2 ),
            'pressures'           => round( $bucket['pressures'] * $factor, 2 ),
            'high_pressures'      => round( $bucket['high_pressures'] * $factor, 2 ),
        );
        return $bucket;
    }

    private static function clock_seconds( array $event ): float {
        return max( 0.0, ( (float) ( $event['minute'] ?? 0 ) * 60.0 ) + (float) ( $event['second'] ?? 0 ) );
    }

    private static function percentage( int $numerator, int $denominator ): float {
        return $denominator > 0 ? round( ( $numerator / $denominator ) * 100, 1 ) : 0.0;
    }

    private static function float_or_null( $value ): ?float {
        return null === $value || '' === $value ? null : (float) $value;
    }

    private static function inside_box( ?float $x, ?float $y ): bool {
        return null !== $x && null !== $y && $x >= 102.0 && $y >= 18.0 && $y <= 62.0;
    }

    private static function outside_box( ?float $x, ?float $y ): bool {
        return ! self::inside_box( $x, $y );
    }
}
