<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Coach-facing presentation for the active fixture and its eligible pre-match
 * evidence. It does not import matches, calculate tactics or persist readiness.
 */
final class TactiTwin_Pre_Match_Readiness_Admin {
    public static function register(): void {
        add_action( 'admin_footer', array( self::class, 'render' ), 31 );
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $page = sanitize_key( (string) ( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! in_array( $page, array( 'tactitwin', 'tactitwin-twin-lab' ), true ) ) {
            return;
        }

        $context = TactiTwin_Planning_Context::current();
        if ( empty( $context ) ) {
            return;
        }

        $language = TactiTwin_I18n::normalize_language( TactiTwin_I18n::current_language() );
        try {
            $readiness = TactiTwin_Pre_Match_Readiness::inspect( $context );
        } catch ( Throwable $e ) {
            return;
        }

        $identity = self::identity( $context );
        if ( 'tactitwin' === $page ) {
            self::render_workspace( $identity, $readiness, $language );
        } else {
            self::render_twin_lab_context( $identity, $readiness, $language );
        }
    }

    private static function identity( array $context ): array {
        $match = is_array( $context['match'] ?? null ) ? $context['match'] : array();
        $fixture = is_array( $context['fixture'] ?? null ) ? $context['fixture'] : array();
        $team_id = (string) ( $context['team_source_id'] ?? '' );
        $home_id = (string) ( $match['home_team_source_id'] ?? '' );
        $away_id = (string) ( $match['away_team_source_id'] ?? '' );
        $home_name = (string) ( $match['home_team_name'] ?? $home_id );
        $away_name = (string) ( $match['away_team_name'] ?? $away_id );
        $our_name = $team_id === $home_id ? $home_name : $away_name;
        $opponent_name = $team_id === $home_id ? $away_name : $home_name;

        return array(
            'our_name' => $our_name,
            'opponent_name' => $opponent_name,
            'date' => (string) ( $match['match_date'] ?? $fixture['match_date'] ?? '' ),
            'competition' => (string) ( $match['competition_name'] ?? '' ),
            'season' => (string) ( $match['season_name'] ?? '' ),
            'perspective' => strtoupper( (string) ( $context['perspective'] ?? '' ) ),
            'source' => (string) ( $fixture['source'] ?? $match['source'] ?? '' ),
            'source_match_id' => (string) ( $fixture['source_match_id'] ?? $match['source_match_id'] ?? '' ),
            'fixture_key' => (string) ( $context['fixture_key'] ?? '' ),
        );
    }

    private static function render_workspace( array $identity, array $readiness, string $language ): void {
        $labels = self::labels( $language );
        $team = is_array( $readiness['team'] ?? null ) ? $readiness['team'] : array();
        $opponent = is_array( $readiness['opponent'] ?? null ) ? $readiness['opponent'] : array();
        ?>
        <template id="tt-v138-readiness-template">
            <section class="tt-v138-readiness" style="max-width:1180px;background:#fff;border:1px solid #c3c4c7;padding:18px 20px;margin:18px 0;">
                <div style="display:flex;justify-content:space-between;gap:18px;align-items:flex-start;flex-wrap:wrap;">
                    <div>
                        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#646970;font-weight:700;">v1.38 · <?php echo esc_html( $labels['eyebrow'] ); ?></div>
                        <h2 style="margin:5px 0 6px;"><?php echo esc_html( $labels['title'] ); ?></h2>
                        <p style="margin:0;max-width:820px;"><?php echo esc_html( self::readiness_sentence( $identity, $readiness, $language ) ); ?></p>
                    </div>
                    <span style="background:#f0f0f1;padding:6px 9px;font-weight:700;border-radius:3px;"><?php echo esc_html( strtoupper( (string) ( $readiness['status'] ?? 'low' ) ) ); ?></span>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px;margin-top:14px;">
                    <?php self::sample_box( $identity['our_name'], $team, $labels ); ?>
                    <?php self::sample_box( $identity['opponent_name'], $opponent, $labels ); ?>
                    <div style="border:1px solid #dcdcde;padding:13px;">
                        <div style="font-size:11px;text-transform:uppercase;color:#646970;"><?php echo esc_html( $labels['effective'] ); ?></div>
                        <strong style="display:block;font-size:22px;margin:4px 0;"><?php echo esc_html( (string) ( $readiness['effective_matches'] ?? 0 ) ); ?></strong>
                        <span><?php echo esc_html( $labels['limited_by'] . ': ' . self::limiting_label( (string) ( $readiness['limiting_side'] ?? 'balanced' ), $language ) ); ?></span>
                    </div>
                </div>
                <details style="margin-top:12px;">
                    <summary><strong><?php echo esc_html( $labels['scope'] ); ?></strong></summary>
                    <p><?php echo esc_html( self::scope_sentence( $readiness, $language ) ); ?></p>
                    <p style="color:#646970;"><?php echo esc_html( $labels['guardrail'] ); ?></p>
                </details>
            </section>
        </template>
        <script>
        (function () {
            var template = document.getElementById('tt-v138-readiness-template');
            if (!template) return;
            var lifecycle = document.querySelector('.tt-fixture-lifecycle');
            var contextForm = document.querySelector('form input[name="action"][value="tactitwin_set_match_workspace_context"]');
            var form = contextForm ? contextForm.closest('form') : null;
            if (!document.querySelector('.tt-v138-readiness')) {
                if (lifecycle) lifecycle.insertAdjacentHTML('beforebegin', template.innerHTML);
                else if (form) form.insertAdjacentHTML('afterend', template.innerHTML);
            }

            // Correct the legacy lifecycle planning-data text with the eligible
            // pre-match sample, while keeping lifecycle artifact semantics intact.
            if (lifecycle) {
                var articles = lifecycle.querySelectorAll('article');
                if (articles.length > 1) {
                    var planning = articles[1];
                    var state = planning.children.length > 1 ? planning.children[1] : null;
                    var detail = planning.querySelector('p');
                    if (state) state.textContent = <?php echo wp_json_encode( self::planning_state_label( $readiness, $language ) ); ?>;
                    if (detail) detail.textContent = <?php echo wp_json_encode( self::readiness_sentence( $identity, $readiness, $language ) ); ?>;
                }
            }

            // Twin Lab is now a first-class Workspace step. Add it only when an
            // older Workspace renderer did not include the card yet.
            var existingTwin = document.querySelector('a[href*="page=tactitwin-twin-lab"]');
            var matchPlan = document.querySelector('a[href*="page=tactitwin-match-plan"]');
            if (!existingTwin && matchPlan) {
                var card = matchPlan.cloneNode(true);
                card.href = <?php echo wp_json_encode( admin_url( 'admin.php?page=tactitwin-twin-lab' ) ); ?>;
                var strong = card.querySelector('strong');
                var p = card.querySelector('p');
                if (strong) strong.textContent = <?php echo wp_json_encode( $labels['twin_lab'] ); ?>;
                if (p) p.textContent = <?php echo wp_json_encode( $labels['twin_lab_desc'] ); ?>;
                matchPlan.insertAdjacentElement('afterend', card);
            }
        }());
        </script>
        <?php
    }

    private static function render_twin_lab_context( array $identity, array $readiness, string $language ): void {
        $labels = self::labels( $language );
        ?>
        <template id="tt-v138-twin-context-template">
            <p>
                <strong><?php echo esc_html( $labels['active_match'] ); ?>:</strong>
                <?php echo esc_html( $identity['our_name'] . ' vs ' . $identity['opponent_name'] ); ?>
                <?php if ( '' !== $identity['date'] ) : ?> · <?php echo esc_html( $identity['date'] ); ?><?php endif; ?>
                · <?php echo esc_html( $labels['effective'] . ': ' . (string) ( $readiness['effective_matches'] ?? 0 ) ); ?>
                · <a href="<?php echo esc_url( admin_url( 'admin.php?page=tactitwin' ) ); ?>"><?php echo esc_html( $labels['change_match'] ); ?></a>
            </p>
        </template>
        <script>
        (function () {
            var template = document.getElementById('tt-v138-twin-context-template');
            if (!template) return;
            var form = document.querySelector('form input[name="action"][value="tactitwin_generate_twin_lab"]');
            form = form ? form.closest('form') : null;
            var notice = form ? form.querySelector('.notice.notice-info.inline') : null;
            if (notice) notice.innerHTML = template.innerHTML;
        }());
        </script>
        <?php
    }

    private static function sample_box( string $name, array $sample, array $labels ): void {
        $oldest = (string) ( $sample['oldest_match'] ?? '' );
        $newest = (string) ( $sample['newest_match'] ?? '' );
        $range = '' === $oldest && '' === $newest
            ? $labels['no_range']
            : ( $oldest === $newest ? $oldest : $oldest . ' → ' . $newest );
        ?>
        <div style="border:1px solid #dcdcde;padding:13px;">
            <div style="font-size:11px;text-transform:uppercase;color:#646970;"><?php echo esc_html( $name ); ?></div>
            <strong style="display:block;font-size:20px;margin:4px 0;"><?php echo esc_html( (string) ( $sample['matches'] ?? 0 ) ); ?></strong>
            <span style="color:#646970;"><?php echo esc_html( $range ); ?></span>
        </div>
        <?php
    }

    private static function readiness_sentence( array $identity, array $readiness, string $language ): string {
        $team = (int) ( $readiness['team_matches'] ?? 0 );
        $opponent = (int) ( $readiness['opponent_matches'] ?? 0 );
        $effective = (int) ( $readiness['effective_matches'] ?? 0 );
        if ( 'es' === $language ) {
            return sprintf(
                'Muestra prepartido elegible: %s %d partido(s), %s %d; efectiva %d. Lado limitante: %s.',
                $identity['our_name'],
                $team,
                $identity['opponent_name'],
                $opponent,
                $effective,
                self::limiting_label( (string) ( $readiness['limiting_side'] ?? 'balanced' ), $language )
            );
        }
        return sprintf(
            'Eligible pre-match sample: %s %d match(es), %s %d; effective %d. Limiting side: %s.',
            $identity['our_name'],
            $team,
            $identity['opponent_name'],
            $opponent,
            $effective,
            self::limiting_label( (string) ( $readiness['limiting_side'] ?? 'balanced' ), $language )
        );
    }

    private static function scope_sentence( array $readiness, string $language ): string {
        $scope = is_array( $readiness['temporal_scope'] ?? null ) ? $readiness['temporal_scope'] : array();
        $date = (string) ( $scope['before_date'] ?? '' );
        $match_id = (string) ( $scope['exclude_match_id'] ?? '' );
        return 'es' === $language
            ? sprintf( 'Solo se cuentan partidos con eventos anteriores a %s. El partido objetivo %s queda excluido explícitamente.', $date, $match_id )
            : sprintf( 'Only event-data matches before %s are counted. Target fixture %s is explicitly excluded.', $date, $match_id );
    }

    private static function planning_state_label( array $readiness, string $language ): string {
        $team = (int) ( $readiness['team_matches'] ?? 0 );
        $opponent = (int) ( $readiness['opponent_matches'] ?? 0 );
        $state = $team > 0 && $opponent > 0 ? 'ready' : ( $team > 0 || $opponent > 0 ? 'partial' : 'missing' );
        $labels = 'es' === $language
            ? array( 'ready' => 'Muestra prepartido disponible', 'partial' => 'Muestra prepartido parcial', 'missing' => 'Sin muestra prepartido' )
            : array( 'ready' => 'Pre-match sample available', 'partial' => 'Partial pre-match sample', 'missing' => 'No pre-match sample' );
        return $labels[ $state ];
    }

    private static function limiting_label( string $side, string $language ): string {
        $labels = 'es' === $language
            ? array( 'team' => 'nuestro equipo', 'opponent' => 'rival', 'balanced' => 'ambos' )
            : array( 'team' => 'our team', 'opponent' => 'opponent', 'balanced' => 'both' );
        return (string) ( $labels[ $side ] ?? $side );
    }

    private static function labels( string $language ): array {
        if ( 'es' === $language ) {
            return array(
                'eyebrow' => 'Preparación prepartido',
                'title' => 'Evidencia elegible antes del fixture',
                'effective' => 'Cobertura efectiva',
                'limited_by' => 'Limitada por',
                'scope' => 'Alcance temporal y anti-fuga',
                'guardrail' => 'Esta capa solo describe disponibilidad de evidencia. No importa datos, no genera un porcentaje de preparación y no modifica ningún score táctico.',
                'no_range' => 'Sin rango temporal',
                'twin_lab' => 'Laboratorio Tactical Twin',
                'twin_lab_desc' => 'Compara respuestas, robustez y cambios preparados sobre el mismo partido activo.',
                'active_match' => 'Partido activo',
                'change_match' => 'Cambiar en Espacio del partido',
            );
        }
        return array(
            'eyebrow' => 'Pre-match preparation',
            'title' => 'Evidence eligible before the fixture',
            'effective' => 'Effective coverage',
            'limited_by' => 'Limited by',
            'scope' => 'Temporal scope and leakage guard',
            'guardrail' => 'This layer only describes evidence availability. It imports no data, creates no preparation percentage and changes no tactical score.',
            'no_range' => 'No date range',
            'twin_lab' => 'Tactical Twin Lab',
            'twin_lab_desc' => 'Compare prepared responses, robustness and switches for the same active match.',
            'active_match' => 'Active match',
            'change_match' => 'Change in Match Workspace',
        );
    }
}
