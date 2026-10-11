<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_DB {
    public const SCHEMA_VERSION = '0.3.0';

    public static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        $competitions          = $wpdb->prefix . 'tactitwin_competitions';
        $teams                 = $wpdb->prefix . 'tactitwin_teams';
        $players               = $wpdb->prefix . 'tactitwin_players';
        $matches               = $wpdb->prefix . 'tactitwin_matches';
        $events                = $wpdb->prefix . 'tactitwin_events';
        $staff_brief_snapshots = $wpdb->prefix . 'tactitwin_staff_brief_snapshots';
        $live_sessions         = $wpdb->prefix . 'tactitwin_live_sessions';
        $live_snapshots        = $wpdb->prefix . 'tactitwin_live_snapshots';

        dbDelta(
            "CREATE TABLE {$competitions} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                source varchar(32) NOT NULL,
                source_competition_id varchar(64) NOT NULL,
                source_season_id varchar(64) NOT NULL,
                country_name varchar(191) NOT NULL DEFAULT '',
                competition_name varchar(191) NOT NULL DEFAULT '',
                competition_gender varchar(32) NOT NULL DEFAULT '',
                season_name varchar(191) NOT NULL DEFAULT '',
                source_updated_at varchar(64) NOT NULL DEFAULT '',
                raw_json longtext NOT NULL,
                imported_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY source_comp_season (source, source_competition_id, source_season_id)
            ) {$charset};"
        );

        dbDelta(
            "CREATE TABLE {$teams} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                source varchar(32) NOT NULL,
                source_team_id varchar(64) NOT NULL,
                team_name varchar(191) NOT NULL,
                country_name varchar(191) NOT NULL DEFAULT '',
                raw_json longtext NOT NULL,
                imported_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY source_team (source, source_team_id),
                KEY team_name (team_name)
            ) {$charset};"
        );

        dbDelta(
            "CREATE TABLE {$players} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                source varchar(32) NOT NULL,
                source_player_id varchar(64) NOT NULL,
                player_name varchar(191) NOT NULL,
                last_team_source_id varchar(64) NOT NULL DEFAULT '',
                last_position_name varchar(191) NOT NULL DEFAULT '',
                raw_json longtext NOT NULL,
                imported_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY source_player (source, source_player_id),
                KEY player_name (player_name)
            ) {$charset};"
        );

        dbDelta(
            "CREATE TABLE {$matches} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                source varchar(32) NOT NULL,
                source_match_id varchar(64) NOT NULL,
                source_competition_id varchar(64) NOT NULL,
                source_season_id varchar(64) NOT NULL,
                match_date date DEFAULT NULL,
                kick_off varchar(32) NOT NULL DEFAULT '',
                home_team_source_id varchar(64) NOT NULL,
                away_team_source_id varchar(64) NOT NULL,
                home_score smallint(6) DEFAULT NULL,
                away_score smallint(6) DEFAULT NULL,
                match_week smallint(6) DEFAULT NULL,
                match_status varchar(64) NOT NULL DEFAULT '',
                raw_json longtext NOT NULL,
                imported_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY source_match (source, source_match_id),
                KEY competition_season (source_competition_id, source_season_id),
                KEY match_date (match_date)
            ) {$charset};"
        );

        dbDelta(
            "CREATE TABLE {$events} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                source varchar(32) NOT NULL,
                source_event_id varchar(64) NOT NULL,
                source_match_id varchar(64) NOT NULL,
                event_index int(11) NOT NULL DEFAULT 0,
                period tinyint(4) NOT NULL DEFAULT 0,
                minute smallint(6) NOT NULL DEFAULT 0,
                second smallint(6) NOT NULL DEFAULT 0,
                event_type_id varchar(32) NOT NULL DEFAULT '',
                event_type_name varchar(96) NOT NULL DEFAULT '',
                possession int(11) DEFAULT NULL,
                possession_team_source_id varchar(64) NOT NULL DEFAULT '',
                team_source_id varchar(64) NOT NULL DEFAULT '',
                player_source_id varchar(64) NOT NULL DEFAULT '',
                position_name varchar(191) NOT NULL DEFAULT '',
                location_x decimal(8,3) DEFAULT NULL,
                location_y decimal(8,3) DEFAULT NULL,
                end_location_x decimal(8,3) DEFAULT NULL,
                end_location_y decimal(8,3) DEFAULT NULL,
                outcome_name varchar(191) NOT NULL DEFAULT '',
                xg decimal(10,8) DEFAULT NULL,
                raw_json longtext NOT NULL,
                imported_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY source_event (source, source_event_id),
                KEY source_match (source, source_match_id),
                KEY event_type (event_type_name),
                KEY team_match (team_source_id, source_match_id),
                KEY player_match (player_source_id, source_match_id)
            ) {$charset};"
        );

        dbDelta(
            "CREATE TABLE {$staff_brief_snapshots} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                snapshot_uuid varchar(64) NOT NULL,
                team_source_id varchar(64) NOT NULL,
                opponent_source_id varchar(64) NOT NULL,
                language varchar(8) NOT NULL DEFAULT 'en',
                sample_size tinyint(4) NOT NULL DEFAULT 5,
                content_sha256 char(64) NOT NULL,
                approved_by bigint(20) unsigned NOT NULL DEFAULT 0,
                approved_at datetime NOT NULL,
                snapshot_json longtext NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY snapshot_uuid (snapshot_uuid),
                KEY team_opponent (team_source_id, opponent_source_id),
                KEY approved_at (approved_at)
            ) {$charset};"
        );

        dbDelta(
            "CREATE TABLE {$live_sessions} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                session_uuid varchar(64) NOT NULL,
                source_match_id varchar(64) NOT NULL DEFAULT '',
                staff_brief_snapshot_uuid varchar(64) DEFAULT NULL,
                team_source_id varchar(64) NOT NULL,
                opponent_source_id varchar(64) NOT NULL,
                language varchar(8) NOT NULL DEFAULT 'en',
                status varchar(32) NOT NULL DEFAULT 'active',
                title varchar(191) NOT NULL DEFAULT '',
                created_by bigint(20) unsigned NOT NULL DEFAULT 0,
                started_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                closed_at datetime DEFAULT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY session_uuid (session_uuid),
                UNIQUE KEY staff_brief_snapshot (staff_brief_snapshot_uuid),
                KEY team_opponent (team_source_id, opponent_source_id),
                KEY source_match (source_match_id),
                KEY status_updated (status, updated_at)
            ) {$charset};"
        );

        dbDelta(
            "CREATE TABLE {$live_snapshots} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                session_uuid varchar(64) NOT NULL,
                minute smallint(6) NOT NULL DEFAULT 1,
                our_score smallint(6) NOT NULL DEFAULT 0,
                opponent_score smallint(6) NOT NULL DEFAULT 0,
                our_players tinyint(4) NOT NULL DEFAULT 11,
                opponent_players tinyint(4) NOT NULL DEFAULT 11,
                active_plan varchar(8) NOT NULL DEFAULT 'A',
                active_mode varchar(64) NOT NULL DEFAULT 'baseline',
                selected_scenario varchar(96) NOT NULL DEFAULT '',
                activation_status varchar(64) NOT NULL DEFAULT '',
                observations_json longtext NOT NULL,
                note text NOT NULL,
                playbook_json longtext NOT NULL,
                created_by bigint(20) unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY session_minute (session_uuid, minute, id),
                KEY active_plan (active_plan),
                KEY selected_scenario (selected_scenario),
                KEY created_at (created_at)
            ) {$charset};"
        );

        update_option( 'tactitwin_schema_version', self::SCHEMA_VERSION );
    }

    public static function table( string $entity ): string {
        global $wpdb;

        $allowed = array( 'competitions', 'teams', 'players', 'matches', 'events', 'staff_brief_snapshots', 'live_sessions', 'live_snapshots' );
        if ( ! in_array( $entity, $allowed, true ) ) {
            throw new InvalidArgumentException( 'Unknown TactiTwin entity table.' );
        }

        return $wpdb->prefix . 'tactitwin_' . $entity;
    }

    public static function counts(): array {
        global $wpdb;

        $counts = array();
        foreach ( array( 'competitions', 'teams', 'players', 'matches', 'events', 'staff_brief_snapshots', 'live_sessions', 'live_snapshots' ) as $entity ) {
            $table             = self::table( $entity );
            $counts[ $entity ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        return $counts;
    }
}
