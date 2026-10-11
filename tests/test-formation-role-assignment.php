<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-formation-model.php';

function tt_assignment_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$canonical = array(
    array( 'player_source_id'=>'1', 'player_name'=>'Keeper', 'position_name'=>'Goalkeeper' ),
    array( 'player_source_id'=>'2', 'player_name'=>'Right Back', 'position_name'=>'Right Back' ),
    array( 'player_source_id'=>'3', 'player_name'=>'Right CB', 'position_name'=>'Right Center Back' ),
    array( 'player_source_id'=>'4', 'player_name'=>'Left CB', 'position_name'=>'Left Center Back' ),
    array( 'player_source_id'=>'5', 'player_name'=>'Left Back', 'position_name'=>'Left Back' ),
    array( 'player_source_id'=>'6', 'player_name'=>'Pivot', 'position_name'=>'Center Defensive Midfield' ),
    array( 'player_source_id'=>'7', 'player_name'=>'Right 8', 'position_name'=>'Right Center Midfield' ),
    array( 'player_source_id'=>'8', 'player_name'=>'Left 8', 'position_name'=>'Left Center Midfield' ),
    array( 'player_source_id'=>'9', 'player_name'=>'Right Wing', 'position_name'=>'Right Wing' ),
    array( 'player_source_id'=>'10', 'player_name'=>'Striker', 'position_name'=>'Center Forward' ),
    array( 'player_source_id'=>'11', 'player_name'=>'Left Wing', 'position_name'=>'Left Wing' ),
);

$shuffled = array(
    $canonical[10], $canonical[4], $canonical[8], $canonical[2], $canonical[6], $canonical[0],
    $canonical[9], $canonical[1], $canonical[7], $canonical[3], $canonical[5],
);

$result = TactiTwin_Formation_Model::assign_players_to_roles( $shuffled, '4-3-3' );
$ids = array_map( static fn( array $player ): string => (string) $player['player_source_id'], $result['players'] );
$roles = array_map( static fn( array $slot ): string => (string) $slot['role'], $result['slots'] );

tt_assignment_assert( array( '1','2','3','4','5','6','7','8','9','10','11' ) === $ids, 'shuffled provider XI is restored to canonical 4-3-3 role order' );
tt_assignment_assert( array( 'GK','RB','RCB','LCB','LB','DM','RCM','LCM','RW','ST','LW' ) === $roles, 'canonical role order returned' );
tt_assignment_assert( 'global-position-family-and-side-assignment' === $result['method'], 'assignment method exposed' );
tt_assignment_assert( '2' === (string) $result['slots'][1]['player_source_id'], 'right back stays on right side' );
tt_assignment_assert( '5' === (string) $result['slots'][4]['player_source_id'], 'left back stays on left side' );
tt_assignment_assert( '3' === (string) $result['slots'][2]['player_source_id'], 'right center back stays on right side' );
tt_assignment_assert( '4' === (string) $result['slots'][3]['player_source_id'], 'left center back stays on left side' );
tt_assignment_assert( '9' === (string) $result['slots'][8]['player_source_id'], 'right winger stays on right side' );
tt_assignment_assert( '11' === (string) $result['slots'][10]['player_source_id'], 'left winger stays on left side' );

$wingback_fit = TactiTwin_Formation_Model::assign_players_to_roles(
    array(
        $canonical[0], $canonical[2], $canonical[3],
        array( 'player_source_id'=>'13', 'player_name'=>'Third CB', 'position_name'=>'Center Back' ),
        $canonical[1], $canonical[6], $canonical[7], $canonical[4],
        array( 'player_source_id'=>'14', 'player_name'=>'Right 10', 'position_name'=>'Right Attacking Midfield' ),
        array( 'player_source_id'=>'15', 'player_name'=>'Left 10', 'position_name'=>'Left Attacking Midfield' ),
        $canonical[9],
    ),
    '3-4-2-1'
);
tt_assignment_assert( '2' === (string) $wingback_fit['slots'][4]['player_source_id'], 'right fullback can map to right wingback' );
tt_assignment_assert( '5' === (string) $wingback_fit['slots'][7]['player_source_id'], 'left fullback can map to left wingback' );

echo "Formation role assignment tests passed.\n";
