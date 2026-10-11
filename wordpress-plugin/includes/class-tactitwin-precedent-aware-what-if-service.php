<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once TACTITWIN_DIR . 'includes/class-tactitwin-historical-team-profile-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-opponent-context-distance.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-opponent-context-similarity-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-precedent-similarity-attachment.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-evidence-suitability-ranker.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-evidence-ranking-robustness.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-evidence-decision-brief.php';

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
            $precedents = TactiTwin_Evidence_Suitability_Ranker::retrieval(
                $precedents,
                $language,
                10
            );
            $precedents = TactiTwin_Evidence_Ranking_Robustness::retrieval(
                $precedents,
                $language,
                $precedent_limit
            );
            $result['historical_precedents'] = TactiTwin_Evidence_Decision_Brief::retrieval(
                $precedents,
                $language
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
                'decision_brief' => array(
                    'brief_id' => 'evidence-decision-brief',
                    'status' => 'unavailable',
                    'language' => $language,
                    'primary' => null,
                    'alternative' => null,
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
            'evidence_ranking_robustness' => true,
            'evidence_decision_brief' => true,
            'quality_similarity_combined_score' => true,
            'combined_score_scope' => 'historical evidence ordering only',
            'robustness_scope' => 'diagnostic sensitivity of historical evidence ordering only',
            'decision_brief_scope' => 'descriptive summary of already-ranked historical evidence only',
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
                ? 'v1.14 resume la evidencia ya ordenada en un brief para staff con precedente principal, alternativa, motivos de encaje, soporte histórico y riesgos de invalidez. No crea un score nuevo ni modifica el orden o las recomendaciones del What-if.'
                : 'v1.14 summarizes already-ranked evidence into a staff brief with a primary precedent, alternative, fit reasons, historical support and invalidation risks. It creates no new score and does not change What-if ordering or recommendations.',
        );

        return $result;
    }
}
