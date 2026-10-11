<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-live-observation.php';

function tt_live_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_live_playbook( string $active = 'A', string $mode = 'baseline' ): array {
    return array(
        'playbook_id'=>'contingency-playbook',
        'active_plan'=>array(
            'id'=>$active,
            'mode'=>$mode,
            'label'=>'A' === $active ? 'Plan A' : 'Plan C',
            'reason'=>'base reason',
            'confidence'=>'high',
        ),
        'recommendations'=>array('Base recommendation.'),
        'method'=>array(
            'type'=>'deterministic-contingency-playbook',
            'limitations'=>array('Base limitation.'),
        ),
    );
}

$scan = array(
    'scenario_id'=>'full-matchup-scan',
    'subscenarios'=>array(
        array(
            'scenario_id'=>'build-up-vs-high-press',
            'scenario_name'=>'High press escape',
            'risk'=>array('score'=>70.0),
            'opportunity'=>array('score'=>55.0),
            'recommendations'=>array(
                'primary'=>'Use the prepared escape route.',
                'support'=>'Keep a free support player.',
            ),
        ),
        array(
            'scenario_id'=>'attack-vs-low-block',
            'scenario_name'=>'Low block attack',
            'risk'=>array('score'=>85.0),
            'opportunity'=>array('score'=>60.0),
            'recommendations'=>array(
                'primary'=>'Increase width and circulation.',
            ),
        ),
        array(
            'scenario_id'=>'defensive-transition-after-loss',
            'scenario_name'=>'Transition security',
            'risk'=>array('score'=>65.0),
            'opportunity'=>array('score'=>40.0),
            'recommendations'=>array(
                'primary'=>'Protect the central counter lane.',
            ),
        ),
    ),
);

$none = TactiTwin_Live_Observation::apply(
    tt_live_playbook(),
    $scan,
    array(),
    'en'
);
tt_live_assert( 'A' === $none['active_plan']['id'], 'no observation keeps Plan A' );
tt_live_assert( 'no_live_plan_b' === $none['live_observation']['activation']['status'], 'no observation records no live Plan B' );
tt_live_assert( 0 === $none['live_observation']['observed_count'], 'no observation count is zero' );

$press_en = TactiTwin_Live_Observation::apply(
    tt_live_playbook(),
    $scan,
    array('high_press'=>true,'note'=>'Press confirmed by analyst'),
    'en'
);
$press_es = TactiTwin_Live_Observation::apply(
    tt_live_playbook(),
    $scan,
    array('high_press'=>'sí','note'=>'Presión confirmada'),
    'es'
);
tt_live_assert( 'B' === $press_en['active_plan']['id'], 'explicit high-press observation activates Plan B' );
tt_live_assert( 'escape_high_press' === $press_en['active_plan']['mode'], 'high press selects escape mode' );
tt_live_assert( 'build-up-vs-high-press' === $press_en['active_plan']['scenario_id'], 'high press maps to correct scenario' );
tt_live_assert( 1 === $press_en['live_observation']['observed_count'], 'one checked behaviour is counted' );
tt_live_assert( 'plan_b_activated' === $press_en['live_observation']['activation']['status'], 'Plan B activation status is explicit' );
tt_live_assert( $press_en['active_plan']['id'] === $press_es['active_plan']['id'], 'language does not change live activation' );
tt_live_assert( $press_en['active_plan']['mode'] === $press_es['active_plan']['mode'], 'language does not change Plan B mode' );
tt_live_assert( $press_en['active_plan']['label'] !== $press_es['active_plan']['label'], 'active Plan B label is translated' );

$multiple = TactiTwin_Live_Observation::apply(
    tt_live_playbook(),
    $scan,
    array('high_press'=>true,'low_block'=>true),
    'en'
);
tt_live_assert( 2 === $multiple['live_observation']['observed_count'], 'multiple simultaneous observations are retained' );
tt_live_assert( 'attack-vs-low-block' === $multiple['active_plan']['scenario_id'], 'highest-risk observed scenario wins Plan B selection' );
tt_live_assert( 'break_low_block' === $multiple['active_plan']['mode'], 'selected observed scenario maps to correct mode' );

$transition = TactiTwin_Live_Observation::apply(
    tt_live_playbook(),
    $scan,
    array('transition_threat'=>'yes'),
    'en'
);
tt_live_assert( 'secure_transition' === $transition['active_plan']['mode'], 'string boolean input normalizes to a transition Plan B' );

$suppressed = TactiTwin_Live_Observation::apply(
    tt_live_playbook('C','protect'),
    $scan,
    array('high_press'=>true),
    'en'
);
tt_live_assert( 'C' === $suppressed['active_plan']['id'], 'Plan C keeps precedence over live Plan B observation' );
tt_live_assert( 'protect' === $suppressed['active_plan']['mode'], 'existing Plan C mode is preserved' );
tt_live_assert( 'plan_b_suppressed_by_plan_c' === $suppressed['live_observation']['activation']['status'], 'suppressed Plan B is recorded explicitly' );
tt_live_assert( 'build-up-vs-high-press' === $suppressed['live_observation']['activation']['selected_plan_b']['scenario_id'], 'suppressed live scenario remains auditable' );

tt_live_assert( 'explicit-live-observation-contingency-playbook' === $press_en['method']['type'], 'live method boundary is explicit' );
tt_live_assert( 'manual-staff-input' === $press_en['method']['live_observation']['source'], 'manual observation source is explicit' );

echo "Live observation activation tests passed.\n";
