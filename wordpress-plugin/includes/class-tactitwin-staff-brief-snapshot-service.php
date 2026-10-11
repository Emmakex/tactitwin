<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Staff_Brief_Snapshot_Service {
    public function approve( array $brief, int $approved_by, int $sample_size = 5 ): array {
        global $wpdb;
        $uuid = self::new_uuid();
        $approved_at = current_time( 'mysql', true );
        $snapshot = TactiTwin_Staff_Brief_Snapshot::freeze( $brief, $uuid, $approved_by, $approved_at, $sample_size );
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
