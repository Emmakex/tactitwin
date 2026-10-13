<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Presentation/orchestration layer joining v1.27, v1.28, v1.30 and v1.31 on
 * one source Full Matchup Scan.
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
        $robustness = TactiTwin_Response_State_Robustness::compose( $matrix, $tree, $language );
        $switch_playbook = TactiTwin_Response_Switch_Playbook::compose( $matrix, $tree, $robustness, $language );

        $most_robust = $robustness['summary']['most_robust_response'] ?? array();

        return array(
            'lab_id'   => 'tactical-twin-lab',
            'lab_name' => self::text( 'lab_name', $language ),
            'language' => $language,
            'team'     => $matrix['team'] ?? array(),
            'opponent' => $matrix['opponent'] ?? array(),
            'coverage' => $scan['coverage'] ?? $matrix['coverage'] ?? array(),
            'preparation_summary' => array(
                'top_response_id' => (string) ( $matrix['top_response_id'] ?? '' ),
                'top_response_name' => (string) ( $matrix['responses'][0]['response_name'] ?? '' ),
                'most_robust_response_id' => (string) ( $most_robust['response_id'] ?? '' ),
                'most_robust_response_name' => (string) ( $most_robust['response_name'] ?? '' ),
                'most_robust_label' => (string) ( $most_robust['robustness_label'] ?? '' ),
                'leader_change_count' => (int) ( $robustness['summary']['leader_change_count'] ?? 0 ),
                'switch_candidate_count' => (int) ( $switch_playbook['summary']['switch_candidate_count'] ?? 0 ),
                'coverage_caution_switches' => (int) ( $switch_playbook['summary']['coverage_caution_count'] ?? 0 ),
                'earliest_switch_candidate' => $switch_playbook['summary']['earliest_switch_candidate'] ?? array(),
                'highest_risk_branch' => $tree['summary']['highest_risk_branch'] ?? array(),
                'highest_opportunity_branch' => $tree['summary']['highest_opportunity_branch'] ?? array(),
                'low_coverage_branches' => (int) ( $tree['summary']['low_coverage_branches'] ?? 0 ),
                'branch_count' => (int) ( $tree['summary']['branch_count'] ?? 0 ),
            ),
            'response_matrix' => $matrix,
            'scenario_tree'   => $tree,
            'response_robustness' => $robustness,
            'response_switch_playbook' => $switch_playbook,
            'method' => array(
                'type' => 'presentation-orchestration-tactical-twin-lab',
                'shared_source_scan' => true,
                'creates_new_tactical_score' => false,
                'changes_response_ranking' => false,
                'adds_state_rank_diagnostics' => true,
                'adds_manual_switch_candidates' => true,
                'automatic_response_activation' => false,
                'changes_branch_outputs' => false,
                'predicts_outcome' => false,
                'activates_plan' => false,
                'coverage_transparency' => 'v1.35-per-side-auditable-samples',
                'note' => self::text( 'method', $language ),
            ),
        );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'lab_name' => 'Tactical Twin Lab',
                'method' => 'The Lab places the v1.27 response comparison, v1.28 match-state scenario tree, v1.30 response-rank robustness diagnostics and v1.31 manual switch playbook on one shared Full Matchup Scan. Baseline ranking and branch outputs are preserved, and every switch remains a staff review candidate rather than an automatic activation.',
            ),
            'es' => array(
                'lab_name' => 'Laboratorio Tactical Twin',
                'method' => 'El Lab coloca la comparación de respuestas v1.27, el árbol de estados v1.28, el diagnóstico v1.30 de robustez del ranking y el playbook manual de cambios v1.31 sobre un único Full Matchup Scan compartido. Se conservan el ranking base y las salidas de las ramas, y cada cambio sigue siendo un candidato para revisión del staff, nunca una activación automática.',
            ),
        );

        return (string) ( $catalog[ $language ][ $key ] ?? $key );
    }
}
