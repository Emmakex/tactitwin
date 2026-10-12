<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Exposes the frozen Twin branch that corresponds to the latest Live Session
 * state and persists explicit staff confirmation as a new append-only live
 * snapshot. The active Plan A/B/C is never changed by this bridge.
 */
final class TactiTwin_Live_Twin_Switch_Confirmation_Admin_Bridge {
    private const RESULT_KEY = 'tactitwin_live_twin_switch_review_';
    private const ERROR_KEY  = 'tactitwin_live_twin_switch_review_error_';

    public static function register(): void {
        add_action( 'admin_post_tactitwin_review_live_twin_switch', array( self::class, 'review' ) );
        add_action( 'admin_footer', array( self::class, 'render' ), 13 );
    }

    public static function review(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage TactiTwin.', 'tactitwin' ) );
        }
        check_admin_referer( 'tactitwin_review_live_twin_switch' );

        $user = get_current_user_id();
        $session_uuid = sanitize_text_field( wp_unslash( (string) ( $_POST['session_uuid'] ?? '' ) ) );
        $decision = sanitize_key( wp_unslash( (string) ( $_POST['staff_decision'] ?? 'defer' ) ) );
        $note = sanitize_textarea_field( wp_unslash( (string) ( $_POST['review_note'] ?? '' ) ) );

        try {
            $service = new TactiTwin_Live_Session_Service();
            $timeline = $service->timeline( $session_uuid );
            $session = is_array( $timeline['session'] ?? null ) ? $timeline['session'] : array();
            if ( 'active' !== (string) ( $session['status'] ?? '' ) ) {
                throw new RuntimeException( 'Twin switch review can only be recorded in an active Live Session.' );
            }

            $snapshots = is_array( $timeline['snapshots'] ?? null ) ? $timeline['snapshots'] : array();
            if ( empty( $snapshots ) ) {
                throw new RuntimeException( 'Record a live state before reviewing the frozen Tactical Twin.' );
            }
            $latest = end( $snapshots );
            if ( ! is_array( $latest ) ) {
                throw new RuntimeException( 'Latest Live Session snapshot is unavailable.' );
            }

            $snapshot_uuid = (string) ( $session['staff_brief_snapshot_uuid'] ?? '' );
            if ( '' === trim( $snapshot_uuid ) ) {
                throw new RuntimeException( 'This Live Session has no approved Staff Brief origin.' );
            }
            $approved = ( new TactiTwin_Staff_Brief_Snapshot_Service() )->snapshot( $snapshot_uuid );
            if ( empty( $approved ) || ! TactiTwin_Staff_Brief_Snapshot::verify( $approved ) ) {
                throw new RuntimeException( 'The approved Staff Brief origin failed integrity verification.' );
            }
            $brief = TactiTwin_Staff_Brief_Snapshot::staff_brief( $approved );
            if ( ! TactiTwin_Frozen_Twin_Handoff::verify_attachment( $brief ) ) {
                throw new RuntimeException( 'The approved Staff Brief has no integrity-valid frozen Tactical Twin.' );
            }
            $twin = TactiTwin_Frozen_Twin_Handoff::twin_lab( $brief );
            $language = TactiTwin_I18n::normalize_language( (string) ( $session['language'] ?? $brief['language'] ?? 'en' ) );
            $context = self::context_from_snapshot( $latest );
            $review = TactiTwin_Live_Twin_Switch_Confirmation::review( $twin, $context, $decision, $note, $language );

            $playbook = is_array( $latest['playbook'] ?? null ) ? $latest['playbook'] : array();
            if ( 'contingency-playbook' !== (string) ( $playbook['playbook_id'] ?? '' ) ) {
                throw new RuntimeException( 'The latest Live Session snapshot has no valid Contingency Playbook.' );
            }

            $review['provenance'] = array(
                'staff_brief_snapshot_uuid' => $snapshot_uuid,
                'staff_brief_content_sha256' => (string) ( $approved['content_sha256'] ?? '' ),
                'frozen_twin_content_sha256' => (string) ( $brief['tactical_twin_preparation']['content_sha256'] ?? '' ),
                'reviewed_by' => $user,
                'reviewed_at' => current_time( 'mysql', true ),
            );
            $playbook['live_twin_switch_review'] = $review;
            if ( ! is_array( $playbook['method'] ?? null ) ) {
                $playbook['method'] = array();
            }
            $playbook['method']['live_twin_switch_confirmation'] = array(
                'source' => 'approved-frozen-tactical-twin',
                'append_only_snapshot' => true,
                'changes_active_plan' => false,
                'changes_plan_abc' => false,
                'automatic_activation' => false,
            );

            $persisted = $service->append_snapshot( $session_uuid, $playbook, $user );
            set_transient(
                self::RESULT_KEY . $user,
                array(
                    'session_uuid' => $session_uuid,
                    'snapshot_id' => (int) ( $persisted['snapshot']['id'] ?? 0 ),
                    'status' => (string) ( $review['staff_decision']['status'] ?? '' ),
                    'branch_name' => (string) ( $review['state_resolution']['branch_name'] ?? '' ),
                ),
                5 * MINUTE_IN_SECONDS
            );
        } catch ( Throwable $e ) {
            set_transient( self::ERROR_KEY . $user, $e->getMessage(), MINUTE_IN_SECONDS );
        }

        self::redirect( $session_uuid );
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $page = sanitize_key( (string) ( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'tactitwin-live-sessions' !== $page ) {
            return;
        }
        $session_uuid = sanitize_text_field( wp_unslash( (string) ( $_GET['session_uuid'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( '' === trim( $session_uuid ) ) {
            return;
        }

        $user = get_current_user_id();
        $error = get_transient( self::ERROR_KEY . $user );
        $result = get_transient( self::RESULT_KEY . $user );
        if ( $error ) {
            delete_transient( self::ERROR_KEY . $user );
        }
        if ( is_array( $result ) && $session_uuid === (string) ( $result['session_uuid'] ?? '' ) ) {
            delete_transient( self::RESULT_KEY . $user );
        } else {
            $result = null;
        }

        try {
            $service = new TactiTwin_Live_Session_Service();
            $timeline = $service->timeline( $session_uuid );
            $session = is_array( $timeline['session'] ?? null ) ? $timeline['session'] : array();
            $snapshots = is_array( $timeline['snapshots'] ?? null ) ? $timeline['snapshots'] : array();
            if ( empty( $snapshots ) ) {
                return;
            }
            $latest = end( $snapshots );
            if ( ! is_array( $latest ) ) {
                return;
            }
            $snapshot_uuid = (string) ( $session['staff_brief_snapshot_uuid'] ?? '' );
            if ( '' === $snapshot_uuid ) {
                return;
            }
            $approved = ( new TactiTwin_Staff_Brief_Snapshot_Service() )->snapshot( $snapshot_uuid );
            if ( empty( $approved ) || ! TactiTwin_Staff_Brief_Snapshot::verify( $approved ) ) {
                return;
            }
            $brief = TactiTwin_Staff_Brief_Snapshot::staff_brief( $approved );
            if ( ! TactiTwin_Frozen_Twin_Handoff::verify_attachment( $brief ) ) {
                return;
            }
            $twin = TactiTwin_Frozen_Twin_Handoff::twin_lab( $brief );
            $language = TactiTwin_I18n::normalize_language( (string) ( $session['language'] ?? $brief['language'] ?? 'en' ) );
            $preview = TactiTwin_Live_Twin_Switch_Confirmation::review(
                $twin,
                self::context_from_snapshot( $latest ),
                'defer',
                '',
                $language
            );
            self::render_panel(
                $session,
                $preview,
                self::latest_review( $snapshots ),
                $snapshot_uuid,
                (string) ( $brief['tactical_twin_preparation']['content_sha256'] ?? '' ),
                $result,
                $error,
                $language
            );
        } catch ( Throwable $e ) {
            return;
        }
    }

    private static function render_panel(
        array $session,
        array $preview,
        array $previous_review,
        string $snapshot_uuid,
        string $twin_sha,
        $result,
        $error,
        string $language
    ): void {
        $labels = self::labels( $language );
        $resolution = is_array( $preview['state_resolution'] ?? null ) ? $preview['state_resolution'] : array();
        $prepared = is_array( $preview['prepared_switch'] ?? null ) ? $preview['prepared_switch'] : array();
        $context = is_array( $preview['context'] ?? null ) ? $preview['context'] : array();
        $closed = 'closed' === (string) ( $session['status'] ?? '' );
        $available = 'prepared' === (string) ( $resolution['status'] ?? '' );
        ?>
        <section class="tt-live-twin-review" style="max-width:1100px;background:#fff;border:1px solid #8c8f94;padding:18px;margin:24px 0;">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
                <div>
                    <div style="font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#646970;"><?php echo esc_html( $labels['eyebrow'] ); ?></div>
                    <h2 style="margin:4px 0 6px;"><?php echo esc_html( $labels['title'] ); ?></h2>
                    <p style="margin:0;"><?php echo esc_html( $labels['intro'] ); ?></p>
                </div>
                <code title="Frozen Twin SHA-256"><?php echo esc_html( substr( $twin_sha, 0, 16 ) . '…' ); ?></code>
            </div>

            <?php if ( $error ) : ?><div class="notice notice-error inline"><p><?php echo esc_html( (string) $error ); ?></p></div><?php endif; ?>
            <?php if ( is_array( $result ) ) : ?><div class="notice notice-success inline"><p><?php echo esc_html( $labels['saved'] ); ?></p></div><?php endif; ?>

            <p><strong><?php echo esc_html( $labels['live_state'] ); ?>:</strong> <?php echo esc_html( self::context_label( $context ) ); ?></p>

            <?php if ( ! $available ) : ?>
                <div class="notice notice-warning inline"><p><strong><?php echo esc_html( $labels['no_branch'] ); ?></strong> <?php echo esc_html( (string) ( $resolution['note'] ?? '' ) ); ?></p></div>
            <?php else : ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin:14px 0;">
                    <?php self::box( $labels['branch'], (string) ( $resolution['branch_name'] ?? '-' ), (string) ( $resolution['match_type'] ?? '' ) ); ?>
                    <?php self::box( $labels['prepared_decision'], (string) ( $prepared['decision_label'] ?? '-' ), (string) ( $prepared['decision'] ?? '' ) ); ?>
                    <?php self::box( $labels['target'], (string) ( $prepared['target_response']['response_name'] ?? '-' ), $labels['margin'] . ': ' . (string) ( $prepared['state_fit_margin'] ?? 0 ) ); ?>
                    <?php self::box( $labels['baseline'], (string) ( $prepared['baseline_response']['response_name'] ?? '-' ), ! empty( $prepared['coverage_caution'] ) ? $labels['coverage_caution'] : $labels['coverage_ok'] ); ?>
                </div>

                <?php if ( ! empty( $prepared['validation_signals'] ) ) : ?>
                    <p><strong><?php echo esc_html( $labels['validate'] ); ?>:</strong></p>
                    <ul style="list-style:disc;margin-left:22px;">
                        <?php foreach ( $prepared['validation_signals'] as $signal ) : ?><li><?php echo esc_html( (string) $signal ); ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ( ! empty( $prepared['first_branch_recommendation'] ) ) : ?><p><strong><?php echo esc_html( $labels['prepared_instruction'] ); ?>:</strong> <?php echo esc_html( (string) $prepared['first_branch_recommendation'] ); ?></p><?php endif; ?>

                <?php if ( ! empty( $previous_review ) ) : ?>
                    <div style="background:#f6f7f7;padding:12px;margin:14px 0;">
                        <strong><?php echo esc_html( $labels['previous'] ); ?>:</strong>
                        <?php echo esc_html( (string) ( $previous_review['staff_decision']['status'] ?? '-' ) ); ?>
                        <?php if ( ! empty( $previous_review['provenance']['reviewed_at'] ) ) : ?> · <?php echo esc_html( (string) $previous_review['provenance']['reviewed_at'] ); ?><?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ( ! $closed ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px;">
                        <input type="hidden" name="action" value="tactitwin_review_live_twin_switch">
                        <input type="hidden" name="session_uuid" value="<?php echo esc_attr( (string) ( $session['session_uuid'] ?? '' ) ); ?>">
                        <?php wp_nonce_field( 'tactitwin_review_live_twin_switch' ); ?>
                        <fieldset>
                            <legend><strong><?php echo esc_html( $labels['staff_decision'] ); ?></strong></legend>
                            <p><label><input type="radio" name="staff_decision" value="confirm-prepared" required> <?php echo esc_html( 'switch-candidate' === (string) ( $prepared['decision'] ?? '' ) ? $labels['confirm_switch'] : $labels['confirm_baseline'] ); ?></label></p>
                            <?php if ( 'switch-candidate' === (string) ( $prepared['decision'] ?? '' ) ) : ?><p><label><input type="radio" name="staff_decision" value="retain-baseline"> <?php echo esc_html( $labels['reject_switch'] ); ?></label></p><?php endif; ?>
                            <p><label><input type="radio" name="staff_decision" value="defer"> <?php echo esc_html( $labels['defer'] ); ?></label></p>
                        </fieldset>
                        <p><label><strong><?php echo esc_html( $labels['note'] ); ?></strong><br><textarea name="review_note" rows="2" maxlength="1000" style="width:100%;max-width:760px;"></textarea></label></p>
                        <?php submit_button( $labels['record'], 'primary', 'submit', false ); ?>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <p style="margin:14px 0 0;font-size:12px;color:#646970;"><?php echo esc_html( $labels['guardrail'] ); ?> · <code><?php echo esc_html( $snapshot_uuid ); ?></code></p>
        </section>
        <?php
    }

    private static function latest_review( array $snapshots ): array {
        for ( $i = count( $snapshots ) - 1; $i >= 0; $i-- ) {
            $playbook = is_array( $snapshots[ $i ]['playbook'] ?? null ) ? $snapshots[ $i ]['playbook'] : array();
            $review = is_array( $playbook['live_twin_switch_review'] ?? null ) ? $playbook['live_twin_switch_review'] : array();
            if ( 'live-twin-switch-confirmation' === (string) ( $review['review_id'] ?? '' ) ) {
                return $review;
            }
        }
        return array();
    }

    private static function context_from_snapshot( array $snapshot ): array {
        return array(
            'minute' => (int) ( $snapshot['minute'] ?? 1 ),
            'our_score' => (int) ( $snapshot['our_score'] ?? 0 ),
            'opponent_score' => (int) ( $snapshot['opponent_score'] ?? 0 ),
            'our_players' => (int) ( $snapshot['our_players'] ?? 11 ),
            'opponent_players' => (int) ( $snapshot['opponent_players'] ?? 11 ),
        );
    }

    private static function context_label( array $context ): string {
        return sprintf(
            "%d' · %d-%d · %dv%d",
            (int) ( $context['minute'] ?? 0 ),
            (int) ( $context['our_score'] ?? 0 ),
            (int) ( $context['opponent_score'] ?? 0 ),
            (int) ( $context['our_players'] ?? 11 ),
            (int) ( $context['opponent_players'] ?? 11 )
        );
    }

    private static function box( string $label, string $value, string $meta ): void {
        ?><div style="background:#f6f7f7;padding:11px;"><div style="font-size:11px;text-transform:uppercase;color:#646970;"><?php echo esc_html( $label ); ?></div><strong><?php echo esc_html( $value ); ?></strong><?php if ( '' !== $meta ) : ?><div style="font-size:12px;color:#646970;margin-top:3px;"><?php echo esc_html( $meta ); ?></div><?php endif; ?></div><?php
    }

    private static function redirect( string $session_uuid ): void {
        wp_safe_redirect( admin_url( 'admin.php?page=tactitwin-live-sessions&session_uuid=' . rawurlencode( $session_uuid ) ) );
        exit;
    }

    private static function labels( string $language ): array {
        if ( 'es' === $language ) {
            return array(
                'eyebrow' => 'Confirmación humana · Twin congelado',
                'title' => 'Revisión de respuesta en vivo',
                'intro' => 'Contrasta el estado real con la preparación aprobada y registra la decisión del staff sin recalcular el Twin.',
                'saved' => 'La revisión se ha guardado como un nuevo snapshot append-only de la Live Session.',
                'live_state' => 'Estado real',
                'no_branch' => 'Sin rama preparada.',
                'branch' => 'Rama aplicable',
                'prepared_decision' => 'Preparación',
                'target' => 'Respuesta objetivo',
                'baseline' => 'Respuesta base',
                'margin' => 'Margen',
                'coverage_caution' => 'Cobertura limitada',
                'coverage_ok' => 'Sin cautela de cobertura',
                'validate' => 'Validar en el campo',
                'prepared_instruction' => 'Primera instrucción preparada',
                'previous' => 'Última decisión registrada',
                'staff_decision' => 'Decisión del staff',
                'confirm_switch' => 'Confirmar la respuesta preparada para esta rama',
                'confirm_baseline' => 'Confirmar que se mantiene la respuesta base',
                'reject_switch' => 'Rechazar el cambio preparado y mantener la respuesta base',
                'defer' => 'Aplazar la decisión',
                'note' => 'Nota del staff',
                'record' => 'Registrar revisión',
                'guardrail' => 'Registrar una revisión no modifica active_plan, no activa Plan A/B/C y no cambia el Twin congelado.',
            );
        }
        return array(
            'eyebrow' => 'Human confirmation · frozen Twin',
            'title' => 'Live response review',
            'intro' => 'Compare the real state with approved preparation and record the staff decision without recalculating the Twin.',
            'saved' => 'The review was stored as a new append-only Live Session snapshot.',
            'live_state' => 'Live state',
            'no_branch' => 'No prepared branch.',
            'branch' => 'Applicable branch',
            'prepared_decision' => 'Preparation',
            'target' => 'Target response',
            'baseline' => 'Baseline response',
            'margin' => 'Margin',
            'coverage_caution' => 'Limited coverage',
            'coverage_ok' => 'No coverage caution',
            'validate' => 'Validate on the field',
            'prepared_instruction' => 'First prepared instruction',
            'previous' => 'Latest recorded decision',
            'staff_decision' => 'Staff decision',
            'confirm_switch' => 'Confirm the prepared response for this branch',
            'confirm_baseline' => 'Confirm the baseline response remains appropriate',
            'reject_switch' => 'Reject the prepared switch and retain the baseline response',
            'defer' => 'Defer the decision',
            'note' => 'Staff note',
            'record' => 'Record review',
            'guardrail' => 'Recording a review does not modify active_plan, activate Plan A/B/C or change the frozen Twin.',
        );
    }
}
