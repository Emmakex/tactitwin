<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Precedent_Planning_Admin {
    public static function register(): void {
        add_action( 'admin_footer', array( self::class, 'render' ) );
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $page = sanitize_key( (string) ( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'tactitwin-match-plan' === $page ) {
            self::render_match_plan_panel();
        } elseif ( 'tactitwin-live-playbook' === $page ) {
            self::render_live_panel();
        }
    }

    private static function render_match_plan_panel(): void {
        $stored = get_transient( 'tactitwin_match_plan_' . get_current_user_id() );
        if ( ! is_array( $stored ) || empty( $stored['plan'] ) ) {
            return;
        }
        $base = $stored['plan'];
        $language = TactiTwin_I18n::normalize_language( (string) ( $stored['language'] ?? $base['language'] ?? 'en' ) );
        $team_id = (int) ( $base['team']['source_id'] ?? 0 );
        $opponent_id = (int) ( $base['opponent']['source_id'] ?? 0 );
        $limit = max( 1, min( 10, (int) ( $base['coverage']['effective_matches'] ?? 5 ) ) );
        if ( $team_id <= 0 || $opponent_id <= 0 ) {
            return;
        }

        try {
            $plan = ( new TactiTwin_Precedent_Aware_Match_Plan_Service() )->evaluate(
                $team_id,
                $opponent_id,
                $limit,
                $language,
                3
            );
            self::catalog_panel( $plan['historical_precedent_catalog'] ?? array(), $language );
        } catch ( Throwable $e ) {
            self::error_panel( self::label( 'catalog_title', $language ), $e->getMessage() );
        }
    }

    private static function render_live_panel(): void {
        $stored = get_transient( 'tactitwin_live_playbook_' . get_current_user_id() );
        if ( ! is_array( $stored ) || empty( $stored['form'] ) ) {
            return;
        }
        $form = $stored['form'];
        $language = TactiTwin_I18n::normalize_language( (string) ( $stored['language'] ?? 'en' ) );

        try {
            $playbook = ( new TactiTwin_Precedent_Aware_Contingency_Service() )->live_evaluate(
                (int) ( $form['team_id'] ?? 0 ),
                (int) ( $form['opponent_id'] ?? 0 ),
                (int) ( $form['our_score'] ?? 0 ),
                (int) ( $form['opponent_score'] ?? 0 ),
                (int) ( $form['minute'] ?? 1 ),
                is_array( $form['observations'] ?? null ) ? $form['observations'] : array(),
                (int) ( $form['sample'] ?? 5 ),
                $language,
                (int) ( $form['our_players'] ?? 11 ),
                (int) ( $form['opponent_players'] ?? 11 ),
                5
            );
            self::playbook_panel( $playbook, $language );
        } catch ( Throwable $e ) {
            self::error_panel( self::label( 'playbook_title', $language ), $e->getMessage() );
        }
    }

    private static function catalog_panel( array $catalog, string $language ): void {
        ?>
        <div class="wrap" style="max-width:1280px;margin-top:28px;">
            <hr>
            <h2><?php echo esc_html( self::label( 'catalog_title', $language ) ); ?></h2>
            <p><?php echo esc_html( self::label( 'catalog_intro', $language ) ); ?></p>
            <?php foreach ( $catalog['summary'] ?? array() as $summary ) : ?><p><strong><?php echo esc_html( (string) $summary ); ?></strong></p><?php endforeach; ?>
            <div style="display:flex;gap:24px;flex-wrap:wrap;">
                <?php if ( ! empty( $catalog['quality_summary']['calibrated_items'] ) ) : ?>
                    <p><strong><?php echo esc_html( self::label( 'quality_mean', $language ) . ': ' . (string) $catalog['quality_summary']['mean_quality_score'] . '/100' ); ?></strong></p>
                <?php endif; ?>
                <?php if ( ! empty( $catalog['opponent_similarity_summary']['calibrated_items'] ) ) : ?>
                    <p><strong><?php echo esc_html( self::label( 'similarity_mean', $language ) . ': ' . (string) $catalog['opponent_similarity_summary']['mean_similarity_score'] . '/100' ); ?></strong></p>
                <?php endif; ?>
                <?php if ( isset( $catalog['conflict_signal_summary']['scenarios_with_material_signals'] ) ) : ?>
                    <p><strong><?php echo esc_html( self::label( 'material_scenarios', $language ) . ': ' . (string) $catalog['conflict_signal_summary']['scenarios_with_material_signals'] ); ?></strong></p>
                <?php endif; ?>
            </div>
            <?php foreach ( $catalog['scenarios'] ?? array() as $scenario ) : ?>
                <?php if ( empty( $scenario['contexts'] ) ) { continue; } ?>
                <div style="background:#fff;border:1px solid #dcdcde;padding:16px 18px;margin:14px 0;">
                    <h3 style="margin-top:0;"><?php echo esc_html( (string) ( $scenario['scenario_name'] ?? '' ) ); ?></h3>
                    <p><?php echo esc_html( self::label( 'current_relevance', $language ) . ': ' . (string) ( $scenario['current_scan']['relevance'] ?? 0 ) . '/100' ); ?></p>
                    <?php if ( isset( $scenario['ranking_robustness']['top_stability_pct'] ) ) : ?>
                        <p><strong><?php echo esc_html( self::robustness_summary_label( $scenario['ranking_robustness'], $language ) ); ?></strong></p>
                    <?php endif; ?>
                    <?php self::decision_brief_card( $scenario['decision_brief'] ?? array(), $language ); ?>
                    <table class="widefat striped">
                        <thead><tr><th><?php echo esc_html( self::label( 'context', $language ) ); ?></th><th><?php echo esc_html( self::label( 'plan', $language ) ); ?></th><th><?php echo esc_html( self::label( 'suitability', $language ) ); ?></th><th><?php echo esc_html( self::label( 'robustness', $language ) ); ?></th><th><?php echo esc_html( self::label( 'quality', $language ) ); ?></th><th><?php echo esc_html( self::label( 'similarity', $language ) ); ?></th><th><?php echo esc_html( self::label( 'sample', $language ) ); ?></th><th><?php echo esc_html( self::label( 'signals', $language ) ); ?></th></tr></thead>
                        <tbody>
                            <?php foreach ( $scenario['contexts'] as $context ) : ?>
                                <tr>
                                    <td><?php echo esc_html( self::context_label( $context, $language ) ); ?></td>
                                    <td><?php echo esc_html( (string) ( $context['to_plan'] ?? '-' ) . ' / ' . (string) ( $context['to_mode'] ?? '-' ) ); ?></td>
                                    <td><?php echo esc_html( self::suitability_label( $context['evidence_suitability'] ?? array() ) ); ?></td>
                                    <td><?php echo esc_html( self::robustness_item_label( $context['ranking_robustness'] ?? array(), $language ) ); ?></td>
                                    <td><?php echo esc_html( self::quality_label( $context['evidence_quality'] ?? array() ) ); ?></td>
                                    <td><?php echo esc_html( self::similarity_label( $context['opponent_context_similarity'] ?? array() ) ); ?></td>
                                    <td><?php echo esc_html( (string) ( $context['sample']['maturity_label'] ?? $context['sample']['maturity'] ?? '-' ) . ' · ' . (string) ( $context['sample']['cleaner'] ?? 0 ) . '/' . (string) ( $context['sample']['context_comparable'] ?? 0 ) ); ?></td>
                                    <td><?php echo esc_html( self::metric_summary( $context['metrics'] ?? array() ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
            <p class="description"><?php echo esc_html( self::label( 'guardrail_evidence', $language ) ); ?></p>
        </div>
        <?php
    }

    private static function playbook_panel( array $playbook, string $language ): void {
        $plans = $playbook['plans'] ?? array();
        ?>
        <div class="wrap" style="max-width:1100px;margin-top:28px;">
            <hr>
            <h2><?php echo esc_html( self::label( 'playbook_title', $language ) ); ?></h2>
            <p><?php echo esc_html( self::label( 'playbook_intro', $language ) ); ?></p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:14px;">
                <?php foreach ( array( 'plan_a'=>'A', 'plan_b'=>'B', 'plan_c'=>'C' ) as $key => $id ) : ?>
                    <?php $precedents = $plans[ $key ]['historical_precedents'] ?? array(); ?>
                    <div style="background:#fff;border:1px solid #dcdcde;padding:16px;">
                        <h3 style="margin-top:0;">Plan <?php echo esc_html( $id ); ?></h3>
                        <?php foreach ( $precedents['summary'] ?? array() as $summary ) : ?><p><?php echo esc_html( (string) $summary ); ?></p><?php endforeach; ?>
                        <?php self::decision_brief_card( $precedents['decision_brief'] ?? array(), $language, true ); ?>
                        <?php if ( empty( $precedents['items'] ) ) : ?>
                            <p class="description"><?php echo esc_html( self::label( 'no_items', $language ) ); ?></p>
                        <?php else : ?>
                            <ul style="list-style:disc;padding-left:20px;">
                                <?php foreach ( $precedents['items'] as $item ) : ?>
                                    <li style="margin-bottom:8px;"><strong><?php echo esc_html( (string) ( $item['scenario_name'] ?? $item['scenario_id'] ?? '' ) ); ?></strong> — <?php echo esc_html( self::suitability_label( $item['evidence_suitability'] ?? array() ) . ' · ' . self::robustness_item_label( $item['ranking_robustness'] ?? array(), $language ) . ' · ' . self::quality_label( $item['evidence_quality'] ?? array() ) . ' · ' . self::similarity_label( $item['opponent_context_similarity'] ?? array() ) . ' · ' . self::metric_summary( $item['metrics'] ?? array() ) ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="description"><?php echo esc_html( self::label( 'guardrail_evidence', $language ) ); ?></p>
        </div>
        <?php
    }

    private static function decision_brief_card( array $brief, string $language, bool $compact = false ): void {
        if ( 'available' !== (string) ( $brief['status'] ?? '' ) || empty( $brief['primary'] ) ) {
            return;
        }
        $primary = $brief['primary'];
        $alternative = is_array( $brief['alternative'] ?? null ) ? $brief['alternative'] : null;
        $scores = is_array( $primary['scores'] ?? null ) ? $primary['scores'] : array();
        $conflicts = is_array( $brief['conflict_signals'] ?? null ) ? $brief['conflict_signals'] : array();
        ?>
        <div style="background:#f6f7f7;border-left:4px solid #2271b1;padding:12px 14px;margin:12px 0 16px;">
            <strong><?php echo esc_html( self::label( 'decision_brief', $language ) ); ?></strong>
            <p style="margin:8px 0 4px;"><strong><?php echo esc_html( self::label( 'primary_precedent', $language ) . ': ' . (string) ( $primary['scenario_name'] ?? $primary['scenario_id'] ?? '-' ) ); ?></strong></p>
            <p style="margin:4px 0;">
                <?php echo esc_html(
                    self::label( 'suitability', $language ) . ' ' . self::score_text( $scores['evidence_suitability'] ?? null ) . ' · '
                    . self::label( 'quality', $language ) . ' ' . self::score_text( $scores['evidence_quality'] ?? null ) . ' · '
                    . self::label( 'similarity', $language ) . ' ' . self::score_text( $scores['opponent_similarity'] ?? null )
                ); ?>
            </p>
            <?php if ( ! empty( $conflicts ) && 'unavailable' !== (string) ( $conflicts['status'] ?? '' ) ) : ?>
                <p style="margin:4px 0;"><strong><?php echo esc_html( self::conflict_summary_label( $conflicts, $language ) ); ?></strong></p>
                <?php if ( ! empty( $conflicts['signals'] ) ) : ?>
                    <details style="margin:6px 0;"><summary><?php echo esc_html( self::label( 'conflict_details', $language ) ); ?></summary><ul style="margin-left:20px;list-style:disc;">
                        <?php foreach ( array_slice( $conflicts['signals'], 0, $compact ? 2 : 5 ) as $signal ) : ?>
                            <li><strong><?php echo esc_html( (string) ( $signal['title'] ?? $signal['id'] ?? '' ) ); ?></strong><?php if ( ! empty( $signal['detail'] ) ) : ?> — <?php echo esc_html( (string) $signal['detail'] ); ?><?php endif; ?></li>
                        <?php endforeach; ?>
                    </ul></details>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ( ! $compact && ! empty( $primary['why_it_fits'] ) ) : ?>
                <ul style="margin:6px 0 6px 20px;list-style:disc;">
                    <?php foreach ( array_slice( $primary['why_it_fits'], 0, 4 ) as $reason ) : ?><li><?php echo esc_html( (string) $reason ); ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ( $alternative ) : ?>
                <p style="margin:6px 0;"><strong><?php echo esc_html( self::label( 'alternative', $language ) . ': ' . (string) ( $alternative['scenario_name'] ?? $alternative['scenario_id'] ?? '-' ) ); ?></strong><?php if ( ! empty( $brief['comparison']['alternative_strength_label'] ) ) : ?> — <?php echo esc_html( (string) $brief['comparison']['alternative_strength_label'] ); ?><?php endif; ?></p>
            <?php endif; ?>
            <?php if ( ! empty( $brief['what_could_invalidate'] ) ) : ?>
                <details style="margin-top:6px;"><summary><?php echo esc_html( self::label( 'invalidators', $language ) ); ?></summary><ul style="margin-left:20px;list-style:disc;">
                    <?php foreach ( array_slice( $brief['what_could_invalidate'], 0, $compact ? 3 : 5 ) as $flag ) : ?><li><?php echo esc_html( (string) $flag ); ?></li><?php endforeach; ?>
                </ul></details>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function conflict_summary_label( array $conflicts, string $language ): string {
        $count = (int) ( $conflicts['signal_count'] ?? 0 );
        if ( 0 === $count ) {
            return self::label( 'conflict_title', $language ) . ': ' . self::label( 'conflict_none', $language );
        }
        $severity = (string) ( $conflicts['highest_severity'] ?? 'watch' );
        $severity_label = 'material' === $severity
            ? self::label( 'conflict_material', $language )
            : self::label( 'conflict_watch', $language );
        return self::label( 'conflict_title', $language ) . ': ' . $severity_label . ' · ' . $count . ' ' . self::label( 'conflict_count', $language );
    }

    private static function score_text( $value ): string {
        return is_numeric( $value ) ? (string) $value . '/100' : '—';
    }

    private static function context_label( array $context, string $language ): string {
        return sprintf(
            '%s · %s · %s',
            (string) ( $context['minute_band'] ?? '-' ),
            self::state_label( (string) ( $context['score_state'] ?? '' ), $language ),
            self::manpower_label( (string) ( $context['manpower_state'] ?? '' ), $language )
        );
    }

    private static function metric_summary( array $metrics ): string {
        $parts = array();
        foreach ( array_slice( $metrics, 0, 3 ) as $metric ) {
            $name = str_replace( '_', ' ', (string) ( $metric['metric'] ?? '' ) );
            $median = $metric['median_delta'] ?? null;
            if ( '' === $name || ! is_numeric( $median ) ) {
                continue;
            }
            $parts[] = $name . ': ' . ( (float) $median > 0 ? '+' : '' ) . (string) $median;
        }
        return ! empty( $parts ) ? implode( ' · ', $parts ) : '—';
    }

    private static function suitability_label( array $suitability ): string {
        if ( ! isset( $suitability['suitability_score'] ) || ! is_numeric( $suitability['suitability_score'] ) ) {
            return '—';
        }
        return (string) ( $suitability['suitability_label'] ?? $suitability['suitability_level'] ?? '' ) . ' · ' . (string) $suitability['suitability_score'] . '/100';
    }

    private static function robustness_summary_label( array $robustness, string $language ): string {
        $stability = $robustness['top_stability_pct'] ?? null;
        if ( ! is_numeric( $stability ) ) {
            return '—';
        }
        $level = str_replace( '-', ' ', (string) ( $robustness['robustness_level'] ?? '' ) );
        $margin = $robustness['baseline_top_margin'] ?? null;
        $text = self::label( 'robustness', $language ) . ': ' . $level . ' · ' . (string) $stability . '%';
        if ( is_numeric( $margin ) ) {
            $text .= ' · ' . self::label( 'top_margin', $language ) . ' ' . (string) $margin;
        }
        return $text;
    }

    private static function robustness_item_label( array $robustness, string $language ): string {
        $best = $robustness['best_rank'] ?? null;
        $worst = $robustness['worst_rank'] ?? null;
        if ( ! is_numeric( $best ) || ! is_numeric( $worst ) ) {
            return '—';
        }
        $top = $robustness['top_rank_frequency_pct'] ?? 0;
        return self::label( 'rank_range', $language ) . ' ' . (string) $best . '–' . (string) $worst . ' · ' . self::label( 'top_frequency', $language ) . ' ' . (string) $top . '%';
    }

    private static function quality_label( array $quality ): string {
        if ( ! isset( $quality['quality_score'] ) ) {
            return '—';
        }
        return (string) ( $quality['quality_label'] ?? $quality['quality_level'] ?? '' ) . ' · ' . (string) $quality['quality_score'] . '/100';
    }

    private static function similarity_label( array $similarity ): string {
        if ( ! isset( $similarity['similarity_score'] ) || ! is_numeric( $similarity['similarity_score'] ) ) {
            return '—';
        }
        return (string) ( $similarity['similarity_label'] ?? $similarity['similarity_level'] ?? '' ) . ' · ' . (string) $similarity['similarity_score'] . '/100';
    }

    private static function state_label( string $state, string $language ): string {
        $labels = array(
            'en'=>array('leading'=>'leading','drawing'=>'drawing','trailing'=>'trailing'),
            'es'=>array('leading'=>'ganando','drawing'=>'empatando','trailing'=>'perdiendo'),
        );
        return $labels[ $language ][ $state ] ?? $state;
    }

    private static function manpower_label( string $state, string $language ): string {
        $labels = array(
            'en'=>array('superior'=>'superior','equal'=>'equal','inferior'=>'inferior'),
            'es'=>array('superior'=>'superior','equal'=>'igual','inferior'=>'inferior'),
        );
        return $labels[ $language ][ $state ] ?? $state;
    }

    private static function error_panel( string $title, string $message ): void {
        ?><div class="wrap" style="max-width:1100px;margin-top:28px;"><hr><h2><?php echo esc_html( $title ); ?></h2><div class="notice notice-warning inline"><p><?php echo esc_html( $message ); ?></p></div></div><?php
    }

    private static function label( string $key, string $language ): string {
        $labels = array(
            'en'=>array(
                'catalog_title'=>'Historical tactical precedents',
                'catalog_intro'=>'Pre-match evidence from prior interventions is kept separated by minute band, score state and manpower state.',
                'current_relevance'=>'Current scenario relevance','suitability'=>'Evidence suitability','robustness'=>'Ranking robustness','quality'=>'Evidence quality','quality_mean'=>'Mean evidence quality',
                'similarity'=>'Opponent similarity','similarity_mean'=>'Mean opponent similarity','top_margin'=>'top margin','rank_range'=>'ranks','top_frequency'=>'top',
                'context'=>'Historical context','plan'=>'Destination plan','sample'=>'Sample','signals'=>'Median historical deltas',
                'decision_brief'=>'Evidence decision brief','primary_precedent'=>'Primary precedent','alternative'=>'Alternative','invalidators'=>'What could invalidate this analogy',
                'conflict_title'=>'Evidence tensions','conflict_none'=>'none configured','conflict_watch'=>'review','conflict_material'=>'material','conflict_count'=>'signals','conflict_details'=>'Evidence tensions to review','material_scenarios'=>'Scenarios with material evidence tensions',
                'playbook_title'=>'Exact-context precedents for Plan A/B/C',
                'playbook_intro'=>'Only historical transitions into the same destination plan are shown. Plan B also requires the current live scenario.',
                'no_items'=>'No exact-context precedents for this plan.',
                'guardrail_evidence'=>'Suitability orders matched historical evidence; robustness tests order sensitivity; the decision brief summarizes it; conflict signals expose disagreements between existing evidence layers. None creates a conflict score or changes tactical scores, priorities, active plan or recommendations.',
            ),
            'es'=>array(
                'catalog_title'=>'Precedentes tácticos históricos',
                'catalog_intro'=>'La evidencia prepartido de intervenciones anteriores se mantiene separada por franja temporal, marcador y estado numérico.',
                'current_relevance'=>'Relevancia actual del escenario','suitability'=>'Idoneidad de evidencia','robustness'=>'Robustez del ranking','quality'=>'Calidad de evidencia','quality_mean'=>'Calidad media de evidencia',
                'similarity'=>'Similitud del rival','similarity_mean'=>'Similitud media del rival','top_margin'=>'margen del primero','rank_range'=>'rangos','top_frequency'=>'primero',
                'context'=>'Contexto histórico','plan'=>'Plan de destino','sample'=>'Muestra','signals'=>'Medianas históricas de delta',
                'decision_brief'=>'Brief de evidencia para decisión','primary_precedent'=>'Precedente principal','alternative'=>'Alternativa','invalidators'=>'Qué podría invalidar esta analogía',
                'conflict_title'=>'Tensiones de evidencia','conflict_none'=>'ninguna configurada','conflict_watch'=>'revisar','conflict_material'=>'material','conflict_count'=>'señales','conflict_details'=>'Tensiones de evidencia a revisar','material_scenarios'=>'Escenarios con tensiones materiales de evidencia',
                'playbook_title'=>'Precedentes de contexto exacto para Plan A/B/C',
                'playbook_intro'=>'Solo se muestran transiciones históricas hacia el mismo plan de destino. Plan B exige además el escenario vivo actual.',
                'no_items'=>'No hay precedentes de contexto exacto para este plan.',
                'guardrail_evidence'=>'La idoneidad ordena evidencia histórica coincidente; la robustez prueba la sensibilidad del orden; el brief lo resume; las señales de conflicto exponen discrepancias entre capas existentes. Ninguna crea un score de conflicto ni cambia scores tácticos, prioridades, plan activo o recomendaciones.',
            ),
        );
        return $labels[ $language ][ $key ] ?? $labels['en'][ $key ] ?? $key;
    }
}
