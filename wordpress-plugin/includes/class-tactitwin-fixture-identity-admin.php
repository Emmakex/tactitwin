<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Fixture_Identity_Admin {
    public static function register(): void {
        add_filter( 'tactitwin_staff_brief_fixture_identity', array( self::class, 'from_generation_request' ), 10, 4 );
        add_action( 'admin_footer', array( self::class, 'render_generation_fields' ) );
    }

    public static function from_generation_request( array $fixture, array $match_plan, array $playbook, string $language ): array {
        unset( $playbook, $language );
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
        ?>
        <template id="tactitwin-fixture-fields-template">
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
            var toggle = document.getElementById('tt-bind-fixture');
            var date = document.getElementById('tt-fixture-date');
            var matchId = document.getElementById('tt-fixture-match-id');
            var sync = function () {
                var enabled = !!(toggle && toggle.checked);
                document.querySelectorAll('.tt-fixture-detail input').forEach(function (field) { field.disabled = !enabled; });
                document.querySelectorAll('.tt-fixture-detail').forEach(function (row) { row.style.opacity = enabled ? '1' : '.55'; });
                if (date) date.required = enabled && !(matchId && matchId.value.trim());
            };
            if (toggle) toggle.addEventListener('change', sync);
            if (matchId) matchId.addEventListener('input', sync);
            sync();
        }());
        </script>
        <?php
    }

    private static function labels( string $language ): array {
        if ( 'es' === TactiTwin_I18n::normalize_language( $language ) ) {
            return array(
                'fixture'=>'Partido objetivo','bind'=>'Vincular este Staff Brief a un partido concreto','help'=>'Evita mezclar revisiones de dos encuentros distintos contra el mismo rival.','date'=>'Fecha del partido','date_help'=>'Obligatoria si no existe un ID de partido del proveedor.','source'=>'Fuente del partido','match_id'=>'ID de partido del proveedor','match_id_help'=>'Si existe, es la identidad principal del fixture.','context'=>'Contexto opcional','competition'=>'Competición','season'=>'Temporada','week'=>'Jornada',
            );
        }
        return array(
            'fixture'=>'Target fixture','bind'=>'Bind this Staff Brief to one exact fixture','help'=>'Prevents revisions from different matches against the same opponent from sharing one approval chain.','date'=>'Match date','date_help'=>'Required when no provider match ID is available.','source'=>'Fixture source','match_id'=>'Provider match ID','match_id_help'=>'When available, this is the primary fixture identity.','context'=>'Optional context','competition'=>'Competition','season'=>'Season','week'=>'Match week',
        );
    }
}
