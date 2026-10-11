<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-team-profile.php';

function tt_transition_profile_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$samples = array();
for ( $i = 1; $i <= 3; $i++ ) {
    $samples[] = array(
        'match_id' => (string) ( 200 + $i ),
        'match_date' => '2026-10-0' . $i,
        'team_metrics' => array(
            'team_source_id' => '10',
            'team_name' => 'Our Team',
            'transition_turnovers' => 10,
            'turnovers_own_third' => 3,
            'turnovers_middle_third' => 5,
            'turnovers_final_third' => 2,
            'opponent_progressions_within_10s' => 4,
            'opponent_shots_within_10s' => 2,
            'xg_conceded_within_10s' => 0.35,
            'counterpress_recoveries_within_8s' => 3,
            'transition_progressions_created_within_10s' => 2,
            'transition_shots_created_within_10s' => 1,
            'transition_xg_created_within_10s' => 0.25,
        ),
        'opponent_metrics' => array(),
    );
}

$profile = TactiTwin_Team_Profile::aggregate( $samples );

tt_transition_profile_assert( 10.0 === $profile['averages']['transition_turnovers_per_match'], 'turnovers averaged' );
tt_transition_profile_assert( 20.0 === $profile['rates']['dangerous_turnover_rate'], 'dangerous turnover rate aggregated' );
tt_transition_profile_assert( 40.0 === $profile['rates']['transition_progression_rate'], 'progression-after-loss rate aggregated' );
tt_transition_profile_assert( 30.0 === $profile['rates']['counterpress_recovery_rate'], 'counterpress rate aggregated' );
tt_transition_profile_assert( 30.0 === $profile['turnover_zone_share']['own_third'], 'own-third loss share aggregated' );
tt_transition_profile_assert( 50.0 === $profile['turnover_zone_share']['middle_third'], 'middle-third loss share aggregated' );
tt_transition_profile_assert( 20.0 === $profile['turnover_zone_share']['final_third'], 'final-third loss share aggregated' );
tt_transition_profile_assert( 2.0 === $profile['averages']['transition_progressions_created_within_10s_per_match'], 'transition progressions created averaged' );
tt_transition_profile_assert( 1.0 === $profile['averages']['transition_shots_created_within_10s_per_match'], 'transition shots created averaged' );
tt_transition_profile_assert( 0.25 === $profile['averages']['transition_xg_created_within_10s_per_match'], 'transition xG created averaged' );

echo "Transition profile tests passed.\n";
