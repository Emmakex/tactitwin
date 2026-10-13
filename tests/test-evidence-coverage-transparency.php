<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-evidence-coverage.php';

function tt_coverage_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$team = array(
    'team_source_id' => '904',
    'team_name' => 'Bayer Leverkusen',
    'coverage' => array(
        'matches' => 5,
        'match_ids' => array( '3895338', '3895328', '3895318', '3895308', '3895298' ),
        'oldest_match' => '2024-04-21',
        'newest_match' => '2024-05-12',
        'confidence' => 'high',
    ),
);
$opponent = array(
    'team_source_id' => '172',
    'team_name' => 'Augsburg',
    'coverage' => array(
        'matches' => 1,
        'match_ids' => array( '3895220' ),
        'oldest_match' => '2024-01-13',
        'newest_match' => '2024-01-13',
        'confidence' => 'low',
    ),
);

$team_before = $team;
$opponent_before = $opponent;
$es = TactiTwin_Evidence_Coverage::compose( $team, $opponent, 'es' );
$en = TactiTwin_Evidence_Coverage::compose( $team, $opponent, 'en' );

tt_coverage_assert( 5 === $es['team_matches'], 'team sample count exposed' );
tt_coverage_assert( 1 === $es['opponent_matches'], 'opponent sample count exposed' );
tt_coverage_assert( 1 === $es['effective_matches'], 'effective coverage uses weaker sample' );
tt_coverage_assert( 'opponent' === $es['limiting_side'], 'opponent identified as limiting side' );
tt_coverage_assert( 4 === $es['asymmetry_matches'], 'coverage asymmetry exposed' );
tt_coverage_assert( true === $es['is_asymmetric'], 'asymmetry flag exposed' );
tt_coverage_assert( '2024-04-21' === $es['team_sample']['oldest_match'], 'team date window retained' );
tt_coverage_assert( '2024-05-12' === $es['team_sample']['newest_match'], 'team newest date retained' );
tt_coverage_assert( array( '3895220' ) === $es['opponent_sample']['match_ids'], 'exact opponent match IDs retained' );
tt_coverage_assert( 'low' === $es['status'], 'effective coverage status remains conservative' );
tt_coverage_assert( ! empty( $es['warning'] ), 'low coverage produces explicit warning' );
tt_coverage_assert( false !== strpos( $es['warning'], '5' ) && false !== strpos( $es['warning'], '1' ), 'warning exposes both sample counts' );
tt_coverage_assert( $es['team_matches'] === $en['team_matches'], 'ES/EN team count invariant' );
tt_coverage_assert( $es['opponent_matches'] === $en['opponent_matches'], 'ES/EN opponent count invariant' );
tt_coverage_assert( $es['effective_matches'] === $en['effective_matches'], 'ES/EN effective count invariant' );
tt_coverage_assert( $es['limiting_side'] === $en['limiting_side'], 'ES/EN limiting side invariant' );
tt_coverage_assert( $team_before === $team && $opponent_before === $opponent, 'coverage composition does not mutate profiles' );

$balanced = $opponent;
$balanced['coverage'] = $team['coverage'];
$balanced_result = TactiTwin_Evidence_Coverage::compose( $team, $balanced, 'es' );
tt_coverage_assert( 5 === $balanced_result['effective_matches'], 'balanced full samples retain five effective matches' );
tt_coverage_assert( 'balanced' === $balanced_result['limiting_side'], 'equal samples are marked balanced' );
tt_coverage_assert( false === $balanced_result['is_asymmetric'], 'equal samples are not asymmetric' );
tt_coverage_assert( null === $balanced_result['warning'], 'high balanced coverage has no low-coverage warning' );

echo "Evidence coverage transparency tests passed.\n";
