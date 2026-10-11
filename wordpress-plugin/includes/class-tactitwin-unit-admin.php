<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Unit_Admin {
    public static function register(): void {
        add_action( 'admin_menu', array( self::class, 'menu' ) );
        add_action( 'admin_post_tactitwin_run_unit_pair', array( self::class, 'run' ) );
    }

    public static function menu(): void {
        add_submenu_page(
            'tactitwin',
            __( 'Unit Lab', 'tactitwin' ),
            __( 'Unit Lab', 'tactitwin' ),
            'manage_options',
            'tactitwin-unit-lab',
            array( self::class, 'render' )
        );
    }

    public static function run(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage TactiTwin.', 'tactitwin' ) );
        }
        check_admin_referer( 'tactitwin_run_unit_pair' );

        $team_id     = absint( $_POST['team_id'] ?? 0 );
        $player_a_id = absint( $_POST['player_a_id'] ?? 0 );
        $player_b_id = absint( $_POST['player_b_id'] ?? 0 );
        $sample      = max( 1, min( 10, absint( $_POST['sample'] ?? 5 ) ) );
        $language    = TactiTwin_I18n::normalize_language( sanitize_key( $_POST['language'] ?? TactiTwin_I18n::current_language() ) );

        try {
            $profile = ( new TactiTwin_Unit_Service() )->pair_profile( $team_id, $player_a_id, $player_b_id, $sample );
            set_transient(
                'tactitwin_unit_' . get_current_user_id(),
                array( 'profile'=>$profile, 'language'=>$language, 'team_id'=>$team_id ),
                10 * MINUTE_IN_SECONDS
            );
        } catch ( Throwable $e ) {
            set_transient( 'tactitwin_unit_error_' . get_current_user_id(), $e->getMessage(), MINUTE_IN_SECONDS );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=tactitwin-unit-lab' ) );
        exit;
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $teams      = self::teams();
        $team_roster= self::team_roster();
        $user       = get_current_user_id();
        $stored     = get_transient( 'tactitwin_unit_' . $user );
        $error      = get_transient( 'tactitwin_unit_error_' . $user );
        $language   = is_array( $stored ) ? TactiTwin_I18n::normalize_language( (string) ( $stored['language'] ?? 'en' ) ) : TactiTwin_I18n::current_language();
        if ( $error ) {
            delete_transient( 'tactitwin_unit_error_' . $user );
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( self::label( 'title', $language ) ); ?></h1>
            <p><?php echo esc_html( self::label( 'intro', $language ) ); ?></p>
            <?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>
            <?php if ( ! empty( $teams ) && ! empty( $team_roster ) ) : self::form( $teams, $team_roster, $language ); else : ?>
                <div class="notice notice-warning inline"><p><?php echo esc_html( self::label( 'need_data', $language ) ); ?></p></div>
            <?php endif; ?>
            <?php if ( is_array( $stored ) && ! empty( $stored['profile'] ) ) : self::result( $stored['profile'], $language ); endif; ?>
        </div>
        <?php
    }

    private static function form( array $teams, array $team_roster, string $language ): void {
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;padding:20px;max-width:980px;border:1px solid #dcdcde;">
            <input type="hidden" name="action" value="tactitwin_run_unit_pair">
            <?php wp_nonce_field( 'tactitwin_run_unit_pair' ); ?>
            <table class="form-table" role="presentation">
                <tr><th><?php echo esc_html( self::label( 'language', $language ) ); ?></th><td><select name="language"><option value="es" <?php selected( $language, 'es' ); ?>>Español</option><option value="en" <?php selected( $language, 'en' ); ?>>English</option></select></td></tr>
                <tr><th><?php echo esc_html( self::label( 'team', $language ) ); ?></th><td><?php self::team_select( $teams ); ?></td></tr>
                <tr><th><?php echo esc_html( self::label( 'player_a', $language ) ); ?></th><td><?php self::player_select( 'player_a_id', 'tt-unit-a', $team_roster, 0 ); ?></td></tr>
                <tr><th><?php echo esc_html( self::label( 'player_b', $language ) ); ?></th><td><?php self::player_select( 'player_b_id', 'tt-unit-b', $team_roster, 1 ); ?></td></tr>
                <tr><th><?php echo esc_html( self::label( 'sample', $language ) ); ?></th><td><input type="number" name="sample" min="1" max="10" value="5"></td></tr>
            </table>
            <?php submit_button( self::label( 'run', $language ) ); ?>
        </form>
        <script>
        (function(){
            const team=document.getElementById('tt-unit-team');
            function filter(){
                ['tt-unit-a','tt-unit-b'].forEach(function(id,selectIndex){
                    const select=document.getElementById(id); const visible=[];
                    Array.from(select.options).forEach(function(option){
                        const show=option.dataset.team===team.value;
                        option.hidden=!show; option.disabled=!show;
                        if(show){visible.push(option);}
                    });
                    if(!select.value || !select.selectedOptions.length || select.selectedOptions[0].disabled){
                        const option=visible[selectIndex]||visible[0]; if(option){option.selected=true;}
                    }
                });
            }
            team.addEventListener('change',filter); filter();
        })();
        </script>
        <?php
    }

    private static function result( array $profile, string $language ): void {
        $coverage = $profile['coverage'] ?? array();
        $signals  = $profile['signals'] ?? array();
        $per90    = $profile['per_90_shared'] ?? array();
        ?>
        <hr style="margin:32px 0;">
        <div style="max-width:1000px;">
            <h2><?php echo esc_html( (string) ( $profile['player_a_name'] ?? '' ) . ' ↔ ' . (string) ( $profile['player_b_name'] ?? '' ) ); ?></h2>
            <p><?php echo esc_html( self::label( 'boundary', $language ) ); ?></p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin:18px 0;">
                <?php self::card( self::label( 'shared', $language ), ( $coverage['shared_minutes'] ?? 0 ) . ' min', strtoupper( (string) ( $coverage['confidence'] ?? 'low' ) ) ); ?>
                <?php self::card( self::label( 'passes', $language ), (string) ( $per90['completed_passes'] ?? 0 ), self::label( 'per90', $language ) ); ?>
                <?php self::card( self::label( 'progression', $language ), (string) ( $signals['progressive_link'] ?? 0 ), self::label( 'signal', $language ) ); ?>
                <?php self::card( self::label( 'chance', $language ), (string) ( $signals['chance_chain'] ?? 0 ), self::label( 'signal', $language ) ); ?>
                <?php self::card( self::label( 'defensive', $language ), (string) ( $signals['defensive_sequence'] ?? 0 ), self::label( 'per90', $language ) ); ?>
                <?php self::card( self::label( 'balance', $language ), ( $profile['direction_balance_pct'] ?? 0 ) . '%', self::label( 'direction', $language ) ); ?>
            </div>

            <h3><?php echo esc_html( self::label( 'detail', $language ) ); ?></h3>
            <table class="widefat striped"><thead><tr><th><?php echo esc_html( self::label( 'metric', $language ) ); ?></th><th><?php echo esc_html( self::label( 'total', $language ) ); ?></th><th><?php echo esc_html( self::label( 'per90', $language ) ); ?></th></tr></thead><tbody>
            <?php foreach ( self::metrics( $language ) as $key => $label ) : ?><tr><td><?php echo esc_html( $label ); ?></td><td><?php echo esc_html( (string) ( $profile['totals'][ $key ] ?? 0 ) ); ?></td><td><?php echo esc_html( (string) ( $per90[ $key ] ?? 0 ) ); ?></td></tr><?php endforeach; ?>
            </tbody></table>

            <details style="margin-top:22px;"><summary><strong><?php echo esc_html( self::label( 'technical', $language ) ); ?></strong></summary><pre style="white-space:pre-wrap;background:#fff;padding:16px;border:1px solid #dcdcde;"><?php echo esc_html( wp_json_encode( $profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre></details>
        </div>
        <?php
    }

    private static function team_select( array $teams ): void {
        ?><select name="team_id" id="tt-unit-team" required><?php foreach ( $teams as $team ) : ?><option value="<?php echo esc_attr( $team['source_team_id'] ); ?>"><?php echo esc_html( $team['team_name'] ); ?></option><?php endforeach; ?></select><?php
    }

    private static function player_select( string $name, string $id, array $rows, int $default_index ): void {
        ?><select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" required style="min-width:300px;"><?php foreach ( $rows as $index => $row ) : ?><option value="<?php echo esc_attr( $row['source_player_id'] ); ?>" data-team="<?php echo esc_attr( $row['team_source_id'] ); ?>" <?php selected( $index, $default_index ); ?>><?php echo esc_html( $row['player_name'] . ( $row['last_position_name'] ? ' — ' . $row['last_position_name'] : '' ) ); ?></option><?php endforeach; ?></select><?php
    }

    private static function card( string $title, string $value, string $meta ): void {
        ?><div style="background:#fff;border:1px solid #dcdcde;padding:16px;"><div style="font-size:12px;text-transform:uppercase;color:#646970;"><?php echo esc_html( $title ); ?></div><div style="font-size:22px;font-weight:700;margin-top:4px;"><?php echo esc_html( $value ); ?></div><div style="margin-top:4px;color:#646970;"><?php echo esc_html( $meta ); ?></div></div><?php
    }

    private static function teams(): array {
        global $wpdb;
        $table = TactiTwin_DB::table( 'teams' );
        return $wpdb->get_results( $wpdb->prepare( "SELECT source_team_id, team_name FROM {$table} WHERE source = %s ORDER BY team_name ASC", TactiTwin_StatsBomb_Normalizer::SOURCE ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function team_roster(): array {
        global $wpdb;
        $players = TactiTwin_DB::table( 'players' );
        $events  = TactiTwin_DB::table( 'events' );
        $source  = TactiTwin_StatsBomb_Normalizer::SOURCE;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT e.team_source_id, p.source_player_id, p.player_name, p.last_position_name
                 FROM {$events} e
                 INNER JOIN {$players} p
                    ON p.source = e.source AND p.source_player_id = e.player_source_id
                 WHERE e.source = %s AND e.player_source_id <> ''
                 ORDER BY e.team_source_id, p.player_name ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $source
            ),
            ARRAY_A
        ) ?: array();
    }

    private static function metrics( string $language ): array {
        return 'es' === $language ? array(
            'completed_passes'=>'Pases completados entre ambos','progressive_passes'=>'Pases progresivos','final_third_entries'=>'Entradas al último tercio','box_entries'=>'Entradas al área','shot_assists'=>'Asistencias de tiro','goal_assists'=>'Asistencias de gol','joint_possessions'=>'Posesiones con participación de ambos','joint_shot_possessions'=>'Posesiones conjuntas terminadas en tiro','joint_possession_xg'=>'xG de posesiones conjuntas','pressure_recovery_sequences'=>'Secuencias presión→recuperación',
        ) : array(
            'completed_passes'=>'Completed passes between pair','progressive_passes'=>'Progressive passes','final_third_entries'=>'Final-third entries','box_entries'=>'Box entries','shot_assists'=>'Shot assists','goal_assists'=>'Goal assists','joint_possessions'=>'Possessions involving both players','joint_shot_possessions'=>'Joint possessions ending in shot','joint_possession_xg'=>'Joint-possession xG','pressure_recovery_sequences'=>'Pressure→recovery sequences',
        );
    }

    private static function label( string $key, string $language ): string {
        $labels = array(
            'en'=>array('title'=>'TactiTwin Unit Lab','intro'=>'Inspect observed interactions between two players while they shared the pitch. This is evidence of past event connections, not a chemistry score.','need_data'=>'Import event data before using Unit Lab.','language'=>'Language','team'=>'Team','player_a'=>'Player A','player_b'=>'Player B','sample'=>'Recent matches','run'=>'Analyze pair','boundary'=>'Metrics are normalized by shared minutes. Off-ball coordination still requires tracking or video.','shared'=>'Shared sample','passes'=>'Direct passes','progression'=>'Progressive link','chance'=>'Chance chain','defensive'=>'Pressure→recovery','balance'=>'Pass direction balance','per90'=>'per 90 shared min','signal'=>'interaction signal','direction'=>'two-way balance','detail'=>'Observed interaction detail','metric'=>'Metric','total'=>'Total','technical'=>'Technical evidence'),
            'es'=>array('title'=>'TactiTwin Unit Lab','intro'=>'Analiza las interacciones observadas entre dos jugadores mientras compartieron campo. Es evidencia de conexiones históricas de eventos, no una puntuación de química.','need_data'=>'Importa datos de eventos antes de usar Unit Lab.','language'=>'Idioma','team'=>'Equipo','player_a'=>'Jugador A','player_b'=>'Jugador B','sample'=>'Partidos recientes','run'=>'Analizar pareja','boundary'=>'Las métricas se normalizan por minutos compartidos. La coordinación sin balón sigue requiriendo tracking o vídeo.','shared'=>'Muestra compartida','passes'=>'Pases directos','progression'=>'Conexión progresiva','chance'=>'Cadena de ocasión','defensive'=>'Presión→recuperación','balance'=>'Equilibrio direccional','per90'=>'por 90 min compartidos','signal'=>'señal de interacción','direction'=>'equilibrio de ida/vuelta','detail'=>'Detalle de interacción observada','metric'=>'Métrica','total'=>'Total','technical'=>'Evidencia técnica'),
        );
        return $labels[ $language ][ $key ] ?? $labels['en'][ $key ] ?? $key;
    }
}
