<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-match-metrics.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-transition-metrics.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-live-timeline.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-transition-window-deltas.php';

function tt_delta_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_delta_event( int $index, int $minute, string $team, string $type, float $x = 50.0, float $y = 40.0, float $x2 = 50.0, float $y2 = 40.0, string $outcome = '', float $xg = 0.0 ): array {
    return array(
        'event_index'=>$index,'period'=>1,'minute'=>$minute,'second'=>0,'possession'=>$index,
        'possession_team_source_id'=>$team,'team_source_id'=>$team,'player_source_id'=>'p'.$index,
        'position_name'=>'','event_type_name'=>$type,'outcome_name'=>$outcome,'xg'=>$xg,
        'location_x'=>$x,'location_y'=>$y,'end_location_x'=>$x2,'end_location_y'=>$y2,'raw_json'=>'{}',
    );
}

$session = array('session_uuid'=>'s1','source_match_id'=>'100','team_source_id'=>'1','opponent_source_id'=>'2','status'=>'closed');
$snapshots = array(
    array('id'=>1,'minute'=>10,'active_plan'=>'A','active_mode'=>'baseline','selected_scenario'=>'','playbook'=>array()),
    array('id'=>2,'minute'=>30,'active_plan'=>'B','active_mode'=>'escape_high_press','selected_scenario'=>'build-up-vs-high-press','playbook'=>array()),
    array('id'=>3,'minute'=>50,'active_plan'=>'B','active_mode'=>'escape_high_press','selected_scenario'=>'build-up-vs-high-press','playbook'=>array()),
);

$events = array();
$index = 1;
for ( $minute = 20; $minute < 30; ++$minute ) {
    $progressive = $minute < 22;
    $events[] = tt_delta_event($index++, $minute, '1', 'Pass', 30, 40, $progressive ? 50 : 38, 40);
    $high = $minute < 22;
    $events[] = tt_delta_event($index++, $minute, '2', 'Pressure', $high ? 90 : 65, 40);
}
for ( $minute = 30; $minute < 40; ++$minute ) {
    $progressive = $minute < 36;
    $events[] = tt_delta_event($index++, $minute, '1', 'Pass', 30, 40, $progressive ? 55 : 38, 40);
    $high = $minute < 38;
    $events[] = tt_delta_event($index++, $minute, '2', 'Pressure', $high ? 90 : 65, 40);
}
// Add later match events so the after window is not clipped by max-event minute.
$events[] = tt_delta_event($index++, 55, '1', 'Pass', 50, 40, 60, 40);
$events[] = tt_delta_event($index++, 55, '2', 'Pressure', 60, 40);

$summary = array(
    'match'=>array('source_match_id'=>'100'),
    'teams'=>array('1'=>array('team_name'=>'Team One'),'2'=>array('team_name'=>'Team Two')),
);

$en = TactiTwin_Transition_Window_Deltas::evaluate($session,$snapshots,$events,$summary,'en',10);
$es = TactiTwin_Transition_Window_Deltas::evaluate($session,$snapshots,$events,$summary,'es',10);

tt_delta_assert('transition-window-deltas' === $en['evidence_id'], 'transition evidence identity retained');
tt_delta_assert(1 === $en['coverage']['transition_count'], 'one A-to-B transition reconstructed');
$row = $en['transitions'][0];
tt_delta_assert(30 === $row['minute'], 'transition minute retained');
tt_delta_assert('A' === $row['from_plan'] && 'B' === $row['to_plan'], 'A-to-B plans retained');
tt_delta_assert('build-up-vs-high-press' === $row['to_scenario'], 'new scenario retained');
tt_delta_assert(20 === $row['before']['from_minute'] && 30 === $row['before']['to_minute'], 'before window is symmetric 20-30');
tt_delta_assert(30 === $row['after']['from_minute'] && 40 === $row['after']['to_minute'], 'after window is symmetric 30-40');
tt_delta_assert(20 === $row['before']['event_count'], 'before window contains exactly 20 events');
tt_delta_assert(20 === $row['after']['event_count'], 'after window contains exactly 20 events including minute 30 only after');
tt_delta_assert('good' === $row['coverage']['status'], 'full symmetric windows with enough events have good coverage');
tt_delta_assert(true === $row['coverage']['comparable'], 'good windows are comparable descriptively');
tt_delta_assert(18.0 === $row['before']['metrics']['opponent_high_pressures_per90'], 'before high pressure rate is correct');
tt_delta_assert(72.0 === $row['after']['metrics']['opponent_high_pressures_per90'], 'after high pressure rate is correct');
tt_delta_assert(54.0 === $row['deltas']['opponent_high_pressures_per90'], 'high pressure delta is after minus before');
tt_delta_assert(18.0 === $row['before']['metrics']['our_progressive_actions_per90'], 'before progression rate is correct');
tt_delta_assert(54.0 === $row['after']['metrics']['our_progressive_actions_per90'], 'after progression rate is correct');
tt_delta_assert(36.0 === $row['deltas']['our_progressive_actions_per90'], 'progression delta is correct');
tt_delta_assert('our_own_third_turnovers_per90' === $row['focus']['metrics'][0]['metric'], 'high-press focus uses scenario-specific metrics');
tt_delta_assert($row['deltas'] === $es['transitions'][0]['deltas'], 'language does not alter numeric deltas');
tt_delta_assert($row['coverage']['status'] === $es['transitions'][0]['coverage']['status'], 'language does not alter coverage status');
tt_delta_assert($row['coverage']['status_label'] !== $es['transitions'][0]['coverage']['status_label'], 'coverage labels are translated');
tt_delta_assert('descriptive-symmetric-before-after-transition-windows' === $en['method']['type'], 'method boundary is explicit');

// Edge transition: before window is clipped to five minutes and has sparse evidence.
$edge_snapshots = array(
    array('id'=>1,'minute'=>1,'active_plan'=>'A','active_mode'=>'baseline','selected_scenario'=>'','playbook'=>array()),
    array('id'=>2,'minute'=>5,'active_plan'=>'B','active_mode'=>'escape_high_press','selected_scenario'=>'build-up-vs-high-press','playbook'=>array()),
);
$edge_events = array(
    tt_delta_event(1,1,'1','Pass',30,40,45,40),
    tt_delta_event(2,2,'2','Pressure',90,40),
    tt_delta_event(3,5,'2','Pressure',90,40),
    tt_delta_event(4,6,'1','Pass',30,40,50,40),
    tt_delta_event(5,20,'1','Pass',50,40,60,40),
);
$edge = TactiTwin_Transition_Window_Deltas::evaluate($session,$edge_snapshots,$edge_events,$summary,'en',10);
tt_delta_assert('limited' === $edge['transitions'][0]['coverage']['status'], 'clipped sparse edge window is limited');
tt_delta_assert(false === $edge['transitions'][0]['coverage']['comparable'], 'limited window is not marked comparable');
tt_delta_assert(0 === $edge['transitions'][0]['before']['from_minute'], 'early before window is safely clipped at zero');

echo "Transition window delta tests passed.\n";
