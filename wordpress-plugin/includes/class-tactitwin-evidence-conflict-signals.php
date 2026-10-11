<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Evidence_Conflict_Signals {
    private const HIGH = 70.0;
    private const LOW = 45.0;
    private const NEAR_TIE_GAP = 5.0;
    private const DELTA_MIN_ABS = 0.5;

    public static function retrieval( array $retrieval, string $language = 'en' ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        if ( 'historical-precedent-retrieval' !== (string) ( $retrieval['precedent_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'Evidence conflict signals require historical precedent retrieval.' );
        }

        $retrieval['decision_brief'] = self::brief(
            is_array( $retrieval['decision_brief'] ?? null ) ? $retrieval['decision_brief'] : array(),
            $language,
            'exact-context-retrieval'
        );
        if ( ! isset( $retrieval['method'] ) || ! is_array( $retrieval['method'] ) ) {
            $retrieval['method'] = array();
        }
        $retrieval['method']['evidence_conflict_signals'] = self::method( $language, 'exact-context-retrieval' );
        return $retrieval;
    }

    public static function catalog( array $catalog, string $language = 'en' ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        if ( 'pre-match-precedent-catalog' !== (string) ( $catalog['catalog_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'Evidence conflict signals require a pre-match precedent catalog.' );
        }

        $scenario_count = 0;
        $with_signals = 0;
        $material = 0;
        if ( ! empty( $catalog['scenarios'] ) && is_array( $catalog['scenarios'] ) ) {
            foreach ( $catalog['scenarios'] as &$scenario ) {
                ++$scenario_count;
                $brief = self::brief(
                    is_array( $scenario['decision_brief'] ?? null ) ? $scenario['decision_brief'] : array(),
                    $language,
                    'match-plan-scenario'
                );
                $scenario['decision_brief'] = $brief;
                if ( (int) ( $brief['conflict_signals']['signal_count'] ?? 0 ) > 0 ) {
                    ++$with_signals;
                }
                if ( (int) ( $brief['conflict_signals']['material_count'] ?? 0 ) > 0 ) {
                    ++$material;
                }
            }
            unset( $scenario );
        }

        $catalog['conflict_signal_summary'] = array(
            'scenario_count' => $scenario_count,
            'scenarios_with_signals' => $with_signals,
            'scenarios_with_material_signals' => $material,
            'note' => self::text( 'catalog_summary', $language ),
        );
        if ( ! isset( $catalog['method'] ) || ! is_array( $catalog['method'] ) ) {
            $catalog['method'] = array();
        }
        $catalog['method']['evidence_conflict_signals'] = self::method( $language, 'within-scenario-brief' );
        return $catalog;
    }

    public static function brief( array $brief, string $language = 'en', string $scope = 'evidence-decision-brief' ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $signals = self::analyze( $brief, $language );
        $brief['conflict_signals'] = $signals;
        if ( ! isset( $brief['method'] ) || ! is_array( $brief['method'] ) ) {
            $brief['method'] = array();
        }
        $brief['method']['evidence_conflict_signals'] = self::method( $language, $scope );
        return $brief;
    }

    private static function analyze( array $brief, string $language ): array {
        if ( 'available' !== (string) ( $brief['status'] ?? '' ) || empty( $brief['primary'] ) ) {
            return array(
                'status' => 'unavailable',
                'signal_count' => 0,
                'material_count' => 0,
                'watch_count' => 0,
                'highest_severity' => 'none',
                'signals' => array(),
                'note' => self::text( 'none_available', $language ),
            );
        }

        $primary = is_array( $brief['primary'] ?? null ) ? $brief['primary'] : array();
        $alternative = is_array( $brief['alternative'] ?? null ) ? $brief['alternative'] : array();
        $comparison = is_array( $brief['comparison'] ?? null ) ? $brief['comparison'] : array();
        $ranking = is_array( $brief['ranking_context'] ?? null ) ? $brief['ranking_context'] : array();
        $signals = array();

        $quality = self::score( $primary, 'evidence_quality' );
        $similarity = self::score( $primary, 'opponent_similarity' );
        $suitability = self::score( $primary, 'evidence_suitability' );

        if ( null !== $quality && null !== $similarity ) {
            if ( $quality >= self::HIGH && $similarity < self::LOW ) {
                $signals[] = self::signal(
                    'high-quality-low-similarity',
                    'material',
                    self::text( 'hq_ls_title', $language ),
                    sprintf( self::text( 'hq_ls_detail', $language ), $quality, $similarity ),
                    array( 'evidence_quality'=>$quality, 'opponent_similarity'=>$similarity )
                );
            } elseif ( $similarity >= self::HIGH && $quality < self::LOW ) {
                $signals[] = self::signal(
                    'high-similarity-low-quality',
                    'material',
                    self::text( 'hs_lq_title', $language ),
                    sprintf( self::text( 'hs_lq_detail', $language ), $similarity, $quality ),
                    array( 'opponent_similarity'=>$similarity, 'evidence_quality'=>$quality )
                );
            }
        }

        $robustness_level = (string) ( $ranking['robustness_level'] ?? '' );
        $top_frequency = self::numeric_or_null( $primary['ranking']['top_rank_frequency_pct'] ?? null );
        $worst_rank = isset( $primary['ranking']['worst_rank'] ) && is_numeric( $primary['ranking']['worst_rank'] )
            ? (int) $primary['ranking']['worst_rank']
            : null;
        if (
            null !== $suitability
            && $suitability >= self::HIGH
            && (
                in_array( $robustness_level, array( 'moderate', 'sensitive' ), true )
                || ( null !== $top_frequency && $top_frequency < 60.0 )
                || ( null !== $worst_rank && $worst_rank > 1 )
            )
        ) {
            $signals[] = self::signal(
                'high-suitability-sensitive-ranking',
                'material',
                self::text( 'hs_sr_title', $language ),
                sprintf(
                    self::text( 'hs_sr_detail', $language ),
                    $suitability,
                    '' !== $robustness_level ? $robustness_level : 'n/a',
                    null !== $top_frequency ? $top_frequency : 0.0
                ),
                array(
                    'evidence_suitability'=>$suitability,
                    'robustness_level'=>$robustness_level,
                    'top_rank_frequency_pct'=>$top_frequency,
                    'worst_rank'=>$worst_rank,
                )
            );
        }

        $gap = self::numeric_or_null( $comparison['suitability_gap'] ?? null );
        if ( ! empty( $alternative ) && null !== $gap && $gap <= self::NEAR_TIE_GAP ) {
            $signals[] = self::signal(
                'primary-alternative-near-tie',
                'watch',
                self::text( 'near_tie_title', $language ),
                sprintf( self::text( 'near_tie_detail', $language ), $gap ),
                array( 'suitability_gap'=>$gap )
            );
        }

        if ( ! empty( $alternative ) ) {
            $opposed = self::opposed_deltas( $primary, $alternative );
            if ( ! empty( $opposed ) ) {
                $signals[] = self::signal(
                    'opposed-historical-deltas',
                    'material',
                    self::text( 'opposed_title', $language ),
                    sprintf( self::text( 'opposed_detail', $language ), implode( ', ', array_column( $opposed, 'metric' ) ) ),
                    array( 'metrics'=>$opposed )
                );
            }
        }

        $missing = self::missing_axes( $primary );
        if ( ! empty( $missing ) ) {
            $signals[] = self::signal(
                'primary-evidence-axis-missing',
                'watch',
                self::text( 'missing_axis_title', $language ),
                sprintf( self::text( 'missing_axis_detail', $language ), implode( ', ', $missing ) ),
                array( 'missing_axes'=>$missing )
            );
        }

        $material_count = count( array_filter( $signals, static fn( array $row ): bool => 'material' === (string) ( $row['severity'] ?? '' ) ) );
        $watch_count = count( array_filter( $signals, static fn( array $row ): bool => 'watch' === (string) ( $row['severity'] ?? '' ) ) );

        return array(
            'status' => 'available',
            'signal_count' => count( $signals ),
            'material_count' => $material_count,
            'watch_count' => $watch_count,
            'highest_severity' => $material_count > 0 ? 'material' : ( $watch_count > 0 ? 'watch' : 'none' ),
            'signals' => $signals,
            'thresholds' => array(
                'high_score' => self::HIGH,
                'low_score' => self::LOW,
                'near_tie_suitability_gap' => self::NEAR_TIE_GAP,
                'opposed_delta_min_abs' => self::DELTA_MIN_ABS,
            ),
            'note' => empty( $signals ) ? self::text( 'no_conflict', $language ) : self::text( 'has_conflict', $language ),
        );
    }

    private static function opposed_deltas( array $primary, array $alternative ): array {
        $a = self::delta_map( $primary );
        $b = self::delta_map( $alternative );
        $opposed = array();
        foreach ( $a as $metric => $primary_delta ) {
            if ( ! array_key_exists( $metric, $b ) ) {
                continue;
            }
            $alternative_delta = $b[ $metric ];
            if ( abs( $primary_delta ) < self::DELTA_MIN_ABS || abs( $alternative_delta ) < self::DELTA_MIN_ABS ) {
                continue;
            }
            if ( ( $primary_delta > 0.0 && $alternative_delta < 0.0 ) || ( $primary_delta < 0.0 && $alternative_delta > 0.0 ) ) {
                $opposed[] = array(
                    'metric' => $metric,
                    'primary_median_delta' => $primary_delta,
                    'alternative_median_delta' => $alternative_delta,
                );
            }
        }
        return $opposed;
    }

    private static function delta_map( array $candidate ): array {
        $map = array();
        foreach ( $candidate['what_supports_it']['historical_deltas'] ?? array() as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $metric = trim( (string) ( $row['metric'] ?? '' ) );
            $delta = $row['median_delta'] ?? null;
            if ( '' !== $metric && is_numeric( $delta ) ) {
                $map[ $metric ] = (float) $delta;
            }
        }
        return $map;
    }

    private static function missing_axes( array $candidate ): array {
        $scores = is_array( $candidate['scores'] ?? null ) ? $candidate['scores'] : array();
        $missing = array();
        foreach ( array( 'scenario_relevance', 'evidence_quality', 'opponent_similarity' ) as $key ) {
            if ( ! is_numeric( $scores[ $key ] ?? null ) ) {
                $missing[] = $key;
            }
        }
        return $missing;
    }

    private static function score( array $candidate, string $key ): ?float {
        return self::numeric_or_null( $candidate['scores'][ $key ] ?? null );
    }

    private static function signal( string $id, string $severity, string $title, string $detail, array $evidence ): array {
        return array(
            'id' => $id,
            'severity' => $severity,
            'title' => $title,
            'detail' => $detail,
            'evidence' => $evidence,
        );
    }

    private static function method( string $language, string $scope ): array {
        return array(
            'type' => 'deterministic-evidence-conflict-signals',
            'scope' => $scope,
            'creates_conflict_score' => false,
            'changes_evidence_order' => false,
            'changes_suitability_scores' => false,
            'changes_tactical_scores' => false,
            'changes_recommendations' => false,
            'changes_active_plan' => false,
            'causal_model' => false,
            'note' => self::text( 'method', $language ),
        );
    }

    private static function numeric_or_null( $value ): ?float {
        return is_numeric( $value ) ? (float) $value : null;
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'method' => 'v1.15 exposes deterministic tension signals when existing evidence dimensions disagree or when the top precedent is fragile. It creates no conflict score, does not reorder evidence and cannot change tactical recommendations or plan activation.',
                'catalog_summary' => 'Conflict signals are evaluated independently inside each current Match Plan scenario.',
                'none_available' => 'No available decision brief exists for conflict analysis.',
                'no_conflict' => 'No configured evidence conflicts were detected in the visible brief.',
                'has_conflict' => 'One or more evidence tensions should be reviewed by staff before relying on the analogy.',
                'hq_ls_title' => 'Strong evidence, weak opponent analogy',
                'hq_ls_detail' => 'Evidence quality is %.1f/100 but opponent similarity is only %.1f/100.',
                'hs_lq_title' => 'Strong opponent analogy, weak evidence base',
                'hs_lq_detail' => 'Opponent similarity is %.1f/100 but evidence quality is only %.1f/100.',
                'hs_sr_title' => 'High suitability, fragile first place',
                'hs_sr_detail' => 'Suitability is %.1f/100, but robustness is %s and the precedent is first only %.1f%% of tested weight profiles.',
                'near_tie_title' => 'Primary and alternative are near-tied',
                'near_tie_detail' => 'The suitability gap between the first two precedents is only %.1f points.',
                'opposed_title' => 'Primary and alternative show opposed historical deltas',
                'opposed_detail' => 'The following shared historical metrics move in opposite directions: %s.',
                'missing_axis_title' => 'Primary precedent has an incomplete evidence axis',
                'missing_axis_detail' => 'These evidence axes are unavailable: %s.',
            ),
            'es' => array(
                'method' => 'v1.15 expone señales deterministas de tensión cuando las dimensiones de evidencia existentes discrepan o cuando el precedente principal es frágil. No crea un score de conflicto, no reordena evidencia y no puede cambiar recomendaciones tácticas ni activar planes.',
                'catalog_summary' => 'Las señales de conflicto se evalúan de forma independiente dentro de cada escenario actual del Plan de Partido.',
                'none_available' => 'No existe un brief de decisión disponible para analizar conflictos.',
                'no_conflict' => 'No se detectaron conflictos de evidencia configurados en el brief visible.',
                'has_conflict' => 'Hay una o más tensiones de evidencia que el staff debería revisar antes de apoyarse en la analogía.',
                'hq_ls_title' => 'Evidencia fuerte, analogía de rival débil',
                'hq_ls_detail' => 'La calidad de evidencia es %.1f/100, pero la similitud del rival es solo %.1f/100.',
                'hs_lq_title' => 'Analogía de rival fuerte, base de evidencia débil',
                'hs_lq_detail' => 'La similitud del rival es %.1f/100, pero la calidad de evidencia es solo %.1f/100.',
                'hs_sr_title' => 'Idoneidad alta, primer puesto frágil',
                'hs_sr_detail' => 'La idoneidad es %.1f/100, pero la robustez es %s y el precedente queda primero solo en el %.1f%% de los perfiles de pesos probados.',
                'near_tie_title' => 'Precedente principal y alternativa casi empatados',
                'near_tie_detail' => 'La diferencia de idoneidad entre los dos primeros precedentes es de solo %.1f puntos.',
                'opposed_title' => 'Principal y alternativa muestran deltas históricos opuestos',
                'opposed_detail' => 'Estas métricas históricas compartidas se mueven en direcciones opuestas: %s.',
                'missing_axis_title' => 'El precedente principal tiene un eje de evidencia incompleto',
                'missing_axis_detail' => 'Estos ejes de evidencia no están disponibles: %s.',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
