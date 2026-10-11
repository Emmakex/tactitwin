<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Match_Target_Service {
    public function target( int $match_row_id ): array {
        global $wpdb;

        if ( $match_row_id <= 0 ) {
            return array();
        }

        $matches = TactiTwin_DB::table( 'matches' );
        $teams = TactiTwin_DB::table( 'teams' );
        $competitions = TactiTwin_DB::table( 'competitions' );

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    m.id,
                    m.source,
                    m.source_match_id,
                    m.source_competition_id,
                    m.source_season_id,
                    m.match_date,
                    m.kick_off,
                    m.home_team_source_id,
                    m.away_team_source_id,
                    m.home_score,
                    m.away_score,
                    m.match_week,
                    m.match_status,
                    home.team_name AS home_team_name,
                    away.team_name AS away_team_name,
                    comp.competition_name,
                    comp.season_name
                 FROM {$matches} m
                 LEFT JOIN {$teams} home
                    ON home.source = m.source
                   AND home.source_team_id = m.home_team_source_id
                 LEFT JOIN {$teams} away
                    ON away.source = m.source
                   AND away.source_team_id = m.away_team_source_id
                 LEFT JOIN {$competitions} comp
                    ON comp.source = m.source
                   AND comp.source_competition_id = m.source_competition_id
                   AND comp.source_season_id = m.source_season_id
                 WHERE m.id = %d
                 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $match_row_id
            ),
            ARRAY_A
        ) ?: array();
    }

    public function target_by_source( string $source, string $source_match_id ): array {
        global $wpdb;

        $source = trim( $source );
        $source_match_id = trim( $source_match_id );
        if ( '' === $source || '' === $source_match_id ) {
            return array();
        }

        $matches = TactiTwin_DB::table( 'matches' );
        $id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$matches} WHERE source = %s AND source_match_id = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $source,
                $source_match_id
            )
        );
        return $id > 0 ? $this->target( $id ) : array();
    }

    public function recent_targets( int $limit = 100 ): array {
        global $wpdb;

        $limit = max( 1, min( 250, $limit ) );
        $matches = TactiTwin_DB::table( 'matches' );
        $teams = TactiTwin_DB::table( 'teams' );
        $competitions = TactiTwin_DB::table( 'competitions' );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    m.id,
                    m.source,
                    m.source_match_id,
                    m.source_competition_id,
                    m.source_season_id,
                    m.match_date,
                    m.kick_off,
                    m.home_team_source_id,
                    m.away_team_source_id,
                    m.home_score,
                    m.away_score,
                    m.match_week,
                    m.match_status,
                    home.team_name AS home_team_name,
                    away.team_name AS away_team_name,
                    comp.competition_name,
                    comp.season_name
                 FROM {$matches} m
                 LEFT JOIN {$teams} home
                    ON home.source = m.source
                   AND home.source_team_id = m.home_team_source_id
                 LEFT JOIN {$teams} away
                    ON away.source = m.source
                   AND away.source_team_id = m.away_team_source_id
                 LEFT JOIN {$competitions} comp
                    ON comp.source = m.source
                   AND comp.source_competition_id = m.source_competition_id
                   AND comp.source_season_id = m.source_season_id
                 ORDER BY
                    CASE WHEN m.match_date IS NULL THEN 1 ELSE 0 END ASC,
                    m.match_date DESC,
                    m.kick_off DESC,
                    m.id DESC
                 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $limit
            ),
            ARRAY_A
        ) ?: array();
    }

    public function resolve( int $match_row_id, string $team_source_id ): array {
        $match = $this->target( $match_row_id );
        if ( empty( $match ) ) {
            throw new RuntimeException( 'Selected target match was not found in TactiTwin.' );
        }
        return self::resolve_row( $match, $team_source_id );
    }

    public static function resolve_row( array $match, string $team_source_id ): array {
        $team_source_id = trim( $team_source_id );
        $home_id = trim( (string) ( $match['home_team_source_id'] ?? '' ) );
        $away_id = trim( (string) ( $match['away_team_source_id'] ?? '' ) );
        $source = trim( (string) ( $match['source'] ?? '' ) );
        $source_match_id = trim( (string) ( $match['source_match_id'] ?? '' ) );

        if ( '' === $source || '' === $source_match_id || '' === $home_id || '' === $away_id || $home_id === $away_id ) {
            throw new InvalidArgumentException( 'Stored target match is missing canonical source or participant identity.' );
        }
        if ( '' === $team_source_id ) {
            throw new InvalidArgumentException( 'A target match requires an explicit team perspective.' );
        }

        if ( $team_source_id === $home_id ) {
            $opponent_source_id = $away_id;
            $perspective = 'home';
        } elseif ( $team_source_id === $away_id ) {
            $opponent_source_id = $home_id;
            $perspective = 'away';
        } else {
            throw new InvalidArgumentException( 'Selected team is not a participant in the target match.' );
        }

        $fixture = TactiTwin_Fixture_Identity::normalize(
            array(
                'source' => $source,
                'source_match_id' => $source_match_id,
                'source_competition_id' => (string) ( $match['source_competition_id'] ?? '' ),
                'source_season_id' => (string) ( $match['source_season_id'] ?? '' ),
                'match_date' => (string) ( $match['match_date'] ?? '' ),
                'kick_off' => (string) ( $match['kick_off'] ?? '' ),
                'match_week' => (string) ( $match['match_week'] ?? '' ),
            ),
            $team_source_id,
            $opponent_source_id
        );

        return array(
            'target_match_row_id' => (int) ( $match['id'] ?? 0 ),
            'team_source_id' => $team_source_id,
            'opponent_source_id' => $opponent_source_id,
            'perspective' => $perspective,
            'fixture' => $fixture,
            'match' => $match,
        );
    }
}
