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
            $plan['historical_precedent_catalog'] = TactiTwin_Pre_Match_Precedent_Catalog::build(
                $library,
                $scan,
                $language,
                $contexts_per_scenario
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
                    'contexts_per_scenario_limit' => max( 1, min( 10, $contexts_per_scenario ) ),
                ),
                'scenarios' => array(),
                'summary' => array(
                    'es' === $language
                        ? 'La memoria histórica de intervenciones no está disponible para este Plan de Partido: ' . $e->getMessage()
                        : 'Historical intervention memory is unavailable for this Match Plan: ' . $e->getMessage(),
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
            'note' => 'es' === $language
                ? 'Los precedentes v1.9 se adjuntan como evidencia prepartido separada por contexto. No modifican prioridades, fases, triggers ni recomendaciones del Plan de Partido.'
                : 'v1.9 precedents are attached as pre-match evidence separated by context. They do not alter Match Plan priorities, phases, triggers or recommendations.',
        );

        return $plan;
    }
}
