<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Workspace_Admin {
    public static function register(): void {
        add_action( 'admin_menu', array( self::class, 'menu' ) );
        add_action( 'admin_post_tactitwin_prepare_scenario', array( self::class, 'prepare_scenario' ) );
    }

    public static function menu(): void {
        add_submenu_page(
            'tactitwin',
            __( 'Scenario Lab', 'tactitwin' ),
            __( 'Scenario Lab', 'tactitwin' ),
            'manage_options',
            'tactitwin-scenario-lab',
            array( self::class, 'render' )
        );
    }

    public static function prepare_scenario(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage TactiTwin.', 'tactitwin' ) );
        }

        check_admin_referer( 'tactitwin_prepare_scenario' );

        $team_id     = absint( $_POST['team_id'] ?? 0 );
        $opponent_id = absint( $_POST['opponent_id'] ?? 0 );
        $sample      = max( 1, min( 10, absint( $_POST['sample'] ?? 5 ) ) );

        try {
            $preparation = ( new TactiTwin_Data_Preparation_Service() )->prepare_matchup( $team_id, $opponent_id, $sample );
            $scenario    = ( new TactiTwin_Profile_Service() )->build_up_vs_high_press( $team_id, $opponent_id, $sample );

            set_transient(
                'tactitwin_scenario_' . get_current_user_id(),
                array(
                    'preparation' => $preparation,
                    'scenario'    => $scenario,
                ),
                10 * MINUTE_IN_SECONDS
            );
        } catch ( Throwable $e ) {
            set_transient(
                'tactitwin_scenario_error_' . get_current_user_id(),
                $e->getMessage(),
                MINUTE_IN_SECONDS
            );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=tactitwin-scenario-lab' ) );
        exit;
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $teams  = self::teams();
        $user   = get_current_user_id();
        $result = get_transient( 'tactitwin_scenario_' . $user );
        $error  = get_transient( 'tactitwin_scenario_error_' . $user );

        if ( $error ) {
            delete_transient( 'tactitwin_scenario_error_' . $user );
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'TactiTwin Scenario Lab', 'tactitwin' ); ?></h1>
            <p><?php echo esc_html__( 'Prepare a data-backed matchup and evaluate how your build-up may behave against an opponent high press.', 'tactitwin' ); ?></p>

            <?php if ( $error ) : ?>
                <div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
            <?php endif; ?>

            <?php if ( count( $teams ) < 2 ) : ?>
                <div class="notice notice-warning inline">
                    <p><?php echo esc_html__( 'Import a competition/season first so at least two teams are available.', 'tactitwin' ); ?></p>
                </div>
            <?php else : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;padding:20px;max-width:900px;border:1px solid #dcdcde;">
                    <input type="hidden" name="action" value="tactitwin_prepare_scenario">
                    <?php wp_nonce_field( 'tactitwin_prepare_scenario' ); ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th><label for="tt-team"><?php echo esc_html__( 'Our team', 'tactitwin' ); ?></label></th>
                            <td><?php self::team_select( 'team_id', 'tt-team', $teams ); ?></td>
                        </tr>
                        <tr>
                            <th><label for="tt-opponent"><?php echo esc_html__( 'Opponent', 'tactitwin' ); ?></label></th>
                            <td><?php self::team_select( 'opponent_id', 'tt-opponent', $teams, 1 ); ?></td>
                        </tr>
                        <tr>
                            <th><label for="tt-sample"><?php echo esc_html__( 'Recent matches', 'tactitwin' ); ?></label></th>
                            <td>
                                <input id="tt-sample" name="sample" type="number" min="1" max="10" value="5">
                                <p class="description"><?php echo esc_html__( 'TactiTwin will automatically import missing event data until this sample is reached where possible.', 'tactitwin' ); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( __( 'Prepare and simulate', 'tactitwin' ) ); ?>
                </form>
            <?php endif; ?>

            <?php if ( is_array( $result ) && ! empty( $result['scenario'] ) ) : ?>
                <?php self::render_result( $result ); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_result( array $result ): void {
        $scenario = $result['scenario'];
        $risk     = $scenario['risk'];
        $opp      = $scenario['opportunity'];
        $coverage = $scenario['coverage'];
        $evidence = $scenario['evidence'];
        $recs     = $scenario['recommendations'];
        ?>
        <hr style="margin:32px 0;">
        <h2><?php echo esc_html( $scenario['team']['name'] . ' vs ' . $scenario['opponent']['name'] ); ?></h2>
        <p><strong><?php echo esc_html__( 'Scenario:', 'tactitwin' ); ?></strong> <?php echo esc_html( $scenario['scenario_name'] ); ?></p>

        <?php if ( ! empty( $coverage['warning'] ) ) : ?>
            <div class="notice notice-warning inline"><p><?php echo esc_html( $coverage['warning'] ); ?></p></div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;max-width:900px;margin:18px 0;">
            <?php self::card( __( 'Risk', 'tactitwin' ), $risk['score'] . '/100', strtoupper( $risk['level'] ) ); ?>
            <?php self::card( __( 'Opportunity', 'tactitwin' ), $opp['score'] . '/100', strtoupper( $opp['level'] ) ); ?>
            <?php self::card( __( 'Coverage', 'tactitwin' ), (string) $coverage['effective_matches'], strtoupper( $coverage['status'] ) ); ?>
            <?php self::card( __( 'Build-up completion', 'tactitwin' ), $evidence['our_build_up_completion_pct'] . '%', '' ); ?>
            <?php self::card( __( 'Build-up escapes', 'tactitwin' ), $evidence['our_build_up_escape_rate'] . '%', '' ); ?>
            <?php self::card( __( 'Opponent high pressures', 'tactitwin' ), (string) $evidence['opponent_high_pressures_per_match'], __( 'per match', 'tactitwin' ) ); ?>
        </div>

        <h3><?php echo esc_html__( 'Recommended response', 'tactitwin' ); ?></h3>
        <ol style="max-width:900px;background:#fff;padding:20px 20px 20px 38px;border:1px solid #dcdcde;">
            <li><?php echo esc_html( $recs['primary'] ); ?></li>
            <li><?php echo esc_html( $recs['secondary'] ); ?></li>
            <li><?php echo esc_html( $recs['attacking_outlet'] ); ?></li>
        </ol>

        <details style="max-width:900px;margin-top:18px;">
            <summary><strong><?php echo esc_html__( 'Technical evidence', 'tactitwin' ); ?></strong></summary>
            <pre style="white-space:pre-wrap;background:#fff;padding:16px;border:1px solid #dcdcde;"><?php echo esc_html( wp_json_encode( $scenario, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre>
        </details>
        <?php
    }

    private static function card( string $title, string $value, string $meta ): void {
        ?>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;">
            <div style="font-size:12px;text-transform:uppercase;color:#646970;"><?php echo esc_html( $title ); ?></div>
            <div style="font-size:28px;font-weight:700;line-height:1.2;margin-top:4px;"><?php echo esc_html( $value ); ?></div>
            <?php if ( '' !== $meta ) : ?><div style="margin-top:4px;color:#646970;"><?php echo esc_html( $meta ); ?></div><?php endif; ?>
        </div>
        <?php
    }

    private static function team_select( string $name, string $id, array $teams, int $default_index = 0 ): void {
        ?>
        <select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" required>
            <?php foreach ( $teams as $index => $team ) : ?>
                <option value="<?php echo esc_attr( $team['source_team_id'] ); ?>" <?php selected( $index, $default_index ); ?>>
                    <?php echo esc_html( $team['team_name'] ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    private static function teams(): array {
        global $wpdb;

        $table = TactiTwin_DB::table( 'teams' );
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT source_team_id, team_name FROM {$table} WHERE source = %s ORDER BY team_name ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                TactiTwin_StatsBomb_Normalizer::SOURCE
            ),
            ARRAY_A
        ) ?: array();
    }
}
