<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Live_Session_Admin {
    public static function register(): void {
        add_action( 'admin_menu', array( self::class, 'menu' ) );
        add_action( 'admin_post_tactitwin_update_live_session', array( self::class, 'update' ) );
        add_action( 'admin_post_tactitwin_close_live_session', array( self::class, 'close' ) );
    }

    public static function menu(): void {
        add_submenu_page(
            'tactitwin',
            __( 'Live Sessions', 'tactitwin' ),
            __( 'Live Sessions', 'tactitwin' ),
            'manage_options',
            'tactitwin-live-sessions',
            array( self::class, 'render' )
        );
    }

    public static function update(): void {
        self::guard( 'tactitwin_update_live_session' );

        $team_id          = absint( $_POST['team_id'] ?? 0 );
        $opponent_id      = absint( $_POST['opponent_id'] ?? 0 );
        $our_score        = min( 20, absint( $_POST['our_score'] ?? 0 ) );
        $opponent_score   = min( 20, absint( $_POST['opponent_score'] ?? 0 ) );
        $our_players      = max( 7, min( 11, absint( $_POST['our_players'] ?? 11 ) ) );
        $opponent_players = max( 7, min( 11, absint( $_POST['opponent_players'] ?? 11 ) ) );
        $minute           = max( 1, min( 120, absint( $_POST['minute'] ?? 1 ) ) );
        $sample           = max( 1, min( 10, absint( $_POST['sample'] ?? 5 ) ) );
        $language         = TactiTwin_I18n::normalize_language( sanitize_key( $_POST['language'] ?? TactiTwin_I18n::current_language() ) );
        $session_uuid     = sanitize_text_field( wp_unslash( (string) ( $_POST['session_uuid'] ?? '' ) ) );
        $source_match_id  = sanitize_text_field( wp_unslash( (string) ( $_POST['source_match_id'] ?? '' ) ) );
        $session_title    = sanitize_text_field( wp_unslash( (string) ( $_POST['session_title'] ?? '' ) ) );
        $observations = array(
            'high_press' => ! empty( $_POST['observed_high_press'] ),
            'low_block' => ! empty( $_POST['observed_low_block'] ),
            'transition_threat' => ! empty( $_POST['observed_transition_threat'] ),
            'side_bias' => ! empty( $_POST['observed_side_bias'] ),
            'weak_side_open' => ! empty( $_POST['observed_weak_side_open'] ),
            'note' => sanitize_text_field( wp_unslash( (string) ( $_POST['observation_note'] ?? '' ) ) ),
        );

        try {
            ( new TactiTwin_Data_Preparation_Service() )->prepare_matchup( $team_id, $opponent_id, $sample );
            $playbook = ( new TactiTwin_Profile_Service() )->live_contingency_playbook(
                $team_id,
                $opponent_id,
                $our_score,
                $opponent_score,
                $minute,
                $observations,
                $sample,
                $language,
                $our_players,
                $opponent_players
            );

            $service = new TactiTwin_Live_Session_Service();
            $session = $service->ensure_session(
                $session_uuid,
                $team_id,
                $opponent_id,
                $language,
                get_current_user_id(),
                $source_match_id,
                $session_title
            );
            $persisted = $service->append_snapshot( (string) $session['session_uuid'], $playbook, get_current_user_id() );

            set_transient(
                'tactitwin_live_session_result_' . get_current_user_id(),
                array(
                    'playbook'=>$playbook,
                    'snapshot'=>$persisted['snapshot'],
                    'language'=>$language,
                    'sample'=>$sample,
                ),
                10 * MINUTE_IN_SECONDS
            );
            self::redirect( (string) $session['session_uuid'] );
        } catch ( Throwable $e ) {
            set_transient( 'tactitwin_live_session_error_' . get_current_user_id(), $e->getMessage(), MINUTE_IN_SECONDS );
            self::redirect( $session_uuid );
        }
    }

    public static function close(): void {
        self::guard( 'tactitwin_close_live_session' );
        $session_uuid = sanitize_text_field( wp_unslash( (string) ( $_POST['session_uuid'] ?? '' ) ) );
        try {
            ( new TactiTwin_Live_Session_Service() )->close_session( $session_uuid );
        } catch ( Throwable $e ) {
            set_transient( 'tactitwin_live_session_error_' . get_current_user_id(), $e->getMessage(), MINUTE_IN_SECONDS );
        }
        self::redirect( $session_uuid );
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $user = get_current_user_id();
        $teams = self::teams();
        $service = new TactiTwin_Live_Session_Service();
        $session_uuid = sanitize_text_field( wp_unslash( (string) ( $_GET['session_uuid'] ?? '' ) ) );
        $timeline = null;
        if ( '' !== $session_uuid ) {
            try {
                $timeline = $service->timeline( $session_uuid );
            } catch ( Throwable $e ) {
                set_transient( 'tactitwin_live_session_error_' . $user, $e->getMessage(), MINUTE_IN_SECONDS );
                $session_uuid = '';
            }
        }
        $result = get_transient( 'tactitwin_live_session_result_' . $user );
        $error = get_transient( 'tactitwin_live_session_error_' . $user );
        if ( $error ) {
            delete_transient( 'tactitwin_live_session_error_' . $user );
        }
        $language = is_array( $result ) && ! empty( $result['language'] )
            ? TactiTwin_I18n::normalize_language( (string) $result['language'] )
            : ( is_array( $timeline ) ? TactiTwin_I18n::normalize_language( (string) ( $timeline['session']['language'] ?? 'en' ) ) : TactiTwin_I18n::current_language() );
        $recent = $service->recent_sessions( 15 );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( self::label( 'title', $language ) ); ?></h1>
            <p><?php echo esc_html( self::label( 'intro', $language ) ); ?></p>
            <?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>

            <p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=tactitwin-live-sessions' ) ); ?>"><?php echo esc_html( self::label( 'new_session', $language ) ); ?></a></p>

            <?php if ( count( $teams ) >= 2 ) : ?>
                <?php self::render_form( $teams, $timeline, $result, $language ); ?>
            <?php else : ?>
                <div class="notice notice-warning inline"><p><?php echo esc_html( self::label( 'need_teams', $language ) ); ?></p></div>
            <?php endif; ?>

            <?php if ( is_array( $timeline ) ) : self::render_timeline( $timeline, $language ); endif; ?>
            <?php if ( is_array( $result ) && ! empty( $result['playbook'] ) && is_array( $timeline ) ) : self::render_current( $result['playbook'], $language ); endif; ?>
            <?php self::render_recent( $recent, $language ); ?>
        </div>
        <?php
    }

    private static function render_form( array $teams, ?array $timeline, $result, string $language ): void {
        $session = is_array( $timeline ) ? ( $timeline['session'] ?? array() ) : array();
        $last = is_array( $timeline ) && ! empty( $timeline['snapshots'] ) ? end( $timeline['snapshots'] ) : array();
        $last_playbook = is_array( $last ) ? ( $last['playbook'] ?? array() ) : array();
        $last_observations = is_array( $last ) ? ( $last['observations'] ?? array() ) : array();
        $closed = 'closed' === (string) ( $session['status'] ?? '' );
        $team_id = (int) ( $session['team_source_id'] ?? 0 );
        $opponent_id = (int) ( $session['opponent_source_id'] ?? 0 );
        $sample = is_array( $result ) ? (int) ( $result['sample'] ?? 5 ) : 5;
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;padding:20px;max-width:1100px;border:1px solid #dcdcde;margin-bottom:20px;">
            <input type="hidden" name="action" value="tactitwin_update_live_session">
            <input type="hidden" name="session_uuid" value="<?php echo esc_attr( (string) ( $session['session_uuid'] ?? '' ) ); ?>">
            <?php wp_nonce_field( 'tactitwin_update_live_session' ); ?>
            <?php if ( ! empty( $session ) ) : ?><p><strong><?php echo esc_html( self::label( 'session', $language ) ); ?>:</strong> <code><?php echo esc_html( (string) $session['session_uuid'] ); ?></code> · <?php echo esc_html( strtoupper( (string) $session['status'] ) ); ?></p><?php endif; ?>
            <table class="form-table" role="presentation">
                <tr><th><?php echo esc_html( self::label( 'title_field', $language ) ); ?></th><td><input type="text" name="session_title" value="<?php echo esc_attr( (string) ( $session['title'] ?? '' ) ); ?>" style="width:100%;max-width:500px" <?php disabled( $closed ); ?>></td></tr>
                <tr><th><?php echo esc_html( self::label( 'source_match', $language ) ); ?></th><td><input type="text" name="source_match_id" value="<?php echo esc_attr( (string) ( $session['source_match_id'] ?? '' ) ); ?>" <?php disabled( $closed ); ?>></td></tr>
                <tr><th><?php echo esc_html( self::label( 'language', $language ) ); ?></th><td><select name="language" <?php disabled( $closed ); ?>><option value="es" <?php selected( $language, 'es' ); ?>>Español</option><option value="en" <?php selected( $language, 'en' ); ?>>English</option></select></td></tr>
                <tr><th><?php echo esc_html( self::label( 'team', $language ) ); ?></th><td><?php self::team_select( 'team_id', $teams, $team_id, 0, $closed || ! empty( $session ) ); ?></td></tr>
                <tr><th><?php echo esc_html( self::label( 'opponent', $language ) ); ?></th><td><?php self::team_select( 'opponent_id', $teams, $opponent_id, 1, $closed || ! empty( $session ) ); ?></td></tr>
                <tr><th><?php echo esc_html( self::label( 'score', $language ) ); ?></th><td><input type="number" name="our_score" min="0" max="20" value="<?php echo esc_attr( (string) ( $last['our_score'] ?? 0 ) ); ?>" style="width:70px" <?php disabled( $closed ); ?>> - <input type="number" name="opponent_score" min="0" max="20" value="<?php echo esc_attr( (string) ( $last['opponent_score'] ?? 0 ) ); ?>" style="width:70px" <?php disabled( $closed ); ?>></td></tr>
                <tr><th><?php echo esc_html( self::label( 'players', $language ) ); ?></th><td><input type="number" name="our_players" min="7" max="11" value="<?php echo esc_attr( (string) ( $last['our_players'] ?? 11 ) ); ?>" style="width:70px" <?php disabled( $closed ); ?>> v <input type="number" name="opponent_players" min="7" max="11" value="<?php echo esc_attr( (string) ( $last['opponent_players'] ?? 11 ) ); ?>" style="width:70px" <?php disabled( $closed ); ?>></td></tr>
                <tr><th><?php echo esc_html( self::label( 'minute', $language ) ); ?></th><td><input type="number" name="minute" min="1" max="120" value="<?php echo esc_attr( (string) ( max( 1, (int) ( $last['minute'] ?? 1 ) ) ) ); ?>" <?php disabled( $closed ); ?>></td></tr>
                <tr><th><?php echo esc_html( self::label( 'sample', $language ) ); ?></th><td><input type="number" name="sample" min="1" max="10" value="<?php echo esc_attr( (string) $sample ); ?>" <?php disabled( $closed ); ?>></td></tr>
            </table>
            <h2><?php echo esc_html( self::label( 'observations', $language ) ); ?></h2>
            <fieldset style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:10px;margin-bottom:14px;">
                <?php self::checkbox( 'observed_high_press', 'high_press', self::label( 'high_press', $language ), $last_observations, $closed ); ?>
                <?php self::checkbox( 'observed_low_block', 'low_block', self::label( 'low_block', $language ), $last_observations, $closed ); ?>
                <?php self::checkbox( 'observed_transition_threat', 'transition_threat', self::label( 'transition', $language ), $last_observations, $closed ); ?>
                <?php self::checkbox( 'observed_side_bias', 'side_bias', self::label( 'side_bias', $language ), $last_observations, $closed ); ?>
                <?php self::checkbox( 'observed_weak_side_open', 'weak_side_open', self::label( 'weak_side', $language ), $last_observations, $closed ); ?>
            </fieldset>
            <p><label><strong><?php echo esc_html( self::label( 'note', $language ) ); ?></strong><br><input type="text" name="observation_note" value="<?php echo esc_attr( (string) ( $last_observations['note'] ?? '' ) ); ?>" style="width:100%;max-width:800px" <?php disabled( $closed ); ?>></label></p>
            <?php if ( ! $closed ) : submit_button( self::label( empty( $session ) ? 'start' : 'save_snapshot', $language ) ); endif; ?>
        </form>
        <?php if ( ! empty( $session ) && ! $closed ) : ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:20px;">
                <input type="hidden" name="action" value="tactitwin_close_live_session"><input type="hidden" name="session_uuid" value="<?php echo esc_attr( (string) $session['session_uuid'] ); ?>">
                <?php wp_nonce_field( 'tactitwin_close_live_session' ); ?>
                <?php submit_button( self::label( 'close_session', $language ), 'secondary', 'submit', false ); ?>
            </form>
        <?php endif;
    }

    private static function render_timeline( array $timeline, string $language ): void {
        $summary = $timeline['summary'] ?? array();
        $snapshots = $timeline['snapshots'] ?? array();
        ?>
        <h2><?php echo esc_html( self::label( 'timeline', $language ) ); ?></h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;max-width:1100px;margin-bottom:16px;">
            <?php self::card( self::label( 'snapshots', $language ), (string) ( $summary['snapshot_count'] ?? 0 ), self::label( 'saved_updates', $language ) ); ?>
            <?php self::card( self::label( 'current_plan', $language ), (string) ( $summary['current_plan'] ?? '-' ), (string) ( $summary['current_mode'] ?? '' ) ); ?>
            <?php self::card( self::label( 'transitions', $language ), (string) count( $summary['transitions'] ?? array() ), self::label( 'plan_changes', $language ) ); ?>
            <?php self::card( self::label( 'last_minute', $language ), (string) ( $summary['last_minute'] ?? '-' ), self::label( 'last_snapshot', $language ) ); ?>
        </div>
        <table class="widefat striped" style="max-width:1100px;"><thead><tr><th>#</th><th><?php echo esc_html( self::label( 'minute', $language ) ); ?></th><th><?php echo esc_html( self::label( 'score', $language ) ); ?></th><th><?php echo esc_html( self::label( 'players', $language ) ); ?></th><th><?php echo esc_html( self::label( 'plan', $language ) ); ?></th><th><?php echo esc_html( self::label( 'scenario', $language ) ); ?></th><th><?php echo esc_html( self::label( 'note', $language ) ); ?></th></tr></thead><tbody>
        <?php foreach ( $snapshots as $row ) : ?>
            <tr><td><?php echo esc_html( (string) $row['id'] ); ?></td><td><?php echo esc_html( (string) $row['minute'] ); ?>'</td><td><?php echo esc_html( (string) $row['our_score'] . '-' . (string) $row['opponent_score'] ); ?></td><td><?php echo esc_html( (string) $row['our_players'] . 'v' . (string) $row['opponent_players'] ); ?></td><td><strong><?php echo esc_html( (string) $row['active_plan'] ); ?></strong> · <?php echo esc_html( (string) $row['active_mode'] ); ?></td><td><?php echo esc_html( (string) $row['selected_scenario'] ); ?></td><td><?php echo esc_html( (string) $row['note'] ); ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
        <?php if ( ! empty( $summary['transitions'] ) ) : ?>
            <h3><?php echo esc_html( self::label( 'transitions', $language ) ); ?></h3><ul style="max-width:1100px;background:#fff;border:1px solid #dcdcde;padding:16px 16px 16px 36px;">
            <?php foreach ( $summary['transitions'] as $transition ) : ?><li><?php echo esc_html( sprintf( "%d' · %s/%s → %s/%s", $transition['minute'], $transition['from_plan'], $transition['from_mode'], $transition['to_plan'], $transition['to_mode'] ) ); ?></li><?php endforeach; ?>
            </ul>
        <?php endif;
    }

    private static function render_current( array $playbook, string $language ): void {
        $active = $playbook['active_plan'] ?? array();
        ?><h2><?php echo esc_html( self::label( 'current_decision', $language ) ); ?></h2><div style="max-width:1100px;background:#fff;border-left:4px solid #2271b1;padding:16px;"><strong><?php echo esc_html( (string) ( $active['label'] ?? $active['id'] ?? '' ) ); ?></strong><br><?php echo esc_html( (string) ( $active['reason'] ?? '' ) ); ?><ol><?php foreach ( array_slice( $playbook['recommendations'] ?? array(), 0, 6 ) as $item ) : ?><li><?php echo esc_html( (string) $item ); ?></li><?php endforeach; ?></ol></div><?php
    }

    private static function render_recent( array $sessions, string $language ): void {
        ?><h2><?php echo esc_html( self::label( 'recent', $language ) ); ?></h2><table class="widefat striped" style="max-width:1100px;"><thead><tr><th><?php echo esc_html( self::label( 'session', $language ) ); ?></th><th><?php echo esc_html( self::label( 'teams', $language ) ); ?></th><th><?php echo esc_html( self::label( 'status', $language ) ); ?></th><th><?php echo esc_html( self::label( 'updated', $language ) ); ?></th></tr></thead><tbody>
        <?php foreach ( $sessions as $session ) : $url = add_query_arg( array('page'=>'tactitwin-live-sessions','session_uuid'=>$session['session_uuid']), admin_url('admin.php') ); ?><tr><td><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( (string) ( $session['title'] ?: $session['session_uuid'] ) ); ?></a></td><td><?php echo esc_html( (string) $session['team_source_id'] . ' vs ' . (string) $session['opponent_source_id'] ); ?></td><td><?php echo esc_html( strtoupper( (string) $session['status'] ) ); ?></td><td><?php echo esc_html( (string) $session['updated_at'] ); ?></td></tr><?php endforeach; ?>
        </tbody></table><?php
    }

    private static function checkbox( string $field, string $key, string $label, array $observations, bool $disabled ): void {
        ?><label style="display:block;border:1px solid #dcdcde;padding:12px;background:#f6f7f7;"><input type="checkbox" name="<?php echo esc_attr( $field ); ?>" value="1" <?php checked( ! empty( $observations[$key] ) ); ?> <?php disabled( $disabled ); ?>> <?php echo esc_html( $label ); ?></label><?php
    }

    private static function team_select( string $name, array $teams, int $selected_id, int $default_index, bool $disabled ): void {
        ?><select name="<?php echo esc_attr( $name ); ?>" required <?php disabled( $disabled ); ?>><?php foreach ( $teams as $index=>$team ) : $id=(int)$team['source_team_id']; ?><option value="<?php echo esc_attr( (string)$id ); ?>" <?php selected( $selected_id > 0 ? $id === $selected_id : $index === $default_index ); ?>><?php echo esc_html( (string)$team['team_name'] ); ?></option><?php endforeach; ?></select><?php
        if ( $disabled && $selected_id > 0 ) {
            echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $selected_id ) . '">';
        }
    }

    private static function card( string $title, string $value, string $meta ): void {
        ?><div style="background:#fff;border:1px solid #dcdcde;padding:16px;"><div style="font-size:12px;text-transform:uppercase;color:#646970;"><?php echo esc_html($title); ?></div><div style="font-size:24px;font-weight:700;margin-top:4px;"><?php echo esc_html($value); ?></div><div style="color:#646970;margin-top:4px;"><?php echo esc_html($meta); ?></div></div><?php
    }

    private static function teams(): array {
        global $wpdb;
        $table = TactiTwin_DB::table( 'teams' );
        return $wpdb->get_results( $wpdb->prepare( "SELECT source_team_id, team_name FROM {$table} WHERE source = %s ORDER BY team_name ASC", TactiTwin_StatsBomb_Normalizer::SOURCE ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function guard( string $nonce ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage TactiTwin.', 'tactitwin' ) );
        }
        check_admin_referer( $nonce );
    }

    private static function redirect( string $session_uuid ): void {
        $url = admin_url( 'admin.php?page=tactitwin-live-sessions' );
        if ( '' !== $session_uuid ) {
            $url = add_query_arg( 'session_uuid', rawurlencode( $session_uuid ), $url );
        }
        wp_safe_redirect( $url );
        exit;
    }

    private static function label( string $key, string $language ): string {
        $catalog = array(
            'en'=>array('title'=>'Live Sessions','intro'=>'Persistent match-day timeline. Every update stores the score, manpower, observations and active A/B/C decision.','new_session'=>'New session','need_teams'=>'Import enough event data so at least two teams are available.','session'=>'Session','title_field'=>'Session title','source_match'=>'Source match ID (optional)','language'=>'Language','team'=>'Our team','opponent'=>'Opponent','score'=>'Score','players'=>'Players','minute'=>'Minute','sample'=>'Recent matches','observations'=>'Live observations','high_press'=>'Opponent presses high / locks short build-up','low_block'=>'Opponent sits in a persistent low/compact block','transition'=>'Our losses repeatedly create immediate transition threat','side_bias'=>'Opponent repeatedly attacks the same side/channel','weak_side'=>'Identified weak side remains consistently available','note'=>'Staff note','start'=>'Start session + save first snapshot','save_snapshot'=>'Save snapshot','close_session'=>'Close session','timeline'=>'Session timeline','snapshots'=>'Snapshots','saved_updates'=>'saved updates','current_plan'=>'Current plan','transitions'=>'Transitions','plan_changes'=>'plan/mode changes','last_minute'=>'Last minute','last_snapshot'=>'last saved snapshot','plan'=>'Plan','scenario'=>'Scenario','current_decision'=>'Current decision','recent'=>'Recent sessions','teams'=>'Teams','status'=>'Status','updated'=>'Updated'),
            'es'=>array('title'=>'Live Sessions','intro'=>'Timeline persistente del día de partido. Cada actualización guarda marcador, superioridad, observaciones y decisión A/B/C activa.','new_session'=>'Nueva sesión','need_teams'=>'Importa suficientes datos de eventos para disponer de al menos dos equipos.','session'=>'Sesión','title_field'=>'Título de la sesión','source_match'=>'ID de partido origen (opcional)','language'=>'Idioma','team'=>'Nuestro equipo','opponent'=>'Rival','score'=>'Marcador','players'=>'Jugadores','minute'=>'Minuto','sample'=>'Partidos recientes','observations'=>'Observaciones en vivo','high_press'=>'El rival presiona alto / bloquea la salida corta','low_block'=>'El rival se instala en bloque bajo/compacto','transition'=>'Nuestras pérdidas generan amenaza inmediata repetida','side_bias'=>'El rival ataca repetidamente por el mismo lado/canal','weak_side'=>'El lado débil identificado sigue disponible','note'=>'Nota del cuerpo técnico','start'=>'Iniciar sesión + guardar primer snapshot','save_snapshot'=>'Guardar snapshot','close_session'=>'Cerrar sesión','timeline'=>'Timeline de la sesión','snapshots'=>'Snapshots','saved_updates'=>'actualizaciones guardadas','current_plan'=>'Plan actual','transitions'=>'Transiciones','plan_changes'=>'cambios de plan/modo','last_minute'=>'Último minuto','last_snapshot'=>'último snapshot guardado','plan'=>'Plan','scenario'=>'Escenario','current_decision'=>'Decisión actual','recent'=>'Sesiones recientes','teams'=>'Equipos','status'=>'Estado','updated'=>'Actualizada'),
        );
        return $catalog[$language][$key] ?? $catalog['en'][$key] ?? $key;
    }
}
