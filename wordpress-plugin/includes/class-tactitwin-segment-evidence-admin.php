<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Segment_Evidence_Admin {
    public static function register(): void {
        add_action( 'admin_menu', array( self::class, 'menu' ) );
    }

    public static function menu(): void {
        add_submenu_page(
            'tactitwin',
            __( 'Segment Evidence', 'tactitwin' ),
            __( 'Segment Evidence', 'tactitwin' ),
            'manage_options',
            'tactitwin-segment-evidence',
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
            <?php if ( is_array( $review ) ) : self::evidence( $review['segment_evidence'] ?? array(), $language ); endif; ?>
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
            <thead><tr><th><?php echo esc_html( self::t( 'session', $language ) ); ?></th><th><?php echo esc_html( self::t( 'teams', $language ) ); ?></th><th><?php echo esc_html( self::t( 'match', $language ) ); ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ( $sessions as $row ) :
                $uuid = (string) $row['session_uuid'];
                $url = add_query_arg( array( 'page'=>'tactitwin-segment-evidence', 'session_uuid'=>$uuid ), admin_url( 'admin.php' ) );
                ?>
                <tr<?php echo $uuid === $selected ? ' style="background:#f0f6fc;"' : ''; ?>>
                    <td><?php echo esc_html( (string) ( $row['title'] ?: $uuid ) ); ?></td>
                    <td><?php echo esc_html( (string) $row['team_source_id'] . ' vs ' . (string) $row['opponent_source_id'] ); ?></td>
                    <td><?php echo esc_html( '' !== (string) $row['source_match_id'] ? '#' . (string) $row['source_match_id'] : '—' ); ?></td>
                    <td><a class="button" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( self::t( 'open', $language ) ); ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private static function evidence( array $evidence, string $language ): void {
        if ( 'available' !== (string) ( $evidence['status'] ?? '' ) ) {
            ?><div class="notice notice-warning inline"><p><?php echo esc_html( (string) ( $evidence['message'] ?? self::t( 'unavailable', $language ) ) ); ?></p></div><?php
            return;
        }
        ?>
        <div class="notice notice-info inline" style="max-width:1140px;"><p><?php echo esc_html( (string) ( $evidence['method']['note'] ?? '' ) ); ?></p></div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;max-width:1180px;margin:18px 0;">
            <?php self::card( self::t('segments',$language), (string) ( $evidence['coverage']['segment_count'] ?? 0 ), self::t('timeline',$language) ); ?>
            <?php self::card( self::t('events',$language), number_format_i18n( (int) ( $evidence['coverage']['events'] ?? 0 ) ), self::t('linked_events',$language) ); ?>
            <?php self::card( self::t('resolution',$language), (string) ( $evidence['coverage']['time_resolution'] ?? '' ), self::t('time_alignment',$language) ); ?>
        </div>
        <h2><?php echo esc_html( self::t( 'summary', $language ) ); ?></h2>
        <ul style="max-width:1140px;background:#fff;border:1px solid #dcdcde;padding:16px 16px 16px 36px;">
            <?php foreach ( $evidence['summary'] ?? array() as $item ) : ?><li><?php echo esc_html( (string) $item ); ?></li><?php endforeach; ?>
        </ul>
        <h2><?php echo esc_html( self::t( 'segment_table', $language ) ); ?></h2>
        <table class="widefat striped" style="max-width:1180px;">
            <thead><tr><th><?php echo esc_html(self::t('interval',$language)); ?></th><th><?php echo esc_html(self::t('plan',$language)); ?></th><th><?php echo esc_html(self::t('scenario',$language)); ?></th><th><?php echo esc_html(self::t('events',$language)); ?></th><th><?php echo esc_html(self::t('evidence',$language)); ?></th></tr></thead>
            <tbody>
            <?php foreach ( $evidence['segments'] ?? array() as $segment ) :
                $active = $segment['active_scenario_evidence'] ?? null;
                ?>
                <tr>
                    <td><strong><?php echo esc_html( (string) $segment['from_minute'] . "' → " . (string) $segment['to_minute'] . "'" ); ?></strong><br><?php echo esc_html( (string) $segment['event_boundary'] ); ?></td>
                    <td><strong><?php echo esc_html( (string) $segment['plan'] ); ?></strong><br><?php echo esc_html( (string) $segment['mode'] ); ?></td>
                    <td><code><?php echo esc_html( (string) ( $segment['scenario_id'] ?: '—' ) ); ?></code></td>
                    <td><?php echo esc_html( (string) $segment['event_count'] ); ?></td>
                    <td>
                        <?php if ( is_array( $active ) ) : ?>
                            <strong><?php echo esc_html( (string) ( $active['status_label'] ?? $active['status'] ) ); ?></strong><br>
                            <small><?php echo esc_html( (string) ( $active['comparison_status'] ?? '' ) ); ?></small>
                        <?php else : ?>—<?php endif; ?>
                        <details style="margin-top:8px;"><summary><?php echo esc_html( self::t('details',$language) ); ?></summary><pre style="white-space:pre-wrap;"><?php echo esc_html( wp_json_encode( array('signals'=>$segment['signals'] ?? array(),'metrics'=>$segment['metrics'] ?? array()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre></details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <h2><?php echo esc_html( self::t( 'limitations', $language ) ); ?></h2>
        <ul style="max-width:1140px;background:#fff;border:1px solid #dcdcde;padding:16px 16px 16px 36px;">
            <?php foreach ( $evidence['method']['limitations'] ?? array() as $item ) : ?><li><?php echo esc_html( (string) $item ); ?></li><?php endforeach; ?>
        </ul>
        <details style="max-width:1180px;margin-top:22px;"><summary><strong><?php echo esc_html( self::t('technical',$language) ); ?></strong></summary><pre style="white-space:pre-wrap;background:#fff;padding:16px;border:1px solid #dcdcde;"><?php echo esc_html( wp_json_encode( $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre></details>
        <?php
    }

    private static function card( string $title, string $value, string $meta ): void {
        ?><div style="background:#fff;border:1px solid #dcdcde;padding:16px;"><div style="font-size:12px;text-transform:uppercase;color:#646970;"><?php echo esc_html($title); ?></div><div style="font-size:22px;font-weight:700;margin-top:4px;"><?php echo esc_html($value); ?></div><div style="color:#646970;margin-top:4px;"><?php echo esc_html($meta); ?></div></div><?php
    }

    private static function t( string $key, string $language ): string {
        $c = array(
            'en'=>array('title'=>'Segment Evidence','intro'=>'Compare event evidence inside the exact A/B/C intervals reconstructed from Live Session snapshots.','no_sessions'=>'No live sessions are available yet.','session'=>'Session','teams'=>'Teams','match'=>'Linked match','open'=>'Open','unavailable'=>'Segment evidence is not available.','segments'=>'Segments','timeline'=>'A/B/C intervals','events'=>'Events','linked_events'=>'linked normalized events','resolution'=>'Resolution','time_alignment'=>'snapshot alignment','summary'=>'Segment summary','segment_table'=>'Time-aligned evidence','interval'=>'Interval','plan'=>'Plan','scenario'=>'Active scenario','evidence'=>'Active evidence','details'=>'Details','limitations'=>'Method limitations','technical'=>'Technical segment evidence'),
            'es'=>array('title'=>'Evidencia por segmentos','intro'=>'Compara la evidencia de eventos dentro de los intervalos A/B/C exactos reconstruidos desde los snapshots de Live Session.','no_sessions'=>'Todavía no hay sesiones en vivo disponibles.','session'=>'Sesión','teams'=>'Equipos','match'=>'Partido enlazado','open'=>'Abrir','unavailable'=>'La evidencia por segmentos no está disponible.','segments'=>'Segmentos','timeline'=>'Intervalos A/B/C','events'=>'Eventos','linked_events'=>'eventos normalizados enlazados','resolution'=>'Resolución','time_alignment'=>'alineación con snapshots','summary'=>'Resumen por segmentos','segment_table'=>'Evidencia alineada temporalmente','interval'=>'Intervalo','plan'=>'Plan','scenario'=>'Escenario activo','evidence'=>'Evidencia activa','details'=>'Detalles','limitations'=>'Limitaciones metodológicas','technical'=>'Evidencia técnica por segmentos'),
        );
        return $c[$language][$key] ?? $c['en'][$key] ?? $key;
    }
}
