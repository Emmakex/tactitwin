<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-tactical-response-matrix.php';

function tt_response_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_response_scenario(
    string $id,
    string $name,
    float $risk,
    float $opportunity,
    string $primary
): array {
    return array(
        'scenario_id'   => $id,
        'scenario_name' => $name,
        'risk'          => array( 'score' => $risk ),
        'opportunity'   => array( 'score' => $opportunity ),
        'recommendations' => array( 'primary' => $primary ),
    );
}

$scan = array(
    'scenario_id'   => 'full-matchup-scan',
    'scenario_name' => 'Full Matchup Scan',
    'team'          => array( 'source_id' => '10', 'name' => 'Our Team' ),
    'opponent'      => array( 'source_id' => '20', 'name' => 'Opponent' ),
    'coverage'      => array( 'effective_matches' => 5, 'status' => 'high', 'warning' => null ),
    'subscenarios'  => array(
        tt_response_scenario( 'build-up-vs-high-press', 'Build-up vs High Press', 80.0, 50.0, 'Escape the first press' ),
        tt_response_scenario( 'opponent-side-bias', 'Opponent Side Bias', 60.0, 35.0, 'Protect the dominant side' ),
        tt_response_scenario( 'defensive-transition-after-loss', 'Defensive Transition After Loss', 70.0, 30.0, 'Protect the first pass after loss' ),
        tt_response_scenario( 'attack-vs-low-block', 'Attack vs Low Block', 45.0, 55.0, 'Move the low block before penetrating' ),
        tt_response_scenario( 'exploit-opponent-weak-side', 'Exploit Opponent Weak Side', 30.0, 90.0, 'Attack the weak-side channel' ),
    ),
);

$original = $scan;
$en = TactiTwin_Tactical_Response_Matrix::compose( $scan, 'en' );
$es = TactiTwin_Tactical_Response_Matrix::compose( $scan, 'es' );

tt_response_assert( 'tactical-response-matrix' === $en['matrix_id'], 'matrix id returned' );
tt_response_assert( 'Tactical Response Matrix' === $en['matrix_name'], 'English matrix name returned' );
tt_response_assert( 'Matriz de respuestas tácticas' === $es['matrix_name'], 'Spanish matrix name returned' );
tt_response_assert( 4 === count( $en['responses'] ), 'four deterministic response packages returned' );
tt_response_assert( 'press-resistant-build-up' === $en['top_response_id'], 'press-resistant response is top fit for sample matchup' );
tt_response_assert( 59.4 === $en['responses'][0]['fit_score'], 'top fit score follows declared formula' );
tt_response_assert( 1 === $en['responses'][0]['rank'], 'response ranking starts at one' );
tt_response_assert( true === $en['responses'][0]['is_top_fit'], 'top response flagged explicitly' );
tt_response_assert( 0.0 === $en['responses'][0]['gap_to_top'], 'top response has zero gap to top' );
tt_response_assert( ! empty( $en['responses'][0]['instructions'] ), 'top response carries existing scenario instructions' );
tt_response_assert( false === $en['responses'][0]['guardrails']['outcome_prediction'], 'response explicitly rejects outcome prediction' );
tt_response_assert( false === $en['responses'][0]['guardrails']['changes_tactical_scores'], 'response does not change tactical scores' );
tt_response_assert( true === $en['method']['not_a_probability'], 'method declares fit is not a probability' );
tt_response_assert( true === $en['method']['not_a_scoreline_prediction'], 'method declares no scoreline prediction' );
tt_response_assert( 'deterministic-tactical-response-comparison' === $en['method']['type'], 'deterministic comparison method declared' );
tt_response_assert( $scan === $original, 'matrix composition does not mutate source scan' );

$en_scores = array();
foreach ( $en['responses'] as $response ) {
    $en_scores[ $response['response_id'] ] = $response['fit_score'];
}
$es_scores = array();
foreach ( $es['responses'] as $response ) {
    $es_scores[ $response['response_id'] ] = $response['fit_score'];
}
tt_response_assert( $en_scores === $es_scores, 'response fit scores are language-neutral' );
tt_response_assert( $en['top_response_id'] === $es['top_response_id'], 'top response is language-neutral' );

$width = null;
foreach ( $en['responses'] as $response ) {
    if ( 'width-and-switch' === $response['response_id'] ) {
        $width = $response;
        break;
    }
}
tt_response_assert( is_array( $width ), 'width-and-switch response exists' );
tt_response_assert( ! empty( $width['trade_offs'] ), 'high-priority underweighted scenario is exposed as trade-off' );
tt_response_assert( 'build-up-vs-high-press' === $width['trade_offs'][0]['scenario_id'], 'trade-off identifies underweighted high-press problem' );

$invalid = false;
try {
    TactiTwin_Tactical_Response_Matrix::compose( array( 'scenario_id' => 'other' ), 'en' );
} catch ( InvalidArgumentException $e ) {
    $invalid = true;
}
tt_response_assert( $invalid, 'invalid scan rejected' );

$missing = $scan;
array_pop( $missing['subscenarios'] );
$missing_rejected = false;
try {
    TactiTwin_Tactical_Response_Matrix::compose( $missing, 'en' );
} catch ( InvalidArgumentException $e ) {
    $missing_rejected = true;
}
tt_response_assert( $missing_rejected, 'missing required scenario rejected' );

echo "Tactical response matrix tests passed.\n";
