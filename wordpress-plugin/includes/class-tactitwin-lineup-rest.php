<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Lineup_REST {
    public static function register(): void {
        add_action( 'rest_api_init', array( self::class, 'routes' ) );
    }

    public static function routes(): void {
        register_rest_route(
            'tactitwin/v1',
            '/planning/recent-xi',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( self::class, 'recent_xi' ),
                'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
                'args'                => array(
                    'team_id' => array(
                        'required' => true,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( $value ): bool => (int) $value > 0,
                    ),
                ),
            )
        );

        register_rest_route(
            'tactitwin/v1',
            '/planning/lineup-what-if',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( self::class, 'lineup_what_if' ),
                'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
            )
        );
    }

    public static function recent_xi( WP_REST_Request $request ) {
        try {
            $result = ( new TactiTwin_Lineup_Service() )->recent_starting_lineup( (int) $request['team_id'] );
            return new WP_REST_Response( array( 'ok' => true, 'result' => $result ), 200 );
        } catch ( Throwable $e ) {
            return new WP_Error( 'tactitwin_recent_xi_failed', $e->getMessage(), array( 'status' => 422 ) );
        }
    }

    public static function lineup_what_if( WP_REST_Request $request ) {
        try {
            $baseline = self::player_ids( $request->get_param( 'baseline_player_ids' ) );
            $candidate = self::player_ids( $request->get_param( 'candidate_player_ids' ) );
            $language = TactiTwin_I18n::normalize_language( (string) ( $request->get_param( 'lang' ) ?: TactiTwin_I18n::current_language() ) );

            $result = ( new TactiTwin_Lineup_Service() )->lineup_what_if(
                absint( $request->get_param( 'team_id' ) ),
                absint( $request->get_param( 'opponent_id' ) ),
                $baseline,
                $candidate,
                sanitize_text_field( (string) $request->get_param( 'baseline_formation' ) ),
                sanitize_text_field( (string) $request->get_param( 'candidate_formation' ) ),
                max( 1, min( 120, absint( $request->get_param( 'minute' ) ?: 60 ) ) ),
                min( 20, absint( $request->get_param( 'our_score' ) ) ),
                min( 20, absint( $request->get_param( 'opponent_score' ) ) ),
                max( 7, min( 11, absint( $request->get_param( 'our_players' ) ?: 11 ) ) ),
                max( 7, min( 11, absint( $request->get_param( 'opponent_players' ) ?: 11 ) ) ),
                max( 1, min( 10, absint( $request->get_param( 'limit' ) ?: 5 ) ) ),
                $language
            );

            return new WP_REST_Response( array( 'ok' => true, 'result' => $result ), 200 );
        } catch ( Throwable $e ) {
            return new WP_Error( 'tactitwin_lineup_what_if_failed', $e->getMessage(), array( 'status' => 422 ) );
        }
    }

    private static function player_ids( mixed $value ): array {
        if ( is_string( $value ) ) {
            $decoded = json_decode( $value, true );
            $value = is_array( $decoded ) ? $decoded : preg_split( '/\s*,\s*/', $value );
        }
        if ( ! is_array( $value ) ) {
            return array();
        }
        return array_values(
            array_filter(
                array_map( 'absint', $value ),
                static fn( $id ): bool => (int) $id > 0
            )
        );
    }
}
