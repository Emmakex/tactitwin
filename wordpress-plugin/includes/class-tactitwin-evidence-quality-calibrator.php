<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Evidence_Quality_Calibrator {
    public static function calibrate(
        array $group,
        string $current_opponent_id = '',
        string $language = 'en',
        ?string $as_of_utc = null
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $as_of = self::date( $as_of_utc ?: gmdate( 'Y-m-d H:i:s' ) );
        $sample = is_array( $group['sample'] ?? null ) ? $group['sample'] : array();
        $cases = self::signal_cases( $group['cases'] ?? array(), (string) ( $sample['signal_basis'] ?? 'insufficient' ) );

        $recency = self::recency( $cases, $as_of );
        $volume = self::volume( count( $cases ) );
        $cleaner = self::cleaner_share( $sample );
        $stability = self::stability( $group['metrics'] ?? array() );
        $opponent = self::opponent_continuity( $cases, $current_opponent_id );

        $score = round(
            ( $recency['score'] * 0.25 )
            + ( $volume['score'] * 0.25 )
            + ( $cleaner['score'] * 0.20 )
            + ( $stability['score'] * 0.20 )
            + ( $opponent['score'] * 0.10 ),
            1
        );
        $level = self::level( $score );

        return array(
            'quality_score' => $score,
            'quality_level' => $level,
            'quality_label' => self::text( 'level_' . str_replace( '-', '_', $level ), $language ),
            'components' => array(
                'recency' => $recency,
                'sample_volume' => $volume,
                'cleaner_share' => $cleaner,
                'directional_stability' => $stability,
                'exact_opponent_continuity' => $opponent,
            ),
            'weights' => array(
                'recency' => 0.25,
                'sample_volume' => 0.25,
                'cleaner_share' => 0.20,
                'directional_stability' => 0.20,
                'exact_opponent_continuity' => 0.10,
            ),
            'method' => array(
                'type' => 'descriptive-historical-evidence-quality-calibration',
                'as_of_utc' => $as_of->format( 'Y-m-d H:i:s' ),
                'changes_tactical_scores' => false,
                'changes_recommendations' => false,
                'changes_active_plan' => false,
                'opponent_strength_model' => false,
                'note' => self::text( 'method', $language ),
                'limitations' => array(
                    self::text( 'limit_opponent', $language ),
                    self::text( 'limit_causality', $language ),
                    self::text( 'limit_missing_dates', $language ),
                ),
            ),
        );
    }

    private static function signal_cases( array $cases, string $basis ): array {
        $comparable = array_values( array_filter(
            $cases,
            static fn( array $case ): bool => true === ( $case['context_comparable'] ?? false )
                && in_array( (string) ( $case['interpretability'] ?? '' ), array( 'cleaner', 'confounded' ), true )
        ) );
        $cleaner = array_values( array_filter(
            $comparable,
            static fn( array $case ): bool => 'cleaner' === (string) ( $case['interpretability'] ?? '' )
        ) );
        if ( 'cleaner' === $basis ) {
            return $cleaner;
        }
        if ( 'context-comparable' === $basis ) {
            return $comparable;
        }
        return ! empty( $cleaner ) ? $cleaner : $comparable;
    }

    private static function recency( array $cases, DateTimeImmutable $as_of ): array {
        $days = array();
        foreach ( $cases as $case ) {
            $date = self::date_or_null( (string) ( $case['session_closed_at'] ?? '' ) );
            if ( null === $date ) {
                continue;
            }
            $seconds = max( 0, $as_of->getTimestamp() - $date->getTimestamp() );
            $days[] = $seconds / DAY_IN_SECONDS;
        }
        sort( $days, SORT_NUMERIC );
        if ( empty( $days ) ) {
            return array( 'score'=>0.0, 'status'=>'unavailable', 'median_age_days'=>null, 'dated_cases'=>0 );
        }
        $median = self::median( $days );
        $score = 10.0;
        if ( $median <= 30 ) $score = 100.0;
        elseif ( $median <= 90 ) $score = 85.0;
        elseif ( $median <= 180 ) $score = 70.0;
        elseif ( $median <= 365 ) $score = 50.0;
        elseif ( $median <= 730 ) $score = 25.0;
        return array(
            'score' => $score,
            'status' => 'available',
            'median_age_days' => round( $median, 1 ),
            'dated_cases' => count( $days ),
        );
    }

    private static function volume( int $count ): array {
        return array(
            'score' => round( min( 100.0, max( 0, $count ) * 12.5 ), 1 ),
            'signal_cases' => max( 0, $count ),
        );
    }

    private static function cleaner_share( array $sample ): array {
        $comparable = max( 0, (int) ( $sample['context_comparable'] ?? 0 ) );
        $cleaner = max( 0, (int) ( $sample['cleaner'] ?? 0 ) );
        $score = $comparable > 0 ? min( 100.0, ( $cleaner / $comparable ) * 100.0 ) : 0.0;
        return array(
            'score' => round( $score, 1 ),
            'cleaner' => $cleaner,
            'context_comparable' => $comparable,
        );
    }

    private static function stability( array $metrics ): array {
        $scores = array();
        foreach ( $metrics as $metric ) {
            $observations = max( 0, (int) ( $metric['observations'] ?? 0 ) );
            if ( $observations < 2 ) {
                continue;
            }
            $up = (float) ( $metric['up_pct'] ?? 0.0 );
            $down = (float) ( $metric['down_pct'] ?? 0.0 );
            $flat = max( 0.0, 100.0 - $up - $down );
            $scores[] = max( $up, $down, $flat );
        }
        if ( empty( $scores ) ) {
            return array( 'score'=>0.0, 'status'=>'insufficient', 'metrics_used'=>0 );
        }
        return array(
            'score' => round( array_sum( $scores ) / count( $scores ), 1 ),
            'status' => 'available',
            'metrics_used' => count( $scores ),
        );
    }

    private static function opponent_continuity( array $cases, string $current_opponent_id ): array {
        $current_opponent_id = trim( $current_opponent_id );
        if ( '' === $current_opponent_id || empty( $cases ) ) {
            return array( 'score'=>0.0, 'status'=>'unavailable', 'exact_cases'=>0, 'signal_cases'=>count( $cases ) );
        }
        $exact = 0;
        foreach ( $cases as $case ) {
            if ( $current_opponent_id === (string) ( $case['opponent_source_id'] ?? '' ) ) {
                ++$exact;
            }
        }
        return array(
            'score' => round( ( $exact / count( $cases ) ) * 100.0, 1 ),
            'status' => 'available',
            'exact_cases' => $exact,
            'signal_cases' => count( $cases ),
        );
    }

    private static function level( float $score ): string {
        if ( $score >= 75.0 ) return 'high';
        if ( $score >= 55.0 ) return 'medium';
        if ( $score >= 35.0 ) return 'low';
        return 'very-low';
    }

    private static function median( array $values ): float {
        $count = count( $values );
        $middle = intdiv( $count, 2 );
        if ( 1 === $count % 2 ) return (float) $values[ $middle ];
        return ( (float) $values[ $middle - 1 ] + (float) $values[ $middle ] ) / 2.0;
    }

    private static function date( string $value ): DateTimeImmutable {
        try {
            return new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
        } catch ( Throwable $e ) {
            return new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
        }
    }

    private static function date_or_null( string $value ): ?DateTimeImmutable {
        if ( '' === trim( $value ) ) return null;
        try {
            return new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
        } catch ( Throwable $e ) {
            return null;
        }
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'level_high' => 'High', 'level_medium' => 'Medium', 'level_low' => 'Low', 'level_very_low' => 'Very low',
                'method' => 'v1.10 scores the quality of historical evidence using recency, signal-sample volume, cleaner-context share, directional stability and exact-opponent continuity. The score calibrates trust in the precedent only; it does not alter tactical decisions.',
                'limit_opponent' => 'Exact-opponent continuity is not an opponent-strength or stylistic-similarity model. A future strength/similarity layer requires additional validated features.',
                'limit_causality' => 'Evidence quality does not turn observational intervention history into causal treatment-effect evidence.',
                'limit_missing_dates' => 'Cases without a persisted session close date receive no recency credit.',
            ),
            'es' => array(
                'level_high' => 'Alta', 'level_medium' => 'Media', 'level_low' => 'Baja', 'level_very_low' => 'Muy baja',
                'method' => 'v1.10 puntúa la calidad de la evidencia histórica usando recencia, volumen de muestra de señal, proporción de contextos limpios, estabilidad direccional y continuidad con el rival exacto. El score calibra la confianza en el precedente; no modifica decisiones tácticas.',
                'limit_opponent' => 'La continuidad con el rival exacto no es un modelo de fuerza ni de similitud estilística del rival. Una futura capa de fuerza/similitud requiere variables adicionales validadas.',
                'limit_causality' => 'La calidad de evidencia no convierte un histórico observacional de intervenciones en evidencia causal.',
                'limit_missing_dates' => 'Los casos sin fecha persistida de cierre de sesión no reciben crédito de recencia.',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
