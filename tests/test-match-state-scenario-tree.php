<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-game-state-what-if.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-context-what-if.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-tactical-response-matrix.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-match-state-scenario-tree.php';

function tt_tree_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_tree_history(
    float $minutes,
    float $xg,
    float $shots,
    float $box_entries,
    float $final_third_entries,
    float $high_pressures
): array {
    return array(
        'minutes' => $minutes,
        'matches_observed' => 3,
        'pass_completion_pct' => 82.0,
        'per_90' => array(
            'xg' => $xg,
            'shots' => $shots,
            'box_entries' => $box_entries,
            'final_third_entries' => $final_third_entries,
            'high_pressures' => $high_pressures,
            'progressive_actions' => 22.0,
        ),
    );
}

function tt_tree_profile( string $id, string $name, float $bias = 0.0 ): array {
    return array(
        'team_source_id' => $id,
        'team_name' => $name,
        'coverage' => array( 'matches' => 5 ),
        'averages' => array(
            'shots_per_match' => 12.0 + $bias,
            'xg_per_match' => 1.4 + ( $bias * 0.03 ),
            'progressive_actions_per_match' => 24.0,
            'final_third_entries_per_match' => 27.0,
            'box_entries_per_match' => 12.0,
            'high_pressures_per_match' => 14.0,
        ),
        'game_states' => array(
            'leading' => tt_tree_history( 180.0, 1.2, 10.0, 10.0, 24.0, 12.0 ),
            'drawing' => tt_tree_history( 270.0, 1.5 + ( $bias * 0.02 ), 13.0, 13.0, 29.0, 15.0 ),
            'trailing' => tt_tree_history( 135.0, 1.8 + ( $bias * 0.03 ), 16.0, 16.0, 33.0, 19.0 ),
        ),
        'manpower_states' => array(
            'equal' => tt_tree_history( 450.0, 1.5, 13.0, 13.0, 29.0, 15.0 ),
            'superior' => tt_tree_history( 90.0, 2.0, 18.0, 18.0, 36.0, 17.0 ),
            'inferior' => tt_tree_history( 90.0, 0.8, 8.0, 7.0, 18.0, 10.0 ),
        ),
    );
}

function tt_tree_scenario( string $id, string $name, float $risk, float $opportunity, string $primary ): array {
    return array(
        'scenario_id' => $id,
        'scenario_name' => $name,
        'risk' => array( 'score' => $risk, 'level' => 'medium' ),
        'opportunity' => array( 'score' => $opportunity, 'level' => 'medium' ),
        'recommendations' => array( 'primary' => $primary ),
        'evidence' => array(),
    );
}

$team = tt_tree_profile( '10', 'Our Team', 0.0 );
$opponent = tt_tree_profile( '20', 'Opponent', 1.0 );
$scan = array(
    'scenario_id' => 'full-matchup-scan',
    'scenario_name' => 'Full Matchup Scan',
    'team' => array( 'source_id' => '10', 'name' => 'Our Team' ),
    'opponent' => array( 'source_id' => '20', 'name' => 'Opponent' ),
    'coverage' => array( 'effective_matches' => 5, 'status' => 'high', 'warning' => null ),
    'risk' => array( 'average_score' => 57.0 ),
    'opportunity' => array( 'score' => 52.0 ),
    'evidence' => array( 'scenario_ranking' => array() ),
    'subscenarios' => array(
        tt_tree_scenario( 'build-up-vs-high-press', 'Build-up vs High Press', 80.0, 50.0, 'Escape the first press' ),
        tt_tree_scenario( 'opponent-side-bias', 'Opponent Side Bias', 60.0, 35.0, 'Protect the dominant side' ),
        tt_tree_scenario( 'defensive-transition-after-loss', 'Defensive Transition After Loss', 70.0, 30.0, 'Protect the first pass after loss' ),
        tt_tree_scenario( 'attack-vs-low-block', 'Attack vs Low Block', 45.0, 55.0, 'Move the low block before penetrating' ),
        tt_tree_scenario( 'exploit-opponent-weak-side', 'Exploit Opponent Weak Side', 30.0, 90.0, 'Attack the weak-side channel' ),
    ),
);

$source_scan = $scan;
$en = TactiTwin_Match_State_Scenario_Tree::compose( $team, $opponent, $scan, 'en' );
$es = TactiTwin_Match_State_Scenario_Tree::compose( $team, $opponent, $scan, 'es' );

tt_tree_assert( 'match-state-scenario-tree' === $en['tree_id'], 'tree id returned' );
tt_tree_assert( 'Match-State Scenario Tree' === $en['tree_name'], 'English tree name returned' );
tt_tree_assert( 'Árbol de escenarios por estado del partido' === $es['tree_name'], 'Spanish tree name returned' );
tt_tree_assert( 7 === count( $en['branches'] ), 'seven predefined match-state branches returned' );
tt_tree_assert( 7 === $en['summary']['branch_count'], 'summary counts seven branches' );
tt_tree_assert( 'opening-neutral' === $en['branches'][0]['branch_id'], 'opening branch is first' );
tt_tree_assert( 15 === $en['branches'][0]['hypothesis']['minute'], 'opening branch uses minute 15' );
tt_tree_assert( 'protect-lead' === $en['branches'][2]['branch_id'], 'lead-protection branch present' );
tt_tree_assert( 'leading' === $en['branches'][2]['hypothesis']['state'], 'lead branch resolves leading state' );
tt_tree_assert( 'chase-game' === $en['branches'][3]['branch_id'], 'chasing branch present' );
tt_tree_assert( 'trailing' === $en['branches'][3]['hypothesis']['state'], 'chasing branch resolves trailing state' );
tt_tree_assert( 'superior' === $en['branches'][5]['hypothesis']['manpower_state'], '11v10 branch resolves numerical superiority' );
tt_tree_assert( 'inferior' === $en['branches'][6]['hypothesis']['manpower_state'], '10v11 branch resolves numerical inferiority' );
tt_tree_assert( false === $en['method']['branch_probability_model'], 'tree explicitly has no branch probability model' );
tt_tree_assert( false === $en['method']['response_state_reranking'], 'v1.27 responses are not reranked per branch' );
tt_tree_assert( false === $en['method']['changes_tactical_scores'], 'tree does not change tactical scores' );
tt_tree_assert( false === $en['response_matrix_reference']['state_reranking_applied'], 'response reference declares no state reranking' );
tt_tree_assert( 4 === count( $en['response_matrix_reference']['responses'] ), 'all four v1.27 response packages are retained as reference' );
tt_tree_assert( $scan === $source_scan, 'scenario tree does not mutate source scan' );

$en_ids = array_column( $en['branches'], 'branch_id' );
$es_ids = array_column( $es['branches'], 'branch_id' );
tt_tree_assert( $en_ids === $es_ids, 'branch order is language-neutral' );

foreach ( $en['branches'] as $index => $branch ) {
    tt_tree_assert(
        $branch['risk']['score'] === $es['branches'][ $index ]['risk']['score'],
        'branch risk scores are language-neutral'
    );
    tt_tree_assert(
        $branch['opportunity']['score'] === $es['branches'][ $index ]['opportunity']['score'],
        'branch opportunity scores are language-neutral'
    );
    tt_tree_assert(
        isset( $branch['stress_signature']['risk_minus_opportunity'] ),
        'branch exposes descriptive risk/opportunity delta'
    );
    tt_tree_assert( ! empty( $branch['recommendations'] ), 'branch carries existing What-if recommendations' );
}

$definitions = TactiTwin_Match_State_Scenario_Tree::branch_definitions();
tt_tree_assert( 7 === count( $definitions ), 'branch definitions are explicit and inspectable' );

$invalid = false;
try {
    TactiTwin_Match_State_Scenario_Tree::compose( $team, $opponent, array( 'scenario_id' => 'other' ), 'en' );
} catch ( InvalidArgumentException $e ) {
    $invalid = true;
}
tt_tree_assert( $invalid, 'invalid source scan rejected' );

echo "Match-state scenario tree tests passed.\n";
