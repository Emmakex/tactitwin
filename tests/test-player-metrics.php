<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-player-metrics.php';

function tt_player_metrics_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$lineup = array();
for ( $i = 101; $i <= 111; $i++ ) {
    $lineup[] = array( 'player' => array( 'id' => $i, 'name' => 'P' . $i ), 'position' => array( 'name' => $i === 101 ? 'Center Midfield' : 'Position' ) );
}

$events = array(
    array( 'period'=>1,'minute'=>0,'second'=>0,'team_source_id'=>'10','player_source_id'=>'','position_name'=>'','event_type_name'=>'Starting XI','outcome_name'=>'','raw_json'=>json_encode( array( 'tactics'=>array( 'lineup'=>$lineup ) ) ) ),
    array( 'period'=>1,'minute'=>10,'second'=>0,'team_source_id'=>'10','player_source_id'=>'101','position_name'=>'Center Midfield','event_type_name'=>'Pass','outcome_name'=>'','location_x'=>50,'location_y'=>40,'end_location_x'=>70,'end_location_y'=>40,'raw_json'=>'{}' ),
    array( 'period'=>1,'minute'=>20,'second'=>0,'team_source_id'=>'10','player_source_id'=>'101','position_name'=>'Center Midfield','event_type_name'=>'Pressure','outcome_name'=>'','location_x'=>70,'location_y'=>40,'raw_json'=>'{}' ),
    array( 'period'=>2,'minute'=>60,'second'=>0,'team_source_id'=>'10','player_source_id'=>'101','position_name'=>'Center Midfield','event_type_name'=>'Substitution','outcome_name'=>'','raw_json'=>json_encode( array( 'substitution'=>array( 'replacement'=>array( 'id'=>150,'name'=>'Incoming' ) ) ) ) ),
    array( 'period'=>2,'minute'=>65,'second'=>0,'team_source_id'=>'10','player_source_id'=>'150','position_name'=>'Center Midfield','event_type_name'=>'Carry','outcome_name'=>'','location_x'=>70,'location_y'=>40,'end_location_x'=>88,'end_location_y'=>40,'raw_json'=>'{}' ),
    array( 'period'=>2,'minute'=>70,'second'=>0,'team_source_id'=>'10','player_source_id'=>'150','position_name'=>'Center Midfield','event_type_name'=>'Shot','outcome_name'=>'Saved','xg'=>0.20,'location_x'=>106,'location_y'=>40,'raw_json'=>'{}' ),
    array( 'period'=>2,'minute'=>80,'second'=>0,'team_source_id'=>'10','player_source_id'=>'150','position_name'=>'Center Midfield','event_type_name'=>'Miscontrol','outcome_name'=>'','raw_json'=>'{}' ),
);

$result = TactiTwin_Player_Metrics::calculate( $events, '10' );

tt_player_metrics_assert( 60.0 === $result['101']['minutes'], 'starter minutes close at substitution' );
tt_player_metrics_assert( 30.0 === $result['150']['minutes'], 'incoming substitute receives remaining minutes' );
tt_player_metrics_assert( true === $result['101']['starter'], 'starter flag retained' );
tt_player_metrics_assert( 1 === $result['150']['substitute_appearances'], 'substitute appearance recorded' );
tt_player_metrics_assert( 1 === $result['101']['progressive_actions'], 'starter progressive action counted' );
tt_player_metrics_assert( 1 === $result['150']['progressive_actions'], 'incoming carry progression counted' );
tt_player_metrics_assert( 0.6 === $result['150']['per_90']['xg'], 'incoming xG normalized over 30 minutes' );
tt_player_metrics_assert( 3.0 === $result['150']['per_90']['turnovers'], 'incoming turnover normalized over 30 minutes' );
tt_player_metrics_assert( 'Center Midfield' === $result['150']['position_name'], 'incoming position inferred from events' );

echo "Player metrics tests passed.\n";
