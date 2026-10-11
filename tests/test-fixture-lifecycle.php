<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-fixture-lifecycle-service.php';

function tt_v124_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$empty = TactiTwin_Fixture_Lifecycle_Service::derive( array( 'context_selected' => false ) );
tt_v124_assert( 'not_selected' === $empty['stages']['target']['state'], 'lifecycle starts without a selected fixture' );
tt_v124_assert( 'select_match' === $empty['next_action']['id'], 'missing context points to match selection' );
tt_v124_assert( true === $empty['method']['no_progress_score'], 'lifecycle explicitly avoids a synthetic progress score' );

$missing_data = TactiTwin_Fixture_Lifecycle_Service::derive( array(
    'context_selected' => true,
    'fixture_key' => str_repeat( 'a', 64 ),
    'team_history_matches' => 0,
    'opponent_history_matches' => 0,
) );
tt_v124_assert( 'missing' === $missing_data['stages']['planning_data']['state'], 'zero historical event samples are reported as missing' );
tt_v124_assert( 'prepare_data' === $missing_data['next_action']['id'], 'missing planning evidence points to Data & Imports' );

$partial_data = TactiTwin_Fixture_Lifecycle_Service::derive( array(
    'context_selected' => true,
    'fixture_key' => str_repeat( 'b', 64 ),
    'team_history_matches' => 4,
    'opponent_history_matches' => 0,
) );
tt_v124_assert( 'partial' === $partial_data['stages']['planning_data']['state'], 'one-sided history is explicit partial coverage' );
tt_v124_assert( 'prepare_data' === $partial_data['next_action']['id'], 'partial planning coverage still recommends completing data' );

$ready = TactiTwin_Fixture_Lifecycle_Service::derive( array(
    'context_selected' => true,
    'fixture_key' => str_repeat( 'c', 64 ),
    'team_history_matches' => 5,
    'opponent_history_matches' => 5,
) );
tt_v124_assert( 'ready' === $ready['stages']['planning_data']['state'], 'both teams with history make planning data ready' );
tt_v124_assert( 'generate_match_plan' === $ready['next_action']['id'], 'ready fixture points to Match Plan when no result exists' );

$temp_plan = TactiTwin_Fixture_Lifecycle_Service::derive( array(
    'context_selected' => true,
    'fixture_key' => str_repeat( 'd', 64 ),
    'team_history_matches' => 5,
    'opponent_history_matches' => 5,
    'temporary_match_plan' => array( 'plan' => array( 'ok' => true ) ),
) );
tt_v124_assert( 'temporary_matchup_only' === $temp_plan['stages']['match_plan']['state'], 'temporary Match Plan is not misrepresented as fixture-bound' );
tt_v124_assert( false === $temp_plan['stages']['match_plan']['persistent'], 'temporary Match Plan is explicitly non-persistent' );
tt_v124_assert( false === $temp_plan['stages']['match_plan']['fixture_bound'], 'temporary Match Plan remains matchup-scoped only' );
tt_v124_assert( 'generate_staff_brief' === $temp_plan['next_action']['id'], 'temporary Match Plan advances to fixture-bound Staff Brief generation' );

$draft = TactiTwin_Fixture_Lifecycle_Service::derive( array(
    'context_selected' => true,
    'fixture_key' => str_repeat( 'e', 64 ),
    'team_history_matches' => 5,
    'opponent_history_matches' => 5,
    'temporary_staff_brief' => array( 'brief' => array( 'ok' => true ) ),
) );
tt_v124_assert( 'draft_temporary' === $draft['stages']['staff_brief']['state'], 'generated unapproved Staff Brief is a temporary draft' );
tt_v124_assert( 'approve_staff_brief' === $draft['next_action']['id'], 'draft points to explicit approval' );

$approved_snapshot = array(
    'snapshot_uuid' => 'snapshot-1',
    'approved_at' => '2026-10-11 12:00:00',
);
$approved = TactiTwin_Fixture_Lifecycle_Service::derive( array(
    'context_selected' => true,
    'fixture_key' => str_repeat( 'f', 64 ),
    'team_history_matches' => 5,
    'opponent_history_matches' => 5,
    'approved_snapshot' => $approved_snapshot,
) );
tt_v124_assert( 'sealed_in_approval' === $approved['stages']['match_plan']['state'], 'approved snapshot seals the source Match Plan' );
tt_v124_assert( 'approved' === $approved['stages']['staff_brief']['state'], 'approved snapshot is persistent Staff Brief state' );
tt_v124_assert( true === $approved['stages']['staff_brief']['persistent'], 'approved Staff Brief is persistent' );
tt_v124_assert( 'handoff_live_session' === $approved['next_action']['id'], 'approved brief without session points to handoff' );

$active = TactiTwin_Fixture_Lifecycle_Service::derive( array(
    'context_selected' => true,
    'fixture_key' => str_repeat( '1', 64 ),
    'team_history_matches' => 5,
    'opponent_history_matches' => 5,
    'approved_snapshot' => $approved_snapshot,
    'session' => array( 'session_uuid'=>'session-1', 'status'=>'active', 'source_match_id'=>'5001' ),
    'fixture_source_match_id' => '5001',
    'session_source_match_id' => '5001',
) );
tt_v124_assert( 'match_day' === $active['phase'], 'active live session moves lifecycle to match day' );
tt_v124_assert( 'active' === $active['stages']['live_session']['state'], 'active session is represented directly' );
tt_v124_assert( 'continue_live_session' === $active['next_action']['id'], 'active session remains the deterministic next action' );

$closed_pending = TactiTwin_Fixture_Lifecycle_Service::derive( array(
    'context_selected' => true,
    'fixture_key' => str_repeat( '2', 64 ),
    'team_history_matches' => 5,
    'opponent_history_matches' => 5,
    'approved_snapshot' => $approved_snapshot,
    'session' => array( 'session_uuid'=>'session-2', 'status'=>'closed', 'source_match_id'=>'5002' ),
    'fixture_source_match_id' => '5002',
    'session_source_match_id' => '5002',
    'evidence_event_count' => 0,
) );
tt_v124_assert( 'post_match' === $closed_pending['phase'], 'closed session moves lifecycle to post-match' );
tt_v124_assert( 'linked_pending_events' === $closed_pending['stages']['evidence']['state'], 'linked fixture without events is not called evidence-ready' );
tt_v124_assert( 'waiting_for_evidence' === $closed_pending['stages']['post_match_review']['state'], 'post-match review waits for actual evidence' );
tt_v124_assert( 'prepare_evidence' === $closed_pending['next_action']['id'], 'closed session without evidence points to Evidence Review' );

$mismatch = TactiTwin_Fixture_Lifecycle_Service::derive( array(
    'context_selected' => true,
    'fixture_key' => str_repeat( '3', 64 ),
    'team_history_matches' => 5,
    'opponent_history_matches' => 5,
    'approved_snapshot' => $approved_snapshot,
    'session' => array( 'session_uuid'=>'session-3', 'status'=>'closed', 'source_match_id'=>'other' ),
    'fixture_source_match_id' => '5003',
    'session_source_match_id' => 'other',
    'evidence_event_count' => 200,
) );
tt_v124_assert( 'fixture_mismatch' === $mismatch['stages']['evidence']['state'], 'events from another fixture never satisfy current-fixture evidence' );

$review_ready = TactiTwin_Fixture_Lifecycle_Service::derive( array(
    'context_selected' => true,
    'fixture_key' => str_repeat( '4', 64 ),
    'team_history_matches' => 5,
    'opponent_history_matches' => 5,
    'approved_snapshot' => $approved_snapshot,
    'session' => array( 'session_uuid'=>'session-4', 'status'=>'closed', 'source_match_id'=>'5004', 'closed_at'=>'2026-10-11 21:50:00' ),
    'fixture_source_match_id' => '5004',
    'session_source_match_id' => '5004',
    'evidence_event_count' => 1843,
) );
tt_v124_assert( 'available' === $review_ready['stages']['evidence']['state'], 'matching normalized match events make evidence available' );
tt_v124_assert( 'available_on_demand' === $review_ready['stages']['post_match_review']['state'], 'review is available but not falsely persisted as completed' );
tt_v124_assert( false === $review_ready['stages']['post_match_review']['persistent'], 'post-match review completion remains non-persistent in v1.24' );
tt_v124_assert( 'review_match' === $review_ready['next_action']['id'], 'evidence-ready closed fixture points to post-match review' );

echo "Fixture lifecycle tests passed.\n";
