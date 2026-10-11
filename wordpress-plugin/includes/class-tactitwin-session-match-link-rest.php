<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Session_Match_Link_REST {
    public static function register(): void {
        add_action( 'rest_api_init', array( self::class, 'routes' ) );
    }

    public static function routes(): void {
        register_rest_route(
            'tactitwin/v1',
            '/planning/live-sessions/(?P<session_uuid>[A-Za-z0-9\-]+)/matching-matches',
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array( self::class, 'matching_matches' ),
                'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
                'args' => array(
                    'session_uuid' => array( 'required'=>true, 'sanitize_callback'=>'sanitize_text_field' ),
                    'limit' => array( 'default'=>30, 'sanitize_callback'=>'absint' ),
                ),
            )
        );

        register_rest_route(
            'tactitwin/v1',
            '/planning/live-sessions/(?P<session_uuid>[A-Za-z0-9\-]+)/link-match',
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array( self::class, 'link_match' ),
                'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
                'args' => array(
                    'session_uuid' => array( 'required'=>true, 'sanitize_callback'=>'sanitize_text_field' ),
                    'match_id' => array(
                        'required'=>true,
                        'sanitize_callback'=>'absint',
                        'validate_callback'=>static fn( $value ): bool => (int) $value > 0,
                    ),
                ),
            )
        );
    }

    public static function matching_matches( WP_REST_Request $request ) {
        try {
            $session = ( new TactiTwin_Live_Session_Service() )->session( (string) $request['session_uuid'] );
            if ( empty( $session ) ) {
                throw new RuntimeException( 'Live session not found.' );
            }
            $matches = ( new TactiTwin_Session_Match_Link_Service() )->matching_matches(
                (int) $session['team_source_id'],
                (int) $session['opponent_source_id'],
                max( 1, min( 100, (int) ( $request['limit'] ?? 30 ) ) )
            );
            return new WP_REST_Response( array( 'ok'=>true, 'session'=>$session, 'matches'=>$matches ), 200 );
        } catch ( Throwable $e ) {
            return new WP_Error( 'tactitwin_matching_matches_failed', $e->getMessage(), array( 'status'=>422 ) );
        }
    }

    public static function link_match( WP_REST_Request $request ) {
        try {
            $result = ( new TactiTwin_Session_Match_Link_Service() )->link(
                (string) $request['session_uuid'],
                (int) $request['match_id']
            );
            return new WP_REST_Response( array( 'ok'=>true, 'result'=>$result ), 200 );
        } catch ( Throwable $e ) {
            return new WP_Error( 'tactitwin_link_match_failed', $e->getMessage(), array( 'status'=>422 ) );
        }
    }
}
