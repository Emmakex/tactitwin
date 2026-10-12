<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-tactical-response-matrix.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-response-state-robustness.php';

function tt_robust_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_robust_scenario( string $id, string $name, float $risk, float $opportunity ): array {
    return array(
        'scenario_id' => $id,
        'scenario_name' => $name,
        'risk' => array( 'score' => $risk, 'level' => 'medium' ),
        'opportunity' => array( 'score' => $opportunity, 'level' => 'medium' ),
        'recommendations' => array( 'primary' => 'Instruction ' . $id ),
        'evidence' => array(),
    );
}

$scan = array(
    'scenario_id' => 'full-matchup-scan',
    'team' => array( 'source_id' => '10', 'name' => 'Our Team' ),
    'opponent' => array( 'source_id' => '20', 'name' => 'Opponent' ),
    'coverage' => array( 'effective_matches' => 5, 'status' => 'high', 'warning' => null ),
    'subscenarios' => array(
        tt_robust_scenario( 'build-up-vs-high-press', 'Build-up vs High Press', 80.0, 50.0 ),
        tt_robust_scenario( 'opponent-side-bias', 'Opponent Side Bias', 60.0, 35.0 ),
        tt_robust_scenario( 'defensive-transition-after-loss', 'Defensive Transition After Loss', 70.0, 30.0 ),
        tt_robust_scenario( 'attack-vs-low-block', 'Attack vs Low Block', 45.0, 55.0 ),
        tt_robust_scenario( 'exploit-opponent-weak-side', 'Exploit Opponent Weak Side', 30.0, 90.0 ),
    ),
);

function tt_robust_tree( string $language ): array {
    $names = array(
        'opening-neutral' => 'Opening neutral',
        'second-half-neutral' => 'Second-half neutral',
        'protect-lead' => 'Protect lead',
        'chase-game' => 'Chase game',
        'late-draw' => 'Late draw',
        'numerical-superiority' => 'Numerical superiority',
        'numerical-inferiority' => 'Numerical inferiority',
    );

    $branches = array();
    foreach ( array_keys( TactiTwin_Response_State_Robustness::branch_emphasis_profiles() ) as $branch_id ) {
        $branches[] = array(
            'branch_id' => $branch_id,
            'branch_name' => ( 'es' === $language ? 'ES ' : '' ) . $names[ $branch_id ],
            'purpose' => 'Purpose ' . $branch_id,
            'hypothesis' => array( 'minute' => 60, 'our_score' => 0, 'opponent_score' => 0, 'our_players' => 11, 'opponent_players' => 11 ),
            'risk' => array( 'score' => 50.0 ),
            'opportunity' => array( 'score' => 50.0 ),
            'coverage' => array( 'status' => 'high' ),
        );
    }

    return array(
        'tree_id' => 'match-state-scenario-tree',
        'branches' => $branches,
    );
}

$matrix_en = TactiTwin_Tactical_Response_Matrix::compose( $scan, 'en' );
$matrix_es = TactiTwin_Tactical_Response_Matrix::compose( $scan, 'es' );
$matrix_source = $matrix_en;

$en = TactiTwin_Response_State_Robustness::compose( $matrix_en, tt_robust_tree( 'en' ), 'en' );
$es = TactiTwin_Response_State_Robustness::compose( $matrix_es, tt_robust_tree( 'es' ), 'es' );

tt_robust_assert( 'response-state-robustness' === $en['robustness_id'], 'robustness id returned' );
tt_robust_assert( 'Response Robustness Across Match States' === $en['robustness_name'], 'English name returned' );
tt_robust_assert( 'Robustez de respuestas entre estados del partido' === $es['robustness_name'], 'Spanish name returned' );
tt_robust_assert( 7 === count( $en['branches'] ), 'seven branches evaluated' );
tt_robust_assert( 4 === count( $en['responses'] ), 'four responses aggregated' );
tt_robust_assert( $matrix_en === $matrix_source, 'source matrix remains immutable' );
tt_robust_assert( false === $en['method']['robustness_score_created'], 'no aggregate robustness score created' );
tt_robust_assert( false === $en['method']['branch_probabilities'], 'no branch probabilities created' );
tt_robust_assert( false === $en['method']['outcome_prediction'], 'no outcome prediction created' );
tt_robust_assert( false === $en['method']['changes_upstream_scores'], 'upstream scores remain unchanged' );
tt_robust_assert( false === $en['method']['activates_plan'], 'robustness cannot activate plan' );

foreach ( TactiTwin_Response_State_Robustness::branch_emphasis_profiles() as $branch_id => $profile ) {
    tt_robust_assert( abs( array_sum( $profile ) - 1.0 ) < 0.0001, $branch_id . ' emphasis sums to one' );
}

$leaders = array();
foreach ( $en['branches'] as $branch ) {
    $leaders[ $branch['branch_id'] ] = $branch['top_response_id'];
    tt_robust_assert( 4 === count( $branch['ranked_responses'] ), $branch['branch_id'] . ' ranks all responses' );
    tt_robust_assert( $branch['ranked_responses'][0]['state_fit'] <= 100.0, $branch['branch_id'] . ' state fit bounded at 100' );
}

tt_robust_assert( 'balanced-control' === $leaders['opening-neutral'], 'opening neutral favors balanced control coverage' );
tt_robust_assert( 'balanced-control' === $leaders['second-half-neutral'], 'second-half neutral favors balanced control coverage' );
tt_robust_assert( 'transition-security' === $leaders['protect-lead'], 'protect-lead favors transition security' );
tt_robust_assert( 'width-and-switch' === $leaders['chase-game'], 'chase-game favors width and switch' );
tt_robust_assert( 'balanced-control' === $leaders['late-draw'], 'late draw favors balanced control' );
tt_robust_assert( 'width-and-switch' === $leaders['numerical-superiority'], 'numerical superiority favors width and switch' );
tt_robust_assert( 'transition-security' === $leaders['numerical-inferiority'], 'numerical inferiority favors transition security' );

tt_robust_assert( 'press-resistant-build-up' === $en['baseline_top_response_id'], 'baseline top response remains v1.27 result' );
tt_robust_assert( 7 === $en['summary']['leader_change_count'], 'all synthetic branches expose a baseline leader change' );
tt_robust_assert( 'balanced-control' === $en['summary']['most_robust_response']['response_id'], 'balanced control is most robust in synthetic case' );
tt_robust_assert( 'stable-competitive' === $en['summary']['most_robust_response']['robustness'], 'synthetic most robust response has expected label' );

$en_rank_signature = array();
foreach ( $en['responses'] as $response ) {
    $en_rank_signature[ $response['response_id'] ] = array(
        $response['first_place_count'],
        $response['top_two_count'],
        $response['mean_rank'],
        $response['worst_rank'],
    );
}
$es_rank_signature = array();
foreach ( $es['responses'] as $response ) {
    $es_rank_signature[ $response['response_id'] ] = array(
        $response['first_place_count'],
        $response['top_two_count'],
        $response['mean_rank'],
        $response['worst_rank'],
    );
}
tt_robust_assert( $en_rank_signature === $es_rank_signature, 'robustness mathematics is language-neutral' );

$invalid = false;
try {
    TactiTwin_Response_State_Robustness::compose( array(), tt_robust_tree( 'en' ), 'en' );
} catch ( InvalidArgumentException $e ) {
    $invalid = true;
}
tt_robust_assert( $invalid, 'invalid matrix rejected' );

echo "Response state robustness tests passed.\n";
