<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Staff_Brief_Snapshot {
    public static function freeze(
        array $staff_brief,
        string $snapshot_uuid,
        int $approved_by,
        string $approved_at,
        int $sample_size = 5
    ): array {
        self::assert_staff_brief( $staff_brief );
        $snapshot_uuid = self::normalize_uuid( $snapshot_uuid );
        if ( '' === $snapshot_uuid ) {
            throw new InvalidArgumentException( 'A snapshot UUID is required.' );
        }

        $source = is_array( $staff_brief['handoff_source'] ?? null ) ? $staff_brief['handoff_source'] : array();
        $match_plan = is_array( $source['match_plan'] ?? null ) ? $source['match_plan'] : array();
        $playbook = is_array( $source['playbook'] ?? null ) ? $source['playbook'] : array();
        self::assert_source( $staff_brief, $match_plan, $playbook );

        $brief_copy = $staff_brief;
        unset( $brief_copy['handoff_source'] );

        $content = array(
            'staff_brief' => $brief_copy,
            'match_plan' => $match_plan,
            'playbook' => $playbook,
        );
        $language = TactiTwin_I18n::normalize_language( (string) ( $staff_brief['language'] ?? 'en' ) );
        $team = is_array( $staff_brief['team'] ?? null ) ? $staff_brief['team'] : array();
        $opponent = is_array( $staff_brief['opponent'] ?? null ) ? $staff_brief['opponent'] : array();

        return array(
            'snapshot_id' => 'match-day-staff-brief-snapshot',
            'snapshot_uuid' => $snapshot_uuid,
            'status' => 'approved',
            'language' => $language,
            'team' => $team,
            'opponent' => $opponent,
            'sample_size' => max( 1, min( 10, $sample_size ) ),
            'approved_by' => max( 0, $approved_by ),
            'approved_at' => trim( $approved_at ),
            'plugin_version' => defined( 'TACTITWIN_VERSION' ) ? (string) TACTITWIN_VERSION : '',
            'content_sha256' => self::hash_content( $content ),
            'content' => $content,
            'method' => array(
                'type' => 'immutable-approved-staff-brief-snapshot',
                'immutable' => true,
                'recalculation_on_handoff' => false,
                'stores_exact_match_plan_source' => true,
                'stores_exact_playbook_source' => true,
                'hash_algorithm' => 'sha256',
                'changes_tactical_scores' => false,
                'changes_recommendations' => false,
                'changes_plan_activation' => false,
            ),
        );
    }

    public static function verify( array $snapshot ): bool {
        if ( 'match-day-staff-brief-snapshot' !== (string) ( $snapshot['snapshot_id'] ?? '' ) ) {
            return false;
        }
        if ( 'approved' !== (string) ( $snapshot['status'] ?? '' ) ) {
            return false;
        }
        if ( empty( $snapshot['content'] ) || ! is_array( $snapshot['content'] ) ) {
            return false;
        }
        $expected = (string) ( $snapshot['content_sha256'] ?? '' );
        return '' !== $expected && hash_equals( $expected, self::hash_content( $snapshot['content'] ) );
    }

    public static function playbook( array $snapshot ): array {
        if ( ! self::verify( $snapshot ) ) {
            throw new RuntimeException( 'The approved Staff Brief snapshot failed integrity verification.' );
        }
        $playbook = $snapshot['content']['playbook'] ?? array();
        if ( ! is_array( $playbook ) || 'contingency-playbook' !== (string) ( $playbook['playbook_id'] ?? '' ) ) {
            throw new RuntimeException( 'The approved Staff Brief snapshot has no valid frozen playbook.' );
        }
        return $playbook;
    }

    public static function staff_brief( array $snapshot ): array {
        if ( ! self::verify( $snapshot ) ) {
            throw new RuntimeException( 'The approved Staff Brief snapshot failed integrity verification.' );
        }
        $brief = $snapshot['content']['staff_brief'] ?? array();
        return is_array( $brief ) ? $brief : array();
    }

    private static function assert_staff_brief( array $staff_brief ): void {
        if ( 'match-day-staff-brief' !== (string) ( $staff_brief['staff_brief_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'An approved snapshot requires a Match-Day Staff Brief.' );
        }
        if ( empty( $staff_brief['handoff_source'] ) || ! is_array( $staff_brief['handoff_source'] ) ) {
            throw new InvalidArgumentException( 'The Staff Brief does not contain a handoff source payload.' );
        }
    }

    private static function assert_source( array $staff_brief, array $match_plan, array $playbook ): void {
        if ( 'match-plan' !== (string) ( $match_plan['plan_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'The Staff Brief handoff source requires a Match Plan.' );
        }
        if ( 'contingency-playbook' !== (string) ( $playbook['playbook_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'The Staff Brief handoff source requires a Contingency Playbook.' );
        }

        $team_id = (string) ( $staff_brief['team']['source_id'] ?? '' );
        $opponent_id = (string) ( $staff_brief['opponent']['source_id'] ?? '' );
        if (
            '' === $team_id
            || '' === $opponent_id
            || $team_id !== (string) ( $match_plan['team']['source_id'] ?? '' )
            || $team_id !== (string) ( $playbook['team']['source_id'] ?? '' )
            || $opponent_id !== (string) ( $match_plan['opponent']['source_id'] ?? '' )
            || $opponent_id !== (string) ( $playbook['opponent']['source_id'] ?? '' )
        ) {
            throw new InvalidArgumentException( 'Staff Brief, Match Plan and Playbook must describe the same matchup.' );
        }
    }

    private static function hash_content( array $content ): string {
        return hash( 'sha256', self::json_encode( self::canonicalize( $content ) ) );
    }

    private static function canonicalize( $value ) {
        if ( ! is_array( $value ) ) {
            return $value;
        }
        if ( self::is_list( $value ) ) {
            return array_map( array( self::class, 'canonicalize' ), $value );
        }
        ksort( $value );
        foreach ( $value as $key => $item ) {
            $value[ $key ] = self::canonicalize( $item );
        }
        return $value;
    }

    private static function is_list( array $value ): bool {
        if ( array() === $value ) {
            return true;
        }
        return array_keys( $value ) === range( 0, count( $value ) - 1 );
    }

    private static function json_encode( $value ): string {
        if ( function_exists( 'wp_json_encode' ) ) {
            return (string) wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION );
        }
        return (string) json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION );
    }

    private static function normalize_uuid( string $snapshot_uuid ): string {
        return preg_replace( '/[^a-zA-Z0-9\-]/', '', trim( $snapshot_uuid ) ) ?: '';
    }
}
