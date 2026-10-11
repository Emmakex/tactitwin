<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Match_Workspace_Admin {
    public static function register(): void {
        add_action( 'admin_post_tactitwin_set_match_workspace_context', array( self::class, 'save_context' ) );
    }

    public static function workflow_steps(): array {
        return array(
            array( 'id' => 'scenario', 'phase' => 'pre_match', 'page' => 'tactitwin-scenario-lab', 'context_mode' => 'direct' ),
            array( 'id' => 'match_plan', 'phase' => 'pre_match', 'page' => 'tactitwin-match-plan', 'context_mode' => 'direct' ),
            array( 'id' => 'what_if', 'phase' => 'pre_match', 'page' => 'tactitwin-what-if', 'context_mode' => 'direct' ),
            array( 'id' => 'lineup', 'phase' => 'pre_match', 'page' => 'tactitwin-lineup-lab', 'context_mode' => 'direct' ),
            array( 'id' => 'personnel', 'phase' => 'pre_match', 'page' => 'tactitwin-personnel-lab', 'context_mode' => 'direct' ),
            array( 'id' => 'unit', 'phase' => 'pre_match', 'page' => 'tactitwin-unit-lab', 'context_mode' => 'team' ),
            array( 'id' => 'staff_brief', 'phase' => 'pre_match', 'page' => 'tactitwin-staff-brief', 'context_mode' => 'direct' ),
            array( 'id' => 'live_playbook', 'phase' => 'match_day', 'page' => 'tactitwin-live-playbook', 'context_mode' => 'direct' ),
            array( 'id' => 'live_sessions', 'phase' => 'match_day', 'page' => 'tactitwin-live-sessions', 'context_mode' => 'handoff' ),
            array( 'id' => 'session_review', 'phase' => 'post_match', 'page' => 'tactitwin-session-review', 'context_mode' => 'session' ),
        );
    }

    public static function save_context(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage TactiTwin.', 'tactitwin' ) );
        }

        check_admin_referer( 'tactitwin_set_match_workspace_context' );
        $selection = sanitize_text_field( wp_unslash( (string) ( $_POST['tactitwin_planning_target'] ?? '' ) ) );
        $language = TactiTwin_I18n::normalize_language( sanitize_key( $_POST['language'] ?? TactiTwin_I18n::current_language() ) );

        try {
            if ( '' === trim( $selection ) ) {
                TactiTwin_Planning_Context::clear_current();
                self::notice( 'success', self::label( 'manual_saved', $language ) );
            } else {
                $context = TactiTwin_Planning_Context::resolve_selection( $selection );
                TactiTwin_Planning_Context::store_current( $context );
                self::notice( 'success', self::label( 'context_saved', $language ) );
            }
        } catch ( Throwable $e ) {
            self::notice( 'error', $e->getMessage() );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=tactitwin' ) );
        exit;
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $language = TactiTwin_I18n::normalize_language( TactiTwin_I18n::current_language() );
        $notice = get_transient( 'tactitwin_workspace_notice_' . get_current_user_id() );
        if ( $notice ) {
            delete_transient( 'tactitwin_workspace_notice_' . get_current_user_id() );
        }

        $targets = array();
        try {
            $targets = ( new TactiTwin_Match_Target_Service() )->recent_targets( 150 );
        } catch ( Throwable $e ) {
            $targets = array();
        }

        $context = TactiTwin_Planning_Context::current();
        $context_error = '';
        if ( ! empty( $context ) ) {
            try {
                $context = TactiTwin_Planning_Context::resolve_selection( TactiTwin_Planning_Context::selection_from_context( $context ) );
                TactiTwin_Planning_Context::store_current( $context );
            } catch ( Throwable $e ) {
                $context_error = $e->getMessage();
            }
        }

        $labels = self::labels( $language );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $labels['title'] ); ?></h1>
            <p style="max-width:1000px;font-size:15px;"><?php echo esc_html( $labels['intro'] ); ?></p>

            <?php if ( is_array( $notice ) ) : ?>
                <div class="notice notice-<?php echo esc_attr( 'error' === ( $notice['type'] ?? '' ) ? 'error' : 'success' ); ?> is-dismissible"><p><?php echo esc_html( (string) ( $notice['message'] ?? '' ) ); ?></p></div>
            <?php endif; ?>
            <?php if ( '' !== $context_error ) : ?>
                <div class="notice notice-error"><p><?php echo esc_html( $context_error ); ?></p></div>
            <?php endif; ?>

            <?php self::render_context_selector( $targets, $context, $language ); ?>
            <?php self::render_context_summary( $context, $language ); ?>
            <?php self::render_workflow( $context, $language ); ?>

            <p style="margin-top:24px;">
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=tactitwin-data' ) ); ?>"><?php echo esc_html( $labels['data_imports'] ); ?></a>
            </p>
        </div>
        <?php
    }

    private static function render_context_selector( array $targets, array $context, string $language ): void {
        $labels = self::labels( $language );
        $current = TactiTwin_Planning_Context::selection_from_context( $context );
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;border:1px solid #dcdcde;padding:20px;max-width:1180px;margin:20px 0;">
            <input type="hidden" name="action" value="tactitwin_set_match_workspace_context">
            <input type="hidden" name="language" value="<?php echo esc_attr( $language ); ?>">
            <?php wp_nonce_field( 'tactitwin_set_match_workspace_context' ); ?>
            <h2 style="margin-top:0;"><?php echo esc_html( $labels['select_title'] ); ?></h2>
            <p><?php echo esc_html( $labels['select_help'] ); ?></p>
            <select name="tactitwin_planning_target" style="min-width:680px;max-width:100%;">
                <option value=""><?php echo esc_html( $labels['manual'] ); ?></option>
                <?php foreach ( $targets as $target ) : ?>
                    <?php
                    $row_id = (int) ( $target['id'] ?? 0 );
                    $home_id = trim( (string) ( $target['home_team_source_id'] ?? '' ) );
                    $away_id = trim( (string) ( $target['away_team_source_id'] ?? '' ) );
                    if ( $row_id <= 0 || '' === $home_id || '' === $away_id ) {
                        continue;
                    }
                    $home_name = (string) ( $target['home_team_name'] ?? $home_id );
                    $away_name = (string) ( $target['away_team_name'] ?? $away_id );
                    $date = (string) ( $target['match_date'] ?? '' );
                    $competition = trim( (string) ( $target['competition_name'] ?? '' ) );
                    $season = trim( (string) ( $target['season_name'] ?? '' ) );
                    $prefix = trim( $date . ( '' !== $competition ? ' · ' . $competition : '' ) . ( '' !== $season ? ' · ' . $season : '' ), ' ·' );
                    foreach ( array( $home_id => $home_name, $away_id => $away_name ) as $our_id => $our_name ) :
                        $selection = $row_id . ':' . $our_id;
                        $label = trim( $prefix . ' · ' . $home_name . ' vs ' . $away_name . ' · ' . $labels['our_team'] . ': ' . $our_name, ' ·' );
                        ?>
                        <option value="<?php echo esc_attr( $selection ); ?>" <?php selected( $current, $selection ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </select>
            <?php submit_button( $labels['save_context'], 'primary', 'submit', false ); ?>
            <?php if ( empty( $targets ) ) : ?><p class="description"><?php echo esc_html( $labels['no_targets'] ); ?></p><?php endif; ?>
        </form>
        <?php
    }

    private static function render_context_summary( array $context, string $language ): void {
        $labels = self::labels( $language );
        if ( empty( $context ) ) {
            ?>
            <div class="notice notice-info inline" style="max-width:1140px;"><p><?php echo esc_html( $labels['manual_status'] ); ?></p></div>
            <?php
            return;
        }

        $match = is_array( $context['match'] ?? null ) ? $context['match'] : array();
        $fixture = is_array( $context['fixture'] ?? null ) ? $context['fixture'] : array();
        $team_id = (string) ( $context['team_source_id'] ?? '' );
        $home_id = (string) ( $match['home_team_source_id'] ?? '' );
        $home_name = (string) ( $match['home_team_name'] ?? $home_id );
        $away_name = (string) ( $match['away_team_name'] ?? $match['away_team_source_id'] ?? '' );
        $our_name = $team_id === $home_id ? $home_name : $away_name;
        $opponent_name = $team_id === $home_id ? $away_name : $home_name;
        $fixture_key = (string) ( $context['fixture_key'] ?? '' );
        ?>
        <section style="max-width:1180px;background:#fff;border:1px solid #dcdcde;border-left:5px solid #2271b1;padding:20px;margin:18px 0;">
            <div style="display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap;align-items:flex-start;">
                <div>
                    <div style="font-size:12px;text-transform:uppercase;color:#646970;font-weight:700;"><?php echo esc_html( $labels['active_context'] ); ?></div>
                    <h2 style="margin:6px 0;"><?php echo esc_html( $our_name . ' vs ' . $opponent_name ); ?></h2>
                    <p style="margin:4px 0;"><?php echo esc_html( trim( (string) ( $match['match_date'] ?? '' ) . ' · ' . (string) ( $match['competition_name'] ?? '' ) . ' · ' . (string) ( $match['season_name'] ?? '' ), ' ·' ) ); ?></p>
                </div>
                <div style="min-width:280px;">
                    <p style="margin:0 0 6px;"><strong><?php echo esc_html( $labels['perspective'] ); ?>:</strong> <?php echo esc_html( strtoupper( (string) ( $context['perspective'] ?? '' ) ) ); ?></p>
                    <p style="margin:0 0 6px;"><strong><?php echo esc_html( $labels['provider'] ); ?>:</strong> <?php echo esc_html( (string) ( $fixture['source'] ?? '' ) . ' / ' . (string) ( $fixture['source_match_id'] ?? '' ) ); ?></p>
                    <p style="margin:0;"><strong>fixture_key:</strong> <code><?php echo esc_html( '' !== $fixture_key ? substr( $fixture_key, 0, 16 ) . '…' : '-' ); ?></code></p>
                </div>
            </div>
        </section>
        <?php
    }

    private static function render_workflow( array $context, string $language ): void {
        $labels = self::labels( $language );
        $active = ! empty( $context );
        $groups = array( 'pre_match', 'match_day', 'post_match' );
        foreach ( $groups as $group ) :
            ?>
            <h2 style="margin-top:28px;"><?php echo esc_html( $labels[ 'phase_' . $group ] ); ?></h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(235px,1fr));gap:14px;max-width:1180px;">
                <?php foreach ( self::workflow_steps() as $step ) : ?>
                    <?php if ( $group !== $step['phase'] ) { continue; } ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $step['page'] ) ); ?>" style="display:block;text-decoration:none;background:#fff;border:1px solid #dcdcde;padding:18px;color:#1d2327;min-height:120px;">
                        <strong style="font-size:16px;"><?php echo esc_html( $labels[ 'step_' . $step['id'] ] ); ?></strong>
                        <p style="margin:8px 0;color:#50575e;"><?php echo esc_html( $labels[ 'desc_' . $step['id'] ] ); ?></p>
                        <span style="font-size:12px;font-weight:700;color:#2271b1;"><?php echo esc_html( self::step_status( $step, $active, $language ) ); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php
        endforeach;
    }

    private static function step_status( array $step, bool $active, string $language ): string {
        $labels = self::labels( $language );
        if ( 'handoff' === $step['context_mode'] ) {
            return $labels['status_handoff'];
        }
        if ( 'session' === $step['context_mode'] ) {
            return $labels['status_session'];
        }
        if ( $active ) {
            return 'team' === $step['context_mode'] ? $labels['status_team_context'] : $labels['status_context'];
        }
        return $labels['status_manual'];
    }

    private static function notice( string $type, string $message ): void {
        set_transient(
            'tactitwin_workspace_notice_' . get_current_user_id(),
            array( 'type' => $type, 'message' => $message ),
            MINUTE_IN_SECONDS
        );
    }

    private static function labels( string $language ): array {
        $es = array(
            'title' => 'TactiTwin Match Workspace',
            'intro' => 'Selecciona el partido que estás preparando y recorre desde una sola pantalla todo el ciclo táctico: análisis, escenarios, alineaciones, Staff Brief, partido en vivo y revisión posterior.',
            'select_title' => 'Partido de trabajo',
            'select_help' => 'Elige un partido importado y la perspectiva de nuestro equipo. El contexto se conserva por usuario y se vuelve a validar contra el registro normalizado antes de cada cálculo compatible.',
            'manual' => '— Modo manual / sin partido activo —',
            'our_team' => 'Nuestro equipo',
            'save_context' => 'Usar este partido',
            'no_targets' => 'No hay partidos importados disponibles. Puedes seguir usando los módulos en modo manual.',
            'manual_saved' => 'Contexto de partido desactivado. Los módulos vuelven a selección manual.',
            'context_saved' => 'Partido activo actualizado para este usuario.',
            'manual_status' => 'No hay un partido activo. TactiTwin mantiene disponibles las selecciones manuales de cada módulo.',
            'active_context' => 'Partido activo',
            'perspective' => 'Perspectiva',
            'provider' => 'Proveedor / partido',
            'data_imports' => 'Data & Imports',
            'phase_pre_match' => 'Preparación prepartido',
            'phase_match_day' => 'Match day',
            'phase_post_match' => 'Post-partido',
            'status_context' => 'Usa el partido activo',
            'status_team_context' => 'Usa el equipo del partido activo',
            'status_manual' => 'Disponible en modo manual',
            'status_handoff' => 'Recibe el partido desde Staff Brief / sesión',
            'status_session' => 'Trabaja sobre la sesión registrada',
            'step_scenario' => 'Scenario Lab',
            'step_match_plan' => 'Match Plan',
            'step_what_if' => 'What-if',
            'step_lineup' => 'Lineup Lab',
            'step_personnel' => 'Personnel Lab',
            'step_unit' => 'Unit Lab',
            'step_staff_brief' => 'Staff Brief',
            'step_live_playbook' => 'Live Playbook',
            'step_live_sessions' => 'Live Sessions',
            'step_session_review' => 'Session Review',
            'desc_scenario' => 'Escaneo táctico y escenarios del rival.',
            'desc_match_plan' => 'Prioridades e instrucciones por fases.',
            'desc_what_if' => 'Minuto, marcador y superioridad/inferioridad.',
            'desc_lineup' => 'Compara XI, formación y encaje frente al rival.',
            'desc_personnel' => 'Evalúa sustituciones hipotéticas dentro del mismo partido.',
            'desc_unit' => 'Analiza conexiones históricas entre dos jugadores de nuestro equipo.',
            'desc_staff_brief' => 'Compone, revisa y aprueba la hoja de partido.',
            'desc_live_playbook' => 'Aplica observaciones reales durante el encuentro.',
            'desc_live_sessions' => 'Continúa la sesión persistente y su timeline.',
            'desc_session_review' => 'Reconstruye lo preparado, observado y activado.',
        );
        $en = array(
            'title' => 'TactiTwin Match Workspace',
            'intro' => 'Select the match you are preparing and move through the full tactical cycle from one screen: analysis, scenarios, lineups, Staff Brief, live match work and post-match review.',
            'select_title' => 'Working match',
            'select_help' => 'Choose an imported match and our-team perspective. Context is stored per user and revalidated against the normalized match record before every compatible calculation.',
            'manual' => '— Manual mode / no active match —',
            'our_team' => 'Our team',
            'save_context' => 'Use this match',
            'no_targets' => 'No imported matches are available. Modules remain usable in manual mode.',
            'manual_saved' => 'Match context disabled. Modules are back in manual-selection mode.',
            'context_saved' => 'Active match updated for this user.',
            'manual_status' => 'No active match is selected. TactiTwin keeps each module available with its manual selectors.',
            'active_context' => 'Active match',
            'perspective' => 'Perspective',
            'provider' => 'Provider / match',
            'data_imports' => 'Data & Imports',
            'phase_pre_match' => 'Pre-match preparation',
            'phase_match_day' => 'Match day',
            'phase_post_match' => 'Post-match',
            'status_context' => 'Uses active match',
            'status_team_context' => 'Uses active-match team',
            'status_manual' => 'Available in manual mode',
            'status_handoff' => 'Receives match from Staff Brief / session',
            'status_session' => 'Works from the recorded session',
            'step_scenario' => 'Scenario Lab',
            'step_match_plan' => 'Match Plan',
            'step_what_if' => 'What-if',
            'step_lineup' => 'Lineup Lab',
            'step_personnel' => 'Personnel Lab',
            'step_unit' => 'Unit Lab',
            'step_staff_brief' => 'Staff Brief',
            'step_live_playbook' => 'Live Playbook',
            'step_live_sessions' => 'Live Sessions',
            'step_session_review' => 'Session Review',
            'desc_scenario' => 'Opponent tactical scan and scenario assessment.',
            'desc_match_plan' => 'Priorities and phase-by-phase instructions.',
            'desc_what_if' => 'Minute, scoreline and manpower hypotheses.',
            'desc_lineup' => 'Compare XI, formation and opponent-conditioned fit.',
            'desc_personnel' => 'Evaluate hypothetical substitutions inside the same match context.',
            'desc_unit' => 'Inspect historical links between two players from our team.',
            'desc_staff_brief' => 'Compose, review and approve the match sheet.',
            'desc_live_playbook' => 'Apply staff-confirmed observations during the match.',
            'desc_live_sessions' => 'Continue the persistent session and its timeline.',
            'desc_session_review' => 'Reconstruct what was prepared, observed and activated.',
        );
        return 'es' === TactiTwin_I18n::normalize_language( $language ) ? $es : $en;
    }

    private static function label( string $key, string $language ): string {
        $labels = self::labels( $language );
        return $labels[ $key ] ?? $key;
    }
}
