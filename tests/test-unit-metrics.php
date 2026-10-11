<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-unit-metrics.php';

function tt_unit_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_unit_event(
    int $index,
    int $minute,
    int $second,
    string $type,
    string $player_id = '',
    int $possession = 1,
    array $extra = array()
): array {
    return array_merge(
        array(
            'event_index' => $index,
            'period' => $minute >= 45 ? 2 : 1,
            'minute' => $minute,
            'second' => $second,
            'possession' => $possession,
            'team_source_id' => 'T',
            'player_source_id' => $player_id,
            'event_type_name' => $type,
            'outcome_name' => '',
            'xg' => null,
            'location_x' => null,
            'location_y' => null,
            'end_location_x' => null,
            'end_location_y' => null,
            'raw_json' => array(),
        ),
        $extra
    );
}

function tt_unit_starting_event( array $ids ): array {
    $lineup = array();
    foreach ( $ids as $id ) {
        $lineup[] = array(
            'player'=>array('id'=>(string) $id,'name'=>(string) $id),
            'position'=>array('name'=>'Synthetic Position'),
        );
    }
    return tt_unit_event( 1, 0, 0, 'Starting XI', '', 0, array(
        'raw_json'=>array('tactics'=>array('lineup'=>$lineup)),
    ) );
}

$starting = tt_unit_event( 1, 0, 0, 'Starting XI', '', 0, array(
    'raw_json' => array(
        'tactics' => array(
            'lineup' => array(
                array( 'player'=>array('id'=>'A','name'=>'A'), 'position'=>array('name'=>'Right Back') ),
                array( 'player'=>array('id'=>'B','name'=>'B'), 'position'=>array('name'=>'Right Wing') ),
                array( 'player'=>array('id'=>'C','name'=>'C'), 'position'=>array('name'=>'Center Midfield') ),
            ),
        ),
    ),
) );

$pass_ab = tt_unit_event( 2, 10, 0, 'Pass', 'A', 1, array(
    'location_x'=>40.0,'location_y'=>60.0,'end_location_x'=>62.0,'end_location_y'=>58.0,
    'raw_json'=>array('pass'=>array('recipient'=>array('id'=>'B','name'=>'B'))),
) );
$carry_b = tt_unit_event( 3, 10, 5, 'Carry', 'B', 1 );
$pass_ba = tt_unit_event( 4, 20, 0, 'Pass', 'B', 2, array(
    'location_x'=>70.0,'location_y'=>55.0,'end_location_x'=>75.0,'end_location_y'=>50.0,
    'raw_json'=>array('pass'=>array('recipient'=>array('id'=>'A','name'=>'A'))),
) );
$touch_a = tt_unit_event( 5, 20, 3, 'Carry', 'A', 2 );
$pressure_a = tt_unit_event( 6, 30, 0, 'Pressure', 'A', 3 );
$recovery_b = tt_unit_event( 7, 30, 5, 'Ball Recovery', 'B', 3 );
$second_recovery_c = tt_unit_event( 8, 30, 7, 'Ball Recovery', 'C', 3 );
$sub = tt_unit_event( 9, 60, 0, 'Substitution', 'B', 4, array(
    'raw_json'=>array('substitution'=>array('replacement'=>array('id'=>'D','name'=>'D'))),
) );
$pass_ad = tt_unit_event( 10, 70, 0, 'Pass', 'A', 5, array(
    'location_x'=>78.0,'location_y'=>40.0,'end_location_x'=>104.0,'end_location_y'=>40.0,
    'raw_json'=>array('pass'=>array('recipient'=>array('id'=>'D','name'=>'D'),'shot_assist'=>true)),
) );
$shot_d = tt_unit_event( 11, 70, 4, 'Shot', 'D', 5, array('xg'=>0.35) );

$result = TactiTwin_Unit_Metrics::calculate(
    array( $starting, $pass_ab, $carry_b, $pass_ba, $touch_a, $pressure_a, $recovery_b, $second_recovery_c, $sub, $pass_ad, $shot_d ),
    'T'
);

$ab = $result['pairs'][ TactiTwin_Unit_Metrics::pair_key( 'A', 'B' ) ];
$ac = $result['pairs'][ TactiTwin_Unit_Metrics::pair_key( 'A', 'C' ) ];
$ad = $result['pairs'][ TactiTwin_Unit_Metrics::pair_key( 'A', 'D' ) ];
$bd_key = TactiTwin_Unit_Metrics::pair_key( 'B', 'D' );

tt_unit_assert( true === $result['lineup_seen'], 'starting lineup coverage detected' );
tt_unit_assert( 60.0 === $ab['shared_minutes'], 'A-B share exactly 60 minutes before substitution' );
tt_unit_assert( 30.0 === $ad['shared_minutes'], 'A-D share exactly 30 minutes after substitution' );
tt_unit_assert( 2 === $ab['completed_passes'], 'two direct A-B completed passes counted' );
tt_unit_assert( 1 === $ab['passes_a_to_b'] && 1 === $ab['passes_b_to_a'], 'pass direction retained' );
tt_unit_assert( 100.0 === $ab['direction_balance_pct'], 'balanced two-way connection detected' );
tt_unit_assert( 1 === $ab['progressive_passes'], 'progressive A-B connection counted' );
tt_unit_assert( 1 === $ab['pressure_recovery_sequences'], 'pressure-to-recovery sequence counted once' );
tt_unit_assert( 0 === $ac['pressure_recovery_sequences'], 'same pressure event is not reused for a second recovery' );
tt_unit_assert( 1 === $ad['completed_passes'], 'A-D direct pass counted' );
tt_unit_assert( 1 === $ad['final_third_entries'], 'A-D final-third entry counted' );
tt_unit_assert( 1 === $ad['box_entries'], 'A-D box entry counted' );
tt_unit_assert( 1 === $ad['shot_assists'], 'A-D shot assist counted' );
tt_unit_assert( 1 === $ad['joint_shot_possessions'], 'A-D shared shot possession counted' );
tt_unit_assert( 0.35 === $ad['joint_possession_xg'], 'A-D joint possession xG retained' );
tt_unit_assert( ! isset( $result['pairs'][ $bd_key ] ) || 0.0 === $result['pairs'][ $bd_key ]['shared_minutes'], 'substituted players do not receive false shared minutes' );

// An active-player dismissal must stop that player's shared-pitch clock at the card time.
$red_active = tt_unit_event( 2, 40, 0, 'Bad Behaviour', 'A', 2, array(
    'raw_json'=>array('bad_behaviour'=>array('card'=>array('name'=>'Red Card'))),
) );
$red_result = TactiTwin_Unit_Metrics::calculate(
    array( tt_unit_starting_event( array('A','B','C') ), $red_active ),
    'T'
);
$red_ab = $red_result['pairs'][ TactiTwin_Unit_Metrics::pair_key( 'A', 'B' ) ];
$red_ac = $red_result['pairs'][ TactiTwin_Unit_Metrics::pair_key( 'A', 'C' ) ];
$red_bc = $red_result['pairs'][ TactiTwin_Unit_Metrics::pair_key( 'B', 'C' ) ];
tt_unit_assert( 40.0 === $red_ab['shared_minutes'], 'red card cuts A-B shared clock at minute 40' );
tt_unit_assert( 40.0 === $red_ac['shared_minutes'], 'red card cuts A-C shared clock at minute 40' );
tt_unit_assert( 90.0 === $red_bc['shared_minutes'], 'remaining active pair continues to full time after red card' );

// A dismissal to a player who was not active must not change the active-pair clock.
$bench_red = tt_unit_event( 2, 20, 0, 'Foul Committed', 'X', 2, array(
    'raw_json'=>array('foul_committed'=>array('card'=>array('name'=>'Second Yellow'))),
) );
$bench_red_result = TactiTwin_Unit_Metrics::calculate(
    array( tt_unit_starting_event( array('B','C') ), $bench_red ),
    'T'
);
$bench_bc = $bench_red_result['pairs'][ TactiTwin_Unit_Metrics::pair_key( 'B', 'C' ) ];
tt_unit_assert( 90.0 === $bench_bc['shared_minutes'], 'bench dismissal does not alter active B-C shared clock' );

echo "Unit interaction metric tests passed.\n";
