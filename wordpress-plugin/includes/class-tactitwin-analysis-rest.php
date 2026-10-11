<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Analysis_REST {
    public static function register(): void {
        add_action( 'rest_api_init', array( self::class, 'routes' ) );
    }

    public static function routes(): void {
        register_rest_route(
            'tactitwin/v1',
            '/analysis/match/(?P<match_id>\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( self::class, 'match_summary' ),
                'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
                'args'                => array(
                    'match_id' => array(
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( $value ): bool => (int) $value > 0,
                    ),
                ),
            )
        );
    }

    public static function match_summary( WP_REST_Request $request ) {
        try {
            $summary = ( new TactiTwin_Analysis_Service() )->match_summary( (int) $request['match_id'] );
            return new WP_REST_Response( array( 'ok' => true, 'result' => $summary ), 200 );
        } catch ( Throwable $e ) {
            return new WP_Error( 'tactitwin_analysis_failed', $e->getMessage(), array( 'status' => 422 ) );
        }
    }
}
