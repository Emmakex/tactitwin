<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-fixture-lifecycle-service.php';

function tt_v139_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$balanced_low = TactiTwin_Fixture_Lifecycle_Service::derive( array(
    'context_selected' => true,
    'fixture_key' => str_repeat( 'a', 64 ),
    'team_history_matches' => 1,
    'opponent_history_matches' => 1,
) );
tt_v139_assert( 'partial' === $balanced_low['stages']['planning_data']['state'], '1 vs 1 is not planning-ready' );
tt_v139_assert( 1 === $balanced_low['stages']['planning_data']['effective_matches'], 'effective coverage keeps the minimum side' );
tt_v139_assert( 3 === $balanced_low['stages']['planning_data']['minimum_ready_matches_per_side'], 'minimum planning-ready sample is explicit' );
tt_v139_assert( 'prepare_data' === $balanced_low['next_action']['id'], 'low pre-match sample points to data preparation' );

$asymmetric = TactiTwin_Fixture_Lifecycle_Service::derive( array(
    'context_selected' => true,
    'fixture_key' => str_repeat( 'b', 64 ),
    'team_history_matches' => 5,
    'opponent_history_matches' => 1,
    'planning_readiness' => array(
        'team_matches' => 5,
        'opponent_matches' => 1,
        'effective_matches' => 1,
        'limiting_side' => 'opponent',
        'team' => array( 'match_ids' => array( '101', '102', '103', '104', '105' ) ),
        'opponent' => array( 'match_ids' => array( '201' ) ),
        'temporal_scope' => array(
            'before_date' => '2024-02-03',
            'exclude_match_id' => '999',
            'future_matches_excluded' => true,
            'target_fixture_excluded' => true,
        ),
    ),
) );
tt_v139_assert( 'partial' === $asymmetric['stages']['planning_data']['state'], '5 vs 1 remains partial' );
tt_v139_assert( 'opponent' === $asymmetric['stages']['planning_data']['limiting_side'], 'limiting side is retained' );
tt_v139_assert( '201' === $asymmetric['stages']['planning_data']['opponent_sample']['match_ids'][0], 'exact eligible sample identity is retained' );
tt_v139_assert( '2024-02-03' === $asymmetric['stages']['planning_data']['temporal_scope']['before_date'], 'fixture cutoff is retained' );

$minimum_ready = TactiTwin_Fixture_Lifecycle_Service::derive( array(
    'context_selected' => true,
    'fixture_key' => str_repeat( 'c', 64 ),
    'team_history_matches' => 3,
    'opponent_history_matches' => 3,
) );
tt_v139_assert( 'ready' === $minimum_ready['stages']['planning_data']['state'], '3 vs 3 meets planning-ready threshold' );
tt_v139_assert( 'generate_match_plan' === $minimum_ready['next_action']['id'], 'ready pre-match fixture proceeds to Match Plan' );

$live_with_low_history = TactiTwin_Fixture_Lifecycle_Service::derive( array(
    'context_selected' => true,
    'fixture_key' => str_repeat( 'd', 64 ),
    'team_history_matches' => 1,
    'opponent_history_matches' => 0,
    'approved_snapshot' => array( 'snapshot_uuid' => 'snapshot-live' ),
    'session' => array( 'session_uuid' => 'session-live', 'status' => 'active', 'source_match_id' => '7001' ),
    'fixture_source_match_id' => '7001',
    'session_source_match_id' => '7001',
) );
tt_v139_assert( 'match_day' === $live_with_low_history['phase'], 'active session keeps match-day phase' );
tt_v139_assert( 'continue_live_session' === $live_with_low_history['next_action']['id'], 'active session takes precedence over pre-match data gaps' );

$closed_with_low_history = TactiTwin_Fixture_Lifecycle_Service::derive( array(
    'context_selected' => true,
    'fixture_key' => str_repeat( 'e', 64 ),
    'team_history_matches' => 1,
    'opponent_history_matches' => 0,
    'approved_snapshot' => array( 'snapshot_uuid' => 'snapshot-closed' ),
    'session' => array( 'session_uuid' => 'session-closed', 'status' => 'closed', 'source_match_id' => '7002' ),
    'fixture_source_match_id' => '7002',
    'session_source_match_id' => '7002',
    'evidence_event_count' => 800,
) );
tt_v139_assert( 'post_match' === $closed_with_low_history['phase'], 'closed session keeps post-match phase' );
tt_v139_assert( 'review_match' === $closed_with_low_history['next_action']['id'], 'post-match review takes precedence over pre-match data gaps' );

tt_v139_assert( 'fixture-lifecycle-v1.39' === $minimum_ready['method']['name'], 'method version exposes v1.39 semantics' );
tt_v139_assert( true === $minimum_ready['method']['phase_precedence_for_next_action'], 'phase precedence guardrail is explicit' );
tt_v139_assert( 'eligible-before-active-fixture' === $minimum_ready['method']['planning_scope'], 'planning scope is explicit' );

echo "v1.39 fixture lifecycle readiness tests passed.\n";
