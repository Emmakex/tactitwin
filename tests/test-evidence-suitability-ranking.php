<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-evidence-suitability-ranker.php';

function tt_v112_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_v112_item( string $key, float $relevance, float $quality, ?float $similarity, float $retrieval_score ): array {
    $item = array(
        'group_key' => $key,
        'scenario_relevance' => $relevance,
        'retrieval_score' => $retrieval_score,
        'evidence_quality' => array('quality_score' => $quality),
    );
    if ( null !== $similarity ) {
        $item['opponent_context_similarity'] = array('similarity_score' => $similarity);
    }
    return $item;
}

$retrieval = array(
    'precedent_id' => 'historical-precedent-retrieval',
    'matched_group_count' => 3,
    'returned_group_count' => 3,
    'items' => array(
        tt_v112_item('legacy-top', 90.0, 50.0, 50.0, 95.0),
        tt_v112_item('best-suitability', 75.0, 95.0, 95.0, 70.0),
        tt_v112_item('missing-similarity', 80.0, 80.0, null, 80.0),
    ),
    'method' => array('changes_recommendations' => false),
);

$ranked_en = TactiTwin_Evidence_Suitability_Ranker::retrieval($retrieval, 'en', 2);
$ranked_es = TactiTwin_Evidence_Suitability_Ranker::retrieval($retrieval, 'es', 2);

tt_v112_assert('best-suitability' === $ranked_en['items'][0]['group_key'], 'suitability can reorder exact-context evidence relative to the legacy retrieval score');
tt_v112_assert(2 === $ranked_en['items'][0]['original_retrieval_rank'], 'original retrieval rank is preserved for audit');
tt_v112_assert(1 === $ranked_en['items'][0]['suitability_rank'], 'new suitability rank is explicit');
tt_v112_assert(true === $ranked_en['items'][0]['ranking_changed'], 'ranking movement is visible');
tt_v112_assert(70.0 === $ranked_en['items'][0]['retrieval_score'], 'v1.12 does not rewrite the v1.8 retrieval score');
tt_v112_assert(95.0 === $ranked_en['items'][0]['evidence_quality']['quality_score'], 'v1.12 leaves v1.10 evidence quality unchanged');
tt_v112_assert(95.0 === $ranked_en['items'][0]['opponent_context_similarity']['similarity_score'], 'v1.12 leaves v1.11 opponent similarity unchanged');
tt_v112_assert(2 === $ranked_en['returned_group_count'], 'requested result limit is applied after suitability ranking');
tt_v112_assert(3 === $ranked_en['suitability_summary']['candidate_count'], 'candidate pool size remains visible');
tt_v112_assert($ranked_en['items'][0]['evidence_suitability']['suitability_score'] === $ranked_es['items'][0]['evidence_suitability']['suitability_score'], 'ES/EN does not alter suitability math');
tt_v112_assert(false === $ranked_en['method']['evidence_suitability_ranking']['changes_tactical_scores'], 'suitability ranking cannot change tactical scores');
tt_v112_assert(false === $ranked_en['method']['evidence_suitability_ranking']['changes_recommendations'], 'suitability ranking cannot change recommendations');
tt_v112_assert(true === $ranked_en['method']['evidence_suitability_ranking']['exact_context_only'], 'v1.12 remains inside exact-context evidence');
tt_v112_assert(false === $ranked_en['method']['evidence_suitability_ranking']['fuzzy_context_fallback'], 'v1.12 does not add fuzzy context fallback');

$missing_similarity = TactiTwin_Evidence_Suitability_Ranker::score(80.0, 80.0, null, 'en');
tt_v112_assert(80.0 === $missing_similarity['suitability_score'], 'missing opponent similarity renormalizes available weights instead of receiving zero');
tt_v112_assert(0.8 === $missing_similarity['available_weight'], 'available weight reports the missing similarity component');
tt_v112_assert(in_array('opponent_similarity', $missing_similarity['missing_components'], true), 'missing similarity is explicitly exposed');

$catalog = array(
    'catalog_id' => 'pre-match-precedent-catalog',
    'coverage' => array('contexts_per_scenario_limit' => 10),
    'scenarios' => array(
        array(
            'scenario_id' => 'scenario-a',
            'current_scan' => array('rank' => 1, 'relevance' => 70.0),
            'context_group_count' => 2,
            'returned_context_count' => 2,
            'contexts' => array(
                array('group_key'=>'a-legacy','support_order_score'=>95.0,'evidence_quality'=>array('quality_score'=>40.0),'opponent_context_similarity'=>array('similarity_score'=>40.0)),
                array('group_key'=>'a-best','support_order_score'=>70.0,'evidence_quality'=>array('quality_score'=>95.0),'opponent_context_similarity'=>array('similarity_score'=>90.0)),
            ),
        ),
        array(
            'scenario_id' => 'scenario-b',
            'current_scan' => array('rank' => 2, 'relevance' => 95.0),
            'context_group_count' => 1,
            'returned_context_count' => 1,
            'contexts' => array(
                array('group_key'=>'b-only','support_order_score'=>100.0,'evidence_quality'=>array('quality_score'=>100.0),'opponent_context_similarity'=>array('similarity_score'=>100.0)),
            ),
        ),
    ),
    'method' => array('changes_recommendations' => false),
);

$catalog_ranked = TactiTwin_Evidence_Suitability_Ranker::catalog($catalog, 'en', 1);
tt_v112_assert('scenario-a' === $catalog_ranked['scenarios'][0]['scenario_id'], 'catalog ranking does not reorder current Match Plan scenarios');
tt_v112_assert('scenario-b' === $catalog_ranked['scenarios'][1]['scenario_id'], 'second scenario position is preserved');
tt_v112_assert('a-best' === $catalog_ranked['scenarios'][0]['contexts'][0]['group_key'], 'historical contexts are reranked within their scenario');
tt_v112_assert(1 === $catalog_ranked['scenarios'][0]['returned_context_count'], 'context limit is applied after within-scenario ranking');
tt_v112_assert('within-scenario-context-shortlist' === $catalog_ranked['method']['evidence_suitability_ranking']['scope'], 'catalog ranking scope is explicit');

echo "Evidence suitability ranking tests passed.\n";
