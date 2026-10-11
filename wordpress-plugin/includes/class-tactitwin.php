<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin {
    private static ?TactiTwin $instance = null;

    public static function instance(): TactiTwin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    public static function activate(): void {
        update_option( 'tactitwin_version', TACTITWIN_VERSION );
    }

    public function register_admin_menu(): void {
        add_menu_page(
            __( 'TactiTwin', 'tactitwin' ),
            __( 'TactiTwin', 'tactitwin' ),
            'manage_options',
            'tactitwin',
            array( $this, 'render_admin_page' ),
            'dashicons-chart-area',
            58
        );
    }

    public function register_rest_routes(): void {
        register_rest_route(
            'tactitwin/v1',
            '/health',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_health' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function rest_health(): WP_REST_Response {
        return new WP_REST_Response(
            array(
                'name'    => 'TactiTwin',
                'version' => TACTITWIN_VERSION,
                'status'  => 'ok',
                'phase'   => 'core-data-layer',
            ),
            200
        );
    }

    public function render_admin_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'TactiTwin', 'tactitwin' ); ?></h1>
            <p><?php echo esc_html__( 'Football Tactical Intelligence & Match Simulation', 'tactitwin' ); ?></p>

            <h2><?php echo esc_html__( 'Bootstrap status', 'tactitwin' ); ?></h2>
            <ul>
                <li><?php echo esc_html__( 'WordPress plugin: ready', 'tactitwin' ); ?></li>
                <li><?php echo esc_html__( 'REST health endpoint: ready', 'tactitwin' ); ?></li>
                <li><?php echo esc_html__( 'Normalized data layer: next', 'tactitwin' ); ?></li>
                <li><?php echo esc_html__( 'Public data connectors: next', 'tactitwin' ); ?></li>
                <li><?php echo esc_html__( 'Scenario engine: planned', 'tactitwin' ); ?></li>
            </ul>
        </div>
        <?php
    }
}
