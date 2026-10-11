<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Transition_Delta_Admin {
    public static function register(): void {
        add_action( 'admin_menu', array( self::class, 'menu' ) );
    }

    public static function menu(): void {
        add_submenu_page(
            'tactitwin',
            __( 'Transition Deltas', 'tactitwin' ),
            __( 'Transition Deltas', 'tactitwin' ),
            'manage_options',
            'tactitwin-transition-deltas',
            array( self::class, 'render' )
        );
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $sessions = ( new TactiTwin_Live_Session_Service() )->recent_sessions( 30 );
        $session_uuid = sanitize_text_field( wp_unslash( (string) ( $_GET['session_uuid'] ?? '' ) ) );
        $review = null;
        $error = null;
        if ( '' !== $session_uuid ) {
            try {
                $review = ( new TactiTwin_Post_Match_Review_Service() )->review( $session_uuid );
            } catch ( Throwable $e ) {
                $error = $e->getMessage();
            }
        }

        $language = is_array( $review )
            ? TactiTwin_I18n::normalize_language( (string) ( $review['language'] ?? 'en' ) )
            : TactiTwin_I18n::current_language();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( self::t( 'title', $language ) ); ?></h1>
            <p><?php echo esc_html( self::t( 'intro', $language ) ); ?></p>
            <?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>
            <?php self::sessions( $sessions, $session_uuid, $language ); ?>
            <?php if ( is_array( $review ) ) :
                $contextual = $review['context_aware_transition_review'] ?? array();
                $fallback = $review['transition_window_deltas'] ?? array();
                self::deltas( 'available' === (string) ( $contextual['status'] ?? '' ) ? $contextual : $fallback, $language );
            endif; ?>
        </div>
        <?php
    }

    private static function sessions( array $sessions, string $selected, string $language ): void {
        if ( empty( $sessions ) ) {
            echo '<p>' . esc_html( self::t( 'no_sessions', $language ) ) . '</p>';
            return;
        }
        ?>
        <table class="widefat striped" style="max-width:1220px;margin:20px 0 26px;">
            <thead><tr><th><?php echo esc_html(self::t('session',$language)); ?></th><th><?php echo esc_html(self::t('teams',$language)); ?></th><th><?php echo esc_html(self::t('match',$language)); ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ( $sessions as $row ) :
                $uuid = (string) $row['session_uuid'];
                $url = add_query_arg( array('page'=>'tactitwin-transition-deltas','session_uuid'=>$uuid), admin_url('admin.php') );
                ?>
                <tr<?php echo $uuid === $selected ? ' style="background:#f0f6fc;"' : ''; ?>>
                    <td><?php echo esc_html( (string) ( $row['title'] ?: $uuid ) ); ?></td>
                    <td><?php echo esc_html( (string) $row['team_source_id'] . ' vs ' . (string) $row['opponent_source_id'] ); ?></td>
                    <td><?php echo esc_html( '' !== (string) $row['source_match_id'] ? '#' . (string) $row['source_match_id'] : '—' ); ?></td>
                    <td><a class="button" href="<?php echo esc_url($url); ?>"><?php echo esc_html(self::t('open',$language)); ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private static function deltas( array $result, string $language ): void {
        if ( 'available' !== (string) ( $result['status'] ?? '' ) ) {
            ?><div class="notice notice-warning inline"><p><?php echo esc_html( (string) ( $result['message'] ?? self::t('unavailable',$language) ) ); ?></p></div><?php
            return;
        }

        $is_contextual = 'context-aware-transition-review' === (string) ( $result['review_id'] ?? '' );
        $counts = array('cleaner'=>0,'confounded'=>0,'highly-confounded'=>0,'insufficient'=>0);
        foreach ( $result['transitions'] ?? array() as $row ) {
            $key = (string) ( $row['context']['interpretability'] ?? '' );
            if ( isset( $counts[$key] ) ) ++$counts[$key];
        }
        ?>
        <div class="notice notice-info inline" style="max-width:1180px;"><p><?php echo esc_html( (string) ( $result['method']['note'] ?? '' ) ); ?></p></div>
        <?php if ( $is_contextual ) : ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;max-width:1220px;margin:18px 0;">
                <?php self::card(self::t('transitions',$language),(string)($result['coverage']['transition_count'] ?? 0),self::t('recorded_changes',$language)); ?>
                <?php self::card(self::t('cleaner',$language),(string)$counts['cleaner'],self::t('interpretability',$language)); ?>
                <?php self::card(self::t('confounded',$language),(string)$counts['confounded'],self::t('interpretability',$language)); ?>
                <?php self::card(self::t('highly_confounded',$language),(string)$counts['highly-confounded'],self::t('interpretability',$language)); ?>
                <?php self::card(self::t('insufficient',$language),(string)$counts['insufficient'],self::t('interpretability',$language)); ?>
            </div>
        <?php endif; ?>

        <h2><?php echo esc_html(self::t('summary',$language)); ?></h2>
        <ul style="max-width:1180px;background:#fff;border:1px solid #dcdcde;padding:16px 16px 16px 36px;">
            <?php foreach ( $result['summary'] ?? array() as $item ) : ?><li><?php echo esc_html((string)$item); ?></li><?php endforeach; ?>
        </ul>

        <h2><?php echo esc_html(self::t('table',$language)); ?></h2>
        <table class="widefat striped" style="max-width:1220px;">
            <thead><tr><th><?php echo esc_html(self::t('minute',$language)); ?></th><th><?php echo esc_html(self::t('change',$language)); ?></th><th><?php echo esc_html(self::t('before_after',$language)); ?></th><?php if($is_contextual): ?><th><?php echo esc_html(self::t('context',$language)); ?></th><?php endif; ?><th><?php echo esc_html(self::t('focus',$language)); ?></th></tr></thead>
            <tbody>
            <?php foreach ( $result['transitions'] ?? array() as $row ) : ?>
                <tr>
                    <td><strong><?php echo esc_html((string)$row['minute']); ?>'</strong><br><?php echo esc_html((string)($row['coverage']['status_label'] ?? $row['coverage']['status'] ?? '')); ?></td>
                    <td><?php echo esc_html((string)$row['from_plan'].' / '.(string)$row['from_mode']); ?><br>→<br><?php echo esc_html((string)$row['to_plan'].' / '.(string)$row['to_mode']); ?><?php if('' !== (string)($row['to_scenario'] ?? '')): ?><br><code><?php echo esc_html((string)$row['to_scenario']); ?></code><?php endif; ?></td>
                    <td><?php echo esc_html((string)$row['before']['from_minute']."'–".(string)$row['before']['to_minute']."' · ".(string)$row['before']['event_count'].' '.self::t('events',$language)); ?><br><?php echo esc_html((string)$row['after']['from_minute']."'–".(string)$row['after']['to_minute']."' · ".(string)$row['after']['event_count'].' '.self::t('events',$language)); ?></td>
                    <?php if ( $is_contextual ) : $context = $row['context'] ?? array(); ?>
                        <td><strong><?php echo esc_html((string)($context['status_label'] ?? '')); ?></strong><br><?php echo esc_html((string)($context['interpretability_label'] ?? '')); ?><ul style="margin:6px 0 0 18px;list-style:disc;"><?php foreach($context['warnings'] ?? array() as $warning): ?><li><?php echo esc_html((string)$warning); ?></li><?php endforeach; ?></ul></td>
                    <?php endif; ?>
                    <td><?php foreach($row['focus']['metrics'] ?? array() as $metric): ?><div style="margin-bottom:7px;"><code><?php echo esc_html((string)$metric['metric']); ?></code><br><?php echo esc_html(self::value($metric['before'] ?? null).' → '.self::value($metric['after'] ?? null).' (Δ '.self::delta($metric['delta'] ?? null).')'); ?></div><?php endforeach; ?><details><summary><?php echo esc_html(self::t('details',$language)); ?></summary><pre style="white-space:pre-wrap;"><?php echo esc_html(wp_json_encode(array('coverage'=>$row['coverage'] ?? array(),'context'=>$row['context'] ?? array(),'deltas'=>$row['deltas'] ?? array()),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); ?></pre></details></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <h2><?php echo esc_html(self::t('limitations',$language)); ?></h2><ul style="max-width:1180px;background:#fff;border:1px solid #dcdcde;padding:16px 16px 16px 36px;"><?php foreach($result['method']['limitations'] ?? array() as $item): ?><li><?php echo esc_html((string)$item); ?></li><?php endforeach; ?></ul>
        <?php
    }

    private static function value( $value ): string { return null === $value ? '—' : ( is_float($value) ? number_format_i18n($value,2) : (string)$value ); }
    private static function delta( $value ): string { if(null === $value) return '—'; $n=(float)$value; return ($n>0?'+':'').number_format_i18n($n,2); }
    private static function card( string $title, string $value, string $meta ): void { ?><div style="background:#fff;border:1px solid #dcdcde;padding:16px;"><div style="font-size:12px;text-transform:uppercase;color:#646970;"><?php echo esc_html($title); ?></div><div style="font-size:22px;font-weight:700;margin-top:4px;"><?php echo esc_html($value); ?></div><div style="color:#646970;margin-top:4px;"><?php echo esc_html($meta); ?></div></div><?php }

    private static function t( string $key, string $language ): string {
        $c=array(
            'en'=>array('title'=>'Transition Deltas','intro'=>'Compare before/after tactical windows and see which score, manpower or personnel changes may confound the observed deltas.','no_sessions'=>'No live sessions are available yet.','session'=>'Session','teams'=>'Teams','match'=>'Linked match','open'=>'Open','unavailable'=>'Transition review is not available.','transitions'=>'Transitions','recorded_changes'=>'recorded changes','cleaner'=>'Cleaner','confounded'=>'Confounded','highly_confounded'=>'Highly confounded','insufficient'=>'Insufficient','interpretability'=>'interpretability','summary'=>'Summary','table'=>'Before / after transition review','minute'=>'Minute','change'=>'Tactical change','before_after'=>'Before / after','context'=>'Context','focus'=>'Scenario focus','events'=>'events','details'=>'Details','limitations'=>'Method limitations'),
            'es'=>array('title'=>'Deltas de transición','intro'=>'Compara ventanas tácticas antes/después y muestra qué cambios de marcador, superioridad o jugadores pueden contaminar los deltas observados.','no_sessions'=>'Todavía no hay sesiones en vivo disponibles.','session'=>'Sesión','teams'=>'Equipos','match'=>'Partido enlazado','open'=>'Abrir','unavailable'=>'La revisión de transiciones no está disponible.','transitions'=>'Transiciones','recorded_changes'=>'cambios registrados','cleaner'=>'Más limpias','confounded'=>'Confundidas','highly_confounded'=>'Muy confundidas','insufficient'=>'Insuficientes','interpretability'=>'interpretabilidad','summary'=>'Resumen','table'=>'Revisión antes / después de transiciones','minute'=>'Minuto','change'=>'Cambio táctico','before_after'=>'Antes / después','context'=>'Contexto','focus'=>'Foco del escenario','events'=>'eventos','details'=>'Detalles','limitations'=>'Limitaciones metodológicas'),
        );
        return $c[$language][$key] ?? $c['en'][$key] ?? $key;
    }
}
