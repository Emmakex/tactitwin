<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Coach-facing v1.35 coverage transparency panel for Match Plan and Twin Lab.
 *
 * The panel is presentation-only. It reads the already generated result and
 * preparation metadata, never changes tactical scores or recommendations.
 */
final class TactiTwin_Evidence_Coverage_Admin_Bridge {
    public static function register(): void {
        add_action( 'admin_footer', array( self::class, 'render' ), 18 );
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $page = sanitize_key( (string) ( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'tactitwin-match-plan' !== $page && 'tactitwin-twin-lab' !== $page ) {
            return;
        }

        $result = self::result( $page );
        if ( empty( $result ) ) {
            return;
        }

        $language = TactiTwin_I18n::normalize_language( (string) ( $result['language'] ?? 'en' ) );
        $coverage = 'tactitwin-twin-lab' === $page
            ? self::twin_coverage( $result )
            : self::match_plan_coverage( $result, $language );

        if ( empty( $coverage ) ) {
            return;
        }

        self::panel( $coverage, $language );
    }

    private static function result( string $page ): array {
        $user_id = get_current_user_id();
        $key = 'tactitwin-twin-lab' === $page
            ? 'tactitwin_twin_lab_' . $user_id
            : 'tactitwin_match_plan_' . $user_id;
        $result = get_transient( $key );
        return is_array( $result ) ? $result : array();
    }

    private static function twin_coverage( array $result ): array {
        $lab = is_array( $result['lab'] ?? null ) ? $result['lab'] : array();
        $coverage = is_array( $lab['coverage'] ?? null ) ? $lab['coverage'] : array();
        return ! empty( $coverage['team_sample'] ) && ! empty( $coverage['opponent_sample'] )
            ? $coverage
            : array();
    }

    private static function match_plan_coverage( array $result, string $language ): array {
        $preparation = is_array( $result['preparation'] ?? null ) ? $result['preparation'] : array();
        $plan = is_array( $result['plan'] ?? null ) ? $result['plan'] : array();
        if ( empty( $preparation['team'] ) || empty( $preparation['opponent'] ) ) {
            return array();
        }

        $team = self::sample_from_preparation(
            $preparation['team'],
            (string) ( $plan['team']['name'] ?? $plan['team']['team_name'] ?? '' )
        );
        $opponent = self::sample_from_preparation(
            $preparation['opponent'],
            (string) ( $plan['opponent']['name'] ?? $plan['opponent']['team_name'] ?? '' )
        );

        return self::coverage_from_samples( $team, $opponent, $language );
    }

    private static function sample_from_preparation( array $side, string $name ): array {
        $ids = array();
        foreach ( is_array( $side['existing'] ?? null ) ? $side['existing'] : array() as $match_id ) {
            $ids[] = (string) $match_id;
        }
        foreach ( is_array( $side['imported'] ?? null ) ? $side['imported'] : array() as $imported ) {
            if ( is_array( $imported ) && isset( $imported['match_id'] ) ) {
                $ids[] = (string) $imported['match_id'];
            }
        }
        $ids = array_values( array_unique( array_filter( $ids, 'strlen' ) ) );
        $dates = self::match_dates( $ids );
        $matches = (int) ( $side['ready_matches'] ?? count( $ids ) );

        return array(
            'team_source_id' => (string) ( $side['team_id'] ?? '' ),
            'team_name' => $name,
            'matches' => $matches,
            'match_ids' => $ids,
            'oldest_match' => empty( $dates ) ? null : min( $dates ),
            'newest_match' => empty( $dates ) ? null : max( $dates ),
            'confidence' => self::status( $matches ),
            'target_matches' => (int) ( $side['target_matches'] ?? 0 ),
            'failed_imports' => count( is_array( $side['failed'] ?? null ) ? $side['failed'] : array() ),
        );
    }

    private static function match_dates( array $ids ): array {
        if ( empty( $ids ) ) {
            return array();
        }

        global $wpdb;
        $table = TactiTwin_DB::table( 'matches' );
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
        $args = array_merge( array( TactiTwin_StatsBomb_Normalizer::SOURCE ), $ids );
        $sql = "SELECT match_date FROM {$table} WHERE source = %s AND source_match_id IN ({$placeholders})"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_col( $wpdb->prepare( $sql, ...$args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return array_values( array_filter( array_map( 'strval', is_array( $rows ) ? $rows : array() ) ) );
    }

    private static function coverage_from_samples( array $team, array $opponent, string $language ): array {
        $team_matches = (int) ( $team['matches'] ?? 0 );
        $opponent_matches = (int) ( $opponent['matches'] ?? 0 );
        $effective = min( $team_matches, $opponent_matches );
        $limiting = $team_matches < $opponent_matches
            ? 'team'
            : ( $opponent_matches < $team_matches ? 'opponent' : 'balanced' );

        return array(
            'team_matches' => $team_matches,
            'opponent_matches' => $opponent_matches,
            'effective_matches' => $effective,
            'status' => self::status( $effective ),
            'team_sample' => $team,
            'opponent_sample' => $opponent,
            'limiting_side' => $limiting,
            'asymmetry_matches' => abs( $team_matches - $opponent_matches ),
            'is_asymmetric' => $team_matches !== $opponent_matches,
            'limitation_note' => self::limitation_note( $team_matches, $opponent_matches, $effective, $limiting, $language ),
        );
    }

    private static function panel( array $coverage, string $language ): void {
        $labels = self::labels( $language );
        $team = is_array( $coverage['team_sample'] ?? null ) ? $coverage['team_sample'] : array();
        $opponent = is_array( $coverage['opponent_sample'] ?? null ) ? $coverage['opponent_sample'] : array();
        $limiting = (string) ( $coverage['limiting_side'] ?? 'balanced' );
        ?>
        <div class="wrap tt-v135-coverage" style="max-width:1240px;margin-top:24px;">
            <div style="background:#fff;border:1px solid #c3c4c7;padding:18px 20px;">
                <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;">
                    <div>
                        <div style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#646970;">v1.35</div>
                        <h2 style="margin:4px 0 6px;"><?php echo esc_html( $labels['title'] ); ?></h2>
                        <p style="margin:0;max-width:920px;"><?php echo esc_html( (string) ( $coverage['limitation_note'] ?? '' ) ); ?></p>
                    </div>
                    <span style="padding:5px 9px;background:#f0f0f1;border-radius:3px;font-weight:600;white-space:nowrap;"><?php echo esc_html( strtoupper( (string) ( $coverage['status'] ?? 'low' ) ) ); ?></span>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:16px;">
                    <?php self::sample_card( $labels['our_team'], $team, $labels ); ?>
                    <?php self::sample_card( $labels['opponent'], $opponent, $labels ); ?>
                    <div style="border:1px solid #dcdcde;padding:14px;">
                        <div style="font-size:11px;text-transform:uppercase;color:#646970;"><?php echo esc_html( $labels['effective'] ); ?></div>
                        <div style="font-size:22px;font-weight:700;margin-top:4px;"><?php echo esc_html( (string) ( $coverage['effective_matches'] ?? 0 ) ); ?></div>
                        <div style="margin-top:5px;color:#646970;"><?php echo esc_html( $labels['limited_by'] . ': ' . self::limiting_label( $limiting, $language ) ); ?></div>
                    </div>
                </div>

                <details style="margin-top:14px;">
                    <summary><strong><?php echo esc_html( $labels['used_matches'] ); ?></strong></summary>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:10px;">
                        <?php self::id_list( $team, $labels ); ?>
                        <?php self::id_list( $opponent, $labels ); ?>
                    </div>
                </details>
                <p style="margin:12px 0 0;color:#646970;font-size:12px;"><?php echo esc_html( $labels['guardrail'] ); ?></p>
            </div>
        </div>
        <?php
    }

    private static function sample_card( string $label, array $sample, array $labels ): void {
        $range = self::range_text( $sample, $labels );
        ?>
        <div style="border:1px solid #dcdcde;padding:14px;">
            <div style="font-size:11px;text-transform:uppercase;color:#646970;"><?php echo esc_html( $label ); ?></div>
            <div style="font-size:18px;font-weight:700;margin-top:4px;"><?php echo esc_html( (string) ( $sample['team_name'] ?? '-' ) ); ?></div>
            <div style="margin-top:5px;"><?php echo esc_html( (string) ( $sample['matches'] ?? 0 ) . ' ' . $labels['matches'] ); ?></div>
            <div style="margin-top:3px;color:#646970;"><?php echo esc_html( $range ); ?></div>
        </div>
        <?php
    }

    private static function id_list( array $sample, array $labels ): void {
        $ids = is_array( $sample['match_ids'] ?? null ) ? $sample['match_ids'] : array();
        ?>
        <div>
            <strong><?php echo esc_html( (string) ( $sample['team_name'] ?? '-' ) ); ?></strong>
            <div style="margin-top:5px;color:#646970;"><?php echo esc_html( $labels['ids'] . ': ' . ( empty( $ids ) ? '-' : implode( ', ', array_map( 'strval', $ids ) ) ) ); ?></div>
        </div>
        <?php
    }

    private static function range_text( array $sample, array $labels ): string {
        $oldest = (string) ( $sample['oldest_match'] ?? '' );
        $newest = (string) ( $sample['newest_match'] ?? '' );
        if ( '' === $oldest && '' === $newest ) {
            return $labels['range_unavailable'];
        }
        if ( $oldest === $newest || '' === $oldest ) {
            return $labels['range'] . ': ' . ( '' !== $newest ? $newest : $oldest );
        }
        return $labels['range'] . ': ' . $oldest . ' → ' . $newest;
    }

    private static function status( int $matches ): string {
        if ( $matches >= 5 ) {
            return 'high';
        }
        if ( $matches >= 3 ) {
            return 'medium';
        }
        return 'low';
    }

    private static function limitation_note( int $team, int $opponent, int $effective, string $limiting, string $language ): string {
        if ( 'es' === $language ) {
            return sprintf(
                'Nuestro equipo aporta %d partido(s), el rival %d y la cobertura efectiva es %d. La comparación queda limitada por %s.',
                $team,
                $opponent,
                $effective,
                self::limiting_label( $limiting, $language )
            );
        }
        return sprintf(
            'Our team contributes %d match(es), the opponent %d and effective coverage is %d. The comparison is constrained by %s.',
            $team,
            $opponent,
            $effective,
            self::limiting_label( $limiting, $language )
        );
    }

    private static function limiting_label( string $side, string $language ): string {
        $labels = array(
            'es' => array( 'team' => 'nuestro equipo', 'opponent' => 'el rival', 'balanced' => 'ambos lados' ),
            'en' => array( 'team' => 'our team', 'opponent' => 'the opponent', 'balanced' => 'both sides' ),
        );
        return (string) ( $labels[ $language ][ $side ] ?? $side );
    }

    private static function labels( string $language ): array {
        if ( 'es' === $language ) {
            return array(
                'title' => 'Detalle de cobertura de evidencia',
                'our_team' => 'Nuestro equipo',
                'opponent' => 'Rival',
                'effective' => 'Cobertura efectiva',
                'limited_by' => 'Limitada por',
                'matches' => 'partido(s)',
                'range' => 'Rango',
                'range_unavailable' => 'Rango temporal no disponible',
                'used_matches' => 'Partidos usados en la muestra',
                'ids' => 'IDs',
                'guardrail' => 'La cobertura describe el volumen de evidencia disponible. No es una probabilidad, no corrige artificialmente la muestra más pequeña y no modifica ningún score táctico.',
            );
        }
        return array(
            'title' => 'Evidence coverage detail',
            'our_team' => 'Our team',
            'opponent' => 'Opponent',
            'effective' => 'Effective coverage',
            'limited_by' => 'Limited by',
            'matches' => 'match(es)',
            'range' => 'Range',
            'range_unavailable' => 'Date range unavailable',
            'used_matches' => 'Matches used in the sample',
            'ids' => 'IDs',
            'guardrail' => 'Coverage describes the available evidence volume. It is not a probability, never inflates the weaker sample and changes no tactical score.',
        );
    }
}
