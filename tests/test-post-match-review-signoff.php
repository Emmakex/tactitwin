<?php

define( 'ABSPATH', __DIR__ );

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $value, int $flags = 0 ) {
        return json_encode( $value, $flags );
    }
}

require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-post-match-review-snapshot.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-fixture-lifecycle-service.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-db.php';

function tt_v125_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$fixture_key = hash( 'sha256', 'fixture-v1.25' );
$review = array(
    'review_id' => 'post-match-review',
    'language' => 'es',
    'session' => array(
        'session_uuid' => 'session-v125',
        'status' => 'closed',
        'title' => 'TactiTwin FC vs Rival FC',
        'source_match_id' => '9001',
        'team_source_id' => '100',
        'opponent_source_id' => '200',
        'started_at' => '2026-10-11 18:00:00',
        'closed_at' => '2026-10-11 19:45:00',
    ),
    'final_context' => array(
        'minute' => 94,
        'our_score' => 2,
        'opponent_score' => 1,
        'our_players' => 11,
        'opponent_players' => 11,
        'active_plan' => 'B',
        'active_mode' => 'press-adjustment',
    ),
    'prepared' => array( 'primary_plan_b' => array( 'scenario_id' => 'high-press' ) ),
    'observed' => array( 'unique_scenarios' => array( 'high-press' ) ),
    'activated' => array( 'transition_count' => 1 ),
    'alignment' => array( 'status' => 'prepared-and-activated' ),
    'sampling' => array( 'status' => 'good', 'snapshot_count' => 6 ),
    'summary' => array( 'Plan B was prepared and activated.' ),
    'objective_evidence' => array(
        'status' => 'available',
        'source_match_id' => '9001',
        'assessment' => 'supported',
    ),
    'segment_evidence' => array( 'status' => 'available' ),
    'transition_window_deltas' => array( 'status' => 'available' ),
    'context_aware_transition_review' => array( 'status' => 'available' ),
);
$binding = array(
    'fixture_key' => $fixture_key,
    'session_uuid' => 'session-v125',
    'staff_brief_snapshot_uuid' => 'staff-v125',
    'source_match_id' => '9001',
);

$sha_a = TactiTwin_Post_Match_Review_Snapshot::review_sha256( $review );
$review_reordered = array_reverse( $review, true );
$sha_b = TactiTwin_Post_Match_Review_Snapshot::review_sha256( $review_reordered );
tt_v125_assert( $sha_a === $sha_b, 'review hash is deterministic across associative key order' );

$first = TactiTwin_Post_Match_Review_Snapshot::freeze(
    $review,
    $binding,
    'review-v125-r1',
    1,
    42,
    '2026-10-11 20:00:00',
    'Reviewed with coaching staff.'
);
tt_v125_assert( TactiTwin_Post_Match_Review_Snapshot::verify( $first ), 'first signed review snapshot verifies' );
tt_v125_assert( 1 === $first['revision_number'], 'first sign-off is revision 1' );
tt_v125_assert( '' === $first['parent_review_uuid'], 'first sign-off has no parent' );
tt_v125_assert( $sha_a === $first['review_sha256'], 'snapshot binds exact displayed review hash' );
tt_v125_assert( '9001' === $first['source_match_id'], 'snapshot binds exact provider match' );
tt_v125_assert( true === $first['method']['application_signoff_not_digital_signature'], 'method explicitly avoids claiming a cryptographic signature' );

$second = TactiTwin_Post_Match_Review_Snapshot::freeze(
    $review,
    $binding,
    'review-v125-r2',
    2,
    43,
    '2026-10-11 20:15:00',
    'Second staff note.',
    array( 'review_uuid' => $first['review_uuid'], 'content_sha256' => $first['content_sha256'] )
);
tt_v125_assert( TactiTwin_Post_Match_Review_Snapshot::verify( $second ), 'second signed review snapshot verifies' );
tt_v125_assert( $first['review_uuid'] === $second['parent_review_uuid'], 'revision 2 binds exact parent UUID' );
tt_v125_assert( $first['content_sha256'] === $second['parent_content_sha256'], 'revision 2 binds exact parent content hash' );

$tampered = $first;
$tampered['content']['review']['final_context']['our_score'] = 3;
tt_v125_assert( ! TactiTwin_Post_Match_Review_Snapshot::verify( $tampered ), 'tampered review content fails integrity verification' );

$changed_review = $review;
$changed_review['summary'][] = 'New evidence arrived.';
tt_v125_assert( $sha_a !== TactiTwin_Post_Match_Review_Snapshot::review_sha256( $changed_review ), 'review changes produce a different stale-review guard hash' );

$open_review = $review;
$open_review['session']['status'] = 'active';
$thrown = false;
try {
    TactiTwin_Post_Match_Review_Snapshot::freeze( $open_review, $binding, 'open-review', 1, 42, '2026-10-11 20:00:00' );
} catch ( RuntimeException $e ) {
    $thrown = true;
}
tt_v125_assert( $thrown, 'open Live Session cannot be signed off' );

$no_evidence = $review;
$no_evidence['objective_evidence']['status'] = 'unavailable';
$thrown = false;
try {
    TactiTwin_Post_Match_Review_Snapshot::freeze( $no_evidence, $binding, 'no-evidence', 1, 42, '2026-10-11 20:00:00' );
} catch ( RuntimeException $e ) {
    $thrown = true;
}
tt_v125_assert( $thrown, 'sign-off requires objective match evidence' );

$wrong_match = $binding;
$wrong_match['source_match_id'] = '9999';
$thrown = false;
try {
    TactiTwin_Post_Match_Review_Snapshot::freeze( $review, $wrong_match, 'wrong-match', 1, 42, '2026-10-11 20:00:00' );
} catch ( InvalidArgumentException $e ) {
    $thrown = true;
}
tt_v125_assert( $thrown, 'cross-fixture evidence cannot be signed off' );

$lifecycle = TactiTwin_Fixture_Lifecycle_Service::derive(
    array(
        'context_selected' => true,
        'fixture_key' => $fixture_key,
        'team_history_matches' => 5,
        'opponent_history_matches' => 5,
        'approved_snapshot' => array( 'snapshot_uuid' => 'staff-v125', 'approved_at' => '2026-10-11 17:00:00' ),
        'session' => array( 'session_uuid' => 'session-v125', 'status' => 'closed', 'started_at' => '2026-10-11 18:00:00', 'closed_at' => '2026-10-11 19:45:00' ),
        'fixture_source_match_id' => '9001',
        'session_source_match_id' => '9001',
        'evidence_event_count' => 1200,
        'signed_review' => $first,
    )
);
tt_v125_assert( 'signed_off' === $lifecycle['stages']['post_match_review']['state'], 'lifecycle closes with persistent signed review' );
tt_v125_assert( true === $lifecycle['stages']['post_match_review']['persistent'], 'signed review is persistent lifecycle evidence' );
tt_v125_assert( 'post_match_review_snapshot' === $lifecycle['stages']['post_match_review']['provenance'], 'lifecycle exposes signed snapshot provenance' );
tt_v125_assert( 'learn_from_match' === $lifecycle['next_action']['id'], 'signed review advances next action to historical learning' );
tt_v125_assert( 'fixture-lifecycle-v1.25' === $lifecycle['method']['name'], 'lifecycle method version advances to v1.25' );

tt_v125_assert( '0.5.0' === TactiTwin_DB::SCHEMA_VERSION, 'v1.25 advances schema to 0.5.0' );

echo "Post-match review sign-off tests passed.\n";
