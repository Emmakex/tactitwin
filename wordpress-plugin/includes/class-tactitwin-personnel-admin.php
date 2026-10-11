<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Personnel_Admin {
    public static function register(): void {
        add_action( 'admin_menu', array( self::class, 'menu' ) );
        add_action( 'admin_post_tactitwin_run_personnel_what_if', array( self::class, 'run' ) );
    }

    public static function menu(): void {
        add_submenu_page(
            'tactitwin',
            __( 'Personnel Lab', 'tactitwin' ),
            __( 'Personnel Lab', 'tactitwin' ),
            'manage_options',
            'tactitwin-personnel-lab',
            array( self::class, 'render' )
        );
    }

    public static function run(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage TactiTwin.', 'tactitwin' ) );
        }
        check_admin_referer( 'tactitwin_run_personnel_what_if' );

        $team_id          = absint( $_POST['team_id'] ?? 0 );
        $opponent_id      = absint( $_POST['opponent_id'] ?? 0 );
        $player_out_id    = absint( $_POST['player_out_id'] ?? 0 );
        $player_in_id     = absint( $_POST['player_in_id'] ?? 0 );
        $minute           = max( 1, min( 120, absint( $_POST['minute'] ?? 60 ) ) );
        $our_score        = min( 20, absint( $_POST['our_score'] ?? 0 ) );
        $opponent_score   = min( 20, absint( $_POST['opponent_score'] ?? 0 ) );
        $our_players      = max( 7, min( 11, absint( $_POST['our_players'] ?? 11 ) ) );
        $opponent_players = max( 7, min( 11, absint( $_POST['opponent_players'] ?? 11 ) ) );
        $sample           = max( 1, min( 10, absint( $_POST['sample'] ?? 5 ) ) );
        $language         = TactiTwin_I18n::normalize_language( sanitize_key( $_POST['language'] ?? TactiTwin_I18n::current_language() ) );

        try {
            ( new TactiTwin_Data_Preparation_Service() )->prepare_matchup( $team_id, $opponent_id, $sample );
            $result = ( new TactiTwin_Personnel_Service() )->personnel_what_if(
                $team_id,
                $opponent_id,
                $player_out_id,
                $player_in_id,
                $minute,
                $our_score,
                $opponent_score,
                $our_players,
                $opponent_players,
                $sample,
                $language
            );
            set_transient(
                'tactitwin_personnel_' . get_current_user_id(),
                array( 'result' => $result, 'language' => $language ),
                10 * MINUTE_IN_SECONDS
            );
        } catch ( Throwable $e ) {
            set_transient( 'tactitwin_personnel_error_' . get_current_user_id(), $e->getMessage(), MINUTE_IN_SECONDS );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=tactitwin-personnel-lab' ) );
        exit;
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $teams    = self::teams();
        $players  = self::players();
        $user     = get_current_user_id();
        $stored   = get_transient( 'tactitwin_personnel_' . $user );
        $error    = get_transient( 'tactitwin_personnel_error_' . $user );
        $language = is_array( $stored ) ? TactiTwin_I18n::normalize_language( (string) ( $stored['language'] ?? 'en' ) ) : TactiTwin_I18n::current_language();
        if ( $error ) {
            delete_transient( 'tactitwin_personnel_error_' . $user );
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( self::label( 'title', $language ) ); ?></h1>
            <p><?php echo esc_html( self::label( 'intro', $language ) ); ?></p>
            <?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>
            <?php if ( count( $teams ) >= 2 && count( $players ) >= 2 ) : ?>
                <?php self::form( $teams, $players, $language ); ?>
            <?php else : ?>
                <div class="notice notice-warning inline"><p><?php echo esc_html( self::label( 'need_data', $language ) ); ?></p></div>
            <?php endif; ?>
            <?php if ( is_array( $stored ) && ! empty( $stored['result'] ) ) : self::result( $stored['result'] ); endif; ?>
        </div>
        <?php
    }

    private static function form( array $teams, array $players, string $language ): void {
        $default_team = (string) ( $teams[0]['source_team_id'] ?? '' );
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;padding:20px;max-width:1000px;border:1px solid #dcdcde;">
            <input type="hidden" name="action" value="tactitwin_run_personnel_what_if">
            <?php wp_nonce_field( 'tactitwin_run_personnel_what_if' ); ?>
            <table class="form-table" role="presentation">
                <tr><th><?php echo esc_html( self::label( 'language', $language ) ); ?></th><td><select name="language"><option value="es" <?php selected( $language, 'es' ); ?>>Español</option><option value="en" <?php selected( $language, 'en' ); ?>>English</option></select></td></tr>
                <tr><th><?php echo esc_html( self::label( 'team', $language ) ); ?></th><td><?php self::team_select( 'team_id', 'tt-personnel-team', $teams ); ?></td></tr>
                <tr><th><?php echo esc_html( self::label( 'opponent', $language ) ); ?></th><td><?php self::team_select( 'opponent_id', 'tt-personnel-opponent', $teams, 1 ); ?></td></tr>
                <tr><th><?php echo esc_html( self::label( 'player_out', $language ) ); ?></th><td><?php self::player_select( 'player_out_id', 'tt-player-out', $players, $default_team ); ?></td></tr>
                <tr><th><?php echo esc_html( self::label( 'player_in', $language ) ); ?></th><td><?php self::player_select( 'player_in_id', 'tt-player-in', $players, $default_team, 1 ); ?></td></tr>
                <tr><th><?php echo esc_html( self::label( 'score', $language ) ); ?></th><td><input type="number" name="our_score" min="0" max="20" value="0" style="width:70px"> - <input type="number" name="opponent_score" min="0" max="20" value="0" style="width:70px"></td></tr>
                <tr><th><?php echo esc_html( self::label( 'players', $language ) ); ?></th><td><input type="number" name="our_players" min="7" max="11" value="11" style="width:70px"> v <input type="number" name="opponent_players" min="7" max="11" value="11" style="width:70px"></td></tr>
                <tr><th><?php echo esc_html( self::label( 'minute', $language ) ); ?></th><td><input type="number" name="minute" min="1" max="120" value="60"></td></tr>
                <tr><th><?php echo esc_html( self::label( 'sample', $language ) ); ?></th><td><input type="number" name="sample" min="1" max="10" value="5"></td></tr>
            </table>
            <?php submit_button( self::label( 'run', $language ) ); ?>
        </form>
        <script>
        (function(){
            const team=document.getElementById('tt-personnel-team');
            const selects=[document.getElementById('tt-player-out'),document.getElementById('tt-player-in')];
            function filter(){
                const id=team.value;
                selects.forEach(function(select){
                    let first=null;
                    Array.from(select.options).forEach(function(option){
                        const show=option.dataset.team===id;
                        option.hidden=!show;
                        option.disabled=!show;
                        if(show && first===null){ first=option; }
                    });
                    if(select.selectedOptions.length===0 || select.selectedOptions[0].disabled){
                        if(first){ first.selected=true; }
                    }
                });
            }
            team.addEventListener('change',filter); filter();
        })();
        </script>
        <?php
    }

    private static function result( array $result ): void {
        $language = TactiTwin_I18n::normalize_language( (string) ( $result['language'] ?? 'en' ) );
        $out = $result['player_out'] ?? array();
        $in  = $result['player_in'] ?? array();
        ?>
        <hr style="margin:32px 0;">
        <div style="max-width:1000px;">
            <h2><?php echo esc_html( (string) ( $out['player_name'] ?? '-' ) . ' → ' . (string) ( $in['player_name'] ?? '-' ) ); ?></h2>
            <?php if ( ! empty( $result['coverage']['warning'] ) ) : ?><div class="notice notice-warning inline"><p><?php echo esc_html( $result['coverage']['warning'] ); ?></p></div><?php endif; ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:18px 0;">
                <?php self::card( self::label( 'minute', $language ), (string) ( $result['minute'] ?? '-' ), self::label( 'hypothesis', $language ) ); ?>
                <?php self::card( self::label( 'similarity', $language ), ( $result['role']['profile_similarity'] ?? 0 ) . '/100', ! empty( $result['role']['same_primary_position'] ) ? self::label( 'same_role', $language ) : self::label( 'different_role', $language ) ); ?>
                <?php self::card( self::label( 'coverage', $language ), ( $result['coverage']['effective_minutes'] ?? 0 ) . ' min', (string) ( $result['coverage']['confidence_label'] ?? '' ) ); ?>
            </div>

            <h3><?php echo esc_html( self::label( 'dimensions', $language ) ); ?></h3>
            <table class="widefat striped"><thead><tr><th><?php echo esc_html( self::label( 'dimension', $language ) ); ?></th><th><?php echo esc_html( self::label( 'delta', $language ) ); ?></th></tr></thead><tbody>
            <?php foreach ( $result['dimensions'] ?? array() as $key => $value ) : ?><tr><td><?php echo esc_html( self::dimension( (string) $key, $language ) ); ?></td><td><?php echo esc_html( ( $value >= 0 ? '+' : '' ) . (string) $value ); ?></td></tr><?php endforeach; ?>
            </tbody></table>

            <h3 style="margin-top:24px;"><?php echo esc_html( self::label( 'raw_deltas', $language ) ); ?></h3>
            <table class="widefat striped"><thead><tr><th><?php echo esc_html( self::label( 'metric', $language ) ); ?></th><th><?php echo esc_html( self::label( 'delta', $language ) ); ?></th></tr></thead><tbody>
            <?php foreach ( $result['deltas'] ?? array() as $key => $value ) : ?><tr><td><?php echo esc_html( str_replace( '_', ' ', (string) $key ) ); ?></td><td><?php echo esc_html( ( $value >= 0 ? '+' : '' ) . (string) $value ); ?></td></tr><?php endforeach; ?>
            </tbody></table>

            <h3 style="margin-top:24px;"><?php echo esc_html( self::label( 'recommendations', $language ) ); ?></h3>
            <ol style="background:#fff;border:1px solid #dcdcde;padding:18px 18px 18px 38px;"><?php foreach ( $result['recommendations'] ?? array() as $item ) : ?><li style="margin-bottom:8px;"><?php echo esc_html( (string) $item ); ?></li><?php endforeach; ?></ol>
            <details style="margin-top:22px;"><summary><strong><?php echo esc_html( self::label( 'technical', $language ) ); ?></strong></summary><pre style="white-space:pre-wrap;background:#fff;padding:16px;border:1px solid #dcdcde;"><?php echo esc_html( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre></details>
        </div>
        <?php
    }

    private static function team_select( string $name, string $id, array $teams, int $default = 0 ): void {
        ?><select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" required><?php foreach ( $teams as $index => $team ) : ?><option value="<?php echo esc_attr( $team['source_team_id'] ); ?>" <?php selected( $index, $default ); ?>><?php echo esc_html( $team['team_name'] ); ?></option><?php endforeach; ?></select><?php
    }

    private static function player_select( string $name, string $id, array $players, string $team_id, int $default = 0 ): void {
        $visible = 0;
        ?><select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" required><?php foreach ( $players as $player ) : $same = (string) $player['last_team_source_id'] === $team_id; ?><option value="<?php echo esc_attr( $player['source_player_id'] ); ?>" data-team="<?php echo esc_attr( $player['last_team_source_id'] ); ?>" <?php selected( $same && $visible++ === $default ); ?>><?php echo esc_html( $player['player_name'] . ( $player['last_position_name'] ? ' — ' . $player['last_position_name'] : '' ) ); ?></option><?php endforeach; ?></select><?php
    }

    private static function card( string $title, string $value, string $meta ): void {
        ?><div style="background:#fff;border:1px solid #dcdcde;padding:16px;"><div style="font-size:12px;text-transform:uppercase;color:#646970;"><?php echo esc_html( $title ); ?></div><div style="font-size:24px;font-weight:700;margin-top:4px;"><?php echo esc_html( $value ); ?></div><div style="margin-top:4px;color:#646970;"><?php echo esc_html( $meta ); ?></div></div><?php
    }

    private static function teams(): array {
        global $wpdb;
        $table = TactiTwin_DB::table( 'teams' );
        return $wpdb->get_results( $wpdb->prepare( "SELECT source_team_id, team_name FROM {$table} WHERE source = %s ORDER BY team_name ASC", TactiTwin_StatsBomb_Normalizer::SOURCE ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function players(): array {
        global $wpdb;
        $table = TactiTwin_DB::table( 'players' );
        return $wpdb->get_results( $wpdb->prepare( "SELECT source_player_id, player_name, last_team_source_id, last_position_name FROM {$table} WHERE source = %s ORDER BY player_name ASC", TactiTwin_StatsBomb_Normalizer::SOURCE ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function dimension( string $key, string $language ): string {
        $map = array(
            'progression' => array( 'en' => 'Progression', 'es' => 'Progresión' ),
            'ball_security' => array( 'en' => 'Ball security', 'es' => 'Seguridad con balón' ),
            'threat' => array( 'en' => 'Attacking threat', 'es' => 'Amenaza ofensiva' ),
            'pressing' => array( 'en' => 'Pressing activity', 'es' => 'Actividad de presión' ),
            'defensive_activity' => array( 'en' => 'Defensive activity', 'es' => 'Actividad defensiva' ),
        );
        return $map[ $key ][ $language ] ?? $key;
    }

    private static function label( string $key, string $language ): string {
        $labels = array(
            'en' => array(
                'title'=>'TactiTwin Personnel Lab','intro'=>'Compare a hypothetical substitution inside the current score/manpower context using recent on-pitch event profiles.','need_data'=>'Import event data with at least two teams and players first.','language'=>'Language','team'=>'Our team','opponent'=>'Opponent','player_out'=>'Player out','player_in'=>'Player in','score'=>'Hypothetical score','players'=>'Players on pitch','minute'=>'Minute','sample'=>'Recent matches','run'=>'Compare substitution','hypothesis'=>'hypothetical substitution','similarity'=>'Profile similarity','same_role'=>'same primary position','different_role'=>'different primary position','coverage'=>'Comparable sample','dimensions'=>'Profile shifts','dimension'=>'Dimension','delta'=>'Incoming − outgoing','raw_deltas'=>'Measured per-90 deltas','metric'=>'Metric','recommendations'=>'Tactical implications','technical'=>'Technical evidence'
            ),
            'es' => array(
                'title'=>'TactiTwin Personnel Lab','intro'=>'Compara una sustitución hipotética dentro del contexto actual de marcador y superioridad numérica usando perfiles recientes mientras los jugadores estuvieron en campo.','need_data'=>'Importa primero datos de eventos con al menos dos equipos y jugadores.','language'=>'Idioma','team'=>'Nuestro equipo','opponent'=>'Rival','player_out'=>'Jugador que sale','player_in'=>'Jugador que entra','score'=>'Marcador hipotético','players'=>'Jugadores en campo','minute'=>'Minuto','sample'=>'Partidos recientes','run'=>'Comparar sustitución','hypothesis'=>'sustitución hipotética','similarity'=>'Similitud de perfil','same_role'=>'misma posición principal','different_role'=>'posición principal distinta','coverage'=>'Muestra comparable','dimensions'=>'Cambios de perfil','dimension'=>'Dimensión','delta'=>'Entrante − saliente','raw_deltas'=>'Diferencias medidas por 90','metric'=>'Métrica','recommendations'=>'Implicaciones tácticas','technical'=>'Evidencia técnica'
            ),
        );
        return $labels[ $language ][ $key ] ?? $labels['en'][ $key ] ?? $key;
    }
}
