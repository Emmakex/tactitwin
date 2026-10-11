<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-evidence-quality-calibrator.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-precedent-quality-attachment.php';

function tt_v110_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_v110_case( string $date, string $opponent, string $interpretability = 'cleaner' ): array {
    return array(
        'session_uuid' => md5( $date . $opponent . $interpretability ),
        'source_match_id' => $date,
        'opponent_source_id' => $opponent,
        'session_closed_at' => $date . ' 20:00:00',
        'interpretability' => $interpretability,
        'context_comparable' => true,
    );
}

$group = array(
    'key' => 'press-b-middle-draw',
    'sample' => array(
        'cleaner' => 4,
        'context_comparable' => 4,
        'signal_basis' => 'cleaner',
    ),
    'metrics' => array(
        array('metric'=>'our_progressive_actions_per90','observations'=>4,'up_pct'=>75.0,'down_pct'=>25.0),
        array('metric'=>'our_own_third_turnovers_per90','observations'=>4,'up_pct'=>0.0,'down_pct'=>100.0),
    ),
    'cases' => array(
        tt_v110_case('2026-09-15','900'),
        tt_v110_case('2026-09-22','900'),
        tt_v110_case('2026-09-29','901'),
        tt_v110_case('2026-10-05','900'),
    ),
);

$recent_en = TactiTwin_Evidence_Quality_Calibrator::calibrate($group,'900','en','2026-10-11 12:00:00');
$recent_es = TactiTwin_Evidence_Quality_Calibrator::calibrate($group,'900','es','2026-10-11 12:00:00');
$old = TactiTwin_Evidence_Quality_Calibrator::calibrate($group,'900','en','2027-11-20 12:00:00');
$other_opponent = TactiTwin_Evidence_Quality_Calibrator::calibrate($group,'999','en','2026-10-11 12:00:00');

tt_v110_assert($recent_en['quality_score'] > $old['quality_score'], 'recency decay lowers evidence quality while the historical group is unchanged');
tt_v110_assert(100.0 === $recent_en['components']['recency']['score'], 'recent median session age receives full recency credit');
tt_v110_assert(75.0 === $recent_en['components']['exact_opponent_continuity']['score'], 'exact-opponent continuity uses the share of signal cases against the current opponent');
tt_v110_assert($recent_en['quality_score'] > $other_opponent['quality_score'], 'exact-opponent continuity can increase evidence quality without changing tactical evidence');
tt_v110_assert($recent_en['quality_score'] === $recent_es['quality_score'], 'ES/EN does not alter evidence-quality math');
tt_v110_assert(false === $recent_en['method']['opponent_strength_model'], 'v1.10 explicitly does not claim an opponent-strength model');
tt_v110_assert(false === $recent_en['method']['changes_tactical_scores'], 'quality calibration cannot alter tactical scores');
tt_v110_assert(false === $recent_en['method']['changes_recommendations'], 'quality calibration cannot alter recommendations');

$missing_dates = $group;
foreach ($missing_dates['cases'] as &$case) { $case['session_closed_at'] = ''; }
unset($case);
$missing = TactiTwin_Evidence_Quality_Calibrator::calibrate($missing_dates,'900','en','2026-10-11 12:00:00');
tt_v110_assert(0.0 === $missing['components']['recency']['score'], 'missing persisted dates receive no recency credit');
tt_v110_assert('unavailable' === $missing['components']['recency']['status'], 'missing recency is visible rather than silently imputed');

$library = array('groups'=>array($group));
$retrieval = array(
    'precedent_id'=>'historical-precedent-retrieval',
    'items'=>array(array('group_key'=>'press-b-middle-draw','retrieval_score'=>88.0,'scenario_id'=>'build-up-vs-high-press')),
    'method'=>array('changes_recommendations'=>false),
);
$attached = TactiTwin_Precedent_Quality_Attachment::retrieval($retrieval,$library,'900','en','2026-10-11 12:00:00');
tt_v110_assert(isset($attached['items'][0]['evidence_quality']), 'retrieval items receive calibrated quality');
tt_v110_assert(88.0 === $attached['items'][0]['retrieval_score'], 'calibration does not rewrite v1.8 retrieval ranking score');
tt_v110_assert(false === $attached['method']['evidence_quality_calibration']['changes_recommendations'], 'retrieval attachment keeps recommendation guardrail');

$catalog = array(
    'catalog_id'=>'pre-match-precedent-catalog',
    'scenarios'=>array(array('scenario_id'=>'build-up-vs-high-press','contexts'=>array(array('group_key'=>'press-b-middle-draw','support_order_score'=>77.0)))),
    'method'=>array('changes_recommendations'=>false),
);
$catalog_attached = TactiTwin_Precedent_Quality_Attachment::catalog($catalog,$library,'900','en','2026-10-11 12:00:00');
tt_v110_assert(isset($catalog_attached['scenarios'][0]['contexts'][0]['evidence_quality']), 'pre-match catalog contexts receive calibrated quality');
tt_v110_assert(77.0 === $catalog_attached['scenarios'][0]['contexts'][0]['support_order_score'], 'quality calibration does not rewrite v1.9 support ordering');

echo "Evidence quality calibration tests passed.\n";
