<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-match-day-staff-brief.php';

function tt_v116_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_v116_evidence( string $group, string $severity = 'none' ): array {
    $signals = array();
    if ( 'material' === $severity ) {
        $signals[] = array(
            'id' => 'high-quality-low-similarity',
            'severity' => 'material',
            'title' => 'Strong evidence, weak opponent analogy',
            'detail' => 'Evidence quality is high but opponent similarity is low.',
        );
    }
    return array(
        'status' => 'available',
        'matched_count' => 2,
        'decision_brief' => array(
            'status' => 'available',
            'primary' => array(
                'group_key' => $group,
                'scenario_id' => 'build-up-vs-high-press',
                'scenario_name' => 'Build-up vs High Press',
                'scores' => array(
                    'evidence_suitability' => 81.0,
                    'evidence_quality' => 78.0,
                    'opponent_similarity' => 72.0,
                ),
            ),
            'alternative' => array(
                'group_key' => $group . '-alt',
                'scenario_id' => 'opponent-side-bias',
                'scenario_name' => 'Opponent Side Bias',
            ),
            'comparison' => array( 'suitability_gap'=>5.0 ),
            'conflict_signals' => array(
                'status' => 'available',
                'signal_count' => count( $signals ),
                'material_count' => 'material' === $severity ? 1 : 0,
                'watch_count' => 0,
                'highest_severity' => $severity,
                'signals' => $signals,
            ),
        ),
    );
}

$match_plan = array(
    'plan_id' => 'match-plan',
    'language' => 'en',
    'team' => array( 'source_id'=>'100', 'name'=>'Our Team' ),
    'opponent' => array( 'source_id'=>'200', 'name'=>'Opponent' ),
    'summary' => array( 'headline'=>'Protect buildup and attack weak side.' ),
    'coverage' => array( 'effective_matches'=>5, 'status'=>'high' ),
    'priorities' => array(
        array( 'rank'=>1, 'scenario_id'=>'one', 'scenario_name'=>'One', 'priority_score'=>88.0, 'instruction'=>'Instruction 1' ),
        array( 'rank'=>2, 'scenario_id'=>'two', 'scenario_name'=>'Two', 'priority_score'=>80.0, 'instruction'=>'Instruction 2' ),
        array( 'rank'=>3, 'scenario_id'=>'three', 'scenario_name'=>'Three', 'priority_score'=>74.0, 'instruction'=>'Instruction 3' ),
        array( 'rank'=>4, 'scenario_id'=>'four', 'scenario_name'=>'Four', 'priority_score'=>66.0, 'instruction'=>'Instruction 4' ),
    ),
    'phases' => array(
        'opening_15_minutes' => array( 'Open with width.', 'Protect central loss.' ),
        'halftime_checks' => array( 'Check press escape rate.', 'Check transition threat.' ),
    ),
);

$playbook = array(
    'playbook_id' => 'contingency-playbook',
    'language' => 'en',
    'team' => array( 'source_id'=>'100', 'name'=>'Our Team' ),
    'opponent' => array( 'source_id'=>'200', 'name'=>'Opponent' ),
    'current_context' => array( 'minute'=>1, 'our_score'=>0, 'opponent_score'=>0, 'score_state'=>'drawing', 'our_players'=>11, 'opponent_players'=>11, 'manpower_state'=>'equal' ),
    'active_plan' => array( 'id'=>'A', 'mode'=>'baseline', 'label'=>'Plan A' ),
    'plans' => array(
        'plan_a' => array(
            'id'=>'A', 'name'=>'Plan A', 'status'=>'available', 'mode'=>'baseline', 'purpose'=>'Baseline plan',
            'instructions'=>array('A1','A2','A3','A4','A5','A6'),
            'exit_conditions'=>array('Exit A1','Exit A2'),
            'historical_precedents'=>tt_v116_evidence('ga'),
        ),
        'plan_b' => array(
            'id'=>'B', 'name'=>'Plan B', 'status'=>'armed', 'mode'=>'press_escape',
            'scenario_id'=>'build-up-vs-high-press', 'scenario_name'=>'Build-up vs High Press', 'risk_score'=>82.0,
            'trigger'=>'If opponent press is confirmed.', 'activation_rule'=>'Manual observation required.',
            'instructions'=>array('B1','B2'),
            'historical_precedents'=>tt_v116_evidence('gb','material'),
        ),
        'plan_c' => array(
            'id'=>'C', 'name'=>'Plan C', 'status'=>'standby', 'mode'=>'standby',
            'trigger'=>'Late score/manpower contingency.', 'instructions'=>array('C1','C2'),
            'historical_precedents'=>tt_v116_evidence('gc'),
        ),
    ),
    'decision_tree' => array(
        array('order'=>1,'condition'=>'Red card','target_plan'=>'C','mode'=>'manpower'),
        array('order'=>2,'condition'=>'Trailing late','target_plan'=>'C','mode'=>'chase'),
        array('order'=>3,'condition'=>'Confirmed press','target_plan'=>'B','mode'=>'press_escape'),
    ),
);

$brief = TactiTwin_Match_Day_Staff_Brief::compose( $match_plan, $playbook, 'en' );
tt_v116_assert( 'match-day-staff-brief' === $brief['staff_brief_id'], 'staff brief ID is stable' );
tt_v116_assert( 3 === count( $brief['top_priorities'] ), 'staff brief keeps only the top three match priorities' );
tt_v116_assert( 'one' === $brief['top_priorities'][0]['scenario_id'], 'priority order is preserved' );
tt_v116_assert( 'three' === $brief['top_priorities'][2]['scenario_id'], 'third priority is preserved before truncation' );
tt_v116_assert( 'A' === $brief['plans']['plan_a']['id'], 'Plan A remains available' );
tt_v116_assert( 'B' === $brief['plans']['plan_b']['id'], 'Plan B remains available' );
tt_v116_assert( 'C' === $brief['plans']['plan_c']['id'], 'Plan C remains available' );
tt_v116_assert( 5 === count( $brief['plans']['plan_a']['instructions'] ), 'plan instructions are presentation-trimmed without changing source order' );
tt_v116_assert( 'gb' === $brief['plans']['plan_b']['evidence']['primary']['group_key'], 'plan-filtered primary evidence is preserved' );
tt_v116_assert( 1 === $brief['evidence_watch']['material_signal_count'], 'material plan evidence tensions are aggregated for staff attention' );
tt_v116_assert( 'B' === $brief['evidence_watch']['material_signals'][0]['plan_id'], 'material tension remains attributed to its plan' );
tt_v116_assert( false === $brief['method']['creates_new_tactical_score'], 'staff brief explicitly creates no new tactical score' );
tt_v116_assert( false === $brief['method']['reorders_priorities'], 'staff brief explicitly cannot reorder match priorities' );
tt_v116_assert( false === $brief['method']['changes_plan_activation'], 'staff brief explicitly cannot activate another plan' );

$brief_es = TactiTwin_Match_Day_Staff_Brief::compose( $match_plan, $playbook, 'es' );
tt_v116_assert( $brief['top_priorities'] === $brief_es['top_priorities'], 'ES/EN does not alter staff-brief priority data' );
tt_v116_assert( $brief['plans']['plan_b']['evidence']['primary'] === $brief_es['plans']['plan_b']['evidence']['primary'], 'ES/EN does not alter source evidence data' );

$mismatched = $playbook;
$mismatched['opponent']['source_id'] = '999';
$thrown = false;
try {
    TactiTwin_Match_Day_Staff_Brief::compose( $match_plan, $mismatched, 'en' );
} catch ( InvalidArgumentException $e ) {
    $thrown = true;
}
tt_v116_assert( true === $thrown, 'composer rejects Match Plan / Playbook matchup mismatch' );

echo "Match-day staff brief tests passed.\n";
