<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-game-state-what-if.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-context-what-if.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-tactical-response-matrix.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-match-state-scenario-tree.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-response-state-robustness.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-tactical-twin-lab.php';

function tt_twin_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_twin_history(
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

function tt_twin_profile( string $id, string $name, float $bias = 0.0 ): array {
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
            'leading' => tt_twin_history( 180.0, 1.2, 10.0, 10.0, 24.0, 12.0 ),
            'drawing' => tt_twin_history( 270.0, 1.5 + ( $bias * 0.02 ), 13.0, 13.0, 29.0, 15.0 ),
            'trailing' => tt_twin_history( 135.0, 1.8 + ( $bias * 0.03 ), 16.0, 16.0, 33.0, 19.0 ),
        ),
        'manpower_states' => array(
            'equal' => tt_twin_history( 450.0, 1.5, 13.0, 13.0, 29.0, 15.0 ),
            'superior' => tt_twin_history( 90.0, 2.0, 18.0, 18.0, 36.0, 17.0 ),
            'inferior' => tt_twin_history( 90.0, 0.8, 8.0, 7.0, 18.0, 10.0 ),
        ),
    );
}

function tt_twin_scenario( string $id, string $name, float $risk, float $opportunity, string $primary ): array {
    return array(
        'scenario_id' => $id,
        'scenario_name' => $name,
        'risk' => array( 'score' => $risk, 'level' => 'medium' ),
        'opportunity' => array( 'score' => $opportunity, 'level' => 'medium' ),
        'recommendations' => array( 'primary' => $primary ),
        'evidence' => array(),
    );
}

$team = tt_twin_profile( '10', 'Our Team', 0.0 );
$opponent = tt_twin_profile( '20', 'Opponent', 1.0 );
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
        tt_twin_scenario( 'build-up-vs-high-press', 'Build-up vs High Press', 80.0, 50.0, 'Escape the first press' ),
        tt_twin_scenario( 'opponent-side-bias', 'Opponent Side Bias', 60.0, 35.0, 'Protect the dominant side' ),
        tt_twin_scenario( 'defensive-transition-after-loss', 'Defensive Transition After Loss', 70.0, 30.0, 'Protect the first pass after loss' ),
        tt_twin_scenario( 'attack-vs-low-block', 'Attack vs Low Block', 45.0, 55.0, 'Move the low block before penetrating' ),
        tt_twin_scenario( 'exploit-opponent-weak-side', 'Exploit Opponent Weak Side', 30.0, 90.0, 'Attack the weak-side channel' ),
    ),
);

$source_scan = $scan;
$en = TactiTwin_Tactical_Twin_Lab::compose( $team, $opponent, $scan, 'en' );
$es = TactiTwin_Tactical_Twin_Lab::compose( $team, $opponent, $scan, 'es' );

tt_twin_assert( 'tactical-twin-lab' === $en['lab_id'], 'lab id returned' );
tt_twin_assert( 'Tactical Twin Lab' === $en['lab_name'], 'English lab name returned' );
tt_twin_assert( 'Laboratorio Tactical Twin' === $es['lab_name'], 'Spanish lab name returned' );
tt_twin_assert( 4 === count( $en['response_matrix']['responses'] ), 'lab exposes all four response packages' );
tt_twin_assert( 7 === count( $en['scenario_tree']['branches'] ), 'lab exposes all seven match-state branches' );
tt_twin_assert( 4 === count( $en['response_robustness']['responses'] ), 'lab exposes robustness for all four responses' );
tt_twin_assert( 7 === count( $en['response_robustness']['branches'] ), 'lab exposes robustness across all seven branches' );
tt_twin_assert( true === $en['method']['shared_source_scan'], 'lab declares one shared source scan' );
tt_twin_assert( false === $en['method']['creates_new_tactical_score'], 'lab creates no new tactical score' );
tt_twin_assert( false === $en['method']['changes_response_ranking'], 'lab preserves baseline response ranking' );
tt_twin_assert( true === $en['method']['adds_state_rank_diagnostics'], 'lab declares state rank diagnostics' );
tt_twin_assert( false === $en['method']['changes_branch_outputs'], 'lab preserves branch outputs' );
tt_twin_assert( false === $en['method']['predicts_outcome'], 'lab explicitly rejects outcome prediction' );
tt_twin_assert( false === $en['method']['activates_plan'], 'lab cannot activate a plan' );
tt_twin_assert( $scan === $source_scan, 'lab does not mutate source scan' );

tt_twin_assert(
    $en['response_matrix']['top_response_id'] === $en['preparation_summary']['top_response_id'],
    'summary top response matches response matrix'
);
tt_twin_assert(
    $en['response_matrix']['responses'][0]['response_name'] === $en['preparation_summary']['top_response_name'],
    'summary top response name matches response matrix'
);
tt_twin_assert(
    $en['response_robustness']['summary']['most_robust_response']['response_id'] === $en['preparation_summary']['most_robust_response_id'],
    'summary most robust response is sourced from robustness diagnostics'
);
tt_twin_assert(
    $en['response_robustness']['summary']['leader_change_count'] === $en['preparation_summary']['leader_change_count'],
    'summary leader change count is sourced from robustness diagnostics'
);
tt_twin_assert(
    $en['scenario_tree']['summary']['highest_risk_branch'] === $en['preparation_summary']['highest_risk_branch'],
    'summary highest-risk branch is sourced from scenario tree'
);
tt_twin_assert(
    $en['scenario_tree']['summary']['highest_opportunity_branch'] === $en['preparation_summary']['highest_opportunity_branch'],
    'summary highest-opportunity branch is sourced from scenario tree'
);
tt_twin_assert(
    $en['scenario_tree']['summary']['branch_count'] === $en['preparation_summary']['branch_count'],
    'summary branch count is sourced from scenario tree'
);

tt_twin_assert(
    $en['response_matrix']['team'] === $en['team'] && $en['scenario_tree']['team'] === $en['team'] && $en['response_robustness']['team'] === $en['team'],
    'matrix, tree and robustness share the same team identity'
);
tt_twin_assert(
    $en['response_matrix']['opponent'] === $en['opponent'] && $en['scenario_tree']['opponent'] === $en['opponent'] && $en['response_robustness']['opponent'] === $en['opponent'],
    'matrix, tree and robustness share the same opponent identity'
);

tt_twin_assert(
    $en['preparation_summary']['top_response_id'] === $es['preparation_summary']['top_response_id'],
    'top response is language-neutral'
);
tt_twin_assert(
    $en['preparation_summary']['most_robust_response_id'] === $es['preparation_summary']['most_robust_response_id'],
    'most robust response is language-neutral'
);
tt_twin_assert(
    array_column( $en['scenario_tree']['branches'], 'branch_id' ) === array_column( $es['scenario_tree']['branches'], 'branch_id' ),
    'branch order is language-neutral'
);

$en_response_scores = array();
foreach ( $en['response_matrix']['responses'] as $response ) {
    $en_response_scores[ $response['response_id'] ] = $response['fit_score'];
}
$es_response_scores = array();
foreach ( $es['response_matrix']['responses'] as $response ) {
    $es_response_scores[ $response['response_id'] ] = $response['fit_score'];
}
tt_twin_assert( $en_response_scores === $es_response_scores, 'response fit values are language-neutral' );

$en_robustness = array();
foreach ( $en['response_robustness']['responses'] as $response ) {
    $en_robustness[ $response['response_id'] ] = array(
        $response['first_place_count'],
        $response['top_two_count'],
        $response['mean_rank'],
        $response['worst_rank'],
    );
}
$es_robustness = array();
foreach ( $es['response_robustness']['responses'] as $response ) {
    $es_robustness[ $response['response_id'] ] = array(
        $response['first_place_count'],
        $response['top_two_count'],
        $response['mean_rank'],
        $response['worst_rank'],
    );
}
tt_twin_assert( $en_robustness === $es_robustness, 'robustness ranks are language-neutral' );

foreach ( $en['scenario_tree']['branches'] as $index => $branch ) {
    tt_twin_assert(
        $branch['risk']['score'] === $es['scenario_tree']['branches'][ $index ]['risk']['score'],
        'branch risk values are language-neutral'
    );
    tt_twin_assert(
        $branch['opportunity']['score'] === $es['scenario_tree']['branches'][ $index ]['opportunity']['score'],
        'branch opportunity values are language-neutral'
    );
}

$invalid = false;
try {
    TactiTwin_Tactical_Twin_Lab::compose( $team, $opponent, array( 'scenario_id' => 'other' ), 'en' );
} catch ( InvalidArgumentException $e ) {
    $invalid = true;
}
tt_twin_assert( $invalid, 'invalid source scan rejected' );

echo "Tactical Twin Lab tests passed.\n";
