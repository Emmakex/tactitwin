<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Formation_Model {
    public static function supported(): array {
        return array_keys( self::templates() );
    }

    public static function template( string $formation ): array {
        $formation = self::normalize( $formation );
        $templates = self::templates();
        if ( ! isset( $templates[ $formation ] ) ) {
            throw new InvalidArgumentException( 'Unsupported formation.' );
        }
        return $templates[ $formation ];
    }

    public static function normalize( string $formation ): string {
        $formation = trim( str_replace( array( '_', ' ' ), '-', $formation ) );
        return preg_replace( '/-+/', '-', $formation ) ?: $formation;
    }

    public static function role_compatibility( string $position_name, string $role ): array {
        $historical = self::position_family( $position_name );
        $expected   = self::role_family( $role );
        $score      = self::compatibility_score( $historical, $expected );

        return array(
            'historical_family' => $historical,
            'expected_family'   => $expected,
            'score'             => $score,
            'level'             => $score >= 85 ? 'high' : ( $score >= 65 ? 'medium' : 'low' ),
        );
    }

    /**
     * Orders a provider Starting XI into TactiTwin's canonical role order.
     *
     * The assignment is solved globally rather than greedily so two similar
     * players cannot both claim the same best role. Position family and
     * left/right information are kept separate and explainable.
     */
    public static function assign_players_to_roles( array $players, string $formation ): array {
        $template = self::template( $formation );
        $roles    = $template['roles'];

        if ( 11 !== count( $players ) || 11 !== count( $roles ) ) {
            throw new InvalidArgumentException( 'Formation assignment requires exactly 11 players and 11 roles.' );
        }

        $states = array(
            0 => array(
                'score'      => 0.0,
                'assignment' => array(),
            ),
        );

        foreach ( array_values( $players ) as $player_index => $player ) {
            $next = array();
            foreach ( $states as $mask => $state ) {
                foreach ( $roles as $role_index => $role ) {
                    $bit = 1 << $role_index;
                    if ( $mask & $bit ) {
                        continue;
                    }

                    $new_mask  = $mask | $bit;
                    $new_score = (float) $state['score'] + self::assignment_score(
                        (string) ( $player['position_name'] ?? '' ),
                        (string) $role
                    );
                    $assignment = $state['assignment'];
                    $assignment[ $role_index ] = $player_index;

                    if ( ! isset( $next[ $new_mask ] ) || $new_score > (float) $next[ $new_mask ]['score'] ) {
                        $next[ $new_mask ] = array(
                            'score'      => $new_score,
                            'assignment' => $assignment,
                        );
                    }
                }
            }
            $states = $next;
        }

        $full_mask = ( 1 << count( $roles ) ) - 1;
        if ( ! isset( $states[ $full_mask ] ) ) {
            throw new RuntimeException( 'Unable to assign the Starting XI to formation roles.' );
        }

        $assignment = $states[ $full_mask ]['assignment'];
        ksort( $assignment );
        $ordered = array();
        $slots   = array();

        foreach ( $roles as $role_index => $role ) {
            $player_index = (int) $assignment[ $role_index ];
            $player       = $players[ $player_index ];
            $fit          = self::role_compatibility( (string) ( $player['position_name'] ?? '' ), (string) $role );
            $ordered[]    = $player;
            $slots[]      = array(
                'role'                => (string) $role,
                'player_source_id'    => (string) ( $player['player_source_id'] ?? '' ),
                'player_name'         => (string) ( $player['player_name'] ?? '' ),
                'historical_position' => (string) ( $player['position_name'] ?? '' ),
                'role_fit'            => $fit,
                'assignment_score'    => round( self::assignment_score( (string) ( $player['position_name'] ?? '' ), (string) $role ), 1 ),
            );
        }

        return array(
            'players'     => $ordered,
            'slots'       => $slots,
            'total_score' => round( (float) $states[ $full_mask ]['score'], 1 ),
            'method'      => 'global-position-family-and-side-assignment',
        );
    }

    public static function structural_delta( string $baseline, string $candidate ): array {
        $a = self::template( $baseline );
        $b = self::template( $candidate );
        $keys = array( 'width', 'central_density', 'rest_defence', 'front_line', 'between_lines', 'double_pivot' );
        $delta = array();
        foreach ( $keys as $key ) {
            $delta[ $key ] = round( (float) ( $b['structure'][ $key ] ?? 0 ) - (float) ( $a['structure'][ $key ] ?? 0 ), 1 );
        }
        return $delta;
    }

    private static function assignment_score( string $position_name, string $role ): float {
        $fit   = self::role_compatibility( $position_name, $role );
        $score = (float) $fit['score'];

        $position_side = self::position_side( $position_name );
        $role_side     = self::role_side( $role );

        if ( in_array( $position_side, array( 'left', 'right' ), true ) && in_array( $role_side, array( 'left', 'right' ), true ) ) {
            $score += $position_side === $role_side ? 18.0 : -22.0;
        } elseif ( 'center' === $position_side && 'center' === $role_side ) {
            $score += 8.0;
        }

        return max( 0.0, $score );
    }

    private static function templates(): array {
        return array(
            '4-3-3' => array(
                'roles' => array( 'GK', 'RB', 'RCB', 'LCB', 'LB', 'DM', 'RCM', 'LCM', 'RW', 'ST', 'LW' ),
                'structure' => array( 'width' => 82, 'central_density' => 72, 'rest_defence' => 72, 'front_line' => 82, 'between_lines' => 65, 'double_pivot' => 20 ),
                'traits' => array( 'back_four', 'single_pivot', 'two_interiors', 'two_wingers', 'single_striker' ),
            ),
            '4-2-3-1' => array(
                'roles' => array( 'GK', 'RB', 'RCB', 'LCB', 'LB', 'RDM', 'LDM', 'RW', 'AM', 'LW', 'ST' ),
                'structure' => array( 'width' => 80, 'central_density' => 82, 'rest_defence' => 80, 'front_line' => 72, 'between_lines' => 84, 'double_pivot' => 95 ),
                'traits' => array( 'back_four', 'double_pivot', 'number_ten', 'two_wingers', 'single_striker' ),
            ),
            '4-4-2' => array(
                'roles' => array( 'GK', 'RB', 'RCB', 'LCB', 'LB', 'RM', 'RCM', 'LCM', 'LM', 'RST', 'LST' ),
                'structure' => array( 'width' => 84, 'central_density' => 66, 'rest_defence' => 74, 'front_line' => 88, 'between_lines' => 52, 'double_pivot' => 55 ),
                'traits' => array( 'back_four', 'flat_midfield', 'wide_midfielders', 'two_strikers' ),
            ),
            '3-4-2-1' => array(
                'roles' => array( 'GK', 'RCB', 'CB', 'LCB', 'RWB', 'RCM', 'LCM', 'LWB', 'RAM', 'LAM', 'ST' ),
                'structure' => array( 'width' => 90, 'central_density' => 78, 'rest_defence' => 84, 'front_line' => 74, 'between_lines' => 92, 'double_pivot' => 70 ),
                'traits' => array( 'back_three', 'wingbacks', 'two_tens', 'single_striker' ),
            ),
            '3-5-2' => array(
                'roles' => array( 'GK', 'RCB', 'CB', 'LCB', 'RWB', 'RCM', 'DM', 'LCM', 'LWB', 'RST', 'LST' ),
                'structure' => array( 'width' => 88, 'central_density' => 92, 'rest_defence' => 86, 'front_line' => 84, 'between_lines' => 68, 'double_pivot' => 45 ),
                'traits' => array( 'back_three', 'wingbacks', 'three_central_midfielders', 'two_strikers' ),
            ),
            '5-4-1' => array(
                'roles' => array( 'GK', 'RWB', 'RCB', 'CB', 'LCB', 'LWB', 'RM', 'RCM', 'LCM', 'LM', 'ST' ),
                'structure' => array( 'width' => 86, 'central_density' => 80, 'rest_defence' => 96, 'front_line' => 48, 'between_lines' => 50, 'double_pivot' => 55 ),
                'traits' => array( 'back_five', 'wide_midfielders', 'single_striker', 'low_block_friendly' ),
            ),
        );
    }

    private static function role_family( string $role ): string {
        $role = strtoupper( trim( $role ) );
        if ( 'GK' === $role ) {
            return 'goalkeeper';
        }
        if ( in_array( $role, array( 'RCB', 'CB', 'LCB' ), true ) ) {
            return 'centerback';
        }
        if ( in_array( $role, array( 'RB', 'LB' ), true ) ) {
            return 'fullback';
        }
        if ( in_array( $role, array( 'RWB', 'LWB' ), true ) ) {
            return 'wingback';
        }
        if ( in_array( $role, array( 'DM', 'RDM', 'LDM' ), true ) ) {
            return 'defensive_midfield';
        }
        if ( in_array( $role, array( 'RCM', 'CM', 'LCM' ), true ) ) {
            return 'central_midfield';
        }
        if ( in_array( $role, array( 'AM', 'RAM', 'LAM' ), true ) ) {
            return 'attacking_midfield';
        }
        if ( in_array( $role, array( 'RW', 'LW' ), true ) ) {
            return 'winger';
        }
        if ( in_array( $role, array( 'RM', 'LM' ), true ) ) {
            return 'wide_midfield';
        }
        if ( in_array( $role, array( 'ST', 'RST', 'LST' ), true ) ) {
            return 'striker';
        }
        return 'unknown';
    }

    private static function position_family( string $position ): string {
        $position = strtolower( trim( $position ) );
        if ( '' === $position ) {
            return 'unknown';
        }
        if ( str_contains( $position, 'goalkeeper' ) ) {
            return 'goalkeeper';
        }
        if ( str_contains( $position, 'center back' ) || str_contains( $position, 'centre back' ) ) {
            return 'centerback';
        }
        if ( str_contains( $position, 'wing back' ) || str_contains( $position, 'wing-back' ) ) {
            return 'wingback';
        }
        if ( str_contains( $position, 'right back' ) || str_contains( $position, 'left back' ) || 'back' === $position ) {
            return 'fullback';
        }
        if ( str_contains( $position, 'defensive midfield' ) ) {
            return 'defensive_midfield';
        }
        if ( str_contains( $position, 'attacking midfield' ) ) {
            return 'attacking_midfield';
        }
        if ( str_contains( $position, 'central midfield' ) || str_contains( $position, 'center midfield' ) || str_contains( $position, 'centre midfield' ) ) {
            return 'central_midfield';
        }
        if ( str_contains( $position, 'right midfield' ) || str_contains( $position, 'left midfield' ) ) {
            return 'wide_midfield';
        }
        if ( str_contains( $position, 'right wing' ) || str_contains( $position, 'left wing' ) || 'winger' === $position ) {
            return 'winger';
        }
        if ( str_contains( $position, 'center forward' ) || str_contains( $position, 'centre forward' ) || str_contains( $position, 'striker' ) ) {
            return 'striker';
        }
        return 'unknown';
    }

    private static function position_side( string $position ): string {
        $position = strtolower( trim( $position ) );
        if ( str_contains( $position, 'right' ) ) {
            return 'right';
        }
        if ( str_contains( $position, 'left' ) ) {
            return 'left';
        }
        return 'center';
    }

    private static function role_side( string $role ): string {
        $role = strtoupper( trim( $role ) );
        if ( str_starts_with( $role, 'R' ) && ! in_array( $role, array( 'RM' ), true ) ) {
            return 'right';
        }
        if ( str_starts_with( $role, 'L' ) && ! in_array( $role, array( 'LM' ), true ) ) {
            return 'left';
        }
        if ( in_array( $role, array( 'RM' ), true ) ) {
            return 'right';
        }
        if ( in_array( $role, array( 'LM' ), true ) ) {
            return 'left';
        }
        return 'center';
    }

    private static function compatibility_score( string $historical, string $expected ): float {
        if ( $historical === $expected && 'unknown' !== $historical ) {
            return 100.0;
        }
        if ( 'goalkeeper' === $historical || 'goalkeeper' === $expected ) {
            return 0.0;
        }
        $pairs = array(
            'fullback:wingback' => 92.0,
            'wingback:fullback' => 92.0,
            'winger:wide_midfield' => 88.0,
            'wide_midfield:winger' => 88.0,
            'central_midfield:defensive_midfield' => 84.0,
            'defensive_midfield:central_midfield' => 84.0,
            'central_midfield:attacking_midfield' => 82.0,
            'attacking_midfield:central_midfield' => 82.0,
            'attacking_midfield:winger' => 78.0,
            'winger:attacking_midfield' => 78.0,
            'striker:winger' => 70.0,
            'winger:striker' => 70.0,
            'centerback:fullback' => 66.0,
            'fullback:centerback' => 66.0,
            'centerback:wingback' => 58.0,
            'wingback:centerback' => 58.0,
        );
        $key = $historical . ':' . $expected;
        if ( isset( $pairs[ $key ] ) ) {
            return $pairs[ $key ];
        }
        if ( 'unknown' === $historical || 'unknown' === $expected ) {
            return 50.0;
        }
        return 42.0;
    }
}
