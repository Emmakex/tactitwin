<?php

define( 'ABSPATH', __DIR__ );

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $value, $flags = 0 ) {
        return json_encode( $value, $flags );
    }
}

if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type, $gmt = false ) {
        return '2026-10-11 08:00:00';
    }
}

require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-statsbomb-normalizer.php';

function tt_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$competition = TactiTwin_StatsBomb_Normalizer::competition(
    array(
        'competition_id'     => 9,
        'season_id'          => 281,
        'country_name'       => 'Germany',
        'competition_name'   => '1. Bundesliga',
        'competition_gender' => 'male',
        'season_name'        => '2023/2024',
        'match_updated'      => '2024-09-28T20:46:38.893391',
    )
);

tt_assert( '9' === $competition['source_competition_id'], 'competition id normalized' );
tt_assert( '281' === $competition['source_season_id'], 'season id normalized' );
tt_assert( '1. Bundesliga' === $competition['competition_name'], 'competition name normalized' );

$match = TactiTwin_StatsBomb_Normalizer::match(
    array(
        'match_id'    => 3895292,
        'match_date'  => '2024-04-06',
        'kick_off'    => '15:30:00.000',
        'competition' => array( 'competition_id' => 9 ),
        'season'      => array( 'season_id' => 281 ),
        'home_team'   => array( 'home_team_id' => 190 ),
        'away_team'   => array( 'away_team_id' => 904 ),
        'home_score'  => 0,
        'away_score'  => 1,
        'match_week'  => 28,
        'match_status'=> 'available',
    )
);

tt_assert( '3895292' === $match['source_match_id'], 'match id normalized' );
tt_assert( '190' === $match['home_team_source_id'], 'home team normalized' );
tt_assert( '904' === $match['away_team_source_id'], 'away team normalized' );

$pass = TactiTwin_StatsBomb_Normalizer::event(
    3895292,
    array(
        'id'              => '6c546c19-5f61-4236-a1ad-0b6f5cded692',
        'index'           => 5,
        'period'          => 1,
        'minute'          => 0,
        'second'          => 1,
        'type'            => array( 'id' => 30, 'name' => 'Pass' ),
        'possession'      => 2,
        'possession_team' => array( 'id' => 190 ),
        'team'            => array( 'id' => 190, 'name' => 'Union Berlin' ),
        'player'          => array( 'id' => 24243, 'name' => 'Brenden Aaronson' ),
        'position'        => array( 'name' => 'Center Attacking Midfield' ),
        'location'        => array( 61.0, 40.1 ),
        'pass'            => array(
            'end_location' => array( 58.5, 38.9 ),
        ),
    )
);

tt_assert( 'Pass' === $pass['event_type_name'], 'event type normalized' );
tt_assert( 61.0 === $pass['location_x'], 'start x normalized' );
tt_assert( 40.1 === $pass['location_y'], 'start y normalized' );
tt_assert( 58.5 === $pass['end_location_x'], 'end x normalized' );
tt_assert( 38.9 === $pass['end_location_y'], 'end y normalized' );

$shot = TactiTwin_StatsBomb_Normalizer::event(
    999,
    array(
        'id'       => 'shot-1',
        'type'     => array( 'id' => 16, 'name' => 'Shot' ),
        'location' => array( 110.0, 40.0 ),
        'shot'     => array(
            'end_location' => array( 120.0, 39.0 ),
            'statsbomb_xg' => 0.42,
            'outcome'      => array( 'name' => 'Goal' ),
        ),
    )
);

tt_assert( 0.42 === $shot['xg'], 'xG normalized' );
tt_assert( 'Goal' === $shot['outcome_name'], 'shot outcome normalized' );

echo "StatsBomb normalizer tests passed.\n";
