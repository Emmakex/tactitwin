<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Renders the v1.34 Tactical Twin response decision timeline on the Live
 * Session screen. This is read-only reconstruction from persisted snapshots.
 */
final class TactiTwin_Live_Response_Decision_Timeline_Admin_Bridge {
    public static function register(): void {
        add_action( 'admin_footer', array( self::class, 'render' ), 14 );
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

        try {
            $timeline = ( new TactiTwin_Live_Session_Service() )->timeline( $session_uuid );
            $session = is_array( $timeline['session'] ?? null ) ? $timeline['session'] : array();
            $snapshots = is_array( $timeline['snapshots'] ?? null ) ? $timeline['snapshots'] : array();
            $language = TactiTwin_I18n::normalize_language( (string) ( $session['language'] ?? 'en' ) );
            $response_timeline = TactiTwin_Live_Response_Decision_Timeline::compose( $snapshots, $language );
            if ( empty( $response_timeline['decisions'] ) ) {
                return;
            }
            self::render_panel( $response_timeline, $language );
        } catch ( Throwable $e ) {
            return;
        }
    }

    private static function render_panel( array $timeline, string $language ): void {
        $labels = self::labels( $language );
        $summary = is_array( $timeline['summary'] ?? null ) ? $timeline['summary'] : array();
        $counts = is_array( $summary['status_counts'] ?? null ) ? $summary['status_counts'] : array();
        ?>
        <section class="tt-live-response-decision-timeline" style="max-width:1100px;background:#fff;border:1px solid #8c8f94;padding:18px;margin:24px 0;">
            <div>
                <div style="font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#646970;"><?php echo esc_html( $labels['eyebrow'] ); ?></div>
                <h2 style="margin:4px 0 6px;"><?php echo esc_html( $labels['title'] ); ?></h2>
                <p style="margin:0;"><?php echo esc_html( $labels['intro'] ); ?></p>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;margin:16px 0;">
                <?php self::box( $labels['decisions'], (string) ( $timeline['decision_count'] ?? 0 ) ); ?>
                <?php self::box( $labels['confirmed_switches'], (string) ( $counts['confirmed-prepared-switch'] ?? 0 ) ); ?>
                <?php self::box( $labels['rejected_switches'], (string) ( $counts['rejected-prepared-switch'] ?? 0 ) ); ?>
                <?php self::box( $labels['deferred'], (string) ( $counts['deferred'] ?? 0 ) ); ?>
                <?php self::box( $labels['latest_status'], self::status_label( (string) ( $summary['latest_status'] ?? '' ), $language ) ); ?>
            </div>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html( $labels['minute'] ); ?></th>
                        <th><?php echo esc_html( $labels['state'] ); ?></th>
                        <th><?php echo esc_html( $labels['branch'] ); ?></th>
                        <th><?php echo esc_html( $labels['prepared'] ); ?></th>
                        <th><?php echo esc_html( $labels['staff'] ); ?></th>
                        <th><?php echo esc_html( $labels['selected'] ); ?></th>
                        <th><?php echo esc_html( $labels['plan'] ); ?></th>
                        <th><?php echo esc_html( $labels['note'] ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $timeline['decisions'] as $row ) : ?>
                        <tr>
                            <td><?php echo esc_html( (string) ( $row['minute'] ?? 0 ) . "'" ); ?><br><small>#<?php echo esc_html( (string) ( $row['snapshot_id'] ?? 0 ) ); ?></small></td>
                            <td><?php echo esc_html( self::state_label( $row ) ); ?></td>
                            <td><?php echo esc_html( (string) ( $row['branch']['branch_name'] ?? '-' ) ); ?><br><small><?php echo esc_html( (string) ( $row['branch']['match_type'] ?? '' ) ); ?></small></td>
                            <td>
                                <?php echo esc_html( self::prepared_label( $row, $language ) ); ?>
                                <?php if ( ! empty( $row['prepared_decision']['coverage_caution'] ) ) : ?><br><small><?php echo esc_html( $labels['coverage_caution'] ); ?></small><?php endif; ?>
                            </td>
                            <td><?php echo esc_html( self::status_label( (string) ( $row['staff_decision']['status'] ?? '' ), $language ) ); ?></td>
                            <td><?php echo esc_html( (string) ( $row['staff_decision']['selected_response_name'] ?? '-' ) ); ?></td>
                            <td><?php echo esc_html( 'Plan ' . (string) ( $row['active_plan']['plan'] ?? 'A' ) ); ?><br><small><?php echo esc_html( (string) ( $row['active_plan']['mode'] ?? '' ) ); ?></small></td>
                            <td><?php echo esc_html( (string) ( $row['staff_decision']['note'] ?? '' ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin:14px 0 0;font-size:12px;color:#646970;"><?php echo esc_html( $labels['guardrail'] ); ?></p>
        </section>
        <?php
    }

    private static function box( string $label, string $value ): void {
        ?>
        <div style="background:#f6f7f7;padding:10px;">
            <div style="font-size:11px;text-transform:uppercase;color:#646970;"><?php echo esc_html( $label ); ?></div>
            <strong><?php echo esc_html( $value ); ?></strong>
        </div>
        <?php
    }

    private static function state_label( array $row ): string {
        return sprintf(
            "%d' · %d-%d · %dv%d",
            (int) ( $row['minute'] ?? 0 ),
            (int) ( $row['score']['our_score'] ?? 0 ),
            (int) ( $row['score']['opponent_score'] ?? 0 ),
            (int) ( $row['manpower']['our_players'] ?? 11 ),
            (int) ( $row['manpower']['opponent_players'] ?? 11 )
        );
    }

    private static function prepared_label( array $row, string $language ): string {
        $decision = (string) ( $row['prepared_decision']['decision'] ?? '' );
        $target = (string) ( $row['prepared_decision']['target_response_name'] ?? '' );
        if ( 'switch-candidate' === $decision ) {
            return ( 'es' === $language ? 'Cambio preparado → ' : 'Prepared switch → ' ) . ( '' !== $target ? $target : '-' );
        }
        if ( 'retain-baseline' === $decision ) {
            return 'es' === $language ? 'Mantener respuesta base' : 'Retain baseline response';
        }
        return '-';
    }

    private static function status_label( string $status, string $language ): string {
        $catalog = array(
            'en' => array(
                'confirmed-prepared-switch' => 'Prepared switch confirmed',
                'confirmed-prepared-baseline' => 'Prepared baseline confirmed',
                'rejected-prepared-switch' => 'Prepared switch rejected',
                'confirmed-baseline' => 'Baseline confirmed',
                'deferred' => 'Deferred',
                'no-prepared-branch' => 'No prepared branch',
            ),
            'es' => array(
                'confirmed-prepared-switch' => 'Cambio preparado confirmado',
                'confirmed-prepared-baseline' => 'Respuesta base preparada confirmada',
                'rejected-prepared-switch' => 'Cambio preparado rechazado',
                'confirmed-baseline' => 'Respuesta base confirmada',
                'deferred' => 'Aplazado',
                'no-prepared-branch' => 'Sin rama preparada',
            ),
        );
        return (string) ( $catalog[ $language ][ $status ] ?? $catalog['en'][ $status ] ?? ( '' !== $status ? $status : '-' ) );
    }

    private static function labels( string $language ): array {
        if ( 'es' === $language ) {
            return array(
                'eyebrow' => 'Historial Tactical Twin',
                'title' => 'Timeline de decisiones de respuesta',
                'intro' => 'Reconstrucción de las decisiones de respuesta realmente registradas durante la Live Session. No se infieren decisiones entre snapshots.',
                'decisions' => 'Decisiones registradas',
                'confirmed_switches' => 'Cambios confirmados',
                'rejected_switches' => 'Cambios rechazados',
                'deferred' => 'Aplazados',
                'latest_status' => 'Última decisión',
                'minute' => 'Minuto',
                'state' => 'Estado real',
                'branch' => 'Rama preparada',
                'prepared' => 'Twin preparado',
                'staff' => 'Decisión staff',
                'selected' => 'Respuesta seleccionada',
                'plan' => 'Plan A/B/C activo',
                'note' => 'Nota',
                'coverage_caution' => 'Cobertura limitada',
                'guardrail' => 'Timeline descriptivo y append-only: registra lo decidido por el staff y el Plan A/B/C que seguía activo, sin reinterpretar ni recalcular el partido.',
            );
        }
        return array(
            'eyebrow' => 'Tactical Twin history',
            'title' => 'Response Decision Timeline',
            'intro' => 'Reconstruction of response decisions actually recorded during the Live Session. No decision is inferred between snapshots.',
            'decisions' => 'Recorded decisions',
            'confirmed_switches' => 'Confirmed switches',
            'rejected_switches' => 'Rejected switches',
            'deferred' => 'Deferred',
            'latest_status' => 'Latest decision',
            'minute' => 'Minute',
            'state' => 'Live state',
            'branch' => 'Prepared branch',
            'prepared' => 'Prepared Twin',
            'staff' => 'Staff decision',
            'selected' => 'Selected response',
            'plan' => 'Active Plan A/B/C',
            'note' => 'Note',
            'coverage_caution' => 'Limited coverage',
            'guardrail' => 'Descriptive append-only timeline: it records what staff decided and which Plan A/B/C remained active, without reinterpreting or recalculating the match.',
        );
    }
}
