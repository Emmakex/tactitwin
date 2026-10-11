<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-evidence-decision-brief.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-playbook-precedent-attachment.php';

function tt_v114_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_v114_item(
    string $key,
    string $scenario,
    string $plan,
    float $suitability,
    float $quality,
    float $similarity,
    float $top_frequency,
    int $best_rank,
    int $worst_rank
): array {
    return array(
        'group_key' => $key,
        'scenario_id' => $scenario,
        'scenario_name' => 'Scenario ' . $scenario,
        'to_plan' => $plan,
        'to_mode' => 'mode-' . strtolower( $plan ),
        'minute_band' => '61-90',
        'score_state' => 'trailing',
        'manpower_state' => 'equal',
        'scenario_relevance' => 82.0,
        'evidence_suitability' => array(
            'suitability_score' => $suitability,
            'suitability_level' => 'high',
            'suitability_label' => 'High',
            'missing_components' => array(),
            'components' => array(
                'scenario_relevance' => array( 'score' => 82.0 ),
            ),
        ),
        'evidence_quality' => array(
            'quality_score' => $quality,
            'quality_level' => 'high',
            'quality_label' => 'High',
        ),
        'opponent_context_similarity' => array(
            'similarity_score' => $similarity,
            'similarity_level' => 'high',
            'similarity_label' => 'High',
        ),
        'ranking_robustness' => array(
            'profile_count' => 5,
            'top_rank_count' => (int) round( $top_frequency / 20.0 ),
            'top_rank_frequency_pct' => $top_frequency,
            'best_rank' => $best_rank,
            'worst_rank' => $worst_rank,
            'rank_spread' => $worst_rank - $best_rank,
            'mean_rank' => ( $best_rank + $worst_rank ) / 2,
        ),
        'sample' => array(
            'maturity' => 'developing',
            'maturity_label' => 'Developing',
            'cleaner' => 4,
            'context_comparable' => 5,
            'signal_basis' => 'cleaner',
        ),
        'metrics' => array(
            array( 'metric' => 'box_entries', 'median_delta' => 2.5, 'direction' => 'positive' ),
            array( 'metric' => 'shots', 'median_delta' => 1.0, 'direction' => 'positive' ),
        ),
        'suitability_rank' => 1,
    );
}

$primary = tt_v114_item( 'g-primary', 'high-press', 'B', 84.0, 80.0, 78.0, 80.0, 1, 2 );
$alternative = tt_v114_item( 'g-alt', 'high-press', 'B', 79.5, 86.0, 88.0, 20.0, 1, 2 );
$alternative['suitability_rank'] = 2;
$third = tt_v114_item( 'g-third', 'transition', 'C', 68.0, 72.0, 61.0, 0.0, 3, 3 );
$third['suitability_rank'] = 3;

$retrieval = array(
    'precedent_id' => 'historical-precedent-retrieval',
    'status' => 'available',
    'language' => 'en',
    'items' => array( $primary, $alternative, $third ),
    'ranking_robustness' => array(
        'robustness_level' => 'stable',
        'top_stability_pct' => 80.0,
        'baseline_top_margin' => 4.5,
        'unique_top_count' => 2,
    ),
    'method' => array(),
);

$briefed = TactiTwin_Evidence_Decision_Brief::retrieval( $retrieval, 'en' );
tt_v114_assert( 'g-primary' === $briefed['items'][0]['group_key'], 'v1.14 preserves baseline evidence order' );
tt_v114_assert( 'g-primary' === $briefed['decision_brief']['primary']['group_key'], 'first-ranked evidence becomes the primary precedent' );
tt_v114_assert( 'g-alt' === $briefed['decision_brief']['alternative']['group_key'], 'second-ranked evidence becomes the visible alternative' );
tt_v114_assert( 'strong' === $briefed['decision_brief']['comparison']['alternative_strength'], 'close or profile-winning alternative is marked strong' );
tt_v114_assert( 4.5 === $briefed['decision_brief']['comparison']['suitability_gap'], 'suitability gap is descriptive and preserved' );
tt_v114_assert( false === $briefed['decision_brief']['method']['creates_new_tactical_score'], 'brief explicitly creates no new tactical score' );
tt_v114_assert( false === $briefed['decision_brief']['method']['changes_evidence_order'], 'brief explicitly cannot reorder evidence' );

$briefed_es = TactiTwin_Evidence_Decision_Brief::retrieval( $retrieval, 'es' );
tt_v114_assert(
    $briefed['decision_brief']['primary']['scores'] === $briefed_es['decision_brief']['primary']['scores'],
    'ES/EN does not alter decision-brief numeric evidence'
);
tt_v114_assert(
    $briefed['decision_brief']['comparison']['alternative_strength'] === $briefed_es['decision_brief']['comparison']['alternative_strength'],
    'ES/EN does not alter alternative classification'
);

$weak = tt_v114_item( 'g-weak', 'low-block', 'A', 46.0, 42.0, 37.0, 20.0, 1, 3 );
$weak['sample']['cleaner'] = 1;
$weak['sample']['context_comparable'] = 5;
$weak_brief = TactiTwin_Evidence_Decision_Brief::from_items( array( $weak ), array(), 'en' );
$weak_flags = implode( ' ', $weak_brief['primary']['what_could_invalidate'] );
tt_v114_assert( false !== strpos( $weak_flags, 'below 50' ), 'low quality/similarity are surfaced as caution flags' );
tt_v114_assert( false !== strpos( $weak_flags, 'Fewer than half' ), 'low cleaner share is surfaced as a caution flag' );

$empty = TactiTwin_Evidence_Decision_Brief::from_items( array(), array(), 'en' );
tt_v114_assert( 'no-evidence' === $empty['status'], 'empty shortlist produces explicit no-evidence brief' );
tt_v114_assert( null === $empty['primary'], 'empty shortlist has no invented primary precedent' );

$catalog = array(
    'catalog_id' => 'pre-match-precedent-catalog',
    'scenarios' => array(
        array(
            'scenario_id' => 'high-press',
            'scenario_name' => 'High press',
            'contexts' => array( $primary, $alternative ),
            'ranking_robustness' => array( 'robustness_level'=>'stable', 'top_stability_pct'=>80.0 ),
        ),
        array(
            'scenario_id' => 'transition',
            'scenario_name' => 'Transition',
            'contexts' => array( $third ),
            'ranking_robustness' => array( 'robustness_level'=>'single-candidate', 'top_stability_pct'=>100.0 ),
        ),
    ),
    'method' => array(),
);
$catalog_briefed = TactiTwin_Evidence_Decision_Brief::catalog( $catalog, 'en' );
tt_v114_assert( 'high-press' === $catalog_briefed['scenarios'][0]['scenario_id'], 'catalog scenario order is preserved' );
tt_v114_assert( 'transition' === $catalog_briefed['scenarios'][1]['scenario_id'], 'second catalog scenario order is preserved' );
tt_v114_assert( 'g-primary' === $catalog_briefed['scenarios'][0]['decision_brief']['primary']['group_key'], 'scenario brief uses first context inside that scenario' );

$playbook = array(
    'playbook_id' => 'contingency-playbook',
    'active_plan' => array( 'id'=>'B', 'scenario_id'=>'high-press' ),
    'plans' => array(
        'plan_a' => array( 'id'=>'A' ),
        'plan_b' => array( 'id'=>'B', 'scenario_id'=>'high-press' ),
        'plan_c' => array( 'id'=>'C' ),
    ),
    'method' => array(),
);
$attached = TactiTwin_Playbook_Precedent_Attachment::attach( $playbook, $briefed, 'en', 3 );
tt_v114_assert( 'g-primary' === $attached['plans']['plan_b']['historical_precedents']['decision_brief']['primary']['group_key'], 'Plan B brief uses only Plan B + active-scenario evidence' );
tt_v114_assert( 'g-third' === $attached['plans']['plan_c']['historical_precedents']['decision_brief']['primary']['group_key'], 'Plan C brief uses only Plan C evidence' );
tt_v114_assert( 'no-evidence' === $attached['plans']['plan_a']['historical_precedents']['decision_brief']['status'], 'Plan A does not borrow evidence from other destination plans' );

echo "Evidence decision brief tests passed.\n";
