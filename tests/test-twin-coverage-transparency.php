<?php

require_once __DIR__ . '/test-tactical-twin-lab.php';

$coverage_scan = $scan;
$coverage_scan['coverage'] = array(
    'team_matches' => 5,
    'opponent_matches' => 1,
    'effective_matches' => 1,
    'status' => 'low',
    'limiting_side' => 'opponent',
    'asymmetry_matches' => 4,
    'is_asymmetric' => true,
    'warning' => 'Low evidence coverage',
    'team_sample' => array(
        'team_source_id' => '10',
        'team_name' => 'Our Team',
        'matches' => 5,
        'match_ids' => array( '1', '2', '3', '4', '5' ),
        'oldest_match' => '2024-04-01',
        'newest_match' => '2024-05-01',
        'confidence' => 'high',
    ),
    'opponent_sample' => array(
        'team_source_id' => '20',
        'team_name' => 'Opponent',
        'matches' => 1,
        'match_ids' => array( '9' ),
        'oldest_match' => '2024-01-01',
        'newest_match' => '2024-01-01',
        'confidence' => 'low',
    ),
    'method' => array(
        'effective_matches_formula' => 'min(team_matches, opponent_matches)',
    ),
);

$coverage_lab = TactiTwin_Tactical_Twin_Lab::compose( $team, $opponent, $coverage_scan, 'en' );

tt_twin_assert( 5 === $coverage_lab['coverage']['team_matches'], 'Twin Lab preserves our-team sample count' );
tt_twin_assert( 1 === $coverage_lab['coverage']['opponent_matches'], 'Twin Lab preserves opponent sample count' );
tt_twin_assert( 'opponent' === $coverage_lab['coverage']['limiting_side'], 'Twin Lab preserves limiting side' );
tt_twin_assert( array( '9' ) === $coverage_lab['coverage']['opponent_sample']['match_ids'], 'Twin Lab preserves exact opponent sample IDs' );
tt_twin_assert( $coverage_lab['coverage'] === $coverage_lab['response_matrix']['coverage'], 'Twin Lab and response matrix expose the same coverage contract' );
tt_twin_assert( 'v1.35-per-side-auditable-samples' === $coverage_lab['method']['coverage_transparency'], 'Twin Lab declares v1.35 coverage transparency' );
tt_twin_assert( $coverage_scan['coverage'] === $coverage_lab['coverage'], 'Coverage propagation does not rewrite source coverage' );

echo "Twin coverage transparency tests passed.\n";
