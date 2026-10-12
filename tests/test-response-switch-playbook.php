<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-tactical-response-matrix.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-response-state-robustness.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-response-switch-playbook.php';

function tt_switch_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_switch_scenario( string $id, float $risk, float $opportunity ): array {
    return array(
        'scenario_id' => $id,
        'scenario_name' => $id,
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
        tt_switch_scenario( 'build-up-vs-high-press', 80.0, 50.0 ),
        tt_switch_scenario( 'opponent-side-bias', 60.0, 35.0 ),
        tt_switch_scenario( 'defensive-transition-after-loss', 70.0, 30.0 ),
        tt_switch_scenario( 'attack-vs-low-block', 45.0, 55.0 ),
        tt_switch_scenario( 'exploit-opponent-weak-side', 30.0, 90.0 ),
    ),
);

function tt_switch_tree( string $language ): array {
    $minutes = array(
        'opening-neutral' => 15,
        'second-half-neutral' => 60,
        'protect-lead' => 70,
        'chase-game' => 70,
        'late-draw' => 80,
        'numerical-superiority' => 60,
        'numerical-inferiority' => 60,
    );
    $branches = array();
    foreach ( array_keys( TactiTwin_Response_State_Robustness::branch_emphasis_profiles() ) as $branch_id ) {
        $is_low = 'numerical-inferiority' === $branch_id;
        $branches[] = array(
            'branch_id' => $branch_id,
            'branch_name' => ( 'es' === $language ? 'ES ' : '' ) . $branch_id,
            'purpose' => 'Prepare ' . $branch_id,
            'hypothesis' => array(
                'minute' => $minutes[ $branch_id ],
                'our_score' => 'protect-lead' === $branch_id ? 1 : 0,
                'opponent_score' => 'chase-game' === $branch_id ? 1 : 0,
                'our_players' => 'numerical-inferiority' === $branch_id ? 10 : 11,
                'opponent_players' => 'numerical-superiority' === $branch_id ? 10 : 11,
                'state' => 'protect-lead' === $branch_id ? 'leading' : ( 'chase-game' === $branch_id ? 'trailing' : 'drawing' ),
                'manpower_state' => 'numerical-inferiority' === $branch_id ? 'inferior' : ( 'numerical-superiority' === $branch_id ? 'superior' : 'equal' ),
                'headline' => ( 'es' === $language ? 'Estado ' : 'State ' ) . $branch_id,
            ),
            'risk' => array( 'score' => 55.0 ),
            'opportunity' => array( 'score' => 50.0 ),
            'coverage' => array(
                'status' => 'high',
                'manpower' => array( 'status' => $is_low ? 'low' : 'high' ),
            ),
            'recommendations' => array( 'First recommendation ' . $branch_id ),
            'decision_triggers' => array(
                array( 'trigger' => 'Observe ' . $branch_id, 'action' => 'Validate response' ),
                array( 'trigger' => 'Second signal ' . $branch_id, 'action' => 'Review structure' ),
            ),
        );
    }
    return array(
        'tree_id' => 'match-state-scenario-tree',
        'team' => array( 'source_id' => '10', 'name' => 'Our Team' ),
        'opponent' => array( 'source_id' => '20', 'name' => 'Opponent' ),
        'branches' => $branches,
    );
}

$matrix_en = TactiTwin_Tactical_Response_Matrix::compose( $scan, 'en' );
$matrix_es = TactiTwin_Tactical_Response_Matrix::compose( $scan, 'es' );
$tree_en = tt_switch_tree( 'en' );
$tree_es = tt_switch_tree( 'es' );
$robustness_en = TactiTwin_Response_State_Robustness::compose( $matrix_en, $tree_en, 'en' );
$robustness_es = TactiTwin_Response_State_Robustness::compose( $matrix_es, $tree_es, 'es' );

$matrix_source = $matrix_en;
$tree_source = $tree_en;
$robustness_source = $robustness_en;

$en = TactiTwin_Response_Switch_Playbook::compose( $matrix_en, $tree_en, $robustness_en, 'en' );
$es = TactiTwin_Response_Switch_Playbook::compose( $matrix_es, $tree_es, $robustness_es, 'es' );

tt_switch_assert( 'response-switch-playbook' === $en['playbook_id'], 'playbook id returned' );
tt_switch_assert( 'Response Switch Playbook' === $en['playbook_name'], 'English name returned' );
tt_switch_assert( 'Playbook de cambios de respuesta' === $es['playbook_name'], 'Spanish name returned' );
tt_switch_assert( 7 === count( $en['items'] ), 'seven branch review points returned' );
tt_switch_assert( 7 === $en['summary']['switch_candidate_count'], 'synthetic case exposes seven switch candidates' );
tt_switch_assert( 0 === $en['summary']['retain_baseline_count'], 'synthetic case retains baseline in zero branches' );
tt_switch_assert( 1 === $en['summary']['coverage_caution_count'], 'one low-coverage switch is flagged' );
tt_switch_assert( 'press-resistant-build-up' === $en['baseline_response']['response_id'], 'v1.27 baseline response preserved' );
tt_switch_assert( 'opening-neutral' === $en['summary']['earliest_switch_candidate']['branch_id'], 'earliest switch candidate identified' );
tt_switch_assert( 15 === $en['summary']['earliest_switch_candidate']['minute'], 'earliest switch minute preserved' );
tt_switch_assert( 'balanced-control' === $en['summary']['earliest_switch_candidate']['target_response_id'], 'earliest switch target derived from robustness leader' );

tt_switch_assert( false === $en['method']['automatic_activation'], 'playbook never auto activates' );
tt_switch_assert( true === $en['method']['manual_confirmation_required'], 'playbook requires manual confirmation' );
tt_switch_assert( false === $en['method']['creates_new_tactical_score'], 'playbook creates no tactical score' );
tt_switch_assert( false === $en['method']['predicts_outcome'], 'playbook predicts no outcome' );
tt_switch_assert( false === $en['method']['changes_plan_abc'], 'playbook does not change Plan A/B/C' );

$targets = array();
foreach ( $en['items'] as $item ) {
    $targets[ $item['branch_id'] ] = $item['target_response']['response_id'];
    tt_switch_assert( 'switch-candidate' === $item['decision'], $item['branch_id'] . ' is a switch candidate' );
    tt_switch_assert( true === $item['manual_confirmation_required'], $item['branch_id'] . ' requires staff confirmation' );
    tt_switch_assert( false === $item['automatic_activation'], $item['branch_id'] . ' cannot auto activate' );
    tt_switch_assert( $item['state_fit_margin'] >= 0.0, $item['branch_id'] . ' margin is non-negative' );
    tt_switch_assert( ! empty( $item['validation_signals'] ), $item['branch_id'] . ' exposes existing validation signals' );
}

tt_switch_assert( 'transition-security' === $targets['protect-lead'], 'protect lead switch targets transition security' );
tt_switch_assert( 'width-and-switch' === $targets['chase-game'], 'chase game switch targets width and switch' );
tt_switch_assert( 'width-and-switch' === $targets['numerical-superiority'], 'numerical superiority switch targets width and switch' );
tt_switch_assert( 'transition-security' === $targets['numerical-inferiority'], 'numerical inferiority switch targets transition security' );

$inferior = null;
foreach ( $en['items'] as $item ) {
    if ( 'numerical-inferiority' === $item['branch_id'] ) {
        $inferior = $item;
        break;
    }
}
tt_switch_assert( is_array( $inferior ) && true === $inferior['coverage_caution'], 'low manpower coverage is surfaced as caution' );
tt_switch_assert( ! empty( $inferior['coverage_caution_note'] ), 'coverage caution has staff-facing note' );

tt_switch_assert( $matrix_en === $matrix_source, 'source matrix is immutable' );
tt_switch_assert( $tree_en === $tree_source, 'source tree is immutable' );
tt_switch_assert( $robustness_en === $robustness_source, 'source robustness is immutable' );

$en_signature = array();
foreach ( $en['items'] as $item ) {
    $en_signature[] = array(
        $item['branch_id'],
        $item['decision'],
        $item['target_response']['response_id'],
        $item['state_fit_margin'],
        $item['coverage_caution'],
    );
}
$es_signature = array();
foreach ( $es['items'] as $item ) {
    $es_signature[] = array(
        $item['branch_id'],
        $item['decision'],
        $item['target_response']['response_id'],
        $item['state_fit_margin'],
        $item['coverage_caution'],
    );
}
tt_switch_assert( $en_signature === $es_signature, 'switch decisions are language-neutral' );

$invalid = false;
try {
    TactiTwin_Response_Switch_Playbook::compose( $matrix_en, $tree_en, array(), 'en' );
} catch ( InvalidArgumentException $e ) {
    $invalid = true;
}
tt_switch_assert( $invalid, 'invalid robustness input rejected' );

echo "Response switch playbook tests passed.\n";
