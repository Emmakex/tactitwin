<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Post_Match_Review_Snapshot {
    public static function review_sha256( array $review ): string {
        return self::hash_value( $review );
    }

    public static function freeze(
        array $review,
        array $binding,
        string $review_uuid,
        int $revision_number,
        int $signed_by,
        string $signed_at,
        string $signoff_note = '',
        array $parent = array()
    ): array {
        self::assert_review( $review, $binding );

        $review_uuid = self::normalize_uuid( $review_uuid );
        if ( '' === $review_uuid ) {
            throw new InvalidArgumentException( 'A post-match review UUID is required.' );
        }
        $revision_number = max( 1, $revision_number );
        $parent_uuid = self::normalize_uuid( (string) ( $parent['review_uuid'] ?? '' ) );
        $parent_sha = strtolower( trim( (string) ( $parent['content_sha256'] ?? '' ) ) );
        if ( $revision_number > 1 ) {
            if ( '' === $parent_uuid || 1 !== preg_match( '/^[a-f0-9]{64}$/', $parent_sha ) ) {
                throw new InvalidArgumentException( 'A later review revision requires the exact parent UUID and content hash.' );
            }
        } elseif ( '' !== $parent_uuid || '' !== $parent_sha ) {
            throw new InvalidArgumentException( 'The first review revision cannot declare a parent.' );
        }

        $content = array(
            'review' => $review,
            'signoff_note' => trim( $signoff_note ),
            'binding' => array(
                'fixture_key' => strtolower( trim( (string) ( $binding['fixture_key'] ?? '' ) ) ),
                'session_uuid' => self::normalize_uuid( (string) ( $binding['session_uuid'] ?? '' ) ),
                'staff_brief_snapshot_uuid' => self::normalize_uuid( (string) ( $binding['staff_brief_snapshot_uuid'] ?? '' ) ),
                'source_match_id' => trim( (string) ( $binding['source_match_id'] ?? '' ) ),
            ),
        );
        $review_sha = self::review_sha256( $review );
        $content_sha = self::hash_value( $content );

        return array(
            'snapshot_id' => 'post-match-review-signoff',
            'review_uuid' => $review_uuid,
            'status' => 'signed_off',
            'fixture_key' => (string) $content['binding']['fixture_key'],
            'session_uuid' => (string) $content['binding']['session_uuid'],
            'staff_brief_snapshot_uuid' => (string) $content['binding']['staff_brief_snapshot_uuid'],
            'source_match_id' => (string) $content['binding']['source_match_id'],
            'revision_number' => $revision_number,
            'parent_review_uuid' => $parent_uuid,
            'parent_content_sha256' => $parent_sha,
            'review_sha256' => $review_sha,
            'content_sha256' => $content_sha,
            'signed_by' => max( 0, $signed_by ),
            'signed_at' => trim( $signed_at ),
            'content' => $content,
            'method' => array(
                'type' => 'immutable-post-match-review-signoff',
                'immutable' => true,
                'hash_algorithm' => 'sha256',
                'application_signoff_not_digital_signature' => true,
                'stores_exact_review_and_evidence' => true,
                'versioned_revision_chain' => true,
                'changes_tactical_scores' => false,
                'changes_recommendations' => false,
                'changes_evidence_ranking' => false,
            ),
        );
    }

    public static function verify( array $snapshot ): bool {
        if ( 'post-match-review-signoff' !== (string) ( $snapshot['snapshot_id'] ?? '' ) ) {
            return false;
        }
        if ( 'signed_off' !== (string) ( $snapshot['status'] ?? '' ) ) {
            return false;
        }
        $uuid = self::normalize_uuid( (string) ( $snapshot['review_uuid'] ?? '' ) );
        if ( '' === $uuid || $uuid !== (string) ( $snapshot['review_uuid'] ?? '' ) ) {
            return false;
        }
        $fixture_key = strtolower( trim( (string) ( $snapshot['fixture_key'] ?? '' ) ) );
        if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $fixture_key ) ) {
            return false;
        }
        if ( empty( $snapshot['content'] ) || ! is_array( $snapshot['content'] ) ) {
            return false;
        }
        $content = $snapshot['content'];
        $binding = is_array( $content['binding'] ?? null ) ? $content['binding'] : array();
        $review = is_array( $content['review'] ?? null ) ? $content['review'] : array();
        if ( empty( $review ) ) {
            return false;
        }
        if ( $fixture_key !== strtolower( trim( (string) ( $binding['fixture_key'] ?? '' ) ) ) ) {
            return false;
        }
        if ( (string) ( $snapshot['session_uuid'] ?? '' ) !== (string) ( $binding['session_uuid'] ?? '' ) ) {
            return false;
        }
        if ( (string) ( $snapshot['staff_brief_snapshot_uuid'] ?? '' ) !== (string) ( $binding['staff_brief_snapshot_uuid'] ?? '' ) ) {
            return false;
        }
        if ( (string) ( $snapshot['source_match_id'] ?? '' ) !== (string) ( $binding['source_match_id'] ?? '' ) ) {
            return false;
        }
        $expected_review = strtolower( trim( (string) ( $snapshot['review_sha256'] ?? '' ) ) );
        if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $expected_review ) || ! hash_equals( $expected_review, self::review_sha256( $review ) ) ) {
            return false;
        }
        $expected_content = strtolower( trim( (string) ( $snapshot['content_sha256'] ?? '' ) ) );
        if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $expected_content ) || ! hash_equals( $expected_content, self::hash_value( $content ) ) ) {
            return false;
        }

        $revision = max( 0, (int) ( $snapshot['revision_number'] ?? 0 ) );
        $parent_uuid = self::normalize_uuid( (string) ( $snapshot['parent_review_uuid'] ?? '' ) );
        $parent_sha = strtolower( trim( (string) ( $snapshot['parent_content_sha256'] ?? '' ) ) );
        if ( 1 === $revision ) {
            return '' === $parent_uuid && '' === $parent_sha;
        }
        return $revision > 1
            && '' !== $parent_uuid
            && 1 === preg_match( '/^[a-f0-9]{64}$/', $parent_sha );
    }

    private static function assert_review( array $review, array $binding ): void {
        if ( 'post-match-review' !== (string) ( $review['review_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'Sign-off requires a generated post-match review.' );
        }
        $session = is_array( $review['session'] ?? null ) ? $review['session'] : array();
        if ( 'closed' !== (string) ( $session['status'] ?? '' ) ) {
            throw new RuntimeException( 'Post-match review sign-off requires a closed Live Session.' );
        }
        $fixture_key = strtolower( trim( (string) ( $binding['fixture_key'] ?? '' ) ) );
        if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $fixture_key ) ) {
            throw new InvalidArgumentException( 'Post-match review sign-off requires an exact fixture identity.' );
        }
        if ( self::normalize_uuid( (string) ( $session['session_uuid'] ?? '' ) ) !== self::normalize_uuid( (string) ( $binding['session_uuid'] ?? '' ) ) ) {
            throw new InvalidArgumentException( 'The review and sign-off binding refer to different Live Sessions.' );
        }
        if ( (string) ( $review['objective_evidence']['status'] ?? '' ) !== 'available' ) {
            throw new RuntimeException( 'Objective match evidence must be available before post-match review sign-off.' );
        }
        $review_match = trim( (string) ( $review['objective_evidence']['source_match_id'] ?? $session['source_match_id'] ?? '' ) );
        $binding_match = trim( (string) ( $binding['source_match_id'] ?? '' ) );
        if ( '' === $binding_match || $review_match !== $binding_match ) {
            throw new InvalidArgumentException( 'The review evidence does not match the bound fixture match identifier.' );
        }
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
        if ( function_exists( 'array_is_list' ) ) {
            return array_is_list( $value );
        }
        return array_keys( $value ) === range( 0, count( $value ) - 1 );
    }

    private static function json_encode( $value ): string {
        $json = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        if ( false === $json ) {
            throw new RuntimeException( 'Could not encode post-match review snapshot content.' );
        }
        return $json;
    }

    private static function normalize_uuid( string $value ): string {
        return preg_replace( '/[^a-zA-Z0-9\-]/', '', trim( $value ) ) ?: '';
    }
}
