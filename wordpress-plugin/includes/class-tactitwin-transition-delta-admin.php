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

        $session_service = new TactiTwin_Live_Session_Service();
        $sessions = $session_service->recent_sessions( 30 );
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
            <?php if ( is_array( $review ) ) : self::deltas( $review['transition_window_deltas'] ?? array(), $language ); endif; ?>
        </div>
        <?php
    }

    private static function sessions( array $sessions, string $selected, string $language ): void {
        if ( empty( $sessions ) ) {
            echo '<p>' . esc_html( self::t( 'no_sessions', $language ) ) . '</p>';
            return;
        }
        ?>
        <table class="widefat striped" style="max-width:1180px;margin:20px 0 26px;">
            <thead><tr><th><?php echo esc_html( self::t('session',$language) ); ?></th><th><?php echo esc_html( self::t('teams',$language) ); ?></th><th><?php echo esc_html( self::t('match',$language) ); ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ( $sessions as $row ) :
                $uuid = (string) $row['session_uuid'];
                $url = add_query_arg( array( 'page'=>'tactitwin-transition-deltas', 'session_uuid'=>$uuid ), admin_url( 'admin.php' ) );
                ?>
                <tr<?php echo $uuid === $selected ? ' style="background:#f0f6fc;"' : ''; ?>>
                    <td><?php echo esc_html( (string) ( $row['title'] ?: $uuid ) ); ?></td>
                    <td><?php echo esc_html( (string) $row['team_source_id'] . ' vs ' . (string) $row['opponent_source_id'] ); ?></td>
                    <td><?php echo esc_html( '' !== (string) $row['source_match_id'] ? '#' . (string) $row['source_match_id'] : '—' ); ?></td>
                    <td><a class="button" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( self::t('open',$language) ); ?></a></td>
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

        $counts = array( 'good'=>0, 'caution'=>0, 'limited'=>0 );
        foreach ( $result['transitions'] ?? array() as $row ) {
            $status = (string) ( $row['coverage']['status'] ?? 'limited' );
            if ( isset( $counts[$status] ) ) {
                ++$counts[$status];
            }
        }
        ?>
        <div class="notice notice-info inline" style="max-width:1140px;"><p><?php echo esc_html( (string) ( $result['method']['note'] ?? '' ) ); ?></p></div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;max-width:1180px;margin:18px 0;">
            <?php self::card( self::t('transitions',$language), (string) ( $result['coverage']['transition_count'] ?? 0 ), self::t('recorded_changes',$language) ); ?>
            <?php self::card( self::t('window',$language), (string) ( $result['coverage']['default_window_minutes'] ?? 10 ) . ' min', self::t('each_side',$language) ); ?>
            <?php self::card( self::t('good',$language), (string) $counts['good'], self::t('coverage',$language) ); ?>
            <?php self::card( self::t('caution',$language), (string) $counts['caution'], self::t('coverage',$language) ); ?>
            <?php self::card( self::t('limited',$language), (string) $counts['limited'], self::t('coverage',$language) ); ?>
        </div>

        <h2><?php echo esc_html( self::t('summary',$language) ); ?></h2>
        <ul style="max-width:1140px;background:#fff;border:1px solid #dcdcde;padding:16px 16px 16px 36px;">
            <?php foreach ( $result['summary'] ?? array() as $item ) : ?><li><?php echo esc_html( (string) $item ); ?></li><?php endforeach; ?>
        </ul>

        <h2><?php echo esc_html( self::t('table',$language) ); ?></h2>
        <table class="widefat striped" style="max-width:1180px;">
            <thead><tr><th><?php echo esc_html(self::t('minute',$language)); ?></th><th><?php echo esc_html(self::t('change',$language)); ?></th><th><?php echo esc_html(self::t('before',$language)); ?></th><th><?php echo esc_html(self::t('after',$language)); ?></th><th><?php echo esc_html(self::t('focus',$language)); ?></th></tr></thead>
            <tbody>
            <?php foreach ( $result['transitions'] ?? array() as $row ) : ?>
                <tr>
                    <td><strong><?php echo esc_html( (string) $row['minute'] ); ?>'</strong><br><?php echo esc_html( (string) ( $row['coverage']['status_label'] ?? $row['coverage']['status'] ?? '' ) ); ?></td>
                    <td><?php echo esc_html( (string) $row['from_plan'] . ' / ' . (string) $row['from_mode'] ); ?><br>→<br><?php echo esc_html( (string) $row['to_plan'] . ' / ' . (string) $row['to_mode'] ); ?><?php if ( '' !== (string) ( $row['to_scenario'] ?? '' ) ) : ?><br><code><?php echo esc_html( (string) $row['to_scenario'] ); ?></code><?php endif; ?></td>
                    <td><?php echo esc_html( (string) $row['before']['from_minute'] . "'–" . (string) $row['before']['to_minute'] . "'" ); ?><br><?php echo esc_html( (string) $row['before']['event_count'] . ' ' . self::t('events',$language) ); ?></td>
                    <td><?php echo esc_html( (string) $row['after']['from_minute'] . "'–" . (string) $row['after']['to_minute'] . "'" ); ?><br><?php echo esc_html( (string) $row['after']['event_count'] . ' ' . self::t('events',$language) ); ?></td>
                    <td>
                        <?php foreach ( $row['focus']['metrics'] ?? array() as $metric ) : ?>
                            <div style="margin-bottom:7px;"><code><?php echo esc_html( (string) $metric['metric'] ); ?></code><br><?php echo esc_html( self::format_value( $metric['before'] ?? null ) . ' → ' . self::format_value( $metric['after'] ?? null ) . ' (Δ ' . self::format_delta( $metric['delta'] ?? null ) . ')' ); ?></div>
                        <?php endforeach; ?>
                        <details><summary><?php echo esc_html(self::t('details',$language)); ?></summary><pre style="white-space:pre-wrap;"><?php echo esc_html( wp_json_encode( array('coverage'=>$row['coverage'] ?? array(),'deltas'=>$row['deltas'] ?? array()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre></details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <h2><?php echo esc_html( self::t('limitations',$language) ); ?></h2>
        <ul style="max-width:1140px;background:#fff;border:1px solid #dcdcde;padding:16px 16px 16px 36px;">
            <?php foreach ( $result['method']['limitations'] ?? array() as $item ) : ?><li><?php echo esc_html( (string) $item ); ?></li><?php endforeach; ?>
        </ul>
        <details style="max-width:1180px;margin-top:22px;"><summary><strong><?php echo esc_html(self::t('technical',$language)); ?></strong></summary><pre style="white-space:pre-wrap;background:#fff;padding:16px;border:1px solid #dcdcde;"><?php echo esc_html( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre></details>
        <?php
    }

    private static function format_value( $value ): string {
        if ( null === $value ) return '—';
        if ( is_float( $value ) ) return number_format_i18n( $value, 2 );
        return (string) $value;
    }

    private static function format_delta( $value ): string {
        if ( null === $value ) return '—';
        $number = (float) $value;
        return ( $number > 0 ? '+' : '' ) . number_format_i18n( $number, 2 );
    }

    private static function card( string $title, string $value, string $meta ): void {
        ?><div style="background:#fff;border:1px solid #dcdcde;padding:16px;"><div style="font-size:12px;text-transform:uppercase;color:#646970;"><?php echo esc_html($title); ?></div><div style="font-size:22px;font-weight:700;margin-top:4px;"><?php echo esc_html($value); ?></div><div style="color:#646970;margin-top:4px;"><?php echo esc_html($meta); ?></div></div><?php
    }

    private static function t( string $key, string $language ): string {
        $c = array(
            'en'=>array('title'=>'Transition Deltas','intro'=>'Compare matched event windows immediately before and after each recorded tactical transition. Deltas are descriptive, not causal.','no_sessions'=>'No live sessions are available yet.','session'=>'Session','teams'=>'Teams','match'=>'Linked match','open'=>'Open','unavailable'=>'Transition-window evidence is not available.','transitions'=>'Transitions','recorded_changes'=>'recorded plan/mode/scenario changes','window'=>'Window','each_side'=>'before and after','good'=>'Good','caution'=>'Caution','limited'=>'Limited','coverage'=>'coverage','summary'=>'Summary','table'=>'Before / after windows','minute'=>'Minute','change'=>'Tactical change','before'=>'Before','after'=>'After','focus'=>'Scenario focus','events'=>'events','details'=>'Details','limitations'=>'Method limitations','technical'=>'Technical transition evidence'),
            'es'=>array('title'=>'Deltas de transición','intro'=>'Compara ventanas equivalentes de eventos inmediatamente antes y después de cada transición táctica registrada. Los deltas son descriptivos, no causales.','no_sessions'=>'Todavía no hay sesiones en vivo disponibles.','session'=>'Sesión','teams'=>'Equipos','match'=>'Partido enlazado','open'=>'Abrir','unavailable'=>'La evidencia de ventanas de transición no está disponible.','transitions'=>'Transiciones','recorded_changes'=>'cambios de plan/modo/escenario registrados','window'=>'Ventana','each_side'=>'antes y después','good'=>'Buena','caution'=>'Precaución','limited'=>'Limitada','coverage'=>'cobertura','summary'=>'Resumen','table'=>'Ventanas antes / después','minute'=>'Minuto','change'=>'Cambio táctico','before'=>'Antes','after'=>'Después','focus'=>'Foco del escenario','events'=>'eventos','details'=>'Detalles','limitations'=>'Limitaciones metodológicas','technical'=>'Evidencia técnica de transición'),
        );
        return $c[$language][$key] ?? $c['en'][$key] ?? $key;
    }
}
