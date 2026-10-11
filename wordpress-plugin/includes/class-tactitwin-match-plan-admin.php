<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Match_Plan_Admin {
    public static function register(): void {
        add_action( 'admin_menu', array( self::class, 'menu' ) );
        add_action( 'admin_post_tactitwin_generate_match_plan', array( self::class, 'generate' ) );
    }

    public static function menu(): void {
        add_submenu_page(
            'tactitwin',
            __( 'Match Plan', 'tactitwin' ),
            __( 'Match Plan', 'tactitwin' ),
            'manage_options',
            'tactitwin-match-plan',
            array( self::class, 'render' )
        );
    }

    public static function generate(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage TactiTwin.', 'tactitwin' ) );
        }

        check_admin_referer( 'tactitwin_generate_match_plan' );

        $team_id     = absint( $_POST['team_id'] ?? 0 );
        $opponent_id = absint( $_POST['opponent_id'] ?? 0 );
        $sample      = max( 1, min( 10, absint( $_POST['sample'] ?? 5 ) ) );
        $language    = TactiTwin_I18n::normalize_language( sanitize_key( $_POST['language'] ?? TactiTwin_I18n::current_language() ) );

        try {
            $preparation = ( new TactiTwin_Data_Preparation_Service() )->prepare_matchup( $team_id, $opponent_id, $sample );
            $plan        = ( new TactiTwin_Profile_Service() )->match_plan( $team_id, $opponent_id, $sample, $language );

            set_transient(
                'tactitwin_match_plan_' . get_current_user_id(),
                array(
                    'preparation' => $preparation,
                    'plan'        => $plan,
                    'language'    => $language,
                ),
                10 * MINUTE_IN_SECONDS
            );
        } catch ( Throwable $e ) {
            set_transient(
                'tactitwin_match_plan_error_' . get_current_user_id(),
                $e->getMessage(),
                MINUTE_IN_SECONDS
            );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=tactitwin-match-plan' ) );
        exit;
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $teams    = self::teams();
        $user     = get_current_user_id();
        $result   = get_transient( 'tactitwin_match_plan_' . $user );
        $error    = get_transient( 'tactitwin_match_plan_error_' . $user );
        $language = is_array( $result ) && ! empty( $result['language'] )
            ? TactiTwin_I18n::normalize_language( (string) $result['language'] )
            : TactiTwin_I18n::current_language();

        if ( $error ) {
            delete_transient( 'tactitwin_match_plan_error_' . $user );
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'TactiTwin Match Plan', 'tactitwin' ); ?></h1>
            <p><?php echo esc_html__( 'Turn the full tactical scan into a phase-by-phase match plan for the coaching staff.', 'tactitwin' ); ?></p>

            <?php if ( $error ) : ?>
                <div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
            <?php endif; ?>

            <?php if ( count( $teams ) < 2 ) : ?>
                <div class="notice notice-warning inline"><p><?php echo esc_html__( 'Import a competition/season first so at least two teams are available.', 'tactitwin' ); ?></p></div>
            <?php else : ?>
                <?php self::render_form( $teams, $language ); ?>
            <?php endif; ?>

            <?php if ( is_array( $result ) && ! empty( $result['plan'] ) ) : ?>
                <?php self::render_plan( $result['plan'] ); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_form( array $teams, string $language ): void {
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;padding:20px;max-width:900px;border:1px solid #dcdcde;">
            <input type="hidden" name="action" value="tactitwin_generate_match_plan">
            <?php wp_nonce_field( 'tactitwin_generate_match_plan' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th><label for="tt-plan-language"><?php echo esc_html__( 'Language', 'tactitwin' ); ?></label></th>
                    <td>
                        <select name="language" id="tt-plan-language">
                            <option value="es" <?php selected( $language, 'es' ); ?>>Español</option>
                            <option value="en" <?php selected( $language, 'en' ); ?>>English</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="tt-plan-team"><?php echo esc_html__( 'Our team', 'tactitwin' ); ?></label></th>
                    <td><?php self::team_select( 'team_id', 'tt-plan-team', $teams ); ?></td>
                </tr>
                <tr>
                    <th><label for="tt-plan-opponent"><?php echo esc_html__( 'Opponent', 'tactitwin' ); ?></label></th>
                    <td><?php self::team_select( 'opponent_id', 'tt-plan-opponent', $teams, 1 ); ?></td>
                </tr>
                <tr>
                    <th><label for="tt-plan-sample"><?php echo esc_html__( 'Recent matches', 'tactitwin' ); ?></label></th>
                    <td>
                        <input id="tt-plan-sample" name="sample" type="number" min="1" max="10" value="5">
                        <p class="description"><?php echo esc_html__( 'Missing event-data matches are imported automatically where available.', 'tactitwin' ); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Generate match plan', 'tactitwin' ) ); ?>
        </form>
        <?php
    }

    private static function render_plan( array $plan ): void {
        $language = TactiTwin_I18n::normalize_language( (string) ( $plan['language'] ?? 'en' ) );
        $summary  = $plan['summary'] ?? array();
        ?>
        <hr style="margin:32px 0;">
        <div style="max-width:1000px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;">
                <div>
                    <h2 style="margin-bottom:4px;"><?php echo esc_html( ( $plan['team']['name'] ?? '' ) . ' vs ' . ( $plan['opponent']['name'] ?? '' ) ); ?></h2>
                    <p style="font-size:16px;margin-top:0;"><?php echo esc_html( (string) ( $summary['headline'] ?? '' ) ); ?></p>
                </div>
                <button type="button" class="button" onclick="window.print();"><?php echo esc_html( self::label( 'print', $language ) ); ?></button>
            </div>

            <?php if ( ! empty( $plan['coverage']['warning'] ) ) : ?>
                <div class="notice notice-warning inline"><p><?php echo esc_html( $plan['coverage']['warning'] ); ?></p></div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin:18px 0;">
                <?php self::card( self::label( 'top_risk', $language ), (string) ( $summary['highest_risk_scenario']['scenario_name'] ?? '-' ), ( $summary['highest_risk_scenario']['score'] ?? 0 ) . '/100' ); ?>
                <?php self::card( self::label( 'best_opportunity', $language ), (string) ( $summary['best_opportunity_scenario']['scenario_name'] ?? '-' ), ( $summary['best_opportunity_scenario']['score'] ?? 0 ) . '/100' ); ?>
                <?php self::card( self::label( 'average_risk', $language ), ( $summary['average_risk'] ?? 0 ) . '/100', '' ); ?>
                <?php self::card( self::label( 'coverage', $language ), (string) ( $plan['coverage']['effective_matches'] ?? 0 ), strtoupper( (string) ( $plan['coverage']['status_label'] ?? '' ) ) ); ?>
            </div>

            <h3><?php echo esc_html( self::label( 'priorities', $language ) ); ?></h3>
            <table class="widefat striped">
                <thead><tr><th>#</th><th><?php echo esc_html( self::label( 'scenario', $language ) ); ?></th><th><?php echo esc_html( self::label( 'priority', $language ) ); ?></th><th><?php echo esc_html( self::label( 'instruction', $language ) ); ?></th></tr></thead>
                <tbody>
                    <?php foreach ( $plan['priorities'] ?? array() as $priority ) : ?>
                        <tr>
                            <td><?php echo esc_html( (string) $priority['rank'] ); ?></td>
                            <td><?php echo esc_html( (string) $priority['scenario_name'] ); ?></td>
                            <td><?php echo esc_html( $priority['priority_score'] . '/100' ); ?></td>
                            <td><?php echo esc_html( (string) $priority['instruction'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3 style="margin-top:28px;"><?php echo esc_html( self::label( 'phases', $language ) ); ?></h3>
            <?php foreach ( $plan['phases'] ?? array() as $phase => $items ) : ?>
                <?php if ( empty( $items ) ) { continue; } ?>
                <div style="background:#fff;border:1px solid #dcdcde;padding:16px 20px;margin:12px 0;">
                    <h4 style="margin-top:0;"><?php echo esc_html( self::phase_label( (string) $phase, $language ) ); ?></h4>
                    <ul style="list-style:disc;padding-left:22px;">
                        <?php foreach ( $items as $item ) : ?><li style="margin-bottom:8px;"><?php echo esc_html( (string) $item ); ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>

            <h3 style="margin-top:28px;"><?php echo esc_html( self::label( 'triggers', $language ) ); ?></h3>
            <table class="widefat striped">
                <thead><tr><th><?php echo esc_html( self::label( 'if_observed', $language ) ); ?></th><th><?php echo esc_html( self::label( 'response', $language ) ); ?></th></tr></thead>
                <tbody>
                    <?php foreach ( $plan['decision_triggers'] ?? array() as $trigger ) : ?>
                        <tr><td><?php echo esc_html( (string) $trigger['trigger'] ); ?></td><td><?php echo esc_html( (string) $trigger['action'] ); ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <details style="margin-top:22px;">
                <summary><strong><?php echo esc_html( self::label( 'technical_evidence', $language ) ); ?></strong></summary>
                <pre style="white-space:pre-wrap;background:#fff;padding:16px;border:1px solid #dcdcde;"><?php echo esc_html( wp_json_encode( $plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre>
            </details>
        </div>
        <?php
    }

    private static function label( string $key, string $language ): string {
        $labels = array(
            'en' => array(
                'print' => 'Print plan', 'top_risk' => 'Top risk', 'best_opportunity' => 'Best opportunity',
                'average_risk' => 'Average risk', 'coverage' => 'Coverage', 'priorities' => 'Match priorities',
                'scenario' => 'Scenario', 'priority' => 'Priority', 'instruction' => 'Instruction', 'phases' => 'Phase-by-phase plan',
                'triggers' => 'Decision triggers', 'if_observed' => 'If observed', 'response' => 'Response', 'technical_evidence' => 'Technical evidence',
            ),
            'es' => array(
                'print' => 'Imprimir plan', 'top_risk' => 'Riesgo principal', 'best_opportunity' => 'Mejor oportunidad',
                'average_risk' => 'Riesgo medio', 'coverage' => 'Cobertura', 'priorities' => 'Prioridades del partido',
                'scenario' => 'Escenario', 'priority' => 'Prioridad', 'instruction' => 'Instrucción', 'phases' => 'Plan por fases',
                'triggers' => 'Disparadores de decisión', 'if_observed' => 'Si observamos', 'response' => 'Respuesta', 'technical_evidence' => 'Evidencia técnica',
            ),
        );
        return $labels[ $language ][ $key ] ?? $labels['en'][ $key ] ?? $key;
    }

    private static function phase_label( string $phase, string $language ): string {
        $labels = array(
            'en' => array(
                'pre_match_training' => 'Pre-match training', 'opening_15_minutes' => 'Opening 15 minutes',
                'in_possession' => 'In possession', 'out_of_possession' => 'Out of possession',
                'after_loss' => 'Immediately after loss', 'after_recovery' => 'Immediately after recovery',
                'halftime_checks' => 'Halftime checks',
            ),
            'es' => array(
                'pre_match_training' => 'Entrenamiento previo', 'opening_15_minutes' => 'Primeros 15 minutos',
                'in_possession' => 'Con posesión', 'out_of_possession' => 'Sin posesión',
                'after_loss' => 'Inmediatamente tras pérdida', 'after_recovery' => 'Inmediatamente tras recuperación',
                'halftime_checks' => 'Comprobaciones al descanso',
            ),
        );
        return $labels[ $language ][ $phase ] ?? $phase;
    }

    private static function card( string $title, string $value, string $meta ): void {
        ?>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;">
            <div style="font-size:12px;text-transform:uppercase;color:#646970;"><?php echo esc_html( $title ); ?></div>
            <div style="font-size:20px;font-weight:700;line-height:1.25;margin-top:5px;"><?php echo esc_html( $value ); ?></div>
            <?php if ( '' !== $meta ) : ?><div style="margin-top:5px;color:#646970;"><?php echo esc_html( $meta ); ?></div><?php endif; ?>
        </div>
        <?php
    }

    private static function team_select( string $name, string $id, array $teams, int $default_index = 0 ): void {
        ?>
        <select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" required>
            <?php foreach ( $teams as $index => $team ) : ?>
                <option value="<?php echo esc_attr( $team['source_team_id'] ); ?>" <?php selected( $index, $default_index ); ?>><?php echo esc_html( $team['team_name'] ); ?></option>
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
