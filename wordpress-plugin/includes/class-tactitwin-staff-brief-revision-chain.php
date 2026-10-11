<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Staff_Brief_Revision_Chain {
    public static function build(
        array $current_brief,
        array $previous_snapshot = array(),
        int $revision_number = 1,
        string $language = 'en',
        string $approval_note = ''
    ): array {
        if ( 'match-day-staff-brief' !== (string) ( $current_brief['staff_brief_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'Approval revision metadata requires a Match-Day Staff Brief.' );
        }

        $language = TactiTwin_I18n::normalize_language( $language );
        $fixture_key = TactiTwin_Fixture_Identity::key_from_brief( $current_brief );
        $parent_uuid = null;
        $parent_sha256 = null;
        $reviewed_diff = null;
        $reviewed_diff_sha256 = null;
        $change_count = 0;
        $change_ids = array();
        $changed_sections = array();

        if ( ! empty( $previous_snapshot ) ) {
            if ( ! TactiTwin_Staff_Brief_Snapshot::verify( $previous_snapshot ) ) {
                throw new InvalidArgumentException( 'Previous Staff Brief snapshot failed integrity verification.' );
            }
            $previous_brief = TactiTwin_Staff_Brief_Snapshot::staff_brief( $previous_snapshot );
            TactiTwin_Fixture_Identity::assert_same_scope( $current_brief, $previous_brief );
            $diff = TactiTwin_Staff_Brief_Version_Diff::current_against_snapshot( $current_brief, $previous_snapshot, $language );
            $reviewed_diff = self::machine_diff( $diff );
            $reviewed_diff_sha256 = self::hash_value( $reviewed_diff );
            $change_count = (int) ( $reviewed_diff['change_count'] ?? 0 );
            $change_ids = array_values(
                array_map(
                    static fn( array $change ): string => (string) ( $change['id'] ?? '' ),
                    is_array( $reviewed_diff['changes'] ?? null ) ? $reviewed_diff['changes'] : array()
                )
            );
            $changed_sections = array_values( is_array( $reviewed_diff['changed_sections'] ?? null ) ? $reviewed_diff['changed_sections'] : array() );
            $parent_uuid = (string) ( $previous_snapshot['snapshot_uuid'] ?? '' );
            $parent_sha256 = (string) ( $previous_snapshot['content_sha256'] ?? '' );
        }

        $revision = array(
            'chain_id' => 'staff-brief-approval-revision',
            'revision_number' => max( 1, $revision_number ),
            'fixture_key' => $fixture_key,
            'parent_snapshot_uuid' => $parent_uuid,
            'parent_content_sha256' => $parent_sha256,
            'approved_snapshot_uuid' => null,
            'approved_content_sha256' => null,
            'reviewed_change_count' => $change_count,
            'reviewed_change_ids' => $change_ids,
            'changed_sections' => $changed_sections,
            'reviewed_diff_sha256' => $reviewed_diff_sha256,
            'reviewed_diff' => $reviewed_diff,
            'requires_change_acknowledgement' => null !== $parent_uuid && $change_count > 0,
            'approval_note' => trim( $approval_note ),
            'method' => array(
                'type' => 'immutable-staff-brief-approval-revision-chain',
                'reads_existing_version_diff' => true,
                'fixture_scoped_lineage' => '' !== $fixture_key,
                'bound_to_approved_snapshot_identity' => true,
                'bound_to_approved_snapshot_content' => true,
                'creates_change_score' => false,
                'recalculates_tactical_models' => false,
                'changes_tactical_scores' => false,
                'changes_recommendations' => false,
                'changes_evidence_order' => false,
                'changes_plan_activation' => false,
            ),
        );
        $revision['revision_sha256'] = self::revision_hash( $revision );

        return $revision;
    }

    public static function bind_to_snapshot( array $revision, string $snapshot_uuid, string $content_sha256 ): array {
        if ( ! self::verify( $revision ) ) {
            throw new InvalidArgumentException( 'Approval revision metadata failed integrity verification before snapshot binding.' );
        }
        $snapshot_uuid = self::normalize_uuid( $snapshot_uuid );
        if ( '' === $snapshot_uuid ) {
            throw new InvalidArgumentException( 'Approval revision snapshot binding requires a valid snapshot UUID.' );
        }
        $content_sha256 = strtolower( trim( $content_sha256 ) );
        if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $content_sha256 ) ) {
            throw new InvalidArgumentException( 'Approval revision snapshot binding requires a valid SHA-256 digest.' );
        }
        $revision['approved_snapshot_uuid'] = $snapshot_uuid;
        $revision['approved_content_sha256'] = $content_sha256;
        $revision['revision_sha256'] = self::revision_hash( $revision );
        return $revision;
    }

    public static function verify( array $revision ): bool {
        if ( 'staff-brief-approval-revision' !== (string) ( $revision['chain_id'] ?? '' ) ) {
            return false;
        }
        $expected = strtolower( trim( (string) ( $revision['revision_sha256'] ?? '' ) ) );
        if ( 64 !== strlen( $expected ) ) {
            return false;
        }
        $fixture_key = strtolower( trim( (string) ( $revision['fixture_key'] ?? '' ) ) );
        if ( '' !== $fixture_key && 1 !== preg_match( '/^[a-f0-9]{64}$/', $fixture_key ) ) {
            return false;
        }
        return hash_equals( $expected, self::revision_hash( $revision ) );
    }

    public static function reviewed_diff_digest( array $diff ): string {
        return self::hash_value( self::machine_diff( $diff ) );
    }

    public static function machine_diff( array $diff ): array {
        $changes = array();
        foreach ( is_array( $diff['changes'] ?? null ) ? $diff['changes'] : array() as $change ) {
            if ( ! is_array( $change ) ) {
                continue;
            }
            $changes[] = array(
                'id' => (string) ( $change['id'] ?? '' ),
                'section' => (string) ( $change['section'] ?? '' ),
                'field' => (string) ( $change['field'] ?? '' ),
                'before' => $change['before'] ?? null,
                'after' => $change['after'] ?? null,
                'context' => is_array( $change['context'] ?? null ) ? $change['context'] : array(),
            );
        }

        return array(
            'diff_id' => (string) ( $diff['diff_id'] ?? 'staff-brief-version-diff' ),
            'status' => (string) ( $diff['status'] ?? ( empty( $changes ) ? 'unchanged' : 'changed' ) ),
            'fixture_key' => (string) ( $diff['fixture_key'] ?? '' ),
            'team_source_id' => (string) ( $diff['team']['source_id'] ?? '' ),
            'opponent_source_id' => (string) ( $diff['opponent']['source_id'] ?? '' ),
            'from' => array(
                'kind' => (string) ( $diff['from']['kind'] ?? '' ),
                'snapshot_uuid' => $diff['from']['snapshot_uuid'] ?? null,
                'approved_at' => $diff['from']['approved_at'] ?? null,
                'sha256' => $diff['from']['sha256'] ?? null,
            ),
            'to' => array(
                'kind' => (string) ( $diff['to']['kind'] ?? '' ),
                'snapshot_uuid' => $diff['to']['snapshot_uuid'] ?? null,
                'approved_at' => $diff['to']['approved_at'] ?? null,
                'sha256' => $diff['to']['sha256'] ?? null,
            ),
            'change_count' => count( $changes ),
            'changed_sections' => array_values( is_array( $diff['changed_sections'] ?? null ) ? $diff['changed_sections'] : array() ),
            'changes' => $changes,
        );
    }

    private static function revision_hash( array $revision ): string {
        $copy = $revision;
        unset( $copy['revision_sha256'] );
        return self::hash_value( $copy );
    }

    private static function hash_value( $value ): string {
        return hash( 'sha256', self::json_encode( self::canonicalize( $value ) ) );
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

    private static function normalize_uuid( string $value ): string {
        return preg_replace( '/[^a-zA-Z0-9\-]/', '', trim( $value ) ) ?: '';
    }

    private static function json_encode( $value ): string {
        if ( function_exists( 'wp_json_encode' ) ) {
            return (string) wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION );
        }
        return (string) json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION );
    }
}
