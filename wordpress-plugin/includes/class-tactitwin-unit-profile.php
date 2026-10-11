<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Unit_Profile {
    public static function aggregate( array $samples, array $metadata = array() ): array {
        $sum_keys = array(
            'shared_seconds', 'completed_passes', 'passes_a_to_b', 'passes_b_to_a',
            'progressive_passes', 'final_third_entries', 'box_entries', 'shot_assists',
            'goal_assists', 'joint_possessions', 'joint_shot_possessions',
            'joint_possession_xg', 'pressure_recovery_sequences',
        );
        $totals = array_fill_keys( $sum_keys, 0.0 );
        $matches_observed = 0;
        $match_ids = array();

        foreach ( $samples as $sample ) {
            $pair = $sample['metrics'] ?? $sample;
            if ( ! is_array( $pair ) ) {
                continue;
            }
            $shared_seconds = (float) ( $pair['shared_seconds'] ?? 0.0 );
            if ( $shared_seconds <= 0.0 ) {
                continue;
            }

            ++$matches_observed;
            if ( ! empty( $sample['match_id'] ) ) {
                $match_ids[] = (string) $sample['match_id'];
            }
            foreach ( $sum_keys as $key ) {
                $totals[ $key ] += (float) ( $pair[ $key ] ?? 0.0 );
            }
        }

        $minutes = max( 0.0, (float) $totals['shared_seconds'] / 60.0 );
        $factor  = $minutes > 0.0 ? 90.0 / $minutes : 0.0;
        $forward = (int) $totals['passes_a_to_b'];
        $back    = (int) $totals['passes_b_to_a'];
        $larger  = max( $forward, $back );
        $smaller = min( $forward, $back );

        $per90 = array(
            'completed_passes'            => round( $totals['completed_passes'] * $factor, 2 ),
            'progressive_passes'          => round( $totals['progressive_passes'] * $factor, 2 ),
            'final_third_entries'         => round( $totals['final_third_entries'] * $factor, 2 ),
            'box_entries'                 => round( $totals['box_entries'] * $factor, 2 ),
            'shot_assists'                => round( $totals['shot_assists'] * $factor, 2 ),
            'goal_assists'                => round( $totals['goal_assists'] * $factor, 2 ),
            'joint_possessions'           => round( $totals['joint_possessions'] * $factor, 2 ),
            'joint_shot_possessions'      => round( $totals['joint_shot_possessions'] * $factor, 2 ),
            'joint_possession_xg'         => round( $totals['joint_possession_xg'] * $factor, 3 ),
            'pressure_recovery_sequences' => round( $totals['pressure_recovery_sequences'] * $factor, 2 ),
        );

        return array(
            'pair_key'      => (string) ( $metadata['pair_key'] ?? '' ),
            'player_a_id'   => (string) ( $metadata['player_a_id'] ?? '' ),
            'player_a_name' => (string) ( $metadata['player_a_name'] ?? ( $metadata['player_a_id'] ?? '' ) ),
            'player_b_id'   => (string) ( $metadata['player_b_id'] ?? '' ),
            'player_b_name' => (string) ( $metadata['player_b_name'] ?? ( $metadata['player_b_id'] ?? '' ) ),
            'coverage' => array(
                'shared_minutes'   => round( $minutes, 1 ),
                'matches_observed' => $matches_observed,
                'match_ids'        => array_values( array_unique( $match_ids ) ),
                'confidence'       => self::confidence( $minutes, $matches_observed ),
            ),
            'totals' => array(
                'completed_passes'            => (int) $totals['completed_passes'],
                'passes_a_to_b'                => $forward,
                'passes_b_to_a'                => $back,
                'progressive_passes'           => (int) $totals['progressive_passes'],
                'final_third_entries'          => (int) $totals['final_third_entries'],
                'box_entries'                  => (int) $totals['box_entries'],
                'shot_assists'                 => (int) $totals['shot_assists'],
                'goal_assists'                 => (int) $totals['goal_assists'],
                'joint_possessions'            => (int) $totals['joint_possessions'],
                'joint_shot_possessions'       => (int) $totals['joint_shot_possessions'],
                'joint_possession_xg'          => round( $totals['joint_possession_xg'], 3 ),
                'pressure_recovery_sequences'  => (int) $totals['pressure_recovery_sequences'],
            ),
            'direction_balance_pct' => $larger > 0 ? round( ( $smaller / $larger ) * 100.0, 1 ) : 0.0,
            'per_90_shared' => $per90,
            'signals' => array(
                'connectivity'       => $per90['completed_passes'],
                'progressive_link'   => round( $per90['progressive_passes'] + ( 0.50 * $per90['final_third_entries'] ) + ( 0.75 * $per90['box_entries'] ), 2 ),
                'chance_chain'       => round( $per90['joint_possession_xg'] + ( 0.25 * $per90['shot_assists'] ), 3 ),
                'defensive_sequence' => $per90['pressure_recovery_sequences'],
            ),
        );
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
