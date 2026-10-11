<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Contingency_REST {
    public static function register(): void {
        add_action( 'rest_api_init', array( self::class, 'routes' ) );
    }

    public static function routes(): void {
        register_rest_route(
            'tactitwin/v1',
            '/planning/contingency-playbook',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( self::class, 'playbook' ),
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
                    'our_score' => array(
                        'default'           => 0,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( $value ): bool => (int) $value >= 0 && (int) $value <= 20,
                    ),
                    'opponent_score' => array(
                        'default'           => 0,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( $value ): bool => (int) $value >= 0 && (int) $value <= 20,
                    ),
                    'our_players' => array(
                        'default'           => 11,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( $value ): bool => (int) $value >= 7 && (int) $value <= 11,
                    ),
                    'opponent_players' => array(
                        'default'           => 11,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( $value ): bool => (int) $value >= 7 && (int) $value <= 11,
                    ),
                    'minute' => array(
                        'default'           => 1,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( $value ): bool => (int) $value >= 1 && (int) $value <= 120,
                    ),
                    'limit' => array(
                        'default'           => 5,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( $value ): bool => (int) $value >= 1 && (int) $value <= 10,
                    ),
                    'precedent_limit' => array(
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

    public static function playbook( WP_REST_Request $request ) {
        try {
            $language = TactiTwin_I18n::normalize_language( (string) ( $request['lang'] ?? TactiTwin_I18n::current_language() ) );
            $result = ( new TactiTwin_Precedent_Aware_Contingency_Service() )->evaluate(
                (int) $request['team_id'],
                (int) $request['opponent_id'],
                (int) $request['our_score'],
                (int) $request['opponent_score'],
                (int) $request['minute'],
                (int) $request['limit'],
                $language,
                (int) $request['our_players'],
                (int) $request['opponent_players'],
                (int) $request['precedent_limit']
            );

            return new WP_REST_Response( array( 'ok' => true, 'result' => $result ), 200 );
        } catch ( Throwable $e ) {
            return new WP_Error( 'tactitwin_contingency_playbook_failed', $e->getMessage(), array( 'status' => 422 ) );
        }
    }
}
