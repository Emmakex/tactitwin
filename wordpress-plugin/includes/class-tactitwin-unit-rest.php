<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Unit_REST {
    public static function register(): void {
        add_action( 'rest_api_init', array( self::class, 'routes' ) );
    }

    public static function routes(): void {
        register_rest_route(
            'tactitwin/v1',
            '/analysis/pair-interaction',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( self::class, 'pair_interaction' ),
                'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
                'args' => array(
                    'team_id' => array( 'required'=>true, 'sanitize_callback'=>'absint', 'validate_callback'=>static fn( $v ): bool => (int) $v > 0 ),
                    'player_a_id' => array( 'required'=>true, 'sanitize_callback'=>'absint', 'validate_callback'=>static fn( $v ): bool => (int) $v > 0 ),
                    'player_b_id' => array( 'required'=>true, 'sanitize_callback'=>'absint', 'validate_callback'=>static fn( $v ): bool => (int) $v > 0 ),
                    'limit' => array( 'default'=>5, 'sanitize_callback'=>'absint', 'validate_callback'=>static fn( $v ): bool => (int) $v >= 1 && (int) $v <= 10 ),
                ),
            )
        );

        register_rest_route(
            'tactitwin/v1',
            '/analysis/unit-network',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( self::class, 'unit_network' ),
                'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
            )
        );

        register_rest_route(
            'tactitwin/v1',
            '/analysis/unit-network-compare',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( self::class, 'unit_network_compare' ),
                'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
            )
        );
    }

    public static function pair_interaction( WP_REST_Request $request ) {
        try {
            $result = ( new TactiTwin_Unit_Service() )->pair_profile(
                (int) $request['team_id'],
                (int) $request['player_a_id'],
                (int) $request['player_b_id'],
                (int) $request['limit']
            );
            return new WP_REST_Response( array( 'ok'=>true, 'result'=>$result ), 200 );
        } catch ( Throwable $e ) {
            return new WP_Error( 'tactitwin_pair_interaction_failed', $e->getMessage(), array( 'status'=>422 ) );
        }
    }

    public static function unit_network( WP_REST_Request $request ) {
        try {
            $ids = self::player_ids( $request->get_param( 'player_ids' ) );
            $result = ( new TactiTwin_Unit_Service() )->lineup_network(
                absint( $request->get_param( 'team_id' ) ),
                $ids,
                max( 1, min( 10, absint( $request->get_param( 'limit' ) ?: 5 ) ) )
            );
            return new WP_REST_Response( array( 'ok'=>true, 'result'=>$result ), 200 );
        } catch ( Throwable $e ) {
            return new WP_Error( 'tactitwin_unit_network_failed', $e->getMessage(), array( 'status'=>422 ) );
        }
    }

    public static function unit_network_compare( WP_REST_Request $request ) {
        try {
            $language = TactiTwin_I18n::normalize_language( (string) ( $request->get_param( 'lang' ) ?: TactiTwin_I18n::current_language() ) );
            $raw = ( new TactiTwin_Unit_Service() )->compare_lineup_networks(
                absint( $request->get_param( 'team_id' ) ),
                self::player_ids( $request->get_param( 'baseline_player_ids' ) ),
                self::player_ids( $request->get_param( 'candidate_player_ids' ) ),
                max( 1, min( 10, absint( $request->get_param( 'limit' ) ?: 5 ) ) )
            );
            $result = TactiTwin_Unit_Comparison::interpret( $raw, $language );
            return new WP_REST_Response( array( 'ok'=>true, 'result'=>$result ), 200 );
        } catch ( Throwable $e ) {
            return new WP_Error( 'tactitwin_unit_network_compare_failed', $e->getMessage(), array( 'status'=>422 ) );
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
