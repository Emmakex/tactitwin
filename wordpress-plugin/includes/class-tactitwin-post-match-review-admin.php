<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Post_Match_Review_Admin {
    public static function register(): void {
        add_action( 'admin_menu', array( self::class, 'menu' ) );
        add_action( 'admin_post_tactitwin_signoff_post_match_review', array( self::class, 'sign_off' ) );
    }

    public static function menu(): void {
        add_submenu_page(
            'tactitwin',
            __( 'Session Review', 'tactitwin' ),
            __( 'Session Review', 'tactitwin' ),
            'manage_options',
            'tactitwin-session-review',
            array( self::class, 'render' )
        );
    }

    public static function sign_off(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage TactiTwin.', 'tactitwin' ) );
        }
        check_admin_referer( 'tactitwin_signoff_post_match_review' );

        $session_uuid = sanitize_text_field( wp_unslash( (string) ( $_POST['session_uuid'] ?? '' ) ) );
        $expected_sha = sanitize_text_field( wp_unslash( (string) ( $_POST['expected_review_sha256'] ?? '' ) ) );
        $note = sanitize_textarea_field( wp_unslash( (string) ( $_POST['signoff_note'] ?? '' ) ) );

        try {
            $snapshot = ( new TactiTwin_Post_Match_Review_Signoff_Service() )->sign_off(
                $session_uuid,
                get_current_user_id(),
                $expected_sha,
                $note
            );
            self::notice(
                'success',
                sprintf(
                    'Post-match review signed off as revision %d (%s).',
                    (int) ( $snapshot['revision_number'] ?? 0 ),
                    (string) ( $snapshot['review_uuid'] ?? '' )
                )
            );
        } catch ( Throwable $e ) {
            self::notice( 'error', $e->getMessage() );
        }

        wp_safe_redirect(
            add_query_arg(
                array( 'page' => 'tactitwin-session-review', 'session_uuid' => $session_uuid ),
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
        $sessions = $session_service->recent_sessions( 30 );
        $session_uuid = sanitize_text_field( wp_unslash( (string) ( $_GET['session_uuid'] ?? '' ) ) );
        $review = null;
        $error = null;
        $latest_signoff = array();
        $revisions = array();
        $binding_ready = false;

        $notice = get_transient( 'tactitwin_post_match_signoff_notice_' . get_current_user_id() );
        if ( $notice ) {
            delete_transient( 'tactitwin_post_match_signoff_notice_' . get_current_user_id() );
        }

        if ( '' !== $session_uuid ) {
            try {
                $service = new TactiTwin_Post_Match_Review_Signoff_Service();
                $review = $service->current_review( $session_uuid );
                $latest_signoff = $service->latest_for_session( $session_uuid );
                $revisions = $service->revisions_for_session( $session_uuid );
                try {
                    $service->binding_for_session( $session_uuid );
                    $binding_ready = true;
                } catch ( Throwable $e ) {
                    $binding_ready = false;
                }
            } catch ( Throwable $e ) {
                $error = $e->getMessage();
            }
        }

        $language = is_array( $review )
            ? TactiTwin_I18n::normalize_language( (string) ( $review['language'] ?? 'en' ) )
            : TactiTwin_I18n::current_language();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( self::label( 'title', $language ) ); ?></h1>
            <p><?php echo esc_html( self::label( 'intro', $language ) ); ?></p>
            <?php if ( is_array( $notice ) ) : ?>
                <div class="notice notice-<?php echo esc_attr( 'error' === ( $notice['type'] ?? '' ) ? 'error' : 'success' ); ?> is-dismissible"><p><?php echo esc_html( (string) ( $notice['message'] ?? '' ) ); ?></p></div>
            <?php endif; ?>
            <?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>
            <?php self::render_sessions( $sessions, $session_uuid, $language ); ?>
            <?php if ( is_array( $review ) ) :
                self::render_review( $review, $language );
                self::render_signoff( $review, $session_uuid, $latest_signoff, $revisions, $binding_ready, $language );
            endif; ?>
        </div>
        <?php
    }

    private static function render_sessions( array $sessions, string $selected_uuid, string $language ): void {
        ?>
        <h2><?php echo esc_html( self::label( 'sessions', $language ) ); ?></h2>
        <?php if ( empty( $sessions ) ) : ?><p><?php echo esc_html( self::label( 'no_sessions', $language ) ); ?></p><?php return; endif; ?>
        <table class="widefat striped" style="max-width:1100px;margin-bottom:28px;">
            <thead><tr><th><?php echo esc_html( self::label( 'session', $language ) ); ?></th><th><?php echo esc_html( self::label( 'teams', $language ) ); ?></th><th><?php echo esc_html( self::label( 'status', $language ) ); ?></th><th><?php echo esc_html( self::label( 'updated', $language ) ); ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ( $sessions as $session ) :
                $uuid = (string) $session['session_uuid'];
                $url = add_query_arg( array('page'=>'tactitwin-session-review','session_uuid'=>$uuid), admin_url('admin.php') );
                ?>
                <tr<?php echo $uuid === $selected_uuid ? ' style="background:#f0f6fc;"' : ''; ?>>
                    <td><?php echo esc_html( (string) ( $session['title'] ?: $uuid ) ); ?></td>
                    <td><?php echo esc_html( (string) $session['team_source_id'] . ' vs ' . (string) $session['opponent_source_id'] ); ?></td>
                    <td><?php echo esc_html( strtoupper( (string) $session['status'] ) ); ?></td>
                    <td><?php echo esc_html( (string) $session['updated_at'] ); ?></td>
                    <td><a class="button" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( self::label( 'review', $language ) ); ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private static function render_review( array $review, string $language ): void {
        $prepared = $review['prepared'] ?? array();
        $observed = $review['observed'] ?? array();
        $activated = $review['activated'] ?? array();
        $alignment = $review['alignment'] ?? array();
        $sampling = $review['sampling'] ?? array();
        $final = $review['final_context'] ?? array();
        ?>
        <hr>
        <h2><?php echo esc_html( self::label( 'review_title', $language ) ); ?></h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;max-width:1100px;margin:18px 0;">
            <?php self::card( self::label( 'final_score', $language ), (string) ( $final['our_score'] ?? 0 ) . '-' . (string) ( $final['opponent_score'] ?? 0 ), sprintf( "%d'", (int) ( $final['minute'] ?? 0 ) ) ); ?>
            <?php self::card( self::label( 'prepared_b', $language ), (string) ( $prepared['primary_plan_b']['scenario_name'] ?: $prepared['primary_plan_b']['status'] ?? '-' ), (string) ( $prepared['primary_plan_b']['mode'] ?? '' ) ); ?>
            <?php self::card( self::label( 'observed_behaviours', $language ), (string) count( $observed['unique_scenarios'] ?? array() ), self::label( 'manual_observations', $language ) ); ?>
            <?php self::card( self::label( 'transitions', $language ), (string) ( $activated['transition_count'] ?? 0 ), self::label( 'plan_mode_changes', $language ) ); ?>
            <?php self::card( self::label( 'sampling', $language ), strtoupper( (string) ( $sampling['status'] ?? '' ) ), (string) ( $sampling['snapshot_count'] ?? 0 ) . ' ' . self::label( 'snapshots', $language ) ); ?>
        </div>

        <div style="max-width:1100px;background:#fff;border-left:4px solid #2271b1;padding:16px;margin-bottom:18px;">
            <strong><?php echo esc_html( self::label( 'alignment', $language ) ); ?>:</strong><br>
            <?php echo esc_html( (string) ( $alignment['label'] ?? '' ) ); ?>
        </div>

        <?php if ( ! empty( $sampling['warnings'] ) ) : ?>
            <div class="notice notice-warning inline" style="max-width:1060px;"><ul style="list-style:disc;padding-left:20px;">
                <?php foreach ( $sampling['warnings'] as $warning ) : ?><li><?php echo esc_html( (string) $warning ); ?></li><?php endforeach; ?>
            </ul></div>
        <?php endif; ?>

        <h3><?php echo esc_html( self::label( 'summary', $language ) ); ?></h3>
        <ul style="max-width:1100px;background:#fff;border:1px solid #dcdcde;padding:16px 16px 16px 36px;">
            <?php foreach ( $review['summary'] ?? array() as $item ) : ?><li><?php echo esc_html( (string) $item ); ?></li><?php endforeach; ?>
        </ul>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;max-width:1100px;">
            <div style="background:#fff;border:1px solid #dcdcde;padding:16px;">
                <h3 style="margin-top:0;"><?php echo esc_html( self::label( 'observed', $language ) ); ?></h3>
                <?php self::scenario_list( $observed['scenario_snapshot_counts'] ?? array(), $observed['scenario_first_seen_minute'] ?? array(), $language ); ?>
            </div>
            <div style="background:#fff;border:1px solid #dcdcde;padding:16px;">
                <h3 style="margin-top:0;"><?php echo esc_html( self::label( 'activated_b', $language ) ); ?></h3>
                <?php self::scenario_list( $activated['plan_b_scenario_counts'] ?? array(), $activated['plan_b_first_activation_minute'] ?? array(), $language ); ?>
            </div>
        </div>

        <?php if ( ! empty( $alignment['unprepared_observed_scenarios'] ) ) : ?>
            <h3><?php echo esc_html( self::label( 'unprepared_observed', $language ) ); ?></h3>
            <p><?php echo esc_html( implode( ', ', array_map( 'strval', $alignment['unprepared_observed_scenarios'] ) ) ); ?></p>
        <?php endif; ?>

        <h3><?php echo esc_html( self::label( 'timeline', $language ) ); ?></h3>
        <table class="widefat striped" style="max-width:1100px;"><thead><tr><th><?php echo esc_html( self::label( 'minute', $language ) ); ?></th><th><?php echo esc_html( self::label( 'from', $language ) ); ?></th><th><?php echo esc_html( self::label( 'to', $language ) ); ?></th><th><?php echo esc_html( self::label( 'scenario', $language ) ); ?></th></tr></thead><tbody>
            <?php foreach ( $activated['transitions'] ?? array() as $row ) : ?><tr><td><?php echo esc_html( (string) $row['minute'] ); ?>'</td><td><?php echo esc_html( (string) $row['from_plan'] . ' / ' . (string) $row['from_mode'] ); ?></td><td><?php echo esc_html( (string) $row['to_plan'] . ' / ' . (string) $row['to_mode'] ); ?></td><td><?php echo esc_html( (string) ( $row['to_scenario'] ?? '' ) ); ?></td></tr><?php endforeach; ?>
        </tbody></table>

        <?php if ( ! empty( $review['staff_notes'] ) ) : ?>
            <h3><?php echo esc_html( self::label( 'notes', $language ) ); ?></h3><ul style="max-width:1100px;background:#fff;border:1px solid #dcdcde;padding:16px 16px 16px 36px;">
                <?php foreach ( $review['staff_notes'] as $note ) : ?><li><strong><?php echo esc_html( (string) $note['minute'] ); ?>'</strong> — <?php echo esc_html( (string) $note['note'] ); ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <details style="max-width:1100px;margin-top:22px;"><summary><strong><?php echo esc_html( self::label( 'technical', $language ) ); ?></strong></summary><pre style="white-space:pre-wrap;background:#fff;padding:16px;border:1px solid #dcdcde;"><?php echo esc_html( wp_json_encode( $review, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre></details>
        <?php
    }

    private static function render_signoff( array $review, string $session_uuid, array $latest, array $revisions, bool $binding_ready, string $language ): void {
        $review_sha = TactiTwin_Post_Match_Review_Snapshot::review_sha256( $review );
        $objective_ready = 'available' === (string) ( $review['objective_evidence']['status'] ?? '' );
        $session_closed = 'closed' === (string) ( $review['session']['status'] ?? '' );
        $can_sign = $binding_ready && $objective_ready && $session_closed;
        $latest_same = ! empty( $latest ) && hash_equals( (string) ( $latest['review_sha256'] ?? '' ), $review_sha );
        ?>
        <section style="max-width:1100px;background:#fff;border:1px solid #dcdcde;border-left:5px solid #135e96;padding:20px;margin:28px 0;">
            <h2 style="margin-top:0;"><?php echo esc_html( self::label( 'signoff_title', $language ) ); ?></h2>
            <p><?php echo esc_html( self::label( 'signoff_intro', $language ) ); ?></p>
            <p><strong>review_sha256:</strong> <code><?php echo esc_html( substr( $review_sha, 0, 20 ) . '…' ); ?></code></p>

            <?php if ( ! empty( $latest ) ) : ?>
                <div class="notice notice-success inline"><p><?php echo esc_html( sprintf( self::label( 'latest_signed', $language ), (int) ( $latest['revision_number'] ?? 0 ), (string) ( $latest['signed_at'] ?? '' ) ) ); ?><?php echo $latest_same ? ' · ' . esc_html( self::label( 'same_review', $language ) ) : ''; ?></p></div>
            <?php endif; ?>

            <?php if ( ! $can_sign ) : ?>
                <div class="notice notice-warning inline"><p><?php echo esc_html( self::label( 'cannot_sign', $language ) ); ?></p></div>
            <?php else : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px;">
                    <input type="hidden" name="action" value="tactitwin_signoff_post_match_review">
                    <input type="hidden" name="session_uuid" value="<?php echo esc_attr( $session_uuid ); ?>">
                    <input type="hidden" name="expected_review_sha256" value="<?php echo esc_attr( $review_sha ); ?>">
                    <?php wp_nonce_field( 'tactitwin_signoff_post_match_review' ); ?>
                    <p><label for="tt-review-signoff-note"><strong><?php echo esc_html( self::label( 'signoff_note', $language ) ); ?></strong></label></p>
                    <textarea id="tt-review-signoff-note" name="signoff_note" rows="4" style="width:100%;max-width:900px;"></textarea>
                    <p class="description"><?php echo esc_html( self::label( 'signoff_guardrail', $language ) ); ?></p>
                    <?php submit_button( self::label( empty( $latest ) ? 'signoff_button' : 'new_revision_button', $language ), 'primary', 'submit', false ); ?>
                </form>
            <?php endif; ?>

            <?php if ( ! empty( $revisions ) ) : ?>
                <h3><?php echo esc_html( self::label( 'revision_history', $language ) ); ?></h3>
                <table class="widefat striped"><thead><tr><th><?php echo esc_html( self::label( 'revision', $language ) ); ?></th><th>UUID</th><th><?php echo esc_html( self::label( 'signed_at', $language ) ); ?></th><th><?php echo esc_html( self::label( 'signed_by', $language ) ); ?></th><th>SHA-256</th></tr></thead><tbody>
                <?php foreach ( $revisions as $row ) : ?>
                    <tr><td><?php echo esc_html( '#' . (string) ( $row['revision_number'] ?? '' ) ); ?></td><td><code><?php echo esc_html( (string) ( $row['review_uuid'] ?? '' ) ); ?></code></td><td><?php echo esc_html( (string) ( $row['signed_at'] ?? '' ) ); ?></td><td><?php echo esc_html( (string) ( $row['signed_by'] ?? '' ) ); ?></td><td><code><?php echo esc_html( substr( (string) ( $row['content_sha256'] ?? '' ), 0, 16 ) . '…' ); ?></code></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
        </section>
        <?php
    }

    private static function scenario_list( array $counts, array $first_seen, string $language ): void {
        if ( empty( $counts ) ) {
            echo '<p>' . esc_html( self::label( 'none', $language ) ) . '</p>';
            return;
        }
        echo '<ul style="list-style:disc;padding-left:20px;">';
        foreach ( $counts as $scenario=>$count ) {
            $minute = isset( $first_seen[$scenario] ) ? " · " . (int) $first_seen[$scenario] . "'" : '';
            echo '<li><code>' . esc_html( (string) $scenario ) . '</code> · ' . esc_html( (string) $count ) . esc_html( $minute ) . '</li>';
        }
        echo '</ul>';
    }

    private static function card( string $title, string $value, string $meta ): void {
        ?><div style="background:#fff;border:1px solid #dcdcde;padding:16px;"><div style="font-size:12px;text-transform:uppercase;color:#646970;"><?php echo esc_html($title); ?></div><div style="font-size:21px;font-weight:700;margin-top:4px;word-break:break-word;"><?php echo esc_html($value); ?></div><div style="color:#646970;margin-top:4px;"><?php echo esc_html($meta); ?></div></div><?php
    }

    private static function notice( string $type, string $message ): void {
        set_transient(
            'tactitwin_post_match_signoff_notice_' . get_current_user_id(),
            array( 'type' => $type, 'message' => $message ),
            MINUTE_IN_SECONDS
        );
    }

    private static function label( string $key, string $language ): string {
        $catalog = array(
            'en'=>array(
                'title'=>'Session Review','intro'=>'Compare what was prepared, what staff observed and what was actually activated. This is an audit trail, not a coaching-quality score.','sessions'=>'Recent live sessions','no_sessions'=>'No live sessions are available yet.','session'=>'Session','teams'=>'Teams','status'=>'Status','updated'=>'Updated','review'=>'Review','review_title'=>'Post-match review','final_score'=>'Final stored score','prepared_b'=>'Prepared Plan B','observed_behaviours'=>'Observed behaviours','manual_observations'=>'manual supported behaviours','transitions'=>'Transitions','plan_mode_changes'=>'plan/mode/scenario changes','sampling'=>'Sampling','snapshots'=>'snapshots','alignment'=>'Prepared vs observed vs activated','summary'=>'Review summary','observed'=>'Observed behaviours','activated_b'=>'Activated Plan B scenarios','unprepared_observed'=>'Observed outside the prepared priority set','timeline'=>'Decision transitions','minute'=>'Minute','from'=>'From','to'=>'To','scenario'=>'Scenario','notes'=>'Staff notes','technical'=>'Technical review evidence','none'=>'None recorded',
                'signoff_title'=>'Post-match review sign-off','signoff_intro'=>'Freeze the exact review and evidence currently displayed into an immutable, versioned staff sign-off.','latest_signed'=>'Latest signed revision #%d · %s','same_review'=>'same review evidence','cannot_sign'=>'Sign-off requires a closed Live Session, the exact approved fixture chain and available objective match evidence.','signoff_note'=>'Staff sign-off note','signoff_guardrail'=>'This records an application-level staff sign-off with integrity hashes; it is not a cryptographic digital signature. If the review changes before submission, sign-off is rejected.','signoff_button'=>'Sign off review','new_revision_button'=>'Create signed revision','revision_history'=>'Signed revision history','revision'=>'Revision','signed_at'=>'Signed at','signed_by'=>'Signed by',
            ),
            'es'=>array(
                'title'=>'Session Review','intro'=>'Compara lo preparado, lo observado por el cuerpo técnico y lo que realmente se activó. Es una trazabilidad del partido, no una nota sobre la calidad del entrenador.','sessions'=>'Sesiones en vivo recientes','no_sessions'=>'Todavía no hay sesiones en vivo disponibles.','session'=>'Sesión','teams'=>'Equipos','status'=>'Estado','updated'=>'Actualizada','review'=>'Revisar','review_title'=>'Revisión post-partido','final_score'=>'Marcador final guardado','prepared_b'=>'Plan B preparado','observed_behaviours'=>'Comportamientos observados','manual_observations'=>'comportamientos soportados observados','transitions'=>'Transiciones','plan_mode_changes'=>'cambios de plan/modo/escenario','sampling'=>'Muestreo','snapshots'=>'snapshots','alignment'=>'Preparado vs observado vs activado','summary'=>'Resumen de revisión','observed'=>'Comportamientos observados','activated_b'=>'Escenarios Plan B activados','unprepared_observed'=>'Observado fuera de las prioridades preparadas','timeline'=>'Transiciones de decisión','minute'=>'Minuto','from'=>'Desde','to'=>'Hacia','scenario'=>'Escenario','notes'=>'Notas del cuerpo técnico','technical'=>'Evidencia técnica de la revisión','none'=>'Ninguno registrado',
                'signoff_title'=>'Cierre de la revisión post-partido','signoff_intro'=>'Congela la revisión y la evidencia exactas que estás viendo en un cierre inmutable y versionado del cuerpo técnico.','latest_signed'=>'Última revisión firmada #%d · %s','same_review'=>'misma evidencia de revisión','cannot_sign'=>'El cierre requiere una Live Session cerrada, la cadena exacta del fixture aprobado y evidencia objetiva del partido disponible.','signoff_note'=>'Nota de cierre del cuerpo técnico','signoff_guardrail'=>'Registra un sign-off del cuerpo técnico a nivel de aplicación con hashes de integridad; no es una firma digital criptográfica. Si la revisión cambia antes de enviar, el cierre se rechaza.','signoff_button'=>'Cerrar y firmar revisión','new_revision_button'=>'Crear revisión firmada','revision_history'=>'Historial de revisiones firmadas','revision'=>'Revisión','signed_at'=>'Firmada el','signed_by'=>'Firmada por',
            ),
        );
        return $catalog[$language][$key] ?? $catalog['en'][$key] ?? $key;
    }
}
