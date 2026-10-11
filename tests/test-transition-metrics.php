<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-transition-metrics.php';

function tt_transition_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$events = array(
    array( 'period' => 1, 'minute' => 0, 'second' => 10, 'team_source_id' => '1', 'event_type_name' => 'Pass', 'outcome_name' => 'Incomplete', 'location_x' => 50, 'end_location_x' => 55, 'xg' => 0 ),
    array( 'period' => 1, 'minute' => 0, 'second' => 12, 'team_source_id' => '2', 'event_type_name' => 'Pass', 'outcome_name' => '', 'location_x' => 45, 'end_location_x' => 68, 'xg' => 0 ),
    array( 'period' => 1, 'minute' => 0, 'second' => 16, 'team_source_id' => '2', 'event_type_name' => 'Shot', 'outcome_name' => 'Saved', 'location_x' => 105, 'end_location_x' => 120, 'xg' => 0.30 ),
    array( 'period' => 1, 'minute' => 0, 'second' => 17, 'team_source_id' => '1', 'event_type_name' => 'Ball Recovery', 'outcome_name' => '', 'location_x' => 35, 'end_location_x' => null, 'xg' => 0 ),

    array( 'period' => 1, 'minute' => 0, 'second' => 30, 'team_source_id' => '1', 'event_type_name' => 'Miscontrol', 'outcome_name' => '', 'location_x' => 20, 'end_location_x' => null, 'xg' => 0 ),
    array( 'period' => 1, 'minute' => 0, 'second' => 32, 'team_source_id' => '2', 'event_type_name' => 'Ball Receipt*', 'outcome_name' => '', 'location_x' => 50, 'end_location_x' => null, 'xg' => 0 ),
    array( 'period' => 1, 'minute' => 0, 'second' => 34, 'team_source_id' => '1', 'event_type_name' => 'Interception', 'outcome_name' => '', 'location_x' => 32, 'end_location_x' => null, 'xg' => 0 ),

    array( 'period' => 1, 'minute' => 0, 'second' => 50, 'team_source_id' => '2', 'event_type_name' => 'Pass', 'outcome_name' => 'Incomplete', 'location_x' => 60, 'end_location_x' => 65, 'xg' => 0 ),
    array( 'period' => 1, 'minute' => 0, 'second' => 54, 'team_source_id' => '1', 'event_type_name' => 'Carry', 'outcome_name' => '', 'location_x' => 40, 'end_location_x' => 61, 'xg' => 0 ),
    array( 'period' => 1, 'minute' => 0, 'second' => 58, 'team_source_id' => '1', 'event_type_name' => 'Shot', 'outcome_name' => 'Saved', 'location_x' => 108, 'end_location_x' => 120, 'xg' => 0.20 ),
);

$result = TactiTwin_Transition_Metrics::calculate( $events );
$one = $result['1'];
$two = $result['2'];

tt_transition_assert( 2 === $one['transition_turnovers'], 'team one turnovers counted' );
tt_transition_assert( 1 === $one['turnovers_by_zone']['own_third'], 'own-third turnover counted' );
tt_transition_assert( 1 === $one['turnovers_by_zone']['middle_third'], 'middle-third turnover counted' );
tt_transition_assert( 1 === $one['opponent_progressions_within_10s'], 'opponent progression after loss counted' );
tt_transition_assert( 1 === $one['opponent_shots_within_10s'], 'opponent shot after loss counted' );
tt_transition_assert( 0.3 === $one['xg_conceded_within_10s'], 'transition xG conceded summed' );
tt_transition_assert( 2 === $one['counterpress_recoveries_within_8s'], 'quick recoveries counted' );
tt_transition_assert( 50.0 === $one['dangerous_turnover_rate'], 'dangerous turnover rate calculated' );
tt_transition_assert( 50.0 === $one['transition_progression_rate'], 'progression-after-loss rate calculated' );
tt_transition_assert( 100.0 === $one['counterpress_recovery_rate'], 'counterpress recovery rate calculated' );
tt_transition_assert( 1 === $one['transition_progressions_created_within_10s'], 'attacking transition progression credited' );
tt_transition_assert( 1 === $one['transition_shots_created_within_10s'], 'attacking transition shot credited' );
tt_transition_assert( 0.2 === $one['transition_xg_created_within_10s'], 'attacking transition xG credited' );

tt_transition_assert( 1 === $two['transition_turnovers'], 'team two turnover counted' );
tt_transition_assert( 1 === $two['transition_progressions_created_within_10s'], 'team two attacking progression credited' );
tt_transition_assert( 1 === $two['transition_shots_created_within_10s'], 'team two attacking shot credited' );
tt_transition_assert( 0.3 === $two['transition_xg_created_within_10s'], 'team two attacking xG credited' );

echo "Transition metrics tests passed.\n";
