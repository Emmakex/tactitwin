<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Unit_Metrics {
    private const PRESSURE_RECOVERY_WINDOW_SECONDS = 8.0;

    public static function calculate( array $events, string $team_id ): array {
        $pairs            = array();
        $active           = array();
        $lineup_seen      = false;
        $recent_pressures = array();
        $possessions      = array();
        $last_clock       = 0.0;
        $match_end        = self::match_end_seconds( $events );

        foreach ( $events as $event ) {
            if ( (int) ( $event['period'] ?? 1 ) >= 5 ) {
                continue;
            }

            $clock = min( $match_end, self::clock_seconds( $event ) );
            if ( $clock > $last_clock && count( $active ) >= 2 ) {
                self::accrue_shared_seconds( $pairs, array_keys( $active ), $clock - $last_clock );
            }
            $last_clock = max( $last_clock, $clock );
            self::expire_pressures( $recent_pressures, $clock );

            if ( (string) ( $event['team_source_id'] ?? '' ) !== $team_id ) {
                continue;
            }

            $type = (string) ( $event['event_type_name'] ?? '' );
            $raw  = self::raw_event( $event );

            if ( 'Starting XI' === $type ) {
                $lineup = $raw['tactics']['lineup'] ?? array();
                if ( is_array( $lineup ) && ! empty( $lineup ) ) {
                    $lineup_seen = true;
                    $active = array();
                    foreach ( $lineup as $entry ) {
                        $player_id = (string) ( $entry['player']['id'] ?? '' );
                        if ( '' !== $player_id ) {
                            $active[ $player_id ] = true;
                        }
                    }
                }
                continue;
            }

            if ( 'Substitution' === $type ) {
                $outgoing = (string) ( $event['player_source_id'] ?? '' );
                if ( '' !== $outgoing ) {
                    unset( $active[ $outgoing ], $recent_pressures[ $outgoing ] );
                }
                $incoming = (string) ( $raw['substitution']['replacement']['id'] ?? '' );
                if ( '' !== $incoming ) {
                    $active[ $incoming ] = true;
                }
                continue;
            }

            $player_id = (string) ( $event['player_source_id'] ?? '' );
            if ( '' === $player_id ) {
                continue;
            }

            if ( ! $lineup_seen && ! isset( $active[ $player_id ] ) ) {
                $active[ $player_id ] = true;
            }

            if ( self::is_dismissal( $event ) ) {
                unset( $active[ $player_id ], $recent_pressures[ $player_id ] );
            }

            self::record_possession_touch( $possessions, $event, $player_id );

            if ( 'Pass' === $type ) {
                self::record_pass_connection( $pairs, $event, $player_id, $raw );
            } elseif ( 'Pressure' === $type ) {
                $recent_pressures[ $player_id ] = array(
                    'clock'      => $clock,
                    'possession' => isset( $event['possession'] ) ? (string) $event['possession'] : '',
                );
            } elseif ( 'Ball Recovery' === $type ) {
                self::record_pressure_recovery_sequences( $pairs, $recent_pressures, $player_id, $clock );
            }
        }

        if ( $match_end > $last_clock && count( $active ) >= 2 ) {
            self::accrue_shared_seconds( $pairs, array_keys( $active ), $match_end - $last_clock );
        }

        self::record_joint_possessions( $pairs, $possessions );

        foreach ( $pairs as &$pair ) {
            $pair = self::finalize_pair( $pair );
        }
        unset( $pair );

        ksort( $pairs );

        return array(
            'team_source_id' => $team_id,
            'lineup_seen'    => $lineup_seen,
            'match_seconds'  => round( $match_end, 1 ),
            'pairs'          => $pairs,
        );
    }

    public static function pair_key( string $player_a, string $player_b ): string {
        return strcmp( $player_a, $player_b ) <= 0
            ? $player_a . ':' . $player_b
            : $player_b . ':' . $player_a;
    }

    private static function accrue_shared_seconds( array &$pairs, array $player_ids, float $seconds ): void {
        $player_ids = array_values( array_unique( array_filter( array_map( 'strval', $player_ids ) ) ) );
        $count = count( $player_ids );
        for ( $i = 0; $i < $count; ++$i ) {
            for ( $j = $i + 1; $j < $count; ++$j ) {
                $pair = &self::ensure_pair( $pairs, $player_ids[ $i ], $player_ids[ $j ] );
                $pair['shared_seconds'] += max( 0.0, $seconds );
                unset( $pair );
            }
        }
    }

    private static function record_pass_connection( array &$pairs, array $event, string $passer_id, array $raw ): void {
        if ( '' !== (string) ( $event['outcome_name'] ?? '' ) ) {
            return;
        }

        $recipient_id = (string) ( $raw['pass']['recipient']['id'] ?? '' );
        if ( '' === $recipient_id || $recipient_id === $passer_id ) {
            return;
        }

        $pair = &self::ensure_pair( $pairs, $passer_id, $recipient_id );
        ++$pair['completed_passes'];

        if ( $passer_id === $pair['player_a_id'] ) {
            ++$pair['passes_a_to_b'];
        } else {
            ++$pair['passes_b_to_a'];
        }

        $x1 = self::float_or_null( $event['location_x'] ?? null );
        $y1 = self::float_or_null( $event['location_y'] ?? null );
        $x2 = self::float_or_null( $event['end_location_x'] ?? null );
        $y2 = self::float_or_null( $event['end_location_y'] ?? null );

        if ( null !== $x1 && null !== $x2 && ( $x2 - $x1 ) >= 15.0 ) {
            ++$pair['progressive_passes'];
        }
        if ( null !== $x1 && null !== $x2 && $x1 < 80.0 && $x2 >= 80.0 ) {
            ++$pair['final_third_entries'];
        }
        if ( self::outside_box( $x1, $y1 ) && self::inside_box( $x2, $y2 ) ) {
            ++$pair['box_entries'];
        }
        if ( ! empty( $raw['pass']['shot_assist'] ) ) {
            ++$pair['shot_assists'];
        }
        if ( ! empty( $raw['pass']['goal_assist'] ) ) {
            ++$pair['goal_assists'];
        }

        unset( $pair );
    }

    private static function record_possession_touch( array &$possessions, array $event, string $player_id ): void {
        $possession = isset( $event['possession'] ) ? (string) $event['possession'] : '';
        if ( '' === $possession ) {
            return;
        }

        if ( ! isset( $possessions[ $possession ] ) ) {
            $possessions[ $possession ] = array(
                'players' => array(),
                'shots'   => 0,
                'xg'      => 0.0,
            );
        }

        $possessions[ $possession ]['players'][ $player_id ] = true;
        if ( 'Shot' === (string) ( $event['event_type_name'] ?? '' ) ) {
            ++$possessions[ $possession ]['shots'];
            $possessions[ $possession ]['xg'] += (float) ( $event['xg'] ?? 0.0 );
        }
    }

    private static function record_joint_possessions( array &$pairs, array $possessions ): void {
        foreach ( $possessions as $possession ) {
            $ids = array_keys( $possession['players'] ?? array() );
            $count = count( $ids );
            if ( $count < 2 ) {
                continue;
            }

            for ( $i = 0; $i < $count; ++$i ) {
                for ( $j = $i + 1; $j < $count; ++$j ) {
                    $pair = &self::ensure_pair( $pairs, (string) $ids[ $i ], (string) $ids[ $j ] );
                    ++$pair['joint_possessions'];
                    if ( (int) ( $possession['shots'] ?? 0 ) > 0 ) {
                        ++$pair['joint_shot_possessions'];
                        $pair['joint_possession_xg'] += (float) ( $possession['xg'] ?? 0.0 );
                    }
                    unset( $pair );
                }
            }
        }
    }

    private static function record_pressure_recovery_sequences(
        array &$pairs,
        array $recent_pressures,
        string $recovery_player_id,
        float $clock
    ): void {
        foreach ( $recent_pressures as $pressure_player_id => $pressure ) {
            if ( (string) $pressure_player_id === $recovery_player_id ) {
                continue;
            }
            $age = $clock - (float) ( $pressure['clock'] ?? -999.0 );
            if ( $age < 0.0 || $age > self::PRESSURE_RECOVERY_WINDOW_SECONDS ) {
                continue;
            }
            $pair = &self::ensure_pair( $pairs, (string) $pressure_player_id, $recovery_player_id );
            ++$pair['pressure_recovery_sequences'];
            unset( $pair );
        }
    }

    private static function expire_pressures( array &$pressures, float $clock ): void {
        foreach ( $pressures as $player_id => $pressure ) {
            if ( $clock - (float) ( $pressure['clock'] ?? 0.0 ) > self::PRESSURE_RECOVERY_WINDOW_SECONDS ) {
                unset( $pressures[ $player_id ] );
            }
        }
    }

    private static function &ensure_pair( array &$pairs, string $player_a, string $player_b ): array {
        $key = self::pair_key( $player_a, $player_b );
        if ( ! isset( $pairs[ $key ] ) ) {
            $parts = explode( ':', $key, 2 );
            $pairs[ $key ] = array(
                'pair_key'                    => $key,
                'player_a_id'                 => (string) ( $parts[0] ?? $player_a ),
                'player_b_id'                 => (string) ( $parts[1] ?? $player_b ),
                'shared_seconds'               => 0.0,
                'completed_passes'             => 0,
                'passes_a_to_b'                => 0,
                'passes_b_to_a'                => 0,
                'progressive_passes'           => 0,
                'final_third_entries'          => 0,
                'box_entries'                  => 0,
                'shot_assists'                 => 0,
                'goal_assists'                 => 0,
                'joint_possessions'            => 0,
                'joint_shot_possessions'       => 0,
                'joint_possession_xg'          => 0.0,
                'pressure_recovery_sequences'  => 0,
            );
        }
        return $pairs[ $key ];
    }

    private static function finalize_pair( array $pair ): array {
        $minutes = max( 0.0, (float) $pair['shared_seconds'] / 60.0 );
        $factor  = $minutes > 0.0 ? 90.0 / $minutes : 0.0;
        $forward = (int) $pair['passes_a_to_b'];
        $back    = (int) $pair['passes_b_to_a'];
        $larger  = max( $forward, $back );
        $smaller = min( $forward, $back );

        $pair['shared_seconds']      = round( (float) $pair['shared_seconds'], 1 );
        $pair['shared_minutes']      = round( $minutes, 2 );
        $pair['joint_possession_xg'] = round( (float) $pair['joint_possession_xg'], 3 );
        $pair['direction_balance_pct'] = $larger > 0 ? round( ( $smaller / $larger ) * 100.0, 1 ) : 0.0;
        $pair['per_90_shared'] = array(
            'completed_passes'            => round( $pair['completed_passes'] * $factor, 2 ),
            'progressive_passes'          => round( $pair['progressive_passes'] * $factor, 2 ),
            'final_third_entries'         => round( $pair['final_third_entries'] * $factor, 2 ),
            'box_entries'                 => round( $pair['box_entries'] * $factor, 2 ),
            'shot_assists'                => round( $pair['shot_assists'] * $factor, 2 ),
            'goal_assists'                => round( $pair['goal_assists'] * $factor, 2 ),
            'joint_possessions'           => round( $pair['joint_possessions'] * $factor, 2 ),
            'joint_shot_possessions'      => round( $pair['joint_shot_possessions'] * $factor, 2 ),
            'joint_possession_xg'         => round( $pair['joint_possession_xg'] * $factor, 3 ),
            'pressure_recovery_sequences' => round( $pair['pressure_recovery_sequences'] * $factor, 2 ),
        );

        return $pair;
    }

    private static function is_dismissal( array $event ): bool {
        $raw  = self::raw_event( $event );
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
