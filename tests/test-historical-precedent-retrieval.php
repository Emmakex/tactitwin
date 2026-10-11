<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-historical-precedent-retrieval.php';

function tt_precedent_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_precedent_group(
    string $key,
    string $scenario,
    string $plan,
    string $mode,
    string $band,
    string $score_state,
    string $manpower,
    string $maturity,
    int $cleaner,
    int $comparable,
    float $median
): array {
    return array(
        'key'=>$key,
        'dimensions'=>array(
            'scenario_id'=>$scenario,
            'to_plan'=>$plan,
            'to_mode'=>$mode,
            'minute_band'=>$band,
            'score_state'=>$score_state,
            'manpower_state'=>$manpower,
        ),
        'sample'=>array(
            'total'=>$comparable,
            'matches'=>$comparable,
            'cleaner'=>$cleaner,
            'context_comparable'=>$comparable,
            'excluded'=>0,
            'signal_basis'=>$cleaner >= 2 ? 'cleaner' : ($comparable >= 2 ? 'context-comparable' : 'insufficient'),
            'maturity'=>$maturity,
            'maturity_label'=>$maturity,
        ),
        'metrics'=>array(
            array('metric'=>'our_progressive_actions_per90','observations'=>max($cleaner,1),'median_delta'=>$median,'direction'=>$median > 0 ? 'mostly-up' : 'mostly-down'),
        ),
        'cases'=>array_fill(0,$comparable,array('session_uuid'=>'s')),
    );
}

$library = array(
    'library_id'=>'context-matched-intervention-library',
    'groups'=>array(
        tt_precedent_group('highpress-B','build-up-vs-high-press','B','scenario','31-60','drawing','equal','developing',4,4,14.0),
        tt_precedent_group('lowblock-B','attack-vs-low-block','B','scenario','31-60','drawing','equal','established',6,6,8.0),
        tt_precedent_group('wrong-score','build-up-vs-high-press','B','scenario','31-60','leading','equal','established',7,7,30.0),
        tt_precedent_group('wrong-manpower','build-up-vs-high-press','C','scenario','31-60','drawing','inferior','established',7,7,-10.0),
        tt_precedent_group('wrong-band','build-up-vs-high-press','B','scenario','61-90','drawing','equal','established',7,7,22.0),
        tt_precedent_group('unknown-scenario','custom-scenario','B','scenario','31-60','drawing','equal','established',8,8,50.0),
    ),
);

$scan = array(
    'evidence'=>array(
        'scenario_ranking'=>array(
            array('rank'=>1,'scenario_id'=>'build-up-vs-high-press','scenario_name'=>'Build-up vs High Press','risk_score'=>82.0,'opportunity_score'=>61.0),
            array('rank'=>2,'scenario_id'=>'attack-vs-low-block','scenario_name'=>'Attack vs Low Block','risk_score'=>48.0,'opportunity_score'=>66.0),
        ),
    ),
);

$hypothesis = array(
    'minute'=>55,
    'our_score'=>1,
    'opponent_score'=>1,
    'state'=>'drawing',
    'our_players'=>11,
    'opponent_players'=>11,
    'manpower_state'=>'equal',
);

$en = TactiTwin_Historical_Precedent_Retrieval::retrieve($library,$scan,$hypothesis,'en',5);
$es = TactiTwin_Historical_Precedent_Retrieval::retrieve($library,$scan,$hypothesis,'es',5);

tt_precedent_assert('historical-precedent-retrieval' === $en['precedent_id'], 'precedent identity retained');
tt_precedent_assert('available' === $en['status'], 'exact historical precedents available');
tt_precedent_assert('31-60' === $en['context']['minute_band'], 'minute band derived from current hypothesis');
tt_precedent_assert('drawing' === $en['context']['score_state'], 'score state matched exactly');
tt_precedent_assert('equal' === $en['context']['manpower_state'], 'manpower state matched exactly');
tt_precedent_assert(2 === $en['matched_group_count'], 'only exact-context groups from current scan are returned');
tt_precedent_assert('build-up-vs-high-press' === $en['items'][0]['scenario_id'], 'higher current scenario relevance outranks larger but less relevant sample');
tt_precedent_assert('B' === $en['items'][0]['to_plan'], 'historical destination plan retained');
tt_precedent_assert(14.0 === $en['items'][0]['metrics'][0]['median_delta'], 'historical aggregate metrics retained without reinterpretation');
tt_precedent_assert(false === $en['method']['reweights_tactical_scores'], 'precedents cannot reweight tactical scores');
tt_precedent_assert(false === $en['method']['changes_recommendations'], 'precedents cannot change recommendations');
tt_precedent_assert($en['items'][0]['group_key'] === $es['items'][0]['group_key'], 'language does not alter retrieval ranking');
tt_precedent_assert($en['items'][0]['retrieval_score'] === $es['items'][0]['retrieval_score'], 'language does not alter retrieval score');
tt_precedent_assert($en['summary'][0] !== $es['summary'][0], 'summary is translated');

$late = $hypothesis;
$late['minute'] = 78;
$late_result = TactiTwin_Historical_Precedent_Retrieval::retrieve($library,$scan,$late,'en',5);
tt_precedent_assert('available' === $late_result['status'], 'late exact precedent exists');
tt_precedent_assert(1 === $late_result['matched_group_count'], 'late band does not mix with 31-60 groups');
tt_precedent_assert('wrong-band' === $late_result['items'][0]['group_key'], '61-90 precedent selected only in late context');

$superior = $hypothesis;
$superior['our_players'] = 11;
$superior['opponent_players'] = 10;
$superior['manpower_state'] = 'superior';
$none = TactiTwin_Historical_Precedent_Retrieval::retrieve($library,$scan,$superior,'en',5);
tt_precedent_assert('no-match' === $none['status'], 'no fuzzy fallback is used for unmatched manpower context');
tt_precedent_assert(0 === $none['matched_group_count'], 'no-match result remains empty');

echo "Historical precedent retrieval tests passed.\n";
