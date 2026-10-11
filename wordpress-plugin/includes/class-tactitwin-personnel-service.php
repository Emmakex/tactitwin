<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Personnel_Service {
    public function player_profile( int $team_id, int $player_id, int $limit = 5 ): array {
        global $wpdb;

        $limit        = max( 1, min( 10, $limit ) );
        $match_table  = TactiTwin_DB::table( 'matches' );
        $event_table  = TactiTwin_DB::table( 'events' );
        $player_table = TactiTwin_DB::table( 'players' );
        $source       = TactiTwin_StatsBomb_Normalizer::SOURCE;

        $matches = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.source_match_id, m.match_date
                 FROM {$match_table} m
                 WHERE m.source = %s
                   AND (m.home_team_source_id = %s OR m.away_team_source_id = %s)
                   AND EXISTS (
                       SELECT 1 FROM {$event_table} e
                       WHERE e.source = m.source AND e.source_match_id = m.source_match_id
                       LIMIT 1
                   )
                 ORDER BY m.match_date DESC, m.id DESC
                 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $source,
                (string) $team_id,
                (string) $team_id,
                $limit
            ),
            ARRAY_A
        ) ?: array();

        if ( empty( $matches ) ) {
            throw new RuntimeException( 'No imported event-data matches are available for this team.' );
        }

        $samples = array();
        foreach ( $matches as $match ) {
            $events = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT event_index, period, minute, second, team_source_id, player_source_id,
                            position_name, event_type_name, outcome_name, xg,
                            location_x, location_y, end_location_x, end_location_y, raw_json
                     FROM {$event_table}
                     WHERE source = %s AND source_match_id = %s
                     ORDER BY event_index ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $source,
                    (string) $match['source_match_id']
                ),
                ARRAY_A
            ) ?: array();

            if ( empty( $events ) ) {
                continue;
            }

            $match_players = TactiTwin_Player_Metrics::calculate( $events, (string) $team_id );
            $key = (string) $player_id;
            if ( isset( $match_players[ $key ] ) && (float) ( $match_players[ $key ]['minutes'] ?? 0 ) > 0.0 ) {
                $samples[] = array(
                    'match_id'   => (string) $match['source_match_id'],
                    'match_date' => (string) $match['match_date'],
                    'metrics'    => $match_players[ $key ],
                );
            }
        }

        $metadata = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT source_player_id, player_name, last_team_source_id, last_position_name
                 FROM {$player_table}
                 WHERE source = %s AND source_player_id = %s
                 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $source,
                (string) $player_id
            ),
            ARRAY_A
        ) ?: array();

        $profile = TactiTwin_Player_Profile::aggregate(
            $samples,
            array(
                'player_source_id' => (string) $player_id,
                'player_name'      => (string) ( $metadata['player_name'] ?? $player_id ),
                'team_source_id'   => (string) $team_id,
                'position_name'    => (string) ( $metadata['last_position_name'] ?? '' ),
            )
        );

        if ( (float) ( $profile['coverage']['minutes'] ?? 0 ) <= 0.0 ) {
            throw new RuntimeException( 'No recent on-pitch event sample is available for this player.' );
        }

        return $profile;
    }

    public function personnel_what_if(
        int $team_id,
        int $opponent_id,
        int $player_out_id,
        int $player_in_id,
        int $minute = 60,
        int $our_score = 0,
        int $opponent_score = 0,
        int $our_players = 11,
        int $opponent_players = 11,
        int $limit = 5,
        string $language = 'en'
    ): array {
        if ( $player_out_id === $player_in_id ) {
            throw new InvalidArgumentException( 'Outgoing and incoming players must be different.' );
        }

        $language = TactiTwin_I18n::normalize_language( $language );
        $out = $this->player_profile( $team_id, $player_out_id, $limit );
        $in  = $this->player_profile( $team_id, $player_in_id, $limit );

        $context = ( new TactiTwin_Profile_Service() )->game_state_what_if(
            $team_id,
            $opponent_id,
            $our_score,
            $opponent_score,
            $minute,
            $limit,
            $language,
            $our_players,
            $opponent_players
        );

        return TactiTwin_Personnel_What_If::compare( $out, $in, $minute, $language, $context );
    }

    public function team_players( int $team_id ): array {
        global $wpdb;

        $player_table = TactiTwin_DB::table( 'players' );
        $event_table  = TactiTwin_DB::table( 'events' );
        $source       = TactiTwin_StatsBomb_Normalizer::SOURCE;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT p.source_player_id, p.player_name, p.last_position_name
                 FROM {$player_table} p
                 WHERE p.source = %s
                   AND (
                       p.last_team_source_id = %s
                       OR EXISTS (
                           SELECT 1 FROM {$event_table} e
                           WHERE e.source = p.source
                             AND e.team_source_id = %s
                             AND e.player_source_id = p.source_player_id
                           LIMIT 1
                       )
                   )
                 ORDER BY p.player_name ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $source,
                (string) $team_id,
                (string) $team_id
            ),
            ARRAY_A
        ) ?: array();
    }
}
