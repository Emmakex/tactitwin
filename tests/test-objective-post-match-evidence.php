<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-objective-post-match-evidence.php';

function tt_objective_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$session = array(
    'session_uuid'=>'session-1',
    'source_match_id'=>'100',
    'team_source_id'=>'1',
    'opponent_source_id'=>'2',
    'status'=>'closed',
);
$review = array(
    'observed'=>array(
        'scenario_snapshot_counts'=>array(
            'build-up-vs-high-press'=>2,
            'defensive-transition-after-loss'=>1,
        ),
        'scenario_first_seen_minute'=>array(
            'build-up-vs-high-press'=>18,
            'defensive-transition-after-loss'=>52,
        ),
    ),
);
$snapshots = array(
    array(
        'id'=>1,
        'minute'=>5,
        'playbook'=>array(
            'plans'=>array(
                'plan_b'=>array('scenario_id'=>'build-up-vs-high-press'),
            ),
            'evidence'=>array(),
        ),
    ),
);
$match_summary = array(
    'match'=>array(
        'source_match_id'=>'100',
        'match_date'=>'2026-10-11',
        'home_team'=>array('source_id'=>'1','name'=>'Team One','score'=>2),
        'away_team'=>array('source_id'=>'2','name'=>'Team Two','score'=>1),
    ),
    'coverage'=>array('events'=>2450,'status'=>'event-data','source'=>'statsbomb-open-data'),
    'teams'=>array(
        '1'=>array(
            'team_name'=>'Team One',
            'transition_turnovers'=>8,
            'opponent_progressions_within_10s'=>4,
            'opponent_shots_within_10s'=>2,
            'xg_conceded_within_10s'=>0.45,
            'transition_progression_rate'=>50.0,
            'dangerous_turnover_rate'=>25.0,
            'final_third_actions'=>20,
            'final_third_channel_share'=>array('left'=>30.0,'central'=>40.0,'right'=>30.0),
        ),
        '2'=>array(
            'team_name'=>'Team Two',
            'high_pressures'=>18,
            'pressures'=>40,
            'high_pressure_share_pct'=>45.0,
            'deep_defensive_actions'=>10,
            'defensive_actions'=>40,
            'deep_defensive_action_share_pct'=>25.0,
            'final_third_actions'=>20,
            'final_third_channel_share'=>array('left'=>60.0,'central'=>20.0,'right'=>20.0),
        ),
    ),
);

$en = TactiTwin_Objective_Post_Match_Evidence::evaluate( $session, $review, $snapshots, $match_summary, 'en' );
$es = TactiTwin_Objective_Post_Match_Evidence::evaluate( $session, $review, $snapshots, $match_summary, 'es' );

$signals = array();
foreach ( $en['signals'] as $signal ) {
    $signals[ $signal['scenario_id'] ] = $signal;
}

tt_objective_assert( 'objective-post-match-event-evidence' === $en['evidence_id'], 'evidence identity retained' );
tt_objective_assert( 2450 === $en['coverage']['events'], 'event coverage retained' );
tt_objective_assert( false === $en['coverage']['time_aligned_to_snapshots'], 'v1.3 explicitly remains full-match rather than time-aligned' );
tt_objective_assert( 'supported' === $signals['build-up-vs-high-press']['status'], 'strong high-press proxy supported' );
tt_objective_assert( true === $signals['build-up-vs-high-press']['staff_observed'], 'staff high-press observation retained' );
tt_objective_assert( 'observed-and-supported' === $signals['build-up-vs-high-press']['comparison']['status'], 'observed high press aligned with event proxy' );
tt_objective_assert( 'not-supported' === $signals['attack-vs-low-block']['status'], 'low-block proxy can remain unsupported' );
tt_objective_assert( 'supported' === $signals['defensive-transition-after-loss']['status'], 'transition threat supported from short-window evidence' );
tt_objective_assert( 52 === $signals['defensive-transition-after-loss']['staff_first_seen_minute'], 'staff first-seen minute preserved' );
tt_objective_assert( 'supported' === $signals['opponent-side-bias']['status'], 'lateral bias supported by final-third distribution' );
tt_objective_assert( 'event-signal-not-recorded-by-staff' === $signals['opponent-side-bias']['comparison']['status'], 'event-only signal distinguished from staff observation' );
tt_objective_assert( 'insufficient' === $signals['exploit-opponent-weak-side']['status'], 'weak-side evidence refuses post-hoc reconstruction without persisted target' );
tt_objective_assert( 'descriptive-full-match-event-proxy-validation' === $en['method']['type'], 'method boundary explicit' );
tt_objective_assert( $en['signals'][0]['status'] === $es['signals'][0]['status'], 'language does not alter classifications' );
tt_objective_assert( $en['signals'][0]['status_label'] !== $es['signals'][0]['status_label'], 'status labels translated' );

$bad_summary = $match_summary;
unset( $bad_summary['teams']['2'] );
$thrown = false;
try {
    TactiTwin_Objective_Post_Match_Evidence::evaluate( $session, $review, $snapshots, $bad_summary, 'en' );
} catch ( InvalidArgumentException $e ) {
    $thrown = true;
}
tt_objective_assert( $thrown, 'mismatched linked match is rejected by evidence engine' );

echo "Objective post-match evidence tests passed.\n";
