<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-live-timeline.php';

function tt_timeline_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$snapshots = array(
    array('id'=>4,'minute'=>72,'active_plan'=>'C','active_mode'=>'protect','selected_scenario'=>'','our_score'=>1,'opponent_score'=>0),
    array('id'=>1,'minute'=>5,'active_plan'=>'A','active_mode'=>'baseline','selected_scenario'=>''),
    array('id'=>3,'minute'=>46,'active_plan'=>'B','active_mode'=>'secure_transition','selected_scenario'=>'defensive-transition-after-loss'),
    array('id'=>2,'minute'=>25,'active_plan'=>'B','active_mode'=>'escape_high_press','selected_scenario'=>'build-up-vs-high-press'),
    array('id'=>5,'minute'=>80,'active_plan'=>'C','active_mode'=>'protect','selected_scenario'=>''),
);

$timeline = TactiTwin_Live_Timeline::summarize( $snapshots );

tt_timeline_assert( 5 === $timeline['snapshot_count'], 'all snapshots are retained' );
tt_timeline_assert( 5 === $timeline['first_minute'], 'snapshots are sorted by minute' );
tt_timeline_assert( 80 === $timeline['last_minute'], 'last minute is retained' );
tt_timeline_assert( 'C' === $timeline['current_plan'], 'current plan is taken from final snapshot' );
tt_timeline_assert( 'protect' === $timeline['current_mode'], 'current mode is taken from final snapshot' );
tt_timeline_assert( 1 === $timeline['plan_snapshot_counts']['A'], 'Plan A count retained' );
tt_timeline_assert( 2 === $timeline['plan_snapshot_counts']['B'], 'Plan B count retained' );
tt_timeline_assert( 2 === $timeline['plan_snapshot_counts']['C'], 'Plan C count retained' );
tt_timeline_assert( 3 === count( $timeline['transitions'] ), 'A->B, B->B mode change and B->C are three transitions' );
tt_timeline_assert( 'A' === $timeline['transitions'][0]['from_plan'] && 'B' === $timeline['transitions'][0]['to_plan'], 'first transition is A to B' );
tt_timeline_assert( 'B' === $timeline['transitions'][1]['from_plan'] && 'B' === $timeline['transitions'][1]['to_plan'], 'Plan B scenario/mode change is still recorded as a transition' );
tt_timeline_assert( 'C' === $timeline['transitions'][2]['to_plan'], 'last transition enters Plan C' );
tt_timeline_assert( 4 === count( $timeline['segments'] ), 'same C/protect state is merged into one final segment' );
tt_timeline_assert( 2 === count( $timeline['segments'][3]['snapshot_ids'] ), 'final C/protect segment contains both snapshots' );
tt_timeline_assert( 1 === $timeline['scenario_snapshot_counts']['build-up-vs-high-press'], 'high press scenario count retained' );
tt_timeline_assert( 1 === $timeline['scenario_snapshot_counts']['defensive-transition-after-loss'], 'transition scenario count retained' );
tt_timeline_assert( 'snapshot-timeline-reconstruction' === $timeline['method']['type'], 'timeline method boundary is explicit' );

$empty = TactiTwin_Live_Timeline::summarize( array() );
tt_timeline_assert( 0 === $empty['snapshot_count'], 'empty timeline is supported' );
tt_timeline_assert( null === $empty['current_plan'], 'empty timeline does not invent a current plan' );

echo "Live timeline tests passed.\n";
