<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-evidence-ranking-robustness.php';

function tt_v113_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_v113_item( string $key, float $relevance, float $quality, ?float $similarity, float $suitability, float $legacy ): array {
    $item = array(
        'group_key' => $key,
        'scenario_relevance' => $relevance,
        'retrieval_score' => $legacy,
        'evidence_quality' => array('quality_score' => $quality),
        'evidence_suitability' => array('suitability_score' => $suitability),
    );
    if ( null !== $similarity ) {
        $item['opponent_context_similarity'] = array('similarity_score' => $similarity);
    }
    return $item;
}

$stable = array(
    'precedent_id' => 'historical-precedent-retrieval',
    'matched_group_count' => 2,
    'returned_group_count' => 2,
    'items' => array(
        tt_v113_item('stable-a', 92.0, 92.0, 92.0, 92.0, 80.0),
        tt_v113_item('stable-b', 60.0, 60.0, 60.0, 60.0, 90.0),
    ),
    'method' => array(),
);

$stable_out = TactiTwin_Evidence_Ranking_Robustness::retrieval($stable, 'en', 2);
tt_v113_assert('stable-a' === $stable_out['items'][0]['group_key'], 'robustness preserves the baseline v1.12 order');
tt_v113_assert(100.0 === $stable_out['ranking_robustness']['top_stability_pct'], 'clearly dominant top evidence is stable across all weight profiles');
tt_v113_assert('very-stable' === $stable_out['ranking_robustness']['robustness_level'], '100 percent stable top receives very-stable label');
tt_v113_assert(1 === $stable_out['ranking_robustness']['unique_top_count'], 'stable ranking has one top candidate across profiles');
tt_v113_assert(0 === $stable_out['items'][0]['ranking_robustness']['rank_spread'], 'stable top keeps the same rank across profiles');
tt_v113_assert(false === $stable_out['method']['evidence_ranking_robustness']['changes_suitability_scores'], 'robustness cannot rewrite v1.12 suitability scores');
tt_v113_assert(false === $stable_out['method']['evidence_ranking_robustness']['changes_tactical_scores'], 'robustness cannot alter tactical scores');
tt_v113_assert(false === $stable_out['method']['evidence_ranking_robustness']['changes_recommendations'], 'robustness cannot alter recommendations');

$sensitive = array(
    'precedent_id' => 'historical-precedent-retrieval',
    'matched_group_count' => 3,
    'returned_group_count' => 3,
    'items' => array(
        tt_v113_item('baseline-b', 70.0, 95.0, 70.0, 78.8, 90.0),
        tt_v113_item('relevance-a', 100.0, 60.0, 40.0, 74.0, 80.0),
        tt_v113_item('similarity-c', 65.0, 65.0, 100.0, 72.0, 70.0),
    ),
    'method' => array(),
);

$sensitive_en = TactiTwin_Evidence_Ranking_Robustness::retrieval($sensitive, 'en', 2);
$sensitive_es = TactiTwin_Evidence_Ranking_Robustness::retrieval($sensitive, 'es', 2);
tt_v113_assert('baseline-b' === $sensitive_en['items'][0]['group_key'], 'sensitivity analysis does not replace the v1.12 baseline order');
tt_v113_assert(60.0 === $sensitive_en['ranking_robustness']['top_stability_pct'], 'baseline top wins three of five configured profiles');
tt_v113_assert('moderate' === $sensitive_en['ranking_robustness']['robustness_level'], '60 percent top stability is reported as moderate');
tt_v113_assert(3 === $sensitive_en['ranking_robustness']['unique_top_count'], 'alternative weight profiles expose multiple plausible top candidates');
tt_v113_assert('relevance-a' === $sensitive_en['ranking_robustness']['profiles']['relevance_heavy']['top_key'], 'relevance-heavy profile surfaces relevance-sensitive alternative');
tt_v113_assert('similarity-c' === $sensitive_en['ranking_robustness']['profiles']['similarity_heavy']['top_key'], 'similarity-heavy profile surfaces similarity-sensitive alternative');
tt_v113_assert($sensitive_en['ranking_robustness']['top_stability_pct'] === $sensitive_es['ranking_robustness']['top_stability_pct'], 'ES/EN does not alter robustness math');
tt_v113_assert(2 === $sensitive_en['returned_group_count'], 'display limit is applied only after analyzing the complete candidate shortlist');
tt_v113_assert(3 === $sensitive_en['ranking_robustness']['candidate_count'], 'robustness summary retains full candidate count before display limit');

$missing = array(
    'precedent_id' => 'historical-precedent-retrieval',
    'matched_group_count' => 2,
    'returned_group_count' => 2,
    'items' => array(
        tt_v113_item('missing-sim', 80.0, 80.0, null, 80.0, 80.0),
        tt_v113_item('complete', 70.0, 70.0, 70.0, 70.0, 70.0),
    ),
    'method' => array(),
);
$missing_out = TactiTwin_Evidence_Ranking_Robustness::retrieval($missing, 'en', 2);
tt_v113_assert(100.0 === $missing_out['ranking_robustness']['top_stability_pct'], 'missing similarity is renormalized consistently across robustness profiles');

$catalog = array(
    'catalog_id' => 'pre-match-precedent-catalog',
    'coverage' => array('contexts_per_scenario_limit' => 10),
    'scenarios' => array(
        array(
            'scenario_id' => 'scenario-a',
            'current_scan' => array('rank' => 1, 'relevance' => 70.0),
            'contexts' => array(
                array('group_key'=>'a1','scenario_relevance'=>70.0,'support_order_score'=>80.0,'evidence_quality'=>array('quality_score'=>85.0),'opponent_context_similarity'=>array('similarity_score'=>80.0),'evidence_suitability'=>array('suitability_score'=>77.3)),
                array('group_key'=>'a2','scenario_relevance'=>70.0,'support_order_score'=>90.0,'evidence_quality'=>array('quality_score'=>50.0),'opponent_context_similarity'=>array('similarity_score'=>50.0),'evidence_suitability'=>array('suitability_score'=>59.0)),
            ),
        ),
        array(
            'scenario_id' => 'scenario-b',
            'current_scan' => array('rank' => 2, 'relevance' => 95.0),
            'contexts' => array(
                array('group_key'=>'b1','scenario_relevance'=>95.0,'support_order_score'=>100.0,'evidence_quality'=>array('quality_score'=>100.0),'opponent_context_similarity'=>array('similarity_score'=>100.0),'evidence_suitability'=>array('suitability_score'=>97.8)),
            ),
        ),
    ),
    'method' => array(),
);

$catalog_out = TactiTwin_Evidence_Ranking_Robustness::catalog($catalog, 'en', 1);
tt_v113_assert('scenario-a' === $catalog_out['scenarios'][0]['scenario_id'], 'robustness never reorders Match Plan scenarios');
tt_v113_assert('scenario-b' === $catalog_out['scenarios'][1]['scenario_id'], 'second scenario remains second');
tt_v113_assert(1 === count($catalog_out['scenarios'][0]['contexts']), 'context display limit is applied after within-scenario robustness analysis');
tt_v113_assert(2 === $catalog_out['scenarios'][0]['ranking_robustness']['candidate_count'], 'scenario robustness sees the complete pre-limit context shortlist');
tt_v113_assert(true === $catalog_out['method']['evidence_ranking_robustness']['baseline_order_is_preserved'], 'catalog robustness explicitly preserves baseline order');

echo "Evidence ranking robustness tests passed.\n";
