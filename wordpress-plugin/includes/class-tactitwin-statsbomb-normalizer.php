<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_StatsBomb_Normalizer {
    public const SOURCE = 'statsbomb';

    public static function competition( array $row ): array {
        return array(
            'source'                => self::SOURCE,
            'source_competition_id' => (string) ( $row['competition_id'] ?? '' ),
            'source_season_id'      => (string) ( $row['season_id'] ?? '' ),
            'country_name'          => (string) ( $row['country_name'] ?? '' ),
            'competition_name'      => (string) ( $row['competition_name'] ?? '' ),
            'competition_gender'    => (string) ( $row['competition_gender'] ?? '' ),
            'season_name'           => (string) ( $row['season_name'] ?? '' ),
            'source_updated_at'     => (string) ( $row['match_updated'] ?? $row['match_available'] ?? '' ),
            'raw_json'              => wp_json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            'imported_at'           => current_time( 'mysql', true ),
        );
    }

    public static function team( array $row, string $prefix = '' ): array {
        $id_key   = $prefix ? $prefix . '_team_id' : 'id';
        $name_key = $prefix ? $prefix . '_team_name' : 'name';
        $country  = isset( $row['country']['name'] ) ? (string) $row['country']['name'] : '';

        return array(
            'source'         => self::SOURCE,
            'source_team_id' => (string) ( $row[ $id_key ] ?? '' ),
            'team_name'      => (string) ( $row[ $name_key ] ?? '' ),
            'country_name'   => $country,
            'raw_json'       => wp_json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            'imported_at'    => current_time( 'mysql', true ),
        );
    }

    public static function player( array $event ): ?array {
        if ( empty( $event['player']['id'] ) || empty( $event['player']['name'] ) ) {
            return null;
        }

        return array(
            'source'                => self::SOURCE,
            'source_player_id'      => (string) $event['player']['id'],
            'player_name'           => (string) $event['player']['name'],
            'last_team_source_id'   => (string) ( $event['team']['id'] ?? '' ),
            'last_position_name'    => (string) ( $event['position']['name'] ?? '' ),
            'raw_json'              => wp_json_encode( $event['player'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            'imported_at'           => current_time( 'mysql', true ),
        );
    }

    public static function match( array $row ): array {
        return array(
            'source'                    => self::SOURCE,
            'source_match_id'           => (string) ( $row['match_id'] ?? '' ),
            'source_competition_id'     => (string) ( $row['competition']['competition_id'] ?? '' ),
            'source_season_id'          => (string) ( $row['season']['season_id'] ?? '' ),
            'match_date'                => ! empty( $row['match_date'] ) ? (string) $row['match_date'] : null,
            'kick_off'                  => (string) ( $row['kick_off'] ?? '' ),
            'home_team_source_id'       => (string) ( $row['home_team']['home_team_id'] ?? '' ),
            'away_team_source_id'       => (string) ( $row['away_team']['away_team_id'] ?? '' ),
            'home_score'                => isset( $row['home_score'] ) ? (int) $row['home_score'] : null,
            'away_score'                => isset( $row['away_score'] ) ? (int) $row['away_score'] : null,
            'match_week'                => isset( $row['match_week'] ) ? (int) $row['match_week'] : null,
            'match_status'              => (string) ( $row['match_status'] ?? '' ),
            'raw_json'                  => wp_json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            'imported_at'               => current_time( 'mysql', true ),
        );
    }

    public static function event( int $match_id, array $row ): array {
        $detail       = self::event_detail( $row );
        $end_location = $detail['end_location'];
        $location     = isset( $row['location'] ) && is_array( $row['location'] ) ? $row['location'] : array();

        return array(
            'source'                    => self::SOURCE,
            'source_event_id'           => (string) ( $row['id'] ?? '' ),
            'source_match_id'           => (string) $match_id,
            'event_index'               => (int) ( $row['index'] ?? 0 ),
            'period'                    => (int) ( $row['period'] ?? 0 ),
            'minute'                    => (int) ( $row['minute'] ?? 0 ),
            'second'                    => (int) ( $row['second'] ?? 0 ),
            'event_type_id'             => (string) ( $row['type']['id'] ?? '' ),
            'event_type_name'           => (string) ( $row['type']['name'] ?? '' ),
            'possession'                => isset( $row['possession'] ) ? (int) $row['possession'] : null,
            'possession_team_source_id' => (string) ( $row['possession_team']['id'] ?? '' ),
            'team_source_id'            => (string) ( $row['team']['id'] ?? '' ),
            'player_source_id'          => (string) ( $row['player']['id'] ?? '' ),
            'position_name'             => (string) ( $row['position']['name'] ?? '' ),
            'location_x'                => isset( $location[0] ) ? (float) $location[0] : null,
            'location_y'                => isset( $location[1] ) ? (float) $location[1] : null,
            'end_location_x'            => isset( $end_location[0] ) ? (float) $end_location[0] : null,
            'end_location_y'            => isset( $end_location[1] ) ? (float) $end_location[1] : null,
            'outcome_name'              => $detail['outcome_name'],
            'xg'                        => $detail['xg'],
            'raw_json'                  => wp_json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            'imported_at'               => current_time( 'mysql', true ),
        );
    }

    private static function event_detail( array $row ): array {
        foreach ( array( 'pass', 'shot', 'carry', 'dribble', 'duel', 'goalkeeper' ) as $key ) {
            if ( empty( $row[ $key ] ) || ! is_array( $row[ $key ] ) ) {
                continue;
            }

            $detail = $row[ $key ];
            return array(
                'end_location' => isset( $detail['end_location'] ) && is_array( $detail['end_location'] ) ? $detail['end_location'] : array(),
                'outcome_name' => (string) ( $detail['outcome']['name'] ?? '' ),
                'xg'           => isset( $detail['statsbomb_xg'] ) ? (float) $detail['statsbomb_xg'] : null,
            );
        }

        return array(
            'end_location' => array(),
            'outcome_name' => '',
            'xg'           => null,
        );
    }
}
