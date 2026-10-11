<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-defensive-transition-scenario.php';

function tt_def_transition_assert( $condition, string $message ): void {
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
        'dangerous_turnover_rate' => 15.0,
        'transition_progression_rate' => 35.0,
        'counterpress_recovery_rate' => 15.0,
    ),
    'averages' => array(
        'transition_turnovers_per_match' => 14.0,
    ),
    'turnover_zone_share' => array(
        'own_third' => 35.0,
        'middle_third' => 45.0,
        'final_third' => 20.0,
    ),
);

$opponent = array(
    'team_source_id' => '20',
    'team_name' => 'Transition Team',
    'coverage' => array( 'matches' => 5 ),
    'averages' => array(
        'transition_progressions_created_within_10s_per_match' => 3.5,
        'transition_shots_created_within_10s_per_match' => 2.2,
        'transition_xg_created_within_10s_per_match' => 0.55,
    ),
);

$result = TactiTwin_Defensive_Transition_Scenario::evaluate( $team, $opponent );

tt_def_transition_assert( 'defensive-transition-after-loss' === $result['scenario_id'], 'scenario id returned' );
tt_def_transition_assert( 'high' === $result['coverage']['status'], 'coverage classified' );
tt_def_transition_assert( 'high' === $result['risk']['level'], 'high transition risk detected' );
tt_def_transition_assert( $result['risk']['score'] >= 80.0, 'risk score reflects combined exposure' );
tt_def_transition_assert( 'low' === $result['opportunity']['level'], 'low counterpress opportunity detected' );
tt_def_transition_assert( 'middle_third' === $result['evidence']['dominant_loss_zone'], 'dominant loss zone detected' );
tt_def_transition_assert( false !== strpos( $result['recommendations']['primary'], 'rest-defence' ), 'rest-defence response recommended' );
tt_def_transition_assert( false !== strpos( $result['recommendations']['counterpress'], '5–8 second' ), 'counterpress training trigger recommended' );
tt_def_transition_assert( isset( $result['recommendations']['build_up_protection'] ), 'own-third protection recommendation added' );
tt_def_transition_assert( 'temporal-transition-window-heuristic' === $result['method']['type'], 'temporal method declared' );
tt_def_transition_assert( 10 === $result['method']['windows']['opponent_threat_seconds'], 'threat window declared' );
tt_def_transition_assert( 8 === $result['method']['windows']['counterpress_recovery_seconds'], 'counterpress window declared' );

$low_team = $team;
$low_team['coverage']['matches'] = 1;
$low = TactiTwin_Defensive_Transition_Scenario::evaluate( $low_team, $opponent );
tt_def_transition_assert( 'low' === $low['coverage']['status'], 'low coverage flagged' );
tt_def_transition_assert( null !== $low['coverage']['warning'], 'low coverage warning returned' );

echo "Defensive transition scenario tests passed.\n";
