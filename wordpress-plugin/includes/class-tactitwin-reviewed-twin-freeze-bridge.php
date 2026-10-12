<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Reuses the exact Tactical Twin Lab payload reviewed by staff when generating
 * the matching Staff Brief. The existing Frozen Twin bridge remains the
 * deterministic fallback when no compatible reviewed result is available.
 */
final class TactiTwin_Reviewed_Twin_Freeze_Bridge {
    private const TWIN_RESULT_KEY = 'tactitwin_twin_lab_';

    public static function register(): void {
        add_action( 'admin_init', array( self::class, 'register_transient_filters' ), 19 );
    }

    public static function register_transient_filters(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $user_id = get_current_user_id();
        if ( $user_id <= 0 ) {
            return;
        }

        $hook = 'pre_set_transient_tactitwin_staff_brief_' . $user_id;
        add_filter( $hook, array( self::class, 'attach_reviewed_twin' ), 15, 2 );
        add_filter( $hook, array( self::class, 'annotate_source' ), 25, 2 );
    }

    public static function attach_reviewed_twin( $value, $expiration = 0 ) {
        if ( ! is_array( $value ) || ! empty( $value['approved_snapshot_uuid'] ) ) {
            return $value;
        }

        $brief = is_array( $value['brief'] ?? null ) ? $value['brief'] : array();
        if ( 'match-day-staff-brief' !== (string) ( $brief['staff_brief_id'] ?? '' ) ) {
            return $value;
        }
        if ( TactiTwin_Frozen_Twin_Handoff::verify_attachment( $brief ) ) {
            return $value;
        }

        $form = is_array( $value['form'] ?? null ) ? $value['form'] : array();
        $team_id = (int) ( $form['team_id'] ?? $brief['team']['source_id'] ?? 0 );
        $opponent_id = (int) ( $form['opponent_id'] ?? $brief['opponent']['source_id'] ?? 0 );
        $sample = max( 1, min( 10, (int) ( $form['sample'] ?? 5 ) ) );
        $language = TactiTwin_I18n::normalize_language( (string) ( $value['language'] ?? $brief['language'] ?? 'en' ) );
        $context = class_exists( 'TactiTwin_Planning_Context' ) ? TactiTwin_Planning_Context::current() : array();
        $reviewed = get_transient( self::TWIN_RESULT_KEY . get_current_user_id() );

        $lab = self::compatible_reviewed_lab(
            is_array( $reviewed ) ? $reviewed : array(),
            $team_id,
            $opponent_id,
            $sample,
            $language,
            is_array( $context ) ? $context : array()
        );
        if ( empty( $lab ) ) {
            return $value;
        }

        $brief = TactiTwin_Frozen_Twin_Handoff::attach( $brief, $lab );
        $brief['tactical_twin_preparation']['review_source'] = 'reviewed-twin-lab-transient';
        if ( ! is_array( $brief['method'] ?? null ) ) {
            $brief['method'] = array();
        }
        $brief['method']['tactical_twin_preparation_source'] = 'reviewed-twin-lab-transient';
        $value['brief'] = $brief;
        $value['tactical_twin_attachment_source'] = 'reviewed-twin-lab-transient';
        $value['tactical_twin_content_sha256'] = (string) ( $brief['tactical_twin_preparation']['content_sha256'] ?? '' );
        return $value;
    }

    public static function annotate_source( $value, $expiration = 0 ) {
        if ( ! is_array( $value ) ) {
            return $value;
        }
        $brief = is_array( $value['brief'] ?? null ) ? $value['brief'] : array();
        if ( ! TactiTwin_Frozen_Twin_Handoff::verify_attachment( $brief ) ) {
            return $value;
        }

        $source = trim( (string) ( $brief['tactical_twin_preparation']['review_source'] ?? '' ) );
        if ( '' === $source ) {
            $source = 'recomputed-fallback';
            $brief['tactical_twin_preparation']['review_source'] = $source;
            if ( ! is_array( $brief['method'] ?? null ) ) {
                $brief['method'] = array();
            }
            $brief['method']['tactical_twin_preparation_source'] = $source;
            $value['brief'] = $brief;
        }
        $value['tactical_twin_attachment_source'] = $source;
        return $value;
    }

    public static function compatible_reviewed_lab(
        array $twin_result,
        int $team_id,
        int $opponent_id,
        int $sample,
        string $language,
        array $current_context = array()
    ): array {
        if ( $team_id <= 0 || $opponent_id <= 0 || $team_id === $opponent_id ) {
            return array();
        }

        $lab = is_array( $twin_result['lab'] ?? null ) ? $twin_result['lab'] : array();
        if ( 'tactical-twin-lab' !== (string) ( $lab['lab_id'] ?? '' ) ) {
            return array();
        }
        if ( (string) $team_id !== (string) ( $lab['team']['source_id'] ?? '' ) ) {
            return array();
        }
        if ( (string) $opponent_id !== (string) ( $lab['opponent']['source_id'] ?? '' ) ) {
            return array();
        }

        $wanted_language = TactiTwin_I18n::normalize_language( $language );
        $stored_language = TactiTwin_I18n::normalize_language(
            (string) ( $twin_result['language'] ?? $lab['language'] ?? 'en' )
        );
        if ( $wanted_language !== $stored_language ) {
            return array();
        }

        $preparation = is_array( $twin_result['preparation'] ?? null ) ? $twin_result['preparation'] : array();
        $team_sample = (int) ( $preparation['team']['target_matches'] ?? $twin_result['sample'] ?? 0 );
        $opponent_sample = (int) ( $preparation['opponent']['target_matches'] ?? $twin_result['sample'] ?? 0 );
        if ( $sample !== $team_sample || $sample !== $opponent_sample ) {
            return array();
        }

        $current_fixture_key = trim( (string) ( $current_context['fixture_key'] ?? '' ) );
        if ( '' !== $current_fixture_key ) {
            $review_context = is_array( $twin_result['context'] ?? null ) ? $twin_result['context'] : array();
            $review_fixture_key = trim( (string) ( $review_context['fixture_key'] ?? '' ) );
            if ( '' === $review_fixture_key || ! hash_equals( $current_fixture_key, $review_fixture_key ) ) {
                return array();
            }
            if ( (string) $team_id !== (string) ( $current_context['team_source_id'] ?? '' ) ||
                (string) $opponent_id !== (string) ( $current_context['opponent_source_id'] ?? '' ) ) {
                return array();
            }
        }

        return $lab;
    }
}
