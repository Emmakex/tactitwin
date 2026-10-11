<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Fixture_Lifecycle_Service {
    public function inspect( array $context, int $user_id ): array {
        if ( empty( $context ) ) {
            return self::derive( array( 'context_selected' => false ) );
        }

        $fixture_key = strtolower( trim( (string) ( $context['fixture_key'] ?? '' ) ) );
        $fixture = is_array( $context['fixture'] ?? null ) ? $context['fixture'] : array();
        $team_id = trim( (string) ( $context['team_source_id'] ?? '' ) );
        $opponent_id = trim( (string) ( $context['opponent_source_id'] ?? '' ) );
        if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $fixture_key ) || '' === $team_id || '' === $opponent_id ) {
            throw new InvalidArgumentException( 'Fixture lifecycle requires a complete canonical planning context.' );
        }

        $planning = $this->planning_data_counts( $fixture, $team_id, $opponent_id );
        $temporary_match_plan = $this->temporary_match_plan_for_fixture( $user_id, $fixture_key );
        $temporary_staff_brief = $this->temporary_staff_brief_for_fixture( $user_id, $fixture_key );

        $snapshot_service = new TactiTwin_Staff_Brief_Snapshot_Service();
        $approved_snapshot = $snapshot_service->latest_for_fixture( $fixture_key );
        $session = $this->latest_session_for_fixture( $fixture_key );

        $fixture_source_match_id = trim( (string) ( $fixture['source_match_id'] ?? '' ) );
        $session_source_match_id = trim( (string) ( $session['source_match_id'] ?? '' ) );
        $evidence_events = 0;
        if ( ! empty( $session ) && '' !== $session_source_match_id && '' !== $fixture_source_match_id && $session_source_match_id === $fixture_source_match_id ) {
            $evidence_events = $this->match_event_count(
                trim( (string) ( $fixture['source'] ?? '' ) ),
                $fixture_source_match_id
            );
        }

        return self::derive(
            array(
                'context_selected' => true,
                'fixture_key' => $fixture_key,
                'team_history_matches' => (int) $planning['team_history_matches'],
                'opponent_history_matches' => (int) $planning['opponent_history_matches'],
                'temporary_match_plan' => $temporary_match_plan,
                'temporary_staff_brief' => $temporary_staff_brief,
                'approved_snapshot' => $approved_snapshot,
                'session' => $session,
                'fixture_source_match_id' => $fixture_source_match_id,
                'session_source_match_id' => $session_source_match_id,
                'evidence_event_count' => $evidence_events,
            )
        );
    }

    public static function derive( array $signals ): array {
        $context_selected = ! empty( $signals['context_selected'] );
        $team_history = max( 0, (int) ( $signals['team_history_matches'] ?? 0 ) );
        $opponent_history = max( 0, (int) ( $signals['opponent_history_matches'] ?? 0 ) );
        $temporary_match_plan = is_array( $signals['temporary_match_plan'] ?? null ) ? $signals['temporary_match_plan'] : array();
        $temporary_staff_brief = is_array( $signals['temporary_staff_brief'] ?? null ) ? $signals['temporary_staff_brief'] : array();
        $approved_snapshot = is_array( $signals['approved_snapshot'] ?? null ) ? $signals['approved_snapshot'] : array();
        $session = is_array( $signals['session'] ?? null ) ? $signals['session'] : array();
        $fixture_source_match_id = trim( (string) ( $signals['fixture_source_match_id'] ?? '' ) );
        $session_source_match_id = trim( (string) ( $signals['session_source_match_id'] ?? '' ) );
        $evidence_event_count = max( 0, (int) ( $signals['evidence_event_count'] ?? 0 ) );

        $planning_state = 'not_available';
        if ( $context_selected ) {
            if ( $team_history > 0 && $opponent_history > 0 ) {
                $planning_state = 'ready';
            } elseif ( $team_history > 0 || $opponent_history > 0 ) {
                $planning_state = 'partial';
            } else {
                $planning_state = 'missing';
            }
        }

        $approved = ! empty( $approved_snapshot );
        $match_plan_state = $approved
            ? 'sealed_in_approval'
            : ( ! empty( $temporary_match_plan ) ? 'current_temporary' : 'not_current' );
        $staff_brief_state = $approved
            ? 'approved'
            : ( ! empty( $temporary_staff_brief ) ? 'draft_temporary' : 'not_generated' );

        $session_state = 'not_started';
        if ( ! empty( $session ) ) {
            $session_state = 'closed' === (string) ( $session['status'] ?? '' ) ? 'closed' : 'active';
        }

        $evidence_state = 'waiting_for_session';
        if ( ! empty( $session ) ) {
            if ( '' === $session_source_match_id ) {
                $evidence_state = 'unlinked';
            } elseif ( '' !== $fixture_source_match_id && $session_source_match_id !== $fixture_source_match_id ) {
                $evidence_state = 'fixture_mismatch';
            } elseif ( $evidence_event_count > 0 ) {
                $evidence_state = 'available';
            } else {
                $evidence_state = 'linked_pending_events';
            }
        }

        if ( 'closed' === $session_state && 'available' === $evidence_state ) {
            $review_state = 'available_on_demand';
        } elseif ( 'closed' !== $session_state ) {
            $review_state = 'waiting_for_closed_session';
        } else {
            $review_state = 'waiting_for_evidence';
        }

        $phase = 'pre_match';
        if ( 'active' === $session_state ) {
            $phase = 'match_day';
        } elseif ( 'closed' === $session_state ) {
            $phase = 'post_match';
        }

        $stages = array(
            'target' => array(
                'state' => $context_selected ? 'selected' : 'not_selected',
                'provenance' => $context_selected ? 'user_meta' : 'none',
                'persistent' => $context_selected,
            ),
            'planning_data' => array(
                'state' => $planning_state,
                'provenance' => 'database',
                'persistent' => true,
                'team_history_matches' => $team_history,
                'opponent_history_matches' => $opponent_history,
            ),
            'match_plan' => array(
                'state' => $match_plan_state,
                'provenance' => $approved ? 'approved_snapshot' : ( ! empty( $temporary_match_plan ) ? 'transient' : 'none' ),
                'persistent' => $approved,
            ),
            'staff_brief' => array(
                'state' => $staff_brief_state,
                'provenance' => $approved ? 'approved_snapshot' : ( ! empty( $temporary_staff_brief ) ? 'transient' : 'none' ),
                'persistent' => $approved,
                'snapshot_uuid' => (string) ( $approved_snapshot['snapshot_uuid'] ?? '' ),
                'approved_at' => (string) ( $approved_snapshot['approved_at'] ?? '' ),
            ),
            'live_session' => array(
                'state' => $session_state,
                'provenance' => empty( $session ) ? 'none' : 'database',
                'persistent' => ! empty( $session ),
                'session_uuid' => (string) ( $session['session_uuid'] ?? '' ),
                'started_at' => (string) ( $session['started_at'] ?? '' ),
                'closed_at' => (string) ( $session['closed_at'] ?? '' ),
            ),
            'evidence' => array(
                'state' => $evidence_state,
                'provenance' => empty( $session ) ? 'none' : 'database_derived',
                'persistent' => 'available' === $evidence_state,
                'event_count' => $evidence_event_count,
                'source_match_id' => $session_source_match_id,
            ),
            'post_match_review' => array(
                'state' => $review_state,
                'provenance' => 'computed_on_demand',
                'persistent' => false,
            ),
        );

        $next_action = self::next_action( $context_selected, $planning_state, $match_plan_state, $staff_brief_state, $session_state, $evidence_state );

        return array(
            'fixture_key' => (string) ( $signals['fixture_key'] ?? '' ),
            'phase' => $phase,
            'stages' => $stages,
            'next_action' => $next_action,
            'method' => array(
                'name' => 'fixture-lifecycle-v1.24',
                'no_progress_score' => true,
                'review_completion_not_persisted' => true,
                'temporary_outputs_explicit' => true,
            ),
        );
    }

    private static function next_action(
        bool $context_selected,
        string $planning_state,
        string $match_plan_state,
        string $staff_brief_state,
        string $session_state,
        string $evidence_state
    ): array {
        if ( ! $context_selected ) {
            return array( 'id' => 'select_match', 'page' => 'tactitwin' );
        }
        if ( in_array( $planning_state, array( 'missing', 'partial' ), true ) ) {
            return array( 'id' => 'prepare_data', 'page' => 'tactitwin-data' );
        }
        if ( 'not_current' === $match_plan_state && 'not_generated' === $staff_brief_state ) {
            return array( 'id' => 'generate_match_plan', 'page' => 'tactitwin-match-plan' );
        }
        if ( 'not_generated' === $staff_brief_state ) {
            return array( 'id' => 'generate_staff_brief', 'page' => 'tactitwin-staff-brief' );
        }
        if ( 'draft_temporary' === $staff_brief_state ) {
            return array( 'id' => 'approve_staff_brief', 'page' => 'tactitwin-staff-brief' );
        }
        if ( 'not_started' === $session_state ) {
            return array( 'id' => 'handoff_live_session', 'page' => 'tactitwin-staff-brief' );
        }
        if ( 'active' === $session_state ) {
            return array( 'id' => 'continue_live_session', 'page' => 'tactitwin-live-sessions' );
        }
        if ( 'available' !== $evidence_state ) {
            return array( 'id' => 'prepare_evidence', 'page' => 'tactitwin-evidence-review' );
        }
        return array( 'id' => 'review_match', 'page' => 'tactitwin-session-review' );
    }

    private function planning_data_counts( array $fixture, string $team_id, string $opponent_id ): array {
        global $wpdb;
        $source = trim( (string) ( $fixture['source'] ?? '' ) );
        if ( '' === $source ) {
            return array( 'team_history_matches' => 0, 'opponent_history_matches' => 0 );
        }
        $events = TactiTwin_DB::table( 'events' );
        $team_matches = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT source_match_id) FROM {$events} WHERE source = %s AND team_source_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $source,
                $team_id
            )
        );
        $opponent_matches = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT source_match_id) FROM {$events} WHERE source = %s AND team_source_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $source,
                $opponent_id
            )
        );
        return array(
            'team_history_matches' => $team_matches,
            'opponent_history_matches' => $opponent_matches,
        );
    }

    private function temporary_match_plan_for_fixture( int $user_id, string $fixture_key ): array {
        if ( $user_id <= 0 || ! function_exists( 'get_transient' ) ) {
            return array();
        }
        $stored = get_transient( 'tactitwin_match_plan_' . $user_id );
        if ( ! is_array( $stored ) ) {
            return array();
        }
        return strtolower( trim( (string) ( $stored['fixture_key'] ?? '' ) ) ) === $fixture_key ? $stored : array();
    }

    private function temporary_staff_brief_for_fixture( int $user_id, string $fixture_key ): array {
        if ( $user_id <= 0 || ! function_exists( 'get_transient' ) ) {
            return array();
        }
        $stored = get_transient( 'tactitwin_staff_brief_' . $user_id );
        $brief = is_array( $stored['brief'] ?? null ) ? $stored['brief'] : array();
        if ( empty( $brief ) ) {
            return array();
        }
        try {
            return TactiTwin_Fixture_Identity::key_from_brief( $brief ) === $fixture_key ? $stored : array();
        } catch ( Throwable $e ) {
            return array();
        }
    }

    private function latest_session_for_fixture( string $fixture_key ): array {
        global $wpdb;
        $sessions = TactiTwin_DB::table( 'live_sessions' );
        $snapshots = TactiTwin_DB::table( 'staff_brief_snapshots' );
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT ls.id, ls.session_uuid, ls.source_match_id, ls.staff_brief_snapshot_uuid, ls.team_source_id, ls.opponent_source_id, ls.language, ls.status, ls.title, ls.created_by, ls.started_at, ls.updated_at, ls.closed_at
                 FROM {$sessions} ls
                 INNER JOIN {$snapshots} sb ON sb.snapshot_uuid = ls.staff_brief_snapshot_uuid
                 WHERE sb.fixture_key = %s
                 ORDER BY ls.id DESC
                 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $fixture_key
            ),
            ARRAY_A
        ) ?: array();
    }

    private function match_event_count( string $source, string $source_match_id ): int {
        global $wpdb;
        if ( '' === $source || '' === $source_match_id ) {
            return 0;
        }
        $events = TactiTwin_DB::table( 'events' );
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$events} WHERE source = %s AND source_match_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $source,
                $source_match_id
            )
        );
    }
}
