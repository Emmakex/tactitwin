<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Fixture_Lifecycle_Admin {
    public static function register(): void {
        add_action( 'admin_footer', array( self::class, 'render' ), 30 );
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $page = sanitize_key( (string) ( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'tactitwin' !== $page ) {
            return;
        }

        $language = TactiTwin_I18n::normalize_language( TactiTwin_I18n::current_language() );
        $labels = self::labels( $language );
        $context = TactiTwin_Planning_Context::current();
        $error = '';
        try {
            $lifecycle = ( new TactiTwin_Fixture_Lifecycle_Service() )->inspect( $context, get_current_user_id() );
        } catch ( Throwable $e ) {
            $lifecycle = TactiTwin_Fixture_Lifecycle_Service::derive( array( 'context_selected' => ! empty( $context ) ) );
            $error = $e->getMessage();
        }

        $session_uuid = (string) ( $lifecycle['stages']['live_session']['session_uuid'] ?? '' );
        $next = is_array( $lifecycle['next_action'] ?? null ) ? $lifecycle['next_action'] : array();
        $next_page = sanitize_key( (string) ( $next['page'] ?? 'tactitwin' ) );
        $next_url = admin_url( 'admin.php?page=' . $next_page );
        if ( '' !== $session_uuid && in_array( $next_page, array( 'tactitwin-live-sessions', 'tactitwin-session-review', 'tactitwin-evidence-review' ), true ) ) {
            $next_url = add_query_arg( 'session_uuid', rawurlencode( $session_uuid ), $next_url );
        }
        ?>
        <template id="tactitwin-fixture-lifecycle-template">
            <section class="tt-fixture-lifecycle" style="max-width:1180px;margin:22px 0 28px;">
                <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;margin-bottom:12px;">
                    <div>
                        <h2 style="margin:0 0 6px;"><?php echo esc_html( $labels['title'] ); ?></h2>
                        <p style="margin:0;max-width:860px;color:#50575e;"><?php echo esc_html( $labels['intro'] ); ?></p>
                    </div>
                    <div style="background:#fff;border:1px solid #dcdcde;padding:12px 14px;min-width:260px;">
                        <div style="font-size:11px;text-transform:uppercase;color:#646970;font-weight:700;"><?php echo esc_html( $labels['phase'] ); ?></div>
                        <strong><?php echo esc_html( self::phase_label( (string) ( $lifecycle['phase'] ?? 'pre_match' ), $language ) ); ?></strong>
                    </div>
                </div>

                <?php if ( '' !== $error ) : ?>
                    <div class="notice notice-error inline"><p><?php echo esc_html( $error ); ?></p></div>
                <?php endif; ?>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;">
                    <?php foreach ( self::stage_order() as $stage_id ) : ?>
                        <?php $stage = is_array( $lifecycle['stages'][ $stage_id ] ?? null ) ? $lifecycle['stages'][ $stage_id ] : array(); ?>
                        <article style="background:#fff;border:1px solid #dcdcde;padding:15px;min-height:132px;">
                            <div style="font-size:12px;text-transform:uppercase;color:#646970;font-weight:700;"><?php echo esc_html( self::stage_label( $stage_id, $language ) ); ?></div>
                            <div style="font-size:17px;font-weight:700;margin:7px 0;"><?php echo esc_html( self::state_label( (string) ( $stage['state'] ?? '' ), $language ) ); ?></div>
                            <p style="margin:0 0 8px;color:#50575e;"><?php echo esc_html( self::stage_detail( $stage_id, $stage, $language ) ); ?></p>
                            <div style="font-size:11px;color:#646970;"><?php echo esc_html( $labels['source'] . ': ' . self::provenance_label( (string) ( $stage['provenance'] ?? 'none' ), $language ) ); ?></div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div style="background:#f0f6fc;border:1px solid #72aee6;padding:16px;margin-top:14px;display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;">
                    <div>
                        <strong><?php echo esc_html( $labels['next_step'] ); ?>:</strong>
                        <?php echo esc_html( self::next_action_label( (string) ( $next['id'] ?? 'select_match' ), $language ) ); ?>
                        <div style="font-size:12px;color:#50575e;margin-top:4px;"><?php echo esc_html( $labels['no_score'] ); ?></div>
                    </div>
                    <a class="button button-primary" href="<?php echo esc_url( $next_url ); ?>"><?php echo esc_html( $labels['continue'] ); ?></a>
                </div>
            </section>
        </template>
        <script>
        (function () {
            var template = document.getElementById('tactitwin-fixture-lifecycle-template');
            var action = document.querySelector('form input[name="action"][value="tactitwin_set_match_workspace_context"]');
            var form = action ? action.closest('form') : null;
            if (!template || !form) return;
            var wrap = form.closest('.wrap') || document.querySelector('.wrap');
            var summary = wrap ? wrap.querySelector('section') : null;
            if (summary) {
                summary.insertAdjacentHTML('afterend', template.innerHTML);
            } else {
                form.insertAdjacentHTML('afterend', template.innerHTML);
            }
        }());
        </script>
        <?php
    }

    private static function stage_order(): array {
        return array( 'target', 'planning_data', 'match_plan', 'staff_brief', 'live_session', 'evidence', 'post_match_review' );
    }

    private static function stage_label( string $id, string $language ): string {
        $labels = 'es' === $language ? array(
            'target'=>'Partido objetivo','planning_data'=>'Datos de planificación','match_plan'=>'Match Plan','staff_brief'=>'Staff Brief','live_session'=>'Live Session','evidence'=>'Evidencia del partido','post_match_review'=>'Revisión post-partido',
        ) : array(
            'target'=>'Match target','planning_data'=>'Planning data','match_plan'=>'Match Plan','staff_brief'=>'Staff Brief','live_session'=>'Live Session','evidence'=>'Match evidence','post_match_review'=>'Post-match review',
        );
        return $labels[ $id ] ?? $id;
    }

    private static function state_label( string $state, string $language ): string {
        $en = array(
            'selected'=>'Selected','not_selected'=>'Not selected','ready'=>'Ready','partial'=>'Partial data','missing'=>'Data missing','not_available'=>'Not available',
            'sealed_in_approval'=>'Sealed in approved brief','temporary_matchup_only'=>'Temporary matchup result','not_current'=>'No current result',
            'approved'=>'Approved','draft_temporary'=>'Draft generated','not_generated'=>'Not generated',
            'not_started'=>'Not started','active'=>'Active','closed'=>'Closed',
            'waiting_for_session'=>'Waiting for session','unlinked'=>'Session not linked','fixture_mismatch'=>'Different fixture linked','available'=>'Evidence available','linked_pending_events'=>'Linked; events pending',
            'available_on_demand'=>'Available on demand','waiting_for_closed_session'=>'Waiting for session close','waiting_for_evidence'=>'Waiting for evidence',
        );
        $es = array(
            'selected'=>'Seleccionado','not_selected'=>'Sin seleccionar','ready'=>'Listos','partial'=>'Datos parciales','missing'=>'Faltan datos','not_available'=>'No disponible',
            'sealed_in_approval'=>'Sellado en brief aprobado','temporary_matchup_only'=>'Resultado temporal del matchup','not_current'=>'Sin resultado actual',
            'approved'=>'Aprobado','draft_temporary'=>'Borrador generado','not_generated'=>'Sin generar',
            'not_started'=>'Sin iniciar','active'=>'Activa','closed'=>'Cerrada',
            'waiting_for_session'=>'Esperando sesión','unlinked'=>'Sesión sin enlazar','fixture_mismatch'=>'Enlazado a otro fixture','available'=>'Evidencia disponible','linked_pending_events'=>'Enlazado; faltan eventos',
            'available_on_demand'=>'Disponible bajo demanda','waiting_for_closed_session'=>'Esperando cierre de sesión','waiting_for_evidence'=>'Esperando evidencia',
        );
        $map = 'es' === $language ? $es : $en;
        return $map[ $state ] ?? $state;
    }

    private static function provenance_label( string $source, string $language ): string {
        $en = array(
            'user_meta'=>'Persistent per-user context','database'=>'Persistent database record','approved_snapshot'=>'Immutable approved snapshot','transient'=>'Temporary transient','transient_matchup'=>'Temporary, matchup-scoped only','database_derived'=>'Derived from persistent records','computed_on_demand'=>'Calculated when opened','none'=>'No artifact',
        );
        $es = array(
            'user_meta'=>'Contexto persistente por usuario','database'=>'Registro persistente en BD','approved_snapshot'=>'Snapshot aprobado e inmutable','transient'=>'Transient temporal','transient_matchup'=>'Temporal, solo por matchup','database_derived'=>'Derivado de registros persistentes','computed_on_demand'=>'Se calcula al abrir','none'=>'Sin artefacto',
        );
        $map = 'es' === $language ? $es : $en;
        return $map[ $source ] ?? $source;
    }

    private static function stage_detail( string $id, array $stage, string $language ): string {
        if ( 'planning_data' === $id ) {
            $team = (int) ( $stage['team_history_matches'] ?? 0 );
            $opponent = (int) ( $stage['opponent_history_matches'] ?? 0 );
            return 'es' === $language
                ? sprintf( 'Histórico con eventos: nuestro equipo %d partido(s), rival %d.', $team, $opponent )
                : sprintf( 'Event history: our team %d match(es), opponent %d.', $team, $opponent );
        }
        if ( 'match_plan' === $id && 'temporary_matchup_only' === (string) ( $stage['state'] ?? '' ) ) {
            return 'es' === $language
                ? 'Existe un Match Plan temporal para este emparejamiento, pero no queda ligado al fixture hasta la aprobación del Staff Brief.'
                : 'A temporary Match Plan exists for this matchup, but it is not fixture-bound until Staff Brief approval.';
        }
        if ( 'staff_brief' === $id && ! empty( $stage['snapshot_uuid'] ) ) {
            return 'es' === $language
                ? 'Snapshot: ' . substr( (string) $stage['snapshot_uuid'], 0, 12 ) . '…'
                : 'Snapshot: ' . substr( (string) $stage['snapshot_uuid'], 0, 12 ) . '…';
        }
        if ( 'live_session' === $id && ! empty( $stage['session_uuid'] ) ) {
            return 'es' === $language
                ? 'Sesión: ' . substr( (string) $stage['session_uuid'], 0, 12 ) . '…'
                : 'Session: ' . substr( (string) $stage['session_uuid'], 0, 12 ) . '…';
        }
        if ( 'evidence' === $id && 'available' === (string) ( $stage['state'] ?? '' ) ) {
            return 'es' === $language
                ? sprintf( '%d eventos normalizados disponibles para contraste.', (int) ( $stage['event_count'] ?? 0 ) )
                : sprintf( '%d normalized events available for comparison.', (int) ( $stage['event_count'] ?? 0 ) );
        }
        if ( 'post_match_review' === $id ) {
            return 'es' === $language
                ? 'La revisión se reconstruye desde la sesión y la evidencia; v1.24 no finge una marca de “completado” que no se persiste.'
                : 'Review is reconstructed from session and evidence; v1.24 does not invent a persisted “completed” flag.';
        }
        return 'es' === $language
            ? 'Estado derivado del artefacto indicado, sin porcentaje de preparación.'
            : 'State derived from the named artifact, with no preparation percentage.';
    }

    private static function next_action_label( string $id, string $language ): string {
        $en = array(
            'select_match'=>'Select the match to prepare','prepare_data'=>'Import or complete planning data','generate_match_plan'=>'Generate the Match Plan','generate_staff_brief'=>'Generate the fixture-bound Staff Brief','approve_staff_brief'=>'Review and approve the Staff Brief','handoff_live_session'=>'Hand off the approved brief to a Live Session','continue_live_session'=>'Continue the active Live Session','prepare_evidence'=>'Link/import match evidence','review_match'=>'Open the post-match review',
        );
        $es = array(
            'select_match'=>'Seleccionar el partido a preparar','prepare_data'=>'Importar o completar datos de planificación','generate_match_plan'=>'Generar el Match Plan','generate_staff_brief'=>'Generar el Staff Brief ligado al fixture','approve_staff_brief'=>'Revisar y aprobar el Staff Brief','handoff_live_session'=>'Pasar el brief aprobado a una Live Session','continue_live_session'=>'Continuar la Live Session activa','prepare_evidence'=>'Enlazar/importar evidencia del partido','review_match'=>'Abrir la revisión post-partido',
        );
        $map = 'es' === $language ? $es : $en;
        return $map[ $id ] ?? $id;
    }

    private static function phase_label( string $phase, string $language ): string {
        $en = array( 'pre_match'=>'Pre-match','match_day'=>'Match day','post_match'=>'Post-match' );
        $es = array( 'pre_match'=>'Prepartido','match_day'=>'Día de partido','post_match'=>'Post-partido' );
        $map = 'es' === $language ? $es : $en;
        return $map[ $phase ] ?? $phase;
    }

    private static function labels( string $language ): array {
        if ( 'es' === $language ) {
            return array(
                'title'=>'Estado real del partido','intro'=>'TactiTwin deriva cada hito de artefactos existentes. Persistente, temporal y calculado bajo demanda se muestran por separado; no existe un score artificial de preparación.','phase'=>'Fase actual','source'=>'Fuente','next_step'=>'Siguiente paso recomendado','continue'=>'Continuar','no_score'=>'La recomendación de siguiente paso es determinista; no suma ni pondera estos estados.',
            );
        }
        return array(
            'title'=>'Real fixture status','intro'=>'TactiTwin derives each milestone from existing artifacts. Persistent, temporary and on-demand states are shown separately; there is no artificial preparation score.','phase'=>'Current phase','source'=>'Source','next_step'=>'Recommended next step','continue'=>'Continue','no_score'=>'The next-step recommendation is deterministic; these states are not summed or weighted.',
        );
    }
}
