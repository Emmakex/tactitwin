<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-unit-profile.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-unit-comparison.php';

function tt_unit_profile_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$samples = array();
for ( $i = 1; $i <= 4; ++$i ) {
    $samples[] = array(
        'match_id'=>(string) $i,
        'metrics'=>array(
            'shared_seconds'=>5400.0,
            'completed_passes'=>20,
            'passes_a_to_b'=>12,
            'passes_b_to_a'=>8,
            'progressive_passes'=>4,
            'final_third_entries'=>2,
            'box_entries'=>1,
            'shot_assists'=>1,
            'goal_assists'=>0,
            'joint_possessions'=>15,
            'joint_shot_possessions'=>3,
            'joint_possession_xg'=>0.50,
            'pressure_recovery_sequences'=>2,
        ),
    );
}

$profile = TactiTwin_Unit_Profile::aggregate( $samples, array(
    'pair_key'=>'1:2','player_a_id'=>'1','player_a_name'=>'A','player_b_id'=>'2','player_b_name'=>'B',
) );

tt_unit_profile_assert( 360.0 === $profile['coverage']['shared_minutes'], 'four full matches aggregate to 360 shared minutes' );
tt_unit_profile_assert( 4 === $profile['coverage']['matches_observed'], 'four observed matches retained' );
tt_unit_profile_assert( 'high' === $profile['coverage']['confidence'], '360 minutes across four matches is high confidence' );
tt_unit_profile_assert( 20.0 === $profile['per_90_shared']['completed_passes'], 'direct passes normalize correctly per 90 shared minutes' );
tt_unit_profile_assert( 66.7 === $profile['direction_balance_pct'], 'aggregate pass direction balance retained' );
tt_unit_profile_assert( $profile['signals']['progressive_link'] > $profile['per_90_shared']['progressive_passes'], 'progressive signal includes territorial connections' );

$baseline = array(
    'coverage'=>array('under_observed_pairs'=>1,'observed_pairs'=>40),
    'signals'=>array('connectivity'=>8.0,'progressive_link'=>3.0,'chance_chain'=>0.20,'defensive_sequence'=>0.40),
    'leaders'=>array(),
    'under_observed_pairs'=>array(),
);
$candidate = array(
    'coverage'=>array('under_observed_pairs'=>3,'observed_pairs'=>38),
    'signals'=>array('connectivity'=>10.0,'progressive_link'=>4.0,'chance_chain'=>0.32,'defensive_sequence'=>0.90),
    'leaders'=>array(),
    'under_observed_pairs'=>array(array('pair_key'=>'3:4')),
);
$raw = array(
    'baseline'=>$baseline,
    'candidate'=>$candidate,
    'changes'=>array(
        'outgoing_player_ids'=>array('7'),
        'incoming_player_ids'=>array('12'),
        'lost_pair_evidence'=>array(),
        'gained_pair_evidence'=>array(),
        'low_evidence_new_pairs'=>array(array('pair_key'=>'12:6','shared_minutes'=>40.0)),
    ),
    'delta'=>array('connectivity'=>2.0,'progressive_link'=>1.0,'chance_chain'=>0.12,'defensive_sequence'=>0.50),
);

$en = TactiTwin_Unit_Comparison::interpret( $raw, 'en' );
$es = TactiTwin_Unit_Comparison::interpret( $raw, 'es' );

tt_unit_profile_assert( $en['delta'] === $es['delta'], 'language does not alter unit-network deltas' );
tt_unit_profile_assert( $en['changes'] === $es['changes'], 'language does not alter unit-network evidence' );
tt_unit_profile_assert( count( $en['recommendations'] ) >= 5, 'multiple material network signals produce explainable recommendations' );
tt_unit_profile_assert( $en['recommendations'][0] !== $es['recommendations'][0], 'unit recommendations are translated' );
tt_unit_profile_assert( 'historical-shared-pitch-unit-interaction-interpretation' === $en['method']['type'], 'method boundary is explicit' );

echo "Unit profile and comparison tests passed.\n";
