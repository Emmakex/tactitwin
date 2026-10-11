<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Team_Profile {
    public static function aggregate( array $samples ): array {
        if ( empty( $samples ) ) {
            throw new InvalidArgumentException( 'At least one match sample is required.' );
        }

        $first       = $samples[0]['team_metrics'] ?? array();
        $team_id     = (string) ( $first['team_source_id'] ?? '' );
        $team_name   = (string) ( $first['team_name'] ?? $team_id );
        $match_count = count( $samples );

        $sum_keys = array(
            'events', 'passes', 'completed_passes', 'shots', 'box_shots', 'goals', 'xg', 'box_xg', 'carries',
            'pressures', 'recoveries', 'duels', 'defensive_actions', 'deep_defensive_actions',
            'progressive_actions', 'final_third_entries', 'box_entries',
            'build_up_actions', 'build_up_passes', 'build_up_completed_passes', 'build_up_escapes',
            'own_third_turnovers', 'high_pressures', 'high_recoveries', 'final_third_actions',
            'final_third_left_channel', 'final_third_central_channel', 'final_third_right_channel',
            'transition_turnovers', 'turnovers_own_third', 'turnovers_middle_third', 'turnovers_final_third',
            'opponent_progressions_within_10s', 'opponent_shots_within_10s', 'xg_conceded_within_10s',
            'counterpress_recoveries_within_8s', 'transition_progressions_created_within_10s',
            'transition_shots_created_within_10s', 'transition_xg_created_within_10s',
        );

        $totals          = array_fill_keys( $sum_keys, 0.0 );
        $opponent_totals = array_fill_keys( $sum_keys, 0.0 );
        $match_ids       = array();
        $match_dates     = array();

        foreach ( $samples as $sample ) {
            $team     = $sample['team_metrics'] ?? array();
            $opponent = $sample['opponent_metrics'] ?? array();

            foreach ( $sum_keys as $key ) {
                $totals[ $key ] += (float) ( $team[ $key ] ?? 0 );
                $opponent_totals[ $key ] += (float) ( $opponent[ $key ] ?? 0 );
            }

            if ( isset( $sample['match_id'] ) ) {
                $match_ids[] = (string) $sample['match_id'];
            }
            if ( ! empty( $sample['match_date'] ) ) {
                $match_dates[] = (string) $sample['match_date'];
            }
        }

        $average_keys = array(
            'passes', 'shots', 'box_shots', 'xg', 'box_xg', 'carries', 'pressures', 'recoveries', 'duels',
            'defensive_actions', 'deep_defensive_actions', 'progressive_actions', 'final_third_entries', 'box_entries',
            'build_up_actions', 'build_up_escapes', 'own_third_turnovers', 'high_pressures', 'high_recoveries',
            'transition_turnovers', 'turnovers_own_third', 'turnovers_middle_third', 'turnovers_final_third',
            'opponent_progressions_within_10s', 'opponent_shots_within_10s', 'xg_conceded_within_10s',
            'counterpress_recoveries_within_8s', 'transition_progressions_created_within_10s',
            'transition_shots_created_within_10s', 'transition_xg_created_within_10s',
        );

        $averages = array();
        foreach ( $average_keys as $key ) {
            $averages[ $key . '_per_match' ] = round( $totals[ $key ] / $match_count, 3 );
        }

        $rates = array(
            'pass_completion_pct'             => self::percentage( $totals['completed_passes'], $totals['passes'] ),
            'build_up_completion_pct'         => self::percentage( $totals['build_up_completed_passes'], $totals['build_up_passes'] ),
            'build_up_escape_rate'            => self::percentage( $totals['build_up_escapes'], $totals['build_up_actions'] ),
            'high_pressure_share_pct'         => self::percentage( $totals['high_pressures'], $totals['pressures'] ),
            'deep_defensive_action_share_pct' => self::percentage( $totals['deep_defensive_actions'], $totals['defensive_actions'] ),
            'box_shot_share_pct'              => self::percentage( $totals['box_shots'], $totals['shots'] ),
            'xg_per_shot'                     => self::ratio( $totals['xg'], $totals['shots'], 3 ),
            'box_xg_per_shot'                 => self::ratio( $totals['box_xg'], $totals['box_shots'], 3 ),
            'dangerous_turnover_rate'         => self::percentage( $totals['opponent_shots_within_10s'], $totals['transition_turnovers'] ),
            'transition_progression_rate'     => self::percentage( $totals['opponent_progressions_within_10s'], $totals['transition_turnovers'] ),
            'counterpress_recovery_rate'      => self::percentage( $totals['counterpress_recoveries_within_8s'], $totals['transition_turnovers'] ),
        );

        $channels         = self::channel_share( $totals );
        $channels_against = self::channel_share( $opponent_totals );
        $turnover_zones   = self::turnover_zone_share( $totals );

        $opponent_context = array(
            'pressures_per_match'             => round( $opponent_totals['pressures'] / $match_count, 2 ),
            'high_pressures_per_match'        => round( $opponent_totals['high_pressures'] / $match_count, 2 ),
            'high_recoveries_per_match'       => round( $opponent_totals['high_recoveries'] / $match_count, 2 ),
            'final_third_entries_per_match'   => round( $opponent_totals['final_third_entries'] / $match_count, 2 ),
            'box_entries_per_match'           => round( $opponent_totals['box_entries'] / $match_count, 2 ),
            'shots_per_match'                 => round( $opponent_totals['shots'] / $match_count, 2 ),
            'box_shots_per_match'             => round( $opponent_totals['box_shots'] / $match_count, 2 ),
            'xg_per_match'                    => round( $opponent_totals['xg'] / $match_count, 3 ),
            'box_xg_per_match'                => round( $opponent_totals['box_xg'] / $match_count, 3 ),
            'box_shot_share_pct'              => self::percentage( $opponent_totals['box_shots'], $opponent_totals['shots'] ),
            'xg_per_shot'                     => self::ratio( $opponent_totals['xg'], $opponent_totals['shots'], 3 ),
        );

        return array(
            'team_source_id' => $team_id,
            'team_name'      => $team_name,
            'coverage'       => array(
                'matches'      => $match_count,
                'match_ids'    => $match_ids,
                'oldest_match' => empty( $match_dates ) ? null : min( $match_dates ),
                'newest_match' => empty( $match_dates ) ? null : max( $match_dates ),
                'confidence'   => self::confidence( $match_count ),
            ),
            'totals'                            => $totals,
            'averages'                          => $averages,
            'rates'                             => $rates,
            'final_third_channel_share'         => $channels,
            'final_third_channel_share_against' => $channels_against,
            'turnover_zone_share'               => $turnover_zones,
            'opponent_context'                  => $opponent_context,
        );
    }

    private static function channel_share( array $totals ): array {
        $total = max( 1.0, (float) ( $totals['final_third_actions'] ?? 0 ) );

        return array(
            'left'    => round( ( (float) ( $totals['final_third_left_channel'] ?? 0 ) / $total ) * 100, 1 ),
            'central' => round( ( (float) ( $totals['final_third_central_channel'] ?? 0 ) / $total ) * 100, 1 ),
            'right'   => round( ( (float) ( $totals['final_third_right_channel'] ?? 0 ) / $total ) * 100, 1 ),
        );
    }

    private static function turnover_zone_share( array $totals ): array {
        $total = max( 1.0, (float) ( $totals['transition_turnovers'] ?? 0 ) );

        return array(
            'own_third'    => round( ( (float) ( $totals['turnovers_own_third'] ?? 0 ) / $total ) * 100, 1 ),
            'middle_third' => round( ( (float) ( $totals['turnovers_middle_third'] ?? 0 ) / $total ) * 100, 1 ),
            'final_third'  => round( ( (float) ( $totals['turnovers_final_third'] ?? 0 ) / $total ) * 100, 1 ),
        );
    }

    private static function percentage( float $numerator, float $denominator ): float {
        return $denominator > 0 ? round( ( $numerator / $denominator ) * 100, 1 ) : 0.0;
    }

    private static function ratio( float $numerator, float $denominator, int $precision = 2 ): float {
        return $denominator > 0 ? round( $numerator / $denominator, $precision ) : 0.0;
    }

    private static function confidence( int $matches ): string {
        if ( $matches >= 5 ) {
            return 'high';
        }
        if ( $matches >= 3 ) {
            return 'medium';
        }
        return 'low';
    }
}
