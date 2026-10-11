<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-contingency-playbook.php';

function tt_playbook_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function tt_match_plan(): array {
    return array(
        'plan_id'=>'match-plan',
        'team'=>array('source_id'=>'1','name'=>'Team'),
        'opponent'=>array('source_id'=>'2','name'=>'Opponent'),
        'coverage'=>array('status'=>'high'),
        'priorities'=>array(
            array('scenario_id'=>'build-up-vs-high-press','scenario_name'=>'High press','priority_score'=>82.0),
            array('scenario_id'=>'defensive-transition-after-loss','scenario_name'=>'Transition','priority_score'=>74.0),
        ),
        'phases'=>array(
            'opening_15_minutes'=>array('Validate press height.'),
            'in_possession'=>array('Use the prepared build-up route.'),
            'out_of_possession'=>array('Protect the inside lane.'),
            'after_loss'=>array('Counterpress with cover.'),
        ),
        'decision_triggers'=>array(
            array(
                'trigger'=>'Opponent locks short build-up with an aggressive first line.',
                'action'=>'Use the high-press escape route.',
                'scenario_id'=>'build-up-vs-high-press',
            ),
            array(
                'trigger'=>'Repeated losses create immediate forward progression.',
                'action'=>'Strengthen rest defence.',
                'scenario_id'=>'defensive-transition-after-loss',
            ),
        ),
    );
}

function tt_scan( string $coverage = 'high' ): array {
    return array(
        'scenario_id'=>'full-matchup-scan',
        'coverage'=>array('status'=>$coverage),
        'subscenarios'=>array(
            array(
                'scenario_id'=>'build-up-vs-high-press',
                'scenario_name'=>'Build-up vs high press',
                'risk'=>array('score'=>82.0),
                'recommendations'=>array(
                    'primary'=>'Use a safer first progression route.',
                    'secondary'=>'Create a free support behind the first line.',
                ),
            ),
            array(
                'scenario_id'=>'attack-vs-low-block',
                'scenario_name'=>'Attack vs low block',
                'risk'=>array('score'=>58.0),
                'recommendations'=>array('primary'=>'Increase width and circulation.'),
            ),
            array(
                'scenario_id'=>'defensive-transition-after-loss',
                'scenario_name'=>'Defensive transition',
                'risk'=>array('score'=>65.0),
                'recommendations'=>array('primary'=>'Protect the central counter route.'),
            ),
        ),
    );
}

function tt_context(
    string $state,
    int $minute,
    int $our_score,
    int $opponent_score,
    int $our_players = 11,
    int $opponent_players = 11,
    string $coverage = 'high',
    string $manpower_coverage = 'high'
): array {
    $manpower = $our_players > $opponent_players ? 'superior' : ( $our_players < $opponent_players ? 'inferior' : 'equal' );
    return array(
        'hypothesis'=>array(
            'minute'=>$minute,
            'our_score'=>$our_score,
            'opponent_score'=>$opponent_score,
            'state'=>$state,
            'state_label'=>$state,
            'our_players'=>$our_players,
            'opponent_players'=>$opponent_players,
            'manpower_state'=>$manpower,
            'manpower_state_label'=>$manpower,
        ),
        'coverage'=>array(
            'status'=>$coverage,
            'manpower'=>array('status'=>$manpower_coverage),
        ),
        'recommendations'=>array(
            'Control the current score-state risk.',
            'Keep a credible transition outlet.',
        ),
        'decision_triggers'=>array(
            array('trigger'=>'Score state changes materially.','action'=>'Reassess risk tolerance.'),
        ),
        'method'=>array('type'=>'historical-score-state-and-manpower-conditioned-what-if'),
    );
}

$plan = tt_match_plan();
$scan = tt_scan();

$opening_en = TactiTwin_Contingency_Playbook::compose(
    $plan,
    tt_context('drawing',20,0,0),
    $scan,
    'en'
);
$opening_es = TactiTwin_Contingency_Playbook::compose(
    $plan,
    tt_context('drawing',20,0,0),
    $scan,
    'es'
);

tt_playbook_assert( 'A' === $opening_en['active_plan']['id'], 'equal early game keeps Plan A active' );
tt_playbook_assert( 'armed' === $opening_en['plans']['plan_b']['status'], 'high opponent risk arms Plan B' );
tt_playbook_assert( 'build-up-vs-high-press' === $opening_en['plans']['plan_b']['scenario_id'], 'highest eligible opponent risk arms the correct Plan B' );
tt_playbook_assert( 'A' !== $opening_en['plans']['plan_b']['id'], 'armed Plan B is not reported as the active plan' );
tt_playbook_assert( false !== strpos( $opening_en['active_plan']['note'], 'cannot prove' ), 'Plan A states that historical risk cannot prove a live Plan B trigger' );
tt_playbook_assert( 'high' === $opening_en['coverage']['status'], 'high source coverage remains high' );
tt_playbook_assert( $opening_en['active_plan']['id'] === $opening_es['active_plan']['id'], 'language does not change active plan logic' );
tt_playbook_assert( $opening_en['plans']['plan_b']['scenario_id'] === $opening_es['plans']['plan_b']['scenario_id'], 'language does not change armed Plan B scenario' );
tt_playbook_assert( $opening_en['active_plan']['label'] !== $opening_es['active_plan']['label'], 'plan label is translated' );

$chase = TactiTwin_Contingency_Playbook::compose(
    $plan,
    tt_context('trailing',70,0,1),
    $scan,
    'en'
);
tt_playbook_assert( 'C' === $chase['active_plan']['id'], 'trailing at minute 70 activates Plan C' );
tt_playbook_assert( 'chase' === $chase['active_plan']['mode'], 'late trailing state uses chase mode' );
tt_playbook_assert( 'chase' === $chase['plans']['plan_c']['mode'], 'Plan C payload mirrors chase mode' );

$protect = TactiTwin_Contingency_Playbook::compose(
    $plan,
    tt_context('leading',75,1,0),
    $scan,
    'en'
);
tt_playbook_assert( 'C' === $protect['active_plan']['id'], 'leading at minute 75 activates Plan C' );
tt_playbook_assert( 'protect' === $protect['active_plan']['mode'], 'late leading state uses protect mode' );

$inferior = TactiTwin_Contingency_Playbook::compose(
    $plan,
    tt_context('drawing',20,0,0,10,11,'high','medium'),
    $scan,
    'en'
);
tt_playbook_assert( 'C' === $inferior['active_plan']['id'], 'numerical inferiority overrides early Plan A' );
tt_playbook_assert( 'manpower_inferior' === $inferior['active_plan']['mode'], 'numerical inferiority uses manpower mode' );
tt_playbook_assert( 'medium' === $inferior['coverage']['status'], 'manpower coverage participates when numerical state is unequal' );

$superior = TactiTwin_Contingency_Playbook::compose(
    $plan,
    tt_context('trailing',30,0,1,11,10),
    $scan,
    'en'
);
tt_playbook_assert( 'C' === $superior['active_plan']['id'], 'numerical superiority overrides normal score-time thresholds' );
tt_playbook_assert( 'manpower_superior' === $superior['active_plan']['mode'], 'numerical superiority receives its dedicated mode' );

$low = TactiTwin_Contingency_Playbook::compose(
    array_merge($plan,array('coverage'=>array('status'=>'low'))),
    tt_context('drawing',20,0,0,'11','11','low'),
    tt_scan('low'),
    'en'
);
tt_playbook_assert( 'low' === $low['coverage']['status'], 'low evidence propagates to playbook coverage' );
tt_playbook_assert( 'A' === $low['active_plan']['id'], 'low evidence does not manufacture a Plan B activation' );
tt_playbook_assert( null !== $low['coverage']['warning'], 'low evidence emits an explicit warning' );
tt_playbook_assert( 'deterministic-contingency-playbook' === $low['method']['type'], 'method boundary is explicit' );

echo "Contingency playbook tests passed.\n";
