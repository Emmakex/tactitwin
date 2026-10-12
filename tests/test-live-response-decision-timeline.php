<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-live-response-decision-timeline.php';

function tt_live_response_timeline_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_live_response_timeline_review( string $status, string $decision, ?string $selected_id, ?string $selected_name, string $branch_id, string $branch_name, string $prepared_decision, string $target_id, string $target_name, string $note = '' ): array {
    return array(
        'review_id' => 'live-twin-switch-confirmation',
        'state_resolution' => array(
            'branch_id' => $branch_id,
            'branch_name' => $branch_name,
            'match_type' => 'windowed-prepared-state',
            'exact_hypothesis' => false,
        ),
        'prepared_switch' => array(
            'decision' => $prepared_decision,
            'target_response' => array( 'response_id'=>$target_id, 'response_name'=>$target_name ),
            'baseline_response' => array( 'response_id'=>'balanced-control', 'response_name'=>'Balanced Control' ),
            'state_fit_margin' => 7.5,
            'coverage_caution' => false,
        ),
        'staff_decision' => array(
            'decision' => $decision,
            'status' => $status,
            'selected_response' => null === $selected_id ? null : array( 'response_id'=>$selected_id, 'response_name'=>$selected_name ),
            'note' => $note,
        ),
        'provenance' => array(
            'staff_brief_snapshot_uuid' => 'brief-uuid',
            'staff_brief_content_sha256' => str_repeat( 'a', 64 ),
            'frozen_twin_content_sha256' => str_repeat( 'b', 64 ),
            'reviewed_by' => 7,
            'reviewed_at' => '2026-10-12 09:00:00',
        ),
    );
}

$snapshots = array(
    array(
        'id'=>1, 'minute'=>10, 'our_score'=>0, 'opponent_score'=>0, 'our_players'=>11, 'opponent_players'=>11,
        'active_plan'=>'A', 'active_mode'=>'baseline', 'playbook'=>array( 'playbook_id'=>'contingency-playbook' ),
    ),
    array(
        'id'=>3, 'minute'=>68, 'our_score'=>1, 'opponent_score'=>0, 'our_players'=>11, 'opponent_players'=>11,
        'active_plan'=>'A', 'active_mode'=>'baseline',
        'playbook'=>array(
            'live_twin_switch_review'=>tt_live_response_timeline_review(
                'confirmed-prepared-switch', 'confirm-prepared', 'transition-security', 'Transition Security',
                'protect-lead', 'Protect a one-goal lead', 'switch-candidate', 'transition-security', 'Transition Security', 'Confirm now'
            ),
        ),
    ),
    array(
        'id'=>2, 'minute'=>62, 'our_score'=>1, 'opponent_score'=>0, 'our_players'=>11, 'opponent_players'=>11,
        'active_plan'=>'A', 'active_mode'=>'baseline',
        'playbook'=>array(
            'live_twin_switch_review'=>tt_live_response_timeline_review(
                'deferred', 'defer', null, null,
                'protect-lead', 'Protect a one-goal lead', 'switch-candidate', 'transition-security', 'Transition Security', 'Wait five minutes'
            ),
        ),
    ),
    array(
        'id'=>4, 'minute'=>74, 'our_score'=>1, 'opponent_score'=>1, 'our_players'=>11, 'opponent_players'=>11,
        'active_plan'=>'B', 'active_mode'=>'escape_high_press',
        'playbook'=>array(
            'live_twin_switch_review'=>tt_live_response_timeline_review(
                'rejected-prepared-switch', 'retain-baseline', 'balanced-control', 'Balanced Control',
                'late-draw', 'Late draw', 'switch-candidate', 'width-switch', 'Width & Switch', 'Reject and retain baseline'
            ),
        ),
    ),
);

$source = $snapshots;
$timeline = TactiTwin_Live_Response_Decision_Timeline::compose( $snapshots, 'en' );

tt_live_response_timeline_assert( 'live-response-decision-timeline' === $timeline['timeline_id'], 'timeline id is stable' );
tt_live_response_timeline_assert( 3 === $timeline['decision_count'], 'only snapshots with recorded Twin reviews are included' );
tt_live_response_timeline_assert( 62 === $timeline['decisions'][0]['minute'], 'decisions are ordered by minute' );
tt_live_response_timeline_assert( 'deferred' === $timeline['decisions'][0]['staff_decision']['status'], 'first decision is deferred' );
tt_live_response_timeline_assert( 68 === $timeline['decisions'][1]['minute'], 'confirmed switch follows defer' );
tt_live_response_timeline_assert( 'transition-security' === $timeline['decisions'][1]['staff_decision']['selected_response_id'], 'confirmed response is preserved' );
tt_live_response_timeline_assert( 'A' === $timeline['decisions'][1]['active_plan']['plan'], 'response decision preserves separately recorded active plan' );
tt_live_response_timeline_assert( 'B' === $timeline['decisions'][2]['active_plan']['plan'], 'later Plan B state remains visible without being rewritten' );
tt_live_response_timeline_assert( 1 === $timeline['summary']['status_counts']['confirmed-prepared-switch'], 'confirmed switch count is correct' );
tt_live_response_timeline_assert( 1 === $timeline['summary']['status_counts']['rejected-prepared-switch'], 'rejected switch count is correct' );
tt_live_response_timeline_assert( 1 === $timeline['summary']['status_counts']['deferred'], 'deferred count is correct' );
tt_live_response_timeline_assert( 62 === $timeline['summary']['first_decision_minute'], 'first decision minute is correct' );
tt_live_response_timeline_assert( 74 === $timeline['summary']['last_decision_minute'], 'last decision minute is correct' );
tt_live_response_timeline_assert( 'rejected-prepared-switch' === $timeline['summary']['latest_status'], 'latest status is correct' );
tt_live_response_timeline_assert( false === $timeline['method']['changes_active_plan'], 'timeline never changes active plan' );
tt_live_response_timeline_assert( false === $timeline['method']['infers_missing_decisions'], 'timeline never infers missing decisions' );
tt_live_response_timeline_assert( $source === $snapshots, 'timeline does not mutate source snapshots' );

$es = TactiTwin_Live_Response_Decision_Timeline::compose( $snapshots, 'es' );
tt_live_response_timeline_assert( $timeline['decision_count'] === $es['decision_count'], 'decision count is language-neutral' );
tt_live_response_timeline_assert( $timeline['decisions'] === $es['decisions'], 'decision reconstruction is language-neutral' );

echo "Live response decision timeline tests passed.\n";
