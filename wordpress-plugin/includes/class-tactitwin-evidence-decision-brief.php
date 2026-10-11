<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Evidence_Decision_Brief {
    public static function retrieval( array $retrieval, string $language = 'en' ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        if ( 'historical-precedent-retrieval' !== (string) ( $retrieval['precedent_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'Evidence decision brief requires historical precedent retrieval.' );
        }

        $retrieval['decision_brief'] = self::build(
            is_array( $retrieval['items'] ?? null ) ? $retrieval['items'] : array(),
            is_array( $retrieval['ranking_robustness'] ?? null ) ? $retrieval['ranking_robustness'] : array(),
            $language,
            'exact-context-retrieval'
        );
        if ( ! isset( $retrieval['method'] ) || ! is_array( $retrieval['method'] ) ) {
            $retrieval['method'] = array();
        }
        $retrieval['method']['evidence_decision_brief'] = self::method( $language, 'exact-context-retrieval' );
        return $retrieval;
    }

    public static function catalog( array $catalog, string $language = 'en' ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        if ( 'pre-match-precedent-catalog' !== (string) ( $catalog['catalog_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'Evidence decision brief requires a pre-match precedent catalog.' );
        }

        $available = 0;
        if ( ! empty( $catalog['scenarios'] ) && is_array( $catalog['scenarios'] ) ) {
            foreach ( $catalog['scenarios'] as &$scenario ) {
                $brief = self::build(
                    is_array( $scenario['contexts'] ?? null ) ? $scenario['contexts'] : array(),
                    is_array( $scenario['ranking_robustness'] ?? null ) ? $scenario['ranking_robustness'] : array(),
                    $language,
                    'match-plan-scenario'
                );
                $scenario['decision_brief'] = $brief;
                if ( 'available' === (string) ( $brief['status'] ?? '' ) ) {
                    ++$available;
                }
            }
            unset( $scenario );
        }

        $catalog['decision_brief_summary'] = array(
            'scenario_brief_count' => $available,
            'note' => self::text( 'catalog_summary', $language ),
        );
        if ( ! isset( $catalog['method'] ) || ! is_array( $catalog['method'] ) ) {
            $catalog['method'] = array();
        }
        $catalog['method']['evidence_decision_brief'] = self::method( $language, 'within-scenario-evidence-brief' );
        return $catalog;
    }

    public static function from_items(
        array $items,
        array $ranking_robustness = array(),
        string $language = 'en',
        string $scope = 'filtered-evidence-shortlist'
    ): array {
        return self::build(
            array_values( $items ),
            $ranking_robustness,
            TactiTwin_I18n::normalize_language( $language ),
            $scope
        );
    }

    private static function build( array $items, array $ranking_robustness, string $language, string $scope ): array {
        if ( empty( $items ) ) {
            return array(
                'brief_id' => 'evidence-decision-brief',
                'status' => 'no-evidence',
                'language' => $language,
                'scope' => $scope,
                'primary' => null,
                'alternative' => null,
                'comparison' => null,
                'what_could_invalidate' => array( self::text( 'no_evidence', $language ) ),
                'method' => self::method( $language, $scope ),
            );
        }

        $primary_item = $items[0];
        $alternative_item = $items[1] ?? null;
        $primary = self::candidate( $primary_item, $language, true );
        $alternative = is_array( $alternative_item ) ? self::candidate( $alternative_item, $language, false ) : null;
        $comparison = self::comparison( $primary_item, $alternative_item, $language );

        $invalidators = $primary['what_could_invalidate'] ?? array();
        $global_level = (string) ( $ranking_robustness['robustness_level'] ?? '' );
        if ( in_array( $global_level, array( 'moderate', 'sensitive' ), true ) ) {
            $invalidators[] = self::text( 'weight_sensitive', $language );
        }
        $invalidators[] = self::text( 'live_context_change', $language );
        $invalidators[] = self::text( 'uncaptured_context', $language );
        $invalidators[] = self::text( 'non_causal', $language );

        return array(
            'brief_id' => 'evidence-decision-brief',
            'status' => 'available',
            'language' => $language,
            'scope' => $scope,
            'primary' => $primary,
            'alternative' => $alternative,
            'comparison' => $comparison,
            'ranking_context' => array(
                'robustness_level' => $ranking_robustness['robustness_level'] ?? null,
                'top_stability_pct' => $ranking_robustness['top_stability_pct'] ?? null,
                'baseline_top_margin' => $ranking_robustness['baseline_top_margin'] ?? null,
                'unique_top_count' => $ranking_robustness['unique_top_count'] ?? null,
            ),
            'what_could_invalidate' => array_values( array_unique( array_filter( $invalidators ) ) ),
            'method' => self::method( $language, $scope ),
        );
    }

    private static function candidate( array $item, string $language, bool $is_primary ): array {
        $quality = self::numeric_or_null( $item['evidence_quality']['quality_score'] ?? null );
        $similarity = self::numeric_or_null( $item['opponent_context_similarity']['similarity_score'] ?? null );
        $suitability = self::numeric_or_null( $item['evidence_suitability']['suitability_score'] ?? null );
        $relevance = self::numeric_or_null( $item['scenario_relevance'] ?? $item['evidence_suitability']['components']['scenario_relevance']['score'] ?? null );
        $robustness = is_array( $item['ranking_robustness'] ?? null ) ? $item['ranking_robustness'] : array();
        $sample = is_array( $item['sample'] ?? null ) ? $item['sample'] : array();
        $why = array();

        $context = self::context( $item );
        if ( '' !== $context['minute_band'] || '' !== $context['score_state'] || '' !== $context['manpower_state'] ) {
            $why[] = sprintf(
                self::text( 'why_context', $language ),
                '' !== $context['minute_band'] ? $context['minute_band'] : '-',
                self::state_label( $context['score_state'], $language ),
                self::manpower_label( $context['manpower_state'], $language )
            );
        }
        if ( null !== $suitability ) {
            $why[] = sprintf( self::text( 'why_suitability', $language ), $suitability );
        }
        if ( null !== $relevance ) {
            $why[] = sprintf( self::text( 'why_relevance', $language ), $relevance );
        }
        if ( null !== $quality ) {
            $why[] = sprintf( self::text( 'why_quality', $language ), $quality );
        }
        if ( null !== $similarity ) {
            $why[] = sprintf( self::text( 'why_similarity', $language ), $similarity );
        }
        if ( isset( $robustness['best_rank'], $robustness['worst_rank'] ) ) {
            $why[] = sprintf(
                self::text( 'why_robustness', $language ),
                (int) $robustness['best_rank'],
                (int) $robustness['worst_rank'],
                (float) ( $robustness['top_rank_frequency_pct'] ?? 0.0 )
            );
        }

        return array(
            'role' => $is_primary ? 'primary' : 'alternative',
            'group_key' => (string) ( $item['group_key'] ?? '' ),
            'scenario_id' => (string) ( $item['scenario_id'] ?? '' ),
            'scenario_name' => (string) ( $item['scenario_name'] ?? $item['scenario_id'] ?? '' ),
            'destination' => array(
                'plan' => strtoupper( (string) ( $item['to_plan'] ?? '' ) ),
                'mode' => (string) ( $item['to_mode'] ?? '' ),
            ),
            'context' => $context,
            'scores' => array(
                'scenario_relevance' => $relevance,
                'evidence_suitability' => $suitability,
                'evidence_quality' => $quality,
                'opponent_similarity' => $similarity,
            ),
            'ranking' => array(
                'suitability_rank' => isset( $item['suitability_rank'] ) ? (int) $item['suitability_rank'] : null,
                'best_rank' => isset( $robustness['best_rank'] ) ? (int) $robustness['best_rank'] : null,
                'worst_rank' => isset( $robustness['worst_rank'] ) ? (int) $robustness['worst_rank'] : null,
                'top_rank_frequency_pct' => isset( $robustness['top_rank_frequency_pct'] ) ? (float) $robustness['top_rank_frequency_pct'] : null,
            ),
            'why_it_fits' => $why,
            'what_supports_it' => array(
                'sample' => array(
                    'maturity' => (string) ( $sample['maturity'] ?? '' ),
                    'maturity_label' => (string) ( $sample['maturity_label'] ?? $sample['maturity'] ?? '' ),
                    'cleaner' => (int) ( $sample['cleaner'] ?? 0 ),
                    'context_comparable' => (int) ( $sample['context_comparable'] ?? 0 ),
                    'signal_basis' => (string) ( $sample['signal_basis'] ?? '' ),
                ),
                'historical_deltas' => self::metric_snapshot( $item['metrics'] ?? array() ),
            ),
            'what_could_invalidate' => self::candidate_invalidators( $item, $quality, $similarity, $robustness, $language ),
        );
    }

    private static function comparison( array $primary, $alternative, string $language ): ?array {
        if ( ! is_array( $alternative ) ) {
            return null;
        }
        $p = self::numeric_or_null( $primary['evidence_suitability']['suitability_score'] ?? null );
        $a = self::numeric_or_null( $alternative['evidence_suitability']['suitability_score'] ?? null );
        $gap = null !== $p && null !== $a ? round( $p - $a, 1 ) : null;
        $alt_top = (float) ( $alternative['ranking_robustness']['top_rank_frequency_pct'] ?? 0.0 );
        $strength = ( $alt_top > 0.0 || ( null !== $gap && $gap <= 8.0 ) ) ? 'strong' : ( ( null !== $gap && $gap <= 15.0 ) ? 'plausible' : 'reserve' );

        return array(
            'suitability_gap' => $gap,
            'alternative_top_rank_frequency_pct' => $alt_top,
            'alternative_strength' => $strength,
            'alternative_strength_label' => self::text( 'alternative_' . $strength, $language ),
            'note' => $alt_top > 0.0
                ? self::text( 'alternative_can_lead', $language )
                : self::text( 'alternative_compare', $language ),
        );
    }

    private static function candidate_invalidators(
        array $item,
        ?float $quality,
        ?float $similarity,
        array $robustness,
        string $language
    ): array {
        $flags = array();
        if ( null === $quality ) {
            $flags[] = self::text( 'quality_missing', $language );
        } elseif ( $quality < 50.0 ) {
            $flags[] = self::text( 'quality_low', $language );
        }
        if ( null === $similarity ) {
            $flags[] = self::text( 'similarity_missing', $language );
        } elseif ( $similarity < 50.0 ) {
            $flags[] = self::text( 'similarity_low', $language );
        }
        if ( (int) ( $robustness['worst_rank'] ?? 1 ) > 1 || (float) ( $robustness['top_rank_frequency_pct'] ?? 100.0 ) < 60.0 ) {
            $flags[] = self::text( 'candidate_sensitive', $language );
        }
        $missing = $item['evidence_suitability']['missing_components'] ?? array();
        if ( is_array( $missing ) && ! empty( $missing ) ) {
            $flags[] = self::text( 'components_missing', $language );
        }
        $sample = is_array( $item['sample'] ?? null ) ? $item['sample'] : array();
        $comparable = (int) ( $sample['context_comparable'] ?? 0 );
        $cleaner = (int) ( $sample['cleaner'] ?? 0 );
        if ( $comparable > 0 && ( $cleaner / $comparable ) < 0.5 ) {
            $flags[] = self::text( 'cleaner_low', $language );
        }
        return array_values( array_unique( $flags ) );
    }

    private static function metric_snapshot( $metrics ): array {
        if ( ! is_array( $metrics ) ) {
            return array();
        }
        $result = array();
        foreach ( array_slice( $metrics, 0, 3 ) as $metric ) {
            if ( ! is_array( $metric ) ) {
                continue;
            }
            $result[] = array(
                'metric' => (string) ( $metric['metric'] ?? '' ),
                'median_delta' => is_numeric( $metric['median_delta'] ?? null ) ? (float) $metric['median_delta'] : null,
                'direction' => (string) ( $metric['direction'] ?? '' ),
            );
        }
        return $result;
    }

    private static function context( array $item ): array {
        return array(
            'minute_band' => (string) ( $item['minute_band'] ?? '' ),
            'score_state' => (string) ( $item['score_state'] ?? '' ),
            'manpower_state' => (string) ( $item['manpower_state'] ?? '' ),
        );
    }

    private static function state_label( string $state, string $language ): string {
        $labels = array(
            'en' => array( 'leading'=>'leading', 'drawing'=>'drawing', 'trailing'=>'trailing', ''=>'-' ),
            'es' => array( 'leading'=>'ganando', 'drawing'=>'empatando', 'trailing'=>'perdiendo', ''=>'-' ),
        );
        return $labels[ $language ][ $state ] ?? $state;
    }

    private static function manpower_label( string $state, string $language ): string {
        $labels = array(
            'en' => array( 'superior'=>'superior', 'equal'=>'equal', 'inferior'=>'inferior', ''=>'-' ),
            'es' => array( 'superior'=>'superior', 'equal'=>'igual', 'inferior'=>'inferior', ''=>'-' ),
        );
        return $labels[ $language ][ $state ] ?? $state;
    }

    private static function method( string $language, string $scope ): array {
        return array(
            'type' => 'descriptive-evidence-decision-brief',
            'scope' => $scope,
            'uses_existing_rank_order' => true,
            'creates_new_tactical_score' => false,
            'changes_evidence_order' => false,
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
                'method' => 'v1.14 converts already-ranked historical evidence into a compact staff brief: primary precedent, strongest visible alternative, fit reasons, supporting historical signals and explicit invalidation risks. It does not create a new score, reorder evidence or change tactical decisions.',
                'catalog_summary' => 'A descriptive decision brief is attached independently inside each current Match Plan scenario.',
                'no_evidence' => 'No eligible historical evidence is available for a decision brief.',
                'why_context' => 'Exact stored context: minute band %s, %s score state, %s manpower state.',
                'why_suitability' => 'Evidence suitability is %.1f/100.',
                'why_relevance' => 'Current scenario relevance is %.1f/100.',
                'why_quality' => 'Historical evidence quality is %.1f/100.',
                'why_similarity' => 'Historical-opponent tactical similarity is %.1f/100.',
                'why_robustness' => 'Across transparent weight profiles this precedent ranges from rank %d to %d and is first %.1f%% of the time.',
                'quality_missing' => 'Evidence quality is unavailable for this precedent.',
                'quality_low' => 'Evidence quality is below 50/100.',
                'similarity_missing' => 'Opponent-context similarity is unavailable.',
                'similarity_low' => 'Opponent-context similarity is below 50/100.',
                'candidate_sensitive' => 'This precedent loses first place under some transparent weight profiles.',
                'components_missing' => 'At least one suitability component is unavailable and the remaining weights were renormalized.',
                'cleaner_low' => 'Fewer than half of context-comparable cases are classified as cleaner evidence.',
                'weight_sensitive' => 'The shortlist leader is sensitive to reasonable changes in ranking weights.',
                'live_context_change' => 'If the live minute/score/manpower context changes, this exact-context precedent may no longer apply.',
                'uncaptured_context' => 'Personnel, fatigue, injuries, competition incentives or off-ball structure may differ from the historical cases.',
                'non_causal' => 'Historical association does not prove that the coaching intervention caused the observed outcome.',
                'alternative_strong' => 'Strong alternative',
                'alternative_plausible' => 'Plausible alternative',
                'alternative_reserve' => 'Reserve comparison',
                'alternative_can_lead' => 'The alternative becomes first under at least one transparent weight profile.',
                'alternative_compare' => 'The alternative remains useful as a comparison but does not lead any tested weight profile.',
            ),
            'es' => array(
                'method' => 'v1.14 convierte evidencia histórica ya ordenada en un brief compacto para el cuerpo técnico: precedente principal, alternativa visible más fuerte, motivos de encaje, señales históricas de apoyo y riesgos explícitos de invalidez. No crea un score nuevo, no reordena evidencia ni cambia decisiones tácticas.',
                'catalog_summary' => 'Se adjunta un brief descriptivo independiente dentro de cada escenario actual del Plan de Partido.',
                'no_evidence' => 'No hay evidencia histórica elegible para construir un brief de decisión.',
                'why_context' => 'Contexto exacto guardado: franja %s, marcador %s y estado numérico %s.',
                'why_suitability' => 'La idoneidad de evidencia es %.1f/100.',
                'why_relevance' => 'La relevancia del escenario actual es %.1f/100.',
                'why_quality' => 'La calidad de la evidencia histórica es %.1f/100.',
                'why_similarity' => 'La similitud táctica del rival histórico es %.1f/100.',
                'why_robustness' => 'Con perfiles transparentes de pesos este precedente oscila entre rango %d y %d y queda primero el %.1f%% de las veces.',
                'quality_missing' => 'La calidad de evidencia no está disponible para este precedente.',
                'quality_low' => 'La calidad de evidencia está por debajo de 50/100.',
                'similarity_missing' => 'La similitud de contexto del rival no está disponible.',
                'similarity_low' => 'La similitud del rival está por debajo de 50/100.',
                'candidate_sensitive' => 'Este precedente pierde el primer puesto con alguno de los perfiles transparentes de pesos.',
                'components_missing' => 'Falta al menos un componente de idoneidad y los pesos restantes se han renormalizado.',
                'cleaner_low' => 'Menos de la mitad de los casos comparables de contexto están clasificados como evidencia más limpia.',
                'weight_sensitive' => 'El líder de la shortlist es sensible a cambios razonables en los pesos del ranking.',
                'live_context_change' => 'Si cambia el minuto, marcador o estado numérico en directo, este precedente de contexto exacto puede dejar de aplicar.',
                'uncaptured_context' => 'Jugadores, fatiga, lesiones, incentivos competitivos o estructura sin balón pueden diferir respecto a los casos históricos.',
                'non_causal' => 'La asociación histórica no demuestra que la intervención del cuerpo técnico causara el resultado observado.',
                'alternative_strong' => 'Alternativa fuerte',
                'alternative_plausible' => 'Alternativa plausible',
                'alternative_reserve' => 'Comparación de reserva',
                'alternative_can_lead' => 'La alternativa pasa al primer puesto con al menos uno de los perfiles transparentes de pesos.',
                'alternative_compare' => 'La alternativa sigue siendo útil como comparación, pero no lidera ninguno de los perfiles de pesos probados.',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
