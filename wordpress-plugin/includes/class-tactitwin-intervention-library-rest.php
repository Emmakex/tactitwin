<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Intervention_Library_REST {
    public static function register(): void {
        add_action( 'rest_api_init', array( self::class, 'routes' ) );
    }

    public static function routes(): void {
        register_rest_route(
            'tactitwin/v1',
            '/planning/intervention-library',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( self::class, 'library' ),
                'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
                'args'                => array(
                    'team_id' => array(
                        'required'=>true,
                        'sanitize_callback'=>'absint',
                    ),
                    'scenario_id' => array(
                        'default'=>'',
                        'sanitize_callback'=>'sanitize_key',
                    ),
                    'lang' => array(
                        'default'=>'',
                        'sanitize_callback'=>'sanitize_key',
                    ),
                    'limit' => array(
                        'default'=>50,
                        'sanitize_callback'=>'absint',
                    ),
                ),
            )
        );
    }

    public static function library( WP_REST_Request $request ) {
        try {
            $language = trim( (string) ( $request['lang'] ?? '' ) );
            $result = ( new TactiTwin_Intervention_Library_Service() )->build(
                (int) $request['team_id'],
                '' === $language ? null : $language,
                (string) ( $request['scenario_id'] ?? '' ),
                (int) ( $request['limit'] ?? 50 )
            );
            return new WP_REST_Response( array( 'ok'=>true, 'result'=>$result ), 200 );
        } catch ( Throwable $e ) {
            return new WP_Error( 'tactitwin_intervention_library_failed', $e->getMessage(), array( 'status'=>422 ) );
        }
    }
}
