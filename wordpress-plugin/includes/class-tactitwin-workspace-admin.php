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
        $scenario_id = sanitize_key( $_POST['scenario_id'] ?? 'full-matchup-scan' );
        $language    = TactiTwin_I18n::normalize_language( sanitize_key( $_POST['language'] ?? TactiTwin_I18n::current_language() ) );

        try {
            $preparation = ( new TactiTwin_Data_Preparation_Service() )->prepare_matchup( $team_id, $opponent_id, $sample );
            $service     = new TactiTwin_Profile_Service();

            switch ( $scenario_id ) {
                case 'opponent-side-bias':
                    $scenario = $service->opponent_side_bias( $team_id, $opponent_id, $sample, $language );
                    break;
                case 'defensive-transition-after-loss':
                    $scenario = $service->defensive_transition_after_loss( $team_id, $opponent_id, $sample, $language );
                    break;
                case 'attack-vs-low-block':
                    $scenario = $service->attack_vs_low_block( $team_id, $opponent_id, $sample, $language );
                    break;
                case 'exploit-opponent-weak-side':
                    $scenario = $service->exploit_opponent_weak_side( $team_id, $opponent_id, $sample, $language );
                    break;
                case 'build-up-vs-high-press':
                    $scenario = $service->build_up_vs_high_press( $team_id, $opponent_id, $sample, $language );
                    break;
                default:
                    $scenario = $service->full_matchup_scan( $team_id, $opponent_id, $sample, $language );
                    break;
            }

            set_transient(
                'tactitwin_scenario_' . get_current_user_id(),
                array(
                    'preparation' => $preparation,
                    'scenario'    => $scenario,
                    'language'    => $language,
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

        $teams    = self::teams();
        $user     = get_current_user_id();
        $result   = get_transient( 'tactitwin_scenario_' . $user );
        $error    = get_transient( 'tactitwin_scenario_error_' . $user );
        $language = is_array( $result ) && ! empty( $result['language'] )
            ? TactiTwin_I18n::normalize_language( (string) $result['language'] )
            : TactiTwin_I18n::current_language();

        if ( $error ) {
            delete_transient( 'tactitwin_scenario_error_' . $user );
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'TactiTwin Scenario Lab', 'tactitwin' ); ?></h1>
            <p><?php echo esc_html__( 'Prepare a data-backed matchup, choose a tactical question and receive an explainable scenario assessment.', 'tactitwin' ); ?></p>

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
                            <th><label for="tt-language"><?php echo esc_html__( 'Language', 'tactitwin' ); ?></label></th>
                            <td>
                                <select name="language" id="tt-language">
                                    <option value="es" <?php selected( $language, 'es' ); ?>>Español</option>
                                    <option value="en" <?php selected( $language, 'en' ); ?>>English</option>
                                </select>
                                <p class="description"><?php echo esc_html__( 'Controls scenario names, explanations and tactical recommendations.', 'tactitwin' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="tt-scenario"><?php echo esc_html__( 'Scenario', 'tactitwin' ); ?></label></th>
                            <td>
                                <select name="scenario_id" id="tt-scenario">
                                    <option value="full-matchup-scan"><?php echo esc_html__( 'Full Matchup Scan', 'tactitwin' ); ?></option>
                                    <option value="build-up-vs-high-press"><?php echo esc_html__( 'Build-up vs High Press', 'tactitwin' ); ?></option>
                                    <option value="opponent-side-bias"><?php echo esc_html__( 'Opponent Side Bias', 'tactitwin' ); ?></option>
                                    <option value="defensive-transition-after-loss"><?php echo esc_html__( 'Defensive Transition After Loss', 'tactitwin' ); ?></option>
                                    <option value="attack-vs-low-block"><?php echo esc_html__( 'Attack vs Low Block', 'tactitwin' ); ?></option>
                                    <option value="exploit-opponent-weak-side"><?php echo esc_html__( 'Exploit Opponent Weak Side', 'tactitwin' ); ?></option>
                                </select>
                            </td>
                        </tr>
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
        $language = TactiTwin_I18n::normalize_language( (string) ( $scenario['language'] ?? $result['language'] ?? 'en' ) );
        ?>
        <hr style="margin:32px 0;">
        <h2><?php echo esc_html( $scenario['team']['name'] . ' vs ' . $scenario['opponent']['name'] ); ?></h2>
        <p><strong><?php echo esc_html__( 'Scenario:', 'tactitwin' ); ?></strong> <?php echo esc_html( $scenario['scenario_name'] ); ?></p>

        <?php if ( ! empty( $coverage['warning'] ) ) : ?>
            <div class="notice notice-warning inline"><p><?php echo esc_html( $coverage['warning'] ); ?></p></div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;max-width:900px;margin:18px 0;">
            <?php self::card( __( 'Risk', 'tactitwin' ), $risk['score'] . '/100', strtoupper( (string) ( $risk['level_label'] ?? TactiTwin_I18n::level_label( $risk['level'], $language ) ) ) ); ?>
            <?php self::card( __( 'Opportunity', 'tactitwin' ), $opp['score'] . '/100', strtoupper( (string) ( $opp['level_label'] ?? TactiTwin_I18n::level_label( $opp['level'], $language ) ) ) ); ?>
            <?php self::card( __( 'Coverage', 'tactitwin' ), (string) $coverage['effective_matches'], strtoupper( TactiTwin_I18n::level_label( (string) $coverage['status'], $language ) ) ); ?>
            <?php self::scenario_cards( $scenario, $language ); ?>
        </div>

        <h3><?php echo esc_html__( 'Recommended response', 'tactitwin' ); ?></h3>
        <ol style="max-width:900px;background:#fff;padding:20px 20px 20px 38px;border:1px solid #dcdcde;">
            <?php foreach ( $scenario['recommendations'] as $recommendation ) : ?>
                <li><?php echo esc_html( $recommendation ); ?></li>
            <?php endforeach; ?>
        </ol>

        <?php if ( 'full-matchup-scan' === ( $scenario['scenario_id'] ?? '' ) ) : ?>
            <?php self::render_scan_ranking( $scenario, $language ); ?>
        <?php endif; ?>

        <details style="max-width:900px;margin-top:18px;">
            <summary><strong><?php echo esc_html__( 'Technical evidence', 'tactitwin' ); ?></strong></summary>
            <pre style="white-space:pre-wrap;background:#fff;padding:16px;border:1px solid #dcdcde;"><?php echo esc_html( wp_json_encode( $scenario, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre>
        </details>
        <?php
    }

    private static function render_scan_ranking( array $scenario, string $language ): void {
        $ranking = $scenario['evidence']['scenario_ranking'] ?? array();
        if ( empty( $ranking ) ) {
            return;
        }
        ?>
        <h3><?php echo esc_html__( 'Scenario priority ranking', 'tactitwin' ); ?></h3>
        <table class="widefat striped" style="max-width:900px;">
            <thead><tr><th>#</th><th><?php echo esc_html__( 'Scenario', 'tactitwin' ); ?></th><th><?php echo esc_html__( 'Risk', 'tactitwin' ); ?></th><th><?php echo esc_html__( 'Opportunity', 'tactitwin' ); ?></th></tr></thead>
            <tbody>
            <?php foreach ( $ranking as $row ) : ?>
                <tr>
                    <td><?php echo esc_html( (string) $row['rank'] ); ?></td>
                    <td><?php echo esc_html( $row['scenario_name'] ); ?></td>
                    <td><?php echo esc_html( $row['risk_score'] . '/100 ' . strtoupper( (string) ( $row['risk_level_label'] ?? TactiTwin_I18n::level_label( $row['risk_level'], $language ) ) ) ); ?></td>
                    <td><?php echo esc_html( $row['opportunity_score'] . '/100' ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private static function scenario_cards( array $scenario, string $language ): void {
        $evidence    = $scenario['evidence'] ?? array();
        $scenario_id = (string) ( $scenario['scenario_id'] ?? '' );

        if ( 'full-matchup-scan' === $scenario_id ) {
            self::card( __( 'Top priority', 'tactitwin' ), (string) ( $scenario['risk']['top_scenario'] ?? '-' ), __( 'highest risk scenario', 'tactitwin' ) );
            self::card( __( 'Average risk', 'tactitwin' ), ( $scenario['risk']['average_score'] ?? 0 ) . '/100', '' );
            self::card( __( 'Scenarios scanned', 'tactitwin' ), (string) ( $evidence['scenario_count'] ?? 0 ), '' );
            return;
        }

        if ( 'opponent-side-bias' === $scenario_id ) {
            self::card( __( 'Opponent dominant channel', 'tactitwin' ), ucfirst( TactiTwin_I18n::channel_label( (string) ( $evidence['opponent_dominant_channel'] ?? 'central' ), $language ) ), '' );
            self::card( __( 'Our vulnerable channel', 'tactitwin' ), ucfirst( TactiTwin_I18n::channel_label( (string) ( $evidence['our_most_vulnerable_channel'] ?? 'central' ), $language ) ), '' );
            self::card( __( 'Preferred trap channel', 'tactitwin' ), ucfirst( TactiTwin_I18n::channel_label( (string) ( $evidence['opponent_weak_channel'] ?? 'central' ), $language ) ), '' );
            return;
        }

        if ( 'defensive-transition-after-loss' === $scenario_id ) {
            self::card( __( 'Dangerous loss rate', 'tactitwin' ), ( $evidence['our_dangerous_turnover_rate'] ?? 0 ) . '%', __( 'shot conceded ≤10s', 'tactitwin' ) );
            self::card( __( 'Counterpress recovery', 'tactitwin' ), ( $evidence['our_counterpress_recovery_rate'] ?? 0 ) . '%', __( 'recovery ≤8s', 'tactitwin' ) );
            self::card( __( 'Main loss zone', 'tactitwin' ), ucfirst( TactiTwin_I18n::zone_label( (string) ( $evidence['dominant_loss_zone'] ?? 'middle_third' ), $language ) ), '' );
            self::card( __( 'Opponent transition xG', 'tactitwin' ), (string) ( $evidence['opponent_transition_xg_per_match'] ?? 0 ), __( 'per match ≤10s', 'tactitwin' ) );
            return;
        }

        if ( 'attack-vs-low-block' === $scenario_id ) {
            self::card( __( 'Deep-defence proxy', 'tactitwin' ), ( $evidence['opponent_deep_defensive_action_share_pct'] ?? 0 ) . '%', __( 'defensive actions in own third', 'tactitwin' ) );
            self::card( __( 'Box entries', 'tactitwin' ), (string) ( $evidence['our_box_entries_per_match'] ?? 0 ), __( 'our per match', 'tactitwin' ) );
            self::card( __( 'Box shot share', 'tactitwin' ), ( $evidence['our_box_shot_share_pct'] ?? 0 ) . '%', '' );
            self::card( __( 'xG per shot', 'tactitwin' ), (string) ( $evidence['our_xg_per_shot'] ?? 0 ), '' );
            self::card( __( 'Opponent concession channel', 'tactitwin' ), ucfirst( TactiTwin_I18n::channel_label( (string) ( $evidence['opponent_most_conceded_channel'] ?? 'central' ), $language ) ), '' );
            return;
        }

        if ( 'exploit-opponent-weak-side' === $scenario_id ) {
            self::card( __( 'Best matchup channel', 'tactitwin' ), ucfirst( TactiTwin_I18n::channel_label( (string) ( $evidence['best_matchup_channel'] ?? 'central' ), $language ) ), '' );
            self::card( __( 'Opponent most conceded channel', 'tactitwin' ), ucfirst( TactiTwin_I18n::channel_label( (string) ( $evidence['opponent_most_conceded_channel'] ?? 'central' ), $language ) ), '' );
            self::card( __( 'Our dominant channel', 'tactitwin' ), ucfirst( TactiTwin_I18n::channel_label( (string) ( $evidence['our_dominant_channel'] ?? 'central' ), $language ) ), '' );
            self::card( __( 'Switching gain', 'tactitwin' ), (string) ( $evidence['potential_switching_gain_points'] ?? 0 ), __( 'matchup points', 'tactitwin' ) );
            self::card( __( 'Channel balance', 'tactitwin' ), ( $evidence['channel_balance_score'] ?? 0 ) . '/100', '' );
            return;
        }

        self::card( __( 'Build-up completion', 'tactitwin' ), ( $evidence['our_build_up_completion_pct'] ?? 0 ) . '%', '' );
        self::card( __( 'Build-up escapes', 'tactitwin' ), ( $evidence['our_build_up_escape_rate'] ?? 0 ) . '%', '' );
        self::card( __( 'Opponent high pressures', 'tactitwin' ), (string) ( $evidence['opponent_high_pressures_per_match'] ?? 0 ), __( 'per match', 'tactitwin' ) );
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
