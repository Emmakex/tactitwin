<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-fixture-identity.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-match-target-service.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-planning-context.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-match-workspace-admin.php';

function tt_v123_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$match = array(
    'id' => 123,
    'source' => 'statsbomb',
    'source_match_id' => '5000123',
    'source_competition_id' => '9',
    'source_season_id' => '281',
    'match_date' => '2026-11-02',
    'kick_off' => '20:30:00',
    'home_team_source_id' => '300',
    'away_team_source_id' => '400',
    'home_team_name' => 'Workspace FC',
    'away_team_name' => 'Opponent FC',
    'competition_name' => 'Open League',
    'season_name' => '2026/27',
    'match_week' => '12',
    'match_status' => 'scheduled',
);

$resolved = TactiTwin_Match_Target_Service::resolve_row( $match, '300' );
$context = TactiTwin_Planning_Context::build_context( $resolved );

tt_v123_assert( '300' === $context['team_source_id'], 'workspace context keeps our-team perspective' );
tt_v123_assert( '400' === $context['opponent_source_id'], 'workspace context keeps the canonical opponent' );
tt_v123_assert( '123:300' === TactiTwin_Planning_Context::selection_from_context( $context ), 'workspace selection remains deterministic' );

$actions = TactiTwin_Planning_Context::supported_actions();
foreach ( array( 'tactitwin_run_personnel_what_if', 'tactitwin_run_unit_pair', 'tactitwin_run_lineup_what_if' ) as $action ) {
    tt_v123_assert( in_array( $action, $actions, true ), $action . ' is covered by the shared match context' );
    $payload = TactiTwin_Planning_Context::apply_to_payload( array( 'team_id'=>'999', 'opponent_id'=>'888' ), $context, $action );
    tt_v123_assert( '300' === $payload['team_id'], $action . ' receives canonical our-team identity' );
    tt_v123_assert( '400' === $payload['opponent_id'], $action . ' receives canonical opponent identity' );
    tt_v123_assert( ! isset( $payload['target_match_id'] ), $action . ' does not pretend to be Staff Brief fixture handoff' );
}

$staff_payload = TactiTwin_Planning_Context::apply_to_payload( array(), $context, 'tactitwin_generate_staff_brief' );
tt_v123_assert( '123' === $staff_payload['target_match_id'], 'Staff Brief still receives the canonical imported match row' );
tt_v123_assert( '1' === $staff_payload['tactitwin_bind_fixture'], 'Staff Brief remains fixture-bound through v1.20/v1.21' );

$steps = TactiTwin_Match_Workspace_Admin::workflow_steps();
$ids = array_column( $steps, 'id' );
$pages = array_column( $steps, 'page' );

$expected_order = array(
    'scenario',
    'match_plan',
    'what_if',
    'lineup',
    'personnel',
    'unit',
    'staff_brief',
    'live_playbook',
    'live_sessions',
    'session_review',
    'evidence_review',
    'segment_evidence',
    'transition_deltas',
    'intervention_library',
);
tt_v123_assert( $expected_order === $ids, 'workspace exposes the intended end-to-end match workflow in deterministic order' );
tt_v123_assert( count( $pages ) === count( array_unique( $pages ) ), 'workspace page links are unique' );

tt_v123_assert( 'direct' === $steps[3]['context_mode'], 'Lineup Lab consumes active match context directly' );
tt_v123_assert( 'direct' === $steps[4]['context_mode'], 'Personnel Lab consumes active match context directly' );
tt_v123_assert( 'team' === $steps[5]['context_mode'], 'Unit Lab consumes the active-match team while remaining opponent-independent' );
tt_v123_assert( 'handoff' === $steps[8]['context_mode'], 'Live Sessions remain downstream of approved/session handoff rather than being silently rebound' );
foreach ( array( 9, 10, 11, 12 ) as $index ) {
    tt_v123_assert( 'session' === $steps[ $index ]['context_mode'], $steps[ $index ]['id'] . ' remains session-derived' );
}
tt_v123_assert( 'history' === $steps[13]['context_mode'], 'Intervention Library remains a cross-session historical layer' );

$away = TactiTwin_Planning_Context::build_context( TactiTwin_Match_Target_Service::resolve_row( $match, '400' ) );
tt_v123_assert( $context['fixture_key'] !== $away['fixture_key'], 'opposite team perspectives remain separate fixture scopes in workspace mode' );

echo "Match workspace tests passed.\n";
