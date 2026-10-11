<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-match-plan.php';

function tt_match_plan_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_plan_scenario( string $id, string $name, float $risk, float $opportunity, array $recommendations, array $evidence = array() ): array {
    return array(
        'scenario_id' => $id,
        'scenario_name' => $name,
        'risk' => array( 'score' => $risk, 'level' => $risk >= 65 ? 'high' : ( $risk >= 45 ? 'medium' : 'low' ) ),
        'opportunity' => array( 'score' => $opportunity, 'level' => $opportunity >= 65 ? 'high' : ( $opportunity >= 45 ? 'medium' : 'low' ) ),
        'recommendations' => $recommendations,
        'evidence' => $evidence,
    );
}

$subscenarios = array(
    tt_plan_scenario(
        'build-up-vs-high-press', 'Build-up vs High Press', 80.0, 50.0,
        array( 'primary' => 'High press primary', 'secondary' => 'High press secondary', 'attacking_outlet' => 'High press outlet' )
    ),
    tt_plan_scenario(
        'opponent-side-bias', 'Opponent Side Bias', 60.0, 35.0,
        array( 'primary' => 'Protect right side', 'pressing_orientation' => 'Press away from left', 'cover_structure' => 'Cover wide duel', 'training_focus' => 'Train defensive shift' ),
        array( 'opponent_dominant_channel' => 'left' )
    ),
    tt_plan_scenario(
        'defensive-transition-after-loss', 'Defensive Transition After Loss', 70.0, 30.0,
        array( 'primary' => 'Keep rest defence', 'counterpress' => 'Counterpress 5-8 seconds', 'loss_zone' => 'Train middle-third loss', 'opponent_threat' => 'Deny first forward pass' ),
        array( 'our_dangerous_turnover_rate' => 12.5 )
    ),
    tt_plan_scenario(
        'attack-vs-low-block', 'Attack vs Low Block', 45.0, 55.0,
        array( 'primary' => 'Move the low block', 'circulation' => 'Switch play', 'rest_defence' => 'Protect clearances' )
    ),
    tt_plan_scenario(
        'exploit-opponent-weak-side', 'Exploit Opponent Weak Side', 30.0, 90.0,
        array( 'primary' => 'Attack right channel', 'creation_pattern' => 'Attract then switch', 'switch_trigger' => 'Switch on pressure', 'validation' => 'Validate right side early' ),
        array( 'best_matchup_channel' => 'right' )
    ),
);

$scan = array(
    'scenario_id' => 'full-matchup-scan',
    'scenario_name' => 'Full Matchup Scan',
    'team' => array( 'source_id' => '10', 'name' => 'Our Team' ),
    'opponent' => array( 'source_id' => '20', 'name' => 'Opponent' ),
    'coverage' => array( 'effective_matches' => 5, 'status' => 'high', 'warning' => null ),
    'risk' => array( 'average_score' => 57.0 ),
    'opportunity' => array( 'score' => 52.0 ),
    'evidence' => array( 'scenario_ranking' => array() ),
    'subscenarios' => $subscenarios,
);

$en = TactiTwin_Match_Plan::compose( $scan, 'en' );
$es = TactiTwin_Match_Plan::compose( $scan, 'es' );

tt_match_plan_assert( 'match-plan' === $en['plan_id'], 'plan id returned' );
tt_match_plan_assert( 'Match Plan' === $en['plan_name'], 'English plan name returned' );
tt_match_plan_assert( 'Plan de partido' === $es['plan_name'], 'Spanish plan name returned' );
tt_match_plan_assert( 'build-up-vs-high-press' === $en['summary']['highest_risk_scenario']['scenario_id'], 'highest risk scenario selected' );
tt_match_plan_assert( 'exploit-opponent-weak-side' === $en['summary']['best_opportunity_scenario']['scenario_id'], 'best opportunity selected' );
tt_match_plan_assert( 3 === count( $en['priorities'] ), 'top three priorities returned' );
tt_match_plan_assert( 1 === $en['priorities'][0]['rank'], 'priority ranking starts at one' );
tt_match_plan_assert( 'build-up-vs-high-press' === $en['priorities'][0]['scenario_id'], 'highest weighted priority ranked first' );
tt_match_plan_assert( ! empty( $en['phases']['pre_match_training'] ), 'pre-match training populated' );
tt_match_plan_assert( ! empty( $en['phases']['opening_15_minutes'] ), 'opening phase populated' );
tt_match_plan_assert( ! empty( $en['phases']['in_possession'] ), 'in-possession phase populated' );
tt_match_plan_assert( ! empty( $en['phases']['out_of_possession'] ), 'out-of-possession phase populated' );
tt_match_plan_assert( ! empty( $en['phases']['after_loss'] ), 'after-loss phase populated' );
tt_match_plan_assert( ! empty( $en['phases']['after_recovery'] ), 'after-recovery phase populated' );
tt_match_plan_assert( 4 === count( $en['decision_triggers'] ), 'four decision triggers returned' );
tt_match_plan_assert( false !== strpos( $es['summary']['headline'], 'Principal preocupación' ), 'Spanish headline returned' );
tt_match_plan_assert( $en['priorities'][0]['priority_score'] === $es['priorities'][0]['priority_score'], 'priority score is language-neutral' );
tt_match_plan_assert( $en['summary']['highest_risk_scenario']['score'] === $es['summary']['highest_risk_scenario']['score'], 'risk logic is language-neutral' );
tt_match_plan_assert( 'deterministic-match-plan-composition' === $en['method']['type'], 'composition method declared' );

$invalid = false;
try {
    TactiTwin_Match_Plan::compose( array( 'scenario_id' => 'other' ), 'en' );
} catch ( InvalidArgumentException $e ) {
    $invalid = true;
}
tt_match_plan_assert( $invalid, 'invalid input rejected' );

echo "Match plan tests passed.\n";
