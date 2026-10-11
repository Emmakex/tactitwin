<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-match-metrics.php';

function tt_low_block_metric_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$events = array(
    array( 'team_source_id' => '10', 'event_type_name' => 'Pressure', 'outcome_name' => '', 'location_x' => 20, 'location_y' => 40 ),
    array( 'team_source_id' => '10', 'event_type_name' => 'Ball Recovery', 'outcome_name' => '', 'location_x' => 30, 'location_y' => 30 ),
    array( 'team_source_id' => '10', 'event_type_name' => 'Duel', 'outcome_name' => '', 'location_x' => 25, 'location_y' => 50 ),
    array( 'team_source_id' => '10', 'event_type_name' => 'Interception', 'outcome_name' => '', 'location_x' => 55, 'location_y' => 40 ),
    array( 'team_source_id' => '10', 'event_type_name' => 'Shot', 'outcome_name' => '', 'xg' => 0.20, 'location_x' => 110, 'location_y' => 40 ),
    array( 'team_source_id' => '10', 'event_type_name' => 'Shot', 'outcome_name' => '', 'xg' => 0.05, 'location_x' => 90, 'location_y' => 40 ),
);

$result = TactiTwin_Match_Metrics::calculate( $events );
$team   = $result['10'];

tt_low_block_metric_assert( 4 === $team['defensive_actions'], 'defensive actions counted' );
tt_low_block_metric_assert( 3 === $team['deep_defensive_actions'], 'deep defensive actions counted' );
tt_low_block_metric_assert( 75.0 === $team['deep_defensive_action_share_pct'], 'deep defensive share calculated' );
tt_low_block_metric_assert( 2 === $team['shots'], 'shots counted' );
tt_low_block_metric_assert( 1 === $team['box_shots'], 'box shots counted' );
tt_low_block_metric_assert( 50.0 === $team['box_shot_share_pct'], 'box shot share calculated' );
tt_low_block_metric_assert( 0.25 === $team['xg'], 'total xG calculated' );
tt_low_block_metric_assert( 0.20 === $team['box_xg'], 'box xG calculated' );
tt_low_block_metric_assert( 0.125 === $team['xg_per_shot'], 'xG per shot calculated' );
tt_low_block_metric_assert( 0.20 === $team['box_xg_per_shot'], 'box xG per shot calculated' );

echo "Low block metric tests passed.\n";
