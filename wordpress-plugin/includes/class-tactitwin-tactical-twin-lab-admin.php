<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Tactical_Twin_Lab_Admin {
    private const RESULT_KEY = 'tactitwin_twin_lab_';
    private const ERROR_KEY  = 'tactitwin_twin_lab_error_';

    public static function register(): void {
        add_action( 'admin_menu', array( self::class, 'menu' ) );
        add_action( 'admin_post_tactitwin_generate_twin_lab', array( self::class, 'generate' ) );
    }

    public static function menu(): void {
        add_submenu_page(
            'tactitwin',
            __( 'Twin Lab', 'tactitwin' ),
            __( 'Twin Lab', 'tactitwin' ),
            'manage_options',
            'tactitwin-twin-lab',
            array( self::class, 'render' )
        );
    }

    public static function generate(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage TactiTwin.', 'tactitwin' ) );
        }

        check_admin_referer( 'tactitwin_generate_twin_lab' );

        $context     = TactiTwin_Planning_Context::current();
        $team_id     = ! empty( $context['team_source_id'] ) ? absint( $context['team_source_id'] ) : absint( $_POST['team_id'] ?? 0 );
        $opponent_id = ! empty( $context['opponent_source_id'] ) ? absint( $context['opponent_source_id'] ) : absint( $_POST['opponent_id'] ?? 0 );
        $sample      = max( 1, min( 10, absint( $_POST['sample'] ?? 5 ) ) );
        $language    = TactiTwin_I18n::normalize_language( sanitize_key( $_POST['language'] ?? TactiTwin_I18n::current_language() ) );
        $user        = get_current_user_id();

        try {
            if ( $team_id <= 0 || $opponent_id <= 0 || $team_id === $opponent_id ) {
                throw new InvalidArgumentException( 'Twin Lab requires two different valid teams.' );
            }

            $preparation = ( new TactiTwin_Data_Preparation_Service() )->prepare_matchup( $team_id, $opponent_id, $sample );
            $lab = ( new TactiTwin_Tactical_Twin_Lab_Service() )->evaluate(
                $team_id,
                $opponent_id,
                $sample,
                $language
            );

            set_transient(
                self::RESULT_KEY . $user,
                array(
                    'lab'         => $lab,
                    'preparation' => $preparation,
                    'language'    => $language,
                    'context'     => $context,
                ),
                10 * MINUTE_IN_SECONDS
            );
        } catch ( Throwable $e ) {
            set_transient( self::ERROR_KEY . $user, $e->getMessage(), MINUTE_IN_SECONDS );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=tactitwin-twin-lab' ) );
        exit;
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $user     = get_current_user_id();
        $result   = get_transient( self::RESULT_KEY . $user );
        $error    = get_transient( self::ERROR_KEY . $user );
        $context  = TactiTwin_Planning_Context::current();
        $language = is_array( $result ) && ! empty( $result['language'] )
            ? TactiTwin_I18n::normalize_language( (string) $result['language'] )
            : TactiTwin_I18n::current_language();
        $labels = self::labels( $language );

        if ( $error ) {
            delete_transient( self::ERROR_KEY . $user );
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $labels['title'] ); ?></h1>
            <p><?php echo esc_html( $labels['intro'] ); ?></p>

            <?php if ( $error ) : ?>
                <div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
            <?php endif; ?>

            <?php self::render_form( $context, $language, $labels ); ?>

            <?php if ( is_array( $result ) && ! empty( $result['lab'] ) ) : ?>
                <?php self::render_lab( $result['lab'], $labels ); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_form( array $context, string $language, array $labels ): void {
        $teams       = self::teams();
        $has_context = ! empty( $context['team_source_id'] ) && ! empty( $context['opponent_source_id'] );
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;padding:20px;max-width:960px;border:1px solid #dcdcde;">
            <input type="hidden" name="action" value="tactitwin_generate_twin_lab">
            <?php wp_nonce_field( 'tactitwin_generate_twin_lab' ); ?>

            <?php if ( $has_context ) : ?>
                <div class="notice notice-info inline" style="margin:0 0 16px;">
                    <p>
                        <strong><?php echo esc_html( $labels['active_context'] ); ?></strong>
                        <?php echo esc_html( (string) $context['team_source_id'] . ' vs ' . (string) $context['opponent_source_id'] ); ?>
                        · <a href="<?php echo esc_url( admin_url( 'admin.php?page=tactitwin' ) ); ?>"><?php echo esc_html( $labels['change_match'] ); ?></a>
                    </p>
                </div>
            <?php endif; ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th><label for="tt-twin-language"><?php echo esc_html( $labels['language'] ); ?></label></th>
                    <td>
                        <select name="language" id="tt-twin-language">
                            <option value="es" <?php selected( $language, 'es' ); ?>>Español</option>
                            <option value="en" <?php selected( $language, 'en' ); ?>>English</option>
                        </select>
                    </td>
                </tr>
                <?php if ( ! $has_context ) : ?>
                    <tr>
                        <th><label for="tt-twin-team"><?php echo esc_html( $labels['our_team'] ); ?></label></th>
                        <td><?php self::team_select( 'team_id', 'tt-twin-team', $teams, 0 ); ?></td>
                    </tr>
                    <tr>
                        <th><label for="tt-twin-opponent"><?php echo esc_html( $labels['opponent'] ); ?></label></th>
                        <td><?php self::team_select( 'opponent_id', 'tt-twin-opponent', $teams, 1 ); ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <th><label for="tt-twin-sample"><?php echo esc_html( $labels['sample'] ); ?></label></th>
                    <td><input id="tt-twin-sample" name="sample" type="number" min="1" max="10" value="5"></td>
                </tr>
            </table>
            <?php submit_button( $labels['generate'] ); ?>
        </form>
        <?php
    }

    private static function render_lab( array $lab, array $labels ): void {
        $summary    = $lab['preparation_summary'] ?? array();
        $matrix     = $lab['response_matrix'] ?? array();
        $tree       = $lab['scenario_tree'] ?? array();
        $robustness = $lab['response_robustness'] ?? array();
        $switches   = $lab['response_switch_playbook'] ?? array();
        ?>
        <hr style="margin:32px 0;">
        <div style="max-width:1240px;">
            <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;">
                <div>
                    <h2 style="margin-bottom:4px;"><?php echo esc_html( ( $lab['team']['name'] ?? '' ) . ' vs ' . ( $lab['opponent']['name'] ?? '' ) ); ?></h2>
                    <p style="margin-top:0;"><?php echo esc_html( $labels['result_intro'] ); ?></p>
                </div>
                <button type="button" class="button" onclick="window.print();"><?php echo esc_html( $labels['print'] ); ?></button>
            </div>

            <?php if ( ! empty( $lab['coverage']['warning'] ) ) : ?>
                <div class="notice notice-warning inline"><p><?php echo esc_html( $lab['coverage']['warning'] ); ?></p></div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin:18px 0 28px;">
                <?php self::card( $labels['top_response'], (string) ( $summary['top_response_name'] ?? '-' ), (string) ( $summary['top_response_id'] ?? '' ) ); ?>
                <?php self::card( $labels['most_robust'], (string) ( $summary['most_robust_response_name'] ?? '-' ), (string) ( $summary['most_robust_label'] ?? '' ) ); ?>
                <?php self::card( $labels['switch_candidates'], (string) ( $summary['switch_candidate_count'] ?? 0 ), $labels['manual_review'] ); ?>
                <?php self::card( $labels['leader_changes'], (string) ( $summary['leader_change_count'] ?? 0 ), $labels['of_branches'] . ' ' . (string) ( $summary['branch_count'] ?? 0 ) ); ?>
                <?php self::card( $labels['highest_risk_branch'], (string) ( $summary['highest_risk_branch']['branch_name'] ?? '-' ), (string) ( $summary['highest_risk_branch']['score'] ?? 0 ) . '/100' ); ?>
                <?php self::card( $labels['coverage'], (string) ( $lab['coverage']['effective_matches'] ?? 0 ), $labels['switch_cautions'] . ': ' . (string) ( $summary['coverage_caution_switches'] ?? 0 ) ); ?>
            </div>

            <?php self::render_response_table( $matrix, $labels ); ?>
            <?php self::render_robustness_table( $robustness, $labels ); ?>
            <?php self::render_switch_playbook( $switches, $labels ); ?>
            <?php self::render_branch_table( $tree, $labels ); ?>

            <div class="notice notice-info inline" style="margin-top:22px;"><p><?php echo esc_html( $labels['guardrail'] ); ?></p></div>

            <details style="margin-top:22px;">
                <summary><strong><?php echo esc_html( $labels['technical'] ); ?></strong></summary>
                <pre style="white-space:pre-wrap;background:#fff;padding:16px;border:1px solid #dcdcde;"><?php echo esc_html( wp_json_encode( $lab, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre>
            </details>
        </div>
        <?php
    }

    private static function render_response_table( array $matrix, array $labels ): void {
        ?>
        <h3><?php echo esc_html( $labels['responses'] ); ?></h3>
        <p><?php echo esc_html( $labels['responses_help'] ); ?></p>
        <table class="widefat striped">
            <thead><tr><th>#</th><th><?php echo esc_html( $labels['response'] ); ?></th><th><?php echo esc_html( $labels['fit'] ); ?></th><th><?php echo esc_html( $labels['driver'] ); ?></th><th><?php echo esc_html( $labels['tradeoffs'] ); ?></th></tr></thead>
            <tbody>
                <?php foreach ( $matrix['responses'] ?? array() as $response ) : ?>
                    <tr>
                        <td><?php echo esc_html( (string) ( $response['rank'] ?? '' ) ); ?></td>
                        <td><strong><?php echo esc_html( (string) ( $response['response_name'] ?? '' ) ); ?></strong><br><span style="color:#646970;"><?php echo esc_html( (string) ( $response['description'] ?? '' ) ); ?></span></td>
                        <td><?php echo esc_html( (string) ( $response['fit_score'] ?? 0 ) . '/100' ); ?></td>
                        <td><?php echo esc_html( (string) ( $response['dominant_driver']['scenario_name'] ?? '-' ) ); ?></td>
                        <td><?php echo esc_html( (string) count( $response['trade_offs'] ?? array() ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private static function render_robustness_table( array $robustness, array $labels ): void {
        ?>
        <h3 style="margin-top:30px;"><?php echo esc_html( $labels['robustness'] ); ?></h3>
        <p><?php echo esc_html( $labels['robustness_help'] ); ?></p>
        <table class="widefat striped">
            <thead><tr><th>#</th><th><?php echo esc_html( $labels['response'] ); ?></th><th><?php echo esc_html( $labels['robustness_state'] ); ?></th><th><?php echo esc_html( $labels['first_places'] ); ?></th><th><?php echo esc_html( $labels['top_two'] ); ?></th><th><?php echo esc_html( $labels['mean_rank'] ); ?></th><th><?php echo esc_html( $labels['worst_rank'] ); ?></th></tr></thead>
            <tbody>
                <?php foreach ( $robustness['responses'] ?? array() as $response ) : ?>
                    <tr>
                        <td><?php echo esc_html( (string) ( $response['robustness_rank'] ?? '' ) ); ?></td>
                        <td><strong><?php echo esc_html( (string) ( $response['response_name'] ?? '' ) ); ?></strong></td>
                        <td><?php echo esc_html( (string) ( $response['robustness_label'] ?? '' ) ); ?></td>
                        <td><?php echo esc_html( (string) ( $response['first_place_count'] ?? 0 ) ); ?></td>
                        <td><?php echo esc_html( (string) ( $response['top_two_count'] ?? 0 ) ); ?></td>
                        <td><?php echo esc_html( (string) ( $response['mean_rank'] ?? 0 ) ); ?></td>
                        <td><?php echo esc_html( (string) ( $response['worst_rank'] ?? 0 ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private static function render_switch_playbook( array $switches, array $labels ): void {
        ?>
        <h3 style="margin-top:30px;"><?php echo esc_html( $labels['switch_playbook'] ); ?></h3>
        <p><?php echo esc_html( $labels['switch_help'] ); ?></p>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php echo esc_html( $labels['branch'] ); ?></th>
                    <th><?php echo esc_html( $labels['decision'] ); ?></th>
                    <th><?php echo esc_html( $labels['target_response'] ); ?></th>
                    <th><?php echo esc_html( $labels['margin'] ); ?></th>
                    <th><?php echo esc_html( $labels['trigger'] ); ?></th>
                    <th><?php echo esc_html( $labels['validation'] ); ?></th>
                    <th><?php echo esc_html( $labels['coverage_note'] ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $switches['items'] ?? array() as $item ) : ?>
                    <?php $signal = $item['validation_signals'][0] ?? array(); ?>
                    <tr>
                        <td><strong><?php echo esc_html( (string) ( $item['branch_name'] ?? '' ) ); ?></strong></td>
                        <td><?php echo esc_html( (string) ( $item['decision_label'] ?? '' ) ); ?></td>
                        <td><?php echo esc_html( (string) ( $item['target_response']['response_name'] ?? '-' ) ); ?></td>
                        <td><?php echo esc_html( (string) ( $item['state_fit_margin'] ?? 0 ) ); ?></td>
                        <td><?php echo esc_html( (string) ( $item['trigger']['headline'] ?? '-' ) ); ?></td>
                        <td><?php echo esc_html( (string) ( $signal['trigger'] ?? $item['first_branch_recommendation'] ?? '-' ) ); ?></td>
                        <td>
                            <?php if ( ! empty( $item['coverage_caution'] ) ) : ?>
                                <strong><?php echo esc_html( $labels['caution'] ); ?></strong><br>
                                <span><?php echo esc_html( (string) ( $item['coverage_caution_note'] ?? '' ) ); ?></span>
                            <?php else : ?>
                                <?php echo esc_html( $labels['coverage_ok'] ); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin:10px 0 0;color:#646970;"><strong><?php echo esc_html( $labels['manual_review'] ); ?>:</strong> <?php echo esc_html( $labels['manual_review_help'] ); ?></p>
        <?php
    }

    private static function render_branch_table( array $tree, array $labels ): void {
        ?>
        <h3 style="margin-top:30px;"><?php echo esc_html( $labels['branches'] ); ?></h3>
        <p><?php echo esc_html( $labels['branches_help'] ); ?></p>
        <table class="widefat striped">
            <thead><tr><th><?php echo esc_html( $labels['branch'] ); ?></th><th><?php echo esc_html( $labels['state'] ); ?></th><th><?php echo esc_html( $labels['risk'] ); ?></th><th><?php echo esc_html( $labels['opportunity'] ); ?></th><th><?php echo esc_html( $labels['orientation'] ); ?></th><th><?php echo esc_html( $labels['first_instruction'] ); ?></th></tr></thead>
            <tbody>
                <?php foreach ( $tree['branches'] ?? array() as $branch ) : ?>
                    <?php $hypothesis = $branch['hypothesis'] ?? array(); ?>
                    <tr>
                        <td><strong><?php echo esc_html( (string) ( $branch['branch_name'] ?? '' ) ); ?></strong><br><span style="color:#646970;"><?php echo esc_html( (string) ( $branch['purpose'] ?? '' ) ); ?></span></td>
                        <td><?php echo esc_html( sprintf( "%d' · %d-%d · %dv%d", (int) ( $hypothesis['minute'] ?? 0 ), (int) ( $hypothesis['our_score'] ?? 0 ), (int) ( $hypothesis['opponent_score'] ?? 0 ), (int) ( $hypothesis['our_players'] ?? 11 ), (int) ( $hypothesis['opponent_players'] ?? 11 ) ) ); ?></td>
                        <td><?php echo esc_html( (string) ( $branch['risk']['score'] ?? 0 ) . '/100' ); ?></td>
                        <td><?php echo esc_html( (string) ( $branch['opportunity']['score'] ?? 0 ) . '/100' ); ?></td>
                        <td><?php echo esc_html( (string) ( $branch['stress_signature']['orientation_label'] ?? '' ) ); ?></td>
                        <td><?php echo esc_html( (string) ( $branch['recommendations'][0] ?? '-' ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
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

    private static function team_select( string $name, string $id, array $teams, int $default_index = 0 ): void {
        ?>
        <select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" required>
            <?php foreach ( $teams as $index => $team ) : ?>
                <option value="<?php echo esc_attr( $team['source_team_id'] ); ?>" <?php selected( $index, $default_index ); ?>><?php echo esc_html( $team['team_name'] ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    private static function labels( string $language ): array {
        if ( 'es' === $language ) {
            return array(
                'title' => 'TactiTwin · Tactical Twin Lab',
                'intro' => 'Compara respuestas, su robustez y los puntos de revisión donde el staff debería valorar un cambio de respuesta.',
                'active_context' => 'Usando el partido activo:',
                'change_match' => 'Cambiar en Match Workspace',
                'language' => 'Idioma',
                'our_team' => 'Nuestro equipo',
                'opponent' => 'Rival',
                'sample' => 'Partidos recientes',
                'generate' => 'Generar Tactical Twin',
                'result_intro' => 'Preparación unificada: encaje base, robustez entre estados y playbook manual de cambios.',
                'print' => 'Imprimir Twin Lab',
                'top_response' => 'Mayor encaje base',
                'most_robust' => 'Respuesta más robusta',
                'switch_candidates' => 'Candidatos de cambio',
                'leader_changes' => 'Cambios de líder',
                'of_branches' => 'de',
                'highest_risk_branch' => 'Rama de mayor riesgo',
                'coverage' => 'Cobertura',
                'switch_cautions' => 'Cambios con cautela',
                'responses' => 'Respuestas tácticas base',
                'responses_help' => 'El encaje base no es probabilidad de ganar ni predicción de resultado.',
                'response' => 'Respuesta',
                'fit' => 'Encaje',
                'driver' => 'Driver principal',
                'tradeoffs' => 'Trade-offs',
                'robustness' => 'Robustez entre estados del partido',
                'robustness_help' => 'La robustez describe estabilidad de ranking; no crea un score global.',
                'robustness_state' => 'Diagnóstico',
                'first_places' => 'Veces 1ª',
                'top_two' => 'Top-2',
                'mean_rank' => 'Rango medio',
                'worst_rank' => 'Peor rango',
                'switch_playbook' => 'Playbook de cambios de respuesta',
                'switch_help' => 'Cada fila es un punto de revisión preparado. Coincidir con el estado no ejecuta el cambio: el staff debe validar las señales reales.',
                'decision' => 'Decisión preparada',
                'target_response' => 'Respuesta objetivo',
                'margin' => 'Margen',
                'trigger' => 'Estado disparador',
                'validation' => 'Señal a validar',
                'coverage_note' => 'Cobertura',
                'caution' => 'Cautela',
                'coverage_ok' => 'Sin cautela específica',
                'manual_review' => 'Confirmación manual obligatoria',
                'manual_review_help' => 'TactiTwin solo propone el punto de revisión. El cambio requiere observación y decisión del staff.',
                'branches' => 'Árbol de estados del partido',
                'branches_help' => 'Cada rama usa el What-if histórico existente y no recibe probabilidad de ocurrencia.',
                'branch' => 'Rama',
                'state' => 'Estado',
                'risk' => 'Riesgo',
                'opportunity' => 'Oportunidad',
                'orientation' => 'Orientación',
                'first_instruction' => 'Primera instrucción',
                'guardrail' => 'v1.31 convierte cambios de líder en candidatos de revisión. No activa respuestas automáticamente, no altera Plan A/B/C y no predice resultados.',
                'technical' => 'Evidencia técnica completa',
            );
        }

        return array(
            'title' => 'TactiTwin · Tactical Twin Lab',
            'intro' => 'Compare responses, their robustness and the review points where staff should consider switching tactical response.',
            'active_context' => 'Using active match:',
            'change_match' => 'Change in Match Workspace',
            'language' => 'Language',
            'our_team' => 'Our team',
            'opponent' => 'Opponent',
            'sample' => 'Recent matches',
            'generate' => 'Generate Tactical Twin',
            'result_intro' => 'Unified preparation: baseline fit, cross-state robustness and a manual response-switch playbook.',
            'print' => 'Print Twin Lab',
            'top_response' => 'Baseline top fit',
            'most_robust' => 'Most robust response',
            'switch_candidates' => 'Switch candidates',
            'leader_changes' => 'Leader changes',
            'of_branches' => 'of',
            'highest_risk_branch' => 'Highest-risk branch',
            'coverage' => 'Coverage',
            'switch_cautions' => 'Caution switches',
            'responses' => 'Baseline tactical responses',
            'responses_help' => 'Baseline fit is not win probability or an outcome prediction.',
            'response' => 'Response',
            'fit' => 'Fit',
            'driver' => 'Dominant driver',
            'tradeoffs' => 'Trade-offs',
            'robustness' => 'Response robustness across match states',
            'robustness_help' => 'Robustness describes ranking stability; it creates no aggregate robustness score.',
            'robustness_state' => 'Diagnosis',
            'first_places' => '1st-place branches',
            'top_two' => 'Top-2 branches',
            'mean_rank' => 'Mean rank',
            'worst_rank' => 'Worst rank',
            'switch_playbook' => 'Response Switch Playbook',
            'switch_help' => 'Each row is a prepared review point. Matching the state never executes the switch: staff must validate the live signals.',
            'decision' => 'Prepared decision',
            'target_response' => 'Target response',
            'margin' => 'Margin',
            'trigger' => 'State trigger',
            'validation' => 'Signal to validate',
            'coverage_note' => 'Coverage',
            'caution' => 'Caution',
            'coverage_ok' => 'No specific caution',
            'manual_review' => 'Manual confirmation required',
            'manual_review_help' => 'TactiTwin only proposes the review point. Any switch requires staff observation and decision.',
            'branches' => 'Match-state scenario tree',
            'branches_help' => 'Every branch reuses the existing historical What-if engine and receives no occurrence probability.',
            'branch' => 'Branch',
            'state' => 'State',
            'risk' => 'Risk',
            'opportunity' => 'Opportunity',
            'orientation' => 'Orientation',
            'first_instruction' => 'First instruction',
            'guardrail' => 'v1.31 converts leader changes into review candidates. It never activates responses automatically, does not alter Plan A/B/C and predicts no outcome.',
            'technical' => 'Full technical evidence',
        );
    }
}
