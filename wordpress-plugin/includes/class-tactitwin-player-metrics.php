<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Player_Metrics {
    public static function calculate( array $events, string $team_id ): array {
        $players     = array();
        $active      = array();
        $lineup_seen = false;
        $match_end   = self::match_end_seconds( $events );

        foreach ( $events as $event ) {
            if ( (string) ( $event['team_source_id'] ?? '' ) !== $team_id ) {
                continue;
            }

            $clock = self::clock_seconds( $event );
            $type  = (string) ( $event['event_type_name'] ?? '' );
            $raw   = self::raw_event( $event );

            if ( 'Starting XI' === $type ) {
                $lineup = $raw['tactics']['lineup'] ?? array();
                if ( is_array( $lineup ) && ! empty( $lineup ) ) {
                    $lineup_seen = true;
                    foreach ( $lineup as $entry ) {
                        $player_id = (string) ( $entry['player']['id'] ?? '' );
                        if ( '' === $player_id ) {
                            continue;
                        }
                        self::ensure_player( $players, $player_id );
                        $players[ $player_id ]['starter'] = true;
                        $players[ $player_id ]['position_name'] = (string) ( $entry['position']['name'] ?? $players[ $player_id ]['position_name'] );
                        $active[ $player_id ] = 0.0;
                    }
                }
                continue;
            }

            if ( 'Substitution' === $type ) {
                $outgoing = (string) ( $event['player_source_id'] ?? '' );
                if ( '' !== $outgoing && isset( $active[ $outgoing ] ) ) {
                    self::close_interval( $players, $active, $outgoing, $clock );
                }

                $incoming = (string) ( $raw['substitution']['replacement']['id'] ?? '' );
                if ( '' !== $incoming ) {
                    self::ensure_player( $players, $incoming );
                    $players[ $incoming ]['player_name'] = (string) ( $raw['substitution']['replacement']['name'] ?? $players[ $incoming ]['player_name'] );
                    $players[ $incoming ]['substitute_appearances'] = 1;
                    $active[ $incoming ] = $clock;
                }
                continue;
            }

            $player_id = (string) ( $event['player_source_id'] ?? '' );
            if ( '' === $player_id ) {
                continue;
            }

            self::ensure_player( $players, $player_id );
            $position = (string) ( $event['position_name'] ?? '' );
            if ( '' !== $position ) {
                $players[ $player_id ]['position_counts'][ $position ] = ( $players[ $player_id ]['position_counts'][ $position ] ?? 0 ) + 1;
            }

            if ( ! $lineup_seen && ! isset( $active[ $player_id ] ) ) {
                $active[ $player_id ] = $clock;
            }

            self::record_event( $players[ $player_id ], $event );

            if ( self::is_dismissal( $event ) && isset( $active[ $player_id ] ) ) {
                self::close_interval( $players, $active, $player_id, $clock );
                $players[ $player_id ]['dismissed'] = true;
            }
        }

        foreach ( array_keys( $active ) as $player_id ) {
            self::close_interval( $players, $active, $player_id, $match_end );
        }

        foreach ( $players as $player_id => &$player ) {
            if ( empty( $player['position_name'] ) && ! empty( $player['position_counts'] ) ) {
                arsort( $player['position_counts'] );
                $player['position_name'] = (string) array_key_first( $player['position_counts'] );
            }
            unset( $player['position_counts'] );
            $player = self::finalize( $player );
        }
        unset( $player );

        return $players;
    }

    private static function ensure_player( array &$players, string $player_id ): void {
        if ( isset( $players[ $player_id ] ) ) {
            return;
        }

        $players[ $player_id ] = array(
            'player_source_id'       => $player_id,
            'player_name'            => '',
            'position_name'          => '',
            'position_counts'        => array(),
            'starter'                => false,
            'substitute_appearances' => 0,
            'dismissed'              => false,
            'seconds'                => 0.0,
            'minutes'                => 0.0,
            'passes'                 => 0,
            'completed_passes'       => 0,
            'carries'                => 0,
            'progressive_actions'    => 0,
            'final_third_entries'    => 0,
            'box_entries'            => 0,
            'shots'                  => 0,
            'xg'                     => 0.0,
            'pressures'              => 0,
            'recoveries'             => 0,
            'duels'                  => 0,
            'turnovers'              => 0,
        );
    }

    private static function close_interval( array &$players, array &$active, string $player_id, float $clock ): void {
        if ( ! isset( $active[ $player_id ] ) ) {
            return;
        }
        self::ensure_player( $players, $player_id );
        $players[ $player_id ]['seconds'] += max( 0.0, $clock - (float) $active[ $player_id ] );
        unset( $active[ $player_id ] );
    }

    private static function record_event( array &$player, array $event ): void {
        $type    = (string) ( $event['event_type_name'] ?? '' );
        $outcome = (string) ( $event['outcome_name'] ?? '' );
        $x1      = self::float_or_null( $event['location_x'] ?? null );
        $y1      = self::float_or_null( $event['location_y'] ?? null );
        $x2      = self::float_or_null( $event['end_location_x'] ?? null );
        $y2      = self::float_or_null( $event['end_location_y'] ?? null );

        if ( 'Pass' === $type ) {
            ++$player['passes'];
            if ( '' === $outcome ) {
                ++$player['completed_passes'];
            }
        } elseif ( 'Carry' === $type ) {
            ++$player['carries'];
        } elseif ( 'Shot' === $type ) {
            ++$player['shots'];
            $player['xg'] += (float) ( $event['xg'] ?? 0.0 );
        } elseif ( 'Pressure' === $type ) {
            ++$player['pressures'];
        } elseif ( 'Ball Recovery' === $type ) {
            ++$player['recoveries'];
        } elseif ( 'Duel' === $type ) {
            ++$player['duels'];
        } elseif ( in_array( $type, array( 'Dispossessed', 'Miscontrol' ), true ) ) {
            ++$player['turnovers'];
        }

        if ( in_array( $type, array( 'Pass', 'Carry' ), true ) && null !== $x1 && null !== $x2 ) {
            if ( ( $x2 - $x1 ) >= 15.0 ) {
                ++$player['progressive_actions'];
            }
            if ( $x1 < 80.0 && $x2 >= 80.0 ) {
                ++$player['final_third_entries'];
            }
            if ( self::outside_box( $x1, $y1 ) && self::inside_box( $x2, $y2 ) ) {
                ++$player['box_entries'];
            }
        }
    }

    private static function finalize( array $player ): array {
        $minutes = max( 0.0, (float) $player['seconds'] / 60.0 );
        $factor  = $minutes > 0.0 ? 90.0 / $minutes : 0.0;

        $player['seconds'] = round( (float) $player['seconds'], 1 );
        $player['minutes'] = round( $minutes, 2 );
        $player['xg']      = round( (float) $player['xg'], 3 );
        $player['pass_completion_pct'] = self::percentage( (int) $player['completed_passes'], (int) $player['passes'] );
        $player['per_90'] = array(
            'passes'              => round( $player['passes'] * $factor, 2 ),
            'carries'             => round( $player['carries'] * $factor, 2 ),
            'progressive_actions' => round( $player['progressive_actions'] * $factor, 2 ),
            'final_third_entries' => round( $player['final_third_entries'] * $factor, 2 ),
            'box_entries'         => round( $player['box_entries'] * $factor, 2 ),
            'shots'               => round( $player['shots'] * $factor, 2 ),
            'xg'                  => round( $player['xg'] * $factor, 3 ),
            'pressures'           => round( $player['pressures'] * $factor, 2 ),
            'recoveries'          => round( $player['recoveries'] * $factor, 2 ),
            'duels'               => round( $player['duels'] * $factor, 2 ),
            'turnovers'           => round( $player['turnovers'] * $factor, 2 ),
        );

        return $player;
    }

    private static function is_dismissal( array $event ): bool {
        $raw = self::raw_event( $event );
        $card = (string) ( $raw['foul_committed']['card']['name'] ?? $raw['bad_behaviour']['card']['name'] ?? '' );
        return in_array( $card, array( 'Red Card', 'Second Yellow' ), true );
    }

    private static function match_end_seconds( array $events ): float {
        $latest     = 90.0 * 60.0;
        $max_period = 2;
        foreach ( $events as $event ) {
            $period = (int) ( $event['period'] ?? 1 );
            if ( $period >= 5 ) {
                continue;
            }
            $max_period = max( $max_period, $period );
            $latest = max( $latest, self::clock_seconds( $event ) );
        }
        if ( $max_period >= 4 ) {
            $latest = max( $latest, 120.0 * 60.0 );
        }
        return $latest;
    }

    private static function clock_seconds( array $event ): float {
        return max( 0.0, ( (float) ( $event['minute'] ?? 0 ) * 60.0 ) + (float) ( $event['second'] ?? 0 ) );
    }

    private static function raw_event( array $event ): array {
        $raw = $event['raw_json'] ?? array();
        if ( is_array( $raw ) ) {
            return $raw;
        }
        $decoded = json_decode( (string) $raw, true );
        return is_array( $decoded ) ? $decoded : array();
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
