<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Phase_Unit_Profile {
    public static function aggregate( array $samples, array $metadata = array() ): array {
        $phase_keys = array( 'build_up', 'progression', 'final_third', 'high_press' );
        $totals = array();
        foreach ( $phase_keys as $phase ) {
            $totals[ $phase ] = self::empty_phase( $phase );
        }

        $shared_minutes = 0.0;
        $matches = 0;
        $match_ids = array();

        foreach ( $samples as $sample ) {
            $pair = $sample['metrics'] ?? $sample;
            if ( ! is_array( $pair ) || (float) ( $pair['shared_minutes'] ?? 0.0 ) <= 0.0 ) {
                continue;
            }
            $shared_minutes += (float) $pair['shared_minutes'];
            ++$matches;
            if ( ! empty( $sample['match_id'] ) ) {
                $match_ids[] = (string) $sample['match_id'];
            }

            foreach ( $phase_keys as $phase ) {
                foreach ( array_keys( $totals[ $phase ] ) as $key ) {
                    $totals[ $phase ][ $key ] += (float) ( $pair['phases'][ $phase ][ $key ] ?? 0.0 );
                }
            }
        }

        $factor = $shared_minutes > 0.0 ? 90.0 / $shared_minutes : 0.0;
        $phases = array();
        foreach ( $phase_keys as $phase ) {
            $phases[ $phase ] = self::finalize_phase( $phase, $totals[ $phase ], $factor );
        }

        return array(
            'pair_key'      => (string) ( $metadata['pair_key'] ?? '' ),
            'player_a_id'   => (string) ( $metadata['player_a_id'] ?? '' ),
            'player_a_name' => (string) ( $metadata['player_a_name'] ?? ( $metadata['player_a_id'] ?? '' ) ),
            'player_b_id'   => (string) ( $metadata['player_b_id'] ?? '' ),
            'player_b_name' => (string) ( $metadata['player_b_name'] ?? ( $metadata['player_b_id'] ?? '' ) ),
            'coverage' => array(
                'shared_minutes'   => round( $shared_minutes, 1 ),
                'matches_observed' => $matches,
                'match_ids'        => array_values( array_unique( $match_ids ) ),
                'confidence'       => self::confidence( $shared_minutes, $matches ),
            ),
            'phases' => $phases,
            'dominant_phase' => self::dominant_phase( $phases ),
        );
    }

    private static function empty_phase( string $phase ): array {
        if ( 'high_press' === $phase ) {
            return array( 'pressure_recovery_sequences'=>0.0 );
        }
        $base = array(
            'completed_passes'=>0.0,
            'progressive_passes'=>0.0,
            'final_third_entries'=>0.0,
            'box_entries'=>0.0,
            'shot_assists'=>0.0,
            'goal_assists'=>0.0,
        );
        if ( 'final_third' === $phase ) {
            $base['joint_shot_possessions'] = 0.0;
            $base['joint_possession_xg'] = 0.0;
        }
        return $base;
    }

    private static function finalize_phase( string $phase, array $totals, float $factor ): array {
        $per90 = array();
        foreach ( $totals as $key => $value ) {
            $precision = 'joint_possession_xg' === $key ? 3 : 2;
            $per90[ $key ] = round( (float) $value * $factor, $precision );
        }

        $signal = 0.0;
        if ( 'build_up' === $phase ) {
            $signal = (float) ( $per90['completed_passes'] ?? 0.0 )
                + ( 0.75 * (float) ( $per90['progressive_passes'] ?? 0.0 ) );
        } elseif ( 'progression' === $phase ) {
            $signal = (float) ( $per90['progressive_passes'] ?? 0.0 )
                + ( 0.50 * (float) ( $per90['final_third_entries'] ?? 0.0 ) )
                + ( 0.75 * (float) ( $per90['box_entries'] ?? 0.0 ) );
        } elseif ( 'final_third' === $phase ) {
            $signal = (float) ( $per90['joint_possession_xg'] ?? 0.0 )
                + ( 0.25 * (float) ( $per90['shot_assists'] ?? 0.0 ) )
                + ( 0.10 * (float) ( $per90['box_entries'] ?? 0.0 ) );
        } elseif ( 'high_press' === $phase ) {
            $signal = (float) ( $per90['pressure_recovery_sequences'] ?? 0.0 );
        }

        return array(
            'totals' => array_map(
                static fn( $value ) => is_float( $value ) && floor( $value ) !== $value ? round( $value, 3 ) : (int) $value,
                $totals
            ),
            'per_90_shared' => $per90,
            'signal' => round( $signal, 'final_third' === $phase ? 3 : 2 ),
        );
    }

    private static function dominant_phase( array $phases ): ?string {
        $best = null;
        $best_value = 0.0;
        foreach ( $phases as $phase => $data ) {
            $value = (float) ( $data['signal'] ?? 0.0 );
            if ( $value > $best_value ) {
                $best_value = $value;
                $best = (string) $phase;
            }
        }
        return $best;
    }

    private static function confidence( float $minutes, int $matches ): string {
        if ( $minutes >= 360.0 && $matches >= 4 ) {
            return 'high';
        }
        if ( $minutes >= 180.0 && $matches >= 2 ) {
            return 'medium';
        }
        return 'low';
    }
}
