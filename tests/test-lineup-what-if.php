<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-formation-model.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-lineup-what-if.php';

function tt_lineup_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_lineup_player( int $id, string $name, string $position, array $overrides = array() ): array {
    $per90 = array_merge(
        array(
            'passes'=>45.0,'carries'=>18.0,'progressive_actions'=>6.0,'final_third_entries'=>3.0,
            'box_entries'=>1.2,'shots'=>1.0,'xg'=>0.10,'pressures'=>10.0,'recoveries'=>5.0,'duels'=>5.0,'turnovers'=>1.2,
        ),
        $overrides['per_90'] ?? array()
    );
    return array(
        'player_source_id'=>(string) $id,
        'player_name'=>$name,
        'position_name'=>$position,
        'coverage'=>array('minutes'=>(float) ( $overrides['minutes'] ?? 600.0 ),'appearances'=>8,'starts'=>7,'confidence'=>'high'),
        'rates'=>array('pass_completion_pct'=>(float) ( $overrides['pass_completion_pct'] ?? 88.0 )),
        'per_90'=>$per90,
    );
}

$players = array(
    1=>tt_lineup_player(1,'GK','Goalkeeper'),
    2=>tt_lineup_player(2,'RB','Right Back'),
    3=>tt_lineup_player(3,'RCB','Right Center Back'),
    4=>tt_lineup_player(4,'LCB','Left Center Back'),
    5=>tt_lineup_player(5,'LB','Left Back'),
    6=>tt_lineup_player(6,'DM','Center Defensive Midfield'),
    7=>tt_lineup_player(7,'RCM','Right Center Midfield'),
    8=>tt_lineup_player(8,'LCM','Left Center Midfield'),
    9=>tt_lineup_player(9,'RW','Right Wing'),
    10=>tt_lineup_player(10,'ST','Center Forward'),
    11=>tt_lineup_player(11,'LW','Left Wing'),
    12=>tt_lineup_player(12,'AM','Center Attacking Midfield',array(
        'pass_completion_pct'=>85.0,
        'per_90'=>array('progressive_actions'=>9.0,'final_third_entries'=>6.0,'box_entries'=>3.0,'shots'=>2.4,'xg'=>0.32,'pressures'=>14.0,'turnovers'=>1.8),
    )),
);

$baseline = array( $players[1],$players[2],$players[3],$players[4],$players[5],$players[6],$players[7],$players[8],$players[9],$players[10],$players[11] );
$candidate = array( $players[1],$players[2],$players[3],$players[4],$players[5],$players[6],$players[8],$players[9],$players[12],$players[11],$players[10] );
$context = array(
    'hypothesis'=>array('minute'=>70,'our_score'=>0,'opponent_score'=>1,'state'=>'trailing','our_players'=>11,'opponent_players'=>11),
    'risk'=>array('score'=>65.0),'opportunity'=>array('score'=>57.0),
);

$en = TactiTwin_Lineup_What_If::compare( $baseline, $candidate, '4-3-3', '4-2-3-1', 'en', $context );
$es = TactiTwin_Lineup_What_If::compare( $baseline, $candidate, '4-3-3', '4-2-3-1', 'es', $context );

tt_lineup_assert( 'lineup-formation-what-if' === $en['scenario_id'], 'scenario id returned' );
tt_lineup_assert( 1 === $en['changes']['personnel_changes'], 'single personnel change detected' );
tt_lineup_assert( '7' === (string) $en['changes']['outgoing'][0]['player_source_id'], 'outgoing player detected' );
tt_lineup_assert( '12' === (string) $en['changes']['incoming'][0]['player_source_id'], 'incoming player detected' );
tt_lineup_assert( $en['delta']['structure']['double_pivot'] > 0, 'double pivot structural increase detected' );
tt_lineup_assert( $en['delta']['dimensions']['attacking_threat'] > 0, 'more attacking threat detected' );
tt_lineup_assert( 'high' === $en['coverage']['status'], 'strong sample classified high' );
tt_lineup_assert( $en['delta'] === $es['delta'], 'language does not alter lineup deltas' );
tt_lineup_assert( $en['changes'] === $es['changes'], 'language does not alter personnel or role changes' );
tt_lineup_assert( $en['recommendations'][0] !== $es['recommendations'][0], 'recommendations translated' );
tt_lineup_assert( 'trailing' === $en['match_context']['hypothesis']['state'], 'match context retained' );
tt_lineup_assert( in_array( '4-3-3', TactiTwin_Formation_Model::supported(), true ), '4-3-3 supported' );
tt_lineup_assert( 92.0 === TactiTwin_Formation_Model::role_compatibility( 'Right Back', 'RWB' )['score'], 'fullback-wingback adjacency explicit' );

$low_candidate = $candidate;
$low_candidate[8]['coverage']['minutes'] = 50.0;
$low = TactiTwin_Lineup_What_If::compare( $baseline, $low_candidate, '4-3-3', '4-2-3-1', 'en' );
tt_lineup_assert( 'low' === $low['coverage']['status'], 'low player coverage lowers lineup confidence' );
tt_lineup_assert( ! empty( $low['coverage']['warning'] ), 'low coverage warning emitted' );

$duplicate = $baseline;
$duplicate[10] = $duplicate[9];
$thrown = false;
try {
    TactiTwin_Lineup_What_If::compare( $duplicate, $candidate, '4-3-3', '4-2-3-1', 'en' );
} catch ( InvalidArgumentException $e ) {
    $thrown = true;
}
tt_lineup_assert( $thrown, 'duplicate lineup rejected' );

echo "Lineup & formation What-if tests passed.\n";
