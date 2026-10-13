<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Builds an auditable coverage contract for matchup-derived tactical outputs.
 *
 * Effective coverage remains the conservative minimum of the two samples, but
 * v1.35 exposes both sides, their date windows and the exact match IDs so a
 * coach can see which side constrains the evidence base.
 */
final class TactiTwin_Evidence_Coverage {
    public static function compose( array $team_profile, array $opponent_profile, string $language = 'en' ): array {
        $language = TactiTwin_I18n::normalize_language( $language );

        $team = self::sample( $team_profile );
        $opponent = self::sample( $opponent_profile );
        $effective = min( $team['matches'], $opponent['matches'] );
        $difference = abs( $team['matches'] - $opponent['matches'] );
        $limiting_side = self::limiting_side( $team['matches'], $opponent['matches'] );
        $status = self::status( $effective );

        return array(
            'team_matches' => $team['matches'],
            'opponent_matches' => $opponent['matches'],
            'effective_matches' => $effective,
            'status' => $status,
            'team_sample' => $team,
            'opponent_sample' => $opponent,
            'limiting_side' => $limiting_side,
            'asymmetry_matches' => $difference,
            'is_asymmetric' => $difference > 0,
            'limitation_note' => self::limitation_note(
                $team['matches'],
                $opponent['matches'],
                $effective,
                $limiting_side,
                $language
            ),
            'warning' => $effective < 3
                ? self::warning(
                    $team['matches'],
                    $opponent['matches'],
                    $effective,
                    $limiting_side,
                    $language
                )
                : null,
            'method' => array(
                'effective_matches_formula' => 'min(team_matches, opponent_matches)',
                'does_not_impute_missing_matches' => true,
                'does_not_inflate_weaker_sample' => true,
                'sample_ids_exposed' => true,
                'sample_date_windows_exposed' => true,
            ),
        );
    }

    private static function sample( array $profile ): array {
        $coverage = is_array( $profile['coverage'] ?? null ) ? $profile['coverage'] : array();
        $match_ids = array_values(
            array_map(
                'strval',
                is_array( $coverage['match_ids'] ?? null ) ? $coverage['match_ids'] : array()
            )
        );

        return array(
            'team_source_id' => (string) ( $profile['team_source_id'] ?? '' ),
            'team_name' => (string) ( $profile['team_name'] ?? $profile['team_source_id'] ?? '' ),
            'matches' => (int) ( $coverage['matches'] ?? count( $match_ids ) ),
            'match_ids' => $match_ids,
            'oldest_match' => self::nullable_string( $coverage['oldest_match'] ?? null ),
            'newest_match' => self::nullable_string( $coverage['newest_match'] ?? null ),
            'confidence' => (string) ( $coverage['confidence'] ?? self::status( (int) ( $coverage['matches'] ?? count( $match_ids ) ) ) ),
        );
    }

    private static function nullable_string( $value ): ?string {
        $value = trim( (string) $value );
        return '' === $value ? null : $value;
    }

    private static function limiting_side( int $team_matches, int $opponent_matches ): string {
        if ( $team_matches < $opponent_matches ) {
            return 'team';
        }
        if ( $opponent_matches < $team_matches ) {
            return 'opponent';
        }
        return 'balanced';
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

    private static function limitation_note(
        int $team_matches,
        int $opponent_matches,
        int $effective,
        string $limiting_side,
        string $language
    ): string {
        $side = self::side_label( $limiting_side, $language );
        if ( 'es' === $language ) {
            return sprintf(
                'Nuestro equipo aporta %d partido(s) y el rival %d. La cobertura efectiva es %d porque la comparación queda limitada por %s.',
                $team_matches,
                $opponent_matches,
                $effective,
                $side
            );
        }

        return sprintf(
            'Our team contributes %d match(es) and the opponent %d. Effective coverage is %d because the comparison is constrained by %s.',
            $team_matches,
            $opponent_matches,
            $effective,
            $side
        );
    }

    private static function warning(
        int $team_matches,
        int $opponent_matches,
        int $effective,
        string $limiting_side,
        string $language
    ): string {
        $side = self::side_label( $limiting_side, $language );
        if ( 'es' === $language ) {
            return sprintf(
                'Cobertura de evidencia baja: nuestro equipo aporta %d partido(s), el rival %d y la cobertura efectiva es %d. El lado limitante es %s. Trata los scores como señales preliminares y valida las hipótesis con observación del staff.',
                $team_matches,
                $opponent_matches,
                $effective,
                $side
            );
        }

        return sprintf(
            'Low evidence coverage: our team contributes %d match(es), the opponent %d and effective coverage is %d. The limiting side is %s. Treat scores as preliminary signals and validate the hypotheses with staff observation.',
            $team_matches,
            $opponent_matches,
            $effective,
            $side
        );
    }

    private static function side_label( string $side, string $language ): string {
        $labels = array(
            'en' => array(
                'team' => 'our-team sample',
                'opponent' => 'opponent sample',
                'balanced' => 'both samples equally',
            ),
            'es' => array(
                'team' => 'la muestra de nuestro equipo',
                'opponent' => 'la muestra del rival',
                'balanced' => 'ambas muestras por igual',
            ),
        );
        return (string) ( $labels[ $language ][ $side ] ?? $side );
    }
}
