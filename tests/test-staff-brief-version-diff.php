<?php

define( 'ABSPATH', __DIR__ );
define( 'TACTITWIN_VERSION', '0.1.0-dev' );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-staff-brief-snapshot.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-staff-brief-version-diff.php';

function tt_v118_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_v118_brief( string $variant = 'base' ): array {
    $changed = 'changed' === $variant;
    $team = array( 'source_id'=>'100', 'name'=>'Our Team' );
    $opponent = array( 'source_id'=>'200', 'name'=>'Opponent' );
    $match_plan = array( 'plan_id'=>'match-plan', 'team'=>$team, 'opponent'=>$opponent );
    $playbook = array( 'playbook_id'=>'contingency-playbook', 'team'=>$team, 'opponent'=>$opponent );

    $plan_b_signals = $changed
        ? array(
            'highest_severity'=>'material',
            'signals'=>array( array( 'id'=>'high-quality-low-similarity', 'severity'=>'material' ) ),
        )
        : array( 'highest_severity'=>'none', 'signals'=>array() );

    return array(
        'staff_brief_id'=>'match-day-staff-brief',
        'language'=>'en',
        'team'=>$team,
        'opponent'=>$opponent,
        'headline'=>$changed ? 'Attack the weak side earlier.' : 'Protect buildup and control transitions.',
        'coverage'=>array( 'effective_matches'=>$changed ? 6 : 5, 'status'=>'high' ),
        'reference_context'=>array( 'minute'=>1, 'our_score'=>0, 'opponent_score'=>0, 'our_players'=>11, 'opponent_players'=>11 ),
        'active_plan'=>array( 'id'=>'A', 'mode'=>'baseline' ),
        'top_priorities'=>$changed
            ? array(
                array( 'rank'=>1, 'scenario_id'=>'weak-side', 'scenario_name'=>'Weak Side', 'priority_score'=>86.0, 'instruction'=>'Switch earlier.' ),
                array( 'rank'=>2, 'scenario_id'=>'press', 'scenario_name'=>'Press', 'priority_score'=>82.0, 'instruction'=>'Protect buildup.' ),
                array( 'rank'=>3, 'scenario_id'=>'transition', 'scenario_name'=>'Transition', 'priority_score'=>71.0, 'instruction'=>'Keep rest defence.' ),
            )
            : array(
                array( 'rank'=>1, 'scenario_id'=>'press', 'scenario_name'=>'Press', 'priority_score'=>84.0, 'instruction'=>'Protect buildup.' ),
                array( 'rank'=>2, 'scenario_id'=>'weak-side', 'scenario_name'=>'Weak Side', 'priority_score'=>80.0, 'instruction'=>'Switch play.' ),
                array( 'rank'=>3, 'scenario_id'=>'transition', 'scenario_name'=>'Transition', 'priority_score'=>71.0, 'instruction'=>'Keep rest defence.' ),
            ),
        'plans'=>array(
            'plan_a'=>array(
                'id'=>'A','name'=>'Plan A','status'=>'available','mode'=>'baseline','scenario_id'=>null,'risk_score'=>null,'trigger'=>'','activation_rule'=>'',
                'instructions'=>array('A1'),
                'evidence'=>array('primary'=>array('group_key'=>'ga'),'alternative'=>array('group_key'=>'ga2'),'conflict_signals'=>array('highest_severity'=>'none','signals'=>array())),
            ),
            'plan_b'=>array(
                'id'=>'B','name'=>'Plan B','status'=>'armed','mode'=>$changed ? 'weak_side' : 'press_escape',
                'scenario_id'=>$changed ? 'weak-side' : 'press','risk_score'=>$changed ? 79.0 : 82.0,
                'trigger'=>$changed ? 'If their far side remains open.' : 'If the press is confirmed.','activation_rule'=>'Manual observation required.',
                'instructions'=>$changed ? array('B-new','B2') : array('B1','B2'),
                'evidence'=>array(
                    'primary'=>array('group_key'=>$changed ? 'gb-new' : 'gb'),
                    'alternative'=>array('group_key'=>'gb2'),
                    'conflict_signals'=>$plan_b_signals,
                ),
            ),
            'plan_c'=>array(
                'id'=>'C','name'=>'Plan C','status'=>'standby','mode'=>'standby','scenario_id'=>null,'risk_score'=>null,'trigger'=>'Late context.','activation_rule'=>'','instructions'=>array('C1'),
                'evidence'=>array('primary'=>array('group_key'=>'gc'),'alternative'=>array(),'conflict_signals'=>array('highest_severity'=>'none','signals'=>array())),
            ),
        ),
        'decision_tree'=>$changed
            ? array( array('order'=>1,'condition'=>'Red card','target_plan'=>'C','mode'=>'manpower'), array('order'=>2,'condition'=>'Weak side confirmed','target_plan'=>'B','mode'=>'weak_side') )
            : array( array('order'=>1,'condition'=>'Red card','target_plan'=>'C','mode'=>'manpower'), array('order'=>2,'condition'=>'Press confirmed','target_plan'=>'B','mode'=>'press_escape') ),
        'phase_checks'=>array(
            'opening_15_minutes'=>$changed ? array('Check weak side.','Protect losses.') : array('Check press.','Protect losses.'),
            'halftime_checks'=>array('Check transition threat.'),
        ),
        'evidence_watch'=>array(
            'material_signal_count'=>$changed ? 1 : 0,
            'material_signals'=>$changed ? array( array('plan_id'=>'B','id'=>'high-quality-low-similarity') ) : array(),
        ),
        'handoff_source'=>array( 'match_plan'=>$match_plan, 'playbook'=>$playbook ),
        'method'=>array( 'type'=>'presentation-only-match-day-staff-brief' ),
    );
}

function tt_v118_snapshot( array $brief, string $uuid, string $at ): array {
    return TactiTwin_Staff_Brief_Snapshot::freeze( $brief, $uuid, 7, $at, 5 );
}

function tt_v118_ids( array $diff ): array {
    return array_map( static fn( array $row ): string => (string) ( $row['id'] ?? '' ), $diff['changes'] ?? array() );
}

$base = tt_v118_brief();
$s1 = tt_v118_snapshot( $base, '11111111-1111-4111-8111-111111111111', '2026-10-11 13:00:00' );
$s2 = tt_v118_snapshot( $base, '22222222-2222-4222-8222-222222222222', '2026-10-11 13:10:00' );
$unchanged = TactiTwin_Staff_Brief_Version_Diff::snapshots( $s1, $s2, 'en' );
tt_v118_assert( 'unchanged' === $unchanged['status'], 'identical approved content produces unchanged status' );
tt_v118_assert( 0 === $unchanged['change_count'], 'identical approved content produces zero changes' );
tt_v118_assert( false === $unchanged['method']['creates_change_score'], 'version diff explicitly creates no change score' );

$changed_brief = tt_v118_brief( 'changed' );
$current_diff = TactiTwin_Staff_Brief_Version_Diff::current_against_snapshot( $changed_brief, $s1, 'en' );
$ids = tt_v118_ids( $current_diff );
tt_v118_assert( 'changed' === $current_diff['status'], 'changed generated brief is detected before approval' );
tt_v118_assert( in_array( 'headline-changed', $ids, true ), 'headline changes are detected' );
tt_v118_assert( in_array( 'coverage-changed', $ids, true ), 'coverage changes are detected' );
tt_v118_assert( in_array( 'priority-order-changed', $ids, true ), 'priority order changes are detected' );
tt_v118_assert( in_array( 'plan-changed:b', $ids, true ), 'Plan B structural changes are detected' );
tt_v118_assert( in_array( 'opening-checks-changed', $ids, true ), 'opening checklist changes are detected' );
tt_v118_assert( in_array( 'decision-tree-changed', $ids, true ), 'decision-tree changes are detected' );
tt_v118_assert( in_array( 'material-evidence-watch-changed', $ids, true ), 'material evidence-warning changes are detected' );
tt_v118_assert( 'generated-current' === $current_diff['to']['kind'], 'pre-approval comparison identifies generated current version' );

$s3 = tt_v118_snapshot( $changed_brief, '33333333-3333-4333-8333-333333333333', '2026-10-11 13:20:00' );
approved_diff = TactiTwin_Staff_Brief_Version_Diff::snapshots( $s1, $s3, 'en' );
tt_v118_assert( tt_v118_ids( $current_diff ) === tt_v118_ids( $approved_diff ), 'pre-approval and approved-to-approved comparisons expose the same change IDs' );

$diff_es = TactiTwin_Staff_Brief_Version_Diff::current_against_snapshot( $changed_brief, $s1, 'es' );
tt_v118_assert( tt_v118_ids( $current_diff ) === tt_v118_ids( $diff_es ), 'ES/EN preserves change IDs' );
tt_v118_assert( $current_diff['change_count'] === $diff_es['change_count'], 'ES/EN preserves change count' );
tt_v118_assert( $current_diff['changes'][0]['before'] === $diff_es['changes'][0]['before'], 'ES/EN preserves before values' );
tt_v118_assert( $current_diff['changes'][0]['after'] === $diff_es['changes'][0]['after'], 'ES/EN preserves after values' );

$mismatch = $changed_brief;
$mismatch['opponent']['source_id'] = '999';
$thrown = false;
try {
    TactiTwin_Staff_Brief_Version_Diff::current_against_snapshot( $mismatch, $s1, 'en' );
} catch ( InvalidArgumentException $e ) {
    $thrown = true;
}
tt_v118_assert( true === $thrown, 'version diff rejects different matchups' );

echo "Staff Brief version diff tests passed.\n";
