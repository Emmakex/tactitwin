<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Live_Session_REST {
    public static function register(): void {
        add_action( 'rest_api_init', array( self::class, 'routes' ) );
    }

    public static function routes(): void {
        register_rest_route(
            'tactitwin/v1',
            '/planning/live-sessions/update',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( self::class, 'update_session' ),
                'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
                'args'                => array_merge(
                    self::context_args(),
                    array(
                        'session_uuid' => array(
                            'default' => '',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'source_match_id' => array(
                            'default' => '',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'session_title' => array(
                            'default' => '',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'observed_high_press' => self::boolean_arg(),
                        'observed_low_block' => self::boolean_arg(),
                        'observed_transition_threat' => self::boolean_arg(),
                        'observed_side_bias' => self::boolean_arg(),
                        'observed_weak_side_open' => self::boolean_arg(),
                        'observation_note' => array(
                            'default' => '',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                    )
                ),
            )
        );

        register_rest_route(
            'tactitwin/v1',
            '/planning/live-sessions/(?P<session_uuid>[A-Za-z0-9\-]+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( self::class, 'get_session' ),
                'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
                'args'                => array(
                    'session_uuid' => array( 'required'=>true, 'sanitize_callback'=>'sanitize_text_field' ),
                ),
            )
        );

        register_rest_route(
            'tactitwin/v1',
            '/planning/live-sessions/(?P<session_uuid>[A-Za-z0-9\-]+)/close',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( self::class, 'close_session' ),
                'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
                'args'                => array(
                    'session_uuid' => array( 'required'=>true, 'sanitize_callback'=>'sanitize_text_field' ),
                ),
            )
        );

        register_rest_route(
            'tactitwin/v1',
            '/planning/live-sessions',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( self::class, 'list_sessions' ),
                'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
                'args'                => array(
                    'limit' => array(
                        'default'=>20,
                        'sanitize_callback'=>'absint',
                        'validate_callback'=>static fn( $value ): bool => (int) $value >= 1 && (int) $value <= 100,
                    ),
                    'team_id' => array(
                        'default'=>0,
                        'sanitize_callback'=>'absint',
                    ),
                ),
            )
        );
    }

    public static function update_session( WP_REST_Request $request ) {
        try {
            $language = TactiTwin_I18n::normalize_language( (string) ( $request['lang'] ?? TactiTwin_I18n::current_language() ) );
            $observations = self::observations( $request );
            $profile_service = new TactiTwin_Profile_Service();
            $playbook = $profile_service->live_contingency_playbook(
                (int) $request['team_id'],
                (int) $request['opponent_id'],
                (int) $request['our_score'],
                (int) $request['opponent_score'],
                (int) $request['minute'],
                $observations,
                (int) $request['limit'],
                $language,
                (int) $request['our_players'],
                (int) $request['opponent_players']
            );

            $service = new TactiTwin_Live_Session_Service();
            $session = $service->ensure_session(
                (string) ( $request['session_uuid'] ?? '' ),
                (int) $request['team_id'],
                (int) $request['opponent_id'],
                $language,
                get_current_user_id(),
                (string) ( $request['source_match_id'] ?? '' ),
                (string) ( $request['session_title'] ?? '' )
            );
            $persisted = $service->append_snapshot( (string) $session['session_uuid'], $playbook, get_current_user_id() );

            return new WP_REST_Response(
                array(
                    'ok'=>true,
                    'result'=>array(
                        'playbook'=>$playbook,
                        'session'=>$persisted['session'],
                        'snapshot'=>$persisted['snapshot'],
                        'timeline'=>$persisted['timeline'],
                    ),
                ),
                200
            );
        } catch ( Throwable $e ) {
            return new WP_Error( 'tactitwin_live_session_update_failed', $e->getMessage(), array( 'status'=>422 ) );
        }
    }

    public static function get_session( WP_REST_Request $request ) {
        try {
            $timeline = ( new TactiTwin_Live_Session_Service() )->timeline( (string) $request['session_uuid'] );
            return new WP_REST_Response( array( 'ok'=>true, 'result'=>$timeline ), 200 );
        } catch ( Throwable $e ) {
            return new WP_Error( 'tactitwin_live_session_not_found', $e->getMessage(), array( 'status'=>404 ) );
        }
    }

    public static function close_session( WP_REST_Request $request ) {
        try {
            $result = ( new TactiTwin_Live_Session_Service() )->close_session( (string) $request['session_uuid'] );
            return new WP_REST_Response( array( 'ok'=>true, 'result'=>$result ), 200 );
        } catch ( Throwable $e ) {
            return new WP_Error( 'tactitwin_live_session_close_failed', $e->getMessage(), array( 'status'=>422 ) );
        }
    }

    public static function list_sessions( WP_REST_Request $request ): WP_REST_Response {
        $team_id = (int) ( $request['team_id'] ?? 0 );
        $rows = ( new TactiTwin_Live_Session_Service() )->recent_sessions(
            (int) ( $request['limit'] ?? 20 ),
            $team_id > 0 ? $team_id : null
        );
        return new WP_REST_Response( array( 'ok'=>true, 'result'=>$rows ), 200 );
    }

    private static function observations( WP_REST_Request $request ): array {
        return array(
            'high_press' => rest_sanitize_boolean( $request['observed_high_press'] ?? false ),
            'low_block' => rest_sanitize_boolean( $request['observed_low_block'] ?? false ),
            'transition_threat' => rest_sanitize_boolean( $request['observed_transition_threat'] ?? false ),
            'side_bias' => rest_sanitize_boolean( $request['observed_side_bias'] ?? false ),
            'weak_side_open' => rest_sanitize_boolean( $request['observed_weak_side_open'] ?? false ),
            'note' => sanitize_text_field( (string) ( $request['observation_note'] ?? '' ) ),
        );
    }

    private static function context_args(): array {
        return array(
            'team_id' => array(
                'required'=>true,
                'sanitize_callback'=>'absint',
                'validate_callback'=>static fn( $value ): bool => (int) $value > 0,
            ),
            'opponent_id' => array(
                'required'=>true,
                'sanitize_callback'=>'absint',
                'validate_callback'=>static fn( $value ): bool => (int) $value > 0,
            ),
            'our_score' => array('default'=>0,'sanitize_callback'=>'absint','validate_callback'=>static fn( $value ): bool => (int) $value >= 0 && (int) $value <= 20),
            'opponent_score' => array('default'=>0,'sanitize_callback'=>'absint','validate_callback'=>static fn( $value ): bool => (int) $value >= 0 && (int) $value <= 20),
            'our_players' => array('default'=>11,'sanitize_callback'=>'absint','validate_callback'=>static fn( $value ): bool => (int) $value >= 7 && (int) $value <= 11),
            'opponent_players' => array('default'=>11,'sanitize_callback'=>'absint','validate_callback'=>static fn( $value ): bool => (int) $value >= 7 && (int) $value <= 11),
            'minute' => array('default'=>1,'sanitize_callback'=>'absint','validate_callback'=>static fn( $value ): bool => (int) $value >= 1 && (int) $value <= 120),
            'limit' => array('default'=>5,'sanitize_callback'=>'absint','validate_callback'=>static fn( $value ): bool => (int) $value >= 1 && (int) $value <= 10),
            'lang' => array(
                'default'=>TactiTwin_I18n::current_language(),
                'sanitize_callback'=>'sanitize_key',
                'validate_callback'=>static fn( $value ): bool => in_array( TactiTwin_I18n::normalize_language( (string) $value ), TactiTwin_I18n::supported(), true ),
            ),
        );
    }

    private static function boolean_arg(): array {
        return array( 'default'=>false, 'sanitize_callback'=>'rest_sanitize_boolean' );
    }
}
