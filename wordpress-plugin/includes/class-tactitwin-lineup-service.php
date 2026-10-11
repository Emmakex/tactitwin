<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Lineup_Service {
    public function lineup_what_if(
        int $team_id,
        int $opponent_id,
        array $baseline_player_ids,
        array $candidate_player_ids,
        string $baseline_formation,
        string $candidate_formation,
        int $minute = 60,
        int $our_score = 0,
        int $opponent_score = 0,
        int $our_players = 11,
        int $opponent_players = 11,
        int $limit = 5,
        string $language = 'en'
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $baseline_player_ids = self::normalize_ids( $baseline_player_ids );
        $candidate_player_ids = self::normalize_ids( $candidate_player_ids );

        if ( 11 !== count( $baseline_player_ids ) || 11 !== count( $candidate_player_ids ) ) {
            throw new InvalidArgumentException( 'Both lineup variants must contain exactly 11 players.' );
        }
        if ( 11 !== count( array_unique( $baseline_player_ids ) ) || 11 !== count( array_unique( $candidate_player_ids ) ) ) {
            throw new InvalidArgumentException( 'A lineup cannot contain duplicate players.' );
        }

        $personnel = new TactiTwin_Personnel_Service();
        $profiles = array();
        foreach ( array_unique( array_merge( $baseline_player_ids, $candidate_player_ids ) ) as $player_id ) {
            $profiles[ (string) $player_id ] = $personnel->player_profile( $team_id, (int) $player_id, $limit );
        }

        $baseline = array();
        foreach ( $baseline_player_ids as $player_id ) {
            $baseline[] = $profiles[ (string) $player_id ];
        }
        $candidate = array();
        foreach ( $candidate_player_ids as $player_id ) {
            $candidate[] = $profiles[ (string) $player_id ];
        }

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

        return TactiTwin_Lineup_What_If::compare(
            $baseline,
            $candidate,
            $baseline_formation,
            $candidate_formation,
            $language,
            $context
        );
    }

    public function recent_starting_lineup( int $team_id ): array {
        global $wpdb;

        $match_table = TactiTwin_DB::table( 'matches' );
        $event_table = TactiTwin_DB::table( 'events' );
        $source = TactiTwin_StatsBomb_Normalizer::SOURCE;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT e.source_match_id, e.raw_json, m.match_date
                 FROM {$event_table} e
                 INNER JOIN {$match_table} m
                    ON m.source = e.source AND m.source_match_id = e.source_match_id
                 WHERE e.source = %s
                   AND e.team_source_id = %s
                   AND e.event_type_name = 'Starting XI'
                 ORDER BY m.match_date DESC, m.id DESC, e.event_index ASC
                 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $source,
                (string) $team_id
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            throw new RuntimeException( 'No imported Starting XI is available for this team.' );
        }

        $raw = json_decode( (string) $row['raw_json'], true );
        $lineup = is_array( $raw ) && isset( $raw['tactics']['lineup'] ) && is_array( $raw['tactics']['lineup'] )
            ? $raw['tactics']['lineup']
            : array();

        $players = array();
        foreach ( $lineup as $slot ) {
            $player = $slot['player'] ?? array();
            $id = (int) ( $player['id'] ?? 0 );
            if ( $id <= 0 ) {
                continue;
            }
            $players[] = array(
                'player_source_id' => $id,
                'player_name'      => (string) ( $player['name'] ?? $id ),
                'position_name'    => (string) ( $slot['position']['name'] ?? '' ),
                'jersey_number'    => isset( $slot['jersey_number'] ) ? (int) $slot['jersey_number'] : null,
            );
        }

        if ( 11 !== count( $players ) ) {
            throw new RuntimeException( 'The latest Starting XI does not contain exactly 11 identifiable players.' );
        }

        $provider_formation = (string) ( $raw['tactics']['formation'] ?? '' );
        $formation          = self::formation_from_provider( $provider_formation );
        $assignment         = null;

        if ( null !== $formation ) {
            $assignment = TactiTwin_Formation_Model::assign_players_to_roles( $players, $formation );
            $players    = $assignment['players'];
        }

        return array(
            'team_id'            => $team_id,
            'match_id'           => (string) $row['source_match_id'],
            'match_date'         => (string) $row['match_date'],
            'provider_formation' => $provider_formation,
            'formation'          => $formation,
            'players'            => $players,
            'role_assignment'    => null === $assignment ? array(
                'method' => 'provider-order-unmapped-formation',
                'slots'  => array(),
            ) : array(
                'method'      => (string) $assignment['method'],
                'total_score' => (float) $assignment['total_score'],
                'slots'       => $assignment['slots'],
            ),
        );
    }

    private static function normalize_ids( array $ids ): array {
        $result = array();
        foreach ( $ids as $id ) {
            $id = absint( $id );
            if ( $id > 0 ) {
                $result[] = $id;
            }
        }
        return $result;
    }

    private static function formation_from_provider( string $formation ): ?string {
        $key = preg_replace( '/[^0-9]/', '', $formation ) ?: '';
        $map = array(
            '433'  => '4-3-3',
            '4231' => '4-2-3-1',
            '442'  => '4-4-2',
            '3421' => '3-4-2-1',
            '352'  => '3-5-2',
            '541'  => '5-4-1',
        );
        return $map[ $key ] ?? null;
    }
}
