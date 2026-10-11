<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-opponent-context-distance.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-precedent-similarity-attachment.php';

function tt_v111_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_v111_profile( string $id, array $override = array() ): array {
    $profile = array(
        'team_source_id'=>$id,
        'team_name'=>'Team ' . $id,
        'coverage'=>array('matches'=>5),
        'averages'=>array(
            'high_pressures_per_match'=>12.0,
            'high_recoveries_per_match'=>2.5,
            'defensive_actions_per_match'=>22.0,
            'transition_progressions_created_within_10s_per_match'=>4.0,
            'transition_shots_created_within_10s_per_match'=>1.2,
            'transition_xg_created_within_10s_per_match'=>0.35,
            'progressive_actions_per_match'=>24.0,
            'final_third_entries_per_match'=>16.0,
            'box_entries_per_match'=>7.0,
            'shots_per_match'=>11.0,
        ),
        'rates'=>array(
            'high_pressure_share_pct'=>38.0,
            'deep_defensive_action_share_pct'=>42.0,
        ),
        'final_third_channel_share'=>array('left'=>34.0,'central'=>36.0,'right'=>30.0),
    );
    foreach ( $override as $section => $values ) {
        if ( is_array( $values ) && isset( $profile[ $section ] ) && is_array( $profile[ $section ] ) ) {
            $profile[ $section ] = array_merge( $profile[ $section ], $values );
        } else {
            $profile[ $section ] = $values;
        }
    }
    return $profile;
}

$current = tt_v111_profile('100');
$identical_other = tt_v111_profile('200');
$identical = TactiTwin_Opponent_Context_Distance::compare($current,$identical_other,'en');
tt_v111_assert(100.0 === $identical['similarity_score'], 'identical tactical profiles produce 100 similarity even for different clubs');
tt_v111_assert(false === $identical['exact_opponent'], 'different club IDs remain distinguishable from tactical similarity');

$changed_same_club = tt_v111_profile('100', array(
    'averages'=>array(
        'high_pressures_per_match'=>2.0,
        'high_recoveries_per_match'=>0.2,
        'transition_progressions_created_within_10s_per_match'=>0.5,
        'transition_shots_created_within_10s_per_match'=>0.1,
        'transition_xg_created_within_10s_per_match'=>0.02,
        'progressive_actions_per_match'=>10.0,
        'final_third_entries_per_match'=>6.0,
        'box_entries_per_match'=>2.0,
        'shots_per_match'=>5.0,
    ),
    'rates'=>array('high_pressure_share_pct'=>8.0,'deep_defensive_action_share_pct'=>78.0),
    'final_third_channel_share'=>array('left'=>70.0,'central'=>20.0,'right'=>10.0),
));
$same_club = TactiTwin_Opponent_Context_Distance::compare($current,$changed_same_club,'en');
tt_v111_assert(true === $same_club['exact_opponent'], 'same source ID is exposed as exact opponent');
tt_v111_assert($same_club['similarity_score'] < 100.0, 'same club does not force style similarity to 100');
tt_v111_assert(true === $same_club['method']['exact_opponent_does_not_force_similarity'], 'same-club style-drift guardrail is explicit');

$far = tt_v111_profile('300', array(
    'averages'=>array(
        'high_pressures_per_match'=>0.0,
        'high_recoveries_per_match'=>0.0,
        'defensive_actions_per_match'=>5.0,
        'transition_progressions_created_within_10s_per_match'=>0.0,
        'transition_shots_created_within_10s_per_match'=>0.0,
        'transition_xg_created_within_10s_per_match'=>0.0,
        'progressive_actions_per_match'=>2.0,
        'final_third_entries_per_match'=>2.0,
        'box_entries_per_match'=>0.0,
        'shots_per_match'=>2.0,
    ),
    'rates'=>array('high_pressure_share_pct'=>0.0,'deep_defensive_action_share_pct'=>92.0),
    'final_third_channel_share'=>array('left'=>0.0,'central'=>0.0,'right'=>100.0),
));
$far_en = TactiTwin_Opponent_Context_Distance::compare($current,$far,'en');
$far_es = TactiTwin_Opponent_Context_Distance::compare($current,$far,'es');
tt_v111_assert($far_en['similarity_score'] < $identical['similarity_score'], 'large tactical differences reduce similarity');
tt_v111_assert($far_en['similarity_score'] === $far_es['similarity_score'], 'ES/EN does not alter similarity math');
tt_v111_assert($far_en['components']['attack_channels']['similarity_score'] < 70.0, 'large channel-distribution shift is visible in its own component');
tt_v111_assert(false === $far_en['method']['strength_model'], 'v1.11 explicitly does not claim a strength model');
tt_v111_assert(false === $far_en['method']['embedding_model'], 'v1.11 uses transparent features rather than opaque embeddings');
tt_v111_assert(false === $far_en['method']['changes_tactical_scores'], 'similarity cannot change tactical scores');

$library = array('groups'=>array(array(
    'key'=>'g1',
    'opponent_context_similarity'=>array('status'=>'available','similarity_score'=>73.4,'similarity_label'=>'High'),
)));
$retrieval = array(
    'items'=>array(array('group_key'=>'g1','retrieval_score'=>81.0,'evidence_quality'=>array('quality_score'=>76.0))),
    'method'=>array(),
);
$attached = TactiTwin_Precedent_Similarity_Attachment::retrieval($retrieval,$library,'en');
tt_v111_assert(73.4 === $attached['items'][0]['opponent_context_similarity']['similarity_score'], 'retrieval receives opponent similarity by group key');
tt_v111_assert(81.0 === $attached['items'][0]['retrieval_score'], 'v1.11 does not rewrite v1.8 retrieval score');
tt_v111_assert(76.0 === $attached['items'][0]['evidence_quality']['quality_score'], 'v1.11 leaves v1.10 evidence quality unchanged');
tt_v111_assert(true === $attached['method']['opponent_context_similarity']['separate_from_evidence_quality'], 'similarity and quality remain separate axes');

echo "Opponent context distance tests passed.\n";
