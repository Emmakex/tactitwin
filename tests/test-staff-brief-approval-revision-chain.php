<?php

define( 'ABSPATH', __DIR__ );
define( 'TACTITWIN_VERSION', '0.1.0-dev' );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-staff-brief-snapshot.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-staff-brief-version-diff.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-staff-brief-revision-chain.php';

function tt_v119_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_v119_brief( string $variant = 'base' ): array {
    $team = array( 'source_id'=>'100', 'name'=>'Our Team' );
    $opponent = array( 'source_id'=>'200', 'name'=>'Opponent' );
    $changed = 'changed' === $variant;
    $match_plan = array( 'plan_id'=>'match-plan', 'team'=>$team, 'opponent'=>$opponent );
    $playbook = array( 'playbook_id'=>'contingency-playbook', 'team'=>$team, 'opponent'=>$opponent );

    return array(
        'staff_brief_id' => 'match-day-staff-brief',
        'language' => 'en',
        'team' => $team,
        'opponent' => $opponent,
        'headline' => $changed ? 'Changed headline' : 'Prepared headline',
        'coverage' => array( 'effective_matches'=>$changed ? 6 : 5, 'status'=>'high' ),
        'top_priorities' => array(
            array( 'rank'=>1, 'scenario_id'=>'press', 'scenario_name'=>'Press', 'priority_score'=>$changed ? 86.0 : 82.0, 'instruction'=>$changed ? 'Use alternate buildup.' : 'Protect buildup.' ),
            array( 'rank'=>2, 'scenario_id'=>'transition', 'scenario_name'=>'Transition', 'priority_score'=>76.0, 'instruction'=>'Protect rest defence.' ),
        ),
        'plans' => array(
            'plan_a' => array( 'id'=>'A', 'status'=>'available', 'mode'=>'baseline', 'instructions'=>array('A1') ),
            'plan_b' => array(
                'id'=>'B',
                'status'=>$changed ? 'armed' : 'dormant',
                'mode'=>$changed ? 'press_escape' : 'observe',
                'scenario_id'=>'build-up-vs-high-press',
                'risk_score'=>$changed ? 84.0 : 78.0,
                'trigger'=>$changed ? 'Confirmed high press.' : 'Observe press.',
                'activation_rule'=>'Manual staff confirmation.',
                'instructions'=>array($changed ? 'B2' : 'B1'),
                'evidence'=>array(
                    'primary'=>array('group_key'=>$changed ? 'new-primary' : 'old-primary'),
                    'alternative'=>array('group_key'=>'alt'),
                    'conflict_signals'=>array('highest_severity'=>'none','signals'=>array()),
                ),
            ),
            'plan_c' => array( 'id'=>'C', 'status'=>'standby', 'mode'=>'standby', 'instructions'=>array('C1') ),
        ),
        'phase_checks' => array(
            'opening_15_minutes'=>array($changed ? 'Check press exits.' : 'Check press height.'),
            'halftime_checks'=>array('Check transitions.'),
        ),
        'decision_tree' => array(
            array('order'=>1,'condition'=>$changed ? 'Press confirmed' : 'Context changes','target_plan'=>$changed ? 'B' : 'C','mode'=>$changed ? 'press_escape' : 'standby'),
        ),
        'evidence_watch' => array( 'material_signal_count'=>0, 'material_signals'=>array() ),
        'handoff_source' => array( 'match_plan'=>$match_plan, 'playbook'=>$playbook ),
    );
}

$base = tt_v119_brief();
$first_revision = TactiTwin_Staff_Brief_Revision_Chain::build( $base, array(), 1, 'en', 'Initial approval.' );
tt_v119_assert( 1 === $first_revision['revision_number'], 'first approval is revision 1' );
tt_v119_assert( null === $first_revision['parent_snapshot_uuid'], 'first approval has no parent snapshot' );
tt_v119_assert( null === $first_revision['approved_content_sha256'], 'prospective revision is not content-bound before snapshot freeze' );
tt_v119_assert( 0 === $first_revision['reviewed_change_count'], 'first approval has no predecessor diff' );
tt_v119_assert( false === $first_revision['requires_change_acknowledgement'], 'first approval needs no change acknowledgement' );
tt_v119_assert( true === TactiTwin_Staff_Brief_Revision_Chain::verify( $first_revision ), 'fresh revision metadata verifies' );

$first_snapshot = TactiTwin_Staff_Brief_Snapshot::freeze(
    $base,
    '11111111-1111-4111-8111-111111111111',
    7,
    '2026-10-11 14:00:00',
    5,
    $first_revision
);
tt_v119_assert( true === TactiTwin_Staff_Brief_Snapshot::verify( $first_snapshot ), 'snapshot verifies with sealed revision metadata' );
tt_v119_assert( 1 === TactiTwin_Staff_Brief_Snapshot::revision( $first_snapshot )['revision_number'], 'snapshot exposes its approval revision' );
tt_v119_assert( $first_snapshot['content_sha256'] === $first_snapshot['revision']['approved_content_sha256'], 'revision is cryptographically bound to exact approved snapshot content' );
tt_v119_assert( true === TactiTwin_Staff_Brief_Revision_Chain::verify( $first_snapshot['revision'] ), 'content binding re-seals the revision digest' );

$changed = tt_v119_brief( 'changed' );
$second_revision = TactiTwin_Staff_Brief_Revision_Chain::build( $changed, $first_snapshot, 2, 'en', 'Updated after opponent review.' );
tt_v119_assert( 2 === $second_revision['revision_number'], 'second approval increments revision number' );
tt_v119_assert( $first_snapshot['snapshot_uuid'] === $second_revision['parent_snapshot_uuid'], 'second approval points to reviewed parent snapshot' );
tt_v119_assert( $first_snapshot['content_sha256'] === $second_revision['parent_content_sha256'], 'parent content digest is preserved' );
tt_v119_assert( $second_revision['reviewed_change_count'] > 0, 'changed brief preserves reviewed structural changes' );
tt_v119_assert( 64 === strlen( (string) $second_revision['reviewed_diff_sha256'] ), 'reviewed diff has deterministic SHA-256 digest' );
tt_v119_assert( true === $second_revision['requires_change_acknowledgement'], 'materially changed revision requires explicit acknowledgement' );
tt_v119_assert( 'Updated after opponent review.' === $second_revision['approval_note'], 'approval note is preserved in revision record' );
tt_v119_assert( in_array( 'headline-changed', $second_revision['reviewed_change_ids'], true ), 'reviewed change IDs preserve the version diff' );
tt_v119_assert( true === TactiTwin_Staff_Brief_Revision_Chain::verify( $second_revision ), 'second revision metadata verifies' );

$second_es = TactiTwin_Staff_Brief_Revision_Chain::build( $changed, $first_snapshot, 2, 'es', 'Updated after opponent review.' );
tt_v119_assert( $second_revision['reviewed_diff_sha256'] === $second_es['reviewed_diff_sha256'], 'ES/EN produces the same machine diff digest' );
tt_v119_assert( $second_revision['reviewed_change_ids'] === $second_es['reviewed_change_ids'], 'ES/EN preserves reviewed change IDs' );

$unchanged_revision = TactiTwin_Staff_Brief_Revision_Chain::build( $base, $first_snapshot, 2, 'en' );
tt_v119_assert( 0 === $unchanged_revision['reviewed_change_count'], 'unchanged brief records zero reviewed changes' );
tt_v119_assert( false === $unchanged_revision['requires_change_acknowledgement'], 'unchanged revision needs no acknowledgement' );
tt_v119_assert( 64 === strlen( (string) $unchanged_revision['reviewed_diff_sha256'] ), 'unchanged predecessor comparison is still digestible/auditable' );

$second_snapshot = TactiTwin_Staff_Brief_Snapshot::freeze(
    $changed,
    '22222222-2222-4222-8222-222222222222',
    7,
    '2026-10-11 14:10:00',
    5,
    $second_revision
);
tt_v119_assert( true === TactiTwin_Staff_Brief_Snapshot::verify( $second_snapshot ), 'changed revision snapshot verifies' );
tt_v119_assert( $second_snapshot['content_sha256'] === $second_snapshot['revision']['approved_content_sha256'], 'second revision binds to second snapshot content' );

$tampered = $second_snapshot;
$tampered['revision']['reviewed_change_count']++;
tt_v119_assert( false === TactiTwin_Staff_Brief_Revision_Chain::verify( $tampered['revision'] ), 'revision tampering invalidates revision digest' );
tt_v119_assert( false === TactiTwin_Staff_Brief_Snapshot::verify( $tampered ), 'revision tampering invalidates the whole v1.19 snapshot' );

$swapped = $second_snapshot;
$swapped['revision'] = $first_snapshot['revision'];
tt_v119_assert( true === TactiTwin_Staff_Brief_Revision_Chain::verify( $swapped['revision'] ), 'swapped revision is independently valid' );
tt_v119_assert( false === TactiTwin_Staff_Brief_Snapshot::verify( $swapped ), 'valid revision from another snapshot cannot be swapped into this snapshot' );

$wrong_binding = $second_snapshot;
$wrong_binding['revision']['approved_content_sha256'] = str_repeat( 'a', 64 );
$wrong_binding['revision'] = TactiTwin_Staff_Brief_Revision_Chain::bind_to_content( $second_revision, str_repeat( 'a', 64 ) );
tt_v119_assert( true === TactiTwin_Staff_Brief_Revision_Chain::verify( $wrong_binding['revision'] ), 'revision can be internally valid while bound to different content' );
tt_v119_assert( false === TactiTwin_Staff_Brief_Snapshot::verify( $wrong_binding ), 'snapshot rejects revision bound to a different content digest' );

$legacy_snapshot = TactiTwin_Staff_Brief_Snapshot::freeze(
    $base,
    '33333333-3333-4333-8333-333333333333',
    7,
    '2026-10-11 13:50:00',
    5
);
tt_v119_assert( true === TactiTwin_Staff_Brief_Snapshot::verify( $legacy_snapshot ), 'pre-v1.19 snapshots without revision metadata remain valid' );

echo "Staff Brief approval revision chain tests passed.\n";
