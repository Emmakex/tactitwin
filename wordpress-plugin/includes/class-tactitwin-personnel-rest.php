<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Personnel_REST {
    public static function register(): void {
        add_action( 'rest_api_init', array( self::class, 'routes' ) );
    }

    public static function routes(): void {
        register_rest_route(
            'tactitwin/v1',
            '/analysis/player/(?P<player_id>\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( self::class, 'player_profile' ),
                'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
                'args'                => array(
                    'player_id' => array(
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( $value ): bool => (int) $value > 0,
                    ),
                    'team_id' => array(
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( $value ): bool => (int) $value > 0,
                    ),
                    'limit' => array(
                        'default'           => 5,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( $value ): bool => (int) $value >= 1 && (int) $value <= 10,
                    ),
                ),
            )
        );

        register_rest_route(
            'tactitwin/v1',
            '/planning/personnel-what-if',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( self::class, 'personnel_what_if' ),
                'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
                'args'                => self::what_if_args(),
            )
        );
    }

    public static function player_profile( WP_REST_Request $request ) {
        try {
            $profile = ( new TactiTwin_Personnel_Service() )->player_profile(
                (int) $request['team_id'],
                (int) $request['player_id'],
                (int) $request['limit']
            );
            return new WP_REST_Response( array( 'ok' => true, 'result' => $profile ), 200 );
        } catch ( Throwable $e ) {
            return new WP_Error( 'tactitwin_player_profile_failed', $e->getMessage(), array( 'status' => 422 ) );
        }
    }

    public static function personnel_what_if( WP_REST_Request $request ) {
        try {
            $language = TactiTwin_I18n::normalize_language( (string) ( $request['lang'] ?? TactiTwin_I18n::current_language() ) );
            $result = ( new TactiTwin_Personnel_Service() )->personnel_what_if(
                (int) $request['team_id'],
                (int) $request['opponent_id'],
                (int) $request['player_out_id'],
                (int) $request['player_in_id'],
                (int) $request['minute'],
                (int) $request['our_score'],
                (int) $request['opponent_score'],
                (int) $request['our_players'],
                (int) $request['opponent_players'],
                (int) $request['limit'],
                $language
            );
            return new WP_REST_Response( array( 'ok' => true, 'result' => $result ), 200 );
        } catch ( Throwable $e ) {
            return new WP_Error( 'tactitwin_personnel_what_if_failed', $e->getMessage(), array( 'status' => 422 ) );
        }
    }

    private static function what_if_args(): array {
        return array(
            'team_id' => array(
                'required' => true, 'sanitize_callback' => 'absint',
                'validate_callback' => static fn( $value ): bool => (int) $value > 0,
            ),
            'opponent_id' => array(
                'required' => true, 'sanitize_callback' => 'absint',
                'validate_callback' => static fn( $value ): bool => (int) $value > 0,
            ),
            'player_out_id' => array(
                'required' => true, 'sanitize_callback' => 'absint',
                'validate_callback' => static fn( $value ): bool => (int) $value > 0,
            ),
            'player_in_id' => array(
                'required' => true, 'sanitize_callback' => 'absint',
                'validate_callback' => static fn( $value ): bool => (int) $value > 0,
            ),
            'minute' => array(
                'default' => 60, 'sanitize_callback' => 'absint',
                'validate_callback' => static fn( $value ): bool => (int) $value >= 1 && (int) $value <= 120,
            ),
            'our_score' => array(
                'default' => 0, 'sanitize_callback' => 'absint',
                'validate_callback' => static fn( $value ): bool => (int) $value >= 0 && (int) $value <= 20,
            ),
            'opponent_score' => array(
                'default' => 0, 'sanitize_callback' => 'absint',
                'validate_callback' => static fn( $value ): bool => (int) $value >= 0 && (int) $value <= 20,
            ),
            'our_players' => array(
                'default' => 11, 'sanitize_callback' => 'absint',
                'validate_callback' => static fn( $value ): bool => (int) $value >= 7 && (int) $value <= 11,
            ),
            'opponent_players' => array(
                'default' => 11, 'sanitize_callback' => 'absint',
                'validate_callback' => static fn( $value ): bool => (int) $value >= 7 && (int) $value <= 11,
            ),
            'limit' => array(
                'default' => 5, 'sanitize_callback' => 'absint',
                'validate_callback' => static fn( $value ): bool => (int) $value >= 1 && (int) $value <= 10,
            ),
            'lang' => array(
                'default' => TactiTwin_I18n::current_language(),
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => static fn( $value ): bool => in_array(
                    TactiTwin_I18n::normalize_language( (string) $value ),
                    TactiTwin_I18n::supported(),
                    true
                ),
            ),
        );
    }
}
