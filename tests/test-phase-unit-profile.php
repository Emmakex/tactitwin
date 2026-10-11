<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-phase-unit-profile.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-phase-unit-comparison.php';

function tt_phase_profile_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_phase_sample( string $match_id ): array {
    return array(
        'match_id'=>$match_id,
        'metrics'=>array(
            'shared_minutes'=>90.0,
            'phases'=>array(
                'build_up'=>array('completed_passes'=>12,'progressive_passes'=>4,'final_third_entries'=>0,'box_entries'=>0,'shot_assists'=>0,'goal_assists'=>0),
                'progression'=>array('completed_passes'=>8,'progressive_passes'=>5,'final_third_entries'=>3,'box_entries'=>1,'shot_assists'=>0,'goal_assists'=>0),
                'final_third'=>array('completed_passes'=>4,'progressive_passes'=>1,'final_third_entries'=>0,'box_entries'=>2,'shot_assists'=>1,'goal_assists'=>0,'joint_shot_possessions'=>2,'joint_possession_xg'=>0.45),
                'high_press'=>array('pressure_recovery_sequences'=>2),
            ),
        ),
    );
}

$profile = TactiTwin_Phase_Unit_Profile::aggregate(
    array( tt_phase_sample('1'),tt_phase_sample('2'),tt_phase_sample('3'),tt_phase_sample('4') ),
    array('pair_key'=>'1:2','player_a_id'=>'1','player_a_name'=>'A','player_b_id'=>'2','player_b_name'=>'B')
);

tt_phase_profile_assert( 360.0 === $profile['coverage']['shared_minutes'], 'phase profile aggregates shared minutes' );
tt_phase_profile_assert( 'high' === $profile['coverage']['confidence'], 'phase profile confidence high at 360 min / 4 matches' );
tt_phase_profile_assert( 12.0 === $profile['phases']['build_up']['per_90_shared']['completed_passes'], 'build-up passes normalized per shared 90' );
tt_phase_profile_assert( 5.0 === $profile['phases']['progression']['per_90_shared']['progressive_passes'], 'progression actions normalized' );
tt_phase_profile_assert( 0.45 === $profile['phases']['final_third']['per_90_shared']['joint_possession_xg'], 'final-third shared xG normalized' );
tt_phase_profile_assert( 2.0 === $profile['phases']['high_press']['per_90_shared']['pressure_recovery_sequences'], 'high-press sequence normalized' );
tt_phase_profile_assert( $profile['phases']['build_up']['signal'] > 0, 'build-up phase signal created' );
tt_phase_profile_assert( $profile['phases']['progression']['signal'] > 0, 'progression phase signal created' );
tt_phase_profile_assert( $profile['phases']['final_third']['signal'] > 0, 'final-third phase signal created' );
tt_phase_profile_assert( $profile['phases']['high_press']['signal'] > 0, 'high-press phase signal created' );

$raw = array(
    'baseline'=>array(
        'coverage'=>array('under_observed_pairs'=>1),
        'phase_signals'=>array('build_up'=>8.0,'progression'=>3.0,'final_third'=>0.20,'high_press'=>0.40),
        'phase_leaders'=>array(),
        'under_observed_pairs'=>array(),
    ),
    'candidate'=>array(
        'coverage'=>array('under_observed_pairs'=>3),
        'phase_signals'=>array('build_up'=>10.0,'progression'=>4.0,'final_third'=>0.31,'high_press'=>0.85),
        'phase_leaders'=>array(),
        'under_observed_pairs'=>array(array('pair_key'=>'4:5')),
    ),
    'changes'=>array(
        'outgoing_player_ids'=>array('7'),
        'incoming_player_ids'=>array('12'),
        'gained_pairs'=>array(array('pair_key'=>'6:12','shared_minutes'=>50.0)),
        'lost_pairs'=>array(),
    ),
    'delta'=>array('build_up'=>2.0,'progression'=>1.0,'final_third'=>0.11,'high_press'=>0.45),
);

$en = TactiTwin_Phase_Unit_Comparison::interpret( $raw, 'en' );
$es = TactiTwin_Phase_Unit_Comparison::interpret( $raw, 'es' );

tt_phase_profile_assert( $en['delta'] === $es['delta'], 'language does not alter phase deltas' );
tt_phase_profile_assert( $en['changes'] === $es['changes'], 'language does not alter phase evidence' );
tt_phase_profile_assert( count( $en['recommendations'] ) >= 6, 'phase changes and uncertainty create explainable recommendations' );
tt_phase_profile_assert( $en['recommendations'][0] !== $es['recommendations'][0], 'phase recommendations translated' );
tt_phase_profile_assert( 'phase-conditioned-unit-network-interpretation' === $en['method']['type'], 'phase method boundary explicit' );

echo "Phase unit profile and comparison tests passed.\n";
