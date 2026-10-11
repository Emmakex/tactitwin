<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-high-press-scenario.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-side-bias-scenario.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-defensive-transition-scenario.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-low-block-scenario.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-weak-side-scenario.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-matchup-scan.php';

function tt_scan_assert( $condition, string $message ): void {
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
        'build_up_completion_pct' => 75.0,
        'build_up_escape_rate' => 50.0,
        'dangerous_turnover_rate' => 5.0,
        'transition_progression_rate' => 10.0,
        'counterpress_recovery_rate' => 50.0,
        'box_shot_share_pct' => 70.0,
        'xg_per_shot' => 0.13,
    ),
    'averages' => array(
        'own_third_turnovers_per_match' => 2.0,
        'progressive_actions_per_match' => 30.0,
        'transition_turnovers_per_match' => 10.0,
        'final_third_entries_per_match' => 26.0,
        'box_entries_per_match' => 14.0,
    ),
    'final_third_channel_share' => array( 'left' => 30.0, 'central' => 30.0, 'right' => 40.0 ),
    'final_third_channel_share_against' => array( 'left' => 20.0, 'central' => 20.0, 'right' => 60.0 ),
    'turnover_zone_share' => array( 'own_third' => 20.0, 'middle_third' => 50.0, 'final_third' => 30.0 ),
);

$opponent = array(
    'team_source_id' => '20',
    'team_name' => 'Opponent',
    'coverage' => array( 'matches' => 5 ),
    'rates' => array(
        'deep_defensive_action_share_pct' => 20.0,
    ),
    'averages' => array(
        'high_pressures_per_match' => 18.0,
        'high_recoveries_per_match' => 3.0,
        'deep_defensive_actions_per_match' => 18.0,
        'transition_progressions_created_within_10s_per_match' => 2.0,
        'transition_shots_created_within_10s_per_match' => 0.5,
        'transition_xg_created_within_10s_per_match' => 0.10,
    ),
    'opponent_context' => array(
        'box_entries_per_match' => 18.0,
        'shots_per_match' => 16.0,
        'xg_per_match' => 1.8,
    ),
    'final_third_channel_share' => array( 'left' => 60.0, 'central' => 20.0, 'right' => 20.0 ),
    'final_third_channel_share_against' => array( 'left' => 30.0, 'central' => 40.0, 'right' => 30.0 ),
);

$result = TactiTwin_Matchup_Scan::evaluate( $team, $opponent, 'en' );

tt_scan_assert( 'full-matchup-scan' === $result['scenario_id'], 'scan id returned' );
tt_scan_assert( 5 === $result['evidence']['scenario_count'], 'five scenarios executed' );
tt_scan_assert( 5 === count( $result['subscenarios'] ), 'subscenarios retained' );
tt_scan_assert( 5 === count( $result['evidence']['scenario_ranking'] ), 'ranking contains all scenarios' );
tt_scan_assert( 5 === count( $result['recommendations'] ), 'one primary recommendation retained per scenario' );
tt_scan_assert( 'Build-up vs High Press' === $result['risk']['top_scenario'], 'highest risk scenario identified' );
for ( $i = 1; $i < count( $result['evidence']['scenario_ranking'] ); $i++ ) {
    tt_scan_assert(
        $result['evidence']['scenario_ranking'][ $i - 1 ]['risk_score'] >= $result['evidence']['scenario_ranking'][ $i ]['risk_score'],
        'ranking remains descending'
    );
}
tt_scan_assert( $result['risk']['score'] === $result['evidence']['scenario_ranking'][0]['risk_score'], 'headline risk equals top scenario risk' );
tt_scan_assert( 'high' === $result['coverage']['status'], 'coverage propagated' );
tt_scan_assert( 'scenario-orchestration' === $result['method']['type'], 'orchestration method declared' );

echo "Full matchup scan tests passed.\n";
