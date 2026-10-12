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
        add_action( 'admin_init', array( $this, 'maybe_upgrade_schema' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 5 );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_action( 'admin_post_tactitwin_import_competition', array( $this, 'admin_import_competition' ) );
        add_action( 'admin_post_tactitwin_import_match', array( $this, 'admin_import_match' ) );
    }

    public static function activate(): void {
        TactiTwin_DB::install();
        update_option( 'tactitwin_version', TACTITWIN_VERSION );
    }

    public function maybe_upgrade_schema(): void {
        if ( TactiTwin_DB::SCHEMA_VERSION !== get_option( 'tactitwin_schema_version' ) ) {
            TactiTwin_DB::install();
        }
    }

    public function register_admin_menu(): void {
        add_menu_page(
            __( 'TactiTwin', 'tactitwin' ),
            __( 'TactiTwin', 'tactitwin' ),
            'manage_options',
            'tactitwin',
            array( TactiTwin_Match_Workspace_Admin::class, 'render' ),
            'dashicons-chart-area',
            58
        );

        add_submenu_page(
            'tactitwin',
            __( 'Match Workspace', 'tactitwin' ),
            __( 'Match Workspace', 'tactitwin' ),
            'manage_options',
            'tactitwin',
            array( TactiTwin_Match_Workspace_Admin::class, 'render' )
        );

        add_submenu_page(
            'tactitwin',
            __( 'Data & Imports', 'tactitwin' ),
            __( 'Data & Imports', 'tactitwin' ),
            'manage_options',
            'tactitwin-data',
            array( $this, 'render_admin_page' )
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

        register_rest_route(
            'tactitwin/v1',
            '/data/status',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_data_status' ),
                'permission_callback' => array( $this, 'rest_manage_options' ),
            )
        );

        register_rest_route(
            'tactitwin/v1',
            '/imports/statsbomb/competition-season',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rest_import_competition' ),
                'permission_callback' => array( $this, 'rest_manage_options' ),
                'args'                => array(
                    'competition_id' => array(
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( $value ): bool => (int) $value > 0,
                    ),
                    'season_id'      => array(
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( $value ): bool => (int) $value > 0,
                    ),
                ),
            )
        );

        register_rest_route(
            'tactitwin/v1',
            '/imports/statsbomb/match',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rest_import_match' ),
                'permission_callback' => array( $this, 'rest_manage_options' ),
                'args'                => array(
                    'match_id' => array(
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( $value ): bool => (int) $value > 0,
                    ),
                ),
            )
        );
    }

    public function rest_manage_options(): bool {
        return current_user_can( 'manage_options' );
    }

    public function rest_health(): WP_REST_Response {
        return new WP_REST_Response(
            array(
                'name'           => 'TactiTwin',
                'version'        => TACTITWIN_VERSION,
                'schema_version' => TactiTwin_DB::SCHEMA_VERSION,
                'status'         => 'ok',
                'phase'          => 'core-data-layer',
            ),
            200
        );
    }

    public function rest_data_status(): WP_REST_Response {
        return new WP_REST_Response(
            array(
                'schema_version' => get_option( 'tactitwin_schema_version' ),
                'counts'         => TactiTwin_DB::counts(),
            ),
            200
        );
    }

    public function rest_import_competition( WP_REST_Request $request ) {
        try {
            $result = ( new TactiTwin_StatsBomb_Importer() )->import_competition_season(
                (int) $request['competition_id'],
                (int) $request['season_id']
            );

            return new WP_REST_Response( array( 'ok' => true, 'result' => $result ), 200 );
        } catch ( Throwable $e ) {
            return new WP_Error( 'tactitwin_import_failed', $e->getMessage(), array( 'status' => 502 ) );
        }
    }

    public function rest_import_match( WP_REST_Request $request ) {
        try {
            $result = ( new TactiTwin_StatsBomb_Importer() )->import_match_events( (int) $request['match_id'] );

            return new WP_REST_Response( array( 'ok' => true, 'result' => $result ), 200 );
        } catch ( Throwable $e ) {
            return new WP_Error( 'tactitwin_import_failed', $e->getMessage(), array( 'status' => 502 ) );
        }
    }

    public function admin_import_competition(): void {
        $this->guard_admin_action( 'tactitwin_import_competition' );

        $competition_id = absint( $_POST['competition_id'] ?? 0 );
        $season_id      = absint( $_POST['season_id'] ?? 0 );

        try {
            $result = ( new TactiTwin_StatsBomb_Importer() )->import_competition_season( $competition_id, $season_id );
            $this->set_admin_notice(
                'success',
                sprintf(
                    'Imported %s %s: %d matches and %d teams.',
                    $result['competition'],
                    $result['season'],
                    $result['matches'],
                    $result['teams']
                )
            );
        } catch ( Throwable $e ) {
            $this->set_admin_notice( 'error', $e->getMessage() );
        }

        $this->redirect_admin();
    }

    public function admin_import_match(): void {
        $this->guard_admin_action( 'tactitwin_import_match' );

        $match_id = absint( $_POST['match_id'] ?? 0 );

        try {
            $result = ( new TactiTwin_StatsBomb_Importer() )->import_match_events( $match_id );
            $this->set_admin_notice(
                'success',
                sprintf(
                    'Imported match %d: %d events, %d players and %d event types.',
                    $match_id,
                    $result['events'],
                    $result['players'],
                    $result['event_types']
                )
            );
        } catch ( Throwable $e ) {
            $this->set_admin_notice( 'error', $e->getMessage() );
        }

        $this->redirect_admin();
    }

    private function guard_admin_action( string $action ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage TactiTwin.', 'tactitwin' ) );
        }

        check_admin_referer( $action );
    }

    private function set_admin_notice( string $type, string $message ): void {
        set_transient(
            'tactitwin_notice_' . get_current_user_id(),
            array( 'type' => $type, 'message' => $message ),
            MINUTE_IN_SECONDS
        );
    }

    private function redirect_admin(): void {
        wp_safe_redirect( admin_url( 'admin.php?page=tactitwin-data' ) );
        exit;
    }

    public function render_admin_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $counts = TactiTwin_DB::counts();
        $notice = get_transient( 'tactitwin_notice_' . get_current_user_id() );
        if ( $notice ) {
            delete_transient( 'tactitwin_notice_' . get_current_user_id() );
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'TactiTwin Data & Imports', 'tactitwin' ); ?></h1>
            <p><?php echo esc_html__( 'Manage the open-data layer used by the Match Workspace and tactical engines.', 'tactitwin' ); ?></p>
            <p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=tactitwin' ) ); ?>"><?php echo esc_html__( 'Back to Match Workspace', 'tactitwin' ); ?></a></p>

            <?php if ( is_array( $notice ) ) : ?>
                <div class="notice notice-<?php echo esc_attr( 'error' === $notice['type'] ? 'error' : 'success' ); ?> is-dismissible">
                    <p><?php echo esc_html( $notice['message'] ); ?></p>
                </div>
            <?php endif; ?>

            <h2><?php echo esc_html__( 'Data layer', 'tactitwin' ); ?></h2>
            <table class="widefat striped" style="max-width:760px">
                <tbody>
                    <?php foreach ( $counts as $entity => $count ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( ucfirst( $entity ) ); ?></strong></td>
                            <td><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2><?php echo esc_html__( 'StatsBomb Open Data import', 'tactitwin' ); ?></h2>
            <p><?php echo esc_html__( 'Import the competition/season first, then import events for a selected match.', 'tactitwin' ); ?></p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:20px 0; padding:16px; background:#fff; max-width:760px">
                <input type="hidden" name="action" value="tactitwin_import_competition">
                <?php wp_nonce_field( 'tactitwin_import_competition' ); ?>
                <h3><?php echo esc_html__( '1. Import competition and season', 'tactitwin' ); ?></h3>
                <p>
                    <label><?php echo esc_html__( 'Competition ID', 'tactitwin' ); ?>
                        <input type="number" name="competition_id" min="1" value="9" required>
                    </label>
                    &nbsp;
                    <label><?php echo esc_html__( 'Season ID', 'tactitwin' ); ?>
                        <input type="number" name="season_id" min="1" value="281" required>
                    </label>
                </p>
                <?php submit_button( __( 'Import competition', 'tactitwin' ), 'secondary', 'submit', false ); ?>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:20px 0; padding:16px; background:#fff; max-width:760px">
                <input type="hidden" name="action" value="tactitwin_import_match">
                <?php wp_nonce_field( 'tactitwin_import_match' ); ?>
                <h3><?php echo esc_html__( '2. Import match events', 'tactitwin' ); ?></h3>
                <p>
                    <label><?php echo esc_html__( 'Match ID', 'tactitwin' ); ?>
                        <input type="number" name="match_id" min="1" value="3895292" required>
                    </label>
                </p>
                <?php submit_button( __( 'Import match events', 'tactitwin' ), 'primary', 'submit', false ); ?>
            </form>

            <p>
                <?php echo esc_html__( 'Open-data source: StatsBomb / Hudl. Any analysis or published insight based on this dataset must retain the required source attribution.', 'tactitwin' ); ?>
            </p>
        </div>
        <?php
    }
}
