<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-weak-side-scenario.php';

function tt_weak_side_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$team = array(
    'team_source_id' => '10',
    'team_name' => 'Our Team',
    'coverage' => array( 'matches' => 5 ),
    'final_third_channel_share' => array(
        'left' => 50.0,
        'central' => 10.0,
        'right' => 40.0,
    ),
);

$opponent = array(
    'team_source_id' => '20',
    'team_name' => 'Opponent',
    'coverage' => array( 'matches' => 5 ),
    'final_third_channel_share_against' => array(
        'left' => 15.0,
        'central' => 25.0,
        'right' => 60.0,
    ),
);

$en = TactiTwin_Weak_Side_Scenario::evaluate( $team, $opponent, 'en' );
$es = TactiTwin_Weak_Side_Scenario::evaluate( $team, $opponent, 'es' );

tt_weak_side_assert( 'exploit-opponent-weak-side' === $en['scenario_id'], 'scenario id returned' );
tt_weak_side_assert( 'Exploit Opponent Weak Side' === $en['scenario_name'], 'English scenario name returned' );
tt_weak_side_assert( 'Explotar el lado débil del rival' === $es['scenario_name'], 'Spanish scenario name returned' );
tt_weak_side_assert( 'left' === $en['evidence']['our_dominant_channel'], 'our dominant channel detected' );
tt_weak_side_assert( 'right' === $en['evidence']['opponent_most_conceded_channel'], 'opponent concession channel detected' );
tt_weak_side_assert( 'right' === $en['evidence']['best_matchup_channel'], 'best matchup channel detected' );
tt_weak_side_assert( false === $en['evidence']['dominant_alignment'], 'misalignment detected' );
tt_weak_side_assert( $en['evidence']['potential_switching_gain_points'] >= 15.0, 'meaningful switching gain detected' );
tt_weak_side_assert( $en['opportunity']['score'] >= 65.0, 'high weak-side opportunity detected' );
tt_weak_side_assert( false !== strpos( $en['recommendations']['primary'], 'right' ), 'English recommendation uses target channel' );
tt_weak_side_assert( false !== strpos( $es['recommendations']['primary'], 'derecho' ), 'Spanish recommendation uses target channel' );
tt_weak_side_assert( $en['risk']['score'] === $es['risk']['score'], 'risk is language-neutral' );
tt_weak_side_assert( $en['opportunity']['score'] === $es['opportunity']['score'], 'opportunity is language-neutral' );
tt_weak_side_assert( 'explainable-offensive-channel-matchup-heuristic' === $en['method']['type'], 'method declared' );

$low = $team;
$low['coverage']['matches'] = 1;
$warning = TactiTwin_Weak_Side_Scenario::evaluate( $low, $opponent, 'es' );
tt_weak_side_assert( 'low' === $warning['coverage']['status'], 'low coverage flagged' );
tt_weak_side_assert( false !== strpos( $warning['coverage']['warning'], 'Cobertura de evidencia baja' ), 'Spanish coverage warning returned' );

echo "Weak-side scenario tests passed.\n";
