<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Adds the v1.35 per-side coverage contract to freshly generated Match Plans
 * without changing Match Plan tactical composition.
 */
final class TactiTwin_Evidence_Coverage_Transient_Bridge {
    public static function register(): void {
        add_action( 'admin_init', array( self::class, 'register_filter' ), 19 );
    }

    public static function register_filter(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $user_id = get_current_user_id();
        if ( $user_id <= 0 ) {
            return;
        }
        add_filter(
            'pre_set_transient_tactitwin_match_plan_' . $user_id,
            array( self::class, 'enrich' ),
            15,
            2
        );
    }

    public static function enrich( $value, $expiration = 0 ) {
        if ( ! is_array( $value ) ) {
            return $value;
        }

        $plan = is_array( $value['plan'] ?? null ) ? $value['plan'] : array();
        $preparation = is_array( $value['preparation'] ?? null ) ? $value['preparation'] : array();
        if ( empty( $plan ) || empty( $preparation['team'] ) || empty( $preparation['opponent'] ) ) {
            return $value;
        }

        $language = TactiTwin_I18n::normalize_language( (string) ( $value['language'] ?? $plan['language'] ?? 'en' ) );
        $team_profile = self::profile_from_preparation(
            $preparation['team'],
            (string) ( $plan['team']['name'] ?? $plan['team']['team_name'] ?? '' )
        );
        $opponent_profile = self::profile_from_preparation(
            $preparation['opponent'],
            (string) ( $plan['opponent']['name'] ?? $plan['opponent']['team_name'] ?? '' )
        );

        $coverage = TactiTwin_Evidence_Coverage::compose( $team_profile, $opponent_profile, $language );
        $coverage['status_label'] = TactiTwin_I18n::level_label( (string) $coverage['status'], $language );
        $plan['coverage'] = $coverage;
        if ( ! is_array( $plan['method'] ?? null ) ) {
            $plan['method'] = array();
        }
        $plan['method']['coverage_transparency'] = 'v1.35-per-side-auditable-samples';

        $value['plan'] = $plan;
        $value['coverage_transparency'] = 'v1.35-per-side-auditable-samples';
        return $value;
    }

    private static function profile_from_preparation( array $side, string $name ): array {
        $ids = array();
        foreach ( is_array( $side['existing'] ?? null ) ? $side['existing'] : array() as $match_id ) {
            $ids[] = (string) $match_id;
        }
        foreach ( is_array( $side['imported'] ?? null ) ? $side['imported'] : array() as $imported ) {
            if ( is_array( $imported ) && isset( $imported['match_id'] ) ) {
                $ids[] = (string) $imported['match_id'];
            }
        }
        $ids = array_values( array_unique( array_filter( $ids, 'strlen' ) ) );
        $dates = self::match_dates( $ids );
        $matches = (int) ( $side['ready_matches'] ?? count( $ids ) );

        return array(
            'team_source_id' => (string) ( $side['team_id'] ?? '' ),
            'team_name' => $name,
            'coverage' => array(
                'matches' => $matches,
                'match_ids' => $ids,
                'oldest_match' => empty( $dates ) ? null : min( $dates ),
                'newest_match' => empty( $dates ) ? null : max( $dates ),
                'confidence' => self::status( $matches ),
            ),
        );
    }

    private static function match_dates( array $ids ): array {
        if ( empty( $ids ) ) {
            return array();
        }

        global $wpdb;
        $table = TactiTwin_DB::table( 'matches' );
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
        $args = array_merge( array( TactiTwin_StatsBomb_Normalizer::SOURCE ), $ids );
        $sql = "SELECT match_date FROM {$table} WHERE source = %s AND source_match_id IN ({$placeholders})"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $dates = $wpdb->get_col( $wpdb->prepare( $sql, ...$args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return array_values( array_filter( array_map( 'strval', is_array( $dates ) ? $dates : array() ) ) );
    }

    private static function status( int $matches ): string {
        if ( $matches >= 5 ) {
            return 'high';
        }
        if ( $matches >= 3 ) {
            return 'medium';
        }
        return 'low';
    }
}
