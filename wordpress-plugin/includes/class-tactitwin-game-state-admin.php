<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Game_State_Admin {
    public static function register(): void {
        add_action( 'admin_menu', array( self::class, 'menu' ) );
        add_action( 'admin_post_tactitwin_run_what_if', array( self::class, 'run' ) );
    }

    public static function menu(): void {
        add_submenu_page(
            'tactitwin',
            __( 'What-if Lab', 'tactitwin' ),
            __( 'What-if Lab', 'tactitwin' ),
            'manage_options',
            'tactitwin-what-if',
            array( self::class, 'render' )
        );
    }

    public static function run(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage TactiTwin.', 'tactitwin' ) );
        }

        check_admin_referer( 'tactitwin_run_what_if' );

        $team_id          = absint( $_POST['team_id'] ?? 0 );
        $opponent_id      = absint( $_POST['opponent_id'] ?? 0 );
        $our_score        = min( 20, absint( $_POST['our_score'] ?? 0 ) );
        $opponent_score   = min( 20, absint( $_POST['opponent_score'] ?? 0 ) );
        $our_players      = max( 7, min( 11, absint( $_POST['our_players'] ?? 11 ) ) );
        $opponent_players = max( 7, min( 11, absint( $_POST['opponent_players'] ?? 11 ) ) );
        $minute           = max( 1, min( 120, absint( $_POST['minute'] ?? 60 ) ) );
        $sample           = max( 1, min( 10, absint( $_POST['sample'] ?? 5 ) ) );
        $language         = TactiTwin_I18n::normalize_language( sanitize_key( $_POST['language'] ?? TactiTwin_I18n::current_language() ) );

        try {
            $preparation = ( new TactiTwin_Data_Preparation_Service() )->prepare_matchup( $team_id, $opponent_id, $sample );
            $scenario    = ( new TactiTwin_Profile_Service() )->game_state_what_if(
                $team_id,
                $opponent_id,
                $our_score,
                $opponent_score,
                $minute,
                $sample,
                $language,
                $our_players,
                $opponent_players
            );

            set_transient(
                'tactitwin_what_if_' . get_current_user_id(),
                array(
                    'preparation' => $preparation,
                    'scenario'    => $scenario,
                    'language'    => $language,
                ),
                10 * MINUTE_IN_SECONDS
            );
        } catch ( Throwable $e ) {
            set_transient( 'tactitwin_what_if_error_' . get_current_user_id(), $e->getMessage(), MINUTE_IN_SECONDS );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=tactitwin-what-if' ) );
        exit;
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $teams    = self::teams();
        $user     = get_current_user_id();
        $result   = get_transient( 'tactitwin_what_if_' . $user );
        $error    = get_transient( 'tactitwin_what_if_error_' . $user );
        $language = is_array( $result ) && ! empty( $result['language'] )
            ? TactiTwin_I18n::normalize_language( (string) $result['language'] )
            : TactiTwin_I18n::current_language();

        if ( $error ) {
            delete_transient( 'tactitwin_what_if_error_' . $user );
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( self::label( 'title', $language ) ); ?></h1>
            <p><?php echo esc_html( self::label( 'intro', $language ) ); ?></p>

            <?php if ( $error ) : ?>
                <div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
            <?php endif; ?>

            <?php if ( count( $teams ) < 2 ) : ?>
                <div class="notice notice-warning inline"><p><?php echo esc_html__( 'Import a competition/season first so at least two teams are available.', 'tactitwin' ); ?></p></div>
            <?php else : ?>
                <?php self::render_form( $teams, $language ); ?>
            <?php endif; ?>

            <?php if ( is_array( $result ) && ! empty( $result['scenario'] ) ) : ?>
                <?php self::render_result( $result['scenario'] ); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_form( array $teams, string $language ): void {
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;padding:20px;max-width:980px;border:1px solid #dcdcde;">
            <input type="hidden" name="action" value="tactitwin_run_what_if">
            <?php wp_nonce_field( 'tactitwin_run_what_if' ); ?>
            <table class="form-table" role="presentation">
                <tr><th><?php echo esc_html( self::label( 'language', $language ) ); ?></th><td><select name="language"><option value="es" <?php selected( $language, 'es' ); ?>>Español</option><option value="en" <?php selected( $language, 'en' ); ?>>English</option></select></td></tr>
                <tr><th><?php echo esc_html( self::label( 'team', $language ) ); ?></th><td><?php self::team_select( 'team_id', $teams ); ?></td></tr>
                <tr><th><?php echo esc_html( self::label( 'opponent', $language ) ); ?></th><td><?php self::team_select( 'opponent_id', $teams, 1 ); ?></td></tr>
                <tr><th><?php echo esc_html( self::label( 'score', $language ) ); ?></th><td><input type="number" name="our_score" min="0" max="20" value="1" style="width:70px"> - <input type="number" name="opponent_score" min="0" max="20" value="0" style="width:70px"></td></tr>
                <tr><th><?php echo esc_html( self::label( 'players', $language ) ); ?></th><td><input type="number" name="our_players" min="7" max="11" value="11" style="width:70px"> v <input type="number" name="opponent_players" min="7" max="11" value="11" style="width:70px"><p class="description"><?php echo esc_html( self::label( 'players_help', $language ) ); ?></p></td></tr>
                <tr><th><?php echo esc_html( self::label( 'minute', $language ) ); ?></th><td><input type="number" name="minute" min="1" max="120" value="60"></td></tr>
                <tr><th><?php echo esc_html( self::label( 'sample', $language ) ); ?></th><td><input type="number" name="sample" min="1" max="10" value="5"></td></tr>
            </table>
            <?php submit_button( self::label( 'run', $language ) ); ?>
        </form>
        <?php
    }

    private static function render_result( array $scenario ): void {
        $language = TactiTwin_I18n::normalize_language( (string) ( $scenario['language'] ?? 'en' ) );
        $history  = $scenario['historical_behavior'] ?? array();
        $manpower = $scenario['coverage']['manpower'] ?? array();
        ?>
        <hr style="margin:32px 0;">
        <div style="max-width:1000px;">
            <h2><?php echo esc_html( (string) ( $scenario['hypothesis']['headline'] ?? '' ) ); ?></h2>
            <?php if ( ! empty( $scenario['coverage']['warning'] ) ) : ?><div class="notice notice-warning inline"><p><?php echo esc_html( $scenario['coverage']['warning'] ); ?></p></div><?php endif; ?>
            <?php if ( ! empty( $manpower['warning'] ) ) : ?><div class="notice notice-warning inline"><p><?php echo esc_html( $manpower['warning'] ); ?></p></div><?php endif; ?>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:18px 0;">
                <?php self::card( self::label( 'state', $language ), strtoupper( (string) ( $scenario['hypothesis']['state_label'] ?? '' ) ), self::label( 'historical_condition', $language ) ); ?>
                <?php self::card( self::label( 'manpower', $language ), (string) ( $scenario['hypothesis']['our_players'] ?? 11 ) . 'v' . (string) ( $scenario['hypothesis']['opponent_players'] ?? 11 ), strtoupper( (string) ( $scenario['hypothesis']['manpower_state_label'] ?? '' ) ) ); ?>
                <?php self::card( self::label( 'risk', $language ), ( $scenario['risk']['score'] ?? 0 ) . '/100', (string) ( $scenario['risk']['level_label'] ?? '' ) ); ?>
                <?php self::card( self::label( 'opportunity', $language ), ( $scenario['opportunity']['score'] ?? 0 ) . '/100', (string) ( $scenario['opportunity']['level_label'] ?? '' ) ); ?>
                <?php self::card( self::label( 'coverage', $language ), ( $scenario['coverage']['effective_minutes'] ?? 0 ) . ' min', (string) ( $scenario['coverage']['status_label'] ?? '' ) ); ?>
                <?php self::card( self::label( 'manpower_coverage', $language ), ( $manpower['effective_minutes'] ?? 0 ) . ' min', (string) ( $manpower['status_label'] ?? '' ) ); ?>
            </div>

            <h3><?php echo esc_html( self::label( 'recommendations', $language ) ); ?></h3>
            <ol style="background:#fff;border:1px solid #dcdcde;padding:18px 18px 18px 38px;">
                <?php foreach ( $scenario['recommendations'] ?? array() as $item ) : ?><li style="margin-bottom:8px;"><?php echo esc_html( (string) $item ); ?></li><?php endforeach; ?>
            </ol>

            <h3><?php echo esc_html( self::label( 'triggers', $language ) ); ?></h3>
            <table class="widefat striped"><thead><tr><th><?php echo esc_html( self::label( 'if', $language ) ); ?></th><th><?php echo esc_html( self::label( 'then', $language ) ); ?></th></tr></thead><tbody>
                <?php foreach ( $scenario['decision_triggers'] ?? array() as $trigger ) : ?><tr><td><?php echo esc_html( (string) $trigger['trigger'] ); ?></td><td><?php echo esc_html( (string) $trigger['action'] ); ?></td></tr><?php endforeach; ?>
            </tbody></table>

            <h3 style="margin-top:24px;"><?php echo esc_html( self::label( 'history', $language ) ); ?></h3>
            <table class="widefat striped"><thead><tr><th></th><th>xG/90</th><th><?php echo esc_html( self::label( 'shots90', $language ) ); ?></th><th><?php echo esc_html( self::label( 'box90', $language ) ); ?></th><th><?php echo esc_html( self::label( 'press90', $language ) ); ?></th><th><?php echo esc_html( self::label( 'minutes', $language ) ); ?></th></tr></thead><tbody>
                <?php self::history_row( self::label( 'ours', $language ), $history['our_state'] ?? array() ); ?>
                <?php self::history_row( self::label( 'theirs', $language ), $history['opponent_reciprocal_state'] ?? array() ); ?>
            </tbody></table>

            <h3 style="margin-top:24px;"><?php echo esc_html( self::label( 'manpower_history', $language ) ); ?></h3>
            <table class="widefat striped"><thead><tr><th></th><th>xG/90</th><th><?php echo esc_html( self::label( 'shots90', $language ) ); ?></th><th><?php echo esc_html( self::label( 'box90', $language ) ); ?></th><th><?php echo esc_html( self::label( 'press90', $language ) ); ?></th><th><?php echo esc_html( self::label( 'minutes', $language ) ); ?></th></tr></thead><tbody>
                <?php self::history_row( self::label( 'ours_manpower', $language ), $history['our_manpower_state'] ?? array() ); ?>
                <?php self::history_row( self::label( 'theirs_manpower', $language ), $history['opponent_reciprocal_manpower_state'] ?? array() ); ?>
            </tbody></table>

            <details style="margin-top:22px;"><summary><strong><?php echo esc_html( self::label( 'technical', $language ) ); ?></strong></summary><pre style="white-space:pre-wrap;background:#fff;padding:16px;border:1px solid #dcdcde;"><?php echo esc_html( wp_json_encode( $scenario, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre></details>
        </div>
        <?php
    }

    private static function history_row( string $label, array $history ): void {
        $per90 = $history['per_90'] ?? array();
        ?><tr><td><strong><?php echo esc_html( $label ); ?></strong></td><td><?php echo esc_html( (string) ( $per90['xg'] ?? 0 ) ); ?></td><td><?php echo esc_html( (string) ( $per90['shots'] ?? 0 ) ); ?></td><td><?php echo esc_html( (string) ( $per90['box_entries'] ?? 0 ) ); ?></td><td><?php echo esc_html( (string) ( $per90['high_pressures'] ?? 0 ) ); ?></td><td><?php echo esc_html( (string) ( $history['minutes'] ?? 0 ) ); ?></td></tr><?php
    }

    private static function card( string $title, string $value, string $meta ): void {
        ?><div style="background:#fff;border:1px solid #dcdcde;padding:16px;"><div style="font-size:12px;text-transform:uppercase;color:#646970;"><?php echo esc_html( $title ); ?></div><div style="font-size:24px;font-weight:700;margin-top:4px;"><?php echo esc_html( $value ); ?></div><div style="margin-top:4px;color:#646970;"><?php echo esc_html( $meta ); ?></div></div><?php
    }

    private static function team_select( string $name, array $teams, int $default_index = 0 ): void {
        ?><select name="<?php echo esc_attr( $name ); ?>" required><?php foreach ( $teams as $index => $team ) : ?><option value="<?php echo esc_attr( $team['source_team_id'] ); ?>" <?php selected( $index, $default_index ); ?>><?php echo esc_html( $team['team_name'] ); ?></option><?php endforeach; ?></select><?php
    }

    private static function teams(): array {
        global $wpdb;
        $table = TactiTwin_DB::table( 'teams' );
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT source_team_id, team_name FROM {$table} WHERE source = %s ORDER BY team_name ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                TactiTwin_StatsBomb_Normalizer::SOURCE
            ),
            ARRAY_A
        ) ?: array();
    }

    private static function label( string $key, string $language ): string {
        $labels = array(
            'en' => array(
                'title' => 'TactiTwin What-if Lab',
                'intro' => 'Choose a hypothetical minute, exact scoreline and player count. TactiTwin conditions the plan on historically observed score-state and numerical-state behaviour.',
                'language' => 'Language', 'team' => 'Our team', 'opponent' => 'Opponent', 'score' => 'Hypothetical score', 'players' => 'Players on pitch', 'players_help' => 'Use 11v11 normally, or reduce either side after a hypothetical dismissal.', 'minute' => 'Minute', 'sample' => 'Recent matches', 'run' => 'Run What-if',
                'state' => 'Game state', 'manpower' => 'Numerical state', 'historical_condition' => 'historical conditioning', 'risk' => 'Opponent intensity', 'opportunity' => 'Our intensity', 'coverage' => 'Score-state coverage', 'manpower_coverage' => 'Manpower coverage',
                'recommendations' => 'Context-adjusted tactical response', 'triggers' => 'Decision triggers', 'if' => 'If observed', 'then' => 'Response', 'history' => 'Historical score-state behaviour', 'manpower_history' => 'Historical numerical-state behaviour',
                'shots90' => 'Shots/90', 'box90' => 'Box entries/90', 'press90' => 'High pressures/90', 'minutes' => 'Observed minutes', 'ours' => 'Our team in selected score state', 'theirs' => 'Opponent in reciprocal score state', 'ours_manpower' => 'Our team in selected numerical state', 'theirs_manpower' => 'Opponent in reciprocal numerical state', 'technical' => 'Technical evidence',
            ),
            'es' => array(
                'title' => 'TactiTwin What-if Lab',
                'intro' => 'Elige minuto, marcador exacto y número de jugadores. TactiTwin condiciona el plan según el comportamiento histórico por estado del marcador y estado numérico.',
                'language' => 'Idioma', 'team' => 'Nuestro equipo', 'opponent' => 'Rival', 'score' => 'Marcador hipotético', 'players' => 'Jugadores en campo', 'players_help' => 'Usa 11v11 normalmente o reduce cualquiera de los equipos tras una expulsión hipotética.', 'minute' => 'Minuto', 'sample' => 'Partidos recientes', 'run' => 'Ejecutar What-if',
                'state' => 'Estado del partido', 'manpower' => 'Estado numérico', 'historical_condition' => 'condicionamiento histórico', 'risk' => 'Intensidad rival', 'opportunity' => 'Nuestra intensidad', 'coverage' => 'Cobertura del marcador', 'manpower_coverage' => 'Cobertura numérica',
                'recommendations' => 'Respuesta táctica adaptada al contexto', 'triggers' => 'Disparadores de decisión', 'if' => 'Si observamos', 'then' => 'Respuesta', 'history' => 'Comportamiento histórico por estado del marcador', 'manpower_history' => 'Comportamiento histórico por estado numérico',
                'shots90' => 'Tiros/90', 'box90' => 'Entradas al área/90', 'press90' => 'Presiones altas/90', 'minutes' => 'Minutos observados', 'ours' => 'Nuestro equipo en el estado de marcador seleccionado', 'theirs' => 'Rival en el estado de marcador recíproco', 'ours_manpower' => 'Nuestro equipo en el estado numérico seleccionado', 'theirs_manpower' => 'Rival en el estado numérico recíproco', 'technical' => 'Evidencia técnica',
            ),
        );
        return $labels[ $language ][ $key ] ?? $labels['en'][ $key ] ?? $key;
    }
}
