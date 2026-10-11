<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Match_Plan_REST {
    public static function register(): void {
        add_action( 'rest_api_init', array( self::class, 'routes' ) );
    }

    public static function routes(): void {
        register_rest_route(
            'tactitwin/v1',
            '/planning/match-plan',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( self::class, 'match_plan' ),
                'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
                'args'                => array(
                    'team_id' => array(
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( $value ): bool => (int) $value > 0,
                    ),
                    'opponent_id' => array(
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( $value ): bool => (int) $value > 0,
                    ),
                    'limit' => array(
                        'default'           => 5,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( $value ): bool => (int) $value >= 1 && (int) $value <= 10,
                    ),
                    'lang' => array(
                        'default'           => TactiTwin_I18n::current_language(),
                        'sanitize_callback' => 'sanitize_key',
                        'validate_callback' => static fn( $value ): bool => in_array(
                            TactiTwin_I18n::normalize_language( (string) $value ),
                            TactiTwin_I18n::supported(),
                            true
                        ),
                    ),
                ),
            )
        );
    }

    public static function match_plan( WP_REST_Request $request ) {
        try {
            $language = TactiTwin_I18n::normalize_language( (string) ( $request['lang'] ?? TactiTwin_I18n::current_language() ) );
            $result = ( new TactiTwin_Profile_Service() )->match_plan(
                (int) $request['team_id'],
                (int) $request['opponent_id'],
                (int) $request['limit'],
                $language
            );

            return new WP_REST_Response( array( 'ok' => true, 'result' => $result ), 200 );
        } catch ( Throwable $e ) {
            return new WP_Error( 'tactitwin_match_plan_failed', $e->getMessage(), array( 'status' => 422 ) );
        }
    }
}
