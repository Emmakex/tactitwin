<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-side-bias-scenario.php';

function tt_side_bias_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$team = array(
    'team_source_id' => '10',
    'team_name' => 'Our Team',
    'coverage' => array( 'matches' => 5 ),
    'final_third_channel_share_against' => array(
        'left' => 20.0,
        'central' => 30.0,
        'right' => 50.0,
    ),
);

$opponent = array(
    'team_source_id' => '20',
    'team_name' => 'Opponent',
    'coverage' => array( 'matches' => 5 ),
    'final_third_channel_share' => array(
        'left' => 55.0,
        'central' => 25.0,
        'right' => 20.0,
    ),
);

$result = TactiTwin_Side_Bias_Scenario::evaluate( $team, $opponent );

tt_side_bias_assert( 'opponent-side-bias' === $result['scenario_id'], 'scenario id returned' );
tt_side_bias_assert( 'left' === $result['evidence']['opponent_dominant_channel'], 'opponent dominant channel detected' );
tt_side_bias_assert( 'right' === $result['evidence']['our_most_vulnerable_channel'], 'our vulnerable channel detected' );
tt_side_bias_assert( 'right' === $result['evidence']['opponent_weak_channel'], 'opponent weak channel detected' );
tt_side_bias_assert( 'left' === $result['risk']['highest_risk_channel'], 'combined risk channel detected' );
tt_side_bias_assert( false !== strpos( $result['recommendations']['primary'], 'right defensive channel' ), 'opponent left mapped to our right defensive side' );
tt_side_bias_assert( 'high' === $result['coverage']['status'], 'coverage classified' );
tt_side_bias_assert( 'explainable-channel-matchup-heuristic' === $result['method']['type'], 'method explicit' );

$overlap_team = $team;
$overlap_team['final_third_channel_share_against'] = array(
    'left' => 55.0,
    'central' => 25.0,
    'right' => 20.0,
);
$overlap = TactiTwin_Side_Bias_Scenario::evaluate( $overlap_team, $opponent );
tt_side_bias_assert( true === $overlap['risk']['dominant_overlap'], 'direct overlap detected' );

echo "Opponent side-bias scenario tests passed.\n";
