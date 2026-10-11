<?php

define( 'ABSPATH', __DIR__ );
define( 'TACTITWIN_VERSION', '0.1.0-dev' );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-match-day-staff-brief.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-staff-brief-snapshot.php';

function tt_v117_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$match_plan = array(
    'plan_id'=>'match-plan', 'language'=>'en',
    'team'=>array('source_id'=>'100','name'=>'Our Team'),
    'opponent'=>array('source_id'=>'200','name'=>'Opponent'),
    'summary'=>array('headline'=>'Prepared headline'),
    'coverage'=>array('effective_matches'=>5),
    'priorities'=>array(array('rank'=>1,'scenario_id'=>'press','scenario_name'=>'Press','priority_score'=>82.0,'instruction'=>'Protect buildup.')),
    'phases'=>array('opening_15_minutes'=>array('Start wide.'),'halftime_checks'=>array('Check press.')),
);
$playbook = array(
    'playbook_id'=>'contingency-playbook','language'=>'en',
    'team'=>array('source_id'=>'100','name'=>'Our Team'),
    'opponent'=>array('source_id'=>'200','name'=>'Opponent'),
    'current_context'=>array('minute'=>1,'our_score'=>0,'opponent_score'=>0,'our_players'=>11,'opponent_players'=>11),
    'active_plan'=>array('id'=>'A','mode'=>'baseline','label'=>'Plan A'),
    'plans'=>array(
        'plan_a'=>array('id'=>'A','name'=>'Plan A','status'=>'available','mode'=>'baseline','instructions'=>array('A1')),
        'plan_b'=>array('id'=>'B','name'=>'Plan B','status'=>'dormant','mode'=>'observe','instructions'=>array('B1')),
        'plan_c'=>array('id'=>'C','name'=>'Plan C','status'=>'standby','mode'=>'standby','instructions'=>array('C1')),
    ),
    'decision_tree'=>array(array('order'=>1,'condition'=>'Context changes','target_plan'=>'C')),
);

$brief = TactiTwin_Match_Day_Staff_Brief::compose( $match_plan, $playbook, 'en' );
tt_v117_assert( $brief['handoff_source']['match_plan'] === $match_plan, 'Staff Brief retains exact Match Plan source for later approval' );
tt_v117_assert( $brief['handoff_source']['playbook'] === $playbook, 'Staff Brief retains exact Playbook source for later approval' );
tt_v117_assert( true === $brief['method']['handoff_source_included'], 'Staff Brief declares handoff source availability' );

$snapshot = TactiTwin_Staff_Brief_Snapshot::freeze(
    $brief,
    '11111111-2222-4333-8444-555555555555',
    7,
    '2026-10-11 13:30:00',
    5
);
tt_v117_assert( 'match-day-staff-brief-snapshot' === $snapshot['snapshot_id'], 'snapshot ID is stable' );
tt_v117_assert( 'approved' === $snapshot['status'], 'snapshot is explicitly approved' );
tt_v117_assert( 64 === strlen( $snapshot['content_sha256'] ), 'snapshot stores a SHA-256 digest' );
tt_v117_assert( true === TactiTwin_Staff_Brief_Snapshot::verify( $snapshot ), 'fresh snapshot passes integrity verification' );
tt_v117_assert( $playbook === TactiTwin_Staff_Brief_Snapshot::playbook( $snapshot ), 'handoff returns the exact frozen Playbook content' );
tt_v117_assert( ! isset( $snapshot['content']['staff_brief']['handoff_source'] ), 'stored presentation brief avoids duplicating full handoff sources' );
tt_v117_assert( $snapshot['content']['match_plan'] === $match_plan, 'snapshot stores exact Match Plan source separately' );

$tampered = $snapshot;
$tampered['content']['playbook']['active_plan']['id'] = 'C';
tt_v117_assert( false === TactiTwin_Staff_Brief_Snapshot::verify( $tampered ), 'tampering invalidates the SHA-256 integrity check' );
$thrown = false;
try {
    TactiTwin_Staff_Brief_Snapshot::playbook( $tampered );
} catch ( RuntimeException $e ) {
    $thrown = true;
}
tt_v117_assert( true === $thrown, 'tampered snapshots cannot be handed off' );

$bad = $brief;
$bad['handoff_source']['playbook']['opponent']['source_id'] = '999';
$thrown = false;
try {
    TactiTwin_Staff_Brief_Snapshot::freeze( $bad, 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', 7, '2026-10-11 13:31:00', 5 );
} catch ( InvalidArgumentException $e ) {
    $thrown = true;
}
tt_v117_assert( true === $thrown, 'snapshot creation rejects a mismatched frozen source' );

echo "Staff Brief snapshot handoff tests passed.\n";
