<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Post_Match_Review_Signoff_Service {
    public function current_review( string $session_uuid ): array {
        return ( new TactiTwin_Post_Match_Review_Service() )->review( $session_uuid );
    }

    public function binding_for_session( string $session_uuid ): array {
        $live = new TactiTwin_Live_Session_Service();
        $session = $live->session( $session_uuid );
        if ( empty( $session ) ) {
            throw new RuntimeException( 'Live Session not found.' );
        }
        if ( 'closed' !== (string) ( $session['status'] ?? '' ) ) {
            throw new RuntimeException( 'Post-match review sign-off requires a closed Live Session.' );
        }

        $staff_uuid = self::normalize_uuid( (string) ( $session['staff_brief_snapshot_uuid'] ?? '' ) );
        if ( '' === $staff_uuid ) {
            throw new RuntimeException( 'Post-match review sign-off requires a Live Session created from an approved Staff Brief.' );
        }
        $staff = ( new TactiTwin_Staff_Brief_Snapshot_Service() )->snapshot( $staff_uuid );
        if ( empty( $staff ) || ! TactiTwin_Staff_Brief_Snapshot::verify( $staff ) ) {
            throw new RuntimeException( 'The source Staff Brief snapshot is missing or failed integrity verification.' );
        }

        $fixture_key = strtolower( trim( (string) ( $staff['fixture_key'] ?? '' ) ) );
        if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $fixture_key ) ) {
            throw new RuntimeException( 'The source Staff Brief is not bound to an exact fixture identity.' );
        }
        $fixture = is_array( $staff['fixture'] ?? null ) ? $staff['fixture'] : array();
        $fixture_match_id = trim( (string) ( $fixture['source_match_id'] ?? '' ) );
        $session_match_id = trim( (string) ( $session['source_match_id'] ?? '' ) );
        if ( '' === $fixture_match_id || '' === $session_match_id || $fixture_match_id !== $session_match_id ) {
            throw new RuntimeException( 'The closed Live Session is not linked to the same provider match as its approved Staff Brief fixture.' );
        }

        return array(
            'fixture_key' => $fixture_key,
            'session_uuid' => (string) $session['session_uuid'],
            'staff_brief_snapshot_uuid' => $staff_uuid,
            'source_match_id' => $session_match_id,
            'team_source_id' => (string) ( $session['team_source_id'] ?? '' ),
            'opponent_source_id' => (string) ( $session['opponent_source_id'] ?? '' ),
            'session' => $session,
            'staff_brief_snapshot' => $staff,
        );
    }

    public function sign_off(
        string $session_uuid,
        int $signed_by,
        string $expected_review_sha256,
        string $signoff_note = ''
    ): array {
        global $wpdb;

        $binding = $this->binding_for_session( $session_uuid );
        $review = $this->current_review( (string) $binding['session_uuid'] );
        $current_review_sha = TactiTwin_Post_Match_Review_Snapshot::review_sha256( $review );
        $expected_review_sha256 = strtolower( trim( $expected_review_sha256 ) );
        if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $expected_review_sha256 ) || ! hash_equals( $current_review_sha, $expected_review_sha256 ) ) {
            throw new RuntimeException( 'The post-match review changed after it was displayed. Review the current evidence before signing off.' );
        }

        $previous = $this->latest_for_session( (string) $binding['session_uuid'] );
        $revision_number = empty( $previous ) ? 1 : (int) ( $previous['revision_number'] ?? 0 ) + 1;
        $parent = empty( $previous ) ? array() : array(
            'review_uuid' => (string) $previous['review_uuid'],
            'content_sha256' => (string) $previous['content_sha256'],
        );
        $review_uuid = self::new_uuid();
        $signed_at = current_time( 'mysql', true );
        $snapshot = TactiTwin_Post_Match_Review_Snapshot::freeze(
            $review,
            $binding,
            $review_uuid,
            $revision_number,
            $signed_by,
            $signed_at,
            sanitize_textarea_field( $signoff_note ),
            $parent
        );

        $table = TactiTwin_DB::table( 'post_match_review_snapshots' );
        $inserted = $wpdb->insert(
            $table,
            array(
                'review_uuid' => (string) $snapshot['review_uuid'],
                'fixture_key' => (string) $snapshot['fixture_key'],
                'session_uuid' => (string) $snapshot['session_uuid'],
                'staff_brief_snapshot_uuid' => (string) $snapshot['staff_brief_snapshot_uuid'],
                'source_match_id' => (string) $snapshot['source_match_id'],
                'revision_number' => (int) $snapshot['revision_number'],
                'parent_review_uuid' => (string) $snapshot['parent_review_uuid'],
                'parent_content_sha256' => (string) $snapshot['parent_content_sha256'],
                'review_sha256' => (string) $snapshot['review_sha256'],
                'content_sha256' => (string) $snapshot['content_sha256'],
                'signed_by' => (int) $snapshot['signed_by'],
                'signed_at' => $signed_at,
                'snapshot_json' => wp_json_encode( $snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            ),
            array( '%s','%s','%s','%s','%s','%d','%s','%s','%s','%s','%d','%s','%s' )
        );
        if ( false === $inserted ) {
            throw new RuntimeException( 'Could not persist the signed-off post-match review.' );
        }

        return $this->snapshot( $review_uuid );
    }

    public function snapshot( string $review_uuid ): array {
        global $wpdb;
        $review_uuid = self::normalize_uuid( $review_uuid );
        if ( '' === $review_uuid ) {
            return array();
        }
        $table = TactiTwin_DB::table( 'post_match_review_snapshots' );
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, review_uuid, fixture_key, session_uuid, staff_brief_snapshot_uuid, source_match_id, revision_number, parent_review_uuid, parent_content_sha256, review_sha256, content_sha256, signed_by, signed_at, snapshot_json
                 FROM {$table} WHERE review_uuid = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $review_uuid
            ),
            ARRAY_A
        ) ?: array();
        if ( empty( $row ) ) {
            return array();
        }
        $snapshot = json_decode( (string) $row['snapshot_json'], true );
        if ( ! is_array( $snapshot ) || ! TactiTwin_Post_Match_Review_Snapshot::verify( $snapshot ) ) {
            throw new RuntimeException( 'Stored post-match review snapshot failed integrity verification.' );
        }
        foreach ( array( 'review_uuid','fixture_key','session_uuid','staff_brief_snapshot_uuid','source_match_id','parent_review_uuid','parent_content_sha256','review_sha256','content_sha256' ) as $field ) {
            if ( (string) ( $row[ $field ] ?? '' ) !== (string) ( $snapshot[ $field ] ?? '' ) ) {
                throw new RuntimeException( 'Stored post-match review index does not match the immutable snapshot payload.' );
            }
        }
        if ( (int) $row['revision_number'] !== (int) ( $snapshot['revision_number'] ?? 0 ) ) {
            throw new RuntimeException( 'Stored post-match review revision does not match the immutable snapshot payload.' );
        }
        if ( (int) $row['signed_by'] !== (int) ( $snapshot['signed_by'] ?? 0 ) || (string) $row['signed_at'] !== (string) ( $snapshot['signed_at'] ?? '' ) ) {
            throw new RuntimeException( 'Stored post-match review sign-off metadata does not match the immutable snapshot payload.' );
        }
        $snapshot['storage'] = array(
            'id' => (int) $row['id'],
            'integrity_valid' => true,
            'lineage_valid' => $this->verify_parent_link( $snapshot ),
        );
        return $snapshot;
    }

    public function latest_for_session( string $session_uuid ): array {
        global $wpdb;
        $session_uuid = self::normalize_uuid( $session_uuid );
        if ( '' === $session_uuid ) {
            return array();
        }
        $table = TactiTwin_DB::table( 'post_match_review_snapshots' );
        $uuid = (string) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT review_uuid FROM {$table} WHERE session_uuid = %s ORDER BY revision_number DESC, id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $session_uuid
            )
        );
        return '' === $uuid ? array() : $this->snapshot( $uuid );
    }

    public function latest_for_fixture( string $fixture_key ): array {
        global $wpdb;
        $fixture_key = strtolower( trim( $fixture_key ) );
        if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $fixture_key ) ) {
            return array();
        }
        $table = TactiTwin_DB::table( 'post_match_review_snapshots' );
        $uuid = (string) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT review_uuid FROM {$table} WHERE fixture_key = %s ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $fixture_key
            )
        );
        return '' === $uuid ? array() : $this->snapshot( $uuid );
    }

    public function revisions_for_session( string $session_uuid ): array {
        global $wpdb;
        $session_uuid = self::normalize_uuid( $session_uuid );
        if ( '' === $session_uuid ) {
            return array();
        }
        $table = TactiTwin_DB::table( 'post_match_review_snapshots' );
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT review_uuid, fixture_key, session_uuid, revision_number, parent_review_uuid, review_sha256, content_sha256, signed_by, signed_at
                 FROM {$table} WHERE session_uuid = %s ORDER BY revision_number DESC, id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $session_uuid
            ),
            ARRAY_A
        ) ?: array();
    }

    private function verify_parent_link( array $snapshot ): bool {
        $revision = (int) ( $snapshot['revision_number'] ?? 0 );
        if ( 1 === $revision ) {
            return '' === (string) ( $snapshot['parent_review_uuid'] ?? '' ) && '' === (string) ( $snapshot['parent_content_sha256'] ?? '' );
        }
        if ( $revision <= 1 ) {
            return false;
        }
        $parent = $this->snapshot_without_lineage( (string) ( $snapshot['parent_review_uuid'] ?? '' ) );
        if ( empty( $parent ) ) {
            return false;
        }
        return (string) ( $parent['session_uuid'] ?? '' ) === (string) ( $snapshot['session_uuid'] ?? '' )
            && (string) ( $parent['fixture_key'] ?? '' ) === (string) ( $snapshot['fixture_key'] ?? '' )
            && (int) ( $parent['revision_number'] ?? 0 ) + 1 === $revision
            && hash_equals( (string) ( $parent['content_sha256'] ?? '' ), (string) ( $snapshot['parent_content_sha256'] ?? '' ) );
    }

    private function snapshot_without_lineage( string $review_uuid ): array {
        global $wpdb;
        $review_uuid = self::normalize_uuid( $review_uuid );
        if ( '' === $review_uuid ) {
            return array();
        }
        $table = TactiTwin_DB::table( 'post_match_review_snapshots' );
        $json = (string) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT snapshot_json FROM {$table} WHERE review_uuid = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $review_uuid
            )
        );
        $snapshot = json_decode( $json, true );
        return is_array( $snapshot ) && TactiTwin_Post_Match_Review_Snapshot::verify( $snapshot ) ? $snapshot : array();
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
