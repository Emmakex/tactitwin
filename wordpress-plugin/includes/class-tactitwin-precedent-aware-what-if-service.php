<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once TACTITWIN_DIR . 'includes/class-tactitwin-historical-team-profile-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-opponent-context-distance.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-opponent-context-similarity-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-precedent-similarity-attachment.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-evidence-suitability-ranker.php';

final class TactiTwin_Precedent_Aware_What_If_Service {
    public function evaluate(
        int $team_id,
        int $opponent_id,
        int $our_score,
        int $opponent_score,
        int $minute,
        int $limit = 5,
        string $language = 'en',
        int $our_players = 11,
        int $opponent_players = 11,
        int $precedent_limit = 5
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $precedent_limit = max( 1, min( 10, $precedent_limit ) );
        $profile_service = new TactiTwin_Profile_Service();
        $profiles = $profile_service->matchup_profiles( $team_id, $opponent_id, $limit );
        $scan = TactiTwin_Matchup_Scan::evaluate( $profiles['team'], $profiles['opponent'], $language );
        $result = TactiTwin_Context_What_If::evaluate(
            $profiles['team'],
            $profiles['opponent'],
            $scan,
            $our_score,
            $opponent_score,
            $minute,
            $language,
            $our_players,
            $opponent_players
        );

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
            $precedents = TactiTwin_Historical_Precedent_Retrieval::retrieve(
                $library,
                $scan,
                $result['hypothesis'] ?? array(),
                $language,
                10
            );
            $precedents = TactiTwin_Precedent_Quality_Attachment::retrieval(
                $precedents,
                $library,
                (string) $opponent_id,
                $language
            );
            $precedents = TactiTwin_Precedent_Similarity_Attachment::retrieval(
                $precedents,
                $library,
                $language
            );
            $result['historical_precedents'] = TactiTwin_Evidence_Suitability_Ranker::retrieval(
                $precedents,
                $language,
                $precedent_limit
            );
        } catch ( Throwable $e ) {
            $result['historical_precedents'] = array(
                'precedent_id' => 'historical-precedent-retrieval',
                'status' => 'unavailable',
                'language' => $language,
                'matched_group_count' => 0,
                'returned_group_count' => 0,
                'items' => array(),
                'summary' => array(
                    'es' === $language
                        ? 'La memoria histórica de intervenciones no está disponible para este What-if: ' . $e->getMessage()
                        : 'Historical intervention memory is unavailable for this What-if: ' . $e->getMessage(),
                ),
                'method' => array(
                    'type' => 'exact-context-historical-precedent-retrieval',
                    'reweights_tactical_scores' => false,
                    'changes_recommendations' => false,
                ),
            );
        }

        $result['method']['historical_precedents'] = array(
            'attached' => true,
            'reweights_tactical_scores' => false,
            'changes_recommendations' => false,
            'quality_calibration' => true,
            'opponent_context_similarity' => true,
            'evidence_suitability_ranking' => true,
            'quality_similarity_combined_score' => true,
            'combined_score_scope' => 'historical evidence ordering only',
            'quality_changes_tactical_scores' => false,
            'quality_changes_recommendations' => false,
            'similarity_changes_tactical_scores' => false,
            'similarity_changes_recommendations' => false,
            'suitability_changes_tactical_scores' => false,
            'suitability_changes_recommendations' => false,
            'note' => 'es' === $language
                ? 'v1.12 ordena los precedentes de contexto exacto por idoneidad de evidencia usando relevancia actual, calidad v1.10 y similitud v1.11. El score combinado solo ordena evidencia histórica y no modifica scores ni recomendaciones del What-if.'
                : 'v1.12 orders exact-context precedents by evidence suitability using current relevance, v1.10 quality and v1.11 similarity. The combined score only orders historical evidence and does not alter What-if scores or recommendations.',
        );

        return $result;
    }
}
