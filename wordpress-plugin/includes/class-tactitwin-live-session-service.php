<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Live_Session_Service {
    public function ensure_session(
        ?string $session_uuid,
        int $team_id,
        int $opponent_id,
        string $language,
        int $created_by,
        string $source_match_id = '',
        string $title = ''
    ): array {
        global $wpdb;

        if ( $team_id <= 0 || $opponent_id <= 0 || $team_id === $opponent_id ) {
            throw new InvalidArgumentException( 'A live session requires two different positive team identifiers.' );
        }

        $language = TactiTwin_I18n::normalize_language( $language );
        $table = TactiTwin_DB::table( 'live_sessions' );
        $session_uuid = self::normalize_uuid( $session_uuid );

        if ( '' !== $session_uuid ) {
            $existing = $this->session( $session_uuid );
            if ( ! empty( $existing ) ) {
                if ( (string) $existing['team_source_id'] !== (string) $team_id || (string) $existing['opponent_source_id'] !== (string) $opponent_id ) {
                    throw new InvalidArgumentException( 'The live session belongs to a different team/opponent pairing.' );
                }
                if ( 'closed' === (string) $existing['status'] ) {
                    throw new RuntimeException( 'The live session is already closed.' );
                }
                return $existing;
            }
        }

        $session_uuid = self::new_uuid();
        $now = current_time( 'mysql', true );
        $inserted = $wpdb->insert(
            $table,
            array(
                'session_uuid' => $session_uuid,
                'source_match_id' => sanitize_text_field( $source_match_id ),
                'team_source_id' => (string) $team_id,
                'opponent_source_id' => (string) $opponent_id,
                'language' => $language,
                'status' => 'active',
                'title' => sanitize_text_field( $title ),
                'created_by' => max( 0, $created_by ),
                'started_at' => $now,
                'updated_at' => $now,
                'closed_at' => null,
            ),
            array( '%s','%s','%s','%s','%s','%s','%s','%d','%s','%s','%s' )
        );

        if ( false === $inserted ) {
            throw new RuntimeException( 'Could not create the live session.' );
        }

        return $this->session( $session_uuid );
    }

    public function append_snapshot( string $session_uuid, array $playbook, int $created_by ): array {
        global $wpdb;

        $session = $this->session_or_fail( $session_uuid );
        if ( 'active' !== (string) $session['status'] ) {
            throw new RuntimeException( 'Snapshots can only be added to an active live session.' );
        }

        $context = $playbook['current_context'] ?? array();
        $active  = $playbook['active_plan'] ?? array();
        $live    = $playbook['live_observation'] ?? array();
        $selected = $live['selected_scenario'] ?? null;
        if ( ! is_array( $selected ) ) {
            $selected = array();
        }
        $observations = $live['observations'] ?? array();
        if ( ! is_array( $observations ) ) {
            $observations = array();
        }

        $minute = max( 1, min( 120, (int) ( $context['minute'] ?? 1 ) ) );
        $our_score = max( 0, min( 20, (int) ( $context['our_score'] ?? 0 ) ) );
        $opponent_score = max( 0, min( 20, (int) ( $context['opponent_score'] ?? 0 ) ) );
        $our_players = max( 7, min( 11, (int) ( $context['our_players'] ?? 11 ) ) );
        $opponent_players = max( 7, min( 11, (int) ( $context['opponent_players'] ?? 11 ) ) );
        $active_plan = strtoupper( substr( (string) ( $active['id'] ?? 'A' ), 0, 1 ) );
        if ( ! in_array( $active_plan, array( 'A','B','C' ), true ) ) {
            $active_plan = 'A';
        }
        $active_mode = sanitize_key( (string) ( $active['mode'] ?? 'baseline' ) );
        $scenario_id = sanitize_key( (string) ( $active['scenario_id'] ?? ( $selected['scenario_id'] ?? '' ) ) );
        $activation_status = sanitize_key( (string) ( $live['activation']['status'] ?? '' ) );
        $note = sanitize_text_field( (string) ( $observations['note'] ?? '' ) );

        $snapshot_table = TactiTwin_DB::table( 'live_snapshots' );
        $now = current_time( 'mysql', true );
        $inserted = $wpdb->insert(
            $snapshot_table,
            array(
                'session_uuid' => (string) $session['session_uuid'],
                'minute' => $minute,
                'our_score' => $our_score,
                'opponent_score' => $opponent_score,
                'our_players' => $our_players,
                'opponent_players' => $opponent_players,
                'active_plan' => $active_plan,
                'active_mode' => $active_mode,
                'selected_scenario' => $scenario_id,
                'activation_status' => $activation_status,
                'observations_json' => wp_json_encode( $observations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
                'note' => $note,
                'playbook_json' => wp_json_encode( $playbook, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
                'created_by' => max( 0, $created_by ),
                'created_at' => $now,
            ),
            array( '%s','%d','%d','%d','%d','%d','%s','%s','%s','%s','%s','%s','%s','%d','%s' )
        );

        if ( false === $inserted ) {
            throw new RuntimeException( 'Could not persist the live playbook snapshot.' );
        }

        $wpdb->update(
            TactiTwin_DB::table( 'live_sessions' ),
            array( 'updated_at'=>$now, 'language'=>(string) ( $playbook['language'] ?? $session['language'] ) ),
            array( 'session_uuid'=>(string) $session['session_uuid'] ),
            array( '%s','%s' ),
            array( '%s' )
        );

        $snapshot_id = (int) $wpdb->insert_id;
        return array(
            'session' => $this->session( (string) $session['session_uuid'] ),
            'snapshot' => $this->snapshot( $snapshot_id ),
            'timeline' => $this->timeline( (string) $session['session_uuid'] ),
        );
    }

    public function close_session( string $session_uuid ): array {
        global $wpdb;

        $session = $this->session_or_fail( $session_uuid );
        if ( 'closed' !== (string) $session['status'] ) {
            $now = current_time( 'mysql', true );
            $wpdb->update(
                TactiTwin_DB::table( 'live_sessions' ),
                array( 'status'=>'closed', 'updated_at'=>$now, 'closed_at'=>$now ),
                array( 'session_uuid'=>(string) $session['session_uuid'] ),
                array( '%s','%s','%s' ),
                array( '%s' )
            );
        }

        return array(
            'session' => $this->session( (string) $session['session_uuid'] ),
            'timeline' => $this->timeline( (string) $session['session_uuid'] ),
        );
    }

    public function session( string $session_uuid ): array {
        global $wpdb;

        $session_uuid = self::normalize_uuid( $session_uuid );
        if ( '' === $session_uuid ) {
            return array();
        }
        $table = TactiTwin_DB::table( 'live_sessions' );
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, session_uuid, source_match_id, team_source_id, opponent_source_id, language, status, title, created_by, started_at, updated_at, closed_at
                 FROM {$table}
                 WHERE session_uuid = %s
                 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $session_uuid
            ),
            ARRAY_A
        ) ?: array();
    }

    public function timeline( string $session_uuid ): array {
        $session = $this->session_or_fail( $session_uuid );
        $snapshots = $this->snapshots( (string) $session['session_uuid'] );
        return array(
            'session' => $session,
            'summary' => TactiTwin_Live_Timeline::summarize( $snapshots ),
            'snapshots' => $snapshots,
        );
    }

    public function snapshots( string $session_uuid ): array {
        global $wpdb;

        $session_uuid = self::normalize_uuid( $session_uuid );
        if ( '' === $session_uuid ) {
            return array();
        }
        $table = TactiTwin_DB::table( 'live_snapshots' );
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, session_uuid, minute, our_score, opponent_score, our_players, opponent_players,
                        active_plan, active_mode, selected_scenario, activation_status,
                        observations_json, note, playbook_json, created_by, created_at
                 FROM {$table}
                 WHERE session_uuid = %s
                 ORDER BY minute ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $session_uuid
            ),
            ARRAY_A
        ) ?: array();

        foreach ( $rows as &$row ) {
            $row['id'] = (int) $row['id'];
            $row['minute'] = (int) $row['minute'];
            $row['our_score'] = (int) $row['our_score'];
            $row['opponent_score'] = (int) $row['opponent_score'];
            $row['our_players'] = (int) $row['our_players'];
            $row['opponent_players'] = (int) $row['opponent_players'];
            $row['created_by'] = (int) $row['created_by'];
            $row['observations'] = self::decode_json( $row['observations_json'] ?? '' );
            $row['playbook'] = self::decode_json( $row['playbook_json'] ?? '' );
            unset( $row['observations_json'], $row['playbook_json'] );
        }
        unset( $row );

        return $rows;
    }

    public function recent_sessions( int $limit = 20, ?int $team_id = null ): array {
        global $wpdb;

        $limit = max( 1, min( 100, $limit ) );
        $table = TactiTwin_DB::table( 'live_sessions' );
        if ( null !== $team_id && $team_id > 0 ) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, session_uuid, source_match_id, team_source_id, opponent_source_id, language, status, title, created_by, started_at, updated_at, closed_at
                     FROM {$table}
                     WHERE team_source_id = %s
                     ORDER BY updated_at DESC, id DESC
                     LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    (string) $team_id,
                    $limit
                ),
                ARRAY_A
            ) ?: array();
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, session_uuid, source_match_id, team_source_id, opponent_source_id, language, status, title, created_by, started_at, updated_at, closed_at
                 FROM {$table}
                 ORDER BY updated_at DESC, id DESC
                 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $limit
            ),
            ARRAY_A
        ) ?: array();
    }

    private function snapshot( int $snapshot_id ): array {
        global $wpdb;

        $table = TactiTwin_DB::table( 'live_snapshots' );
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, session_uuid, minute, our_score, opponent_score, our_players, opponent_players,
                        active_plan, active_mode, selected_scenario, activation_status,
                        observations_json, note, playbook_json, created_by, created_at
                 FROM {$table}
                 WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $snapshot_id
            ),
            ARRAY_A
        ) ?: array();
        if ( empty( $row ) ) {
            return array();
        }
        $row['id'] = (int) $row['id'];
        $row['minute'] = (int) $row['minute'];
        $row['our_score'] = (int) $row['our_score'];
        $row['opponent_score'] = (int) $row['opponent_score'];
        $row['our_players'] = (int) $row['our_players'];
        $row['opponent_players'] = (int) $row['opponent_players'];
        $row['created_by'] = (int) $row['created_by'];
        $row['observations'] = self::decode_json( $row['observations_json'] ?? '' );
        $row['playbook'] = self::decode_json( $row['playbook_json'] ?? '' );
        unset( $row['observations_json'], $row['playbook_json'] );
        return $row;
    }

    private function session_or_fail( string $session_uuid ): array {
        $session = $this->session( $session_uuid );
        if ( empty( $session ) ) {
            throw new RuntimeException( 'Live session not found.' );
        }
        return $session;
    }

    private static function normalize_uuid( ?string $session_uuid ): string {
        return preg_replace( '/[^a-zA-Z0-9\-]/', '', trim( (string) $session_uuid ) ) ?: '';
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

    private static function decode_json( $value ): array {
        if ( is_array( $value ) ) {
            return $value;
        }
        $decoded = json_decode( (string) $value, true );
        return is_array( $decoded ) ? $decoded : array();
    }
}
