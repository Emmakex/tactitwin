<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Data_Preparation_Service {
    public function ensure_team_sample( int $team_id, int $target = 5, array $scope = array() ): array {
        global $wpdb;

        $target      = max( 1, min( 10, $target ) );
        $match_table = TactiTwin_DB::table( 'matches' );
        $event_table = TactiTwin_DB::table( 'events' );
        $source      = TactiTwin_StatsBomb_Normalizer::SOURCE;
        $scope       = $this->normalize_scope( $scope );

        $where = "WHERE m.source = %s
                   AND (m.home_team_source_id = %s OR m.away_team_source_id = %s)";
        $args = array(
            $source,
            (string) $team_id,
            (string) $team_id,
        );

        if ( '' !== $scope['before_date'] ) {
            $where .= "\n                   AND m.match_date < %s";
            $args[] = $scope['before_date'];
        }

        if ( '' !== $scope['exclude_match_id'] ) {
            $where .= "\n                   AND m.source_match_id <> %s";
            $args[] = $scope['exclude_match_id'];
        }

        $sql = "SELECT m.source_match_id, m.match_date,
                        EXISTS(
                            SELECT 1 FROM {$event_table} e
                            WHERE e.source = m.source AND e.source_match_id = m.source_match_id
                            LIMIT 1
                        ) AS has_events
                 FROM {$match_table} m
                 {$where}
                 ORDER BY m.match_date DESC, m.id DESC
                 LIMIT 30"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $matches = $wpdb->get_results(
            $wpdb->prepare( $sql, ...$args ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            ARRAY_A
        );

        if ( empty( $matches ) ) {
            $suffix = '' !== $scope['before_date']
                ? ' before the active fixture. Import an earlier competition/season or reduce the requested sample.'
                : '. Import its competition/season first.';
            throw new RuntimeException( 'No match catalogue is available for this team' . $suffix );
        }

        $ready    = 0;
        $imported = array();
        $existing = array();
        $failed   = array();
        $importer = new TactiTwin_StatsBomb_Importer();

        foreach ( $matches as $match ) {
            if ( $ready >= $target ) {
                break;
            }

            $match_id = (int) $match['source_match_id'];
            if ( ! empty( $match['has_events'] ) ) {
                ++$ready;
                $existing[] = $match_id;
                continue;
            }

            try {
                $result = $importer->import_match_events( $match_id );
                ++$ready;
                $imported[] = array(
                    'match_id' => $match_id,
                    'events'   => (int) ( $result['events'] ?? 0 ),
                );
            } catch ( Throwable $e ) {
                $failed[] = array(
                    'match_id' => $match_id,
                    'message'  => $e->getMessage(),
                );
            }
        }

        return array(
            'team_id'        => $team_id,
            'target_matches' => $target,
            'ready_matches'  => $ready,
            'existing'       => $existing,
            'imported'       => $imported,
            'failed'         => $failed,
            'coverage'       => $ready >= $target ? 'target-met' : 'partial',
            'temporal_scope' => $scope,
        );
    }

    public function prepare_matchup( int $team_id, int $opponent_id, int $target = 5, array $scope = array() ): array {
        if ( $team_id === $opponent_id ) {
            throw new InvalidArgumentException( 'Team and opponent must be different.' );
        }

        if ( empty( $scope ) ) {
            $scope = $this->scope_for_matchup( $team_id, $opponent_id );
        } else {
            $scope = $this->normalize_scope( $scope );
        }

        return array(
            'team'     => $this->ensure_team_sample( $team_id, $target, $scope ),
            'opponent' => $this->ensure_team_sample( $opponent_id, $target, $scope ),
            'temporal_scope' => $scope,
        );
    }

    public function scope_for_matchup( int $team_id, int $opponent_id ): array {
        if ( $team_id === $opponent_id ) {
            throw new InvalidArgumentException( 'Team and opponent must be different.' );
        }

        return $this->normalize_scope(
            $this->active_fixture_scope_for_matchup( $team_id, $opponent_id )
        );
    }

    private function active_fixture_scope_for_matchup( int $team_id, int $opponent_id ): array {
        if ( ! class_exists( 'TactiTwin_Planning_Context' ) ) {
            return array();
        }

        $context = TactiTwin_Planning_Context::current();
        if ( empty( $context ) ) {
            return array();
        }

        $context_team = trim( (string) ( $context['team_source_id'] ?? '' ) );
        $context_opponent = trim( (string) ( $context['opponent_source_id'] ?? '' ) );
        if ( (string) $team_id !== $context_team || (string) $opponent_id !== $context_opponent ) {
            return array();
        }

        $match = is_array( $context['match'] ?? null ) ? $context['match'] : array();
        $fixture = is_array( $context['fixture'] ?? null ) ? $context['fixture'] : array();

        return array(
            'before_date' => trim( (string) ( $match['match_date'] ?? $fixture['match_date'] ?? '' ) ),
            'exclude_match_id' => trim( (string) ( $fixture['source_match_id'] ?? $match['source_match_id'] ?? '' ) ),
            'fixture_key' => trim( (string) ( $context['fixture_key'] ?? $fixture['fixture_key'] ?? '' ) ),
            'scope_source' => 'active-planning-context',
        );
    }

    private function normalize_scope( array $scope ): array {
        $before_date = trim( (string) ( $scope['before_date'] ?? '' ) );
        if ( '' !== $before_date ) {
            if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $before_date ) ) {
                throw new InvalidArgumentException( 'Pre-match sample cutoff date must use YYYY-MM-DD.' );
            }
            [ $year, $month, $day ] = array_map( 'intval', explode( '-', $before_date ) );
            if ( ! checkdate( $month, $day, $year ) ) {
                throw new InvalidArgumentException( 'Pre-match sample cutoff date is not a valid calendar date.' );
            }
        }

        $exclude_match_id = preg_replace(
            '/[^a-zA-Z0-9_.:\-]/',
            '',
            trim( (string) ( $scope['exclude_match_id'] ?? '' ) )
        ) ?: '';

        return array(
            'before_date' => $before_date,
            'exclude_match_id' => $exclude_match_id,
            'fixture_key' => trim( (string) ( $scope['fixture_key'] ?? '' ) ),
            'scope_source' => trim( (string) ( $scope['scope_source'] ?? ( '' !== $before_date || '' !== $exclude_match_id ? 'explicit' : 'unbounded' ) ) ),
            'target_fixture_excluded' => '' !== $exclude_match_id,
            'future_matches_excluded' => '' !== $before_date,
        );
    }
}
