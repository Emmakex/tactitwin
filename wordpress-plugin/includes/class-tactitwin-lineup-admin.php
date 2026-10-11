<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Lineup_Admin {
    public static function register(): void {
        add_action( 'admin_menu', array( self::class, 'menu' ) );
        add_action( 'admin_post_tactitwin_run_lineup_what_if', array( self::class, 'run' ) );
    }

    public static function menu(): void {
        add_submenu_page(
            'tactitwin',
            __( 'Lineup Lab', 'tactitwin' ),
            __( 'Lineup Lab', 'tactitwin' ),
            'manage_options',
            'tactitwin-lineup-lab',
            array( self::class, 'render' )
        );
    }

    public static function run(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage TactiTwin.', 'tactitwin' ) );
        }
        check_admin_referer( 'tactitwin_run_lineup_what_if' );

        $team_id          = absint( $_POST['team_id'] ?? 0 );
        $opponent_id      = absint( $_POST['opponent_id'] ?? 0 );
        $baseline_ids     = array_map( 'absint', (array) ( $_POST['baseline_player_ids'] ?? array() ) );
        $candidate_ids    = array_map( 'absint', (array) ( $_POST['candidate_player_ids'] ?? array() ) );
        $baseline_formation = sanitize_text_field( (string) ( $_POST['baseline_formation'] ?? '4-3-3' ) );
        $candidate_formation = sanitize_text_field( (string) ( $_POST['candidate_formation'] ?? '4-2-3-1' ) );
        $minute           = max( 1, min( 120, absint( $_POST['minute'] ?? 60 ) ) );
        $our_score        = min( 20, absint( $_POST['our_score'] ?? 0 ) );
        $opponent_score   = min( 20, absint( $_POST['opponent_score'] ?? 0 ) );
        $our_players      = max( 7, min( 11, absint( $_POST['our_players'] ?? 11 ) ) );
        $opponent_players = max( 7, min( 11, absint( $_POST['opponent_players'] ?? 11 ) ) );
        $sample           = max( 1, min( 10, absint( $_POST['sample'] ?? 5 ) ) );
        $language         = TactiTwin_I18n::normalize_language( sanitize_key( $_POST['language'] ?? TactiTwin_I18n::current_language() ) );

        try {
            ( new TactiTwin_Data_Preparation_Service() )->prepare_matchup( $team_id, $opponent_id, $sample );
            $result = ( new TactiTwin_Lineup_Service() )->lineup_what_if(
                $team_id,
                $opponent_id,
                $baseline_ids,
                $candidate_ids,
                $baseline_formation,
                $candidate_formation,
                $minute,
                $our_score,
                $opponent_score,
                $our_players,
                $opponent_players,
                $sample,
                $language
            );
            set_transient(
                'tactitwin_lineup_' . get_current_user_id(),
                array( 'result' => $result, 'language' => $language ),
                10 * MINUTE_IN_SECONDS
            );
        } catch ( Throwable $e ) {
            set_transient( 'tactitwin_lineup_error_' . get_current_user_id(), $e->getMessage(), MINUTE_IN_SECONDS );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=tactitwin-lineup-lab' ) );
        exit;
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $teams    = self::teams();
        $players  = self::players();
        $user     = get_current_user_id();
        $stored   = get_transient( 'tactitwin_lineup_' . $user );
        $error    = get_transient( 'tactitwin_lineup_error_' . $user );
        $language = is_array( $stored ) ? TactiTwin_I18n::normalize_language( (string) ( $stored['language'] ?? 'en' ) ) : TactiTwin_I18n::current_language();
        if ( $error ) {
            delete_transient( 'tactitwin_lineup_error_' . $user );
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( self::label( 'title', $language ) ); ?></h1>
            <p><?php echo esc_html( self::label( 'intro', $language ) ); ?></p>
            <?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>
            <?php if ( count( $teams ) >= 2 && count( $players ) >= 11 ) : ?>
                <?php self::form( $teams, $players, $language ); ?>
            <?php else : ?>
                <div class="notice notice-warning inline"><p><?php echo esc_html( self::label( 'need_data', $language ) ); ?></p></div>
            <?php endif; ?>
            <?php if ( is_array( $stored ) && ! empty( $stored['result'] ) ) : self::result( $stored['result'] ); endif; ?>
        </div>
        <?php
    }

    private static function form( array $teams, array $players, string $language ): void {
        $formation_roles = array();
        foreach ( TactiTwin_Formation_Model::supported() as $formation ) {
            $formation_roles[ $formation ] = TactiTwin_Formation_Model::template( $formation )['roles'];
        }
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="tt-lineup-form" style="background:#fff;padding:20px;max-width:1180px;border:1px solid #dcdcde;">
            <input type="hidden" name="action" value="tactitwin_run_lineup_what_if">
            <?php wp_nonce_field( 'tactitwin_run_lineup_what_if' ); ?>
            <table class="form-table" role="presentation">
                <tr><th><?php echo esc_html( self::label( 'language', $language ) ); ?></th><td><select name="language"><option value="es" <?php selected( $language, 'es' ); ?>>Español</option><option value="en" <?php selected( $language, 'en' ); ?>>English</option></select></td></tr>
                <tr><th><?php echo esc_html( self::label( 'team', $language ) ); ?></th><td><?php self::team_select( 'team_id', 'tt-lineup-team', $teams ); ?> <button type="button" class="button" id="tt-load-recent"><?php echo esc_html( self::label( 'load_recent', $language ) ); ?></button> <span id="tt-recent-status"></span></td></tr>
                <tr><th><?php echo esc_html( self::label( 'opponent', $language ) ); ?></th><td><?php self::team_select( 'opponent_id', 'tt-lineup-opponent', $teams, 1 ); ?></td></tr>
                <tr><th><?php echo esc_html( self::label( 'score', $language ) ); ?></th><td><input type="number" name="our_score" min="0" max="20" value="0" style="width:70px"> - <input type="number" name="opponent_score" min="0" max="20" value="0" style="width:70px"> &nbsp; <?php echo esc_html( self::label( 'players', $language ) ); ?> <input type="number" name="our_players" min="7" max="11" value="11" style="width:65px"> v <input type="number" name="opponent_players" min="7" max="11" value="11" style="width:65px"></td></tr>
                <tr><th><?php echo esc_html( self::label( 'minute', $language ) ); ?></th><td><input type="number" name="minute" min="1" max="120" value="60"> &nbsp; <?php echo esc_html( self::label( 'sample', $language ) ); ?> <input type="number" name="sample" min="1" max="10" value="5"></td></tr>
            </table>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(430px,1fr));gap:20px;align-items:start;">
                <?php self::lineup_panel( 'baseline', '4-3-3', $players, $language ); ?>
                <?php self::lineup_panel( 'candidate', '4-2-3-1', $players, $language ); ?>
            </div>
            <p><button type="button" class="button" id="tt-copy-xi"><?php echo esc_html( self::label( 'copy_xi', $language ) ); ?></button></p>
            <?php submit_button( self::label( 'run', $language ) ); ?>
        </form>
        <script>
        (function(){
            const roles=<?php echo wp_json_encode( $formation_roles ); ?>;
            const team=document.getElementById('tt-lineup-team');
            const nonce=<?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
            const recentUrl=<?php echo wp_json_encode( rest_url( 'tactitwin/v1/planning/recent-xi' ) ); ?>;
            function selects(kind){ return Array.from(document.querySelectorAll('select[data-lineup="'+kind+'"]')); }
            function refreshRoleLabels(kind){
                const formation=document.getElementById('tt-'+kind+'-formation').value;
                const list=roles[formation]||[];
                document.querySelectorAll('[data-role-label="'+kind+'"]').forEach(function(el,i){ el.textContent=list[i]||('Slot '+(i+1)); });
            }
            function filterPlayers(){
                const id=team.value;
                ['baseline','candidate'].forEach(function(kind){
                    selects(kind).forEach(function(select,index){
                        let visible=[];
                        Array.from(select.options).forEach(function(option){
                            const show=option.dataset.team===id;
                            option.hidden=!show; option.disabled=!show;
                            if(show){ visible.push(option); }
                        });
                        if(!select.value || select.selectedOptions.length===0 || select.selectedOptions[0].disabled){
                            if(visible[index]||visible[0]){ (visible[index]||visible[0]).selected=true; }
                        }
                    });
                });
            }
            function copy(){
                const from=selects('baseline'), to=selects('candidate');
                from.forEach(function(select,i){ if(to[i]){ to[i].value=select.value; } });
            }
            document.getElementById('tt-copy-xi').addEventListener('click',copy);
            document.getElementById('tt-baseline-formation').addEventListener('change',function(){refreshRoleLabels('baseline');});
            document.getElementById('tt-candidate-formation').addEventListener('change',function(){refreshRoleLabels('candidate');});
            team.addEventListener('change',filterPlayers);
            document.getElementById('tt-load-recent').addEventListener('click',async function(){
                const status=document.getElementById('tt-recent-status'); status.textContent='…';
                try{
                    const response=await fetch(recentUrl+'?team_id='+encodeURIComponent(team.value),{headers:{'X-WP-Nonce':nonce}});
                    const body=await response.json();
                    if(!response.ok || !body.ok){ throw new Error(body.message||'Unable to load recent XI'); }
                    const ids=body.result.players.map(function(p){return String(p.player_source_id);});
                    const base=selects('baseline');
                    ids.forEach(function(id,i){ if(base[i]){ base[i].value=id; } });
                    if(body.result.formation && roles[body.result.formation]){ document.getElementById('tt-baseline-formation').value=body.result.formation; refreshRoleLabels('baseline'); }
                    copy(); status.textContent=<?php echo wp_json_encode( self::label( 'recent_loaded', $language ) ); ?>;
                }catch(e){ status.textContent=e.message; }
            });
            filterPlayers(); refreshRoleLabels('baseline'); refreshRoleLabels('candidate');
        })();
        </script>
        <?php
    }

    private static function lineup_panel( string $kind, string $formation, array $players, string $language ): void {
        $title = 'baseline' === $kind ? self::label( 'baseline', $language ) : self::label( 'candidate', $language );
        ?>
        <section style="border:1px solid #dcdcde;padding:16px;background:#f6f7f7;">
            <h2 style="margin-top:0;"><?php echo esc_html( $title ); ?></h2>
            <p><label><strong><?php echo esc_html( self::label( 'formation', $language ) ); ?></strong> <?php self::formation_select( $kind . '_formation', 'tt-' . $kind . '-formation', $formation ); ?></label></p>
            <div style="display:grid;grid-template-columns:90px 1fr;gap:8px;align-items:center;">
            <?php for ( $i = 0; $i < 11; ++$i ) : ?>
                <strong data-role-label="<?php echo esc_attr( $kind ); ?>"><?php echo esc_html( 'Slot ' . ( $i + 1 ) ); ?></strong>
                <?php self::player_select( $kind . '_player_ids[]', $players, $kind, $i ); ?>
            <?php endfor; ?>
            </div>
        </section>
        <?php
    }

    private static function result( array $result ): void {
        $language = TactiTwin_I18n::normalize_language( (string) ( $result['language'] ?? 'en' ) );
        ?>
        <hr style="margin:32px 0;">
        <div style="max-width:1180px;">
            <h2><?php echo esc_html( (string) ( $result['baseline']['formation'] ?? '-' ) . ' → ' . (string) ( $result['candidate']['formation'] ?? '-' ) ); ?></h2>
            <?php if ( ! empty( $result['coverage']['warning'] ) ) : ?><div class="notice notice-warning inline"><p><?php echo esc_html( $result['coverage']['warning'] ); ?></p></div><?php endif; ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:18px 0;">
                <?php self::card( self::label( 'baseline_fit', $language ), ( $result['baseline']['profile']['role_fit']['average'] ?? 0 ) . '/100', self::label( 'role_fit', $language ) ); ?>
                <?php self::card( self::label( 'candidate_fit', $language ), ( $result['candidate']['profile']['role_fit']['average'] ?? 0 ) . '/100', self::label( 'role_fit', $language ) ); ?>
                <?php self::card( self::label( 'changes', $language ), (string) ( $result['changes']['personnel_changes'] ?? 0 ), self::label( 'personnel_changes', $language ) ); ?>
                <?php self::card( self::label( 'coverage', $language ), ( $result['coverage']['average_player_minutes'] ?? 0 ) . ' min', (string) ( $result['coverage']['status_label'] ?? '' ) ); ?>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:20px;">
                <div><h3><?php echo esc_html( self::label( 'profile_delta', $language ) ); ?></h3><?php self::delta_table( $result['delta']['dimensions'] ?? array(), $language, true ); ?></div>
                <div><h3><?php echo esc_html( self::label( 'structure_delta', $language ) ); ?></h3><?php self::delta_table( $result['delta']['structure'] ?? array(), $language, false ); ?></div>
            </div>

            <h3 style="margin-top:24px;"><?php echo esc_html( self::label( 'recommendations', $language ) ); ?></h3>
            <ol style="background:#fff;border:1px solid #dcdcde;padding:18px 18px 18px 38px;"><?php foreach ( $result['recommendations'] ?? array() as $item ) : ?><li style="margin-bottom:8px;"><?php echo esc_html( (string) $item ); ?></li><?php endforeach; ?></ol>

            <?php if ( ! empty( $result['changes']['role_changes'] ) ) : ?>
                <h3><?php echo esc_html( self::label( 'role_changes', $language ) ); ?></h3>
                <table class="widefat striped"><thead><tr><th><?php echo esc_html( self::label( 'player', $language ) ); ?></th><th><?php echo esc_html( self::label( 'from', $language ) ); ?></th><th><?php echo esc_html( self::label( 'to', $language ) ); ?></th></tr></thead><tbody><?php foreach ( $result['changes']['role_changes'] as $change ) : ?><tr><td><?php echo esc_html( $change['player_name'] ); ?></td><td><?php echo esc_html( $change['from_role'] ); ?></td><td><?php echo esc_html( $change['to_role'] ); ?></td></tr><?php endforeach; ?></tbody></table>
            <?php endif; ?>

            <details style="margin-top:22px;"><summary><strong><?php echo esc_html( self::label( 'technical', $language ) ); ?></strong></summary><pre style="white-space:pre-wrap;background:#fff;padding:16px;border:1px solid #dcdcde;"><?php echo esc_html( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre></details>
        </div>
        <?php
    }

    private static function delta_table( array $values, string $language, bool $translate ): void {
        ?><table class="widefat striped"><thead><tr><th><?php echo esc_html( self::label( 'dimension', $language ) ); ?></th><th><?php echo esc_html( self::label( 'delta', $language ) ); ?></th></tr></thead><tbody><?php foreach ( $values as $key => $value ) : ?><tr><td><?php echo esc_html( $translate ? self::dimension( (string) $key, $language ) : str_replace( '_', ' ', (string) $key ) ); ?></td><td><?php echo esc_html( ( (float) $value >= 0 ? '+' : '' ) . (string) $value ); ?></td></tr><?php endforeach; ?></tbody></table><?php
    }

    private static function formation_select( string $name, string $id, string $default ): void {
        ?><select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>"><?php foreach ( TactiTwin_Formation_Model::supported() as $formation ) : ?><option value="<?php echo esc_attr( $formation ); ?>" <?php selected( $formation, $default ); ?>><?php echo esc_html( $formation ); ?></option><?php endforeach; ?></select><?php
    }

    private static function team_select( string $name, string $id, array $teams, int $default = 0 ): void {
        ?><select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" required><?php foreach ( $teams as $index => $team ) : ?><option value="<?php echo esc_attr( $team['source_team_id'] ); ?>" <?php selected( $index, $default ); ?>><?php echo esc_html( $team['team_name'] ); ?></option><?php endforeach; ?></select><?php
    }

    private static function player_select( string $name, array $players, string $kind, int $default_index ): void {
        ?><select name="<?php echo esc_attr( $name ); ?>" data-lineup="<?php echo esc_attr( $kind ); ?>" required style="min-width:260px;"><?php foreach ( $players as $player ) : ?><option value="<?php echo esc_attr( $player['source_player_id'] ); ?>" data-team="<?php echo esc_attr( $player['last_team_source_id'] ); ?>"><?php echo esc_html( $player['player_name'] . ( $player['last_position_name'] ? ' — ' . $player['last_position_name'] : '' ) ); ?></option><?php endforeach; ?></select><?php
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
        return $wpdb->get_results( $wpdb->prepare( "SELECT source_player_id, player_name, last_team_source_id, last_position_name FROM {$table} WHERE source = %s ORDER BY last_team_source_id, player_name ASC", TactiTwin_StatsBomb_Normalizer::SOURCE ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function dimension( string $key, string $language ): string {
        $map = array(
            'en' => array( 'progression'=>'Progression','ball_security'=>'Ball security','attacking_threat'=>'Attacking threat','pressing_activity'=>'Pressing activity','defensive_activity'=>'Defensive activity' ),
            'es' => array( 'progression'=>'Progresión','ball_security'=>'Seguridad con balón','attacking_threat'=>'Amenaza ofensiva','pressing_activity'=>'Actividad de presión','defensive_activity'=>'Actividad defensiva' ),
        );
        return $map[ $language ][ $key ] ?? str_replace( '_', ' ', $key );
    }

    private static function label( string $key, string $language ): string {
        $labels = array(
            'en' => array(
                'title'=>'TactiTwin Lineup Lab','intro'=>'Compare two complete XIs and nominal formations inside the current opponent, score and manpower context. Player-profile evidence and structural formation rules are shown separately.',
                'need_data'=>'Import event data with teams and players before using Lineup Lab.','language'=>'Language','team'=>'Our team','opponent'=>'Opponent','load_recent'=>'Load latest XI','recent_loaded'=>'Latest XI loaded','score'=>'Hypothetical score','players'=>'Players','minute'=>'Minute','sample'=>'Recent matches','baseline'=>'Baseline XI','candidate'=>'Candidate XI','formation'=>'Formation','copy_xi'=>'Copy baseline XI to candidate','run'=>'Compare XI variants','baseline_fit'=>'Baseline role fit','candidate_fit'=>'Candidate role fit','role_fit'=>'historical role fit','changes'=>'Changes','personnel_changes'=>'players in/out','coverage'=>'Profile coverage','profile_delta'=>'Player-profile delta','structure_delta'=>'Formation-structure delta','dimension'=>'Dimension','delta'=>'Delta','recommendations'=>'Tactical interpretation','role_changes'=>'Role changes','player'=>'Player','from'=>'From','to'=>'To','technical'=>'Technical evidence'
            ),
            'es' => array(
                'title'=>'TactiTwin Lineup Lab','intro'=>'Compara dos XI completos y sus formaciones nominales dentro del contexto actual de rival, marcador y superioridad numérica. La evidencia de perfiles y las reglas estructurales se muestran por separado.',
                'need_data'=>'Importa datos de eventos con equipos y jugadores antes de usar Lineup Lab.','language'=>'Idioma','team'=>'Nuestro equipo','opponent'=>'Rival','load_recent'=>'Cargar último XI','recent_loaded'=>'Último XI cargado','score'=>'Marcador hipotético','players'=>'Jugadores','minute'=>'Minuto','sample'=>'Partidos recientes','baseline'=>'XI base','candidate'=>'XI variante','formation'=>'Formación','copy_xi'=>'Copiar XI base a variante','run'=>'Comparar variantes','baseline_fit'=>'Encaje XI base','candidate_fit'=>'Encaje XI variante','role_fit'=>'encaje histórico de rol','changes'=>'Cambios','personnel_changes'=>'jugadores entran/salen','coverage'=>'Cobertura de perfiles','profile_delta'=>'Delta de perfiles','structure_delta'=>'Delta estructural','dimension'=>'Dimensión','delta'=>'Delta','recommendations'=>'Interpretación táctica','role_changes'=>'Cambios de rol','player'=>'Jugador','from'=>'De','to'=>'A','technical'=>'Evidencia técnica'
            ),
        );
        return $labels[ $language ][ $key ] ?? $labels['en'][ $key ] ?? $key;
    }
}
