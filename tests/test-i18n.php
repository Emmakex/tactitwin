<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-high-press-scenario.php';

function tt_i18n_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

tt_i18n_assert( 'es' === TactiTwin_I18n::normalize_language( 'es_ES' ), 'Spanish locale normalized' );
tt_i18n_assert( 'en' === TactiTwin_I18n::normalize_language( 'en-US' ), 'English locale normalized' );
tt_i18n_assert( 'en' === TactiTwin_I18n::normalize_language( 'fr_FR' ), 'unsupported locale falls back to English' );
tt_i18n_assert( array( 'en', 'es' ) === TactiTwin_I18n::supported(), 'supported languages are explicit' );
tt_i18n_assert( 'izquierdo' === TactiTwin_I18n::channel_label( 'left', 'es' ), 'channel label translated' );
tt_i18n_assert( 'High' === TactiTwin_I18n::level_label( 'high', 'en' ), 'English level label available' );

$team = array(
    'team_source_id' => '10',
    'team_name' => 'Equipo',
    'coverage' => array( 'matches' => 5 ),
    'rates' => array(
        'build_up_completion_pct' => 72.0,
        'build_up_escape_rate' => 32.0,
    ),
    'averages' => array(
        'own_third_turnovers_per_match' => 6.0,
        'progressive_actions_per_match' => 24.0,
    ),
    'final_third_channel_share' => array(
        'left' => 45.0,
        'central' => 30.0,
        'right' => 25.0,
    ),
);

$opponent = array(
    'team_source_id' => '20',
    'team_name' => 'Rival',
    'coverage' => array( 'matches' => 5 ),
    'averages' => array(
        'high_pressures_per_match' => 18.0,
        'high_recoveries_per_match' => 4.0,
    ),
);

$en = TactiTwin_High_Press_Scenario::evaluate( $team, $opponent, 'en' );
$es = TactiTwin_High_Press_Scenario::evaluate( $team, $opponent, 'es' );

tt_i18n_assert( 'Build-up vs High Press' === $en['scenario_name'], 'English scenario name returned' );
tt_i18n_assert( 'Salida de balón vs presión alta' === $es['scenario_name'], 'Spanish scenario name returned' );
tt_i18n_assert( 'high' === $en['risk']['level'] && 'high' === $es['risk']['level'], 'canonical level remains language-neutral' );
tt_i18n_assert( 'Alto' === $es['risk']['level_label'], 'Spanish presentation label returned' );
tt_i18n_assert( false !== strpos( $es['recommendations']['attacking_outlet'], 'izquierdo' ), 'Spanish recommendation localizes channel label' );
tt_i18n_assert( $en['risk']['score'] === $es['risk']['score'], 'language does not change tactical score' );

echo "ES/EN localization tests passed.\n";
