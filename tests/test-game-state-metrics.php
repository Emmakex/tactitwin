<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-game-state-metrics.php';

function tt_game_state_metrics_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$events = array(
    array( 'period' => 1, 'minute' => 0, 'second' => 0, 'team_source_id' => '10', 'event_type_name' => 'Pass', 'outcome_name' => '', 'location_x' => 30, 'location_y' => 40, 'end_location_x' => 50, 'end_location_y' => 40 ),
    array( 'period' => 1, 'minute' => 10, 'second' => 0, 'team_source_id' => '10', 'event_type_name' => 'Shot', 'outcome_name' => 'Goal', 'xg' => 0.30, 'location_x' => 108, 'location_y' => 40 ),
    array( 'period' => 1, 'minute' => 20, 'second' => 0, 'team_source_id' => '20', 'event_type_name' => 'Shot', 'outcome_name' => 'Saved', 'xg' => 0.20, 'location_x' => 106, 'location_y' => 35 ),
    array( 'period' => 1, 'minute' => 30, 'second' => 0, 'team_source_id' => '20', 'event_type_name' => 'Shot', 'outcome_name' => 'Goal', 'xg' => 0.40, 'location_x' => 108, 'location_y' => 40 ),
    array( 'period' => 2, 'minute' => 45, 'second' => 0, 'team_source_id' => '10', 'event_type_name' => 'Pass', 'outcome_name' => '', 'location_x' => 70, 'location_y' => 20, 'end_location_x' => 85, 'end_location_y' => 20 ),
    array( 'period' => 2, 'minute' => 60, 'second' => 0, 'team_source_id' => '20', 'event_type_name' => 'Shot', 'outcome_name' => 'Goal', 'xg' => 0.25, 'location_x' => 107, 'location_y' => 42 ),
    array( 'period' => 2, 'minute' => 75, 'second' => 0, 'team_source_id' => '10', 'event_type_name' => 'Shot', 'outcome_name' => 'Saved', 'xg' => 0.15, 'location_x' => 105, 'location_y' => 38 ),
);

$result = TactiTwin_Game_State_Metrics::calculate( $events, array( '10', '20' ) );

tt_game_state_metrics_assert( isset( $result['10'], $result['20'] ), 'both teams returned' );
tt_game_state_metrics_assert( 40.0 === $result['10']['drawing']['minutes'], 'team 10 drawing minutes reconstructed' );
tt_game_state_metrics_assert( 20.0 === $result['10']['leading']['minutes'], 'team 10 leading minutes reconstructed' );
tt_game_state_metrics_assert( 30.0 === $result['10']['trailing']['minutes'], 'team 10 trailing minutes reconstructed' );
tt_game_state_metrics_assert( 20.0 === $result['20']['trailing']['minutes'], 'team 20 trailing minutes reciprocal' );
tt_game_state_metrics_assert( 30.0 === $result['20']['leading']['minutes'], 'team 20 leading minutes reciprocal' );
tt_game_state_metrics_assert( 1 === $result['10']['drawing']['goals'], 'opening goal attributed to pre-goal drawing state' );
tt_game_state_metrics_assert( 1 === $result['20']['trailing']['goals'], 'equalizer attributed to trailing state' );
tt_game_state_metrics_assert( 1 === $result['20']['drawing']['goals'], 'go-ahead goal attributed to drawing state' );
tt_game_state_metrics_assert( 1 === $result['10']['trailing']['shots'], 'late shot allocated to trailing state' );
tt_game_state_metrics_assert( 0.45 === $result['10']['trailing']['per_90']['xg'], 'trailing xG normalized per 90' );

echo "Game-state metrics tests passed.\n";
