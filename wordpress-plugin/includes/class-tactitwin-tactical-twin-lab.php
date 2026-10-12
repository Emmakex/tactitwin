<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Presentation/orchestration layer joining v1.27 and v1.28 on one source scan.
 */
final class TactiTwin_Tactical_Twin_Lab {
    public static function compose(
        array $team_profile,
        array $opponent_profile,
        array $scan,
        string $language = 'en'
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );

        if ( 'full-matchup-scan' !== (string) ( $scan['scenario_id'] ?? '' ) || empty( $scan['subscenarios'] ) ) {
            throw new InvalidArgumentException( 'A Full Matchup Scan with subscenarios is required.' );
        }

        $matrix = TactiTwin_Tactical_Response_Matrix::compose( $scan, $language );
        $tree   = TactiTwin_Match_State_Scenario_Tree::compose(
            $team_profile,
            $opponent_profile,
            $scan,
            $language
        );

        return array(
            'lab_id'   => 'tactical-twin-lab',
            'lab_name' => self::text( 'lab_name', $language ),
            'language' => $language,
            'team'     => $matrix['team'] ?? array(),
            'opponent' => $matrix['opponent'] ?? array(),
            'coverage' => $matrix['coverage'] ?? array(),
            'preparation_summary' => array(
                'top_response_id' => (string) ( $matrix['top_response_id'] ?? '' ),
                'top_response_name' => (string) ( $matrix['responses'][0]['response_name'] ?? '' ),
                'highest_risk_branch' => $tree['summary']['highest_risk_branch'] ?? array(),
                'highest_opportunity_branch' => $tree['summary']['highest_opportunity_branch'] ?? array(),
                'low_coverage_branches' => (int) ( $tree['summary']['low_coverage_branches'] ?? 0 ),
                'branch_count' => (int) ( $tree['summary']['branch_count'] ?? 0 ),
            ),
            'response_matrix' => $matrix,
            'scenario_tree'   => $tree,
            'method' => array(
                'type' => 'presentation-orchestration-tactical-twin-lab',
                'shared_source_scan' => true,
                'creates_new_tactical_score' => false,
                'changes_response_ranking' => false,
                'changes_branch_outputs' => false,
                'predicts_outcome' => false,
                'activates_plan' => false,
                'note' => self::text( 'method', $language ),
            ),
        );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'lab_name' => 'Tactical Twin Lab',
                'method' => 'The Lab places the v1.27 response comparison and v1.28 match-state scenario tree on one shared Full Matchup Scan. It is a preparation workspace only: it introduces no new score, outcome prediction, response re-ranking or plan activation.',
            ),
            'es' => array(
                'lab_name' => 'Laboratorio Tactical Twin',
                'method' => 'El Lab coloca la comparación de respuestas v1.27 y el árbol de estados v1.28 sobre un único Full Matchup Scan compartido. Es solo un espacio de preparación: no introduce un nuevo score, predicción de resultado, reordenación de respuestas ni activación de plan.',
            ),
        );

        return (string) ( $catalog[ $language ][ $key ] ?? $key );
    }
}
