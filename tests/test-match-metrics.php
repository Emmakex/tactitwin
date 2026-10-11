<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-match-metrics.php';

function tt_metric_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$events = array(
    array(
        'team_source_id' => '1', 'event_type_name' => 'Pass', 'outcome_name' => '',
        'location_x' => 60, 'location_y' => 20, 'end_location_x' => 85, 'end_location_y' => 20, 'xg' => null,
    ),
    array(
        'team_source_id' => '1', 'event_type_name' => 'Pass', 'outcome_name' => 'Incomplete',
        'location_x' => 70, 'location_y' => 40, 'end_location_x' => 90, 'end_location_y' => 40, 'xg' => null,
    ),
    array(
        'team_source_id' => '1', 'event_type_name' => 'Pass', 'outcome_name' => '',
        'location_x' => 20, 'location_y' => 30, 'end_location_x' => 45, 'end_location_y' => 30, 'xg' => null,
    ),
    array(
        'team_source_id' => '1', 'event_type_name' => 'Pass', 'outcome_name' => 'Incomplete',
        'location_x' => 30, 'location_y' => 35, 'end_location_x' => 35, 'end_location_y' => 35, 'xg' => null,
    ),
    array(
        'team_source_id' => '1', 'event_type_name' => 'Carry', 'outcome_name' => '',
        'location_x' => 25, 'location_y' => 40, 'end_location_x' => 42, 'end_location_y' => 40, 'xg' => null,
    ),
    array(
        'team_source_id' => '1', 'event_type_name' => 'Carry', 'outcome_name' => '',
        'location_x' => 95, 'location_y' => 40, 'end_location_x' => 105, 'end_location_y' => 40, 'xg' => null,
    ),
    array(
        'team_source_id' => '1', 'event_type_name' => 'Shot', 'outcome_name' => 'Goal',
        'location_x' => 110, 'location_y' => 40, 'end_location_x' => 120, 'end_location_y' => 40, 'xg' => 0.4,
    ),
    array(
        'team_source_id' => '1', 'event_type_name' => 'Pressure', 'outcome_name' => '',
        'location_x' => 60, 'location_y' => 40, 'end_location_x' => null, 'end_location_y' => null, 'xg' => null,
    ),
    array(
        'team_source_id' => '1', 'event_type_name' => 'Pressure', 'outcome_name' => '',
        'location_x' => 90, 'location_y' => 40, 'end_location_x' => null, 'end_location_y' => null, 'xg' => null,
    ),
    array(
        'team_source_id' => '1', 'event_type_name' => 'Ball Recovery', 'outcome_name' => '',
        'location_x' => 85, 'location_y' => 40, 'end_location_x' => null, 'end_location_y' => null, 'xg' => null,
    ),
    array(
        'team_source_id' => '1', 'event_type_name' => 'Miscontrol', 'outcome_name' => '',
        'location_x' => 25, 'location_y' => 40, 'end_location_x' => null, 'end_location_y' => null, 'xg' => null,
    ),
    array(
        'team_source_id' => '2', 'event_type_name' => 'Pass', 'outcome_name' => '',
        'location_x' => 40, 'location_y' => 70, 'end_location_x' => 60, 'end_location_y' => 70, 'xg' => null,
    ),
);

$result = TactiTwin_Match_Metrics::calculate( $events );

$one = $result['1'];
tt_metric_assert( 4 === $one['passes'], 'passes counted' );
tt_metric_assert( 2 === $one['completed_passes'], 'completed passes counted' );
tt_metric_assert( 50.0 === $one['pass_completion_pct'], 'pass completion calculated' );
tt_metric_assert( 2 === $one['final_third_entries'], 'final-third entries counted' );
tt_metric_assert( 1 === $one['box_entries'], 'box entries counted' );
tt_metric_assert( 4 === $one['progressive_actions'], 'progressive actions counted' );
tt_metric_assert( 1 === $one['shots'], 'shots counted' );
tt_metric_assert( 1 === $one['goals'], 'goals counted' );
tt_metric_assert( 0.4 === $one['xg'], 'xG summed' );
tt_metric_assert( 2 === $one['pressures'], 'pressures counted' );
tt_metric_assert( 1 === $one['high_pressures'], 'high pressures counted' );
tt_metric_assert( 1 === $one['high_recoveries'], 'high recoveries counted' );
tt_metric_assert( 3 === $one['build_up_actions'], 'build-up actions counted' );
tt_metric_assert( 2 === $one['build_up_passes'], 'build-up passes counted' );
tt_metric_assert( 50.0 === $one['build_up_completion_pct'], 'build-up completion calculated' );
tt_metric_assert( 2 === $one['build_up_escapes'], 'build-up escapes counted' );
tt_metric_assert( 66.7 === $one['build_up_escape_rate'], 'build-up escape rate calculated' );
tt_metric_assert( 2 === $one['own_third_turnovers'], 'own-third turnovers counted' );
tt_metric_assert( 50.0 === $one['high_pressure_share_pct'], 'high-pressure share calculated' );

echo "Match metrics tests passed.\n";
