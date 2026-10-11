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
        int $sample_size = 5,
        array $revision = array()
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

        if ( ! empty( $revision ) && ! TactiTwin_Staff_Brief_Revision_Chain::verify( $revision ) ) {
            throw new InvalidArgumentException( 'Staff Brief approval revision metadata failed integrity verification.' );
        }

        $brief_copy = $staff_brief;
        unset( $brief_copy['handoff_source'] );

        $content = array(
            'staff_brief' => $brief_copy,
            'match_plan' => $match_plan,
            'playbook' => $playbook,
        );
        $content_sha256 = self::hash_content( $content );
        if ( ! empty( $revision ) ) {
            $revision = TactiTwin_Staff_Brief_Revision_Chain::bind_to_snapshot( $revision, $snapshot_uuid, $content_sha256 );
        }

        $language = TactiTwin_I18n::normalize_language( (string) ( $staff_brief['language'] ?? 'en' ) );
        $team = is_array( $staff_brief['team'] ?? null ) ? $staff_brief['team'] : array();
        $opponent = is_array( $staff_brief['opponent'] ?? null ) ? $staff_brief['opponent'] : array();
        $fixture = TactiTwin_Fixture_Identity::from_brief( $staff_brief );

        $snapshot = array(
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
            'content_sha256' => $content_sha256,
            'content' => $content,
            'method' => array(
                'type' => 'immutable-approved-staff-brief-snapshot',
                'immutable' => true,
                'recalculation_on_handoff' => false,
                'stores_exact_match_plan_source' => true,
                'stores_exact_playbook_source' => true,
                'hash_algorithm' => 'sha256',
                'revision_chain_included' => ! empty( $revision ),
                'fixture_identity_included' => ! empty( $fixture ),
                'changes_tactical_scores' => false,
                'changes_recommendations' => false,
                'changes_plan_activation' => false,
            ),
        );

        if ( ! empty( $fixture ) ) {
            $snapshot['fixture_key'] = (string) $fixture['fixture_key'];
            $snapshot['fixture'] = $fixture;
        }
        if ( ! empty( $revision ) ) {
            $snapshot['revision'] = $revision;
        }

        return $snapshot;
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
        $expected = strtolower( trim( (string) ( $snapshot['content_sha256'] ?? '' ) ) );
        if ( '' === $expected || ! hash_equals( $expected, self::hash_content( $snapshot['content'] ) ) ) {
            return false;
        }

        $stored_brief = is_array( $snapshot['content']['staff_brief'] ?? null ) ? $snapshot['content']['staff_brief'] : array();
        try {
            $fixture = TactiTwin_Fixture_Identity::from_brief( $stored_brief );
        } catch ( Throwable $e ) {
            return false;
        }
        $declared_fixture_key = strtolower( trim( (string) ( $snapshot['fixture_key'] ?? '' ) ) );
        if ( ! empty( $fixture ) ) {
            if ( '' === $declared_fixture_key || ! hash_equals( (string) $fixture['fixture_key'], $declared_fixture_key ) ) {
                return false;
            }
            if ( ! is_array( $snapshot['fixture'] ?? null ) ) {
                return false;
            }
            try {
                $top_fixture = TactiTwin_Fixture_Identity::normalize(
                    $snapshot['fixture'],
                    (string) ( $snapshot['team']['source_id'] ?? '' ),
                    (string) ( $snapshot['opponent']['source_id'] ?? '' )
                );
            } catch ( Throwable $e ) {
                return false;
            }
            if ( ! hash_equals( (string) $fixture['fixture_key'], (string) $top_fixture['fixture_key'] ) ) {
                return false;
            }
        } elseif ( '' !== $declared_fixture_key || isset( $snapshot['fixture'] ) ) {
            return false;
        }

        if ( isset( $snapshot['revision'] ) ) {
            if ( ! is_array( $snapshot['revision'] ) || ! TactiTwin_Staff_Brief_Revision_Chain::verify( $snapshot['revision'] ) ) {
                return false;
            }
            $bound_uuid = self::normalize_uuid( (string) ( $snapshot['revision']['approved_snapshot_uuid'] ?? '' ) );
            $snapshot_uuid = self::normalize_uuid( (string) ( $snapshot['snapshot_uuid'] ?? '' ) );
            if ( '' === $bound_uuid || '' === $snapshot_uuid || ! hash_equals( $snapshot_uuid, $bound_uuid ) ) {
                return false;
            }
            $bound_content = strtolower( trim( (string) ( $snapshot['revision']['approved_content_sha256'] ?? '' ) ) );
            if ( '' === $bound_content || ! hash_equals( $expected, $bound_content ) ) {
                return false;
            }
            $revision_fixture = strtolower( trim( (string) ( $snapshot['revision']['fixture_key'] ?? '' ) ) );
            if ( '' !== $declared_fixture_key && ( '' === $revision_fixture || ! hash_equals( $declared_fixture_key, $revision_fixture ) ) ) {
                return false;
            }
            if ( '' === $declared_fixture_key && '' !== $revision_fixture ) {
                return false;
            }
        }

        return true;
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

    public static function revision( array $snapshot ): array {
        if ( ! self::verify( $snapshot ) ) {
            throw new RuntimeException( 'The approved Staff Brief snapshot failed integrity verification.' );
        }
        $revision = $snapshot['revision'] ?? array();
        return is_array( $revision ) ? $revision : array();
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
        TactiTwin_Fixture_Identity::from_brief( $staff_brief );
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
