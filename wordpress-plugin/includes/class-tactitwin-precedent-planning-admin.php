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
        <div class="wrap" style="max-width:1100px;margin-top:28px;">
            <hr>
            <h2><?php echo esc_html( self::label( 'catalog_title', $language ) ); ?></h2>
            <p><?php echo esc_html( self::label( 'catalog_intro', $language ) ); ?></p>
            <?php foreach ( $catalog['summary'] ?? array() as $summary ) : ?><p><strong><?php echo esc_html( (string) $summary ); ?></strong></p><?php endforeach; ?>
            <?php if ( ! empty( $catalog['quality_summary']['calibrated_items'] ) ) : ?>
                <p><strong><?php echo esc_html( self::label( 'quality_mean', $language ) . ': ' . (string) $catalog['quality_summary']['mean_quality_score'] . '/100' ); ?></strong></p>
            <?php endif; ?>
            <?php foreach ( $catalog['scenarios'] ?? array() as $scenario ) : ?>
                <?php if ( empty( $scenario['contexts'] ) ) { continue; } ?>
                <div style="background:#fff;border:1px solid #dcdcde;padding:16px 18px;margin:14px 0;">
                    <h3 style="margin-top:0;"><?php echo esc_html( (string) ( $scenario['scenario_name'] ?? '' ) ); ?></h3>
                    <p><?php echo esc_html( self::label( 'current_relevance', $language ) . ': ' . (string) ( $scenario['current_scan']['relevance'] ?? 0 ) . '/100' ); ?></p>
                    <table class="widefat striped">
                        <thead><tr><th><?php echo esc_html( self::label( 'context', $language ) ); ?></th><th><?php echo esc_html( self::label( 'plan', $language ) ); ?></th><th><?php echo esc_html( self::label( 'quality', $language ) ); ?></th><th><?php echo esc_html( self::label( 'sample', $language ) ); ?></th><th><?php echo esc_html( self::label( 'signals', $language ) ); ?></th></tr></thead>
                        <tbody>
                            <?php foreach ( $scenario['contexts'] as $context ) : ?>
                                <tr>
                                    <td><?php echo esc_html( self::context_label( $context, $language ) ); ?></td>
                                    <td><?php echo esc_html( (string) ( $context['to_plan'] ?? '-' ) . ' / ' . (string) ( $context['to_mode'] ?? '-' ) ); ?></td>
                                    <td><?php echo esc_html( self::quality_label( $context['evidence_quality'] ?? array() ) ); ?></td>
                                    <td><?php echo esc_html( (string) ( $context['sample']['maturity_label'] ?? $context['sample']['maturity'] ?? '-' ) . ' · ' . (string) ( $context['sample']['cleaner'] ?? 0 ) . '/' . (string) ( $context['sample']['context_comparable'] ?? 0 ) ); ?></td>
                                    <td><?php echo esc_html( self::metric_summary( $context['metrics'] ?? array() ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
            <p class="description"><?php echo esc_html( self::label( 'guardrail_quality', $language ) ); ?></p>
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
                        <?php if ( empty( $precedents['items'] ) ) : ?>
                            <p class="description"><?php echo esc_html( self::label( 'no_items', $language ) ); ?></p>
                        <?php else : ?>
                            <ul style="list-style:disc;padding-left:20px;">
                                <?php foreach ( $precedents['items'] as $item ) : ?>
                                    <li style="margin-bottom:8px;"><strong><?php echo esc_html( (string) ( $item['scenario_name'] ?? $item['scenario_id'] ?? '' ) ); ?></strong> — <?php echo esc_html( self::quality_label( $item['evidence_quality'] ?? array() ) . ' · ' . self::metric_summary( $item['metrics'] ?? array() ) ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="description"><?php echo esc_html( self::label( 'guardrail_quality', $language ) ); ?></p>
        </div>
        <?php
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

    private static function quality_label( array $quality ): string {
        if ( ! isset( $quality['quality_score'] ) ) {
            return '—';
        }
        return (string) ( $quality['quality_label'] ?? $quality['quality_level'] ?? '' ) . ' · ' . (string) $quality['quality_score'] . '/100';
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
                'current_relevance'=>'Current scenario relevance','quality'=>'Evidence quality','quality_mean'=>'Mean evidence quality',
                'context'=>'Historical context','plan'=>'Destination plan','sample'=>'Sample','signals'=>'Median historical deltas',
                'playbook_title'=>'Exact-context precedents for Plan A/B/C',
                'playbook_intro'=>'Only historical transitions into the same destination plan are shown. Plan B also requires the current live scenario.',
                'no_items'=>'No exact-context precedents for this plan.',
                'guardrail_quality'=>'Evidence quality calibrates trust in historical precedent only. It does not change tactical scores, priorities, active plan or recommendations.',
            ),
            'es'=>array(
                'catalog_title'=>'Precedentes tácticos históricos',
                'catalog_intro'=>'La evidencia prepartido de intervenciones anteriores se mantiene separada por franja temporal, marcador y estado numérico.',
                'current_relevance'=>'Relevancia actual del escenario','quality'=>'Calidad de evidencia','quality_mean'=>'Calidad media de evidencia',
                'context'=>'Contexto histórico','plan'=>'Plan de destino','sample'=>'Muestra','signals'=>'Medianas históricas de delta',
                'playbook_title'=>'Precedentes de contexto exacto para Plan A/B/C',
                'playbook_intro'=>'Solo se muestran transiciones históricas hacia el mismo plan de destino. Plan B exige además el escenario vivo actual.',
                'no_items'=>'No hay precedentes de contexto exacto para este plan.',
                'guardrail_quality'=>'La calidad de evidencia calibra únicamente la confianza en el precedente histórico. No cambia scores tácticos, prioridades, plan activo ni recomendaciones.',
            ),
        );
        return $labels[ $language ][ $key ] ?? $labels['en'][ $key ] ?? $key;
    }
}
