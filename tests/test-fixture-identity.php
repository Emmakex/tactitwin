<?php

define( 'ABSPATH', __DIR__ );
define( 'TACTITWIN_VERSION', '0.1.0-dev' );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-fixture-identity.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-match-day-staff-brief.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-staff-brief-snapshot.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-staff-brief-version-diff.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-staff-brief-revision-chain.php';

function tt_v120_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_v120_sources( string $headline = 'Prepared headline' ): array {
    $match_plan = array(
        'plan_id'=>'match-plan','language'=>'en',
        'team'=>array('source_id'=>'100','name'=>'Our Team'),
        'opponent'=>array('source_id'=>'200','name'=>'Opponent'),
        'summary'=>array('headline'=>$headline),
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
    return array( $match_plan, $playbook );
}

$scheduled_a = TactiTwin_Fixture_Identity::normalize(
    array('source'=>'manual','match_date'=>'2026-10-20','kick_off'=>'20:00','source_competition_id'=>'liga','source_season_id'=>'2026','match_week'=>'8'),
    '100',
    '200'
);
$scheduled_a_repeat = TactiTwin_Fixture_Identity::normalize(
    array('match_week'=>'8','source_season_id'=>'2026','source_competition_id'=>'liga','kick_off'=>'20:00','match_date'=>'2026-10-20','source'=>'manual'),
    '100',
    '200'
);
$scheduled_b = TactiTwin_Fixture_Identity::normalize(
    array('source'=>'manual','match_date'=>'2027-02-14','kick_off'=>'20:00','source_competition_id'=>'liga','source_season_id'=>'2026','match_week'=>'24'),
    '100',
    '200'
);
tt_v120_assert( $scheduled_a['fixture_key'] === $scheduled_a_repeat['fixture_key'], 'same scheduled fixture is deterministic regardless of input field order' );
tt_v120_assert( $scheduled_a['fixture_key'] !== $scheduled_b['fixture_key'], 'different dates against the same opponent produce different fixture keys' );
tt_v120_assert( 'scheduled-composite' === $scheduled_a['identity_mode'], 'date-based identity declares scheduled-composite mode' );

$provider_a = TactiTwin_Fixture_Identity::normalize(
    array('source'=>'statsbomb','source_match_id'=>'3895292','match_date'=>'2026-10-20'),
    '100',
    '200'
);
$provider_a_corrected = TactiTwin_Fixture_Identity::normalize(
    array('source'=>'statsbomb','source_match_id'=>'3895292','match_date'=>'2026-10-21'),
    '100',
    '200'
);
tt_v120_assert( $provider_a['fixture_key'] === $provider_a_corrected['fixture_key'], 'provider match ID remains stable when descriptive date metadata is corrected' );
tt_v120_assert( 'provider-match-id' === $provider_a['identity_mode'], 'provider ID identity declares provider-match-id mode' );

$thrown = false;
try {
    TactiTwin_Fixture_Identity::normalize( array('source'=>'manual'), '100', '200' );
} catch ( InvalidArgumentException $e ) {
    $thrown = true;
}
tt_v120_assert( $thrown, 'manual fixture without match ID or date is rejected' );

[ $match_plan, $playbook ] = tt_v120_sources();
$brief_a = TactiTwin_Match_Day_Staff_Brief::compose( $match_plan, $playbook, 'en', $scheduled_a );
tt_v120_assert( $scheduled_a['fixture_key'] === $brief_a['fixture']['fixture_key'], 'Staff Brief carries exact fixture key' );
tt_v120_assert( true === $brief_a['method']['fixture_identity_bound'], 'Staff Brief declares fixture binding' );

$revision_a = TactiTwin_Staff_Brief_Revision_Chain::build( $brief_a, array(), 1, 'en', 'Initial approval.' );
tt_v120_assert( $scheduled_a['fixture_key'] === $revision_a['fixture_key'], 'approval revision is fixture scoped' );
$snapshot_a = TactiTwin_Staff_Brief_Snapshot::freeze(
    $brief_a,
    '11111111-1111-4111-8111-111111111111',
    7,
    '2026-10-11 16:00:00',
    5,
    $revision_a
);
tt_v120_assert( true === TactiTwin_Staff_Brief_Snapshot::verify( $snapshot_a ), 'fixture-bound snapshot verifies' );
tt_v120_assert( $scheduled_a['fixture_key'] === $snapshot_a['fixture_key'], 'snapshot exposes fixture key at top level' );

$brief_other_fixture = TactiTwin_Match_Day_Staff_Brief::compose( $match_plan, $playbook, 'en', $scheduled_b );
$thrown = false;
try {
    TactiTwin_Staff_Brief_Revision_Chain::build( $brief_other_fixture, $snapshot_a, 2, 'en', 'Wrong fixture parent.' );
} catch ( InvalidArgumentException $e ) {
    $thrown = true;
}
tt_v120_assert( $thrown, 'revision cannot use a snapshot from another fixture against the same opponent' );

[ $changed_plan, $same_playbook ] = tt_v120_sources( 'Updated headline for the same fixture' );
$brief_same_fixture = TactiTwin_Match_Day_Staff_Brief::compose( $changed_plan, $same_playbook, 'es', $scheduled_a );
tt_v120_assert( $brief_same_fixture['fixture']['fixture_key'] === $brief_a['fixture']['fixture_key'], 'language does not alter fixture identity' );
$revision_b = TactiTwin_Staff_Brief_Revision_Chain::build( $brief_same_fixture, $snapshot_a, 2, 'es', 'Reviewed update.' );
tt_v120_assert( $snapshot_a['snapshot_uuid'] === $revision_b['parent_snapshot_uuid'], 'same fixture can continue the approval chain' );
$snapshot_b = TactiTwin_Staff_Brief_Snapshot::freeze(
    $brief_same_fixture,
    '22222222-2222-4222-8222-222222222222',
    7,
    '2026-10-11 16:10:00',
    5,
    $revision_b
);
tt_v120_assert( true === TactiTwin_Staff_Brief_Snapshot::verify( $snapshot_b ), 'second revision for same fixture verifies' );

$tampered = $snapshot_b;
$tampered['fixture']['match_date'] = '2027-01-01';
tt_v120_assert( false === TactiTwin_Staff_Brief_Snapshot::verify( $tampered ), 'fixture metadata tampering invalidates snapshot verification' );

echo "Fixture identity tests passed.\n";
