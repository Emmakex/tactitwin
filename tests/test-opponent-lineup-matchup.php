<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-opponent-lineup-matchup.php';

function tt_opp_lineup_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$lineup = array(
    'coverage' => array( 'status'=>'high' ),
    'delta' => array(
        'dimensions' => array( 'ball_security'=>1.2 ),
        'structure' => array( 'rest_defence'=>10.0 ),
    ),
    'unit_interactions' => array(
        'delta' => array( 'defensive_sequence'=>0.5 ),
    ),
    'phase_unit_interactions' => array(
        'delta' => array(
            'build_up'=>2.2,
            'progression'=>1.0,
            'final_third'=>-0.10,
            'high_press'=>0.50,
        ),
        'candidate' => array(
            'coverage' => array(
                'possible_pairs'=>55,
                'under_observed_pairs'=>5,
            ),
        ),
    ),
);

$team = array(
    'team_source_id'=>'1',
    'team_name'=>'Team',
);
$opponent = array(
    'team_source_id'=>'2',
    'team_name'=>'Opponent',
    'rates'=>array( 'build_up_escape_rate'=>30.0 ),
    'averages'=>array( 'own_third_turnovers_per_match'=>5.0 ),
);
$scan = array(
    'coverage'=>array( 'status'=>'high' ),
    'subscenarios'=>array(
        array( 'scenario_id'=>'build-up-vs-high-press', 'risk'=>array('score'=>80.0) ),
        array( 'scenario_id'=>'attack-vs-low-block', 'risk'=>array('score'=>70.0) ),
        array( 'scenario_id'=>'defensive-transition-after-loss', 'risk'=>array('score'=>75.0) ),
    ),
);

$en = TactiTwin_Opponent_Lineup_Matchup::evaluate( $lineup, $team, $opponent, $scan, 'en' );
$es = TactiTwin_Opponent_Lineup_Matchup::evaluate( $lineup, $team, $opponent, $scan, 'es' );

tt_opp_lineup_assert( 5 === count( $en['priorities'] ), 'five opponent-conditioned priorities are returned' );
tt_opp_lineup_assert( 'candidate' === $en['decision']['lean'], 'candidate XI wins opponent-conditioned weighted votes' );
tt_opp_lineup_assert( $en['decision']['candidate_votes'] > $en['decision']['baseline_votes'], 'candidate vote evidence retained' );
tt_opp_lineup_assert( 'high' === $en['coverage']['status'], 'strong underlying evidence yields high coverage' );
tt_opp_lineup_assert( $en['priorities'][0]['demand']['score'] >= 75.0, 'highest opponent demand is ranked first' );
tt_opp_lineup_assert( $en['priorities'] !== array(), 'priority evidence is not collapsed into one score' );
tt_opp_lineup_assert( $en['decision']['candidate_votes'] === $es['decision']['candidate_votes'], 'language does not alter decision votes' );
tt_opp_lineup_assert( $en['priorities'][0]['demand']['score'] === $es['priorities'][0]['demand']['score'], 'language does not alter opponent demand scores' );
tt_opp_lineup_assert( $en['decision']['label'] !== $es['decision']['label'], 'decision label is translated' );
tt_opp_lineup_assert( 'opponent-conditioned-relative-lineup-decision-support' === $en['method']['type'], 'method boundary is explicit' );

$low = $lineup;
$low['coverage']['status'] = 'low';
$low['phase_unit_interactions']['candidate']['coverage']['under_observed_pairs'] = 30;
$low_result = TactiTwin_Opponent_Lineup_Matchup::evaluate( $low, $team, $opponent, $scan, 'en' );
tt_opp_lineup_assert( 'low' === $low_result['coverage']['status'], 'low lineup/network evidence is propagated' );
tt_opp_lineup_assert( 'inconclusive' === $low_result['decision']['lean'], 'low coverage blocks a lineup preference' );
tt_opp_lineup_assert( 0 === $low_result['decision']['candidate_votes'] && 0 === $low_result['decision']['baseline_votes'], 'low coverage prevents hidden voting' );

echo "Opponent-conditioned lineup matchup tests passed.\n";
