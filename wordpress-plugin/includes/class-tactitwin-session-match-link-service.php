<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Session_Match_Link_Service {
    public function link( string $session_uuid, int $match_id ): array {
        global $wpdb;

        if ( $match_id <= 0 ) {
            throw new InvalidArgumentException( 'A positive imported match ID is required.' );
        }

        $session_service = new TactiTwin_Live_Session_Service();
        $session = $session_service->session( $session_uuid );
        if ( empty( $session ) ) {
            throw new RuntimeException( 'Live session not found.' );
        }

        $match = $this->match( $match_id );
        if ( empty( $match ) ) {
            throw new RuntimeException( 'The selected match is not imported.' );
        }

        $session_pair = array_map( 'strval', array(
            $session['team_source_id'] ?? '',
            $session['opponent_source_id'] ?? '',
        ) );
        $match_pair = array_map( 'strval', array(
            $match['home_team_source_id'] ?? '',
            $match['away_team_source_id'] ?? '',
        ) );
        sort( $session_pair );
        sort( $match_pair );
        if ( $session_pair !== $match_pair ) {
            throw new InvalidArgumentException( 'The imported match does not contain the same two teams as the live session.' );
        }

        $now = current_time( 'mysql', true );
        $updated = $wpdb->update(
            TactiTwin_DB::table( 'live_sessions' ),
            array(
                'source_match_id' => (string) $match_id,
                'updated_at' => $now,
            ),
            array( 'session_uuid' => (string) $session['session_uuid'] ),
            array( '%s', '%s' ),
            array( '%s' )
        );
        if ( false === $updated ) {
            throw new RuntimeException( 'Could not link the imported match to the live session.' );
        }

        return array(
            'session' => $session_service->session( (string) $session['session_uuid'] ),
            'match' => $match,
        );
    }

    public function matching_matches( int $team_id, int $opponent_id, int $limit = 30 ): array {
        global $wpdb;

        if ( $team_id <= 0 || $opponent_id <= 0 || $team_id === $opponent_id ) {
            return array();
        }
        $limit = max( 1, min( 100, $limit ) );
        $table = TactiTwin_DB::table( 'matches' );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT source_match_id, match_date, kick_off, home_team_source_id, away_team_source_id, home_score, away_score, match_status
                 FROM {$table}
                 WHERE source = %s
                   AND (
                       (home_team_source_id = %s AND away_team_source_id = %s)
                       OR
                       (home_team_source_id = %s AND away_team_source_id = %s)
                   )
                 ORDER BY match_date DESC, id DESC
                 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                TactiTwin_StatsBomb_Normalizer::SOURCE,
                (string) $team_id,
                (string) $opponent_id,
                (string) $opponent_id,
                (string) $team_id,
                $limit
            ),
            ARRAY_A
        ) ?: array();
    }

    public function match( int $match_id ): array {
        global $wpdb;

        $table = TactiTwin_DB::table( 'matches' );
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT source_match_id, match_date, kick_off, home_team_source_id, away_team_source_id, home_score, away_score, match_status
                 FROM {$table}
                 WHERE source = %s AND source_match_id = %s
                 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                TactiTwin_StatsBomb_Normalizer::SOURCE,
                (string) $match_id
            ),
            ARRAY_A
        ) ?: array();
    }
}
