<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Match_Metrics {
    public static function calculate( array $events ): array {
        $teams = array();

        foreach ( $events as $event ) {
            $team_id = (string) ( $event['team_source_id'] ?? '' );
            if ( '' === $team_id ) {
                continue;
            }

            if ( ! isset( $teams[ $team_id ] ) ) {
                $teams[ $team_id ] = self::empty_team_metrics( $team_id );
            }

            $metrics =& $teams[ $team_id ];
            ++$metrics['events'];

            $type    = (string) ( $event['event_type_name'] ?? '' );
            $outcome = (string) ( $event['outcome_name'] ?? '' );
            $x1      = self::float_or_null( $event['location_x'] ?? null );
            $y1      = self::float_or_null( $event['location_y'] ?? null );
            $x2      = self::float_or_null( $event['end_location_x'] ?? null );
            $y2      = self::float_or_null( $event['end_location_y'] ?? null );

            if ( 'Pass' === $type ) {
                ++$metrics['passes'];
                if ( '' === $outcome ) {
                    ++$metrics['completed_passes'];
                }

                if ( null !== $x1 && $x1 < 40.0 ) {
                    ++$metrics['build_up_actions'];
                    ++$metrics['build_up_passes'];

                    if ( '' === $outcome ) {
                        ++$metrics['build_up_completed_passes'];
                    } else {
                        ++$metrics['own_third_turnovers'];
                    }

                    if ( '' === $outcome && null !== $x2 && $x2 >= 40.0 ) {
                        ++$metrics['build_up_escapes'];
                    }
                }
            } elseif ( 'Shot' === $type ) {
                ++$metrics['shots'];
                $metrics['xg'] += (float) ( $event['xg'] ?? 0.0 );
                if ( 'Goal' === $outcome ) {
                    ++$metrics['goals'];
                }
            } elseif ( 'Carry' === $type ) {
                ++$metrics['carries'];
                if ( null !== $x1 && $x1 < 40.0 ) {
                    ++$metrics['build_up_actions'];
                    if ( null !== $x2 && $x2 >= 40.0 ) {
                        ++$metrics['build_up_escapes'];
                    }
                }
            } elseif ( 'Pressure' === $type ) {
                ++$metrics['pressures'];
                if ( null !== $x1 && $x1 >= 80.0 ) {
                    ++$metrics['high_pressures'];
                }
            } elseif ( 'Ball Recovery' === $type ) {
                ++$metrics['recoveries'];
                if ( null !== $x1 && $x1 >= 80.0 ) {
                    ++$metrics['high_recoveries'];
                }
            } elseif ( 'Duel' === $type ) {
                ++$metrics['duels'];
            } elseif ( in_array( $type, array( 'Dispossessed', 'Miscontrol' ), true ) && null !== $x1 && $x1 < 40.0 ) {
                ++$metrics['own_third_turnovers'];
            }

            if ( in_array( $type, array( 'Pass', 'Carry' ), true ) && null !== $x1 && null !== $x2 ) {
                if ( $x1 < 80.0 && $x2 >= 80.0 ) {
                    ++$metrics['final_third_entries'];
                }

                if ( self::outside_box( $x1, $y1 ) && self::inside_box( $x2, $y2 ) ) {
                    ++$metrics['box_entries'];
                }

                if ( ( $x2 - $x1 ) >= 15.0 ) {
                    ++$metrics['progressive_actions'];
                }

                if ( $x2 >= 80.0 && null !== $y2 ) {
                    ++$metrics['final_third_actions'];
                    if ( $y2 < ( 80.0 / 3.0 ) ) {
                        ++$metrics['final_third_left_channel'];
                    } elseif ( $y2 > ( 160.0 / 3.0 ) ) {
                        ++$metrics['final_third_right_channel'];
                    } else {
                        ++$metrics['final_third_central_channel'];
                    }
                }
            }
        }

        foreach ( $teams as &$metrics ) {
            $metrics['pass_completion_pct'] = self::percentage( $metrics['completed_passes'], $metrics['passes'] );
            $metrics['build_up_completion_pct'] = self::percentage( $metrics['build_up_completed_passes'], $metrics['build_up_passes'] );
            $metrics['build_up_escape_rate'] = self::percentage( $metrics['build_up_escapes'], $metrics['build_up_actions'] );
            $metrics['high_pressure_share_pct'] = self::percentage( $metrics['high_pressures'], $metrics['pressures'] );
            $metrics['xg'] = round( $metrics['xg'], 3 );

            $final_third_total = max( 1, $metrics['final_third_actions'] );
            $metrics['final_third_channel_share'] = array(
                'left'    => round( ( $metrics['final_third_left_channel'] / $final_third_total ) * 100, 1 ),
                'central' => round( ( $metrics['final_third_central_channel'] / $final_third_total ) * 100, 1 ),
                'right'   => round( ( $metrics['final_third_right_channel'] / $final_third_total ) * 100, 1 ),
            );
        }
        unset( $metrics );

        return $teams;
    }

    private static function empty_team_metrics( string $team_id ): array {
        return array(
            'team_source_id'              => $team_id,
            'events'                      => 0,
            'passes'                      => 0,
            'completed_passes'            => 0,
            'pass_completion_pct'         => 0.0,
            'shots'                       => 0,
            'goals'                       => 0,
            'xg'                          => 0.0,
            'carries'                     => 0,
            'pressures'                   => 0,
            'recoveries'                  => 0,
            'duels'                       => 0,
            'progressive_actions'         => 0,
            'final_third_entries'         => 0,
            'box_entries'                 => 0,
            'build_up_actions'            => 0,
            'build_up_passes'             => 0,
            'build_up_completed_passes'   => 0,
            'build_up_completion_pct'     => 0.0,
            'build_up_escapes'            => 0,
            'build_up_escape_rate'        => 0.0,
            'own_third_turnovers'         => 0,
            'high_pressures'              => 0,
            'high_recoveries'             => 0,
            'high_pressure_share_pct'     => 0.0,
            'final_third_actions'         => 0,
            'final_third_left_channel'    => 0,
            'final_third_central_channel' => 0,
            'final_third_right_channel'   => 0,
            'final_third_channel_share'   => array( 'left' => 0.0, 'central' => 0.0, 'right' => 0.0 ),
        );
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
