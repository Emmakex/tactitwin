<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-frozen-twin-handoff.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-staff-brief-snapshot.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-staff-brief-version-diff.php';

function tt_frozen_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_frozen_brief(): array {
    $match_plan = array(
        'plan_id' => 'match-plan',
        'team' => array( 'source_id' => '10', 'name' => 'Our Team' ),
        'opponent' => array( 'source_id' => '20', 'name' => 'Opponent' ),
    );
    $playbook = array(
        'playbook_id' => 'contingency-playbook',
        'team' => array( 'source_id' => '10', 'name' => 'Our Team' ),
        'opponent' => array( 'source_id' => '20', 'name' => 'Opponent' ),
        'current_context' => array( 'minute' => 1, 'our_score' => 0, 'opponent_score' => 0, 'our_players' => 11, 'opponent_players' => 11 ),
    );
    return array(
        'staff_brief_id' => 'match-day-staff-brief',
        'language' => 'en',
        'team' => $match_plan['team'],
        'opponent' => $match_plan['opponent'],
        'headline' => 'Prepared match',
        'coverage' => array( 'effective_matches' => 5, 'status' => 'high' ),
        'top_priorities' => array(),
        'plans' => array(),
        'phase_checks' => array( 'opening_15_minutes' => array(), 'halftime_checks' => array() ),
        'decision_tree' => array(),
        'evidence_watch' => array( 'material_signals' => array() ),
        'handoff_source' => array(
            'match_plan' => $match_plan,
            'playbook' => $playbook,
        ),
        'method' => array( 'type' => 'test-brief' ),
    );
}

function tt_frozen_lab( string $target = 'transition-security', float $margin = 8.0 ): array {
    return array(
        'lab_id' => 'tactical-twin-lab',
        'language' => 'en',
        'team' => array( 'source_id' => '10', 'name' => 'Our Team' ),
        'opponent' => array( 'source_id' => '20', 'name' => 'Opponent' ),
        'preparation_summary' => array(
            'top_response_id' => 'press-resistant-build-up',
            'top_response_name' => 'Press-Resistant Build-up',
            'most_robust_response_id' => 'balanced-control',
            'most_robust_response_name' => 'Balanced Control',
            'switch_candidate_count' => 1,
            'coverage_caution_switches' => 0,
        ),
        'response_matrix' => array( 'matrix_id' => 'tactical-response-matrix', 'responses' => array( array( 'response_id' => 'press-resistant-build-up' ) ) ),
        'scenario_tree' => array( 'tree_id' => 'match-state-scenario-tree', 'branches' => array( array( 'branch_id' => 'protect-lead' ) ) ),
        'response_robustness' => array( 'robustness_id' => 'response-state-robustness', 'responses' => array( array( 'response_id' => $target ) ) ),
        'response_switch_playbook' => array(
            'playbook_id' => 'response-switch-playbook',
            'items' => array(
                array(
                    'branch_id' => 'protect-lead',
                    'branch_name' => 'Protect lead',
                    'decision' => 'switch-candidate',
                    'decision_label' => 'Review switch candidate',
                    'target_response' => array( 'response_id' => $target, 'response_name' => $target ),
                    'state_fit_margin' => $margin,
                    'coverage_caution' => false,
                ),
            ),
        ),
    );
}

$plain = tt_frozen_brief();
$lab = tt_frozen_lab();
$attached = TactiTwin_Frozen_Twin_Handoff::attach( $plain, $lab );

tt_frozen_assert( TactiTwin_Frozen_Twin_Handoff::verify_attachment( $attached ), 'fresh attachment verifies' );
tt_frozen_assert( 'frozen-tactical-twin-preparation' === $attached['tactical_twin_preparation']['attachment_id'], 'attachment id returned' );
tt_frozen_assert( 64 === strlen( $attached['tactical_twin_preparation']['content_sha256'] ), 'Twin digest has SHA-256 length' );
tt_frozen_assert( true === $attached['method']['tactical_twin_preparation_included'], 'brief method declares Twin preparation' );
tt_frozen_assert( false === $attached['method']['tactical_twin_recalculated_on_handoff'], 'brief method rejects handoff recalculation' );
tt_frozen_assert( $lab === TactiTwin_Frozen_Twin_Handoff::twin_lab( $attached ), 'exact Twin Lab payload can be recovered' );

$signature = TactiTwin_Frozen_Twin_Handoff::signature( $attached );
tt_frozen_assert( true === $signature['integrity_valid'], 'attachment signature exposes valid integrity' );
tt_frozen_assert( 'press-resistant-build-up' === $signature['top_response_id'], 'signature preserves baseline response' );
tt_frozen_assert( 'balanced-control' === $signature['most_robust_response_id'], 'signature preserves most robust response' );
tt_frozen_assert( 'transition-security' === $signature['switch_map']['protect-lead']['target_response_id'], 'signature preserves switch map target' );

$snapshot = TactiTwin_Staff_Brief_Snapshot::freeze(
    $attached,
    '11111111-2222-4333-8444-555555555555',
    7,
    '2026-10-12 10:00:00',
    5
);
tt_frozen_assert( TactiTwin_Staff_Brief_Snapshot::verify( $snapshot ), 'approved snapshot verifies with frozen Twin attached' );
$frozen_brief = TactiTwin_Staff_Brief_Snapshot::staff_brief( $snapshot );
tt_frozen_assert( TactiTwin_Frozen_Twin_Handoff::verify_attachment( $frozen_brief ), 'Twin attachment survives Staff Brief freeze' );
tt_frozen_assert( $signature === TactiTwin_Frozen_Twin_Handoff::signature( $frozen_brief ), 'frozen Twin signature is identical after snapshot freeze' );

$tampered = $snapshot;
$tampered['content']['staff_brief']['tactical_twin_preparation']['twin_lab']['preparation_summary']['top_response_id'] = 'tampered-response';
tt_frozen_assert( ! TactiTwin_Staff_Brief_Snapshot::verify( $tampered ), 'outer Staff Brief SHA detects frozen Twin tampering' );

$changed_brief = TactiTwin_Frozen_Twin_Handoff::attach( $plain, tt_frozen_lab( 'width-and-switch', 3.5 ) );
$diff = TactiTwin_Staff_Brief_Version_Diff::current_against_snapshot( $changed_brief, $snapshot, 'en' );
tt_frozen_assert( 'changed' === $diff['status'], 'changed Twin makes Staff Brief diff changed' );
tt_frozen_assert( in_array( 'tactical_twin', $diff['changed_sections'], true ), 'Twin change appears in changed sections' );
$found_twin_change = false;
foreach ( $diff['changes'] as $change ) {
    if ( 'tactical-twin-preparation-changed' === (string) ( $change['id'] ?? '' ) ) {
        $found_twin_change = true;
        break;
    }
}
tt_frozen_assert( $found_twin_change, 'Twin change has a stable structural change id' );
tt_frozen_assert( true === $diff['method']['tracks_tactical_twin_preparation'], 'version diff declares Twin tracking' );

$legacy_snapshot = TactiTwin_Staff_Brief_Snapshot::freeze(
    $plain,
    'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
    7,
    '2026-10-12 09:00:00',
    5
);
tt_frozen_assert( TactiTwin_Staff_Brief_Snapshot::verify( $legacy_snapshot ), 'legacy Staff Brief without Twin remains valid' );

$bad_matchup = $lab;
$bad_matchup['opponent']['source_id'] = '99';
$rejected = false;
try {
    TactiTwin_Frozen_Twin_Handoff::attach( $plain, $bad_matchup );
} catch ( InvalidArgumentException $e ) {
    $rejected = true;
}
tt_frozen_assert( $rejected, 'different matchup Twin is rejected' );

echo "Frozen Twin handoff tests passed.\n";
