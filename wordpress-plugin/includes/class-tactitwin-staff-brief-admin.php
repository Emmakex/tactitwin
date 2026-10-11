<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Staff_Brief_Admin {
    public static function register(): void {
        add_action( 'admin_menu', array( self::class, 'menu' ) );
        add_action( 'admin_post_tactitwin_generate_staff_brief', array( self::class, 'generate' ) );
    }

    public static function menu(): void {
        add_submenu_page(
            'tactitwin',
            __( 'Staff Brief', 'tactitwin' ),
            __( 'Staff Brief', 'tactitwin' ),
            'manage_options',
            'tactitwin-staff-brief',
            array( self::class, 'render' )
        );
    }

    public static function generate(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage TactiTwin.', 'tactitwin' ) );
        }
        check_admin_referer( 'tactitwin_generate_staff_brief' );

        $team_id = absint( $_POST['team_id'] ?? 0 );
        $opponent_id = absint( $_POST['opponent_id'] ?? 0 );
        $sample = max( 1, min( 10, absint( $_POST['sample'] ?? 5 ) ) );
        $language = TactiTwin_I18n::normalize_language( sanitize_key( $_POST['language'] ?? TactiTwin_I18n::current_language() ) );

        try {
            $preparation = ( new TactiTwin_Data_Preparation_Service() )->prepare_matchup( $team_id, $opponent_id, $sample );
            $match_plan = ( new TactiTwin_Precedent_Aware_Match_Plan_Service() )->evaluate(
                $team_id,
                $opponent_id,
                $sample,
                $language,
                3
            );
            $playbook = ( new TactiTwin_Precedent_Aware_Contingency_Service() )->evaluate(
                $team_id,
                $opponent_id,
                0,
                0,
                1,
                $sample,
                $language,
                11,
                11,
                3
            );
            $brief = TactiTwin_Match_Day_Staff_Brief::compose( $match_plan, $playbook, $language );

            set_transient(
                'tactitwin_staff_brief_' . get_current_user_id(),
                array(
                    'preparation' => $preparation,
                    'brief' => $brief,
                    'language' => $language,
                    'form' => array(
                        'team_id' => $team_id,
                        'opponent_id' => $opponent_id,
                        'sample' => $sample,
                    ),
                ),
                10 * MINUTE_IN_SECONDS
            );
        } catch ( Throwable $e ) {
            set_transient(
                'tactitwin_staff_brief_error_' . get_current_user_id(),
                $e->getMessage(),
                MINUTE_IN_SECONDS
            );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=tactitwin-staff-brief' ) );
        exit;
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $user = get_current_user_id();
        $result = get_transient( 'tactitwin_staff_brief_' . $user );
        $error = get_transient( 'tactitwin_staff_brief_error_' . $user );
        $language = is_array( $result ) && ! empty( $result['language'] )
            ? TactiTwin_I18n::normalize_language( (string) $result['language'] )
            : TactiTwin_I18n::current_language();
        $teams = self::teams();

        if ( $error ) {
            delete_transient( 'tactitwin_staff_brief_error_' . $user );
        }

        self::print_styles();
        ?>
        <div class="wrap tactitwin-staff-brief-page">
            <div class="tt-screen-only">
                <h1><?php echo esc_html( self::label( 'page_title', $language ) ); ?></h1>
                <p><?php echo esc_html( self::label( 'page_intro', $language ) ); ?></p>
                <?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( (string) $error ); ?></p></div><?php endif; ?>
                <?php if ( count( $teams ) < 2 ) : ?>
                    <div class="notice notice-warning inline"><p><?php echo esc_html( self::label( 'need_teams', $language ) ); ?></p></div>
                <?php else : ?>
                    <?php self::form( $teams, $language, is_array( $result['form'] ?? null ) ? $result['form'] : array() ); ?>
                <?php endif; ?>
            </div>

            <?php if ( is_array( $result ) && ! empty( $result['brief'] ) ) : ?>
                <?php self::brief( $result['brief'], $language ); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function form( array $teams, string $language, array $form ): void {
        $selected_team = (int) ( $form['team_id'] ?? 0 );
        $selected_opponent = (int) ( $form['opponent_id'] ?? 0 );
        $sample = max( 1, min( 10, (int) ( $form['sample'] ?? 5 ) ) );
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;padding:20px;max-width:900px;border:1px solid #dcdcde;">
            <input type="hidden" name="action" value="tactitwin_generate_staff_brief">
            <?php wp_nonce_field( 'tactitwin_generate_staff_brief' ); ?>
            <table class="form-table" role="presentation">
                <tr><th><label for="tt-staff-language"><?php echo esc_html( self::label( 'language', $language ) ); ?></label></th><td><select id="tt-staff-language" name="language"><option value="es" <?php selected( $language, 'es' ); ?>>Español</option><option value="en" <?php selected( $language, 'en' ); ?>>English</option></select></td></tr>
                <tr><th><label for="tt-staff-team"><?php echo esc_html( self::label( 'our_team', $language ) ); ?></label></th><td><?php self::team_select( 'team_id', 'tt-staff-team', $teams, $selected_team, 0 ); ?></td></tr>
                <tr><th><label for="tt-staff-opponent"><?php echo esc_html( self::label( 'opponent', $language ) ); ?></label></th><td><?php self::team_select( 'opponent_id', 'tt-staff-opponent', $teams, $selected_opponent, 1 ); ?></td></tr>
                <tr><th><label for="tt-staff-sample"><?php echo esc_html( self::label( 'recent_matches', $language ) ); ?></label></th><td><input id="tt-staff-sample" name="sample" type="number" min="1" max="10" value="<?php echo esc_attr( (string) $sample ); ?>"><p class="description"><?php echo esc_html( self::label( 'sample_note', $language ) ); ?></p></td></tr>
            </table>
            <?php submit_button( self::label( 'generate', $language ) ); ?>
        </form>
        <?php
    }

    private static function brief( array $brief, string $language ): void {
        $context = is_array( $brief['reference_context'] ?? null ) ? $brief['reference_context'] : array();
        $active = is_array( $brief['active_plan'] ?? null ) ? $brief['active_plan'] : array();
        ?>
        <section class="tt-staff-sheet" style="max-width:1080px;background:#fff;border:1px solid #dcdcde;padding:28px;margin-top:28px;">
            <div style="display:flex;justify-content:space-between;gap:20px;align-items:flex-start;">
                <div>
                    <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#646970;"><?php echo esc_html( self::label( 'document_title', $language ) ); ?></div>
                    <h2 style="font-size:28px;margin:4px 0 6px;"><?php echo esc_html( (string) ( $brief['team']['name'] ?? '' ) . ' vs ' . (string) ( $brief['opponent']['name'] ?? '' ) ); ?></h2>
                    <p style="font-size:16px;margin:0;"><?php echo esc_html( (string) ( $brief['headline'] ?? '' ) ); ?></p>
                </div>
                <div class="tt-screen-only"><button type="button" class="button button-primary" onclick="window.print();"><?php echo esc_html( self::label( 'print', $language ) ); ?></button></div>
            </div>

            <div style="display:flex;gap:16px;flex-wrap:wrap;margin:18px 0 24px;padding:12px;background:#f6f7f7;">
                <strong><?php echo esc_html( self::label( 'reference', $language ) . ': ' . (string) ( $context['minute'] ?? 1 ) . "' · " . (string) ( $context['our_score'] ?? 0 ) . '-' . (string) ( $context['opponent_score'] ?? 0 ) . ' · ' . (string) ( $context['our_players'] ?? 11 ) . 'v' . (string) ( $context['opponent_players'] ?? 11 ) ); ?></strong>
                <span><?php echo esc_html( self::label( 'initial_plan', $language ) . ': ' . (string) ( $active['label'] ?? $active['id'] ?? 'A' ) ); ?></span>
                <span><?php echo esc_html( self::label( 'coverage', $language ) . ': ' . (string) ( $brief['coverage']['effective_matches'] ?? 0 ) ); ?></span>
            </div>

            <h3><?php echo esc_html( self::label( 'top_priorities', $language ) ); ?></h3>
            <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:24px;">
                <?php foreach ( $brief['top_priorities'] ?? array() as $priority ) : ?>
                    <div style="border:1px solid #dcdcde;padding:14px;break-inside:avoid;">
                        <div style="font-size:12px;color:#646970;">#<?php echo esc_html( (string) ( $priority['rank'] ?? '' ) ); ?> · <?php echo esc_html( is_numeric( $priority['priority_score'] ?? null ) ? (string) $priority['priority_score'] . '/100' : '—' ); ?></div>
                        <strong style="display:block;margin:5px 0;"><?php echo esc_html( (string) ( $priority['scenario_name'] ?? '' ) ); ?></strong>
                        <div><?php echo esc_html( (string) ( $priority['instruction'] ?? '' ) ); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <h3><?php echo esc_html( self::label( 'plans', $language ) ); ?></h3>
            <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;">
                <?php foreach ( array( 'plan_a', 'plan_b', 'plan_c' ) as $key ) : ?>
                    <?php self::plan_card( $brief['plans'][ $key ] ?? array(), $language ); ?>
                <?php endforeach; ?>
            </div>

            <?php if ( ! empty( $brief['evidence_watch']['material_signals'] ) ) : ?>
                <div style="margin-top:20px;border:1px solid #d63638;padding:14px;break-inside:avoid;">
                    <h3 style="margin-top:0;"><?php echo esc_html( self::label( 'evidence_watch', $language ) ); ?></h3>
                    <p><?php echo esc_html( (string) ( $brief['evidence_watch']['note'] ?? '' ) ); ?></p>
                    <ul style="margin-left:20px;list-style:disc;">
                        <?php foreach ( $brief['evidence_watch']['material_signals'] as $signal ) : ?>
                            <li><strong><?php echo esc_html( 'Plan ' . (string) ( $signal['plan_id'] ?? '' ) . ': ' . (string) ( $signal['title'] ?? '' ) ); ?></strong><?php if ( ! empty( $signal['detail'] ) ) : ?> — <?php echo esc_html( (string) $signal['detail'] ); ?><?php endif; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:24px;">
                <?php self::checklist( self::label( 'opening_checks', $language ), $brief['phase_checks']['opening_15_minutes'] ?? array() ); ?>
                <?php self::checklist( self::label( 'halftime_checks', $language ), $brief['phase_checks']['halftime_checks'] ?? array() ); ?>
            </div>

            <h3 style="margin-top:24px;"><?php echo esc_html( self::label( 'decision_tree', $language ) ); ?></h3>
            <table class="widefat striped">
                <thead><tr><th>#</th><th><?php echo esc_html( self::label( 'condition', $language ) ); ?></th><th><?php echo esc_html( self::label( 'target_plan', $language ) ); ?></th></tr></thead>
                <tbody><?php foreach ( $brief['decision_tree'] ?? array() as $row ) : ?><tr><td><?php echo esc_html( (string) ( $row['order'] ?? '' ) ); ?></td><td><?php echo esc_html( (string) ( $row['condition'] ?? '' ) ); ?></td><td><?php echo esc_html( 'Plan ' . (string) ( $row['target_plan'] ?? '' ) ); ?></td></tr><?php endforeach; ?></tbody>
            </table>

            <p style="margin-top:18px;font-size:12px;color:#646970;"><?php echo esc_html( self::label( 'guardrail', $language ) ); ?></p>

            <details class="tt-screen-only" style="margin-top:18px;"><summary><strong><?php echo esc_html( self::label( 'technical', $language ) ); ?></strong></summary><pre style="white-space:pre-wrap;background:#f6f7f7;padding:14px;overflow:auto;"><?php echo esc_html( wp_json_encode( $brief, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre></details>
        </section>
        <?php
    }

    private static function plan_card( array $plan, string $language ): void {
        $evidence = is_array( $plan['evidence'] ?? null ) ? $plan['evidence'] : array();
        $primary = is_array( $evidence['primary'] ?? null ) ? $evidence['primary'] : null;
        $alternative = is_array( $evidence['alternative'] ?? null ) ? $evidence['alternative'] : null;
        $conflicts = is_array( $evidence['conflict_signals'] ?? null ) ? $evidence['conflict_signals'] : array();
        ?>
        <article style="border:1px solid #dcdcde;padding:14px;break-inside:avoid;">
            <div style="display:flex;justify-content:space-between;gap:8px;"><h4 style="margin:0;"><?php echo esc_html( (string) ( $plan['name'] ?? 'Plan ' . (string) ( $plan['id'] ?? '' ) ) ); ?></h4><span><?php echo esc_html( strtoupper( (string) ( $plan['status'] ?? '' ) ) ); ?></span></div>
            <?php if ( ! empty( $plan['scenario_name'] ) ) : ?><p><strong><?php echo esc_html( (string) $plan['scenario_name'] ); ?></strong><?php if ( is_numeric( $plan['risk_score'] ?? null ) ) : ?> · <?php echo esc_html( (string) $plan['risk_score'] . '/100' ); ?><?php endif; ?></p><?php endif; ?>
            <?php if ( ! empty( $plan['purpose'] ) ) : ?><p><?php echo esc_html( (string) $plan['purpose'] ); ?></p><?php endif; ?>
            <?php if ( ! empty( $plan['trigger'] ) ) : ?><p><strong><?php echo esc_html( self::label( 'trigger', $language ) ); ?>:</strong> <?php echo esc_html( (string) $plan['trigger'] ); ?></p><?php endif; ?>
            <ul style="margin-left:18px;list-style:disc;">
                <?php foreach ( $plan['instructions'] ?? array() as $instruction ) : ?><li><?php echo esc_html( (string) $instruction ); ?></li><?php endforeach; ?>
            </ul>
            <hr>
            <div style="font-size:12px;text-transform:uppercase;color:#646970;"><?php echo esc_html( self::label( 'historical_evidence', $language ) ); ?></div>
            <?php if ( $primary ) : ?>
                <p><strong><?php echo esc_html( self::label( 'primary', $language ) . ': ' . (string) ( $primary['scenario_name'] ?? $primary['scenario_id'] ?? '-' ) ); ?></strong><br><?php echo esc_html( self::score_line( $primary, $language ) ); ?></p>
                <?php if ( $alternative ) : ?><p><?php echo esc_html( self::label( 'alternative', $language ) . ': ' . (string) ( $alternative['scenario_name'] ?? $alternative['scenario_id'] ?? '-' ) ); ?></p><?php endif; ?>
                <p><strong><?php echo esc_html( self::label( 'tensions', $language ) . ': ' . self::conflict_label( $conflicts, $language ) ); ?></strong></p>
            <?php else : ?>
                <p><?php echo esc_html( self::label( 'no_precedent', $language ) ); ?></p>
            <?php endif; ?>
        </article>
        <?php
    }

    private static function checklist( string $title, array $items ): void {
        ?>
        <div style="border:1px solid #dcdcde;padding:14px;break-inside:avoid;"><h3 style="margin-top:0;"><?php echo esc_html( $title ); ?></h3><ul style="list-style:none;margin-left:0;"><?php foreach ( $items as $item ) : ?><li style="margin-bottom:7px;">☐ <?php echo esc_html( (string) $item ); ?></li><?php endforeach; ?></ul></div>
        <?php
    }

    private static function score_line( array $candidate, string $language ): string {
        $scores = is_array( $candidate['scores'] ?? null ) ? $candidate['scores'] : array();
        return self::label( 'suitability', $language ) . ' ' . self::score( $scores['evidence_suitability'] ?? null )
            . ' · ' . self::label( 'quality', $language ) . ' ' . self::score( $scores['evidence_quality'] ?? null )
            . ' · ' . self::label( 'similarity', $language ) . ' ' . self::score( $scores['opponent_similarity'] ?? null );
    }

    private static function score( $value ): string {
        return is_numeric( $value ) ? (string) $value . '/100' : '—';
    }

    private static function conflict_label( array $conflicts, string $language ): string {
        $count = (int) ( $conflicts['signal_count'] ?? 0 );
        if ( 0 === $count ) return self::label( 'none', $language );
        return 'material' === (string) ( $conflicts['highest_severity'] ?? '' )
            ? self::label( 'material', $language ) . ' (' . $count . ')'
            : self::label( 'review', $language ) . ' (' . $count . ')';
    }

    private static function team_select( string $name, string $id, array $teams, int $selected_id, int $default_index ): void {
        ?>
        <select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" required>
            <?php foreach ( $teams as $index => $team ) : ?>
                <?php $value = (int) $team['source_team_id']; $selected = $selected_id > 0 ? $selected_id === $value : $index === $default_index; ?>
                <option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $selected, true ); ?>><?php echo esc_html( (string) $team['team_name'] ); ?></option>
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

    private static function print_styles(): void {
        ?><style>
        @media print {
            #adminmenumain,#wpadminbar,#screen-meta-links,.notice,.tt-screen-only,#wpfooter{display:none!important}
            #wpcontent,#wpbody-content{margin:0!important;padding:0!important}
            .tactitwin-staff-brief-page{margin:0!important}
            .tt-staff-sheet{border:0!important;max-width:none!important;margin:0!important;padding:10mm!important}
            .tt-staff-sheet table{font-size:10px}
            @page{margin:8mm}
        }
        </style><?php
    }

    private static function label( string $key, string $language ): string {
        $labels = array(
            'en' => array(
                'page_title'=>'TactiTwin Staff Brief','page_intro'=>'Generate a compact pre-match staff sheet from the validated Match Plan, Plan A/B/C and historical-evidence layers.','need_teams'=>'Import a competition/season first so at least two teams are available.','language'=>'Language','our_team'=>'Our team','opponent'=>'Opponent','recent_matches'=>'Recent matches','sample_note'=>'Missing event-data matches are imported automatically where available.','generate'=>'Generate staff brief','document_title'=>'Match-day staff brief','print'=>'Print / Save as PDF','reference'=>'Reference context','initial_plan'=>'Initial plan','coverage'=>'Coverage','top_priorities'=>'Top 3 tactical priorities','plans'=>'Plan A / B / C','evidence_watch'=>'Material evidence tensions','opening_checks'=>'Opening 15′ checklist','halftime_checks'=>'Halftime checklist','decision_tree'=>'Decision tree','condition'=>'Condition','target_plan'=>'Target','guardrail'=>'This sheet reorganizes existing TactiTwin outputs for staff use. It creates no new tactical score, does not change priority order and cannot activate a plan.','technical'=>'Technical source payload','trigger'=>'Trigger','historical_evidence'=>'Historical evidence','primary'=>'Primary precedent','alternative'=>'Alternative','tensions'=>'Evidence tensions','no_precedent'=>'No exact-context historical precedent is available for this plan.','suitability'=>'Suitability','quality'=>'Quality','similarity'=>'Similarity','none'=>'none','review'=>'review','material'=>'material',
            ),
            'es' => array(
                'page_title'=>'TactiTwin Staff Brief','page_intro'=>'Genera una hoja prepartido compacta para el staff a partir del Match Plan, Plan A/B/C y las capas de evidencia ya validadas.','need_teams'=>'Importa primero una competición/temporada para disponer de al menos dos equipos.','language'=>'Idioma','our_team'=>'Nuestro equipo','opponent'=>'Rival','recent_matches'=>'Partidos recientes','sample_note'=>'Se importarán automáticamente los partidos con eventos que falten cuando estén disponibles.','generate'=>'Generar staff brief','document_title'=>'Brief de staff para día de partido','print'=>'Imprimir / Guardar como PDF','reference'=>'Contexto de referencia','initial_plan'=>'Plan inicial','coverage'=>'Cobertura','top_priorities'=>'Top 3 prioridades tácticas','plans'=>'Plan A / B / C','evidence_watch'=>'Tensiones materiales de evidencia','opening_checks'=>'Checklist primeros 15′','halftime_checks'=>'Checklist descanso','decision_tree'=>'Árbol de decisión','condition'=>'Condición','target_plan'=>'Destino','guardrail'=>'Esta hoja reorganiza resultados existentes de TactiTwin para uso del staff. No crea scores tácticos nuevos, no cambia el orden de prioridades y no puede activar un plan.','technical'=>'Payload técnico de origen','trigger'=>'Disparador','historical_evidence'=>'Evidencia histórica','primary'=>'Precedente principal','alternative'=>'Alternativa','tensions'=>'Tensiones de evidencia','no_precedent'=>'No hay precedente histórico de contexto exacto para este plan.','suitability'=>'Idoneidad','quality'=>'Calidad','similarity'=>'Similitud','none'=>'ninguna','review'=>'revisar','material'=>'material',
            ),
        );
        return $labels[ $language ][ $key ] ?? $labels['en'][ $key ] ?? $key;
    }
}
