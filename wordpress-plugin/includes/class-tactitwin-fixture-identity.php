<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Fixture_Identity {
    public static function normalize( array $fixture, string $team_source_id = '', string $opponent_source_id = '' ): array {
        $team_source_id = self::clean_id( $team_source_id ?: (string) ( $fixture['team_source_id'] ?? '' ) );
        $opponent_source_id = self::clean_id( $opponent_source_id ?: (string) ( $fixture['opponent_source_id'] ?? '' ) );
        if ( '' === $team_source_id || '' === $opponent_source_id || $team_source_id === $opponent_source_id ) {
            throw new InvalidArgumentException( 'Fixture identity requires two different team identifiers.' );
        }

        $source = strtolower( self::clean_id( (string) ( $fixture['source'] ?? 'manual' ) ) );
        $source = '' === $source ? 'manual' : $source;
        $source_match_id = self::clean_id( (string) ( $fixture['source_match_id'] ?? '' ) );
        $competition_id = self::clean_id( (string) ( $fixture['source_competition_id'] ?? '' ) );
        $season_id = self::clean_id( (string) ( $fixture['source_season_id'] ?? '' ) );
        $match_date = self::date( (string) ( $fixture['match_date'] ?? '' ) );
        $kick_off = self::clean_clock( (string) ( $fixture['kick_off'] ?? '' ) );
        $match_week = self::clean_id( (string) ( $fixture['match_week'] ?? '' ) );

        if ( '' !== $source_match_id ) {
            $mode = 'provider-match-id';
            $identity = array(
                'source' => $source,
                'source_match_id' => $source_match_id,
                'team_source_id' => $team_source_id,
                'opponent_source_id' => $opponent_source_id,
            );
        } else {
            if ( '' === $match_date ) {
                throw new InvalidArgumentException( 'Fixture identity requires either a provider match ID or a match date.' );
            }
            $mode = 'scheduled-composite';
            $identity = array(
                'source' => $source,
                'source_competition_id' => $competition_id,
                'source_season_id' => $season_id,
                'match_date' => $match_date,
                'kick_off' => $kick_off,
                'match_week' => $match_week,
                'team_source_id' => $team_source_id,
                'opponent_source_id' => $opponent_source_id,
            );
        }

        return array(
            'fixture_key' => self::hash( $identity ),
            'identity_mode' => $mode,
            'source' => $source,
            'source_match_id' => $source_match_id,
            'source_competition_id' => $competition_id,
            'source_season_id' => $season_id,
            'match_date' => $match_date,
            'kick_off' => $kick_off,
            'match_week' => $match_week,
            'team_source_id' => $team_source_id,
            'opponent_source_id' => $opponent_source_id,
        );
    }

    public static function from_brief( array $brief ): array {
        $fixture = is_array( $brief['fixture'] ?? null ) ? $brief['fixture'] : array();
        if ( empty( $fixture ) ) {
            return array();
        }
        $team_id = (string) ( $brief['team']['source_id'] ?? '' );
        $opponent_id = (string) ( $brief['opponent']['source_id'] ?? '' );
        $normalized = self::normalize( $fixture, $team_id, $opponent_id );
        $declared = strtolower( trim( (string) ( $fixture['fixture_key'] ?? '' ) ) );
        if ( '' !== $declared && ! hash_equals( $normalized['fixture_key'], $declared ) ) {
            throw new InvalidArgumentException( 'Fixture identity key does not match its machine fields.' );
        }
        return $normalized;
    }

    public static function key_from_brief( array $brief ): string {
        $fixture = self::from_brief( $brief );
        return (string) ( $fixture['fixture_key'] ?? '' );
    }

    public static function assert_same_scope( array $a, array $b ): void {
        $a_key = self::key_from_brief( $a );
        $b_key = self::key_from_brief( $b );
        if ( '' === $a_key && '' === $b_key ) {
            return;
        }
        if ( '' === $a_key || '' === $b_key || ! hash_equals( $a_key, $b_key ) ) {
            throw new InvalidArgumentException( 'Staff Brief versions belong to different fixture identities.' );
        }
    }

    private static function hash( array $identity ): string {
        ksort( $identity );
        $json = function_exists( 'wp_json_encode' )
            ? wp_json_encode( $identity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
            : json_encode( $identity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        return hash( 'sha256', (string) $json );
    }

    private static function clean_id( string $value ): string {
        return preg_replace( '/[^a-zA-Z0-9_.:\-]/', '', trim( $value ) ) ?: '';
    }

    private static function date( string $value ): string {
        $value = trim( $value );
        if ( '' === $value ) {
            return '';
        }
        if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
            throw new InvalidArgumentException( 'Fixture match date must use YYYY-MM-DD.' );
        }
        [ $year, $month, $day ] = array_map( 'intval', explode( '-', $value ) );
        if ( ! checkdate( $month, $day, $year ) ) {
            throw new InvalidArgumentException( 'Fixture match date is not a valid calendar date.' );
        }
        return $value;
    }

    private static function clean_clock( string $value ): string {
        $value = trim( $value );
        if ( '' === $value ) {
            return '';
        }
        if ( 1 !== preg_match( '/^(\d{1,2}):(\d{2})(?::(\d{2})(?:\.\d{1,9})?)?$/', $value, $matches ) ) {
            throw new InvalidArgumentException( 'Fixture kick-off time must use HH:MM, HH:MM:SS or provider fractional seconds.' );
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];
        $has_seconds = isset( $matches[3] ) && '' !== $matches[3];
        $second = $has_seconds ? (int) $matches[3] : 0;

        if ( $hour > 23 || $minute > 59 || $second > 59 ) {
            throw new InvalidArgumentException( 'Fixture kick-off time is invalid.' );
        }

        return sprintf( '%02d:%02d%s', $hour, $minute, $has_seconds ? sprintf( ':%02d', $second ) : '' );
    }
}
