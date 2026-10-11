<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-manpower-metrics.php';

function tt_manpower_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_starting_xi_raw( int $first_id ): string {
    $lineup = array();
    for ( $i = 0; $i < 11; $i++ ) {
        $lineup[] = array( 'player' => array( 'id' => $first_id + $i ) );
    }
    return json_encode( array( 'tactics' => array( 'lineup' => $lineup ) ) );
}

$events = array(
    array( 'event_index' => 1, 'period' => 1, 'minute' => 0, 'second' => 0, 'team_source_id' => '10', 'player_source_id' => '', 'position_name' => '', 'event_type_name' => 'Starting XI', 'outcome_name' => '', 'raw_json' => tt_starting_xi_raw( 101 ) ),
    array( 'event_index' => 2, 'period' => 1, 'minute' => 0, 'second' => 0, 'team_source_id' => '20', 'player_source_id' => '', 'position_name' => '', 'event_type_name' => 'Starting XI', 'outcome_name' => '', 'raw_json' => tt_starting_xi_raw( 201 ) ),
    array( 'event_index' => 3, 'period' => 1, 'minute' => 10, 'second' => 0, 'team_source_id' => '10', 'player_source_id' => '101', 'position_name' => 'Center Forward', 'event_type_name' => 'Pass', 'outcome_name' => '', 'location_x' => 30, 'location_y' => 40, 'end_location_x' => 50, 'end_location_y' => 40, 'raw_json' => '{}' ),
    array( 'event_index' => 4, 'period' => 1, 'minute' => 20, 'second' => 0, 'team_source_id' => '20', 'player_source_id' => '299', 'position_name' => '', 'event_type_name' => 'Bad Behaviour', 'outcome_name' => '', 'raw_json' => json_encode( array( 'bad_behaviour' => array( 'card' => array( 'name' => 'Red Card' ) ) ) ) ),
    array( 'event_index' => 5, 'period' => 1, 'minute' => 25, 'second' => 0, 'team_source_id' => '20', 'player_source_id' => '201', 'position_name' => 'Center Forward', 'event_type_name' => 'Shot', 'outcome_name' => 'Saved', 'xg' => 0.20, 'location_x' => 105, 'location_y' => 40, 'raw_json' => '{}' ),
    array( 'event_index' => 6, 'period' => 1, 'minute' => 30, 'second' => 0, 'team_source_id' => '20', 'player_source_id' => '202', 'position_name' => 'Center Midfield', 'event_type_name' => 'Foul Committed', 'outcome_name' => '', 'raw_json' => json_encode( array( 'foul_committed' => array( 'card' => array( 'name' => 'Red Card' ) ) ) ) ),
    array( 'event_index' => 7, 'period' => 1, 'minute' => 40, 'second' => 0, 'team_source_id' => '10', 'player_source_id' => '102', 'position_name' => 'Left Wing', 'event_type_name' => 'Shot', 'outcome_name' => 'Saved', 'xg' => 0.30, 'location_x' => 106, 'location_y' => 38, 'raw_json' => '{}' ),
    array( 'event_index' => 8, 'period' => 2, 'minute' => 50, 'second' => 0, 'team_source_id' => '20', 'player_source_id' => '203', 'position_name' => 'Right Back', 'event_type_name' => 'Substitution', 'outcome_name' => '', 'raw_json' => json_encode( array( 'substitution' => array( 'replacement' => array( 'id' => 250 ) ) ) ) ),
    array( 'event_index' => 9, 'period' => 2, 'minute' => 60, 'second' => 0, 'team_source_id' => '20', 'player_source_id' => '250', 'position_name' => 'Right Back', 'event_type_name' => 'Pass', 'outcome_name' => '', 'location_x' => 70, 'location_y' => 30, 'end_location_x' => 85, 'end_location_y' => 30, 'raw_json' => '{}' ),
    array( 'event_index' => 10, 'period' => 2, 'minute' => 70, 'second' => 0, 'team_source_id' => '20', 'player_source_id' => '250', 'position_name' => 'Right Back', 'event_type_name' => 'Bad Behaviour', 'outcome_name' => '', 'raw_json' => json_encode( array( 'bad_behaviour' => array( 'card' => array( 'name' => 'Second Yellow' ) ) ) ) ),
    array( 'event_index' => 11, 'period' => 2, 'minute' => 80, 'second' => 0, 'team_source_id' => '10', 'player_source_id' => '103', 'position_name' => 'Center Midfield', 'event_type_name' => 'Pressure', 'outcome_name' => '', 'location_x' => 85, 'location_y' => 40, 'raw_json' => '{}' ),
    array( 'event_index' => 12, 'period' => 2, 'minute' => 85, 'second' => 0, 'team_source_id' => '20', 'player_source_id' => '202', 'position_name' => 'Center Midfield', 'event_type_name' => 'Bad Behaviour', 'outcome_name' => '', 'raw_json' => json_encode( array( 'bad_behaviour' => array( 'card' => array( 'name' => 'Second Yellow' ) ) ) ) ),
);

$result = TactiTwin_Manpower_Metrics::calculate( $events, array( '10', '20' ) );

tt_manpower_assert( 30.0 === $result['10']['equal']['minutes'], 'bench red card must not change 11v11 state' );
tt_manpower_assert( 60.0 === $result['10']['superior']['minutes'], 'team 10 superior minutes after active-player dismissal' );
tt_manpower_assert( 30.0 === $result['20']['equal']['minutes'], 'team 20 remains equal until active player is dismissed' );
tt_manpower_assert( 60.0 === $result['20']['inferior']['minutes'], 'team 20 inferior minutes after active-player dismissal' );
tt_manpower_assert( 1 === $result['10']['superior']['shots'], 'team 10 post-red shot assigned to superior state' );
tt_manpower_assert( 1 === $result['20']['inferior']['final_third_entries'], 'substitute action after red assigned to inferior state' );
tt_manpower_assert( 0.45 === $result['10']['superior']['per_90']['xg'], 'superior-state xG normalized per 90' );
tt_manpower_assert( 0.0 === $result['10']['inferior']['minutes'], 'duplicate dismissal cannot reverse manpower state' );

echo "Manpower metrics tests passed.\n";
