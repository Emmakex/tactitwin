<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Post_Match_Review_Admin {
    public static function register(): void {
        add_action( 'admin_menu', array( self::class, 'menu' ) );
    }

    public static function menu(): void {
        add_submenu_page(
            'tactitwin',
            __( 'Session Review', 'tactitwin' ),
            __( 'Session Review', 'tactitwin' ),
            'manage_options',
            'tactitwin-session-review',
            array( self::class, 'render' )
        );
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $session_service = new TactiTwin_Live_Session_Service();
        $sessions = $session_service->recent_sessions( 30 );
        $session_uuid = sanitize_text_field( wp_unslash( (string) ( $_GET['session_uuid'] ?? '' ) ) );
        $review = null;
        $error = null;

        if ( '' !== $session_uuid ) {
            try {
                $review = ( new TactiTwin_Post_Match_Review_Service() )->review( $session_uuid );
            } catch ( Throwable $e ) {
                $error = $e->getMessage();
            }
        }

        $language = is_array( $review )
            ? TactiTwin_I18n::normalize_language( (string) ( $review['language'] ?? 'en' ) )
            : TactiTwin_I18n::current_language();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( self::label( 'title', $language ) ); ?></h1>
            <p><?php echo esc_html( self::label( 'intro', $language ) ); ?></p>
            <?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>
            <?php self::render_sessions( $sessions, $session_uuid, $language ); ?>
            <?php if ( is_array( $review ) ) : self::render_review( $review, $language ); endif; ?>
        </div>
        <?php
    }

    private static function render_sessions( array $sessions, string $selected_uuid, string $language ): void {
        ?>
        <h2><?php echo esc_html( self::label( 'sessions', $language ) ); ?></h2>
        <?php if ( empty( $sessions ) ) : ?><p><?php echo esc_html( self::label( 'no_sessions', $language ) ); ?></p><?php return; endif; ?>
        <table class="widefat striped" style="max-width:1100px;margin-bottom:28px;">
            <thead><tr><th><?php echo esc_html( self::label( 'session', $language ) ); ?></th><th><?php echo esc_html( self::label( 'teams', $language ) ); ?></th><th><?php echo esc_html( self::label( 'status', $language ) ); ?></th><th><?php echo esc_html( self::label( 'updated', $language ) ); ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ( $sessions as $session ) :
                $uuid = (string) $session['session_uuid'];
                $url = add_query_arg( array('page'=>'tactitwin-session-review','session_uuid'=>$uuid), admin_url('admin.php') );
                ?>
                <tr<?php echo $uuid === $selected_uuid ? ' style="background:#f0f6fc;"' : ''; ?>>
                    <td><?php echo esc_html( (string) ( $session['title'] ?: $uuid ) ); ?></td>
                    <td><?php echo esc_html( (string) $session['team_source_id'] . ' vs ' . (string) $session['opponent_source_id'] ); ?></td>
                    <td><?php echo esc_html( strtoupper( (string) $session['status'] ) ); ?></td>
                    <td><?php echo esc_html( (string) $session['updated_at'] ); ?></td>
                    <td><a class="button" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( self::label( 'review', $language ) ); ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private static function render_review( array $review, string $language ): void {
        $prepared = $review['prepared'] ?? array();
        $observed = $review['observed'] ?? array();
        $activated = $review['activated'] ?? array();
        $alignment = $review['alignment'] ?? array();
        $sampling = $review['sampling'] ?? array();
        $final = $review['final_context'] ?? array();
        ?>
        <hr>
        <h2><?php echo esc_html( self::label( 'review_title', $language ) ); ?></h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;max-width:1100px;margin:18px 0;">
            <?php self::card( self::label( 'final_score', $language ), (string) ( $final['our_score'] ?? 0 ) . '-' . (string) ( $final['opponent_score'] ?? 0 ), sprintf( "%d'", (int) ( $final['minute'] ?? 0 ) ) ); ?>
            <?php self::card( self::label( 'prepared_b', $language ), (string) ( $prepared['primary_plan_b']['scenario_name'] ?: $prepared['primary_plan_b']['status'] ?? '-' ), (string) ( $prepared['primary_plan_b']['mode'] ?? '' ) ); ?>
            <?php self::card( self::label( 'observed_behaviours', $language ), (string) count( $observed['unique_scenarios'] ?? array() ), self::label( 'manual_observations', $language ) ); ?>
            <?php self::card( self::label( 'transitions', $language ), (string) ( $activated['transition_count'] ?? 0 ), self::label( 'plan_mode_changes', $language ) ); ?>
            <?php self::card( self::label( 'sampling', $language ), strtoupper( (string) ( $sampling['status'] ?? '' ) ), (string) ( $sampling['snapshot_count'] ?? 0 ) . ' ' . self::label( 'snapshots', $language ) ); ?>
        </div>

        <div style="max-width:1100px;background:#fff;border-left:4px solid #2271b1;padding:16px;margin-bottom:18px;">
            <strong><?php echo esc_html( self::label( 'alignment', $language ) ); ?>:</strong><br>
            <?php echo esc_html( (string) ( $alignment['label'] ?? '' ) ); ?>
        </div>

        <?php if ( ! empty( $sampling['warnings'] ) ) : ?>
            <div class="notice notice-warning inline" style="max-width:1060px;"><ul style="list-style:disc;padding-left:20px;">
                <?php foreach ( $sampling['warnings'] as $warning ) : ?><li><?php echo esc_html( (string) $warning ); ?></li><?php endforeach; ?>
            </ul></div>
        <?php endif; ?>

        <h3><?php echo esc_html( self::label( 'summary', $language ) ); ?></h3>
        <ul style="max-width:1100px;background:#fff;border:1px solid #dcdcde;padding:16px 16px 16px 36px;">
            <?php foreach ( $review['summary'] ?? array() as $item ) : ?><li><?php echo esc_html( (string) $item ); ?></li><?php endforeach; ?>
        </ul>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;max-width:1100px;">
            <div style="background:#fff;border:1px solid #dcdcde;padding:16px;">
                <h3 style="margin-top:0;"><?php echo esc_html( self::label( 'observed', $language ) ); ?></h3>
                <?php self::scenario_list( $observed['scenario_snapshot_counts'] ?? array(), $observed['scenario_first_seen_minute'] ?? array(), $language ); ?>
            </div>
            <div style="background:#fff;border:1px solid #dcdcde;padding:16px;">
                <h3 style="margin-top:0;"><?php echo esc_html( self::label( 'activated_b', $language ) ); ?></h3>
                <?php self::scenario_list( $activated['plan_b_scenario_counts'] ?? array(), $activated['plan_b_first_activation_minute'] ?? array(), $language ); ?>
            </div>
        </div>

        <?php if ( ! empty( $alignment['unprepared_observed_scenarios'] ) ) : ?>
            <h3><?php echo esc_html( self::label( 'unprepared_observed', $language ) ); ?></h3>
            <p><?php echo esc_html( implode( ', ', array_map( 'strval', $alignment['unprepared_observed_scenarios'] ) ) ); ?></p>
        <?php endif; ?>

        <h3><?php echo esc_html( self::label( 'timeline', $language ) ); ?></h3>
        <table class="widefat striped" style="max-width:1100px;"><thead><tr><th><?php echo esc_html( self::label( 'minute', $language ) ); ?></th><th><?php echo esc_html( self::label( 'from', $language ) ); ?></th><th><?php echo esc_html( self::label( 'to', $language ) ); ?></th><th><?php echo esc_html( self::label( 'scenario', $language ) ); ?></th></tr></thead><tbody>
            <?php foreach ( $activated['transitions'] ?? array() as $row ) : ?><tr><td><?php echo esc_html( (string) $row['minute'] ); ?>'</td><td><?php echo esc_html( (string) $row['from_plan'] . ' / ' . (string) $row['from_mode'] ); ?></td><td><?php echo esc_html( (string) $row['to_plan'] . ' / ' . (string) $row['to_mode'] ); ?></td><td><?php echo esc_html( (string) ( $row['to_scenario'] ?? '' ) ); ?></td></tr><?php endforeach; ?>
        </tbody></table>

        <?php if ( ! empty( $review['staff_notes'] ) ) : ?>
            <h3><?php echo esc_html( self::label( 'notes', $language ) ); ?></h3><ul style="max-width:1100px;background:#fff;border:1px solid #dcdcde;padding:16px 16px 16px 36px;">
                <?php foreach ( $review['staff_notes'] as $note ) : ?><li><strong><?php echo esc_html( (string) $note['minute'] ); ?>'</strong> — <?php echo esc_html( (string) $note['note'] ); ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <details style="max-width:1100px;margin-top:22px;"><summary><strong><?php echo esc_html( self::label( 'technical', $language ) ); ?></strong></summary><pre style="white-space:pre-wrap;background:#fff;padding:16px;border:1px solid #dcdcde;"><?php echo esc_html( wp_json_encode( $review, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre></details>
        <?php
    }

    private static function scenario_list( array $counts, array $first_seen, string $language ): void {
        if ( empty( $counts ) ) {
            echo '<p>' . esc_html( self::label( 'none', $language ) ) . '</p>';
            return;
        }
        echo '<ul style="list-style:disc;padding-left:20px;">';
        foreach ( $counts as $scenario=>$count ) {
            $minute = isset( $first_seen[$scenario] ) ? " · " . (int) $first_seen[$scenario] . "'" : '';
            echo '<li><code>' . esc_html( (string) $scenario ) . '</code> · ' . esc_html( (string) $count ) . esc_html( $minute ) . '</li>';
        }
        echo '</ul>';
    }

    private static function card( string $title, string $value, string $meta ): void {
        ?><div style="background:#fff;border:1px solid #dcdcde;padding:16px;"><div style="font-size:12px;text-transform:uppercase;color:#646970;"><?php echo esc_html($title); ?></div><div style="font-size:21px;font-weight:700;margin-top:4px;word-break:break-word;"><?php echo esc_html($value); ?></div><div style="color:#646970;margin-top:4px;"><?php echo esc_html($meta); ?></div></div><?php
    }

    private static function label( string $key, string $language ): string {
        $catalog = array(
            'en'=>array('title'=>'Session Review','intro'=>'Compare what was prepared, what staff observed and what was actually activated. This is an audit trail, not a coaching-quality score.','sessions'=>'Recent live sessions','no_sessions'=>'No live sessions are available yet.','session'=>'Session','teams'=>'Teams','status'=>'Status','updated'=>'Updated','review'=>'Review','review_title'=>'Post-match review','final_score'=>'Final stored score','prepared_b'=>'Prepared Plan B','observed_behaviours'=>'Observed behaviours','manual_observations'=>'manual supported behaviours','transitions'=>'Transitions','plan_mode_changes'=>'plan/mode/scenario changes','sampling'=>'Sampling','snapshots'=>'snapshots','alignment'=>'Prepared vs observed vs activated','summary'=>'Review summary','observed'=>'Observed behaviours','activated_b'=>'Activated Plan B scenarios','unprepared_observed'=>'Observed outside the prepared priority set','timeline'=>'Decision transitions','minute'=>'Minute','from'=>'From','to'=>'To','scenario'=>'Scenario','notes'=>'Staff notes','technical'=>'Technical review evidence','none'=>'None recorded'),
            'es'=>array('title'=>'Session Review','intro'=>'Compara lo preparado, lo observado por el cuerpo técnico y lo que realmente se activó. Es una trazabilidad del partido, no una nota sobre la calidad del entrenador.','sessions'=>'Sesiones en vivo recientes','no_sessions'=>'Todavía no hay sesiones en vivo disponibles.','session'=>'Sesión','teams'=>'Equipos','status'=>'Estado','updated'=>'Actualizada','review'=>'Revisar','review_title'=>'Revisión post-partido','final_score'=>'Marcador final guardado','prepared_b'=>'Plan B preparado','observed_behaviours'=>'Comportamientos observados','manual_observations'=>'comportamientos soportados observados','transitions'=>'Transiciones','plan_mode_changes'=>'cambios de plan/modo/escenario','sampling'=>'Muestreo','snapshots'=>'snapshots','alignment'=>'Preparado vs observado vs activado','summary'=>'Resumen de revisión','observed'=>'Comportamientos observados','activated_b'=>'Escenarios Plan B activados','unprepared_observed'=>'Observado fuera de las prioridades preparadas','timeline'=>'Transiciones de decisión','minute'=>'Minuto','from'=>'Desde','to'=>'Hacia','scenario'=>'Escenario','notes'=>'Notas del cuerpo técnico','technical'=>'Evidencia técnica de la revisión','none'=>'Ninguno registrado'),
        );
        return $catalog[$language][$key] ?? $catalog['en'][$key] ?? $key;
    }
}
