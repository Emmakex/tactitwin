<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Player_Profile {
    public static function aggregate( array $samples, array $metadata = array() ): array {
        $sum_keys = array(
            'minutes', 'passes', 'completed_passes', 'carries', 'progressive_actions',
            'final_third_entries', 'box_entries', 'shots', 'xg', 'pressures',
            'recoveries', 'duels', 'turnovers',
        );
        $totals = array_fill_keys( $sum_keys, 0.0 );
        $appearances = 0;
        $starts = 0;
        $positions = array();
        $match_ids = array();

        foreach ( $samples as $sample ) {
            $metrics = $sample['metrics'] ?? array();
            if ( empty( $metrics ) || (float) ( $metrics['minutes'] ?? 0 ) <= 0.0 ) {
                continue;
            }

            ++$appearances;
            if ( ! empty( $metrics['starter'] ) ) {
                ++$starts;
            }
            if ( ! empty( $sample['match_id'] ) ) {
                $match_ids[] = (string) $sample['match_id'];
            }
            $position = (string) ( $metrics['position_name'] ?? '' );
            if ( '' !== $position ) {
                $positions[ $position ] = ( $positions[ $position ] ?? 0 ) + 1;
            }
            foreach ( $sum_keys as $key ) {
                $totals[ $key ] += (float) ( $metrics[ $key ] ?? 0 );
            }
        }

        $minutes = (float) $totals['minutes'];
        $factor  = $minutes > 0.0 ? 90.0 / $minutes : 0.0;
        arsort( $positions );
        $primary_position = ! empty( $positions ) ? (string) array_key_first( $positions ) : (string) ( $metadata['position_name'] ?? '' );

        return array(
            'player_source_id' => (string) ( $metadata['player_source_id'] ?? '' ),
            'player_name'      => (string) ( $metadata['player_name'] ?? ( $metadata['player_source_id'] ?? '' ) ),
            'team_source_id'   => (string) ( $metadata['team_source_id'] ?? '' ),
            'position_name'    => $primary_position,
            'positions'        => $positions,
            'coverage' => array(
                'minutes'     => round( $minutes, 1 ),
                'appearances' => $appearances,
                'starts'      => $starts,
                'match_ids'   => array_values( array_unique( $match_ids ) ),
                'confidence'  => self::confidence( $minutes ),
            ),
            'totals' => array(
                'passes'               => (int) $totals['passes'],
                'completed_passes'     => (int) $totals['completed_passes'],
                'carries'              => (int) $totals['carries'],
                'progressive_actions'  => (int) $totals['progressive_actions'],
                'final_third_entries'  => (int) $totals['final_third_entries'],
                'box_entries'          => (int) $totals['box_entries'],
                'shots'                => (int) $totals['shots'],
                'xg'                   => round( $totals['xg'], 3 ),
                'pressures'            => (int) $totals['pressures'],
                'recoveries'           => (int) $totals['recoveries'],
                'duels'                => (int) $totals['duels'],
                'turnovers'            => (int) $totals['turnovers'],
            ),
            'rates' => array(
                'pass_completion_pct' => self::percentage( $totals['completed_passes'], $totals['passes'] ),
            ),
            'per_90' => array(
                'passes'               => round( $totals['passes'] * $factor, 2 ),
                'carries'              => round( $totals['carries'] * $factor, 2 ),
                'progressive_actions'  => round( $totals['progressive_actions'] * $factor, 2 ),
                'final_third_entries'  => round( $totals['final_third_entries'] * $factor, 2 ),
                'box_entries'          => round( $totals['box_entries'] * $factor, 2 ),
                'shots'                => round( $totals['shots'] * $factor, 2 ),
                'xg'                   => round( $totals['xg'] * $factor, 3 ),
                'pressures'            => round( $totals['pressures'] * $factor, 2 ),
                'recoveries'           => round( $totals['recoveries'] * $factor, 2 ),
                'duels'                => round( $totals['duels'] * $factor, 2 ),
                'turnovers'            => round( $totals['turnovers'] * $factor, 2 ),
            ),
        );
    }

    private static function confidence( float $minutes ): string {
        if ( $minutes >= 450.0 ) {
            return 'high';
        }
        if ( $minutes >= 180.0 ) {
            return 'medium';
        }
        return 'low';
    }

    private static function percentage( float $numerator, float $denominator ): float {
        return $denominator > 0.0 ? round( ( $numerator / $denominator ) * 100.0, 1 ) : 0.0;
    }
}
