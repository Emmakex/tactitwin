<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-fixture-identity.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-match-target-service.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-planning-context.php';

function tt_v122_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$match = array(
    'id' => 88,
    'source' => 'statsbomb',
    'source_match_id' => '3900001',
    'source_competition_id' => '9',
    'source_season_id' => '281',
    'match_date' => '2026-11-01',
    'kick_off' => '18:30:00',
    'home_team_source_id' => '300',
    'away_team_source_id' => '400',
    'home_team_name' => 'Blue FC',
    'away_team_name' => 'Red FC',
    'match_week' => '12',
    'match_status' => 'scheduled',
);

$decoded = TactiTwin_Planning_Context::decode_selection( '88:300' );
tt_v122_assert( 88 === $decoded['target_match_row_id'], 'shared selector preserves the normalized match row locator' );
tt_v122_assert( '300' === $decoded['team_source_id'], 'shared selector preserves the chosen team perspective' );
tt_v122_assert( array() === TactiTwin_Planning_Context::decode_selection( '' ), 'empty shared selector explicitly represents manual mode' );

$thrown = false;
try {
    TactiTwin_Planning_Context::decode_selection( '88/300' );
} catch ( InvalidArgumentException $e ) {
    $thrown = true;
}
tt_v122_assert( $thrown, 'malformed shared planning selections are rejected' );

$resolved = TactiTwin_Match_Target_Service::resolve_row( $match, '300' );
$context = TactiTwin_Planning_Context::build_context( $resolved );
tt_v122_assert( 1 === $context['context_version'], 'planning context declares its schema version' );
tt_v122_assert( 88 === $context['target_match_row_id'], 'planning context keeps the selected canonical match row' );
tt_v122_assert( '300' === $context['team_source_id'], 'planning context keeps our team' );
tt_v122_assert( '400' === $context['opponent_source_id'], 'planning context resolves the opponent once for all modules' );
tt_v122_assert( 'home' === $context['perspective'], 'planning context keeps home/away perspective' );
tt_v122_assert( $resolved['fixture']['fixture_key'] === $context['fixture_key'], 'planning context carries the exact v1.20 fixture key' );
tt_v122_assert( '88:300' === TactiTwin_Planning_Context::selection_from_context( $context ), 'planning context round-trips to one deterministic selector value' );

foreach ( array(
    'tactitwin_prepare_scenario',
    'tactitwin_generate_match_plan',
    'tactitwin_run_what_if',
    'tactitwin_run_live_playbook',
) as $action ) {
    $payload = TactiTwin_Planning_Context::apply_to_payload(
        array( 'team_id' => '999', 'opponent_id' => '998' ),
        $context,
        $action
    );
    tt_v122_assert( '300' === $payload['team_id'], $action . ' inherits the shared team perspective' );
    tt_v122_assert( '400' === $payload['opponent_id'], $action . ' inherits the shared opponent' );
    tt_v122_assert( ! isset( $payload['target_match_id'] ), $action . ' does not invent Staff Brief-only request fields' );
}

$staff_payload = TactiTwin_Planning_Context::apply_to_payload(
    array( 'team_id' => '999', 'opponent_id' => '998' ),
    $context,
    'tactitwin_generate_staff_brief'
);
tt_v122_assert( '300' === $staff_payload['team_id'], 'Staff Brief inherits the shared team perspective' );
tt_v122_assert( '400' === $staff_payload['opponent_id'], 'Staff Brief inherits the shared opponent' );
tt_v122_assert( '88' === $staff_payload['target_match_id'], 'Staff Brief receives the canonical imported match row for v1.21 resolution' );
tt_v122_assert( '1' === $staff_payload['tactitwin_bind_fixture'], 'Staff Brief fixture binding is forced when a canonical planning context exists' );

$away_context = TactiTwin_Planning_Context::build_context( TactiTwin_Match_Target_Service::resolve_row( $match, '400' ) );
tt_v122_assert( '88:400' === TactiTwin_Planning_Context::selection_from_context( $away_context ), 'opposite team perspective is a distinct shared context selection' );
tt_v122_assert( $context['fixture_key'] !== $away_context['fixture_key'], 'opposite perspectives preserve the distinct v1.20 fixture scopes' );

$invalid_context = $context;
unset( $invalid_context['opponent_source_id'] );
$thrown = false;
try {
    TactiTwin_Planning_Context::apply_to_payload( array(), $invalid_context, 'tactitwin_run_what_if' );
} catch ( InvalidArgumentException $e ) {
    $thrown = true;
}
tt_v122_assert( $thrown, 'incomplete shared planning context cannot silently reach a tactical module' );

echo "Planning context tests passed.\n";
