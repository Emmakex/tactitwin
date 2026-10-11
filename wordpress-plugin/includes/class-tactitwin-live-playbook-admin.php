<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Live_Playbook_Admin {
    public static function register(): void {
        add_action( 'admin_menu', array( self::class, 'menu' ) );
        add_action( 'admin_post_tactitwin_run_live_playbook', array( self::class, 'run' ) );
    }

    public static function menu(): void {
        add_submenu_page(
            'tactitwin',
            __( 'Live Playbook', 'tactitwin' ),
            __( 'Live Playbook', 'tactitwin' ),
            'manage_options',
            'tactitwin-live-playbook',
            array( self::class, 'render' )
        );
    }

    public static function run(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage TactiTwin.', 'tactitwin' ) );
        }
        check_admin_referer( 'tactitwin_run_live_playbook' );

        $team_id          = absint( $_POST['team_id'] ?? 0 );
        $opponent_id      = absint( $_POST['opponent_id'] ?? 0 );
        $our_score        = min( 20, absint( $_POST['our_score'] ?? 0 ) );
        $opponent_score   = min( 20, absint( $_POST['opponent_score'] ?? 0 ) );
        $our_players      = max( 7, min( 11, absint( $_POST['our_players'] ?? 11 ) ) );
        $opponent_players = max( 7, min( 11, absint( $_POST['opponent_players'] ?? 11 ) ) );
        $minute           = max( 1, min( 120, absint( $_POST['minute'] ?? 1 ) ) );
        $sample           = max( 1, min( 10, absint( $_POST['sample'] ?? 5 ) ) );
        $language         = TactiTwin_I18n::normalize_language( sanitize_key( $_POST['language'] ?? TactiTwin_I18n::current_language() ) );
        $observations = array(
            'high_press' => ! empty( $_POST['observed_high_press'] ),
            'low_block' => ! empty( $_POST['observed_low_block'] ),
            'transition_threat' => ! empty( $_POST['observed_transition_threat'] ),
            'side_bias' => ! empty( $_POST['observed_side_bias'] ),
            'weak_side_open' => ! empty( $_POST['observed_weak_side_open'] ),
            'note' => sanitize_text_field( wp_unslash( (string) ( $_POST['observation_note'] ?? '' ) ) ),
        );

        try {
            $preparation = ( new TactiTwin_Data_Preparation_Service() )->prepare_matchup( $team_id, $opponent_id, $sample );
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
            set_transient(
                'tactitwin_live_playbook_' . get_current_user_id(),
                array(
                    'preparation'=>$preparation,
                    'playbook'=>$playbook,
                    'language'=>$language,
                    'form'=>array(
                        'team_id'=>$team_id,
                        'opponent_id'=>$opponent_id,
                        'our_score'=>$our_score,
                        'opponent_score'=>$opponent_score,
                        'our_players'=>$our_players,
                        'opponent_players'=>$opponent_players,
                        'minute'=>$minute,
                        'sample'=>$sample,
                        'observations'=>$observations,
                    ),
                ),
                10 * MINUTE_IN_SECONDS
            );
        } catch ( Throwable $e ) {
            set_transient( 'tactitwin_live_playbook_error_' . get_current_user_id(), $e->getMessage(), MINUTE_IN_SECONDS );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=tactitwin-live-playbook' ) );
        exit;
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $teams = self::teams();
        $user = get_current_user_id();
        $result = get_transient( 'tactitwin_live_playbook_' . $user );
        $error = get_transient( 'tactitwin_live_playbook_error_' . $user );
        if ( $error ) {
            delete_transient( 'tactitwin_live_playbook_error_' . $user );
        }
        $language = is_array( $result ) && ! empty( $result['language'] )
            ? TactiTwin_I18n::normalize_language( (string) $result['language'] )
            : TactiTwin_I18n::current_language();
        $form = is_array( $result ) ? ( $result['form'] ?? array() ) : array();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( self::label( 'title', $language ) ); ?></h1>
            <p><?php echo esc_html( self::label( 'intro', $language ) ); ?></p>
            <?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>
            <?php if ( count( $teams ) < 2 ) : ?>
                <div class="notice notice-warning inline"><p><?php echo esc_html( self::label( 'need_teams', $language ) ); ?></p></div>
            <?php else : ?>
                <?php self::render_form( $teams, $language, $form ); ?>
            <?php endif; ?>
            <?php if ( is_array( $result ) && ! empty( $result['playbook'] ) ) : self::render_result( $result['playbook'] ); endif; ?>
        </div>
        <?php
    }

    private static function render_form( array $teams, string $language, array $form ): void {
        $observed = $form['observations'] ?? array();
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;padding:20px;max-width:1080px;border:1px solid #dcdcde;">
            <input type="hidden" name="action" value="tactitwin_run_live_playbook">
            <?php wp_nonce_field( 'tactitwin_run_live_playbook' ); ?>
            <table class="form-table" role="presentation">
                <tr><th><?php echo esc_html( self::label( 'language', $language ) ); ?></th><td><select name="language"><option value="es" <?php selected( $language, 'es' ); ?>>Español</option><option value="en" <?php selected( $language, 'en' ); ?>>English</option></select></td></tr>
                <tr><th><?php echo esc_html( self::label( 'team', $language ) ); ?></th><td><?php self::team_select( 'team_id', $teams, (int) ( $form['team_id'] ?? 0 ), 0 ); ?></td></tr>
                <tr><th><?php echo esc_html( self::label( 'opponent', $language ) ); ?></th><td><?php self::team_select( 'opponent_id', $teams, (int) ( $form['opponent_id'] ?? 0 ), 1 ); ?></td></tr>
                <tr><th><?php echo esc_html( self::label( 'score', $language ) ); ?></th><td><input type="number" name="our_score" min="0" max="20" value="<?php echo esc_attr( (string) ( $form['our_score'] ?? 0 ) ); ?>" style="width:70px"> - <input type="number" name="opponent_score" min="0" max="20" value="<?php echo esc_attr( (string) ( $form['opponent_score'] ?? 0 ) ); ?>" style="width:70px"></td></tr>
                <tr><th><?php echo esc_html( self::label( 'players', $language ) ); ?></th><td><input type="number" name="our_players" min="7" max="11" value="<?php echo esc_attr( (string) ( $form['our_players'] ?? 11 ) ); ?>" style="width:70px"> v <input type="number" name="opponent_players" min="7" max="11" value="<?php echo esc_attr( (string) ( $form['opponent_players'] ?? 11 ) ); ?>" style="width:70px"></td></tr>
                <tr><th><?php echo esc_html( self::label( 'minute', $language ) ); ?></th><td><input type="number" name="minute" min="1" max="120" value="<?php echo esc_attr( (string) ( $form['minute'] ?? 1 ) ); ?>"></td></tr>
                <tr><th><?php echo esc_html( self::label( 'sample', $language ) ); ?></th><td><input type="number" name="sample" min="1" max="10" value="<?php echo esc_attr( (string) ( $form['sample'] ?? 5 ) ); ?>"></td></tr>
            </table>
            <h2><?php echo esc_html( self::label( 'live_observations', $language ) ); ?></h2>
            <p><?php echo esc_html( self::label( 'observations_help', $language ) ); ?></p>
            <fieldset style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:10px;margin-bottom:14px;">
                <?php self::checkbox( 'observed_high_press', 'high_press', self::label( 'high_press', $language ), $observed ); ?>
                <?php self::checkbox( 'observed_low_block', 'low_block', self::label( 'low_block', $language ), $observed ); ?>
                <?php self::checkbox( 'observed_transition_threat', 'transition_threat', self::label( 'transition', $language ), $observed ); ?>
                <?php self::checkbox( 'observed_side_bias', 'side_bias', self::label( 'side_bias', $language ), $observed ); ?>
                <?php self::checkbox( 'observed_weak_side_open', 'weak_side_open', self::label( 'weak_side', $language ), $observed ); ?>
            </fieldset>
            <p><label><strong><?php echo esc_html( self::label( 'note', $language ) ); ?></strong><br><input type="text" name="observation_note" value="<?php echo esc_attr( (string) ( $observed['note'] ?? '' ) ); ?>" style="width:100%;max-width:800px"></label></p>
            <?php submit_button( self::label( 'run', $language ) ); ?>
        </form>
        <?php
    }

    private static function render_result( array $playbook ): void {
        $language = TactiTwin_I18n::normalize_language( (string) ( $playbook['language'] ?? 'en' ) );
        $active = $playbook['active_plan'] ?? array();
        $live = $playbook['live_observation'] ?? array();
        $plans = $playbook['plans'] ?? array();
        ?>
        <hr style="margin:32px 0;">
        <div style="max-width:1100px;">
            <h2><?php echo esc_html( self::label( 'result_title', $language ) ); ?></h2>
            <?php if ( ! empty( $playbook['coverage']['warning'] ) ) : ?><div class="notice notice-warning inline"><p><?php echo esc_html( (string) $playbook['coverage']['warning'] ); ?></p></div><?php endif; ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin:18px 0;">
                <?php self::card( self::label( 'active_plan', $language ), (string) ( $active['id'] ?? '-' ), (string) ( $active['mode'] ?? '' ) ); ?>
                <?php self::card( self::label( 'minute', $language ), (string) ( $playbook['current_context']['minute'] ?? '-' ), (string) ( $playbook['current_context']['score_state_label'] ?? '' ) ); ?>
                <?php self::card( self::label( 'score', $language ), (string) ( $playbook['current_context']['our_score'] ?? 0 ) . '-' . (string) ( $playbook['current_context']['opponent_score'] ?? 0 ), (string) ( $playbook['current_context']['manpower_state_label'] ?? '' ) ); ?>
                <?php self::card( self::label( 'live_count', $language ), (string) ( $live['observed_count'] ?? 0 ), (string) ( $live['activation']['status'] ?? '' ) ); ?>
                <?php self::card( self::label( 'coverage', $language ), strtoupper( (string) ( $playbook['coverage']['status_label'] ?? $playbook['coverage']['status'] ?? '' ) ), self::label( 'evidence', $language ) ); ?>
            </div>
            <div style="background:#fff;border-left:4px solid #2271b1;padding:14px 16px;margin:15px 0;"><strong><?php echo esc_html( (string) ( $active['label'] ?? '' ) ); ?></strong><br><?php echo esc_html( (string) ( $active['reason'] ?? '' ) ); ?></div>

            <h3><?php echo esc_html( self::label( 'observed', $language ) ); ?></h3>
            <?php if ( empty( $live['candidate_scenarios'] ) ) : ?>
                <p><?php echo esc_html( self::label( 'none_observed', $language ) ); ?></p>
            <?php else : ?>
                <table class="widefat striped"><thead><tr><th><?php echo esc_html( self::label( 'scenario', $language ) ); ?></th><th><?php echo esc_html( self::label( 'risk', $language ) ); ?></th><th><?php echo esc_html( self::label( 'mode', $language ) ); ?></th></tr></thead><tbody>
                <?php foreach ( $live['candidate_scenarios'] as $candidate ) : ?><tr><td><?php echo esc_html( (string) ( $candidate['scenario_name'] ?? '' ) ); ?></td><td><?php echo esc_html( (string) ( $candidate['risk_score'] ?? 0 ) ); ?>/100</td><td><?php echo esc_html( (string) ( $candidate['mode'] ?? '' ) ); ?></td></tr><?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>

            <h3 style="margin-top:24px;"><?php echo esc_html( self::label( 'plans', $language ) ); ?></h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;">
                <?php foreach ( array('plan_a','plan_b','plan_c') as $plan_key ) : $plan = $plans[$plan_key] ?? array(); ?>
                    <div style="background:#fff;border:1px solid #dcdcde;padding:16px;">
                        <h3 style="margin-top:0;"><?php echo esc_html( (string) ( $plan['name'] ?? strtoupper($plan_key) ) ); ?></h3>
                        <p><strong><?php echo esc_html( (string) ( $plan['mode'] ?? '' ) ); ?></strong> · <?php echo esc_html( (string) ( $plan['status'] ?? '' ) ); ?></p>
                        <?php if ( ! empty( $plan['trigger'] ) ) : ?><p><?php echo esc_html( (string) $plan['trigger'] ); ?></p><?php endif; ?>
                        <ul style="list-style:disc;padding-left:20px;">
                            <?php foreach ( array_slice( $plan['instructions'] ?? array(), 0, 5 ) as $item ) : ?><li><?php echo esc_html( (string) $item ); ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>

            <h3 style="margin-top:24px;"><?php echo esc_html( self::label( 'decision_tree', $language ) ); ?></h3>
            <table class="widefat striped"><thead><tr><th>#</th><th><?php echo esc_html( self::label( 'condition', $language ) ); ?></th><th><?php echo esc_html( self::label( 'target', $language ) ); ?></th><th><?php echo esc_html( self::label( 'mode', $language ) ); ?></th></tr></thead><tbody>
                <?php foreach ( $playbook['decision_tree'] ?? array() as $row ) : ?><tr><td><?php echo esc_html( (string) ( $row['order'] ?? '' ) ); ?></td><td><?php echo esc_html( (string) ( $row['condition'] ?? '' ) ); ?></td><td><?php echo esc_html( (string) ( $row['target_plan'] ?? '' ) ); ?></td><td><?php echo esc_html( (string) ( $row['mode'] ?? '' ) ); ?></td></tr><?php endforeach; ?>
            </tbody></table>

            <h3 style="margin-top:24px;"><?php echo esc_html( self::label( 'recommendations', $language ) ); ?></h3>
            <ol style="background:#fff;border:1px solid #dcdcde;padding:18px 18px 18px 38px;">
                <?php foreach ( $playbook['recommendations'] ?? array() as $item ) : ?><li style="margin-bottom:8px;"><?php echo esc_html( (string) $item ); ?></li><?php endforeach; ?>
            </ol>
            <details style="margin-top:22px;"><summary><strong><?php echo esc_html( self::label( 'technical', $language ) ); ?></strong></summary><pre style="white-space:pre-wrap;background:#fff;padding:16px;border:1px solid #dcdcde;"><?php echo esc_html( wp_json_encode( $playbook, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre></details>
        </div>
        <?php
    }

    private static function checkbox( string $field, string $key, string $label, array $observed ): void {
        ?><label style="display:block;border:1px solid #dcdcde;padding:12px;background:#f6f7f7;"><input type="checkbox" name="<?php echo esc_attr( $field ); ?>" value="1" <?php checked( ! empty( $observed[$key] ) ); ?>> <?php echo esc_html( $label ); ?></label><?php
    }

    private static function card( string $title, string $value, string $meta ): void {
        ?><div style="background:#fff;border:1px solid #dcdcde;padding:16px;"><div style="font-size:12px;text-transform:uppercase;color:#646970;"><?php echo esc_html( $title ); ?></div><div style="font-size:24px;font-weight:700;margin-top:4px;"><?php echo esc_html( $value ); ?></div><div style="margin-top:4px;color:#646970;"><?php echo esc_html( $meta ); ?></div></div><?php
    }

    private static function team_select( string $name, array $teams, int $selected_id, int $default_index ): void {
        ?><select name="<?php echo esc_attr( $name ); ?>" required><?php foreach ( $teams as $index => $team ) : $id = (int) $team['source_team_id']; ?><option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( $selected_id > 0 ? $id === $selected_id : $index === $default_index ); ?>><?php echo esc_html( (string) $team['team_name'] ); ?></option><?php endforeach; ?></select><?php
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
        $catalog = array(
            'en'=>array(
                'title'=>'Live Playbook','intro'=>'Update score/manpower and tick only the opponent behaviours actually observed. TactiTwin keeps Plan C precedence and activates Plan B only from explicit live input.','need_teams'=>'Import enough event data so at least two teams are available.','language'=>'Language','team'=>'Our team','opponent'=>'Opponent','score'=>'Score','players'=>'Players','minute'=>'Minute','sample'=>'Recent matches','live_observations'=>'Live opponent observations','observations_help'=>'Tick behaviours the staff can confirm now. Leaving all unchecked keeps the automatic Plan A/C selection.','high_press'=>'Opponent is pressing high / locking short build-up','low_block'=>'Opponent has settled into a persistent low or compact block','transition'=>'Our losses repeatedly create immediate transition threat','side_bias'=>'Opponent is repeatedly attacking through the same side/channel','weak_side'=>'The identified weak side is still consistently available','note'=>'Staff note','run'=>'Update live playbook','result_title'=>'Current live playbook','active_plan'=>'Active plan','live_count'=>'Live signals','coverage'=>'Coverage','evidence'=>'evidence','observed'=>'Observed Plan B candidates','none_observed'=>'No live Plan B behaviour is currently checked.','scenario'=>'Scenario','risk'=>'Pre-match risk','mode'=>'Mode','plans'=>'Prepared Plan A / B / C','decision_tree'=>'Decision tree','condition'=>'Condition','target'=>'Target plan','recommendations'=>'Current recommendations','technical'=>'Technical evidence',
            ),
            'es'=>array(
                'title'=>'Live Playbook','intro'=>'Actualiza marcador/superioridad y marca únicamente los comportamientos rivales realmente observados. TactiTwin mantiene la prioridad de Plan C y solo activa Plan B mediante una entrada explícita en vivo.','need_teams'=>'Importa suficientes datos de eventos para disponer de al menos dos equipos.','language'=>'Idioma','team'=>'Nuestro equipo','opponent'=>'Rival','score'=>'Marcador','players'=>'Jugadores','minute'=>'Minuto','sample'=>'Partidos recientes','live_observations'=>'Observaciones del rival en vivo','observations_help'=>'Marca solo los comportamientos que el cuerpo técnico puede confirmar ahora. Si no marcas ninguno, se mantiene la selección automática Plan A/C.','high_press'=>'El rival presiona alto / bloquea la salida corta','low_block'=>'El rival se ha instalado en bloque bajo o compacto','transition'=>'Nuestras pérdidas generan amenaza inmediata repetida en transición','side_bias'=>'El rival ataca repetidamente por el mismo lado/canal','weak_side'=>'El lado débil identificado sigue disponible de forma consistente','note'=>'Nota del cuerpo técnico','run'=>'Actualizar live playbook','result_title'=>'Playbook actual en vivo','active_plan'=>'Plan activo','live_count'=>'Señales en vivo','coverage'=>'Cobertura','evidence'=>'evidencia','observed'=>'Candidatos observados para Plan B','none_observed'=>'No hay ningún comportamiento Plan B marcado en vivo.','scenario'=>'Escenario','risk'=>'Riesgo prepartido','mode'=>'Modo','plans'=>'Plan A / B / C preparados','decision_tree'=>'Árbol de decisión','condition'=>'Condición','target'=>'Plan destino','recommendations'=>'Recomendaciones actuales','technical'=>'Evidencia técnica',
            ),
        );
        return $catalog[$language][$key] ?? $catalog['en'][$key] ?? $key;
    }
}
