<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Intervention_Library_Admin {
    public static function register(): void {
        add_action( 'admin_menu', array( self::class, 'menu' ) );
    }

    public static function menu(): void {
        add_submenu_page(
            'tactitwin',
            __( 'Intervention Library', 'tactitwin' ),
            __( 'Intervention Library', 'tactitwin' ),
            'manage_options',
            'tactitwin-intervention-library',
            array( self::class, 'render' )
        );
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $language = TactiTwin_I18n::current_language();
        $session_service = new TactiTwin_Live_Session_Service();
        $sessions = $session_service->recent_sessions( 100 );
        $teams = array();
        foreach ( $sessions as $session ) {
            $id = (int) ( $session['team_source_id'] ?? 0 );
            if ( $id > 0 ) $teams[ $id ] = $id;
        }
        ksort( $teams );

        $team_id = absint( $_GET['team_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $team_id <= 0 && ! empty( $teams ) ) {
            $team_id = (int) reset( $teams );
        }
        $scenario = sanitize_key( wp_unslash( (string) ( $_GET['scenario_id'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $learning_scope = TactiTwin_Intervention_Library_Service::normalize_learning_scope(
            sanitize_key( wp_unslash( (string) ( $_GET['learning_scope'] ?? 'all' ) ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        );
        $library = null;
        $error = null;

        if ( $team_id > 0 ) {
            try {
                $library = ( new TactiTwin_Intervention_Library_Service() )->build( $team_id, $language, $scenario, 100, $learning_scope );
            } catch ( Throwable $e ) {
                $error = $e->getMessage();
            }
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( self::t( 'title', $language ) ); ?></h1>
            <p><?php echo esc_html( self::t( 'intro', $language ) ); ?></p>
            <?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>
            <?php self::filters( $teams, $team_id, $scenario, $learning_scope, $language ); ?>
            <?php if ( is_array( $library ) ) self::library( $library, $language ); ?>
        </div>
        <?php
    }

    private static function filters( array $teams, int $team_id, string $scenario, string $learning_scope, string $language ): void {
        ?>
        <form method="get" style="display:flex;gap:10px;align-items:end;margin:18px 0 24px;flex-wrap:wrap;">
            <input type="hidden" name="page" value="tactitwin-intervention-library">
            <label><strong><?php echo esc_html( self::t('team',$language) ); ?></strong><br>
                <select name="team_id">
                    <?php foreach ( $teams as $id ) : ?><option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( $team_id, $id ); ?>>#<?php echo esc_html( (string) $id ); ?></option><?php endforeach; ?>
                </select>
            </label>
            <label><strong><?php echo esc_html( self::t('scenario',$language) ); ?></strong><br>
                <input type="text" name="scenario_id" value="<?php echo esc_attr( $scenario ); ?>" placeholder="build-up-vs-high-press" style="min-width:250px;">
            </label>
            <label><strong><?php echo esc_html( self::t('learning_scope',$language) ); ?></strong><br>
                <select name="learning_scope">
                    <option value="all" <?php selected( $learning_scope, 'all' ); ?>><?php echo esc_html( self::t('scope_all',$language) ); ?></option>
                    <option value="signed" <?php selected( $learning_scope, 'signed' ); ?>><?php echo esc_html( self::t('scope_signed',$language) ); ?></option>
                </select>
            </label>
            <button class="button button-primary"><?php echo esc_html( self::t('apply',$language) ); ?></button>
        </form>
        <?php
    }

    private static function library( array $library, string $language ): void {
        $coverage = $library['coverage'] ?? array();
        $learning = $library['learning_review'] ?? array();
        ?>
        <div class="notice notice-info inline" style="max-width:1180px;"><p><?php echo esc_html( (string) ( $library['method']['note'] ?? '' ) ); ?></p></div>
        <div class="notice notice-info inline" style="max-width:1180px;"><p><?php echo esc_html( self::t('signoff_note',$language) ); ?></p></div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;max-width:1180px;margin:18px 0;">
            <?php self::card( self::t('sessions',$language), (string) ( $coverage['sessions_processed'] ?? 0 ), self::t('processed',$language) ); ?>
            <?php self::card( self::t('signed_sessions',$language), (string) ( $coverage['signed_review_sessions'] ?? 0 ), self::t('signed_reviewed',$language) ); ?>
            <?php self::card( self::t('groups',$language), (string) ( $coverage['group_count'] ?? 0 ), self::t('matched',$language) ); ?>
            <?php self::card( self::t('comparable',$language), (string) ( $coverage['context_comparable_interventions'] ?? 0 ), self::t('interventions',$language) ); ?>
            <?php self::card( self::t('excluded',$language), (string) ( $coverage['excluded_interventions'] ?? 0 ), self::t('interventions',$language) ); ?>
        </div>

        <p style="max-width:1180px;"><strong><?php echo esc_html( self::t('active_scope',$language) ); ?>:</strong> <?php echo esc_html( 'signed' === (string) ( $learning['scope'] ?? 'all' ) ? self::t('scope_signed',$language) : self::t('scope_all',$language) ); ?></p>

        <ul style="max-width:1140px;background:#fff;border:1px solid #dcdcde;padding:16px 16px 16px 36px;">
            <?php foreach ( $library['summary'] ?? array() as $item ) : ?><li><?php echo esc_html( (string) $item ); ?></li><?php endforeach; ?>
        </ul>

        <?php if ( empty( $library['groups'] ) ) : ?>
            <div class="notice notice-warning inline"><p><?php echo esc_html( self::t('no_groups',$language) ); ?></p></div>
            <?php return; ?>
        <?php endif; ?>

        <table class="widefat striped" style="max-width:1180px;margin-top:20px;">
            <thead><tr>
                <th><?php echo esc_html( self::t('context',$language) ); ?></th>
                <th><?php echo esc_html( self::t('sample',$language) ); ?></th>
                <th><?php echo esc_html( self::t('review_provenance',$language) ); ?></th>
                <th><?php echo esc_html( self::t('signals',$language) ); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ( $library['groups'] as $group ) : $d = $group['dimensions']; $s = $group['sample']; $lr = $group['learning_review'] ?? array(); ?>
                <tr>
                    <td>
                        <strong><code><?php echo esc_html( (string) $d['scenario_id'] ); ?></code></strong><br>
                        <?php echo esc_html( (string) $d['to_plan'] . ' / ' . (string) $d['to_mode'] ); ?><br>
                        <?php echo esc_html( (string) $d['minute_band'] . "' · " . (string) $d['score_state'] . ' · ' . (string) $d['manpower_state'] ); ?>
                    </td>
                    <td>
                        <strong><?php echo esc_html( (string) ( $s['maturity_label'] ?? '' ) ); ?></strong><br>
                        <?php echo esc_html( self::t('cleaner',$language) . ': ' . (string) ( $s['cleaner'] ?? 0 ) ); ?><br>
                        <?php echo esc_html( self::t('context_comparable',$language) . ': ' . (string) ( $s['context_comparable'] ?? 0 ) ); ?><br>
                        <?php echo esc_html( self::t('excluded',$language) . ': ' . (string) ( $s['excluded'] ?? 0 ) ); ?><br>
                        <em><?php echo esc_html( (string) ( $s['signal_basis_label'] ?? '' ) ); ?></em>
                    </td>
                    <td>
                        <strong><?php echo esc_html( self::review_status_label( (string) ( $lr['status'] ?? 'unsigned' ), $language ) ); ?></strong><br>
                        <?php echo esc_html( self::t('signed_cases',$language) . ': ' . (string) ( $lr['signed_cases'] ?? 0 ) . '/' . (string) ( $lr['total_cases'] ?? 0 ) ); ?><br>
                        <?php echo esc_html( self::t('signed_sessions',$language) . ': ' . (string) ( $lr['signed_sessions'] ?? 0 ) ); ?><br>
                        <?php if ( (int) ( $lr['latest_signed_revision'] ?? 0 ) > 0 ) : ?>
                            <?php echo esc_html( self::t('latest_revision',$language) . ': ' . (string) $lr['latest_signed_revision'] ); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php foreach ( $group['metrics'] ?? array() as $metric ) : ?>
                            <div style="margin-bottom:8px;">
                                <code><?php echo esc_html( (string) $metric['metric'] ); ?></code><br>
                                <?php echo esc_html( self::t('median',$language) . ': ' . self::delta( $metric['median_delta'] ?? null ) ); ?> ·
                                <?php echo esc_html( self::t('up',$language) . ': ' . (string) ( $metric['up'] ?? 0 ) . '/' . (string) ( $metric['observations'] ?? 0 ) ); ?> ·
                                <?php echo esc_html( self::t('direction',$language) . ': ' . (string) ( $metric['direction'] ?? '' ) ); ?>
                            </div>
                        <?php endforeach; ?>
                        <details><summary><?php echo esc_html( self::t('details',$language) ); ?></summary><pre style="white-space:pre-wrap;"><?php echo esc_html( wp_json_encode( $group, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre></details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <h2><?php echo esc_html( self::t('limitations',$language) ); ?></h2>
        <ul style="max-width:1140px;background:#fff;border:1px solid #dcdcde;padding:16px 16px 16px 36px;">
            <?php foreach ( $library['method']['limitations'] ?? array() as $item ) : ?><li><?php echo esc_html( (string) $item ); ?></li><?php endforeach; ?>
            <li><?php echo esc_html( self::t('signoff_limit',$language) ); ?></li>
        </ul>
        <?php
    }

    private static function review_status_label( string $status, string $language ): string {
        $en = array( 'all-signed'=>'All cases signed','mixed'=>'Mixed signed/unsigned','unsigned'=>'Unsigned historical cases','invalid'=>'Signed review integrity issue' );
        $es = array( 'all-signed'=>'Todos los casos firmados','mixed'=>'Mezcla firmados/sin firmar','unsigned'=>'Casos históricos sin firmar','invalid'=>'Problema de integridad en revisión firmada' );
        $map = 'es' === $language ? $es : $en;
        return $map[ $status ] ?? $status;
    }

    private static function delta( $value ): string {
        if ( null === $value ) return '—';
        $number = (float) $value;
        return ( $number > 0 ? '+' : '' ) . number_format_i18n( $number, 2 );
    }

    private static function card( string $title, string $value, string $meta ): void {
        ?><div style="background:#fff;border:1px solid #dcdcde;padding:16px;"><div style="font-size:12px;text-transform:uppercase;color:#646970;"><?php echo esc_html($title); ?></div><div style="font-size:22px;font-weight:700;margin-top:4px;"><?php echo esc_html($value); ?></div><div style="color:#646970;margin-top:4px;"><?php echo esc_html($meta); ?></div></div><?php
    }

    private static function t( string $key, string $language ): string {
        $c = array(
            'en'=>array(
                'title'=>'Intervention Library','intro'=>'Historical context-matched tactical interventions. Repeated deltas are descriptive associations, not causal effects.','team'=>'Team','scenario'=>'Scenario filter','learning_scope'=>'Learning scope','scope_all'=>'All eligible historical cases','scope_signed'=>'Signed staff reviews only','active_scope'=>'Active learning scope','apply'=>'Apply','sessions'=>'Sessions','processed'=>'closed linked sessions processed','signed_sessions'=>'Signed sessions','signed_reviewed'=>'with a valid staff review sign-off','groups'=>'Groups','matched'=>'matched tactical contexts','comparable'=>'Comparable','excluded'=>'Excluded','interventions'=>'interventions','no_groups'=>'No matched intervention groups are available for this selection yet.','context'=>'Matched context','sample'=>'Sample','review_provenance'=>'Review provenance','signals'=>'Observed deltas','cleaner'=>'Cleaner','context_comparable'=>'Context-comparable','median'=>'Median Δ','up'=>'Up','direction'=>'Direction','details'=>'Details','limitations'=>'Method limitations','signed_cases'=>'Signed cases','latest_revision'=>'Latest signed revision','signoff_note'=>'Signed review provenance confirms that staff closed and reviewed the match workflow. It does not add effectiveness points and does not prove causality.','signoff_limit'=>'Staff sign-off is workflow/provenance validation, not evidence that a tactical intervention caused a positive or negative outcome.'
            ),
            'es'=>array(
                'title'=>'Biblioteca de intervenciones','intro'=>'Intervenciones tácticas históricas agrupadas por contexto comparable. Los deltas repetidos son asociaciones descriptivas, no efectos causales.','team'=>'Equipo','scenario'=>'Filtro de escenario','learning_scope'=>'Ámbito de aprendizaje','scope_all'=>'Todos los casos históricos elegibles','scope_signed'=>'Solo revisiones firmadas por el staff','active_scope'=>'Ámbito de aprendizaje activo','apply'=>'Aplicar','sessions'=>'Sesiones','processed'=>'sesiones cerradas y enlazadas procesadas','signed_sessions'=>'Sesiones firmadas','signed_reviewed'=>'con revisión final válida del staff','groups'=>'Grupos','matched'=>'contextos tácticos comparables','comparable'=>'Comparables','excluded'=>'Excluidas','interventions'=>'intervenciones','no_groups'=>'Todavía no hay grupos de intervenciones comparables para esta selección.','context'=>'Contexto comparable','sample'=>'Muestra','review_provenance'=>'Procedencia de revisión','signals'=>'Deltas observados','cleaner'=>'Más limpias','context_comparable'=>'Comparables por contexto','median'=>'Mediana Δ','up'=>'Al alza','direction'=>'Dirección','details'=>'Detalles','limitations'=>'Limitaciones metodológicas','signed_cases'=>'Casos firmados','latest_revision'=>'Última revisión firmada','signoff_note'=>'La procedencia firmada confirma que el staff cerró y revisó el flujo del partido. No añade puntos de eficacia ni demuestra causalidad.','signoff_limit'=>'El sign-off del staff valida flujo y procedencia; no demuestra que una intervención táctica causara un resultado positivo o negativo.'
            ),
        );
        return $c[$language][$key] ?? $c['en'][$key] ?? $key;
    }
}
