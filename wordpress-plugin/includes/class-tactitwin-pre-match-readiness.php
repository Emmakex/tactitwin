<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Read-only view of the event-data sample that is actually eligible before the
 * active fixture. It mirrors the same temporal boundary used by preparation and
 * profile services without importing data or calculating tactical scores.
 */
final class TactiTwin_Pre_Match_Readiness {
    public static function inspect( array $context ): array {
        $team_id = trim( (string) ( $context['team_source_id'] ?? '' ) );
        $opponent_id = trim( (string) ( $context['opponent_source_id'] ?? '' ) );
        $match = is_array( $context['match'] ?? null ) ? $context['match'] : array();
        $fixture = is_array( $context['fixture'] ?? null ) ? $context['fixture'] : array();
        $source = trim( (string) ( $fixture['source'] ?? $match['source'] ?? TactiTwin_StatsBomb_Normalizer::SOURCE ) );
        $cutoff = trim( (string) ( $match['match_date'] ?? $fixture['match_date'] ?? '' ) );
        $excluded = trim( (string) ( $fixture['source_match_id'] ?? $match['source_match_id'] ?? '' ) );

        if ( '' === $team_id || '' === $opponent_id || $team_id === $opponent_id ) {
            throw new InvalidArgumentException( 'Pre-match readiness requires two different teams.' );
        }
        if ( '' === $source ) {
            throw new InvalidArgumentException( 'Pre-match readiness requires a data source.' );
        }
        if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $cutoff ) ) {
            throw new InvalidArgumentException( 'Pre-match readiness requires the active fixture date.' );
        }

        $team = self::sample( $source, $team_id, $cutoff, $excluded );
        $opponent = self::sample( $source, $opponent_id, $cutoff, $excluded );
        $effective = min( $team['matches'], $opponent['matches'] );
        $limiting = $team['matches'] < $opponent['matches']
            ? 'team'
            : ( $opponent['matches'] < $team['matches'] ? 'opponent' : 'balanced' );

        return array(
            'team' => $team,
            'opponent' => $opponent,
            'team_matches' => $team['matches'],
            'opponent_matches' => $opponent['matches'],
            'effective_matches' => $effective,
            'limiting_side' => $limiting,
            'status' => self::status( $effective ),
            'temporal_scope' => array(
                'before_date' => $cutoff,
                'exclude_match_id' => $excluded,
                'future_matches_excluded' => true,
                'target_fixture_excluded' => '' !== $excluded,
                'scope_source' => 'active-planning-context',
            ),
            'method' => array(
                'name' => 'pre-match-readiness-v1.38',
                'read_only' => true,
                'imports_data' => false,
                'calculates_tactical_score' => false,
                'effective_matches_formula' => 'min(team_matches, opponent_matches)',
            ),
        );
    }

    private static function sample( string $source, string $team_id, string $cutoff, string $excluded ): array {
        global $wpdb;
        $matches = TactiTwin_DB::table( 'matches' );
        $events = TactiTwin_DB::table( 'events' );

        $where = "WHERE m.source = %s
                   AND (m.home_team_source_id = %s OR m.away_team_source_id = %s)
                   AND m.match_date < %s
                   AND EXISTS (
                       SELECT 1 FROM {$events} e
                       WHERE e.source = m.source AND e.source_match_id = m.source_match_id
                       LIMIT 1
                   )";
        $args = array( $source, $team_id, $team_id, $cutoff );
        if ( '' !== $excluded ) {
            $where .= "\n                   AND m.source_match_id <> %s";
            $args[] = $excluded;
        }

        $sql = "SELECT DISTINCT m.source_match_id, m.match_date
                 FROM {$matches} m
                 {$where}
                 ORDER BY m.match_date DESC, m.id DESC"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare( $sql, ...$args ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            ARRAY_A
        );
        $rows = is_array( $rows ) ? $rows : array();

        $ids = array();
        $dates = array();
        foreach ( $rows as $row ) {
            $id = trim( (string) ( $row['source_match_id'] ?? '' ) );
            $date = trim( (string) ( $row['match_date'] ?? '' ) );
            if ( '' !== $id ) {
                $ids[] = $id;
            }
            if ( '' !== $date ) {
                $dates[] = $date;
            }
        }

        return array(
            'team_source_id' => $team_id,
            'matches' => count( $ids ),
            'match_ids' => $ids,
            'oldest_match' => empty( $dates ) ? null : min( $dates ),
            'newest_match' => empty( $dates ) ? null : max( $dates ),
            'confidence' => self::status( count( $ids ) ),
        );
    }

    private static function status( int $matches ): string {
        if ( $matches >= 5 ) {
            return 'high';
        }
        if ( $matches >= 3 ) {
            return 'medium';
        }
        return 'low';
    }
}
