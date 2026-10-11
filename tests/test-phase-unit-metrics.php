<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-unit-metrics.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-phase-unit-metrics.php';

function tt_phase_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_phase_event( int $index, int $minute, string $type, string $player, int $possession, array $extra = array() ): array {
    return array_merge(
        array(
            'event_index'=>$index,
            'period'=>$minute >= 45 ? 2 : 1,
            'minute'=>$minute,
            'second'=>0,
            'possession'=>$possession,
            'possession_team_source_id'=>'T',
            'team_source_id'=>'T',
            'player_source_id'=>$player,
            'event_type_name'=>$type,
            'outcome_name'=>'',
            'xg'=>null,
            'location_x'=>null,
            'location_y'=>null,
            'end_location_x'=>null,
            'end_location_y'=>null,
            'raw_json'=>array(),
        ),
        $extra
    );
}

$starting = tt_phase_event( 1, 0, 'Starting XI', '', 0, array(
    'raw_json'=>array('tactics'=>array('lineup'=>array(
        array('player'=>array('id'=>'A','name'=>'A')),
        array('player'=>array('id'=>'B','name'=>'B')),
        array('player'=>array('id'=>'C','name'=>'C')),
    ))),
) );

$build = tt_phase_event( 2, 5, 'Pass', 'A', 1, array(
    'location_x'=>20.0,'location_y'=>40.0,'end_location_x'=>50.0,'end_location_y'=>42.0,
    'raw_json'=>array('pass'=>array('recipient'=>array('id'=>'B','name'=>'B'))),
) );
$build_touch = tt_phase_event( 3, 5, 'Carry', 'B', 1, array('location_x'=>50.0,'location_y'=>42.0) );

$progression = tt_phase_event( 4, 15, 'Pass', 'B', 2, array(
    'location_x'=>55.0,'location_y'=>35.0,'end_location_x'=>85.0,'end_location_y'=>34.0,
    'raw_json'=>array('pass'=>array('recipient'=>array('id'=>'C','name'=>'C'))),
) );
$progression_touch = tt_phase_event( 5, 15, 'Carry', 'C', 2, array('location_x'=>85.0,'location_y'=>34.0) );

$final_pass = tt_phase_event( 6, 25, 'Pass', 'C', 3, array(
    'location_x'=>86.0,'location_y'=>40.0,'end_location_x'=>104.0,'end_location_y'=>40.0,
    'raw_json'=>array('pass'=>array('recipient'=>array('id'=>'A','name'=>'A'),'shot_assist'=>true)),
) );
$shot = tt_phase_event( 7, 25, 'Shot', 'A', 3, array(
    'location_x'=>104.0,'location_y'=>40.0,'xg'=>0.40,
) );

$pressure = tt_phase_event( 8, 35, 'Pressure', 'B', 4, array(
    'possession_team_source_id'=>'O','location_x'=>78.0,'location_y'=>30.0,
) );
$recovery = tt_phase_event( 9, 35, 'Ball Recovery', 'C', 4, array(
    'second'=>5,'possession_team_source_id'=>'O','location_x'=>76.0,'location_y'=>31.0,
) );

$result = TactiTwin_Phase_Unit_Metrics::calculate(
    array( $starting, $build, $build_touch, $progression, $progression_touch, $final_pass, $shot, $pressure, $recovery ),
    'T'
);

$ab = $result['pairs'][ TactiTwin_Unit_Metrics::pair_key('A','B') ];
$bc = $result['pairs'][ TactiTwin_Unit_Metrics::pair_key('B','C') ];
$ac = $result['pairs'][ TactiTwin_Unit_Metrics::pair_key('A','C') ];

tt_phase_assert( 90.0 === $ab['shared_minutes'], 'phase layer inherits exact shared minutes' );
tt_phase_assert( 1 === $ab['phases']['build_up']['completed_passes'], 'A-B build-up connection classified' );
tt_phase_assert( 1 === $ab['phases']['build_up']['progressive_passes'], 'build-up progressive pass retained' );
tt_phase_assert( 0 === $ab['phases']['progression']['completed_passes'], 'build-up pass does not leak into progression phase' );

tt_phase_assert( 1 === $bc['phases']['progression']['completed_passes'], 'B-C progression connection classified' );
tt_phase_assert( 1 === $bc['phases']['progression']['progressive_passes'], 'B-C progressive action retained' );
tt_phase_assert( 1 === $bc['phases']['progression']['final_third_entries'], 'B-C entry into final third retained' );
tt_phase_assert( 1 === $bc['phases']['high_press']['pressure_recovery_sequences'], 'B-C high-press recovery sequence classified' );

tt_phase_assert( 1 === $ac['phases']['final_third']['completed_passes'], 'C-A final-third connection classified' );
tt_phase_assert( 1 === $ac['phases']['final_third']['box_entries'], 'final-third box entry retained' );
tt_phase_assert( 1 === $ac['phases']['final_third']['shot_assists'], 'final-third shot assist retained' );
tt_phase_assert( 1 === $ac['phases']['final_third']['joint_shot_possessions'], 'shared final-third shot possession retained' );
tt_phase_assert( 0.40 === $ac['phases']['final_third']['joint_possession_xg'], 'shared final-third possession xG retained' );

tt_phase_assert( 1.0 === $ab['phases']['build_up']['per_90_shared']['completed_passes'], 'phase rate normalized per 90 shared minutes' );
tt_phase_assert( 1.0 === $bc['phases']['high_press']['per_90_shared']['pressure_recovery_sequences'], 'high-press sequence normalized per 90 shared minutes' );

echo "Phase unit metric tests passed.\n";
