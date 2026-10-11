<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-game-state-what-if.php';

function tt_what_if_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$team = array(
    'team_source_id' => '10',
    'team_name' => 'Our Team',
    'averages' => array(
        'shots_per_match' => 12.0,
        'xg_per_match' => 1.20,
        'progressive_actions_per_match' => 28.0,
        'final_third_entries_per_match' => 24.0,
        'box_entries_per_match' => 12.0,
        'high_pressures_per_match' => 10.0,
    ),
    'game_states' => array(
        'leading' => array(
            'minutes' => 135.0, 'matches_observed' => 4, 'pass_completion_pct' => 84.0,
            'per_90' => array( 'shots' => 9.0, 'xg' => 0.90, 'goals' => 1.0, 'progressive_actions' => 24.0, 'final_third_entries' => 21.0, 'box_entries' => 9.0, 'pressures' => 18.0, 'high_pressures' => 8.0 ),
        ),
        'drawing' => array(
            'minutes' => 200.0, 'matches_observed' => 5, 'pass_completion_pct' => 86.0,
            'per_90' => array( 'shots' => 13.0, 'xg' => 1.30, 'goals' => 1.4, 'progressive_actions' => 30.0, 'final_third_entries' => 26.0, 'box_entries' => 13.0, 'pressures' => 20.0, 'high_pressures' => 11.0 ),
        ),
        'trailing' => array(
            'minutes' => 90.0, 'matches_observed' => 3, 'pass_completion_pct' => 82.0,
            'per_90' => array( 'shots' => 16.0, 'xg' => 1.50, 'goals' => 1.2, 'progressive_actions' => 35.0, 'final_third_entries' => 32.0, 'box_entries' => 16.0, 'pressures' => 25.0, 'high_pressures' => 15.0 ),
        ),
    ),
);

$opponent = array(
    'team_source_id' => '20',
    'team_name' => 'Opponent',
    'averages' => array(
        'shots_per_match' => 10.0,
        'xg_per_match' => 1.00,
        'progressive_actions_per_match' => 24.0,
        'final_third_entries_per_match' => 22.0,
        'box_entries_per_match' => 10.0,
        'high_pressures_per_match' => 10.0,
    ),
    'game_states' => array(
        'leading' => array(
            'minutes' => 100.0, 'matches_observed' => 4, 'pass_completion_pct' => 80.0,
            'per_90' => array( 'shots' => 8.0, 'xg' => 0.80, 'goals' => 1.0, 'progressive_actions' => 21.0, 'final_third_entries' => 19.0, 'box_entries' => 8.0, 'pressures' => 17.0, 'high_pressures' => 7.0 ),
        ),
        'drawing' => array(
            'minutes' => 180.0, 'matches_observed' => 5, 'pass_completion_pct' => 82.0,
            'per_90' => array( 'shots' => 11.0, 'xg' => 1.10, 'goals' => 1.1, 'progressive_actions' => 25.0, 'final_third_entries' => 23.0, 'box_entries' => 11.0, 'pressures' => 19.0, 'high_pressures' => 10.0 ),
        ),
        'trailing' => array(
            'minutes' => 120.0, 'matches_observed' => 4, 'pass_completion_pct' => 79.0,
            'per_90' => array( 'shots' => 15.0, 'xg' => 1.60, 'goals' => 1.3, 'progressive_actions' => 34.0, 'final_third_entries' => 31.0, 'box_entries' => 15.0, 'pressures' => 27.0, 'high_pressures' => 14.0 ),
        ),
    ),
);

$scan = array(
    'scenario_id' => 'full-matchup-scan',
    'subscenarios' => array(
        array(
            'scenario_id' => 'build-up-vs-high-press', 'scenario_name' => 'Build-up vs High Press',
            'risk' => array( 'score' => 72.0 ), 'opportunity' => array( 'score' => 45.0 ),
            'recommendations' => array( 'primary' => 'Use a mixed build-up.' ),
        ),
        array(
            'scenario_id' => 'exploit-opponent-weak-side', 'scenario_name' => 'Exploit Opponent Weak Side',
            'risk' => array( 'score' => 30.0 ), 'opportunity' => array( 'score' => 78.0 ),
            'recommendations' => array( 'primary' => 'Attack the weak-side channel.' ),
        ),
    ),
);

$en = TactiTwin_Game_State_What_If::evaluate( $team, $opponent, $scan, 1, 0, 80, 'en' );
$es = TactiTwin_Game_State_What_If::evaluate( $team, $opponent, $scan, 1, 0, 80, 'es' );

tt_what_if_assert( 'game-state-what-if' === $en['scenario_id'], 'scenario id returned' );
tt_what_if_assert( 'leading' === $en['hypothesis']['state'], '1-0 produces leading state' );
tt_what_if_assert( 'trailing' === $en['hypothesis']['opponent_state'], 'opponent reciprocal state selected' );
tt_what_if_assert( 120.0 === $en['coverage']['opponent_state_minutes'], 'opponent trailing minutes used' );
tt_what_if_assert( 'high' === $en['coverage']['status'], 'sufficient state exposure classified high' );
tt_what_if_assert( $en['risk']['score'] === $es['risk']['score'], 'language does not alter risk logic' );
tt_what_if_assert( $en['opportunity']['score'] === $es['opportunity']['score'], 'language does not alter opportunity logic' );
tt_what_if_assert( $en['hypothesis']['headline'] !== $es['hypothesis']['headline'], 'headline translated' );
tt_what_if_assert( 'exploit-opponent-weak-side' === $en['scan_context']['best_opportunity_scenario']['scenario_id'], 'best scan opportunity propagated' );
tt_what_if_assert( 'build-up-vs-high-press' === $en['scan_context']['highest_risk_scenario']['scenario_id'], 'top scan risk propagated' );
tt_what_if_assert( count( $en['decision_triggers'] ) >= 1, 'decision triggers returned' );
tt_what_if_assert( false !== strpos( implode( ' ', $en['recommendations'] ), 'Late-game' ), 'late leading recommendation included' );

$trailing = TactiTwin_Game_State_What_If::evaluate( $team, $opponent, $scan, 0, 1, 70, 'en' );
tt_what_if_assert( 'trailing' === $trailing['hypothesis']['state'], '0-1 produces trailing state' );
tt_what_if_assert( 'leading' === $trailing['hypothesis']['opponent_state'], 'opponent leading history selected when trailing' );

echo "Game State What-if tests passed.\n";
