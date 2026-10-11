<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-live-observation.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-live-timeline.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-post-match-review.php';

function tt_review_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$base_playbook = array(
    'plans'=>array(
        'plan_a'=>array(
            'instructions'=>array('A1','A2'),
            'priority_scenarios'=>array(
                array('scenario_id'=>'build-up-vs-high-press','scenario_name'=>'High press','priority_score'=>82.0),
                array('scenario_id'=>'defensive-transition-after-loss','scenario_name'=>'Transition','priority_score'=>70.0),
            ),
        ),
        'plan_b'=>array(
            'status'=>'armed','scenario_id'=>'build-up-vs-high-press','scenario_name'=>'High press escape','risk_score'=>82.0,'mode'=>'escape_high_press','trigger'=>'Press observed',
        ),
    ),
);

$session = array(
    'session_uuid'=>'abc','status'=>'closed','title'=>'Test','source_match_id'=>'100','team_source_id'=>'1','opponent_source_id'=>'2','started_at'=>'2026-10-11 10:00:00','closed_at'=>'2026-10-11 12:00:00',
);
$snapshots = array(
    array('id'=>1,'minute'=>5,'our_score'=>0,'opponent_score'=>0,'our_players'=>11,'opponent_players'=>11,'active_plan'=>'A','active_mode'=>'baseline','selected_scenario'=>'','note'=>'Start','observations'=>array('high_press'=>false,'low_block'=>false,'transition_threat'=>false,'side_bias'=>false,'weak_side_open'=>false),'playbook'=>$base_playbook),
    array('id'=>2,'minute'=>22,'our_score'=>0,'opponent_score'=>0,'our_players'=>11,'opponent_players'=>11,'active_plan'=>'B','active_mode'=>'escape_high_press','selected_scenario'=>'build-up-vs-high-press','note'=>'Press confirmed','observations'=>array('high_press'=>true,'low_block'=>false,'transition_threat'=>false,'side_bias'=>false,'weak_side_open'=>false),'playbook'=>$base_playbook),
    array('id'=>3,'minute'=>50,'our_score'=>1,'opponent_score'=>0,'our_players'=>11,'opponent_players'=>11,'active_plan'=>'B','active_mode'=>'secure_transition','selected_scenario'=>'defensive-transition-after-loss','note'=>'Transition threat','observations'=>array('high_press'=>false,'low_block'=>false,'transition_threat'=>true,'side_bias'=>false,'weak_side_open'=>false),'playbook'=>$base_playbook),
    array('id'=>4,'minute'=>75,'our_score'=>1,'opponent_score'=>0,'our_players'=>11,'opponent_players'=>11,'active_plan'=>'C','active_mode'=>'protect','selected_scenario'=>'','note'=>'Protect lead','observations'=>array('high_press'=>false,'low_block'=>false,'transition_threat'=>false,'side_bias'=>false,'weak_side_open'=>false),'playbook'=>$base_playbook),
    array('id'=>5,'minute'=>90,'our_score'=>1,'opponent_score'=>0,'our_players'=>11,'opponent_players'=>11,'active_plan'=>'C','active_mode'=>'protect','selected_scenario'=>'','note'=>'','observations'=>array(),'playbook'=>$base_playbook),
);

$en = TactiTwin_Post_Match_Review::build( $session, $snapshots, 'en' );
$es = TactiTwin_Post_Match_Review::build( $session, $snapshots, 'es' );

tt_review_assert( 'build-up-vs-high-press' === $en['prepared']['primary_plan_b']['scenario_id'], 'prepared primary Plan B retained' );
tt_review_assert( true === $en['alignment']['primary_plan_b_observed'], 'prepared Plan B behaviour was observed' );
tt_review_assert( true === $en['alignment']['primary_plan_b_activated'], 'prepared Plan B was activated' );
tt_review_assert( 'prepared-and-activated' === $en['alignment']['status'], 'alignment status is descriptive and correct' );
tt_review_assert( 22 === $en['observed']['scenario_first_seen_minute']['build-up-vs-high-press'], 'first observed minute retained' );
tt_review_assert( 22 === $en['activated']['first_plan_b_minute'], 'first Plan B activation minute retained' );
tt_review_assert( 75 === $en['activated']['first_plan_c_minute'], 'first Plan C activation minute retained' );
tt_review_assert( 3 === $en['activated']['transition_count'], 'A->B, B mode/scenario change and B->C retained' );
tt_review_assert( 4 === count( $en['staff_notes'] ), 'non-empty staff notes retained' );
tt_review_assert( 'good' === $en['sampling']['status'], 'dense closed timeline has good sampling status' );
tt_review_assert( 1 === $en['final_context']['our_score'] && 0 === $en['final_context']['opponent_score'], 'final score retained descriptively' );
tt_review_assert( $en['alignment']['status'] === $es['alignment']['status'], 'language does not alter review classification' );
tt_review_assert( $en['activated']['transition_count'] === $es['activated']['transition_count'], 'language does not alter transitions' );
tt_review_assert( $en['alignment']['label'] !== $es['alignment']['label'], 'alignment explanation is translated' );
tt_review_assert( 'descriptive-planned-observed-activated-review' === $en['method']['type'], 'method boundary is explicit' );

$alternate = $snapshots;
$alternate[1]['active_plan'] = 'A';
$alternate[1]['active_mode'] = 'baseline';
$alternate[1]['selected_scenario'] = '';
$alternate[1]['observations']['high_press'] = false;
$alternate[2]['selected_scenario'] = 'attack-vs-low-block';
$alternate[2]['active_mode'] = 'break_low_block';
$alternate[2]['observations']['transition_threat'] = false;
$alternate[2]['observations']['low_block'] = true;
$alt = TactiTwin_Post_Match_Review::build( $session, $alternate, 'en' );
tt_review_assert( 'different-adaptation-activated' === $alt['alignment']['status'], 'different observed adaptation is distinguished from prepared Plan B' );
tt_review_assert( in_array( 'attack-vs-low-block', $alt['alignment']['unprepared_observed_scenarios'], true ), 'unprepared observed scenario is surfaced' );

$open_session = $session;
$open_session['status'] = 'active';
$sparse = TactiTwin_Post_Match_Review::build( $open_session, array($snapshots[2]), 'en' );
tt_review_assert( 'limited' === $sparse['sampling']['status'], 'single late snapshot in open session is limited' );
tt_review_assert( count( $sparse['sampling']['warnings'] ) >= 2, 'sampling limitations are explicit' );

echo "Post-match review tests passed.\n";
