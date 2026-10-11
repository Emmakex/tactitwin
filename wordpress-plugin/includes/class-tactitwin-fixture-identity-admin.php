<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Fixture_Identity_Admin {
    public static function register(): void {
        add_action( 'admin_post_tactitwin_generate_staff_brief', array( self::class, 'resolve_target_before_generation' ), 5 );
        add_filter( 'tactitwin_staff_brief_fixture_identity', array( self::class, 'from_generation_request' ), 10, 4 );
        add_action( 'admin_footer', array( self::class, 'render_generation_fields' ) );
    }

    public static function resolve_target_before_generation(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $target_match_id = absint( $_POST['target_match_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified below.
        if ( $target_match_id <= 0 ) {
            return;
        }

        check_admin_referer( 'tactitwin_generate_staff_brief' );
        $team_id = (string) absint( $_POST['team_id'] ?? 0 );

        try {
            $resolved = ( new TactiTwin_Match_Target_Service() )->resolve( $target_match_id, $team_id );
            $_POST['team_id'] = (string) $resolved['team_source_id'];
            $_POST['opponent_id'] = (string) $resolved['opponent_source_id'];
            $_POST['tactitwin_bind_fixture'] = '1';
        } catch ( Throwable $e ) {
            set_transient(
                'tactitwin_staff_brief_error_' . get_current_user_id(),
                $e->getMessage(),
                MINUTE_IN_SECONDS
            );
            wp_safe_redirect( admin_url( 'admin.php?page=tactitwin-staff-brief' ) );
            exit;
        }
    }

    public static function from_generation_request( array $fixture, array $match_plan, array $playbook, string $language ): array {
        unset( $playbook, $language );

        $target_match_id = absint( $_POST['target_match_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- parent Staff Brief action verifies nonce.
        if ( $target_match_id > 0 ) {
            $resolved = ( new TactiTwin_Match_Target_Service() )->resolve(
                $target_match_id,
                (string) ( $match_plan['team']['source_id'] ?? '' )
            );
            if ( (string) ( $match_plan['opponent']['source_id'] ?? '' ) !== (string) $resolved['opponent_source_id'] ) {
                throw new RuntimeException( 'Resolved target match opponent does not match the generated Match Plan.' );
            }
            return $resolved['fixture'];
        }

        if ( empty( $_POST['tactitwin_bind_fixture'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- parent Staff Brief action verifies nonce.
            return array();
        }

        $candidate = array(
            'source' => sanitize_key( wp_unslash( (string) ( $_POST['fixture_source'] ?? 'manual' ) ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'source_match_id' => sanitize_text_field( wp_unslash( (string) ( $_POST['fixture_source_match_id'] ?? '' ) ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'source_competition_id' => sanitize_text_field( wp_unslash( (string) ( $_POST['fixture_source_competition_id'] ?? '' ) ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'source_season_id' => sanitize_text_field( wp_unslash( (string) ( $_POST['fixture_source_season_id'] ?? '' ) ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'match_date' => sanitize_text_field( wp_unslash( (string) ( $_POST['fixture_match_date'] ?? '' ) ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'kick_off' => sanitize_text_field( wp_unslash( (string) ( $_POST['fixture_kick_off'] ?? '' ) ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'match_week' => sanitize_text_field( wp_unslash( (string) ( $_POST['fixture_match_week'] ?? '' ) ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
        );

        return TactiTwin_Fixture_Identity::normalize(
            $candidate,
            (string) ( $match_plan['team']['source_id'] ?? '' ),
            (string) ( $match_plan['opponent']['source_id'] ?? '' )
        );
    }

    public static function render_generation_fields(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $page = sanitize_key( (string) ( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'tactitwin-staff-brief' !== $page ) {
            return;
        }

        $language = TactiTwin_I18n::current_language();
        $result = get_transient( 'tactitwin_staff_brief_' . get_current_user_id() );
        $stored_fixture = is_array( $result['brief']['fixture'] ?? null ) ? $result['brief']['fixture'] : array();
        $enabled = ! empty( $stored_fixture ) || ! is_array( $result );
        $labels = self::labels( $language );
        $targets = array();
        $selected_target_id = 0;

        try {
            $service = new TactiTwin_Match_Target_Service();
            $targets = $service->recent_targets( 150 );
            if ( ! empty( $stored_fixture['source'] ) && ! empty( $stored_fixture['source_match_id'] ) ) {
                $stored_target = $service->target_by_source(
                    (string) $stored_fixture['source'],
                    (string) $stored_fixture['source_match_id']
                );
                $selected_target_id = (int) ( $stored_target['id'] ?? 0 );
            }
        } catch ( Throwable $e ) {
            $targets = array();
        }
        ?>
        <template id="tactitwin-fixture-fields-template">
            <tr class="tt-fixture-row">
                <th><label for="tt-target-match"><?php echo esc_html( $labels['target'] ); ?></label></th>
                <td>
                    <select id="tt-target-match" name="target_match_id" style="min-width:520px;max-width:100%;">
                        <option value="0"><?php echo esc_html( $labels['manual'] ); ?></option>
                        <?php foreach ( $targets as $target ) : ?>
                            <?php
                            $target_id = (int) ( $target['id'] ?? 0 );
                            $home_name = (string) ( $target['home_team_name'] ?? $target['home_team_source_id'] ?? '' );
                            $away_name = (string) ( $target['away_team_name'] ?? $target['away_team_source_id'] ?? '' );
                            $date = (string) ( $target['match_date'] ?? '' );
                            $competition = trim( (string) ( $target['competition_name'] ?? '' ) );
                            $season = trim( (string) ( $target['season_name'] ?? '' ) );
                            $context = trim( $competition . ( '' !== $season ? ' · ' . $season : '' ), ' ·' );
                            $option_label = trim( $date . ' · ' . $home_name . ' vs ' . $away_name . ( '' !== $context ? ' · ' . $context : '' ), ' ·' );
                            ?>
                            <option
                                value="<?php echo esc_attr( (string) $target_id ); ?>"
                                <?php selected( $selected_target_id, $target_id ); ?>
                                data-home="<?php echo esc_attr( (string) ( $target['home_team_source_id'] ?? '' ) ); ?>"
                                data-away="<?php echo esc_attr( (string) ( $target['away_team_source_id'] ?? '' ) ); ?>"
                                data-source="<?php echo esc_attr( (string) ( $target['source'] ?? '' ) ); ?>"
                                data-match-id="<?php echo esc_attr( (string) ( $target['source_match_id'] ?? '' ) ); ?>"
                                data-competition="<?php echo esc_attr( (string) ( $target['source_competition_id'] ?? '' ) ); ?>"
                                data-season="<?php echo esc_attr( (string) ( $target['source_season_id'] ?? '' ) ); ?>"
                                data-date="<?php echo esc_attr( (string) ( $target['match_date'] ?? '' ) ); ?>"
                                data-kick-off="<?php echo esc_attr( (string) ( $target['kick_off'] ?? '' ) ); ?>"
                                data-week="<?php echo esc_attr( (string) ( $target['match_week'] ?? '' ) ); ?>"
                            ><?php echo esc_html( $option_label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php echo esc_html( empty( $targets ) ? $labels['no_targets'] : $labels['target_help'] ); ?></p>
                </td>
            </tr>
            <tr class="tt-fixture-row">
                <th><?php echo esc_html( $labels['fixture'] ); ?></th>
                <td>
                    <label><input type="checkbox" id="tt-bind-fixture" name="tactitwin_bind_fixture" value="1" <?php checked( $enabled ); ?>> <strong><?php echo esc_html( $labels['bind'] ); ?></strong></label>
                    <p class="description"><?php echo esc_html( $labels['help'] ); ?></p>
                </td>
            </tr>
            <tr class="tt-fixture-detail"><th><label for="tt-fixture-date"><?php echo esc_html( $labels['date'] ); ?></label></th><td><input id="tt-fixture-date" name="fixture_match_date" type="date" value="<?php echo esc_attr( (string) ( $stored_fixture['match_date'] ?? '' ) ); ?>"> <span class="description"><?php echo esc_html( $labels['date_help'] ); ?></span></td></tr>
            <tr class="tt-fixture-detail"><th><label for="tt-fixture-source"><?php echo esc_html( $labels['source'] ); ?></label></th><td><input id="tt-fixture-source" name="fixture_source" type="text" value="<?php echo esc_attr( (string) ( $stored_fixture['source'] ?? 'manual' ) ); ?>" placeholder="manual / statsbomb / football-data"></td></tr>
            <tr class="tt-fixture-detail"><th><label for="tt-fixture-match-id"><?php echo esc_html( $labels['match_id'] ); ?></label></th><td><input id="tt-fixture-match-id" name="fixture_source_match_id" type="text" value="<?php echo esc_attr( (string) ( $stored_fixture['source_match_id'] ?? '' ) ); ?>"> <span class="description"><?php echo esc_html( $labels['match_id_help'] ); ?></span></td></tr>
            <tr class="tt-fixture-detail"><th><?php echo esc_html( $labels['context'] ); ?></th><td style="display:flex;gap:8px;flex-wrap:wrap;"><input name="fixture_source_competition_id" type="text" value="<?php echo esc_attr( (string) ( $stored_fixture['source_competition_id'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( $labels['competition'] ); ?>"><input name="fixture_source_season_id" type="text" value="<?php echo esc_attr( (string) ( $stored_fixture['source_season_id'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( $labels['season'] ); ?>"><input name="fixture_match_week" type="text" value="<?php echo esc_attr( (string) ( $stored_fixture['match_week'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( $labels['week'] ); ?>"><input name="fixture_kick_off" type="time" step="1" value="<?php echo esc_attr( (string) ( $stored_fixture['kick_off'] ?? '' ) ); ?>"></td></tr>
        </template>
        <script>
        (function () {
            var sample = document.getElementById('tt-staff-sample');
            var template = document.getElementById('tactitwin-fixture-fields-template');
            if (!sample || !template || !sample.closest('tr')) return;
            sample.closest('tr').insertAdjacentHTML('beforebegin', template.innerHTML);

            var target = document.getElementById('tt-target-match');
            var toggle = document.getElementById('tt-bind-fixture');
            var team = document.getElementById('tt-staff-team');
            var opponent = document.getElementById('tt-staff-opponent');
            var fields = {
                source: document.getElementById('tt-fixture-source'),
                matchId: document.getElementById('tt-fixture-match-id'),
                date: document.getElementById('tt-fixture-date'),
                competition: document.querySelector('[name="fixture_source_competition_id"]'),
                season: document.querySelector('[name="fixture_source_season_id"]'),
                week: document.querySelector('[name="fixture_match_week"]'),
                kickOff: document.querySelector('[name="fixture_kick_off"]')
            };

            var setSelectValue = function (select, value) {
                if (!select || !value) return false;
                var option = Array.prototype.find.call(select.options, function (item) { return item.value === String(value); });
                if (!option) return false;
                select.value = String(value);
                return true;
            };

            var applyTarget = function () {
                if (!target || !target.value || target.value === '0') return;
                var option = target.options[target.selectedIndex];
                if (!option) return;
                var home = option.dataset.home || '';
                var away = option.dataset.away || '';
                var current = team ? team.value : '';
                var ourTeam = current === home || current === away ? current : home;
                var rival = ourTeam === home ? away : home;
                setSelectValue(team, ourTeam);
                setSelectValue(opponent, rival);
                if (toggle) toggle.checked = true;
                if (fields.source) fields.source.value = option.dataset.source || '';
                if (fields.matchId) fields.matchId.value = option.dataset.matchId || '';
                if (fields.date) fields.date.value = option.dataset.date || '';
                if (fields.competition) fields.competition.value = option.dataset.competition || '';
                if (fields.season) fields.season.value = option.dataset.season || '';
                if (fields.week) fields.week.value = option.dataset.week || '';
                if (fields.kickOff) fields.kickOff.value = option.dataset.kickOff || '';
            };

            var sync = function () {
                var canonical = !!(target && target.value && target.value !== '0');
                if (canonical && toggle) toggle.checked = true;
                var enabled = canonical || !!(toggle && toggle.checked);
                document.querySelectorAll('.tt-fixture-detail input').forEach(function (field) {
                    field.disabled = !enabled;
                    field.readOnly = canonical;
                });
                document.querySelectorAll('.tt-fixture-detail').forEach(function (row) { row.style.opacity = enabled ? '1' : '.55'; });
                if (fields.date) fields.date.required = enabled && !canonical && !(fields.matchId && fields.matchId.value.trim());
            };

            if (target) target.addEventListener('change', function () { applyTarget(); sync(); });
            if (toggle) toggle.addEventListener('change', sync);
            if (fields.matchId) fields.matchId.addEventListener('input', sync);
            applyTarget();
            sync();
        }());
        </script>
        <?php
    }

    private static function labels( string $language ): array {
        if ( 'es' === TactiTwin_I18n::normalize_language( $language ) ) {
            return array(
                'target'=>'Partido importado','target_help'=>'Selecciona un partido real ya importado. TactiTwin resolverá automáticamente rival e identidad del fixture.','no_targets'=>'No hay partidos importados disponibles; puedes usar la identidad manual.','manual'=>'— Identidad manual / sin partido importado —','fixture'=>'Partido objetivo','bind'=>'Vincular este Staff Brief a un partido concreto','help'=>'Evita mezclar revisiones de dos encuentros distintos contra el mismo rival.','date'=>'Fecha del partido','date_help'=>'Obligatoria si no existe un ID de partido del proveedor.','source'=>'Fuente del partido','match_id'=>'ID de partido del proveedor','match_id_help'=>'Si existe, es la identidad principal del fixture.','context'=>'Contexto del partido','competition'=>'Competición','season'=>'Temporada','week'=>'Jornada',
            );
        }
        return array(
            'target'=>'Imported match','target_help'=>'Select an already imported real match. TactiTwin resolves the opponent and fixture identity automatically.','no_targets'=>'No imported matches are available; manual fixture identity remains available.','manual'=>'— Manual identity / no imported match —','fixture'=>'Target fixture','bind'=>'Bind this Staff Brief to one exact fixture','help'=>'Prevents revisions from different matches against the same opponent from sharing one approval chain.','date'=>'Match date','date_help'=>'Required when no provider match ID is available.','source'=>'Fixture source','match_id'=>'Provider match ID','match_id_help'=>'When available, this is the primary fixture identity.','context'=>'Match context','competition'=>'Competition','season'=>'Season','week'=>'Match week',
        );
    }
}
