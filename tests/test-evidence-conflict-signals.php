<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-evidence-conflict-signals.php';

function tt_v115_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_v115_candidate(
    string $key,
    float $suitability,
    $quality,
    $similarity,
    float $top_frequency = 100.0,
    int $worst_rank = 1,
    array $deltas = array()
): array {
    $rows = array();
    foreach ( $deltas as $metric => $delta ) {
        $rows[] = array(
            'metric' => $metric,
            'median_delta' => $delta,
            'direction' => $delta > 0 ? 'positive' : ( $delta < 0 ? 'negative' : 'neutral' ),
        );
    }
    return array(
        'group_key' => $key,
        'scenario_id' => 'scenario-' . $key,
        'scenario_name' => 'Scenario ' . $key,
        'scores' => array(
            'scenario_relevance' => 82.0,
            'evidence_suitability' => $suitability,
            'evidence_quality' => $quality,
            'opponent_similarity' => $similarity,
        ),
        'ranking' => array(
            'suitability_rank' => 1,
            'best_rank' => 1,
            'worst_rank' => $worst_rank,
            'top_rank_frequency_pct' => $top_frequency,
        ),
        'what_supports_it' => array(
            'historical_deltas' => $rows,
        ),
    );
}

function tt_v115_brief( array $primary, ?array $alternative = null, array $override = array() ): array {
    $brief = array(
        'brief_id' => 'evidence-decision-brief',
        'status' => 'available',
        'language' => 'en',
        'scope' => 'test',
        'primary' => $primary,
        'alternative' => $alternative,
        'comparison' => null === $alternative ? null : array(
            'suitability_gap' => round(
                (float) $primary['scores']['evidence_suitability'] - (float) $alternative['scores']['evidence_suitability'],
                1
            ),
        ),
        'ranking_context' => array(
            'robustness_level' => 'very-stable',
            'top_stability_pct' => 100.0,
            'baseline_top_margin' => 12.0,
            'unique_top_count' => 1,
        ),
        'method' => array(),
    );
    return array_replace_recursive( $brief, $override );
}

function tt_v115_ids( array $signals ): array {
    return array_values( array_map(
        static fn( array $row ): string => (string) ( $row['id'] ?? '' ),
        $signals['signals'] ?? array()
    ) );
}

$balanced = tt_v115_brief(
    tt_v115_candidate( 'balanced', 81.0, 78.0, 76.0, 100.0, 1, array( 'box_entries'=>2.0, 'shots'=>1.0 ) ),
    tt_v115_candidate( 'alt', 67.0, 70.0, 72.0, 0.0, 2, array( 'box_entries'=>1.0, 'shots'=>0.5 ) )
);
$balanced_en = TactiTwin_Evidence_Conflict_Signals::brief( $balanced, 'en' );
tt_v115_assert( 0 === $balanced_en['conflict_signals']['signal_count'], 'balanced stable evidence produces no configured conflict signal' );
tt_v115_assert( 'none' === $balanced_en['conflict_signals']['highest_severity'], 'no-conflict brief exposes none severity' );
tt_v115_assert( false === $balanced_en['method']['evidence_conflict_signals']['creates_conflict_score'], 'v1.15 explicitly creates no conflict score' );
tt_v115_assert( false === $balanced_en['method']['evidence_conflict_signals']['changes_evidence_order'], 'v1.15 cannot reorder evidence' );

$quality_vs_similarity = tt_v115_brief(
    tt_v115_candidate( 'hq-ls', 76.0, 86.0, 32.0 )
);
$quality_vs_similarity = TactiTwin_Evidence_Conflict_Signals::brief( $quality_vs_similarity, 'en' );
tt_v115_assert( in_array( 'high-quality-low-similarity', tt_v115_ids( $quality_vs_similarity['conflict_signals'] ), true ), 'high quality + low similarity is detected' );
tt_v115_assert( 'material' === $quality_vs_similarity['conflict_signals']['highest_severity'], 'quality/similarity divergence is material' );

$similarity_vs_quality = tt_v115_brief(
    tt_v115_candidate( 'hs-lq', 74.0, 38.0, 84.0 )
);
$similarity_vs_quality = TactiTwin_Evidence_Conflict_Signals::brief( $similarity_vs_quality, 'en' );
tt_v115_assert( in_array( 'high-similarity-low-quality', tt_v115_ids( $similarity_vs_quality['conflict_signals'] ), true ), 'high similarity + low quality is detected' );

$sensitive = tt_v115_brief(
    tt_v115_candidate( 'fragile', 84.0, 75.0, 74.0, 40.0, 3 ),
    tt_v115_candidate( 'challenger', 81.0, 78.0, 80.0, 40.0, 2 ),
    array( 'ranking_context'=>array( 'robustness_level'=>'sensitive', 'top_stability_pct'=>40.0, 'baseline_top_margin'=>3.0, 'unique_top_count'=>3 ) )
);
$sensitive = TactiTwin_Evidence_Conflict_Signals::brief( $sensitive, 'en' );
$ids = tt_v115_ids( $sensitive['conflict_signals'] );
tt_v115_assert( in_array( 'high-suitability-sensitive-ranking', $ids, true ), 'high suitability + fragile ranking is detected' );
tt_v115_assert( in_array( 'primary-alternative-near-tie', $ids, true ), 'near tie is detected independently' );

$opposed = tt_v115_brief(
    tt_v115_candidate( 'primary-opposed', 78.0, 74.0, 72.0, 80.0, 2, array( 'box_entries'=>2.5, 'shots'=>1.2, 'xg'=>0.4 ) ),
    tt_v115_candidate( 'alt-opposed', 75.0, 76.0, 74.0, 20.0, 2, array( 'box_entries'=>-1.5, 'shots'=>0.8, 'xg'=>-0.6 ) )
);
$opposed = TactiTwin_Evidence_Conflict_Signals::brief( $opposed, 'en' );
$opposed_ids = tt_v115_ids( $opposed['conflict_signals'] );
tt_v115_assert( in_array( 'opposed-historical-deltas', $opposed_ids, true ), 'opposed shared historical deltas are detected' );
$opposed_signal = null;
foreach ( $opposed['conflict_signals']['signals'] as $signal ) {
    if ( 'opposed-historical-deltas' === (string) ( $signal['id'] ?? '' ) ) {
        $opposed_signal = $signal;
        break;
    }
}
$opposed_metrics = $opposed_signal['evidence']['metrics'] ?? array();
tt_v115_assert( 1 === count( $opposed_metrics ), 'only materially opposed shared metrics are reported' );
tt_v115_assert( 'box_entries' === (string) ( $opposed_metrics[0]['metric'] ?? '' ), 'sub-threshold xG opposition is deliberately excluded' );

$missing = tt_v115_brief(
    tt_v115_candidate( 'missing', 72.0, null, 73.0 )
);
$missing = TactiTwin_Evidence_Conflict_Signals::brief( $missing, 'en' );
tt_v115_assert( in_array( 'primary-evidence-axis-missing', tt_v115_ids( $missing['conflict_signals'] ), true ), 'missing evidence axis produces watch signal' );
tt_v115_assert( 'watch' === $missing['conflict_signals']['highest_severity'], 'missing axis alone is a watch signal' );

$opposed_es = TactiTwin_Evidence_Conflict_Signals::brief( tt_v115_brief(
    tt_v115_candidate( 'primary-opposed', 78.0, 74.0, 72.0, 80.0, 2, array( 'box_entries'=>2.5, 'shots'=>1.2, 'xg'=>0.4 ) ),
    tt_v115_candidate( 'alt-opposed', 75.0, 76.0, 74.0, 20.0, 2, array( 'box_entries'=>-1.5, 'shots'=>0.8, 'xg'=>-0.6 ) )
), 'es' );
tt_v115_assert( tt_v115_ids( $opposed['conflict_signals'] ) === tt_v115_ids( $opposed_es['conflict_signals'] ), 'ES/EN preserves conflict IDs' );
tt_v115_assert( $opposed['conflict_signals']['highest_severity'] === $opposed_es['conflict_signals']['highest_severity'], 'ES/EN preserves conflict severity' );

$catalog = array(
    'catalog_id' => 'pre-match-precedent-catalog',
    'scenarios' => array(
        array( 'scenario_id'=>'one', 'decision_brief'=>$balanced ),
        array( 'scenario_id'=>'two', 'decision_brief'=>tt_v115_brief( tt_v115_candidate( 'hq-ls', 76.0, 86.0, 32.0 ) ) ),
    ),
    'method' => array(),
);
$catalog = TactiTwin_Evidence_Conflict_Signals::catalog( $catalog, 'en' );
tt_v115_assert( 'one' === $catalog['scenarios'][0]['scenario_id'], 'catalog scenario order remains unchanged' );
tt_v115_assert( 'two' === $catalog['scenarios'][1]['scenario_id'], 'second scenario order remains unchanged' );
tt_v115_assert( 1 === $catalog['conflict_signal_summary']['scenarios_with_material_signals'], 'catalog summary counts material-signal scenarios' );

$unavailable = TactiTwin_Evidence_Conflict_Signals::brief( array( 'status'=>'no-evidence' ), 'en' );
tt_v115_assert( 'unavailable' === $unavailable['conflict_signals']['status'], 'no-evidence brief does not invent conflicts' );
tt_v115_assert( 0 === $unavailable['conflict_signals']['signal_count'], 'no-evidence brief has zero conflict signals' );

echo "Evidence conflict signal tests passed.\n";
