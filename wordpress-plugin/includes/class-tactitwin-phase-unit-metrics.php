<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Phase_Unit_Metrics {
    private const HIGH_PRESS_X = 72.0;
    private const PRESSURE_RECOVERY_WINDOW_SECONDS = 8.0;

    public static function calculate( array $events, string $team_id ): array {
        $base = TactiTwin_Unit_Metrics::calculate( $events, $team_id );
        $pairs = array();

        foreach ( $base['pairs'] ?? array() as $key => $pair ) {
            $pairs[ $key ] = self::empty_pair(
                (string) ( $pair['player_a_id'] ?? '' ),
                (string) ( $pair['player_b_id'] ?? '' ),
                (float) ( $pair['shared_minutes'] ?? 0.0 )
            );
        }

        $pressures   = array();
        $possessions = array();

        foreach ( $events as $event ) {
            if ( (int) ( $event['period'] ?? 1 ) >= 5 ) {
                continue;
            }

            $clock = self::clock_seconds( $event );
            self::expire_pressures( $pressures, $clock );

            $event_team      = (string) ( $event['team_source_id'] ?? '' );
            $possession_team = (string) ( $event['possession_team_source_id'] ?? '' );
            $player_id       = (string) ( $event['player_source_id'] ?? '' );
            $type            = (string) ( $event['event_type_name'] ?? '' );
            $raw             = self::raw_event( $event );

            if ( $event_team === $team_id && 'Pass' === $type && '' === (string) ( $event['outcome_name'] ?? '' ) ) {
                $recipient = (string) ( $raw['pass']['recipient']['id'] ?? '' );
                if ( '' !== $player_id && '' !== $recipient && $player_id !== $recipient ) {
                    $key = TactiTwin_Unit_Metrics::pair_key( $player_id, $recipient );
                    if ( isset( $pairs[ $key ] ) ) {
                        $phase = self::attacking_phase( self::float_or_null( $event['location_x'] ?? null ) );
                        self::record_pass( $pairs[ $key ]['phases'][ $phase ], $event, $raw );
                    }
                }
            }

            if ( $event_team === $team_id && 'Pressure' === $type && '' !== $player_id ) {
                $x = self::float_or_null( $event['location_x'] ?? null );
                if ( null !== $x && $x >= self::HIGH_PRESS_X ) {
                    $pressures[ $player_id ] = array( 'clock'=>$clock, 'x'=>$x );
                }
            } elseif ( $event_team === $team_id && 'Ball Recovery' === $type && '' !== $player_id ) {
                foreach ( array_keys( $pressures ) as $pressure_player ) {
                    if ( (string) $pressure_player === $player_id ) {
                        continue;
                    }
                    $pressure = $pressures[ $pressure_player ] ?? array();
                    $age = $clock - (float) ( $pressure['clock'] ?? -999.0 );
                    if ( $age < 0.0 || $age > self::PRESSURE_RECOVERY_WINDOW_SECONDS ) {
                        continue;
                    }
                    $key = TactiTwin_Unit_Metrics::pair_key( (string) $pressure_player, $player_id );
                    if ( isset( $pairs[ $key ] ) ) {
                        ++$pairs[ $key ]['phases']['high_press']['pressure_recovery_sequences'];
                    }
                    unset( $pressures[ $pressure_player ] );
                }
            }

            if ( $possession_team === $team_id && '' !== $player_id ) {
                $possession = isset( $event['possession'] ) ? (string) $event['possession'] : '';
                if ( '' !== $possession ) {
                    if ( ! isset( $possessions[ $possession ] ) ) {
                        $possessions[ $possession ] = array(
                            'players'=>array(), 'max_x'=>null, 'shot_xg'=>0.0, 'shots'=>0,
                        );
                    }
                    $possessions[ $possession ]['players'][ $player_id ] = true;
                    $x = self::float_or_null( $event['location_x'] ?? null );
                    if ( null !== $x ) {
                        $possessions[ $possession ]['max_x'] = null === $possessions[ $possession ]['max_x']
                            ? $x
                            : max( (float) $possessions[ $possession ]['max_x'], $x );
                    }
                    if ( 'Shot' === $type ) {
                        ++$possessions[ $possession ]['shots'];
                        $possessions[ $possession ]['shot_xg'] += (float) ( $event['xg'] ?? 0.0 );
                    }
                }
            }
        }

        self::record_final_third_possessions( $pairs, $possessions );

        foreach ( $pairs as &$pair ) {
            self::finalize_pair( $pair );
        }
        unset( $pair );

        return array(
            'team_source_id' => $team_id,
            'phase_definitions' => array(
                'build_up'    => 'Pass starts with x < 40.',
                'progression' => 'Pass starts with 40 <= x < 80.',
                'final_third' => 'Pass starts with x >= 80; shared shot possessions require team possession to reach x >= 80.',
                'high_press'  => 'Pressure starts with x >= 72 and a teammate records a Ball Recovery within 8 seconds.',
            ),
            'pairs' => $pairs,
        );
    }

    private static function empty_pair( string $a, string $b, float $minutes ): array {
        return array(
            'pair_key'       => TactiTwin_Unit_Metrics::pair_key( $a, $b ),
            'player_a_id'    => $a,
            'player_b_id'    => $b,
            'shared_minutes' => round( $minutes, 2 ),
            'phases' => array(
                'build_up'    => self::empty_attack_phase(),
                'progression' => self::empty_attack_phase(),
                'final_third' => array_merge( self::empty_attack_phase(), array(
                    'joint_shot_possessions'=>0,
                    'joint_possession_xg'=>0.0,
                ) ),
                'high_press' => array(
                    'pressure_recovery_sequences'=>0,
                ),
            ),
        );
    }

    private static function empty_attack_phase(): array {
        return array(
            'completed_passes'=>0,
            'progressive_passes'=>0,
            'final_third_entries'=>0,
            'box_entries'=>0,
            'shot_assists'=>0,
            'goal_assists'=>0,
        );
    }

    private static function record_pass( array &$phase, array $event, array $raw ): void {
        ++$phase['completed_passes'];
        $x1 = self::float_or_null( $event['location_x'] ?? null );
        $y1 = self::float_or_null( $event['location_y'] ?? null );
        $x2 = self::float_or_null( $event['end_location_x'] ?? null );
        $y2 = self::float_or_null( $event['end_location_y'] ?? null );
        if ( null !== $x1 && null !== $x2 && ( $x2 - $x1 ) >= 15.0 ) {
            ++$phase['progressive_passes'];
        }
        if ( null !== $x1 && null !== $x2 && $x1 < 80.0 && $x2 >= 80.0 ) {
            ++$phase['final_third_entries'];
        }
        if ( self::outside_box( $x1, $y1 ) && self::inside_box( $x2, $y2 ) ) {
            ++$phase['box_entries'];
        }
        if ( ! empty( $raw['pass']['shot_assist'] ) ) {
            ++$phase['shot_assists'];
        }
        if ( ! empty( $raw['pass']['goal_assist'] ) ) {
            ++$phase['goal_assists'];
        }
    }

    private static function record_final_third_possessions( array &$pairs, array $possessions ): void {
        foreach ( $possessions as $possession ) {
            if ( (float) ( $possession['max_x'] ?? 0.0 ) < 80.0 || (int) ( $possession['shots'] ?? 0 ) < 1 ) {
                continue;
            }
            $ids = array_keys( $possession['players'] ?? array() );
            $count = count( $ids );
            for ( $i = 0; $i < $count; ++$i ) {
                for ( $j = $i + 1; $j < $count; ++$j ) {
                    $key = TactiTwin_Unit_Metrics::pair_key( (string) $ids[ $i ], (string) $ids[ $j ] );
                    if ( ! isset( $pairs[ $key ] ) ) {
                        continue;
                    }
                    ++$pairs[ $key ]['phases']['final_third']['joint_shot_possessions'];
                    $pairs[ $key ]['phases']['final_third']['joint_possession_xg'] += (float) ( $possession['shot_xg'] ?? 0.0 );
                }
            }
        }
    }

    private static function finalize_pair( array &$pair ): void {
        $minutes = (float) ( $pair['shared_minutes'] ?? 0.0 );
        $factor = $minutes > 0.0 ? 90.0 / $minutes : 0.0;
        foreach ( array( 'build_up', 'progression', 'final_third' ) as $phase_name ) {
            $phase = &$pair['phases'][ $phase_name ];
            $phase['joint_possession_xg'] = isset( $phase['joint_possession_xg'] )
                ? round( (float) $phase['joint_possession_xg'], 3 )
                : null;
            $phase['per_90_shared'] = array();
            foreach ( array( 'completed_passes','progressive_passes','final_third_entries','box_entries','shot_assists','goal_assists' ) as $key ) {
                $phase['per_90_shared'][ $key ] = round( (float) ( $phase[ $key ] ?? 0 ) * $factor, 2 );
            }
            if ( 'final_third' === $phase_name ) {
                $phase['per_90_shared']['joint_shot_possessions'] = round( (float) ( $phase['joint_shot_possessions'] ?? 0 ) * $factor, 2 );
                $phase['per_90_shared']['joint_possession_xg'] = round( (float) ( $phase['joint_possession_xg'] ?? 0.0 ) * $factor, 3 );
            }
            unset( $phase );
        }
        $pair['phases']['high_press']['per_90_shared'] = array(
            'pressure_recovery_sequences' => round(
                (float) ( $pair['phases']['high_press']['pressure_recovery_sequences'] ?? 0 ) * $factor,
                2
            ),
        );
    }

    private static function attacking_phase( ?float $x ): string {
        if ( null === $x || $x < 40.0 ) {
            return 'build_up';
        }
        if ( $x < 80.0 ) {
            return 'progression';
        }
        return 'final_third';
    }

    private static function expire_pressures( array &$pressures, float $clock ): void {
        foreach ( $pressures as $player_id => $pressure ) {
            if ( $clock - (float) ( $pressure['clock'] ?? 0.0 ) > self::PRESSURE_RECOVERY_WINDOW_SECONDS ) {
                unset( $pressures[ $player_id ] );
            }
        }
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
