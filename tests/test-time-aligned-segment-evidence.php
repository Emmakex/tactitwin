<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-match-metrics.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-transition-metrics.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-live-timeline.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-time-aligned-evidence.php';

function tt_segment_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_event( int $index, int $minute, string $team, string $type, float $x = 50.0, float $y = 40.0, float $x2 = 50.0, float $y2 = 40.0, string $outcome = '', float $xg = 0.0 ): array {
    return array(
        'event_index'=>$index,'period'=>1,'minute'=>$minute,'second'=>0,'possession'=>$index,
        'possession_team_source_id'=>$team,'team_source_id'=>$team,'player_source_id'=>'p'.$index,
        'position_name'=>'','event_type_name'=>$type,'outcome_name'=>$outcome,'xg'=>$xg,
        'location_x'=>$x,'location_y'=>$y,'end_location_x'=>$x2,'end_location_y'=>$y2,'raw_json'=>'{}',
    );
}

$base_playbook = array(
    'evidence'=>array(
        'scenario_fingerprints'=>array(
            'exploit-opponent-weak-side'=>array('target_channel'=>'left'),
        ),
    ),
);
$session = array('session_uuid'=>'s1','source_match_id'=>'100','team_source_id'=>'1','opponent_source_id'=>'2','status'=>'closed');
$snapshots = array(
    array('id'=>1,'minute'=>5,'active_plan'=>'A','active_mode'=>'baseline','selected_scenario'=>'','observations'=>array(),'playbook'=>$base_playbook),
    array('id'=>2,'minute'=>20,'active_plan'=>'B','active_mode'=>'escape_high_press','selected_scenario'=>'build-up-vs-high-press','observations'=>array('high_press'=>true),'playbook'=>$base_playbook),
    array('id'=>3,'minute'=>40,'active_plan'=>'C','active_mode'=>'protect','selected_scenario'=>'','observations'=>array(),'playbook'=>$base_playbook),
    array('id'=>4,'minute'=>60,'active_plan'=>'C','active_mode'=>'protect','selected_scenario'=>'','observations'=>array(),'playbook'=>$base_playbook),
);

$events = array(
    tt_event(1,4,'1','Pass',20,40,45,40), // before first snapshot: excluded
    tt_event(2,5,'1','Pass',20,40,45,40),
    tt_event(3,19,'2','Pressure',70,40),
    tt_event(4,20,'2','Pressure',90,40), // transition minute belongs to B
    tt_event(5,22,'2','Pressure',92,40),
    tt_event(6,24,'2','Pressure',91,40),
    tt_event(7,26,'2','Pressure',93,40),
    tt_event(8,28,'2','Pressure',89,40),
    tt_event(9,30,'2','Pressure',60,40),
    tt_event(10,31,'1','Pass',30,40,35,40,'Incomplete'),
    tt_event(11,31,'2','Pass',45,40,70,40),
    tt_event(12,34,'1','Pass',70,20,90,20),
    tt_event(13,35,'1','Pass',75,20,90,20),
    tt_event(14,40,'2','Pressure',90,40), // transition minute belongs to C
    tt_event(15,45,'1','Pass',50,40,60,40),
    tt_event(16,60,'1','Pass',50,40,60,40), // final boundary included
);

$match_summary = array(
    'match'=>array(
        'source_match_id'=>'100','match_date'=>'2026-10-11',
        'home_team'=>array('source_id'=>'1','name'=>'Team One','score'=>1),
        'away_team'=>array('source_id'=>'2','name'=>'Team Two','score'=>0),
    ),
    'teams'=>array('1'=>array('team_name'=>'Team One'),'2'=>array('team_name'=>'Team Two')),
);

$en = TactiTwin_Time_Aligned_Evidence::evaluate($session,$snapshots,$events,$match_summary,'en');
$es = TactiTwin_Time_Aligned_Evidence::evaluate($session,$snapshots,$events,$match_summary,'es');

tt_segment_assert('time-aligned-segment-evidence' === $en['evidence_id'], 'segment evidence identity retained');
tt_segment_assert(true === $en['coverage']['time_aligned_to_snapshots'], 'coverage marks minute-aligned evidence');
tt_segment_assert(3 === $en['coverage']['segment_count'], 'A, B and C reconstructed as three segments');
tt_segment_assert(5 === $en['coverage']['first_snapshot_minute'], 'first snapshot minute retained');
tt_segment_assert(5 === $en['coverage']['unobserved_pre_session_minutes'], 'pre-session interval is explicit');

$a = $en['segments'][0];
$b = $en['segments'][1];
$c = $en['segments'][2];
tt_segment_assert('A' === $a['plan'] && 5 === $a['from_minute'] && 20 === $a['to_minute'], 'Plan A interval reconstructed');
tt_segment_assert('B' === $b['plan'] && 20 === $b['from_minute'] && 40 === $b['to_minute'], 'Plan B interval reconstructed');
tt_segment_assert('C' === $c['plan'] && 40 === $c['from_minute'] && 60 === $c['to_minute'], 'Plan C interval reconstructed');
tt_segment_assert('[from,to)' === $b['event_boundary'], 'non-final boundary is half-open');
tt_segment_assert('[from,to]' === $c['event_boundary'], 'final boundary includes last minute');
tt_segment_assert(1 === $a['event_count'], 'event at minute 20 is not counted in A');
tt_segment_assert(10 === $b['event_count'], 'B receives transition-minute event and excludes minute 40');
tt_segment_assert(3 === $c['event_count'], 'C receives minute 40 and final minute 60 events');
tt_segment_assert(14 === $a['event_count'] + $b['event_count'] + $c['event_count'], 'events inside the observed window are counted once');

tt_segment_assert('build-up-vs-high-press' === $b['scenario_id'], 'active Plan B scenario retained');
tt_segment_assert('supported' === $b['active_scenario_evidence']['status'], 'high-press evidence is supported inside Plan B interval');
tt_segment_assert(true === $b['active_scenario_evidence']['staff_observed_in_segment'], 'staff observation is aligned to Plan B segment');
tt_segment_assert('observed-and-supported' === $b['active_scenario_evidence']['comparison_status'], 'staff/event alignment classified at segment level');
tt_segment_assert(null === $a['active_scenario_evidence'], 'Plan A without scenario does not invent active evidence');
tt_segment_assert($en['segments'][1]['active_scenario_evidence']['status'] === $es['segments'][1]['active_scenario_evidence']['status'], 'language does not alter segment classification');
tt_segment_assert($en['segments'][1]['active_scenario_evidence']['status_label'] !== $es['segments'][1]['active_scenario_evidence']['status_label'], 'segment labels are translated');
tt_segment_assert('descriptive-minute-aligned-event-segment-validation' === $en['method']['type'], 'method boundary explicit');

echo "Time-aligned segment evidence tests passed.\n";
