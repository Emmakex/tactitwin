<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-high-press-scenario.php';

function tt_scenario_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$team = array(
    'team_source_id' => '10',
    'team_name' => 'Our Team',
    'coverage' => array( 'matches' => 5 ),
    'rates' => array(
        'build_up_completion_pct' => 72.0,
        'build_up_escape_rate' => 32.0,
    ),
    'averages' => array(
        'own_third_turnovers_per_match' => 6.0,
        'progressive_actions_per_match' => 24.0,
    ),
    'final_third_channel_share' => array(
        'left' => 45.0,
        'central' => 30.0,
        'right' => 25.0,
    ),
);

$opponent = array(
    'team_source_id' => '20',
    'team_name' => 'Pressing Team',
    'coverage' => array( 'matches' => 5 ),
    'averages' => array(
        'high_pressures_per_match' => 18.0,
        'high_recoveries_per_match' => 4.0,
    ),
);

$result = TactiTwin_High_Press_Scenario::evaluate( $team, $opponent );

tt_scenario_assert( 'build-up-vs-high-press' === $result['scenario_id'], 'scenario id returned' );
tt_scenario_assert( 'high' === $result['coverage']['status'], 'coverage classified' );
tt_scenario_assert( $result['risk']['score'] >= 65.0, 'high-risk matchup detected' );
tt_scenario_assert( 'high' === $result['risk']['level'], 'risk level classified' );
tt_scenario_assert( false !== strpos( $result['recommendations']['attacking_outlet'], 'left' ), 'strongest channel used in recommendation' );
tt_scenario_assert( 'explainable-weighted-heuristic' === $result['method']['type'], 'method is explicit' );

$low_coverage_team = $team;
$low_coverage_team['coverage']['matches'] = 1;
$low = TactiTwin_High_Press_Scenario::evaluate( $low_coverage_team, $opponent );
tt_scenario_assert( 'low' === $low['coverage']['status'], 'low coverage flagged' );
tt_scenario_assert( null !== $low['coverage']['warning'], 'coverage warning returned' );

echo "High press scenario tests passed.\n";
