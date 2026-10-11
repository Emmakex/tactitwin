<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Objective_Evidence_Admin {
    public static function register(): void {
        add_action( 'admin_menu', array( self::class, 'menu' ) );
        add_action( 'admin_post_tactitwin_link_evidence_match', array( self::class, 'link_match' ) );
    }

    public static function menu(): void {
        add_submenu_page(
            'tactitwin',
            __( 'Evidence Review', 'tactitwin' ),
            __( 'Evidence Review', 'tactitwin' ),
            'manage_options',
            'tactitwin-evidence-review',
            array( self::class, 'render' )
        );
    }

    public static function link_match(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage TactiTwin.', 'tactitwin' ) );
        }
        check_admin_referer( 'tactitwin_link_evidence_match' );

        $session_uuid = sanitize_text_field( wp_unslash( (string) ( $_POST['session_uuid'] ?? '' ) ) );
        $match_id = absint( $_POST['match_id'] ?? 0 );
        try {
            ( new TactiTwin_Session_Match_Link_Service() )->link( $session_uuid, $match_id );
            set_transient(
                'tactitwin_evidence_notice_' . get_current_user_id(),
                array( 'type'=>'success', 'message'=>__( 'Imported match linked to the live session.', 'tactitwin' ) ),
                MINUTE_IN_SECONDS
            );
        } catch ( Throwable $e ) {
            set_transient(
                'tactitwin_evidence_notice_' . get_current_user_id(),
                array( 'type'=>'error', 'message'=>$e->getMessage() ),
                MINUTE_IN_SECONDS
            );
        }

        wp_safe_redirect(
            add_query_arg(
                array( 'page'=>'tactitwin-evidence-review', 'session_uuid'=>$session_uuid ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $session_service = new TactiTwin_Live_Session_Service();
        $link_service = new TactiTwin_Session_Match_Link_Service();
        $sessions = $session_service->recent_sessions( 30 );
        $session_uuid = sanitize_text_field( wp_unslash( (string) ( $_GET['session_uuid'] ?? '' ) ) );
        $review = null;
        $session = array();
        $matches = array();
        $error = null;

        if ( '' !== $session_uuid ) {
            try {
                $session = $session_service->session( $session_uuid );
                if ( empty( $session ) ) {
                    throw new RuntimeException( 'Live session not found.' );
                }
                $matches = $link_service->matching_matches(
                    (int) $session['team_source_id'],
                    (int) $session['opponent_source_id'],
                    30
                );
                $review = ( new TactiTwin_Post_Match_Review_Service() )->review( $session_uuid );
            } catch ( Throwable $e ) {
                $error = $e->getMessage();
            }
        }

        $language = is_array( $review )
            ? TactiTwin_I18n::normalize_language( (string) ( $review['language'] ?? 'en' ) )
            : TactiTwin_I18n::current_language();
        $notice = get_transient( 'tactitwin_evidence_notice_' . get_current_user_id() );
        if ( $notice ) {
            delete_transient( 'tactitwin_evidence_notice_' . get_current_user_id() );
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( self::label( 'title', $language ) ); ?></h1>
            <p><?php echo esc_html( self::label( 'intro', $language ) ); ?></p>
            <?php if ( is_array( $notice ) ) : ?><div class="notice notice-<?php echo esc_attr( 'error' === $notice['type'] ? 'error' : 'success' ); ?> is-dismissible"><p><?php echo esc_html( (string) $notice['message'] ); ?></p></div><?php endif; ?>
            <?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>
            <?php self::sessions( $sessions, $session_uuid, $language ); ?>
            <?php if ( ! empty( $session ) ) : self::link_box( $session, $matches, $language ); endif; ?>
            <?php if ( is_array( $review ) ) : self::evidence( $review, $language ); endif; ?>
        </div>
        <?php
    }

    private static function sessions( array $sessions, string $selected, string $language ): void {
        ?>
        <h2><?php echo esc_html( self::label( 'sessions', $language ) ); ?></h2>
        <?php if ( empty( $sessions ) ) : ?><p><?php echo esc_html( self::label( 'no_sessions', $language ) ); ?></p><?php return; endif; ?>
        <table class="widefat striped" style="max-width:1180px;margin-bottom:26px;">
            <thead><tr><th><?php echo esc_html( self::label( 'session', $language ) ); ?></th><th><?php echo esc_html( self::label( 'teams', $language ) ); ?></th><th><?php echo esc_html( self::label( 'linked_match', $language ) ); ?></th><th><?php echo esc_html( self::label( 'status', $language ) ); ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ( $sessions as $row ) :
                $uuid = (string) $row['session_uuid'];
                $url = add_query_arg( array('page'=>'tactitwin-evidence-review','session_uuid'=>$uuid), admin_url('admin.php') );
                ?>
                <tr<?php echo $uuid === $selected ? ' style="background:#f0f6fc;"' : ''; ?>>
                    <td><?php echo esc_html( (string) ( $row['title'] ?: $uuid ) ); ?></td>
                    <td><?php echo esc_html( (string) $row['team_source_id'] . ' vs ' . (string) $row['opponent_source_id'] ); ?></td>
                    <td><?php echo esc_html( '' !== (string) $row['source_match_id'] ? (string) $row['source_match_id'] : '—' ); ?></td>
                    <td><?php echo esc_html( strtoupper( (string) $row['status'] ) ); ?></td>
                    <td><a class="button" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( self::label( 'open', $language ) ); ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private static function link_box( array $session, array $matches, string $language ): void {
        $linked = (string) ( $session['source_match_id'] ?? '' );
        ?>
        <div style="max-width:1180px;background:#fff;border:1px solid #dcdcde;padding:18px;margin-bottom:24px;">
            <h2 style="margin-top:0;"><?php echo esc_html( self::label( 'link_title', $language ) ); ?></h2>
            <p><?php echo esc_html( self::label( 'link_help', $language ) ); ?></p>
            <?php if ( '' !== $linked ) : ?><p><strong><?php echo esc_html( self::label( 'current_link', $language ) ); ?>:</strong> <?php echo esc_html( $linked ); ?></p><?php endif; ?>
            <?php if ( empty( $matches ) ) : ?>
                <div class="notice notice-warning inline"><p><?php echo esc_html( self::label( 'no_matching_matches', $language ) ); ?></p></div>
            <?php else : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="tactitwin_link_evidence_match">
                    <input type="hidden" name="session_uuid" value="<?php echo esc_attr( (string) $session['session_uuid'] ); ?>">
                    <?php wp_nonce_field( 'tactitwin_link_evidence_match' ); ?>
                    <select name="match_id" required style="min-width:420px;max-width:100%;">
                        <?php foreach ( $matches as $match ) :
                            $id = (string) $match['source_match_id'];
                            $score = null === $match['home_score'] || null === $match['away_score'] ? '' : ' · ' . (string) $match['home_score'] . '-' . (string) $match['away_score'];
                            $text = (string) $match['match_date'] . ' · ' . (string) $match['home_team_source_id'] . ' vs ' . (string) $match['away_team_source_id'] . $score . ' · #' . $id;
                            ?>
                            <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $linked, $id ); ?>><?php echo esc_html( $text ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php submit_button( self::label( 'link_button', $language ), 'secondary', 'submit', false ); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function evidence( array $review, string $language ): void {
        $evidence = $review['objective_evidence'] ?? array();
        $status = (string) ( $evidence['status'] ?? 'unavailable' );
        ?>
        <hr>
        <h2><?php echo esc_html( self::label( 'evidence_title', $language ) ); ?></h2>
        <?php if ( 'available' !== $status ) : ?>
            <div class="notice notice-warning inline" style="max-width:1140px;"><p><?php echo esc_html( (string) ( $evidence['message'] ?? self::label( 'unavailable', $language ) ) ); ?></p></div>
            <?php return; ?>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;max-width:1180px;margin:16px 0;">
            <?php self::card( self::label( 'match_id', $language ), (string) ( $evidence['source_match_id'] ?? '' ), (string) ( $evidence['source'] ?? '' ) ); ?>
            <?php self::card( self::label( 'events', $language ), number_format_i18n( (int) ( $evidence['coverage']['events'] ?? 0 ) ), (string) ( $evidence['coverage']['scope'] ?? '' ) ); ?>
            <?php self::card( self::label( 'score', $language ), self::score( $evidence ), self::label( 'imported_result', $language ) ); ?>
            <?php self::card( self::label( 'signals', $language ), (string) count( $evidence['signals'] ?? array() ), self::label( 'event_proxies', $language ) ); ?>
        </div>

        <div class="notice notice-info inline" style="max-width:1140px;"><p><?php echo esc_html( (string) ( $evidence['method']['note'] ?? '' ) ); ?></p></div>

        <h3><?php echo esc_html( self::label( 'summary', $language ) ); ?></h3>
        <ul style="max-width:1140px;background:#fff;border:1px solid #dcdcde;padding:16px 16px 16px 36px;">
            <?php foreach ( $evidence['summary'] ?? array() as $item ) : ?><li><?php echo esc_html( (string) $item ); ?></li><?php endforeach; ?>
        </ul>

        <h3><?php echo esc_html( self::label( 'comparison', $language ) ); ?></h3>
        <table class="widefat striped" style="max-width:1180px;">
            <thead><tr><th><?php echo esc_html( self::label( 'scenario', $language ) ); ?></th><th><?php echo esc_html( self::label( 'staff', $language ) ); ?></th><th><?php echo esc_html( self::label( 'event_status', $language ) ); ?></th><th><?php echo esc_html( self::label( 'reading', $language ) ); ?></th></tr></thead>
            <tbody>
            <?php foreach ( $evidence['signals'] ?? array() as $signal ) : ?>
                <tr>
                    <td><strong><?php echo esc_html( (string) ( $signal['scenario_label'] ?? $signal['scenario_id'] ?? '' ) ); ?></strong><br><code><?php echo esc_html( (string) ( $signal['scenario_id'] ?? '' ) ); ?></code></td>
                    <td><?php echo esc_html( ! empty( $signal['staff_observed'] ) ? self::label( 'yes', $language ) : self::label( 'no', $language ) ); ?><?php if ( null !== ( $signal['staff_first_seen_minute'] ?? null ) ) : ?><br><?php echo esc_html( (string) $signal['staff_first_seen_minute'] ); ?>'<?php endif; ?></td>
                    <td><strong><?php echo esc_html( (string) ( $signal['status_label'] ?? $signal['status'] ?? '' ) ); ?></strong><br><?php echo esc_html( (string) ( $signal['comparison']['label'] ?? '' ) ); ?></td>
                    <td><?php echo esc_html( (string) ( $signal['interpretation'] ?? '' ) ); ?><details style="margin-top:8px;"><summary><?php echo esc_html( self::label( 'details', $language ) ); ?></summary><pre style="white-space:pre-wrap;"><?php echo esc_html( wp_json_encode( array('indicators'=>$signal['indicators'] ?? array(),'thresholds'=>$signal['thresholds'] ?? array(),'caveat'=>$signal['caveat'] ?? ''), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre></details></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <h3><?php echo esc_html( self::label( 'limitations', $language ) ); ?></h3>
        <ul style="max-width:1140px;background:#fff;border:1px solid #dcdcde;padding:16px 16px 16px 36px;">
            <?php foreach ( $evidence['method']['limitations'] ?? array() as $item ) : ?><li><?php echo esc_html( (string) $item ); ?></li><?php endforeach; ?>
        </ul>

        <details style="max-width:1180px;margin-top:22px;"><summary><strong><?php echo esc_html( self::label( 'technical', $language ) ); ?></strong></summary><pre style="white-space:pre-wrap;background:#fff;padding:16px;border:1px solid #dcdcde;"><?php echo esc_html( wp_json_encode( $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre></details>
        <?php
    }

    private static function score( array $evidence ): string {
        $match = $evidence['match'] ?? array();
        $home = $match['home_team']['score'] ?? null;
        $away = $match['away_team']['score'] ?? null;
        return null === $home || null === $away ? '—' : (string) $home . '-' . (string) $away;
    }

    private static function card( string $title, string $value, string $meta ): void {
        ?><div style="background:#fff;border:1px solid #dcdcde;padding:16px;"><div style="font-size:12px;text-transform:uppercase;color:#646970;"><?php echo esc_html( $title ); ?></div><div style="font-size:22px;font-weight:700;margin-top:4px;word-break:break-word;"><?php echo esc_html( $value ); ?></div><div style="color:#646970;margin-top:4px;"><?php echo esc_html( $meta ); ?></div></div><?php
    }

    private static function label( string $key, string $language ): string {
        $catalog = array(
            'en'=>array(
                'title'=>'Evidence Review','intro'=>'Link a persistent live session to its imported match and compare sampled staff observations with transparent full-match event proxies. Event evidence remains separate from live observations.','sessions'=>'Recent live sessions','no_sessions'=>'No live sessions are available yet.','session'=>'Session','teams'=>'Teams','linked_match'=>'Linked match','status'=>'Status','open'=>'Open evidence','link_title'=>'Imported match link','link_help'=>'Only imported matches containing the same two teams are offered. Linking does not require event data yet; objective evidence appears once match events are imported.','current_link'=>'Current linked match','no_matching_matches'=>'No imported match with this exact team pairing is available. Import the match metadata first.','link_button'=>'Link selected match','evidence_title'=>'Objective post-match evidence','unavailable'=>'Event evidence is not available for this session.','match_id'=>'Match ID','events'=>'Events','score'=>'Score','imported_result'=>'imported match result','signals'=>'Signals','event_proxies'=>'transparent event proxies','summary'=>'Evidence summary','comparison'=>'Staff observation vs event evidence','scenario'=>'Scenario','staff'=>'Staff observed','event_status'=>'Event evidence','reading'=>'Reading','yes'=>'Yes','no'=>'No','details'=>'Indicators and thresholds','limitations'=>'Method limitations','technical'=>'Technical event evidence'),
            'es'=>array(
                'title'=>'Evidence Review','intro'=>'Enlaza una sesión persistente con su partido importado y compara las observaciones muestreadas del cuerpo técnico con proxies transparentes del partido completo. La evidencia de eventos se mantiene separada de las observaciones en vivo.','sessions'=>'Sesiones en vivo recientes','no_sessions'=>'Todavía no hay sesiones en vivo disponibles.','session'=>'Sesión','teams'=>'Equipos','linked_match'=>'Partido enlazado','status'=>'Estado','open'=>'Abrir evidencia','link_title'=>'Enlace con partido importado','link_help'=>'Solo se ofrecen partidos importados que contienen exactamente los mismos dos equipos. El enlace no exige todavía eventos; la evidencia aparece cuando se importan los eventos del partido.','current_link'=>'Partido enlazado actual','no_matching_matches'=>'No hay un partido importado con esta pareja exacta de equipos. Importa primero los metadatos del partido.','link_button'=>'Enlazar partido seleccionado','evidence_title'=>'Evidencia post-partido derivada de eventos','unavailable'=>'La evidencia de eventos no está disponible para esta sesión.','match_id'=>'ID partido','events'=>'Eventos','score'=>'Marcador','imported_result'=>'resultado importado','signals'=>'Señales','event_proxies'=>'proxies transparentes de eventos','summary'=>'Resumen de evidencia','comparison'=>'Observación del staff vs evidencia de eventos','scenario'=>'Escenario','staff'=>'Observado por staff','event_status'=>'Evidencia de eventos','reading'=>'Lectura','yes'=>'Sí','no'=>'No','details'=>'Indicadores y umbrales','limitations'=>'Limitaciones del método','technical'=>'Evidencia técnica de eventos'),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
