<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-context-aware-transition-review.php';

function tt_context_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_context_event( int $index, int $minute, string $team, string $type, string $outcome = '', array $raw = array() ): array {
    return array(
        'event_index'=>$index,
        'minute'=>$minute,
        'team_source_id'=>$team,
        'event_type_name'=>$type,
        'outcome_name'=>$outcome,
        'raw_json'=>$raw,
    );
}

$transition_deltas = array(
    'evidence_id'=>'transition-window-deltas',
    'source_match_id'=>'100',
    'coverage'=>array('default_window_minutes'=>10),
    'transitions'=>array(
        array(
            'transition_index'=>1,'minute'=>30,'snapshot_id'=>2,'from_plan'=>'A','to_plan'=>'B',
            'coverage'=>array('status'=>'good','comparable'=>true),
            'before'=>array('from_minute'=>20,'to_minute'=>30),'after'=>array('from_minute'=>30,'to_minute'=>40),
            'deltas'=>array('our_progressive_actions_per90'=>10.0),
        ),
        array(
            'transition_index'=>2,'minute'=>60,'snapshot_id'=>3,'from_plan'=>'B','to_plan'=>'C',
            'coverage'=>array('status'=>'good','comparable'=>true),
            'before'=>array('from_minute'=>50,'to_minute'=>60),'after'=>array('from_minute'=>60,'to_minute'=>70),
            'deltas'=>array('our_progressive_actions_per90'=>-8.0),
        ),
        array(
            'transition_index'=>3,'minute'=>80,'snapshot_id'=>4,'from_plan'=>'C','to_plan'=>'B',
            'coverage'=>array('status'=>'caution','comparable'=>true),
            'before'=>array('from_minute'=>70,'to_minute'=>80),'after'=>array('from_minute'=>80,'to_minute'=>90),
            'deltas'=>array('our_progressive_actions_per90'=>4.0),
        ),
    ),
);

$snapshots = array(
    array('id'=>1,'minute'=>20,'our_score'=>0,'opponent_score'=>0,'our_players'=>11,'opponent_players'=>11),
    array('id'=>2,'minute'=>30,'our_score'=>0,'opponent_score'=>0,'our_players'=>11,'opponent_players'=>11),
    array('id'=>3,'minute'=>60,'our_score'=>1,'opponent_score'=>0,'our_players'=>10,'opponent_players'=>11),
    array('id'=>4,'minute'=>80,'our_score'=>1,'opponent_score'=>0,'our_players'=>10,'opponent_players'=>11),
);

$events = array(
    tt_context_event(1,25,'1','Pass'),
    tt_context_event(2,35,'2','Pressure'),
    tt_context_event(3,55,'1','Shot','Goal'),
    tt_context_event(4,58,'1','Bad Behaviour','',array('bad_behaviour'=>array('card'=>array('name'=>'Red Card')))),
    tt_context_event(5,65,'2','Pressure'),
    tt_context_event(6,82,'1','Substitution'),
);

$en = TactiTwin_Context_Aware_Transition_Review::annotate($transition_deltas,$snapshots,$events,'1','2','en');
$es = TactiTwin_Context_Aware_Transition_Review::annotate($transition_deltas,$snapshots,$events,'1','2','es');

tt_context_assert('context-aware-transition-review' === $en['review_id'], 'context review identity retained');
tt_context_assert(3 === $en['coverage']['transition_count'], 'all transition deltas retained');

$stable = $en['transitions'][0]['context'];
tt_context_assert('stable' === $stable['status'], 'incident-free transition is stable');
tt_context_assert('cleaner' === $stable['interpretability'], 'stable good-coverage transition is cleaner');
tt_context_assert(true === $stable['context_comparable'], 'stable transition remains context-comparable');

$material = $en['transitions'][1]['context'];
tt_context_assert('materially-changed' === $material['status'], 'goal/red context is materially changed');
tt_context_assert('highly-confounded' === $material['interpretability'], 'material change is highly confounded');
tt_context_assert(false === $material['context_comparable'], 'materially changed transition is not context-comparable');
tt_context_assert(true === $material['recorded_snapshot_change']['score_changed'], 'snapshot score change detected');
tt_context_assert(true === $material['recorded_snapshot_change']['manpower_changed'], 'snapshot manpower change detected');
tt_context_assert(1 === $material['window_incidents']['before']['our_goals'], 'goal incident detected in before window');
tt_context_assert(1 === $material['window_incidents']['before']['our_dismissals'], 'red-card incident detected in before window');

$personnel = $en['transitions'][2]['context'];
tt_context_assert('changed' === $personnel['status'], 'substitution-only transition is personnel-changed');
tt_context_assert('confounded' === $personnel['interpretability'], 'substitution-only comparison is confounded');
tt_context_assert(true === $personnel['context_comparable'], 'substitution-only comparison remains comparable with caution');
tt_context_assert(1 === $personnel['window_incidents']['after']['our_substitutions'], 'substitution incident detected after transition');

tt_context_assert($en['transitions'][1]['context']['status'] === $es['transitions'][1]['context']['status'], 'language does not alter context classification');
tt_context_assert($en['transitions'][1]['context']['interpretability'] === $es['transitions'][1]['context']['interpretability'], 'language does not alter interpretability');
tt_context_assert($en['transitions'][1]['context']['status_label'] !== $es['transitions'][1]['context']['status_label'], 'context labels are translated');
tt_context_assert('descriptive-context-annotated-transition-window-review' === $en['method']['type'], 'method boundary is explicit');

$limited = $transition_deltas;
$limited['transitions'] = array($limited['transitions'][0]);
$limited['transitions'][0]['coverage']['comparable'] = false;
$limited_result = TactiTwin_Context_Aware_Transition_Review::annotate($limited,array($snapshots[0],$snapshots[1]),array(),'1','2','en');
tt_context_assert('insufficient' === $limited_result['transitions'][0]['context']['interpretability'], 'limited base coverage stays insufficient despite stable context');
tt_context_assert(false === $limited_result['transitions'][0]['context']['context_comparable'], 'limited base coverage cannot become comparable');

echo "Context-aware transition review tests passed.\n";
