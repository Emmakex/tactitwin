<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Analysis_Service {
    public function match_summary( int $match_id ): array {
        global $wpdb;

        $match_table = TactiTwin_DB::table( 'matches' );
        $event_table = TactiTwin_DB::table( 'events' );
        $team_table  = TactiTwin_DB::table( 'teams' );

        $match = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$match_table} WHERE source = %s AND source_match_id = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                TactiTwin_StatsBomb_Normalizer::SOURCE,
                (string) $match_id
            ),
            ARRAY_A
        );

        if ( ! $match ) {
            throw new RuntimeException( 'Match metadata is not imported yet.' );
        }

        $events = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT event_index, period, minute, second, possession, possession_team_source_id,
                        team_source_id, player_source_id, position_name, event_type_name, outcome_name, xg,
                        location_x, location_y, end_location_x, end_location_y, raw_json
                 FROM {$event_table}
                 WHERE source = %s AND source_match_id = %s
                 ORDER BY event_index ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                TactiTwin_StatsBomb_Normalizer::SOURCE,
                (string) $match_id
            ),
            ARRAY_A
        );

        if ( empty( $events ) ) {
            throw new RuntimeException( 'Match events are not imported yet.' );
        }

        $metrics     = TactiTwin_Match_Metrics::calculate( $events );
        $transitions = TactiTwin_Transition_Metrics::calculate( $events );
        $team_ids    = array_values(
            array_filter(
                array_unique(
                    array(
                        (string) $match['home_team_source_id'],
                        (string) $match['away_team_source_id'],
                    )
                )
            )
        );
        $game_states    = TactiTwin_Game_State_Metrics::calculate( $events, $team_ids );
        $manpower_states = TactiTwin_Manpower_Metrics::calculate( $events, $team_ids );

        foreach ( $team_ids as $team_id ) {
            if ( ! isset( $metrics[ $team_id ] ) ) {
                $metrics[ $team_id ] = array( 'team_source_id' => $team_id );
            }

            $transition = $transitions[ $team_id ] ?? array();
            $zones      = $transition['turnovers_by_zone'] ?? array();
            unset( $transition['turnovers_by_zone'] );

            $metrics[ $team_id ] = array_merge(
                $metrics[ $team_id ],
                $transition,
                array(
                    'turnovers_own_third'    => (int) ( $zones['own_third'] ?? 0 ),
                    'turnovers_middle_third' => (int) ( $zones['middle_third'] ?? 0 ),
                    'turnovers_final_third'  => (int) ( $zones['final_third'] ?? 0 ),
                    'game_states'            => $game_states[ $team_id ] ?? array(),
                    'manpower_states'        => $manpower_states[ $team_id ] ?? array(),
                )
            );
        }

        $names = array();
        foreach ( $team_ids as $team_id ) {
            $name = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT team_name FROM {$team_table} WHERE source = %s AND source_team_id = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    TactiTwin_StatsBomb_Normalizer::SOURCE,
                    $team_id
                )
            );
            $names[ $team_id ] = $name ?: $team_id;
        }

        foreach ( $metrics as $team_id => &$team_metrics ) {
            $team_metrics['team_name'] = $names[ $team_id ] ?? $team_id;
        }
        unset( $team_metrics );

        return array(
            'match' => array(
                'source_match_id' => (string) $match['source_match_id'],
                'match_date'      => (string) $match['match_date'],
                'home_team'       => array(
                    'source_id' => (string) $match['home_team_source_id'],
                    'name'      => $names[ (string) $match['home_team_source_id'] ] ?? (string) $match['home_team_source_id'],
                    'score'     => null === $match['home_score'] ? null : (int) $match['home_score'],
                ),
                'away_team'       => array(
                    'source_id' => (string) $match['away_team_source_id'],
                    'name'      => $names[ (string) $match['away_team_source_id'] ] ?? (string) $match['away_team_source_id'],
                    'score'     => null === $match['away_score'] ? null : (int) $match['away_score'],
                ),
            ),
            'coverage' => array(
                'events' => count( $events ),
                'status' => 'event-data',
                'source' => TactiTwin_StatsBomb_Normalizer::SOURCE,
            ),
            'teams' => $metrics,
        );
    }
}
