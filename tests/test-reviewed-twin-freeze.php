<?php

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

final class TactiTwin_I18n {
    public static function normalize_language( string $language ): string {
        return 'es' === strtolower( trim( $language ) ) ? 'es' : 'en';
    }
}

require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-reviewed-twin-freeze-bridge.php';

function tt_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, $message . "\n" );
        exit( 1 );
    }
}

$lab = array(
    'lab_id' => 'tactical-twin-lab',
    'language' => 'es',
    'team' => array( 'source_id' => '904', 'name' => 'Bayer Leverkusen' ),
    'opponent' => array( 'source_id' => '172', 'name' => 'Augsburg' ),
    'response_matrix' => array( 'responses' => array( array( 'response_id' => 'press-resistant-build-up' ) ) ),
    'scenario_tree' => array( 'branches' => array( array( 'branch_id' => 'opening-neutral' ) ) ),
    'response_robustness' => array( 'responses' => array( array( 'response_id' => 'balanced-control' ) ) ),
    'response_switch_playbook' => array( 'playbook_id' => 'response-switch-playbook', 'items' => array() ),
);

$result = array(
    'lab' => $lab,
    'language' => 'es',
    'preparation' => array(
        'team' => array( 'target_matches' => 5 ),
        'opponent' => array( 'target_matches' => 5 ),
    ),
    'context' => array(
        'fixture_key' => 'fixture-3895348',
        'team_source_id' => '904',
        'opponent_source_id' => '172',
    ),
);

$current_context = array(
    'fixture_key' => 'fixture-3895348',
    'team_source_id' => '904',
    'opponent_source_id' => '172',
);

$before = hash( 'sha256', json_encode( $result ) );
$compatible = TactiTwin_Reviewed_Twin_Freeze_Bridge::compatible_reviewed_lab(
    $result,
    904,
    172,
    5,
    'es',
    $current_context
);

tt_assert( $compatible === $lab, 'Exact reviewed Twin Lab should be reused unchanged.' );
tt_assert( $before === hash( 'sha256', json_encode( $result ) ), 'Compatibility check must not mutate reviewed result.' );

tt_assert(
    array() === TactiTwin_Reviewed_Twin_Freeze_Bridge::compatible_reviewed_lab( $result, 904, 172, 4, 'es', $current_context ),
    'Different requested sample must reject reviewed Twin.'
);

tt_assert(
    array() === TactiTwin_Reviewed_Twin_Freeze_Bridge::compatible_reviewed_lab( $result, 904, 172, 5, 'en', $current_context ),
    'Different language must reject reviewed Twin.'
);

$other_fixture = $current_context;
$other_fixture['fixture_key'] = 'fixture-other';
tt_assert(
    array() === TactiTwin_Reviewed_Twin_Freeze_Bridge::compatible_reviewed_lab( $result, 904, 172, 5, 'es', $other_fixture ),
    'Different active fixture must reject reviewed Twin.'
);

tt_assert(
    array() === TactiTwin_Reviewed_Twin_Freeze_Bridge::compatible_reviewed_lab( $result, 172, 904, 5, 'es', array() ),
    'Reversed matchup must reject reviewed Twin.'
);

tt_assert(
    $lab === TactiTwin_Reviewed_Twin_Freeze_Bridge::compatible_reviewed_lab( $result, 904, 172, 5, 'es', array() ),
    'Manual/no-fixture context may reuse an otherwise exact reviewed Twin.'
);

$legacy_result = $result;
unset( $legacy_result['preparation'] );
$legacy_result['sample'] = 5;
tt_assert(
    $lab === TactiTwin_Reviewed_Twin_Freeze_Bridge::compatible_reviewed_lab( $legacy_result, 904, 172, 5, 'es', $current_context ),
    'Explicit stored sample must be accepted when preparation metadata is absent.'
);

echo "reviewed Twin freeze compatibility ok\n";
