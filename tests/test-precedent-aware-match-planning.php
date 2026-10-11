<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-pre-match-precedent-catalog.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-playbook-precedent-attachment.php';

function tt_v19_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_v19_group( string $key, string $scenario, string $plan, string $mode, string $band, string $score, string $manpower, string $maturity, int $cleaner, int $comparable, float $median ): array {
    return array(
        'key'=>$key,
        'dimensions'=>array(
            'scenario_id'=>$scenario,
            'to_plan'=>$plan,
            'to_mode'=>$mode,
            'minute_band'=>$band,
            'score_state'=>$score,
            'manpower_state'=>$manpower,
        ),
        'sample'=>array(
            'total'=>max($cleaner,$comparable),
            'matches'=>max($cleaner,$comparable),
            'cleaner'=>$cleaner,
            'context_comparable'=>$comparable,
            'excluded'=>0,
            'signal_basis'=>$cleaner >= 2 ? 'cleaner' : 'context-comparable',
            'maturity'=>$maturity,
            'maturity_label'=>$maturity,
        ),
        'metrics'=>array(
            array(
                'metric'=>'our_progressive_actions_per90',
                'observations'=>max(1,$cleaner),
                'median_delta'=>$median,
                'mean_delta'=>$median,
                'direction'=>'mostly-up',
            ),
        ),
        'cases'=>array_fill(0,max(1,$comparable),array('source_match_id'=>'x')),
    );
}

$scan = array(
    'scenario_id'=>'full-matchup-scan',
    'evidence'=>array(
        'scenario_ranking'=>array(
            array('rank'=>1,'scenario_id'=>'build-up-vs-high-press','scenario_name'=>'Build-up vs High Press','risk_score'=>82.0,'opportunity_score'=>61.0),
            array('rank'=>2,'scenario_id'=>'attack-vs-low-block','scenario_name'=>'Attack vs Low Block','risk_score'=>58.0,'opportunity_score'=>74.0),
        ),
    ),
);

$library = array(
    'library_id'=>'context-matched-intervention-library',
    'groups'=>array(
        tt_v19_group('press-b-early-draw','build-up-vs-high-press','B','escape','01-30','drawing','equal','developing',4,4,12.0),
        tt_v19_group('press-b-late-leading','build-up-vs-high-press','B','escape','61-90','leading','equal','emerging',2,2,7.0),
        tt_v19_group('press-c-late-inferior','build-up-vs-high-press','C','protect','61-90','trailing','inferior','established',7,7,-3.0),
        tt_v19_group('low-a-middle-draw','attack-vs-low-block','A','baseline','31-60','drawing','equal','emerging',2,3,5.0),
        tt_v19_group('unrelated-b','opponent-side-bias','B','shift','31-60','drawing','equal','established',8,8,99.0),
    ),
);

$catalog_en = TactiTwin_Pre_Match_Precedent_Catalog::build($library,$scan,'en',2);
$catalog_es = TactiTwin_Pre_Match_Precedent_Catalog::build($library,$scan,'es',2);

tt_v19_assert('pre-match-precedent-catalog' === $catalog_en['catalog_id'], 'catalog identity retained');
tt_v19_assert(2 === $catalog_en['coverage']['current_scenario_count'], 'catalog only indexes current scan scenarios');
tt_v19_assert(4 === $catalog_en['coverage']['context_group_count'], 'unrelated scenario group excluded without merging valid contexts');
tt_v19_assert(2 === count($catalog_en['scenarios']), 'two scan scenarios returned');
tt_v19_assert('build-up-vs-high-press' === $catalog_en['scenarios'][0]['scenario_id'], 'scan rank ordering retained');
tt_v19_assert(3 === $catalog_en['scenarios'][0]['context_group_count'], 'three press contexts remain distinct');
tt_v19_assert(2 === $catalog_en['scenarios'][0]['returned_context_count'], 'context limit applied after counting');
tt_v19_assert($catalog_en['scenarios'][0]['contexts'][0]['minute_band'] !== $catalog_en['scenarios'][0]['contexts'][1]['minute_band'] || $catalog_en['scenarios'][0]['contexts'][0]['score_state'] !== $catalog_en['scenarios'][0]['contexts'][1]['score_state'] || $catalog_en['scenarios'][0]['contexts'][0]['manpower_state'] !== $catalog_en['scenarios'][0]['contexts'][1]['manpower_state'], 'distinct historical contexts stay distinct');
tt_v19_assert(false === $catalog_en['method']['cross_context_metric_aggregation'], 'cross-context metric aggregation is explicitly disabled');
tt_v19_assert(false === $catalog_en['method']['reweights_tactical_scores'], 'pre-match catalog cannot reweight scores');
tt_v19_assert($catalog_en['scenarios'][0]['contexts'][0]['group_key'] === $catalog_es['scenarios'][0]['contexts'][0]['group_key'], 'language does not alter catalog ordering');

$precedents = array(
    'precedent_id'=>'historical-precedent-retrieval',
    'status'=>'available',
    'items'=>array(
        array('group_key'=>'a1','scenario_id'=>'attack-vs-low-block','scenario_name'=>'Attack vs Low Block','to_plan'=>'A','to_mode'=>'baseline','retrieval_score'=>85.0),
        array('group_key'=>'b-press','scenario_id'=>'build-up-vs-high-press','scenario_name'=>'Build-up vs High Press','to_plan'=>'B','to_mode'=>'escape','retrieval_score'=>90.0),
        array('group_key'=>'b-low','scenario_id'=>'attack-vs-low-block','scenario_name'=>'Attack vs Low Block','to_plan'=>'B','to_mode'=>'circulate','retrieval_score'=>88.0),
        array('group_key'=>'c1','scenario_id'=>'build-up-vs-high-press','scenario_name'=>'Build-up vs High Press','to_plan'=>'C','to_mode'=>'protect','retrieval_score'=>80.0),
    ),
    'method'=>array('reweights_tactical_scores'=>false,'changes_recommendations'=>false),
);

$base_playbook = array(
    'playbook_id'=>'contingency-playbook',
    'language'=>'en',
    'active_plan'=>array('id'=>'A','mode'=>'baseline','reason'=>'baseline reason'),
    'plans'=>array(
        'plan_a'=>array('id'=>'A','mode'=>'baseline','instructions'=>array('keep baseline')),
        'plan_b'=>array('id'=>'B','mode'=>'escape','scenario_id'=>'build-up-vs-high-press','instructions'=>array('escape press')),
        'plan_c'=>array('id'=>'C','mode'=>'protect','instructions'=>array('protect state')),
    ),
    'recommendations'=>array('original recommendation'),
    'method'=>array('type'=>'deterministic-contingency-playbook'),
);

$attached = TactiTwin_Playbook_Precedent_Attachment::attach($base_playbook,$precedents,'en',5);
tt_v19_assert(1 === $attached['plans']['plan_a']['historical_precedents']['matched_count'], 'Plan A receives only historical transitions into A');
tt_v19_assert(1 === $attached['plans']['plan_b']['historical_precedents']['matched_count'], 'armed Plan B also requires current B scenario');
tt_v19_assert('b-press' === $attached['plans']['plan_b']['historical_precedents']['items'][0]['group_key'], 'wrong-scenario Plan B precedent excluded');
tt_v19_assert(1 === $attached['plans']['plan_c']['historical_precedents']['matched_count'], 'Plan C receives only historical transitions into C');
tt_v19_assert($base_playbook['recommendations'] === $attached['recommendations'], 'precedent attachment does not change recommendations');
tt_v19_assert($base_playbook['active_plan'] === $attached['active_plan'], 'precedent attachment does not change active plan');
tt_v19_assert(false === $attached['method']['historical_precedents']['changes_recommendations'], 'playbook guardrail forbids recommendation changes');

$live_playbook = $base_playbook;
$live_playbook['active_plan'] = array(
    'id'=>'B','mode'=>'circulate','scenario_id'=>'attack-vs-low-block','scenario_name'=>'Attack vs Low Block','reason'=>'live observation',
);
$live_attached = TactiTwin_Playbook_Precedent_Attachment::attach($live_playbook,$precedents,'en',5);
tt_v19_assert(1 === $live_attached['plans']['plan_b']['historical_precedents']['matched_count'], 'live Plan B keeps one exact scenario precedent');
tt_v19_assert('b-low' === $live_attached['plans']['plan_b']['historical_precedents']['items'][0]['group_key'], 'live activated B scenario overrides pre-match B scenario for precedent filtering');
tt_v19_assert($live_playbook['active_plan'] === $live_attached['active_plan'], 'live active plan remains immutable during evidence attachment');

echo "Precedent-aware match planning tests passed.\n";
