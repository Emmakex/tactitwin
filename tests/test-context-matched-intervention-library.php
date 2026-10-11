<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-context-matched-intervention-library.php';

function tt_library_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_library_case(
    string $session,
    string $match,
    string $opponent,
    int $minute,
    int $our_score,
    int $opponent_score,
    int $our_players,
    int $opponent_players,
    string $interpretability,
    bool $comparable,
    string $scenario,
    float $progression_delta,
    float $turnover_delta
): array {
    return array(
        'session_uuid'=>$session,
        'source_match_id'=>$match,
        'opponent_source_id'=>$opponent,
        'transition'=>array(
            'minute'=>$minute,
            'from_plan'=>'A',
            'to_plan'=>'B',
            'to_mode'=>'scenario',
            'to_scenario'=>$scenario,
            'context'=>array(
                'interpretability'=>$interpretability,
                'context_comparable'=>$comparable,
                'recorded_snapshot_change'=>array(
                    'after'=>array(
                        'our_score'=>$our_score,
                        'opponent_score'=>$opponent_score,
                        'our_players'=>$our_players,
                        'opponent_players'=>$opponent_players,
                    ),
                ),
                'warnings'=>array(),
            ),
            'focus'=>array(
                'metrics'=>array(
                    array('metric'=>'our_progressive_actions_per90','delta'=>$progression_delta),
                    array('metric'=>'our_own_third_turnovers_per90','delta'=>$turnover_delta),
                ),
            ),
        ),
    );
}

$cases = array(
    tt_library_case('s1','m1','2',50,0,0,11,11,'cleaner',true,'build-up-vs-high-press',10.0,-2.0),
    tt_library_case('s2','m2','3',54,1,1,11,11,'cleaner',true,'build-up-vs-high-press',20.0,-4.0),
    tt_library_case('s3','m3','4',58,2,2,11,11,'cleaner',true,'build-up-vs-high-press',30.0,-6.0),
    tt_library_case('s4','m4','5',52,0,0,11,11,'confounded',true,'build-up-vs-high-press',80.0,-20.0),
    tt_library_case('s5','m5','6',56,0,0,11,11,'highly-confounded',false,'build-up-vs-high-press',100.0,-30.0),
    tt_library_case('s6','m6','7',55,1,0,11,11,'cleaner',true,'build-up-vs-high-press',5.0,-1.0),
    tt_library_case('s7','m7','8',72,0,0,11,11,'cleaner',true,'build-up-vs-high-press',7.0,-1.0),
    tt_library_case('s8','m8','9',55,0,0,10,11,'cleaner',true,'build-up-vs-high-press',6.0,-1.0),
    tt_library_case('s9','m9','10',55,0,0,11,11,'cleaner',true,'attack-vs-low-block',4.0,0.0),
);

$en = TactiTwin_Context_Matched_Intervention_Library::build($cases,'1','en');
$es = TactiTwin_Context_Matched_Intervention_Library::build($cases,'1','es');

tt_library_assert('context-matched-intervention-library' === $en['library_id'], 'library identity retained');
tt_library_assert(5 === $en['coverage']['group_count'], 'score, manpower, minute band and scenario split groups deterministically');
tt_library_assert(8 === $en['coverage']['context_comparable_interventions'], 'all cleaner/confounded comparable cases counted');
tt_library_assert(1 === $en['coverage']['excluded_interventions'], 'highly-confounded case excluded from aggregate signal');

$group = $en['groups'][0];
tt_library_assert('build-up-vs-high-press' === $group['dimensions']['scenario_id'], 'largest group scenario retained');
tt_library_assert('31-60' === $group['dimensions']['minute_band'], 'minute band matched');
tt_library_assert('drawing' === $group['dimensions']['score_state'], 'score state matched');
tt_library_assert('equal' === $group['dimensions']['manpower_state'], 'manpower state matched');
tt_library_assert(3 === $group['sample']['cleaner'], 'cleaner sample counted');
tt_library_assert(4 === $group['sample']['context_comparable'], 'substitution-confounded case retained as secondary evidence');
tt_library_assert(1 === $group['sample']['excluded'], 'materially changed case retained as excluded evidence');
tt_library_assert('cleaner' === $group['sample']['signal_basis'], 'two-plus cleaner cases take precedence over fallback sample');
tt_library_assert('emerging' === $group['sample']['maturity'], 'three cleaner cases are an emerging sample');

$metrics = array_column($group['metrics'], null, 'metric');
tt_library_assert(20.0 === $metrics['our_progressive_actions_per90']['median_delta'], 'median uses cleaner sample and ignores confounded/excluded outliers');
tt_library_assert(20.0 === $metrics['our_progressive_actions_per90']['mean_delta'], 'mean uses cleaner sample');
tt_library_assert(3 === $metrics['our_progressive_actions_per90']['up'], 'all cleaner progression deltas are upward');
tt_library_assert('mostly-up' === $metrics['our_progressive_actions_per90']['direction'], 'consistent upward descriptive direction detected');
tt_library_assert(-4.0 === $metrics['our_own_third_turnovers_per90']['median_delta'], 'negative turnover median preserved');
tt_library_assert('mostly-down' === $metrics['our_own_third_turnovers_per90']['direction'], 'consistent downward descriptive direction detected');

$filtered = TactiTwin_Context_Matched_Intervention_Library::build($cases,'1','en','attack-vs-low-block');
tt_library_assert(1 === $filtered['coverage']['group_count'], 'scenario filter keeps only requested scenario');
tt_library_assert('attack-vs-low-block' === $filtered['groups'][0]['dimensions']['scenario_id'], 'scenario filter result is correct');

tt_library_assert($en['groups'][0]['key'] === $es['groups'][0]['key'], 'language does not alter grouping');
tt_library_assert($en['groups'][0]['metrics'][0]['median_delta'] === $es['groups'][0]['metrics'][0]['median_delta'], 'language does not alter aggregate deltas');
tt_library_assert($en['groups'][0]['sample']['signal_basis_label'] !== $es['groups'][0]['sample']['signal_basis_label'], 'sample-basis label is translated');
tt_library_assert('descriptive-context-matched-cross-match-intervention-library' === $en['method']['type'], 'method boundary is explicit');

echo "Context-matched intervention library tests passed.\n";
