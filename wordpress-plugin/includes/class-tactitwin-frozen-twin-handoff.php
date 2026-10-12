<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Attaches the exact generated Tactical Twin Lab payload to a Staff Brief so
 * the existing approved Staff Brief snapshot/hash chain freezes it without a
 * parallel persistence model.
 */
final class TactiTwin_Frozen_Twin_Handoff {
    public static function attach( array $staff_brief, array $twin_lab ): array {
        self::assert_staff_brief( $staff_brief );
        self::assert_twin_lab( $twin_lab );
        self::assert_same_matchup( $staff_brief, $twin_lab );

        $digest = self::digest( $twin_lab );
        $staff_brief['tactical_twin_preparation'] = array(
            'attachment_id' => 'frozen-tactical-twin-preparation',
            'content_sha256' => $digest,
            'twin_lab' => $twin_lab,
            'signature' => self::lab_signature( $twin_lab, $digest ),
            'method' => array(
                'type' => 'staff-brief-frozen-tactical-twin-attachment',
                'immutable_after_staff_brief_approval' => true,
                'recalculation_on_live_handoff' => false,
                'uses_existing_staff_brief_snapshot_hash' => true,
                'creates_parallel_persistence' => false,
                'changes_tactical_scores' => false,
                'changes_response_ranking' => false,
                'automatic_response_activation' => false,
            ),
        );

        if ( ! is_array( $staff_brief['method'] ?? null ) ) {
            $staff_brief['method'] = array();
        }
        $staff_brief['method']['tactical_twin_preparation_included'] = true;
        $staff_brief['method']['tactical_twin_recalculated_on_handoff'] = false;

        return $staff_brief;
    }

    public static function verify_attachment( array $staff_brief ): bool {
        $attachment = is_array( $staff_brief['tactical_twin_preparation'] ?? null )
            ? $staff_brief['tactical_twin_preparation']
            : array();
        if ( 'frozen-tactical-twin-preparation' !== (string) ( $attachment['attachment_id'] ?? '' ) ) {
            return false;
        }
        $lab = is_array( $attachment['twin_lab'] ?? null ) ? $attachment['twin_lab'] : array();
        try {
            self::assert_twin_lab( $lab );
            self::assert_same_matchup( $staff_brief, $lab );
        } catch ( Throwable $e ) {
            return false;
        }
        $expected = strtolower( trim( (string) ( $attachment['content_sha256'] ?? '' ) ) );
        if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $expected ) || ! hash_equals( $expected, self::digest( $lab ) ) ) {
            return false;
        }
        $signature = is_array( $attachment['signature'] ?? null ) ? $attachment['signature'] : array();
        return $signature === self::lab_signature( $lab, $expected );
    }

    public static function signature( array $staff_brief ): array {
        $attachment = is_array( $staff_brief['tactical_twin_preparation'] ?? null )
            ? $staff_brief['tactical_twin_preparation']
            : array();
        if ( empty( $attachment ) ) {
            return array();
        }
        if ( ! self::verify_attachment( $staff_brief ) ) {
            return array(
                'integrity_valid' => false,
                'content_sha256' => strtolower( trim( (string) ( $attachment['content_sha256'] ?? '' ) ) ),
            );
        }
        $signature = is_array( $attachment['signature'] ?? null ) ? $attachment['signature'] : array();
        return array_merge( array( 'integrity_valid' => true ), $signature );
    }

    public static function twin_lab( array $staff_brief ): array {
        if ( ! self::verify_attachment( $staff_brief ) ) {
            return array();
        }
        return $staff_brief['tactical_twin_preparation']['twin_lab'];
    }

    private static function lab_signature( array $lab, string $digest ): array {
        $switches = is_array( $lab['response_switch_playbook']['items'] ?? null )
            ? $lab['response_switch_playbook']['items']
            : array();
        $switch_map = array();
        foreach ( $switches as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $branch_id = (string) ( $item['branch_id'] ?? '' );
            if ( '' === $branch_id ) {
                continue;
            }
            $switch_map[ $branch_id ] = array(
                'decision' => (string) ( $item['decision'] ?? '' ),
                'target_response_id' => (string) ( $item['target_response']['response_id'] ?? '' ),
                'state_fit_margin' => is_numeric( $item['state_fit_margin'] ?? null ) ? (float) $item['state_fit_margin'] : null,
                'coverage_caution' => ! empty( $item['coverage_caution'] ),
            );
        }
        ksort( $switch_map );

        return array(
            'content_sha256' => strtolower( $digest ),
            'top_response_id' => (string) ( $lab['preparation_summary']['top_response_id'] ?? '' ),
            'most_robust_response_id' => (string) ( $lab['preparation_summary']['most_robust_response_id'] ?? '' ),
            'switch_candidate_count' => (int) ( $lab['preparation_summary']['switch_candidate_count'] ?? 0 ),
            'coverage_caution_switches' => (int) ( $lab['preparation_summary']['coverage_caution_switches'] ?? 0 ),
            'switch_map' => $switch_map,
        );
    }

    private static function assert_staff_brief( array $staff_brief ): void {
        if ( 'match-day-staff-brief' !== (string) ( $staff_brief['staff_brief_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'Frozen Twin handoff requires a Match-Day Staff Brief.' );
        }
    }

    private static function assert_twin_lab( array $twin_lab ): void {
        if ( 'tactical-twin-lab' !== (string) ( $twin_lab['lab_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'Frozen Twin handoff requires a Tactical Twin Lab result.' );
        }
        if ( 'response-switch-playbook' !== (string) ( $twin_lab['response_switch_playbook']['playbook_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'Tactical Twin Lab has no valid Response Switch Playbook.' );
        }
        if ( empty( $twin_lab['response_matrix'] ) || empty( $twin_lab['scenario_tree'] ) || empty( $twin_lab['response_robustness'] ) ) {
            throw new InvalidArgumentException( 'Tactical Twin Lab is incomplete.' );
        }
    }

    private static function assert_same_matchup( array $staff_brief, array $twin_lab ): void {
        $brief_team = (string) ( $staff_brief['team']['source_id'] ?? '' );
        $brief_opponent = (string) ( $staff_brief['opponent']['source_id'] ?? '' );
        $lab_team = (string) ( $twin_lab['team']['source_id'] ?? '' );
        $lab_opponent = (string) ( $twin_lab['opponent']['source_id'] ?? '' );
        if ( '' === $brief_team || '' === $brief_opponent || $brief_team !== $lab_team || $brief_opponent !== $lab_opponent ) {
            throw new InvalidArgumentException( 'Staff Brief and Tactical Twin Lab must describe the same matchup.' );
        }
    }

    private static function digest( array $value ): string {
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
        return array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
    }

    private static function json_encode( $value ): string {
        if ( function_exists( 'wp_json_encode' ) ) {
            return (string) wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION );
        }
        return (string) json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION );
    }
}
