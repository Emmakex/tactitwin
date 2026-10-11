<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Staff_Brief_Handoff_Admin {
    public static function register(): void {
        add_action( 'admin_post_tactitwin_approve_staff_brief', array( self::class, 'approve' ) );
        add_action( 'admin_post_tactitwin_handoff_staff_brief', array( self::class, 'handoff' ) );
        add_action( 'admin_footer', array( self::class, 'render' ) );
    }

    public static function approve(): void {
        self::guard( 'tactitwin_approve_staff_brief' );
        $user = get_current_user_id();
        $result = get_transient( 'tactitwin_staff_brief_' . $user );
        try {
            if ( ! is_array( $result ) || empty( $result['brief'] ) || ! is_array( $result['brief'] ) ) {
                throw new RuntimeException( 'Generate a Staff Brief before approving it.' );
            }
            $sample = max( 1, min( 10, (int) ( $result['form']['sample'] ?? 5 ) ) );
            $snapshot = ( new TactiTwin_Staff_Brief_Snapshot_Service() )->approve( $result['brief'], $user, $sample );
            $result['approved_snapshot_uuid'] = (string) $snapshot['snapshot_uuid'];
            $result['approved_snapshot_sha256'] = (string) $snapshot['content_sha256'];
            $result['approved_at'] = (string) $snapshot['approved_at'];
            set_transient( 'tactitwin_staff_brief_' . $user, $result, 30 * MINUTE_IN_SECONDS );
        } catch ( Throwable $e ) {
            set_transient( 'tactitwin_staff_brief_handoff_error_' . $user, $e->getMessage(), MINUTE_IN_SECONDS );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=tactitwin-staff-brief' ) );
        exit;
    }

    public static function handoff(): void {
        self::guard( 'tactitwin_handoff_staff_brief' );
        $user = get_current_user_id();
        $snapshot_uuid = sanitize_text_field( wp_unslash( (string) ( $_POST['snapshot_uuid'] ?? '' ) ) );
        try {
            $result = ( new TactiTwin_Staff_Brief_Snapshot_Service() )->handoff( $snapshot_uuid, $user );
            $session = is_array( $result['session'] ?? null ) ? $result['session'] : array();
            $snapshot = is_array( $result['staff_brief_snapshot'] ?? null ) ? $result['staff_brief_snapshot'] : array();
            $first = is_array( $result['snapshot'] ?? null ) ? $result['snapshot'] : array();
            set_transient(
                'tactitwin_live_session_result_' . $user,
                array(
                    'playbook' => TactiTwin_Staff_Brief_Snapshot::playbook( $snapshot ),
                    'snapshot' => $first,
                    'language' => (string) ( $snapshot['language'] ?? 'en' ),
                    'sample' => (int) ( $snapshot['sample_size'] ?? 5 ),
                    'staff_brief_snapshot_uuid' => $snapshot_uuid,
                ),
                10 * MINUTE_IN_SECONDS
            );
            wp_safe_redirect( admin_url( 'admin.php?page=tactitwin-live-sessions&session_uuid=' . rawurlencode( (string) ( $session['session_uuid'] ?? '' ) ) ) );
            exit;
        } catch ( Throwable $e ) {
            set_transient( 'tactitwin_staff_brief_handoff_error_' . $user, $e->getMessage(), MINUTE_IN_SECONDS );
            wp_safe_redirect( admin_url( 'admin.php?page=tactitwin-staff-brief' ) );
            exit;
        }
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $page = sanitize_key( (string) ( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'tactitwin-staff-brief' === $page ) {
            self::render_staff_brief_controls();
        } elseif ( 'tactitwin-live-sessions' === $page ) {
            self::render_live_origin();
        }
    }

    private static function render_staff_brief_controls(): void {
        $user = get_current_user_id();
        $result = get_transient( 'tactitwin_staff_brief_' . $user );
        $error = get_transient( 'tactitwin_staff_brief_handoff_error_' . $user );
        if ( $error ) {
            delete_transient( 'tactitwin_staff_brief_handoff_error_' . $user );
        }
        $language = is_array( $result ) ? TactiTwin_I18n::normalize_language( (string) ( $result['language'] ?? 'en' ) ) : TactiTwin_I18n::current_language();
        $recent = array();
        try {
            $recent = ( new TactiTwin_Staff_Brief_Snapshot_Service() )->recent( 5 );
        } catch ( Throwable $e ) {
            if ( ! $error ) {
                $error = $e->getMessage();
            }
        }
        ?>
        <div class="wrap" style="max-width:1080px;margin-top:22px;">
            <hr>
            <h2><?php echo esc_html( self::text( 'title', $language ) ); ?></h2>
            <p><?php echo esc_html( self::text( 'intro', $language ) ); ?></p>
            <?php if ( $error ) : ?><div class="notice notice-error inline"><p><?php echo esc_html( (string) $error ); ?></p></div><?php endif; ?>
            <?php if ( is_array( $result ) && ! empty( $result['brief'] ) ) : ?>
                <div style="background:#fff;border:1px solid #dcdcde;padding:16px;margin:12px 0;">
                    <?php if ( empty( $result['approved_snapshot_uuid'] ) ) : ?>
                        <p><strong><?php echo esc_html( self::text( 'ready', $language ) ); ?></strong></p>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="tactitwin_approve_staff_brief">
                            <?php wp_nonce_field( 'tactitwin_approve_staff_brief' ); ?>
                            <?php submit_button( self::text( 'approve', $language ), 'primary', 'submit', false ); ?>
                        </form>
                    <?php else : ?>
                        <p><strong><?php echo esc_html( self::text( 'approved', $language ) ); ?></strong></p>
                        <p><code><?php echo esc_html( (string) $result['approved_snapshot_uuid'] ); ?></code><br><small>SHA-256: <?php echo esc_html( (string) ( $result['approved_snapshot_sha256'] ?? '' ) ); ?></small></p>
                        <?php self::handoff_form( (string) $result['approved_snapshot_uuid'], self::text( 'start', $language ) ); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ( ! empty( $recent ) ) : ?>
                <h3><?php echo esc_html( self::text( 'recent', $language ) ); ?></h3>
                <table class="widefat striped"><thead><tr><th><?php echo esc_html( self::text( 'snapshot', $language ) ); ?></th><th><?php echo esc_html( self::text( 'matchup', $language ) ); ?></th><th><?php echo esc_html( self::text( 'approved_at', $language ) ); ?></th><th></th></tr></thead><tbody>
                <?php foreach ( $recent as $row ) : ?>
                    <tr><td><code><?php echo esc_html( (string) $row['snapshot_uuid'] ); ?></code><br><small><?php echo esc_html( substr( (string) $row['content_sha256'], 0, 16 ) . '…' ); ?></small></td><td><?php echo esc_html( (string) $row['team_source_id'] . ' vs ' . (string) $row['opponent_source_id'] ); ?></td><td><?php echo esc_html( (string) $row['approved_at'] ); ?></td><td><?php self::handoff_form( (string) $row['snapshot_uuid'], self::text( 'start', $language ) ); ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_live_origin(): void {
        $session_uuid = sanitize_text_field( wp_unslash( (string) ( $_GET['session_uuid'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( '' === $session_uuid ) {
            return;
        }
        try {
            $session = ( new TactiTwin_Live_Session_Service() )->session( $session_uuid );
            $origin_uuid = (string) ( $session['staff_brief_snapshot_uuid'] ?? '' );
            if ( '' === $origin_uuid ) {
                return;
            }
            $snapshot = ( new TactiTwin_Staff_Brief_Snapshot_Service() )->snapshot( $origin_uuid );
            $language = TactiTwin_I18n::normalize_language( (string) ( $session['language'] ?? 'en' ) );
            ?>
            <div class="wrap" style="max-width:1100px;margin-top:22px;"><div style="background:#fff;border-left:4px solid #00a32a;padding:14px 16px;">
                <strong><?php echo esc_html( self::text( 'origin', $language ) ); ?></strong>
                <p style="margin-bottom:4px;"><code><?php echo esc_html( $origin_uuid ); ?></code></p>
                <small>SHA-256: <?php echo esc_html( (string) ( $snapshot['content_sha256'] ?? '' ) ); ?> · <?php echo esc_html( self::text( ! empty( $snapshot['storage']['integrity_valid'] ) ? 'integrity_ok' : 'integrity_bad', $language ) ); ?></small>
            </div></div>
            <?php
        } catch ( Throwable $e ) {
            return;
        }
    }

    private static function handoff_form( string $snapshot_uuid, string $label ): void {
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
            <input type="hidden" name="action" value="tactitwin_handoff_staff_brief"><input type="hidden" name="snapshot_uuid" value="<?php echo esc_attr( $snapshot_uuid ); ?>">
            <?php wp_nonce_field( 'tactitwin_handoff_staff_brief' ); ?>
            <?php submit_button( $label, 'secondary', 'submit', false ); ?>
        </form>
        <?php
    }

    private static function guard( string $nonce_action ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage TactiTwin.', 'tactitwin' ) );
        }
        check_admin_referer( $nonce_action );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'title'=>'Approved Staff Brief handoff','intro'=>'Approve the exact pre-match brief to freeze its Match Plan and Playbook. A Live Session started from it keeps that immutable origin.','ready'=>'This generated brief is ready for explicit staff approval.','approve'=>'Approve and freeze this brief','approved'=>'Approved immutable snapshot','start'=>'Start / open Live Session','recent'=>'Recent approved briefs','snapshot'=>'Snapshot','matchup'=>'Matchup','approved_at'=>'Approved at','origin'=>'Live Session origin: approved Staff Brief','integrity_ok'=>'integrity verified','integrity_bad'=>'integrity failed',
            ),
            'es' => array(
                'title'=>'Handoff del Staff Brief aprobado','intro'=>'Aprueba el brief prepartido exacto para congelar su Match Plan y Playbook. Una Live Session iniciada desde él conserva ese origen inmutable.','ready'=>'Este brief generado está listo para aprobación explícita del staff.','approve'=>'Aprobar y congelar este brief','approved'=>'Snapshot inmutable aprobado','start'=>'Iniciar / abrir Live Session','recent'=>'Briefs aprobados recientes','snapshot'=>'Snapshot','matchup'=>'Partido','approved_at'=>'Aprobado','origin'=>'Origen de Live Session: Staff Brief aprobado','integrity_ok'=>'integridad verificada','integrity_bad'=>'fallo de integridad',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
