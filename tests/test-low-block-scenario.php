<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-low-block-scenario.php';

function tt_low_block_assert( $condition, string $message ): void {
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
        'box_shot_share_pct' => 45.0,
        'xg_per_shot' => 0.08,
    ),
    'averages' => array(
        'final_third_entries_per_match' => 20.0,
        'box_entries_per_match' => 8.0,
    ),
    'final_third_channel_share' => array(
        'left' => 60.0,
        'central' => 20.0,
        'right' => 20.0,
    ),
);

$opponent = array(
    'team_source_id' => '20',
    'team_name' => 'Deep Team',
    'coverage' => array( 'matches' => 5 ),
    'rates' => array(
        'deep_defensive_action_share_pct' => 55.0,
    ),
    'averages' => array(
        'deep_defensive_actions_per_match' => 45.0,
    ),
    'opponent_context' => array(
        'box_entries_per_match' => 8.0,
        'shots_per_match' => 8.0,
        'xg_per_match' => 0.70,
    ),
    'final_third_channel_share_against' => array(
        'left' => 20.0,
        'central' => 55.0,
        'right' => 25.0,
    ),
);

$en = TactiTwin_Low_Block_Scenario::evaluate( $team, $opponent, 'en' );
$es = TactiTwin_Low_Block_Scenario::evaluate( $team, $opponent, 'es' );

tt_low_block_assert( 'attack-vs-low-block' === $en['scenario_id'], 'scenario id returned' );
tt_low_block_assert( 'Attack vs Low Block' === $en['scenario_name'], 'English scenario name returned' );
tt_low_block_assert( 'Ataque vs bloque bajo' === $es['scenario_name'], 'Spanish scenario name returned' );
tt_low_block_assert( $en['risk']['score'] >= 65.0, 'deep block difficulty detected' );
tt_low_block_assert( 'high' === $en['risk']['level'], 'high risk classified' );
tt_low_block_assert( 55.0 === $en['evidence']['opponent_deep_defensive_action_share_pct'], 'deep defensive share exposed' );
tt_low_block_assert( 'central' === $en['evidence']['opponent_most_conceded_channel'], 'most conceded channel detected' );
tt_low_block_assert( false !== strpos( $en['recommendations']['box_access'], 'inside the box' ), 'English box-access recommendation generated' );
tt_low_block_assert( false !== strpos( $es['recommendations']['box_access'], 'dentro del área' ), 'Spanish box-access recommendation generated' );
tt_low_block_assert( $en['risk']['score'] === $es['risk']['score'], 'risk score is language-neutral' );
tt_low_block_assert( $en['opportunity']['score'] === $es['opportunity']['score'], 'opportunity score is language-neutral' );
tt_low_block_assert( 'event-data-low-block-proxy-heuristic' === $en['method']['type'], 'proxy method declared' );
tt_low_block_assert( in_array( 'exact_defensive_line_height', $en['method']['tracking_required_for'], true ), 'tracking limitation declared' );

$low_coverage = $team;
$low_coverage['coverage']['matches'] = 1;
$warning = TactiTwin_Low_Block_Scenario::evaluate( $low_coverage, $opponent, 'es' );
tt_low_block_assert( 'low' === $warning['coverage']['status'], 'low coverage flagged' );
tt_low_block_assert( false !== strpos( $warning['coverage']['warning'], 'Cobertura de evidencia baja' ), 'Spanish coverage warning returned' );

echo "Low block scenario tests passed.\n";
