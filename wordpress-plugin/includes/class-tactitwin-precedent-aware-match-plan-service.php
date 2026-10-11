<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Precedent_Aware_Match_Plan_Service {
    public function evaluate(
        int $team_id,
        int $opponent_id,
        int $limit = 5,
        string $language = 'en',
        int $contexts_per_scenario = 3
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $contexts_per_scenario = max( 1, min( 10, $contexts_per_scenario ) );
        $profile_service = new TactiTwin_Profile_Service();
        $profiles = $profile_service->matchup_profiles( $team_id, $opponent_id, $limit );
        $scan = TactiTwin_Matchup_Scan::evaluate( $profiles['team'], $profiles['opponent'], $language );
        $plan = TactiTwin_Match_Plan::compose( $scan, $language );

        try {
            $library = ( new TactiTwin_Intervention_Library_Service() )->build(
                $team_id,
                $language,
                '',
                100
            );
            $library = ( new TactiTwin_Opponent_Context_Similarity_Service() )->enrich_library(
                $library,
                $opponent_id,
                $limit,
                $language,
                $profiles['opponent']
            );
            $catalog = TactiTwin_Pre_Match_Precedent_Catalog::build(
                $library,
                $scan,
                $language,
                10
            );
            $catalog = TactiTwin_Precedent_Quality_Attachment::catalog(
                $catalog,
                $library,
                (string) $opponent_id,
                $language
            );
            $catalog = TactiTwin_Precedent_Similarity_Attachment::catalog(
                $catalog,
                $library,
                $language
            );
            $catalog = TactiTwin_Evidence_Suitability_Ranker::catalog(
                $catalog,
                $language,
                10
            );
            $catalog = $this->attach_context_scenario_relevance( $catalog );
            $catalog = TactiTwin_Evidence_Ranking_Robustness::catalog(
                $catalog,
                $language,
                $contexts_per_scenario
            );
            $plan['historical_precedent_catalog'] = TactiTwin_Evidence_Decision_Brief::catalog(
                $catalog,
                $language
            );
        } catch ( Throwable $e ) {
            $plan['historical_precedent_catalog'] = array(
                'catalog_id' => 'pre-match-precedent-catalog',
                'status' => 'unavailable',
                'language' => $language,
                'coverage' => array(
                    'current_scenario_count' => count( $scan['evidence']['scenario_ranking'] ?? array() ),
                    'scenarios_with_history' => 0,
                    'context_group_count' => 0,
                    'contexts_per_scenario_limit' => $contexts_per_scenario,
                ),
                'scenarios' => array(),
                'summary' => array(
                    'es' === $language
                        ? 'La memoria histórica de intervenciones no está disponible para este Plan de Partido: ' . $e->getMessage()
                        : 'Historical intervention memory is unavailable for this Match Plan: ' . $e->getMessage(),
                ),
                'decision_brief_summary' => array(
                    'scenario_brief_count' => 0,
                ),
                'method' => array(
                    'type' => 'scenario-indexed-context-preserving-pre-match-precedent-catalog',
                    'cross_context_metric_aggregation' => false,
                    'reweights_tactical_scores' => false,
                    'changes_recommendations' => false,
                ),
            );
        }

        $plan['method']['historical_precedents'] = array(
            'attached' => true,
            'cross_context_metric_aggregation' => false,
            'reweights_tactical_scores' => false,
            'changes_recommendations' => false,
            'quality_calibration' => true,
            'opponent_context_similarity' => true,
            'evidence_suitability_ranking' => true,
            'evidence_ranking_robustness' => true,
            'evidence_decision_brief' => true,
            'quality_similarity_combined_score' => true,
            'combined_score_scope' => 'historical context ordering within each scenario only',
            'robustness_scope' => 'diagnostic sensitivity within each scenario only',
            'decision_brief_scope' => 'descriptive summary within each scenario only',
            'quality_changes_tactical_scores' => false,
            'quality_changes_recommendations' => false,
            'similarity_changes_tactical_scores' => false,
            'similarity_changes_recommendations' => false,
            'suitability_changes_tactical_scores' => false,
            'suitability_changes_recommendations' => false,
            'robustness_changes_suitability_scores' => false,
            'robustness_changes_tactical_scores' => false,
            'robustness_changes_recommendations' => false,
            'decision_brief_creates_new_score' => false,
            'decision_brief_changes_evidence_order' => false,
            'decision_brief_changes_tactical_scores' => false,
            'decision_brief_changes_recommendations' => false,
            'note' => 'es' === $language
                ? 'v1.14 resume por escenario la evidencia ya ordenada con un precedente principal, alternativa y riesgos de invalidez. No reordena escenarios, no crea un score nuevo y no modifica prioridades, fases, triggers o recomendaciones.'
                : 'v1.14 summarizes already-ranked evidence inside each scenario with a primary precedent, alternative and invalidation risks. It does not reorder scenarios, create a new score or alter priorities, phases, triggers or recommendations.',
        );

        return $plan;
    }

    private function attach_context_scenario_relevance( array $catalog ): array {
        if ( empty( $catalog['scenarios'] ) || ! is_array( $catalog['scenarios'] ) ) {
            return $catalog;
        }

        foreach ( $catalog['scenarios'] as &$scenario ) {
            $relevance = $scenario['current_scan']['relevance'] ?? null;
            if ( empty( $scenario['contexts'] ) || ! is_array( $scenario['contexts'] ) ) {
                continue;
            }
            foreach ( $scenario['contexts'] as &$context ) {
                $context['scenario_relevance'] = is_numeric( $relevance ) ? (float) $relevance : null;
            }
            unset( $context );
        }
        unset( $scenario );
        return $catalog;
    }
}
