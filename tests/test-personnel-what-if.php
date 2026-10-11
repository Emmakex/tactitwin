<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-personnel-what-if.php';

function tt_personnel_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$out = array(
    'player_source_id'=>'101','player_name'=>'Control 8','position_name'=>'Center Midfield',
    'coverage'=>array('minutes'=>520.0,'appearances'=>7,'starts'=>6,'confidence'=>'high'),
    'rates'=>array('pass_completion_pct'=>91.0),
    'per_90'=>array('passes'=>62.0,'carries'=>22.0,'progressive_actions'=>7.0,'final_third_entries'=>4.0,'box_entries'=>1.0,'shots'=>1.0,'xg'=>0.10,'pressures'=>12.0,'recoveries'=>6.0,'duels'=>5.0,'turnovers'=>1.0),
);
$in = array(
    'player_source_id'=>'150','player_name'=>'Vertical 8','position_name'=>'Center Midfield',
    'coverage'=>array('minutes'=>480.0,'appearances'=>8,'starts'=>5,'confidence'=>'high'),
    'rates'=>array('pass_completion_pct'=>86.0),
    'per_90'=>array('passes'=>48.0,'carries'=>28.0,'progressive_actions'=>11.0,'final_third_entries'=>7.0,'box_entries'=>3.0,'shots'=>2.5,'xg'=>0.35,'pressures'=>18.0,'recoveries'=>7.0,'duels'=>7.0,'turnovers'=>2.5),
);
$context = array(
    'hypothesis'=>array('minute'=>70,'our_score'=>0,'opponent_score'=>1,'state'=>'trailing','our_players'=>11,'opponent_players'=>11),
    'risk'=>array('score'=>66.0),
    'opportunity'=>array('score'=>58.0),
    'scan_context'=>array('best_opportunity_scenario'=>array('scenario_id'=>'exploit-opponent-weak-side')),
);

$en = TactiTwin_Personnel_What_If::compare( $out, $in, 70, 'en', $context );
$es = TactiTwin_Personnel_What_If::compare( $out, $in, 70, 'es', $context );

tt_personnel_assert( 'personnel-what-if' === $en['scenario_id'], 'scenario id returned' );
tt_personnel_assert( 'high' === $en['coverage']['confidence'], 'high comparable sample classified' );
tt_personnel_assert( 480.0 === $en['coverage']['effective_minutes'], 'minimum player sample used' );
tt_personnel_assert( true === $en['role']['same_primary_position'], 'same role identified' );
tt_personnel_assert( $en['role']['profile_similarity'] >= 0 && $en['role']['profile_similarity'] <= 100, 'similarity bounded' );
tt_personnel_assert( 4.0 === $en['deltas']['progressive_actions_per_90'], 'progression delta correct' );
tt_personnel_assert( -5.0 === $en['deltas']['pass_completion_pp'], 'pass completion tradeoff correct' );
tt_personnel_assert( 0.25 === $en['deltas']['xg_per_90'], 'xG delta correct' );
tt_personnel_assert( $en['deltas'] === $es['deltas'], 'language does not alter measured deltas' );
tt_personnel_assert( $en['dimensions'] === $es['dimensions'], 'language does not alter dimension logic' );
tt_personnel_assert( $en['role']['profile_similarity'] === $es['role']['profile_similarity'], 'language does not alter similarity' );
tt_personnel_assert( $en['recommendations'][0] !== $es['recommendations'][0], 'recommendation translated' );
tt_personnel_assert( 'trailing' === $en['match_context']['hypothesis']['state'], 'match context retained' );
tt_personnel_assert( false !== strpos( implode( ' ', $en['recommendations'] ), 'progression' ), 'progression gain described' );
tt_personnel_assert( false !== strpos( implode( ' ', $en['recommendations'] ), 'Ball-security caution' ), 'turnover tradeoff described' );

$low = $in;
$low['coverage']['minutes'] = 90.0;
$low_result = TactiTwin_Personnel_What_If::compare( $out, $low, 60, 'en' );
tt_personnel_assert( 'low' === $low_result['coverage']['confidence'], 'low sample classified' );
tt_personnel_assert( ! empty( $low_result['coverage']['warning'] ), 'low sample warning emitted' );

echo "Personnel What-if tests passed.\n";
