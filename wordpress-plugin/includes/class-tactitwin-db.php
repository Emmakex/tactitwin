<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_DB {
    public const SCHEMA_VERSION = '0.1.0';

    public static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        $competitions = $wpdb->prefix . 'tactitwin_competitions';
        $teams        = $wpdb->prefix . 'tactitwin_teams';
        $players      = $wpdb->prefix . 'tactitwin_players';
        $matches      = $wpdb->prefix . 'tactitwin_matches';
        $events       = $wpdb->prefix . 'tactitwin_events';

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

        update_option( 'tactitwin_schema_version', self::SCHEMA_VERSION );
    }

    public static function table( string $entity ): string {
        global $wpdb;

        $allowed = array( 'competitions', 'teams', 'players', 'matches', 'events' );
        if ( ! in_array( $entity, $allowed, true ) ) {
            throw new InvalidArgumentException( 'Unknown TactiTwin entity table.' );
        }

        return $wpdb->prefix . 'tactitwin_' . $entity;
    }

    public static function counts(): array {
        global $wpdb;

        $counts = array();
        foreach ( array( 'competitions', 'teams', 'players', 'matches', 'events' ) as $entity ) {
            $table            = self::table( $entity );
            $counts[ $entity ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        return $counts;
    }
}
