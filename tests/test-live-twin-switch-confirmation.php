<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-live-twin-switch-confirmation.php';

function tt_live_twin_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_live_twin_item( string $branch_id, string $decision, string $target_id, string $target_name, int $minute, int $our_score, int $opp_score, int $our_players = 11, int $opp_players = 11 ): array {
    return array(
        'switch_id' => 'response-switch-' . $branch_id,
        'branch_id' => $branch_id,
        'branch_name' => $branch_id,
        'decision' => $decision,
        'decision_label' => $decision,
        'baseline_response' => array( 'response_id'=>'balanced-control', 'response_name'=>'Balanced Control' ),
        'target_response' => array( 'response_id'=>$target_id, 'response_name'=>$target_name ),
        'runner_up_response' => array( 'response_id'=>'balanced-control', 'response_name'=>'Balanced Control' ),
        'state_fit_margin' => 8.5,
        'trigger' => array(
            'minute'=>$minute,
            'our_score'=>$our_score,
            'opponent_score'=>$opp_score,
            'our_players'=>$our_players,
            'opponent_players'=>$opp_players,
        ),
        'validation_signals' => array( 'Validate prepared trigger' ),
        'first_branch_recommendation' => 'Use prepared instruction',
        'coverage_caution' => false,
    );
}

$items = array(
    tt_live_twin_item( 'opening-neutral', 'retain-baseline', 'balanced-control', 'Balanced Control', 15, 0, 0 ),
    tt_live_twin_item( 'second-half-neutral', 'retain-baseline', 'balanced-control', 'Balanced Control', 60, 0, 0 ),
    tt_live_twin_item( 'protect-lead', 'switch-candidate', 'transition-security', 'Transition Security', 70, 1, 0 ),
    tt_live_twin_item( 'chase-game', 'switch-candidate', 'width-switch', 'Width & Switch', 70, 0, 1 ),
    tt_live_twin_item( 'late-draw', 'retain-baseline', 'balanced-control', 'Balanced Control', 80, 0, 0 ),
    tt_live_twin_item( 'numerical-superiority', 'switch-candidate', 'width-switch', 'Width & Switch', 60, 0, 0, 11, 10 ),
    tt_live_twin_item( 'numerical-inferiority', 'switch-candidate', 'transition-security', 'Transition Security', 60, 0, 0, 10, 11 ),
);

$twin = array(
    'lab_id' => 'tactical-twin-lab',
    'response_switch_playbook' => array(
        'playbook_id' => 'response-switch-playbook',
        'baseline_response' => array( 'response_id'=>'balanced-control', 'response_name'=>'Balanced Control' ),
        'items' => $items,
    ),
);
$source = $twin;

$protect = TactiTwin_Live_Twin_Switch_Confirmation::review(
    $twin,
    array( 'minute'=>72, 'our_score'=>2, 'opponent_score'=>1, 'our_players'=>11, 'opponent_players'=>11 ),
    'confirm-prepared',
    'Staff confirms',
    'en'
);
tt_live_twin_assert( 'protect-lead' === $protect['state_resolution']['branch_id'], 'one-goal lead after minute 60 resolves protect-lead' );
tt_live_twin_assert( 'windowed-prepared-state' === $protect['state_resolution']['match_type'], 'non-exact one-goal lead is a windowed prepared state' );
tt_live_twin_assert( 'confirmed-prepared-switch' === $protect['staff_decision']['status'], 'prepared switch can be explicitly confirmed' );
tt_live_twin_assert( 'transition-security' === $protect['staff_decision']['selected_response']['response_id'], 'confirmed switch selects prepared target response' );

$reject = TactiTwin_Live_Twin_Switch_Confirmation::review(
    $twin,
    array( 'minute'=>70, 'our_score'=>0, 'opponent_score'=>1, 'our_players'=>11, 'opponent_players'=>11 ),
    'retain-baseline',
    '',
    'en'
);
tt_live_twin_assert( 'chase-game' === $reject['state_resolution']['branch_id'], 'one-goal deficit resolves chase-game' );
tt_live_twin_assert( 'rejected-prepared-switch' === $reject['staff_decision']['status'], 'staff can reject a prepared switch' );
tt_live_twin_assert( 'balanced-control' === $reject['staff_decision']['selected_response']['response_id'], 'rejected switch retains baseline response' );

$superior = TactiTwin_Live_Twin_Switch_Confirmation::review(
    $twin,
    array( 'minute'=>34, 'our_score'=>1, 'opponent_score'=>0, 'our_players'=>11, 'opponent_players'=>10 ),
    'defer',
    '',
    'en'
);
tt_live_twin_assert( 'numerical-superiority' === $superior['state_resolution']['branch_id'], 'manpower state has structural precedence over score phase' );
tt_live_twin_assert( 'structural-manpower-analogy' === $superior['state_resolution']['match_type'], 'manpower mapping is labelled as structural analogy' );

$late = TactiTwin_Live_Twin_Switch_Confirmation::review(
    $twin,
    array( 'minute'=>83, 'our_score'=>1, 'opponent_score'=>1, 'our_players'=>11, 'opponent_players'=>11 ),
    'confirm-prepared',
    '',
    'en'
);
tt_live_twin_assert( 'late-draw' === $late['state_resolution']['branch_id'], 'late level score resolves late-draw' );
tt_live_twin_assert( 'confirmed-prepared-baseline' === $late['staff_decision']['status'], 'staff can explicitly confirm prepared baseline retention' );

$unprepared = TactiTwin_Live_Twin_Switch_Confirmation::review(
    $twin,
    array( 'minute'=>35, 'our_score'=>0, 'opponent_score'=>0, 'our_players'=>11, 'opponent_players'=>11 ),
    'confirm-prepared',
    '',
    'en'
);
tt_live_twin_assert( 'unavailable' === $unprepared['state_resolution']['status'], 'unprepared state remains unavailable' );
tt_live_twin_assert( 'no-prepared-branch' === $unprepared['staff_decision']['status'], 'unprepared state cannot force a confirmation' );
tt_live_twin_assert( null === $unprepared['staff_decision']['selected_response'], 'unprepared state selects no response' );

tt_live_twin_assert( false === $protect['method']['automatic_activation'], 'review never activates automatically' );
tt_live_twin_assert( false === $protect['method']['changes_active_plan'], 'review never changes active plan' );
tt_live_twin_assert( false === $protect['method']['changes_plan_abc'], 'review never changes Plan A/B/C' );
tt_live_twin_assert( $source === $twin, 'review does not mutate frozen Twin' );

$es = TactiTwin_Live_Twin_Switch_Confirmation::review(
    $twin,
    array( 'minute'=>72, 'our_score'=>2, 'opponent_score'=>1, 'our_players'=>11, 'opponent_players'=>11 ),
    'confirm-prepared',
    '',
    'es'
);
tt_live_twin_assert( $protect['state_resolution']['branch_id'] === $es['state_resolution']['branch_id'], 'branch resolution is language-neutral' );
tt_live_twin_assert( $protect['staff_decision']['selected_response']['response_id'] === $es['staff_decision']['selected_response']['response_id'], 'staff response selection is language-neutral' );

echo "Live Twin switch confirmation tests passed.\n";
