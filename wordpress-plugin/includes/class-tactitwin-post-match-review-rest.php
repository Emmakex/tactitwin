<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Post_Match_Review_REST {
    public static function register(): void {
        add_action( 'rest_api_init', array( self::class, 'routes' ) );
    }

    public static function routes(): void {
        register_rest_route(
            'tactitwin/v1',
            '/planning/live-sessions/(?P<session_uuid>[A-Za-z0-9\-]+)/review',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( self::class, 'review' ),
                'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
                'args'                => array(
                    'session_uuid' => array( 'required'=>true, 'sanitize_callback'=>'sanitize_text_field' ),
                    'lang' => array(
                        'default'=>'',
                        'sanitize_callback'=>'sanitize_key',
                    ),
                ),
            )
        );
    }

    public static function review( WP_REST_Request $request ) {
        try {
            $language = trim( (string) ( $request['lang'] ?? '' ) );
            $result = ( new TactiTwin_Post_Match_Review_Service() )->review(
                (string) $request['session_uuid'],
                '' === $language ? null : $language
            );
            return new WP_REST_Response( array( 'ok'=>true, 'result'=>$result ), 200 );
        } catch ( Throwable $e ) {
            return new WP_Error( 'tactitwin_post_match_review_failed', $e->getMessage(), array( 'status'=>422 ) );
        }
    }
}
