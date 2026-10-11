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
            $review_context = array(
                'enforce_review_guard' => true,
                'expected_parent_snapshot_uuid' => sanitize_text_field( wp_unslash( (string) ( $_POST['expected_parent_snapshot_uuid'] ?? '' ) ) ),
                'expected_diff_sha256' => sanitize_text_field( wp_unslash( (string) ( $_POST['expected_diff_sha256'] ?? '' ) ) ),
                'acknowledged_changes' => ! empty( $_POST['acknowledged_changes'] ),
                'approval_note' => sanitize_textarea_field( wp_unslash( (string) ( $_POST['approval_note'] ?? '' ) ) ),
            );
            $snapshot = ( new TactiTwin_Staff_Brief_Snapshot_Service() )->approve( $result['brief'], $user, $sample, $review_context );
            $revision = TactiTwin_Staff_Brief_Snapshot::revision( $snapshot );
            $result['approved_snapshot_uuid'] = (string) $snapshot['snapshot_uuid'];
            $result['approved_snapshot_sha256'] = (string) $snapshot['content_sha256'];
            $result['approved_at'] = (string) $snapshot['approved_at'];
            $result['approved_revision_number'] = (int) ( $revision['revision_number'] ?? 0 );
            $result['approved_parent_snapshot_uuid'] = (string) ( $revision['parent_snapshot_uuid'] ?? '' );
            $result['approved_reviewed_change_count'] = (int) ( $revision['reviewed_change_count'] ?? 0 );
            $result['approved_revision_sha256'] = (string) ( $revision['revision_sha256'] ?? '' );
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
        $diff = array();
        $approval_review = array();
        $current_snapshot = array();
        try {
            $service = new TactiTwin_Staff_Brief_Snapshot_Service();
            $recent = $service->recent( 5 );
            if ( is_array( $result ) && ! empty( $result['brief'] ) && is_array( $result['brief'] ) ) {
                $brief = $result['brief'];
                $team_id = (string) ( $brief['team']['source_id'] ?? '' );
                $opponent_id = (string) ( $brief['opponent']['source_id'] ?? '' );
                $current_uuid = (string) ( $result['approved_snapshot_uuid'] ?? '' );
                if ( '' !== $current_uuid ) {
                    $previous = $service->latest_for_matchup( $team_id, $opponent_id, $current_uuid );
                    $current_snapshot = $service->snapshot( $current_uuid );
                    if ( ! empty( $previous ) && ! empty( $current_snapshot ) ) {
                        $diff = TactiTwin_Staff_Brief_Version_Diff::snapshots( $previous, $current_snapshot, $language );
                    }
                } else {
                    $approval_review = $service->approval_review( $brief, $language );
                    $parent_uuid = (string) ( $approval_review['parent_snapshot_uuid'] ?? '' );
                    if ( '' !== $parent_uuid ) {
                        $previous = $service->snapshot( $parent_uuid );
                        if ( ! empty( $previous ) ) {
                            $diff = TactiTwin_Staff_Brief_Version_Diff::current_against_snapshot( $brief, $previous, $language );
                        }
                    }
                }
            }
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
                <?php self::render_diff( $diff, $language ); ?>
                <div style="background:#fff;border:1px solid #dcdcde;padding:16px;margin:12px 0;">
                    <?php if ( empty( $result['approved_snapshot_uuid'] ) ) : ?>
                        <p><strong><?php echo esc_html( self::text( 'ready', $language ) ); ?></strong></p>
                        <?php if ( ! empty( $approval_review ) ) : ?>
                            <p class="description"><?php echo esc_html( sprintf( self::text( 'revision_target', $language ), (int) ( $approval_review['revision_number'] ?? 1 ) ) ); ?></p>
                        <?php endif; ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="tactitwin_approve_staff_brief">
                            <input type="hidden" name="expected_parent_snapshot_uuid" value="<?php echo esc_attr( (string) ( $approval_review['parent_snapshot_uuid'] ?? '' ) ); ?>">
                            <input type="hidden" name="expected_diff_sha256" value="<?php echo esc_attr( (string) ( $approval_review['reviewed_diff_sha256'] ?? '' ) ); ?>">
                            <?php wp_nonce_field( 'tactitwin_approve_staff_brief' ); ?>
                            <?php if ( ! empty( $approval_review['requires_change_acknowledgement'] ) ) : ?>
                                <p><label><input type="checkbox" name="acknowledged_changes" value="1" required> <strong><?php echo esc_html( self::text( 'ack_changes', $language ) ); ?></strong></label></p>
                            <?php endif; ?>
                            <p><label for="tactitwin-approval-note"><strong><?php echo esc_html( self::text( 'approval_note', $language ) ); ?></strong></label><br>
                            <textarea id="tactitwin-approval-note" name="approval_note" rows="3" maxlength="1000" style="width:100%;max-width:760px;"></textarea><br><span class="description"><?php echo esc_html( self::text( 'approval_note_help', $language ) ); ?></span></p>
                            <?php submit_button( self::text( 'approve', $language ), 'primary', 'submit', false ); ?>
                        </form>
                    <?php else : ?>
                        <?php
                        $revision = ! empty( $current_snapshot ) ? TactiTwin_Staff_Brief_Snapshot::revision( $current_snapshot ) : array();
                        $revision_number = (int) ( $revision['revision_number'] ?? $result['approved_revision_number'] ?? 0 );
                        $parent_uuid = (string) ( $revision['parent_snapshot_uuid'] ?? $result['approved_parent_snapshot_uuid'] ?? '' );
                        ?>
                        <p><strong><?php echo esc_html( self::text( 'approved', $language ) ); ?></strong><?php if ( $revision_number > 0 ) : ?> · <?php echo esc_html( sprintf( self::text( 'revision_label', $language ), $revision_number ) ); ?><?php endif; ?></p>
                        <p><code><?php echo esc_html( (string) $result['approved_snapshot_uuid'] ); ?></code><br><small>SHA-256: <?php echo esc_html( (string) ( $result['approved_snapshot_sha256'] ?? '' ) ); ?></small></p>
                        <?php if ( '' !== $parent_uuid ) : ?><p class="description"><?php echo esc_html( self::text( 'parent', $language ) ); ?> <code><?php echo esc_html( $parent_uuid ); ?></code></p><?php endif; ?>
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

    private static function render_diff( array $diff, string $language ): void {
        if ( empty( $diff ) ) {
            return;
        }
        $changed = 'changed' === (string) ( $diff['status'] ?? '' );
        $border = $changed ? '#dba617' : '#00a32a';
        ?>
        <div style="background:#fff;border-left:4px solid <?php echo esc_attr( $border ); ?>;padding:14px 16px;margin:12px 0;">
            <h3 style="margin-top:0;"><?php echo esc_html( self::text( 'diff_title', $language ) ); ?></h3>
            <p><?php echo esc_html( $changed ? sprintf( self::text( 'diff_count', $language ), (int) ( $diff['change_count'] ?? 0 ) ) : self::text( 'diff_none', $language ) ); ?></p>
            <?php if ( ! empty( $diff['from']['snapshot_uuid'] ) ) : ?><p class="description"><?php echo esc_html( self::text( 'compared_with', $language ) ); ?> <code><?php echo esc_html( (string) $diff['from']['snapshot_uuid'] ); ?></code> · <?php echo esc_html( (string) ( $diff['from']['approved_at'] ?? '' ) ); ?></p><?php endif; ?>
            <?php if ( $changed ) : ?>
                <ul style="list-style:disc;padding-left:22px;">
                    <?php foreach ( $diff['changes'] ?? array() as $change ) : ?>
                        <li style="margin-bottom:6px;"><strong><?php echo esc_html( (string) ( $change['label'] ?? $change['id'] ?? '' ) ); ?></strong><?php echo esc_html( self::change_context( $change, $language ) ); ?></li>
                    <?php endforeach; ?>
                </ul>
                <details><summary><?php echo esc_html( self::text( 'diff_details', $language ) ); ?></summary><pre style="white-space:pre-wrap;max-height:360px;overflow:auto;background:#f6f7f7;padding:12px;"><?php echo esc_html( wp_json_encode( $diff['changes'] ?? array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre></details>
            <?php endif; ?>
            <p class="description"><?php echo esc_html( self::text( 'diff_guardrail', $language ) ); ?></p>
        </div>
        <?php
    }

    private static function change_context( array $change, string $language ): string {
        $context = is_array( $change['context'] ?? null ) ? $change['context'] : array();
        if ( ! empty( $context['plan_id'] ) ) {
            return ' · ' . self::text( 'plan', $language ) . ' ' . (string) $context['plan_id'] . self::changed_fields_suffix( $context, $language );
        }
        if ( ! empty( $context['scenario_id'] ) ) {
            return ' · ' . (string) $context['scenario_id'] . self::changed_fields_suffix( $context, $language );
        }
        return '';
    }

    private static function changed_fields_suffix( array $context, string $language ): string {
        $fields = is_array( $context['changed_fields'] ?? null ) ? $context['changed_fields'] : array();
        return empty( $fields ) ? '' : ' · ' . self::text( 'fields', $language ) . ': ' . implode( ', ', array_map( 'strval', $fields ) );
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
            $revision = TactiTwin_Staff_Brief_Snapshot::revision( $snapshot );
            $language = TactiTwin_I18n::normalize_language( (string) ( $session['language'] ?? 'en' ) );
            ?>
            <div class="wrap" style="max-width:1100px;margin-top:22px;"><div style="background:#fff;border-left:4px solid #00a32a;padding:14px 16px;">
                <strong><?php echo esc_html( self::text( 'origin', $language ) ); ?></strong>
                <p style="margin-bottom:4px;"><code><?php echo esc_html( $origin_uuid ); ?></code><?php if ( ! empty( $revision['revision_number'] ) ) : ?> · <?php echo esc_html( sprintf( self::text( 'revision_label', $language ), (int) $revision['revision_number'] ) ); ?><?php endif; ?></p>
                <small>SHA-256: <?php echo esc_html( (string) ( $snapshot['content_sha256'] ?? '' ) ); ?> · <?php echo esc_html( self::text( ! empty( $snapshot['storage']['integrity_valid'] ) ? 'integrity_ok' : 'integrity_bad', $language ) ); ?></small>
                <?php if ( ! empty( $revision['parent_snapshot_uuid'] ) ) : ?><p class="description"><?php echo esc_html( self::text( 'parent', $language ) ); ?> <code><?php echo esc_html( (string) $revision['parent_snapshot_uuid'] ); ?></code></p><?php endif; ?>
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
                'diff_title'=>'Changes since last approval','diff_none'=>'No tracked Staff Brief field changed since the previous approved version.','diff_count'=>'%d tracked changes detected before approval.','compared_with'=>'Compared with','diff_details'=>'Technical before/after details','diff_guardrail'=>'This is a structural audit diff only. It does not score the changes, recalculate tactics, reorder evidence or activate a plan.','plan'=>'Plan','fields'=>'fields',
                'revision_target'=>'This approval will create revision #%d.','revision_label'=>'Revision #%d','parent'=>'Previous approved snapshot:','ack_changes'=>'I reviewed the displayed changes and approve this revised brief.','approval_note'=>'Approval note (optional)','approval_note_help'=>'Stored inside the immutable revision record. It does not change any tactical calculation.',
            ),
            'es' => array(
                'title'=>'Handoff del Staff Brief aprobado','intro'=>'Aprueba el brief prepartido exacto para congelar su Match Plan y Playbook. Una Live Session iniciada desde él conserva ese origen inmutable.','ready'=>'Este brief generado está listo para aprobación explícita del staff.','approve'=>'Aprobar y congelar este brief','approved'=>'Snapshot inmutable aprobado','start'=>'Iniciar / abrir Live Session','recent'=>'Briefs aprobados recientes','snapshot'=>'Snapshot','matchup'=>'Partido','approved_at'=>'Aprobado','origin'=>'Origen de Live Session: Staff Brief aprobado','integrity_ok'=>'integridad verificada','integrity_bad'=>'fallo de integridad',
                'diff_title'=>'Cambios desde la última aprobación','diff_none'=>'No cambió ningún campo controlado del Staff Brief desde la versión aprobada anterior.','diff_count'=>'Se detectaron %d cambios controlados antes de aprobar.','compared_with'=>'Comparado con','diff_details'=>'Detalle técnico antes/después','diff_guardrail'=>'Es solo un diff estructural de auditoría. No puntúa los cambios, no recalcula táctica, no reordena evidencia ni activa un plan.','plan'=>'Plan','fields'=>'campos',
                'revision_target'=>'Esta aprobación creará la revisión #%d.','revision_label'=>'Revisión #%d','parent'=>'Snapshot aprobado anterior:','ack_changes'=>'He revisado los cambios mostrados y apruebo este brief revisado.','approval_note'=>'Nota de aprobación (opcional)','approval_note_help'=>'Se guarda dentro del registro inmutable de revisión. No cambia ningún cálculo táctico.',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
