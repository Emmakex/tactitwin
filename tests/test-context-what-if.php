<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-game-state-what-if.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-context-what-if.php';

function tt_context_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_context_state( float $minutes, float $shots, float $xg, float $box, float $final_third, float $high_pressures ): array {
    return array(
        'minutes' => $minutes,
        'matches_observed' => $minutes > 0 ? 3 : 0,
        'pass_completion_pct' => 82.0,
        'per_90' => array(
            'shots' => $shots,
            'xg' => $xg,
            'goals' => 1.0,
            'progressive_actions' => 28.0,
            'final_third_entries' => $final_third,
            'box_entries' => $box,
            'pressures' => 20.0,
            'high_pressures' => $high_pressures,
        ),
    );
}

$team = array(
    'team_source_id' => '10', 'team_name' => 'Our Team',
    'averages' => array( 'shots_per_match' => 12.0, 'xg_per_match' => 1.2, 'progressive_actions_per_match' => 28.0, 'final_third_entries_per_match' => 24.0, 'box_entries_per_match' => 12.0, 'high_pressures_per_match' => 10.0 ),
    'game_states' => array(
        'leading' => tt_context_state( 120.0, 9.0, 0.9, 9.0, 21.0, 8.0 ),
        'drawing' => tt_context_state( 180.0, 12.0, 1.2, 12.0, 24.0, 10.0 ),
        'trailing' => tt_context_state( 90.0, 16.0, 1.6, 16.0, 32.0, 15.0 ),
    ),
    'manpower_states' => array(
        'equal' => tt_context_state( 360.0, 12.0, 1.2, 12.0, 24.0, 10.0 ),
        'superior' => tt_context_state( 60.0, 18.0, 1.8, 18.0, 34.0, 14.0 ),
        'inferior' => tt_context_state( 60.0, 7.0, 0.6, 7.0, 17.0, 6.0 ),
    ),
);

$opponent = array(
    'team_source_id' => '20', 'team_name' => 'Opponent',
    'averages' => array( 'shots_per_match' => 10.0, 'xg_per_match' => 1.0, 'progressive_actions_per_match' => 24.0, 'final_third_entries_per_match' => 22.0, 'box_entries_per_match' => 10.0, 'high_pressures_per_match' => 10.0 ),
    'game_states' => array(
        'leading' => tt_context_state( 100.0, 8.0, 0.8, 8.0, 19.0, 7.0 ),
        'drawing' => tt_context_state( 180.0, 11.0, 1.1, 11.0, 23.0, 10.0 ),
        'trailing' => tt_context_state( 120.0, 15.0, 1.6, 15.0, 31.0, 14.0 ),
    ),
    'manpower_states' => array(
        'equal' => tt_context_state( 350.0, 10.0, 1.0, 10.0, 22.0, 10.0 ),
        'superior' => tt_context_state( 70.0, 17.0, 1.7, 17.0, 33.0, 15.0 ),
        'inferior' => tt_context_state( 55.0, 6.0, 0.5, 6.0, 16.0, 5.0 ),
    ),
);

$scan = array(
    'scenario_id' => 'full-matchup-scan',
    'subscenarios' => array(
        array( 'scenario_id' => 'build-up-vs-high-press', 'scenario_name' => 'Build-up vs High Press', 'risk' => array( 'score' => 70.0 ), 'opportunity' => array( 'score' => 40.0 ), 'recommendations' => array( 'primary' => 'Use a mixed build-up.' ) ),
        array( 'scenario_id' => 'exploit-opponent-weak-side', 'scenario_name' => 'Exploit Opponent Weak Side', 'risk' => array( 'score' => 25.0 ), 'opportunity' => array( 'score' => 80.0 ), 'recommendations' => array( 'primary' => 'Attack the weak side.' ) ),
    ),
);

$base = TactiTwin_Game_State_What_If::evaluate( $team, $opponent, $scan, 1, 0, 75, 'en' );
$equal = TactiTwin_Context_What_If::evaluate( $team, $opponent, $scan, 1, 0, 75, 'en', 11, 11 );

tt_context_assert( 'equal' === $equal['hypothesis']['manpower_state'], '11v11 classified as equal' );
tt_context_assert( false === $equal['method']['manpower_adjustment']['applied_to_scores'], '11v11 does not blend manpower scores' );
tt_context_assert( $base['risk']['score'] === $equal['risk']['score'], '11v11 preserves score-state risk' );
tt_context_assert( $base['opportunity']['score'] === $equal['opportunity']['score'], '11v11 preserves score-state opportunity' );

$inferior_en = TactiTwin_Context_What_If::evaluate( $team, $opponent, $scan, 1, 0, 75, 'en', 10, 11 );
$inferior_es = TactiTwin_Context_What_If::evaluate( $team, $opponent, $scan, 1, 0, 75, 'es', 10, 11 );

tt_context_assert( 'inferior' === $inferior_en['hypothesis']['manpower_state'], '10v11 classified as inferior' );
tt_context_assert( 'superior' === $inferior_en['hypothesis']['opponent_manpower_state'], 'opponent reciprocal manpower state selected' );
tt_context_assert( true === $inferior_en['method']['manpower_adjustment']['applied_to_scores'], 'sufficient manpower coverage activates blend' );
tt_context_assert( 60.0 === $inferior_en['coverage']['manpower']['our_state_minutes'], 'our inferior-state minutes exposed' );
tt_context_assert( 70.0 === $inferior_en['coverage']['manpower']['opponent_state_minutes'], 'opponent superior-state minutes exposed' );
tt_context_assert( $inferior_en['risk']['score'] !== $base['risk']['score'], '10v11 can alter risk score when evidence threshold met' );
tt_context_assert( $inferior_en['opportunity']['score'] !== $base['opportunity']['score'], '10v11 can alter opportunity score when evidence threshold met' );
tt_context_assert( $inferior_en['risk']['score'] === $inferior_es['risk']['score'], 'language does not alter blended risk' );
tt_context_assert( $inferior_en['opportunity']['score'] === $inferior_es['opportunity']['score'], 'language does not alter blended opportunity' );
tt_context_assert( $inferior_en['hypothesis']['headline'] !== $inferior_es['hypothesis']['headline'], 'combined context headline translated' );
tt_context_assert( false !== strpos( implode( ' ', $inferior_en['recommendations'] ), 'player disadvantage' ), 'inferiority recommendation added' );

$low_team = $team;
$low_opponent = $opponent;
$low_team['manpower_states']['inferior']['minutes'] = 20.0;
$low_opponent['manpower_states']['superior']['minutes'] = 25.0;
$low = TactiTwin_Context_What_If::evaluate( $low_team, $low_opponent, $scan, 1, 0, 75, 'en', 10, 11 );
tt_context_assert( false === $low['method']['manpower_adjustment']['applied_to_scores'], 'low manpower coverage blocks numeric blend' );
tt_context_assert( $base['risk']['score'] === $low['risk']['score'], 'low manpower coverage preserves score-state risk' );
tt_context_assert( ! empty( $low['coverage']['manpower']['warning'] ), 'low manpower coverage warning emitted' );

echo "Context What-if tests passed.\n";
