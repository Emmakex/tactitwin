<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Historical_Team_Profile_Service {
    public function team_profile_as_of( int $team_id, string $as_of_date, int $limit = 5 ): array {
        global $wpdb;

        if ( $team_id <= 0 ) {
            throw new InvalidArgumentException( 'A positive team identifier is required.' );
        }
        $as_of_date = trim( $as_of_date );
        if ( '' === $as_of_date ) {
            throw new InvalidArgumentException( 'Historical team profile requires an as-of date.' );
        }
        $limit = max( 1, min( 10, $limit ) );
        $match_table = TactiTwin_DB::table( 'matches' );
        $event_table = TactiTwin_DB::table( 'events' );
        $source = TactiTwin_StatsBomb_Normalizer::SOURCE;

        $matches = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.source_match_id, m.match_date, m.home_team_source_id, m.away_team_source_id
                 FROM {$match_table} m
                 WHERE m.source = %s
                   AND m.match_date <= %s
                   AND (m.home_team_source_id = %s OR m.away_team_source_id = %s)
                   AND EXISTS (
                       SELECT 1 FROM {$event_table} e
                       WHERE e.source = m.source AND e.source_match_id = m.source_match_id
                       LIMIT 1
                   )
                 ORDER BY m.match_date DESC, m.id DESC
                 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $source,
                $as_of_date,
                (string) $team_id,
                (string) $team_id,
                $limit
            ),
            ARRAY_A
        );

        if ( empty( $matches ) ) {
            throw new RuntimeException( 'No imported event-data matches are available for this team at or before the requested historical date.' );
        }

        $analysis = new TactiTwin_Analysis_Service();
        $samples = array();
        foreach ( $matches as $match ) {
            $summary = $analysis->match_summary( (int) $match['source_match_id'] );
            $team_key = (string) $team_id;
            if ( ! isset( $summary['teams'][ $team_key ] ) ) {
                continue;
            }
            $opponent_id = (string) $match['home_team_source_id'] === $team_key
                ? (string) $match['away_team_source_id']
                : (string) $match['home_team_source_id'];
            $samples[] = array(
                'match_id' => (string) $match['source_match_id'],
                'match_date' => (string) $match['match_date'],
                'team_metrics' => $summary['teams'][ $team_key ],
                'opponent_metrics' => $summary['teams'][ $opponent_id ] ?? array(),
            );
        }

        if ( empty( $samples ) ) {
            throw new RuntimeException( 'Historical imported matches do not contain normalized metrics for this team.' );
        }

        $profile = TactiTwin_Team_Profile::aggregate( $samples );
        $profile['historical_context'] = array(
            'as_of_date' => $as_of_date,
            'future_matches_excluded' => true,
            'sample_limit' => $limit,
        );
        return $profile;
    }
}
