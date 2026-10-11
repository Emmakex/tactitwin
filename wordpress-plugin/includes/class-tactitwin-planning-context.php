<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Planning_Context {
    private const USER_META_KEY = 'tactitwin_planning_context_v1';

    private const ACTION_NONCES = array(
        'tactitwin_prepare_scenario' => 'tactitwin_prepare_scenario',
        'tactitwin_generate_match_plan' => 'tactitwin_generate_match_plan',
        'tactitwin_run_what_if' => 'tactitwin_run_what_if',
        'tactitwin_run_live_playbook' => 'tactitwin_run_live_playbook',
        'tactitwin_generate_staff_brief' => 'tactitwin_generate_staff_brief',
    );

    private const PAGE_ACTIONS = array(
        'tactitwin-scenario-lab' => 'tactitwin_prepare_scenario',
        'tactitwin-match-plan' => 'tactitwin_generate_match_plan',
        'tactitwin-what-if' => 'tactitwin_run_what_if',
        'tactitwin-live-playbook' => 'tactitwin_run_live_playbook',
        'tactitwin-staff-brief' => 'tactitwin_generate_staff_brief',
    );

    public static function register(): void {
        foreach ( array_keys( self::ACTION_NONCES ) as $action ) {
            add_action( 'admin_post_' . $action, array( self::class, 'apply_request_context' ), 1 );
        }
        add_action( 'admin_footer', array( self::class, 'render_selector' ), 4 );
    }

    public static function decode_selection( string $selection ): array {
        $selection = trim( $selection );
        if ( '' === $selection ) {
            return array();
        }
        if ( 1 !== preg_match( '/^(\d+):([a-zA-Z0-9_.:\-]+)$/', $selection, $matches ) ) {
            throw new InvalidArgumentException( 'Planning context selection is malformed.' );
        }
        $match_row_id = (int) $matches[1];
        $team_source_id = trim( (string) $matches[2] );
        if ( $match_row_id <= 0 || '' === $team_source_id ) {
            throw new InvalidArgumentException( 'Planning context requires a match and team perspective.' );
        }
        return array(
            'target_match_row_id' => $match_row_id,
            'team_source_id' => $team_source_id,
        );
    }

    public static function selection_from_context( array $context ): string {
        $row_id = (int) ( $context['target_match_row_id'] ?? 0 );
        $team_id = trim( (string) ( $context['team_source_id'] ?? '' ) );
        return $row_id > 0 && '' !== $team_id ? $row_id . ':' . $team_id : '';
    }

    public static function build_context( array $resolved ): array {
        $row_id = (int) ( $resolved['target_match_row_id'] ?? 0 );
        $team_id = trim( (string) ( $resolved['team_source_id'] ?? '' ) );
        $opponent_id = trim( (string) ( $resolved['opponent_source_id'] ?? '' ) );
        $fixture = is_array( $resolved['fixture'] ?? null ) ? $resolved['fixture'] : array();
        if ( $row_id <= 0 || '' === $team_id || '' === $opponent_id || empty( $fixture['fixture_key'] ) ) {
            throw new InvalidArgumentException( 'Resolved planning context is incomplete.' );
        }
        return array(
            'context_version' => 1,
            'target_match_row_id' => $row_id,
            'team_source_id' => $team_id,
            'opponent_source_id' => $opponent_id,
            'perspective' => (string) ( $resolved['perspective'] ?? '' ),
            'fixture_key' => (string) $fixture['fixture_key'],
            'fixture' => $fixture,
            'match' => is_array( $resolved['match'] ?? null ) ? $resolved['match'] : array(),
        );
    }

    public static function apply_to_payload( array $payload, array $context, string $action ): array {
        $team_id = trim( (string) ( $context['team_source_id'] ?? '' ) );
        $opponent_id = trim( (string) ( $context['opponent_source_id'] ?? '' ) );
        $row_id = (int) ( $context['target_match_row_id'] ?? 0 );
        if ( '' === $team_id || '' === $opponent_id || $row_id <= 0 ) {
            throw new InvalidArgumentException( 'Planning context cannot be applied because its identity is incomplete.' );
        }
        $payload['team_id'] = $team_id;
        $payload['opponent_id'] = $opponent_id;
        if ( 'tactitwin_generate_staff_brief' === $action ) {
            $payload['target_match_id'] = (string) $row_id;
            $payload['tactitwin_bind_fixture'] = '1';
        }
        return $payload;
    }

    public static function current(): array {
        if ( ! function_exists( 'get_current_user_id' ) || ! function_exists( 'get_user_meta' ) ) {
            return array();
        }
        $user_id = get_current_user_id();
        if ( $user_id <= 0 ) {
            return array();
        }
        $stored = get_user_meta( $user_id, self::USER_META_KEY, true );
        return is_array( $stored ) ? $stored : array();
    }

    public static function apply_request_context(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $hook = function_exists( 'current_action' ) ? (string) current_action() : '';
        $action = str_starts_with( $hook, 'admin_post_' ) ? substr( $hook, 11 ) : '';
        if ( ! isset( self::ACTION_NONCES[ $action ] ) ) {
            return;
        }

        check_admin_referer( self::ACTION_NONCES[ $action ] );

        $has_selector = array_key_exists( 'tactitwin_planning_target', $_POST );
        $selection = $has_selector
            ? sanitize_text_field( wp_unslash( (string) $_POST['tactitwin_planning_target'] ) )
            : self::selection_from_context( self::current() );

        if ( '' === trim( $selection ) ) {
            if ( $has_selector ) {
                delete_user_meta( get_current_user_id(), self::USER_META_KEY );
            }
            return;
        }

        try {
            $decoded = self::decode_selection( $selection );
            $resolved = ( new TactiTwin_Match_Target_Service() )->resolve(
                (int) $decoded['target_match_row_id'],
                (string) $decoded['team_source_id']
            );
            $context = self::build_context( $resolved );
            update_user_meta( get_current_user_id(), self::USER_META_KEY, $context );
            $_POST = self::apply_to_payload( $_POST, $context, $action );
        } catch ( Throwable $e ) {
            $page = array_search( $action, self::PAGE_ACTIONS, true );
            $error_key = self::error_transient_key( $action );
            if ( '' !== $error_key ) {
                set_transient( $error_key . get_current_user_id(), $e->getMessage(), MINUTE_IN_SECONDS );
            }
            if ( is_string( $page ) && '' !== $page ) {
                wp_safe_redirect( admin_url( 'admin.php?page=' . $page ) );
                exit;
            }
            throw $e;
        }
    }

    public static function render_selector(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $page = sanitize_key( (string) ( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! isset( self::PAGE_ACTIONS[ $page ] ) ) {
            return;
        }

        $targets = array();
        try {
            $targets = ( new TactiTwin_Match_Target_Service() )->recent_targets( 150 );
        } catch ( Throwable $e ) {
            $targets = array();
        }

        $language = TactiTwin_I18n::normalize_language( TactiTwin_I18n::current_language() );
        $labels = self::labels( $language );
        $current_selection = self::selection_from_context( self::current() );
        $action = self::PAGE_ACTIONS[ $page ];
        ?>
        <template id="tactitwin-planning-context-template">
            <tr class="tt-planning-context-row">
                <th><label for="tt-planning-context"><?php echo esc_html( $labels['label'] ); ?></label></th>
                <td>
                    <select id="tt-planning-context" name="tactitwin_planning_target" style="min-width:560px;max-width:100%;">
                        <option value=""><?php echo esc_html( $labels['manual'] ); ?></option>
                        <?php foreach ( $targets as $target ) : ?>
                            <?php
                            $row_id = (int) ( $target['id'] ?? 0 );
                            $home_id = trim( (string) ( $target['home_team_source_id'] ?? '' ) );
                            $away_id = trim( (string) ( $target['away_team_source_id'] ?? '' ) );
                            if ( $row_id <= 0 || '' === $home_id || '' === $away_id ) {
                                continue;
                            }
                            $home_name = (string) ( $target['home_team_name'] ?? $home_id );
                            $away_name = (string) ( $target['away_team_name'] ?? $away_id );
                            $date = (string) ( $target['match_date'] ?? '' );
                            $competition = trim( (string) ( $target['competition_name'] ?? '' ) );
                            $season = trim( (string) ( $target['season_name'] ?? '' ) );
                            $prefix = trim( $date . ( '' !== $competition ? ' · ' . $competition : '' ) . ( '' !== $season ? ' · ' . $season : '' ), ' ·' );
                            foreach ( array( $home_id => $home_name, $away_id => $away_name ) as $our_id => $our_name ) :
                                $opponent_id = $our_id === $home_id ? $away_id : $home_id;
                                $selection = $row_id . ':' . $our_id;
                                $label = trim( $prefix . ' · ' . $home_name . ' vs ' . $away_name . ' · ' . $labels['our_team'] . ': ' . $our_name, ' ·' );
                                ?>
                                <option
                                    value="<?php echo esc_attr( $selection ); ?>"
                                    <?php selected( $current_selection, $selection ); ?>
                                    data-team="<?php echo esc_attr( $our_id ); ?>"
                                    data-opponent="<?php echo esc_attr( $opponent_id ); ?>"
                                    data-match-row="<?php echo esc_attr( (string) $row_id ); ?>"
                                ><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php echo esc_html( empty( $targets ) ? $labels['none'] : $labels['help'] ); ?></p>
                </td>
            </tr>
        </template>
        <script>
        (function () {
            var form = document.querySelector('form input[name="action"][value="<?php echo esc_js( $action ); ?>"]');
            form = form ? form.closest('form') : null;
            var template = document.getElementById('tactitwin-planning-context-template');
            if (!form || !template) return;
            var team = form.querySelector('[name="team_id"]');
            var opponent = form.querySelector('[name="opponent_id"]');
            var anchor = team ? team.closest('tr') : form.querySelector('table.form-table tr');
            if (!anchor) return;
            anchor.insertAdjacentHTML('beforebegin', template.innerHTML);
            var selector = form.querySelector('#tt-planning-context');
            if (!selector) return;

            var selectValue = function (field, value) {
                if (!field || !value) return;
                var option = Array.prototype.find.call(field.options || [], function (item) { return item.value === String(value); });
                if (option) field.value = String(value);
            };
            var sync = function () {
                var option = selector.options[selector.selectedIndex];
                var active = !!(option && option.value);
                if (active) {
                    selectValue(team, option.dataset.team || '');
                    selectValue(opponent, option.dataset.opponent || '');
                }
                [team, opponent].forEach(function (field) {
                    if (!field) return;
                    field.style.opacity = active ? '.7' : '1';
                    field.title = active ? '<?php echo esc_js( $labels['locked'] ); ?>' : '';
                });
            };
            selector.addEventListener('change', sync);
            sync();
        }());
        </script>
        <?php
    }

    private static function error_transient_key( string $action ): string {
        return array(
            'tactitwin_prepare_scenario' => 'tactitwin_scenario_error_',
            'tactitwin_generate_match_plan' => 'tactitwin_match_plan_error_',
            'tactitwin_run_what_if' => 'tactitwin_what_if_error_',
            'tactitwin_run_live_playbook' => 'tactitwin_live_playbook_error_',
            'tactitwin_generate_staff_brief' => 'tactitwin_staff_brief_error_',
        )[ $action ] ?? '';
    }

    private static function labels( string $language ): array {
        if ( 'es' === $language ) {
            return array(
                'label' => 'Contexto del partido',
                'manual' => '— Selección manual de equipos —',
                'our_team' => 'Nuestro equipo',
                'help' => 'Selecciona una vez el partido real y la perspectiva. TactiTwin reutilizará este mismo contexto en Scenario Lab, Match Plan, What-if, Live Playbook y Staff Brief.',
                'none' => 'No hay partidos importados disponibles. Se mantiene la selección manual de equipos.',
                'locked' => 'Estos equipos vienen del contexto de partido activo y se validan de nuevo en servidor.',
            );
        }
        return array(
            'label' => 'Match context',
            'manual' => '— Manual team selection —',
            'our_team' => 'Our team',
            'help' => 'Select the real match and team perspective once. TactiTwin will reuse this same context across Scenario Lab, Match Plan, What-if, Live Playbook and Staff Brief.',
            'none' => 'No imported matches are available. Manual team selection remains available.',
            'locked' => 'These teams come from the active match context and are validated again server-side.',
        );
    }
}
