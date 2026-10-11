<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-team-profile.php';

function tt_profile_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$samples = array();
for ( $i = 1; $i <= 3; $i++ ) {
    $samples[] = array(
        'match_id'   => (string) ( 100 + $i ),
        'match_date' => '2026-09-0' . $i,
        'team_metrics' => array(
            'team_source_id' => '10',
            'team_name' => 'Our Team',
            'passes' => 400,
            'completed_passes' => 340,
            'shots' => 10,
            'xg' => 1.2,
            'carries' => 100,
            'pressures' => 120,
            'recoveries' => 50,
            'duels' => 60,
            'progressive_actions' => 30,
            'final_third_entries' => 24,
            'box_entries' => 12,
            'build_up_actions' => 80,
            'build_up_passes' => 60,
            'build_up_completed_passes' => 51,
            'build_up_escapes' => 28,
            'own_third_turnovers' => 5,
            'high_pressures' => 18,
            'high_recoveries' => 3,
            'final_third_actions' => 60,
            'final_third_left_channel' => 30,
            'final_third_central_channel' => 18,
            'final_third_right_channel' => 12,
        ),
        'opponent_metrics' => array(
            'pressures' => 130,
            'high_pressures' => 20,
            'high_recoveries' => 4,
            'shots' => 8,
            'xg' => 0.8,
        ),
    );
}

$profile = TactiTwin_Team_Profile::aggregate( $samples );

tt_profile_assert( 3 === $profile['coverage']['matches'], 'match coverage counted' );
tt_profile_assert( 'medium' === $profile['coverage']['confidence'], 'coverage confidence classified' );
tt_profile_assert( 85.0 === $profile['rates']['pass_completion_pct'], 'weighted pass completion calculated' );
tt_profile_assert( 85.0 === $profile['rates']['build_up_completion_pct'], 'build-up completion calculated' );
tt_profile_assert( 35.0 === $profile['rates']['build_up_escape_rate'], 'build-up escape rate calculated' );
tt_profile_assert( 5.0 === $profile['averages']['own_third_turnovers_per_match'], 'turnover average calculated' );
tt_profile_assert( 20.0 === $profile['opponent_context']['high_pressures_per_match'], 'opponent context aggregated' );
tt_profile_assert( 50.0 === $profile['final_third_channel_share']['left'], 'channel share aggregated' );

echo "Team profile tests passed.\n";
