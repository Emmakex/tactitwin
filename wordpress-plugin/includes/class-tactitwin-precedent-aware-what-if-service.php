<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once TACTITWIN_DIR . 'includes/class-tactitwin-historical-team-profile-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-opponent-context-distance.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-opponent-context-similarity-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-precedent-similarity-attachment.php';

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
                $precedent_limit
            );
            $precedents = TactiTwin_Precedent_Quality_Attachment::retrieval(
                $precedents,
                $library,
                (string) $opponent_id,
                $language
            );
            $result['historical_precedents'] = TactiTwin_Precedent_Similarity_Attachment::retrieval(
                $precedents,
                $library,
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
            'quality_similarity_combined_score' => false,
            'quality_changes_tactical_scores' => false,
            'quality_changes_recommendations' => false,
            'similarity_changes_tactical_scores' => false,
            'similarity_changes_recommendations' => false,
            'note' => 'es' === $language
                ? 'Los precedentes se muestran con calidad v1.10 y similitud táctica del rival v1.11 como ejes separados. Ninguna capa modifica scores ni recomendaciones del What-if.'
                : 'Precedents expose v1.10 evidence quality and v1.11 opponent tactical similarity as separate axes. Neither layer changes What-if scores or recommendations.',
        );

        return $result;
    }
}
