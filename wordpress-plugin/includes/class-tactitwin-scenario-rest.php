<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Scenario_REST {
    public static function register(): void {
        add_action( 'rest_api_init', array( self::class, 'routes' ) );
    }

    public static function routes(): void {
        register_rest_route(
            'tactitwin/v1',
            '/analysis/team/(?P<team_id>\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( self::class, 'team_profile' ),
                'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
                'args'                => array(
                    'team_id' => array(
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

        foreach (
            array(
                'build-up-vs-high-press'          => 'high_press',
                'opponent-side-bias'              => 'side_bias',
                'defensive-transition-after-loss' => 'defensive_transition',
                'attack-vs-low-block'             => 'low_block',
                'full-matchup-scan'               => 'full_scan',
            ) as $route => $callback
        ) {
            register_rest_route(
                'tactitwin/v1',
                '/scenarios/' . $route,
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( self::class, $callback ),
                    'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
                    'args'                => self::matchup_args(),
                )
            );
        }
    }

    public static function team_profile( WP_REST_Request $request ) {
        try {
            $profile = ( new TactiTwin_Profile_Service() )->team_profile(
                (int) $request['team_id'],
                (int) $request['limit']
            );
            return new WP_REST_Response( array( 'ok' => true, 'result' => $profile ), 200 );
        } catch ( Throwable $e ) {
            return new WP_Error( 'tactitwin_profile_failed', $e->getMessage(), array( 'status' => 422 ) );
        }
    }

    public static function high_press( WP_REST_Request $request ) {
        return self::scenario_response(
            static fn() => ( new TactiTwin_Profile_Service() )->build_up_vs_high_press(
                (int) $request['team_id'],
                (int) $request['opponent_id'],
                (int) $request['limit'],
                self::request_language( $request )
            )
        );
    }

    public static function side_bias( WP_REST_Request $request ) {
        return self::scenario_response(
            static fn() => ( new TactiTwin_Profile_Service() )->opponent_side_bias(
                (int) $request['team_id'],
                (int) $request['opponent_id'],
                (int) $request['limit'],
                self::request_language( $request )
            )
        );
    }

    public static function defensive_transition( WP_REST_Request $request ) {
        return self::scenario_response(
            static fn() => ( new TactiTwin_Profile_Service() )->defensive_transition_after_loss(
                (int) $request['team_id'],
                (int) $request['opponent_id'],
                (int) $request['limit'],
                self::request_language( $request )
            )
        );
    }

    public static function low_block( WP_REST_Request $request ) {
        return self::scenario_response(
            static fn() => ( new TactiTwin_Profile_Service() )->attack_vs_low_block(
                (int) $request['team_id'],
                (int) $request['opponent_id'],
                (int) $request['limit'],
                self::request_language( $request )
            )
        );
    }

    public static function full_scan( WP_REST_Request $request ) {
        return self::scenario_response(
            static fn() => ( new TactiTwin_Profile_Service() )->full_matchup_scan(
                (int) $request['team_id'],
                (int) $request['opponent_id'],
                (int) $request['limit'],
                self::request_language( $request )
            )
        );
    }

    private static function request_language( WP_REST_Request $request ): string {
        return TactiTwin_I18n::normalize_language( (string) ( $request['lang'] ?? TactiTwin_I18n::current_language() ) );
    }

    private static function scenario_response( callable $callback ) {
        try {
            return new WP_REST_Response( array( 'ok' => true, 'result' => $callback() ), 200 );
        } catch ( Throwable $e ) {
            return new WP_Error( 'tactitwin_scenario_failed', $e->getMessage(), array( 'status' => 422 ) );
        }
    }

    private static function matchup_args(): array {
        return array(
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
        );
    }
}
