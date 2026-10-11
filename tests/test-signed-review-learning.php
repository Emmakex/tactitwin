<?php

define( 'ABSPATH', __DIR__ );

if ( ! class_exists( 'TactiTwin_I18n' ) ) {
    final class TactiTwin_I18n {
        public static function normalize_language( string $language ): string {
            return 'es' === strtolower( $language ) ? 'es' : 'en';
        }
    }
}

require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-intervention-library-service.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-historical-precedent-retrieval.php';

function tt_v126_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

tt_v126_assert( 'all' === TactiTwin_Intervention_Library_Service::normalize_learning_scope( '' ), 'empty learning scope defaults to all' );
tt_v126_assert( 'all' === TactiTwin_Intervention_Library_Service::normalize_learning_scope( 'anything' ), 'unknown learning scope defaults to all' );
tt_v126_assert( 'signed' === TactiTwin_Intervention_Library_Service::normalize_learning_scope( 'SIGNED' ), 'signed learning scope is normalized deterministically' );

$signed_snapshot = array(
    'review_uuid' => 'review-1',
    'session_uuid' => 'session-a',
    'revision_number' => 2,
    'signed_at' => '2026-10-11 17:00:00',
    'signed_by' => 7,
    'content_sha256' => str_repeat( 'a', 64 ),
);
$marker = TactiTwin_Intervention_Library_Service::review_marker( $signed_snapshot, 'session-a' );
tt_v126_assert( 'signed' === $marker['status'], 'valid signed snapshot creates signed learning marker' );
tt_v126_assert( 2 === $marker['revision_number'], 'learning marker preserves signed revision number' );
tt_v126_assert( 'review-1' === $marker['review_uuid'], 'learning marker preserves review UUID' );
$wrong_marker = TactiTwin_Intervention_Library_Service::review_marker( $signed_snapshot, 'session-b' );
tt_v126_assert( 'invalid' === $wrong_marker['status'], 'cross-session signed snapshot is invalid provenance' );

$library = array(
    'library_id' => 'context-matched-intervention-library',
    'groups' => array(
        array(
            'key' => 'press|B|aggressive|31-60|drawing|equal',
            'dimensions' => array(
                'scenario_id' => 'press',
                'to_plan' => 'B',
                'to_mode' => 'aggressive',
                'minute_band' => '31-60',
                'score_state' => 'drawing',
                'manpower_state' => 'equal',
            ),
            'sample' => array(
                'maturity' => 'emerging',
                'maturity_label' => 'Emerging sample',
                'cleaner' => 2,
                'context_comparable' => 2,
                'signal_basis' => 'cleaner',
            ),
            'metrics' => array(),
            'cases' => array(
                array( 'session_uuid'=>'session-a', 'source_match_id'=>'m1' ),
                array( 'session_uuid'=>'session-b', 'source_match_id'=>'m2' ),
            ),
        ),
        array(
            'key' => 'weak-side|B|wide|31-60|drawing|equal',
            'dimensions' => array(
                'scenario_id' => 'weak-side',
                'to_plan' => 'B',
                'to_mode' => 'wide',
                'minute_band' => '31-60',
                'score_state' => 'drawing',
                'manpower_state' => 'equal',
            ),
            'sample' => array(
                'maturity' => 'emerging',
                'maturity_label' => 'Emerging sample',
                'cleaner' => 2,
                'context_comparable' => 2,
                'signal_basis' => 'cleaner',
            ),
            'metrics' => array(),
            'cases' => array(
                array( 'session_uuid'=>'session-a', 'source_match_id'=>'m1' ),
                array( 'session_uuid'=>'session-c', 'source_match_id'=>'m3' ),
            ),
        ),
    ),
);

$markers = array(
    'session-a' => $marker,
    'session-b' => array( 'status'=>'unsigned', 'review_uuid'=>'', 'revision_number'=>0, 'signed_at'=>'', 'signed_by'=>0, 'content_sha256'=>'' ),
    'session-c' => array( 'status'=>'signed', 'review_uuid'=>'review-3', 'revision_number'=>1, 'signed_at'=>'2026-10-10 17:00:00', 'signed_by'=>8, 'content_sha256'=>str_repeat('b',64) ),
);
$annotated = TactiTwin_Intervention_Library_Service::attach_signed_review_provenance( $library, $markers, 'all' );
tt_v126_assert( 'mixed' === $annotated['groups'][0]['learning_review']['status'], 'mixed group exposes signed and unsigned cases' );
tt_v126_assert( 1 === $annotated['groups'][0]['learning_review']['signed_cases'], 'mixed group counts signed cases' );
tt_v126_assert( 1 === $annotated['groups'][0]['learning_review']['unsigned_cases'], 'mixed group counts unsigned cases' );
tt_v126_assert( 'all-signed' === $annotated['groups'][1]['learning_review']['status'], 'all-signed group is distinguished from mixed group' );
tt_v126_assert( 2 === $annotated['learning_review']['signed_sessions'], 'library coverage counts distinct signed sessions' );
tt_v126_assert( true === $annotated['learning_review']['signed_review_is_not_tactical_effectiveness'], 'library explicitly rejects effectiveness interpretation' );

$signed_scope = TactiTwin_Intervention_Library_Service::attach_signed_review_provenance( $library, $markers, 'signed' );
tt_v126_assert( true === $signed_scope['groups'][0]['learning_review']['affects_metrics'], 'signed-only scope declares that the selected source population affects aggregates' );
tt_v126_assert( false === $signed_scope['groups'][0]['learning_review']['affects_ranking'], 'signed-review provenance never claims to alter precedent ranking' );

$scan = array(
    'evidence' => array(
        'scenario_ranking' => array(
            array( 'scenario_id'=>'press', 'scenario_name'=>'Press', 'risk_score'=>80, 'opportunity_score'=>30, 'rank'=>1 ),
            array( 'scenario_id'=>'weak-side', 'scenario_name'=>'Weak Side', 'risk_score'=>70, 'opportunity_score'=>40, 'rank'=>2 ),
        ),
    ),
);
$hypothesis = array(
    'minute'=>45,
    'our_score'=>0,
    'opponent_score'=>0,
    'our_players'=>11,
    'opponent_players'=>11,
);

$without_provenance = TactiTwin_Historical_Precedent_Retrieval::retrieve( $library, $scan, $hypothesis, 'en', 5 );
$with_provenance = TactiTwin_Historical_Precedent_Retrieval::retrieve( $annotated, $scan, $hypothesis, 'en', 5 );

tt_v126_assert( count( $with_provenance['items'] ) === 2, 'exact-context retrieval still returns both eligible groups' );
tt_v126_assert( $without_provenance['items'][0]['group_key'] === $with_provenance['items'][0]['group_key'], 'signed provenance does not change default precedent order' );
tt_v126_assert( $without_provenance['items'][0]['retrieval_score'] === $with_provenance['items'][0]['retrieval_score'], 'signed provenance does not change retrieval score' );
tt_v126_assert( 'mixed' === $with_provenance['items'][0]['learning_review']['status'], 'retrieved precedent exposes signed-review coverage' );
tt_v126_assert( false === $with_provenance['method']['signed_review_affects_retrieval_score'], 'retrieval method explicitly says sign-off does not affect score' );
tt_v126_assert( false === $with_provenance['learning_review_coverage']['affects_retrieval_score'], 'returned coverage explicitly remains descriptive' );

echo "Signed review learning tests passed.\n";
