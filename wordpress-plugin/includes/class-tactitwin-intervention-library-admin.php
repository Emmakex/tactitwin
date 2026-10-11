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

        $team_id = absint( $_GET['team_id'] ?? 0 );
        if ( $team_id <= 0 && ! empty( $teams ) ) {
            $team_id = (int) reset( $teams );
        }
        $scenario = sanitize_key( wp_unslash( (string) ( $_GET['scenario_id'] ?? '' ) ) );
        $library = null;
        $error = null;

        if ( $team_id > 0 ) {
            try {
                $library = ( new TactiTwin_Intervention_Library_Service() )->build( $team_id, $language, $scenario, 100 );
            } catch ( Throwable $e ) {
                $error = $e->getMessage();
            }
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( self::t( 'title', $language ) ); ?></h1>
            <p><?php echo esc_html( self::t( 'intro', $language ) ); ?></p>
            <?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>
            <?php self::filters( $teams, $team_id, $scenario, $language ); ?>
            <?php if ( is_array( $library ) ) self::library( $library, $language ); ?>
        </div>
        <?php
    }

    private static function filters( array $teams, int $team_id, string $scenario, string $language ): void {
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
            <button class="button button-primary"><?php echo esc_html( self::t('apply',$language) ); ?></button>
        </form>
        <?php
    }

    private static function library( array $library, string $language ): void {
        $coverage = $library['coverage'] ?? array();
        ?>
        <div class="notice notice-info inline" style="max-width:1180px;"><p><?php echo esc_html( (string) ( $library['method']['note'] ?? '' ) ); ?></p></div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;max-width:1180px;margin:18px 0;">
            <?php self::card( self::t('sessions',$language), (string) ( $coverage['sessions_processed'] ?? 0 ), self::t('processed',$language) ); ?>
            <?php self::card( self::t('groups',$language), (string) ( $coverage['group_count'] ?? 0 ), self::t('matched',$language) ); ?>
            <?php self::card( self::t('comparable',$language), (string) ( $coverage['context_comparable_interventions'] ?? 0 ), self::t('interventions',$language) ); ?>
            <?php self::card( self::t('excluded',$language), (string) ( $coverage['excluded_interventions'] ?? 0 ), self::t('interventions',$language) ); ?>
        </div>

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
                <th><?php echo esc_html( self::t('signals',$language) ); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ( $library['groups'] as $group ) : $d = $group['dimensions']; $s = $group['sample']; ?>
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
        </ul>
        <?php
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
            'en'=>array('title'=>'Intervention Library','intro'=>'Historical context-matched tactical interventions. Repeated deltas are descriptive associations, not causal effects.','team'=>'Team','scenario'=>'Scenario filter','apply'=>'Apply','sessions'=>'Sessions','processed'=>'closed linked sessions processed','groups'=>'Groups','matched'=>'matched tactical contexts','comparable'=>'Comparable','excluded'=>'Excluded','interventions'=>'interventions','no_groups'=>'No matched intervention groups are available for this selection yet.','context'=>'Matched context','sample'=>'Sample','signals'=>'Observed deltas','cleaner'=>'Cleaner','context_comparable'=>'Context-comparable','median'=>'Median Δ','up'=>'Up','direction'=>'Direction','details'=>'Details','limitations'=>'Method limitations'),
            'es'=>array('title'=>'Biblioteca de intervenciones','intro'=>'Intervenciones tácticas históricas agrupadas por contexto comparable. Los deltas repetidos son asociaciones descriptivas, no efectos causales.','team'=>'Equipo','scenario'=>'Filtro de escenario','apply'=>'Aplicar','sessions'=>'Sesiones','processed'=>'sesiones cerradas y enlazadas procesadas','groups'=>'Grupos','matched'=>'contextos tácticos comparables','comparable'=>'Comparables','excluded'=>'Excluidas','interventions'=>'intervenciones','no_groups'=>'Todavía no hay grupos de intervenciones comparables para esta selección.','context'=>'Contexto comparable','sample'=>'Muestra','signals'=>'Deltas observados','cleaner'=>'Más limpias','context_comparable'=>'Comparables por contexto','median'=>'Mediana Δ','up'=>'Al alza','direction'=>'Dirección','details'=>'Detalles','limitations'=>'Limitaciones metodológicas'),
        );
        return $c[$language][$key] ?? $c['en'][$key] ?? $key;
    }
}
