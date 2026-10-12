<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Adds the current Tactical Twin Lab result to the generated Staff Brief before
 * it is shown/approved, then renders a compact summary inside the Staff Brief.
 */
final class TactiTwin_Frozen_Twin_Handoff_Admin_Bridge {
    public static function register(): void {
        add_action( 'admin_init', array( self::class, 'register_transient_filter' ), 20 );
        add_action( 'admin_footer', array( self::class, 'render_staff_brief_attachment' ), 12 );
    }

    public static function register_transient_filter(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $user_id = get_current_user_id();
        if ( $user_id <= 0 ) {
            return;
        }
        add_filter(
            'pre_set_transient_tactitwin_staff_brief_' . $user_id,
            array( self::class, 'attach_to_result' ),
            20,
            2
        );
    }

    public static function attach_to_result( $value, $expiration = 0 ) {
        if ( ! is_array( $value ) || ! empty( $value['approved_snapshot_uuid'] ) ) {
            return $value;
        }
        $brief = is_array( $value['brief'] ?? null ) ? $value['brief'] : array();
        if ( 'match-day-staff-brief' !== (string) ( $brief['staff_brief_id'] ?? '' ) ) {
            return $value;
        }
        if ( TactiTwin_Frozen_Twin_Handoff::verify_attachment( $brief ) ) {
            return $value;
        }

        $form = is_array( $value['form'] ?? null ) ? $value['form'] : array();
        $team_id = (int) ( $form['team_id'] ?? $brief['team']['source_id'] ?? 0 );
        $opponent_id = (int) ( $form['opponent_id'] ?? $brief['opponent']['source_id'] ?? 0 );
        $sample = max( 1, min( 10, (int) ( $form['sample'] ?? 5 ) ) );
        $language = TactiTwin_I18n::normalize_language( (string) ( $value['language'] ?? $brief['language'] ?? 'en' ) );

        if ( $team_id <= 0 || $opponent_id <= 0 || $team_id === $opponent_id ) {
            throw new InvalidArgumentException( 'Frozen Twin Staff Brief attachment requires a valid matchup.' );
        }

        $twin_lab = ( new TactiTwin_Tactical_Twin_Lab_Service() )->evaluate(
            $team_id,
            $opponent_id,
            $sample,
            $language
        );
        $value['brief'] = TactiTwin_Frozen_Twin_Handoff::attach( $brief, $twin_lab );
        $value['tactical_twin_content_sha256'] = (string) ( $value['brief']['tactical_twin_preparation']['content_sha256'] ?? '' );
        return $value;
    }

    public static function render_staff_brief_attachment(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $page = sanitize_key( (string) ( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'tactitwin-staff-brief' !== $page ) {
            return;
        }

        $result = get_transient( 'tactitwin_staff_brief_' . get_current_user_id() );
        $brief = is_array( $result['brief'] ?? null ) ? $result['brief'] : array();
        if ( ! TactiTwin_Frozen_Twin_Handoff::verify_attachment( $brief ) ) {
            return;
        }

        $language = TactiTwin_I18n::normalize_language( (string) ( $brief['language'] ?? 'en' ) );
        $labels = self::labels( $language );
        $lab = TactiTwin_Frozen_Twin_Handoff::twin_lab( $brief );
        $summary = is_array( $lab['preparation_summary'] ?? null ) ? $lab['preparation_summary'] : array();
        $switches = is_array( $lab['response_switch_playbook']['items'] ?? null ) ? $lab['response_switch_playbook']['items'] : array();
        $digest = (string) ( $brief['tactical_twin_preparation']['content_sha256'] ?? '' );
        ?>
        <template id="tactitwin-frozen-twin-template">
            <section class="tt-frozen-twin" style="margin-top:24px;border:1px solid #8c8f94;padding:16px;break-inside:avoid;">
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
                    <div>
                        <div style="font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#646970;"><?php echo esc_html( $labels['eyebrow'] ); ?></div>
                        <h3 style="margin:4px 0 6px;"><?php echo esc_html( $labels['title'] ); ?></h3>
                        <p style="margin:0;"><?php echo esc_html( $labels['intro'] ); ?></p>
                    </div>
                    <code title="SHA-256"><?php echo esc_html( substr( $digest, 0, 16 ) . '…' ); ?></code>
                </div>
                <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:14px 0;">
                    <?php self::summary_box( $labels['baseline'], (string) ( $summary['top_response_name'] ?? '-' ) ); ?>
                    <?php self::summary_box( $labels['robust'], (string) ( $summary['most_robust_response_name'] ?? '-' ) ); ?>
                    <?php self::summary_box( $labels['switches'], (string) ( $summary['switch_candidate_count'] ?? 0 ) ); ?>
                </div>
                <table class="widefat striped">
                    <thead><tr><th><?php echo esc_html( $labels['state'] ); ?></th><th><?php echo esc_html( $labels['decision'] ); ?></th><th><?php echo esc_html( $labels['target'] ); ?></th><th><?php echo esc_html( $labels['caution'] ); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ( $switches as $item ) : ?>
                            <tr>
                                <td><?php echo esc_html( (string) ( $item['branch_name'] ?? '' ) ); ?></td>
                                <td><?php echo esc_html( (string) ( $item['decision_label'] ?? '' ) ); ?></td>
                                <td><?php echo esc_html( (string) ( $item['target_response']['response_name'] ?? '-' ) ); ?></td>
                                <td><?php echo esc_html( ! empty( $item['coverage_caution'] ) ? $labels['yes'] : $labels['no'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin:10px 0 0;font-size:12px;color:#646970;"><?php echo esc_html( $labels['guardrail'] ); ?></p>
            </section>
        </template>
        <script>
        (function () {
            var sheet = document.querySelector('.tt-staff-sheet');
            var template = document.getElementById('tactitwin-frozen-twin-template');
            if (!sheet || !template || sheet.querySelector('.tt-frozen-twin')) return;
            var technical = sheet.querySelector('details');
            if (technical) {
                technical.insertAdjacentHTML('beforebegin', template.innerHTML);
            } else {
                sheet.insertAdjacentHTML('beforeend', template.innerHTML);
            }
        }());
        </script>
        <?php
    }

    private static function summary_box( string $label, string $value ): void {
        ?>
        <div style="background:#f6f7f7;padding:10px;">
            <div style="font-size:11px;text-transform:uppercase;color:#646970;"><?php echo esc_html( $label ); ?></div>
            <strong><?php echo esc_html( $value ); ?></strong>
        </div>
        <?php
    }

    private static function labels( string $language ): array {
        if ( 'es' === $language ) {
            return array(
                'eyebrow' => 'Preparación congelable',
                'title' => 'Tactical Twin preparado',
                'intro' => 'Este Twin es el que quedará dentro del snapshot si apruebas el Staff Brief. El handoff no lo recalcula.',
                'baseline' => 'Respuesta base',
                'robust' => 'Más robusta',
                'switches' => 'Candidatos de cambio',
                'state' => 'Estado preparado',
                'decision' => 'Revisión',
                'target' => 'Respuesta objetivo',
                'caution' => 'Cautela',
                'yes' => 'Sí',
                'no' => 'No',
                'guardrail' => 'Los candidatos siguen requiriendo confirmación humana. Aprobar congela la preparación; no activa ningún cambio táctico.',
            );
        }
        return array(
            'eyebrow' => 'Freezable preparation',
            'title' => 'Prepared Tactical Twin',
            'intro' => 'This is the Twin that will be included in the snapshot if the Staff Brief is approved. Handoff does not recalculate it.',
            'baseline' => 'Baseline response',
            'robust' => 'Most robust',
            'switches' => 'Switch candidates',
            'state' => 'Prepared state',
            'decision' => 'Review',
            'target' => 'Target response',
            'caution' => 'Caution',
            'yes' => 'Yes',
            'no' => 'No',
            'guardrail' => 'Candidates still require human confirmation. Approval freezes preparation; it activates no tactical switch.',
        );
    }
}
