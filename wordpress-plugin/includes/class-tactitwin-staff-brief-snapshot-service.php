<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Staff_Brief_Snapshot_Service {
    public function approval_review( array $brief, string $language = 'en', string $approval_note = '' ): array {
        $team_id = (string) ( $brief['team']['source_id'] ?? '' );
        $opponent_id = (string) ( $brief['opponent']['source_id'] ?? '' );
        if ( '' === $team_id || '' === $opponent_id ) {
            throw new InvalidArgumentException( 'Staff Brief approval requires a valid team/opponent matchup.' );
        }

        $previous = $this->latest_for_matchup( $team_id, $opponent_id );
        $revision_number = $this->count_for_matchup( $team_id, $opponent_id ) + 1;

        return TactiTwin_Staff_Brief_Revision_Chain::build(
            $brief,
            $previous,
            $revision_number,
            $language,
            $approval_note
        );
    }

    public function approve( array $brief, int $approved_by, int $sample_size = 5, array $review_context = array() ): array {
        global $wpdb;
        $uuid = self::new_uuid();
        $approved_at = current_time( 'mysql', true );
        $language = TactiTwin_I18n::normalize_language( (string) ( $brief['language'] ?? 'en' ) );
        $approval_note = trim( (string) ( $review_context['approval_note'] ?? '' ) );
        $revision = $this->approval_review( $brief, $language, $approval_note );

        if ( ! empty( $review_context['enforce_review_guard'] ) ) {
            $expected_parent = self::normalize_uuid( (string) ( $review_context['expected_parent_snapshot_uuid'] ?? '' ) );
            $actual_parent = self::normalize_uuid( (string) ( $revision['parent_snapshot_uuid'] ?? '' ) );
            if ( $expected_parent !== $actual_parent ) {
                throw new RuntimeException( 'The previously reviewed Staff Brief version changed before approval. Review the latest diff and approve again.' );
            }

            $expected_diff = strtolower( trim( (string) ( $review_context['expected_diff_sha256'] ?? '' ) ) );
            $actual_diff = strtolower( trim( (string) ( $revision['reviewed_diff_sha256'] ?? '' ) ) );
            if ( $expected_diff !== $actual_diff ) {
                throw new RuntimeException( 'The Staff Brief diff changed before approval. Review the latest changes and approve again.' );
            }

            if ( ! empty( $revision['requires_change_acknowledgement'] ) && empty( $review_context['acknowledged_changes'] ) ) {
                throw new RuntimeException( 'Acknowledge the displayed Staff Brief changes before approving this revision.' );
            }
        }

        $snapshot = TactiTwin_Staff_Brief_Snapshot::freeze( $brief, $uuid, $approved_by, $approved_at, $sample_size, $revision );
        $table = TactiTwin_DB::table( 'staff_brief_snapshots' );
        $ok = $wpdb->insert(
            $table,
            array(
                'snapshot_uuid' => $uuid,
                'team_source_id' => (string) ( $snapshot['team']['source_id'] ?? '' ),
                'opponent_source_id' => (string) ( $snapshot['opponent']['source_id'] ?? '' ),
                'language' => (string) $snapshot['language'],
                'sample_size' => (int) $snapshot['sample_size'],
                'content_sha256' => (string) $snapshot['content_sha256'],
                'approved_by' => (int) $snapshot['approved_by'],
                'approved_at' => $approved_at,
                'snapshot_json' => wp_json_encode( $snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            ),
            array( '%s','%s','%s','%s','%d','%s','%d','%s','%s' )
        );
        if ( false === $ok ) {
            throw new RuntimeException( 'Could not persist the approved Staff Brief snapshot.' );
        }
        return $this->snapshot( $uuid );
    }

    public function snapshot( string $snapshot_uuid ): array {
        global $wpdb;
        $snapshot_uuid = self::normalize_uuid( $snapshot_uuid );
        if ( '' === $snapshot_uuid ) {
            return array();
        }
        $table = TactiTwin_DB::table( 'staff_brief_snapshots' );
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, snapshot_uuid, team_source_id, opponent_source_id, language, sample_size, content_sha256, approved_by, approved_at, snapshot_json FROM {$table} WHERE snapshot_uuid = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $snapshot_uuid
            ),
            ARRAY_A
        ) ?: array();
        if ( empty( $row ) ) {
            return array();
        }
        $snapshot = json_decode( (string) $row['snapshot_json'], true );
        if ( ! is_array( $snapshot ) ) {
            throw new RuntimeException( 'Stored Staff Brief snapshot JSON is invalid.' );
        }
        $snapshot['storage'] = array(
            'id' => (int) $row['id'],
            'integrity_valid' => TactiTwin_Staff_Brief_Snapshot::verify( $snapshot ),
            'revision_chain_present' => ! empty( $snapshot['revision'] ),
        );
        return $snapshot;
    }

    public function recent( int $limit = 10 ): array {
        global $wpdb;
        $limit = max( 1, min( 50, $limit ) );
        $table = TactiTwin_DB::table( 'staff_brief_snapshots' );
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, snapshot_uuid, team_source_id, opponent_source_id, language, sample_size, content_sha256, approved_by, approved_at FROM {$table} ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $limit
            ),
            ARRAY_A
        ) ?: array();
    }

    public function latest_for_matchup( string $team_id, string $opponent_id, string $exclude_snapshot_uuid = '' ): array {
        global $wpdb;
        $team_id = trim( $team_id );
        $opponent_id = trim( $opponent_id );
        $exclude_snapshot_uuid = self::normalize_uuid( $exclude_snapshot_uuid );
        if ( '' === $team_id || '' === $opponent_id ) {
            return array();
        }

        $table = TactiTwin_DB::table( 'staff_brief_snapshots' );
        if ( '' !== $exclude_snapshot_uuid ) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT snapshot_uuid FROM {$table} WHERE team_source_id = %s AND opponent_source_id = %s AND snapshot_uuid <> %s ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $team_id,
                    $opponent_id,
                    $exclude_snapshot_uuid
                ),
                ARRAY_A
            ) ?: array();
        } else {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT snapshot_uuid FROM {$table} WHERE team_source_id = %s AND opponent_source_id = %s ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $team_id,
                    $opponent_id
                ),
                ARRAY_A
            ) ?: array();
        }
        return empty( $row['snapshot_uuid'] ) ? array() : $this->snapshot( (string) $row['snapshot_uuid'] );
    }

    public function count_for_matchup( string $team_id, string $opponent_id ): int {
        global $wpdb;
        $team_id = trim( $team_id );
        $opponent_id = trim( $opponent_id );
        if ( '' === $team_id || '' === $opponent_id ) {
            return 0;
        }
        $table = TactiTwin_DB::table( 'staff_brief_snapshots' );
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE team_source_id = %s AND opponent_source_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $team_id,
                $opponent_id
            )
        );
    }

    public function handoff( string $snapshot_uuid, int $created_by, string $source_match_id = '', string $title = '' ): array {
        $snapshot = $this->snapshot( $snapshot_uuid );
        if ( empty( $snapshot ) ) {
            throw new RuntimeException( 'Approved Staff Brief snapshot not found.' );
        }
        if ( ! TactiTwin_Staff_Brief_Snapshot::verify( $snapshot ) ) {
            throw new RuntimeException( 'Approved Staff Brief snapshot failed integrity verification.' );
        }

        $live = new TactiTwin_Live_Session_Service();
        $existing = $live->session_for_staff_brief( (string) $snapshot['snapshot_uuid'] );
        if ( ! empty( $existing ) ) {
            return array(
                'reused' => true,
                'staff_brief_snapshot' => $snapshot,
                'session' => $existing,
                'timeline' => $live->timeline( (string) $existing['session_uuid'] ),
            );
        }

        $team_id = (int) ( $snapshot['team']['source_id'] ?? 0 );
        $opponent_id = (int) ( $snapshot['opponent']['source_id'] ?? 0 );
        $playbook = TactiTwin_Staff_Brief_Snapshot::playbook( $snapshot );
        if ( '' === trim( $title ) ) {
            $title = trim( (string) ( $snapshot['team']['name'] ?? '' ) . ' vs ' . (string) ( $snapshot['opponent']['name'] ?? '' ) );
        }

        $session = $live->ensure_session(
            null,
            $team_id,
            $opponent_id,
            (string) $snapshot['language'],
            $created_by,
            $source_match_id,
            $title,
            (string) $snapshot['snapshot_uuid']
        );
        $persisted = $live->append_snapshot( (string) $session['session_uuid'], $playbook, $created_by );

        return array(
            'reused' => false,
            'staff_brief_snapshot' => $snapshot,
            'session' => $persisted['session'],
            'snapshot' => $persisted['snapshot'],
            'timeline' => $persisted['timeline'],
        );
    }

    private static function normalize_uuid( string $value ): string {
        return preg_replace( '/[^a-zA-Z0-9\-]/', '', trim( $value ) ) ?: '';
    }

    private static function new_uuid(): string {
        if ( function_exists( 'wp_generate_uuid4' ) ) {
            return wp_generate_uuid4();
        }
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int( 0, 0xffff ), random_int( 0, 0xffff ), random_int( 0, 0xffff ),
            random_int( 0, 0x0fff ) | 0x4000, random_int( 0, 0x3fff ) | 0x8000,
            random_int( 0, 0xffff ), random_int( 0, 0xffff ), random_int( 0, 0xffff )
        );
    }
}
