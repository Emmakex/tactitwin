<?php

define( 'ABSPATH', __DIR__ . '/' );

require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-planning-context.php';

function tt_assert_same( $expected, $actual, string $message ): void {
    if ( $expected !== $actual ) {
        fwrite(
            STDERR,
            $message . "\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n"
        );
        exit( 1 );
    }
}

$target = array(
    'home_team_source_id' => '904',
    'home_team_name' => 'Bayer Leverkusen',
    'away_team_source_id' => '963',
    'away_team_name' => 'Augsburg',
);

$perspectives = TactiTwin_Planning_Context::target_perspectives( $target );

tt_assert_same( 2, count( $perspectives ), 'A valid fixture must expose exactly two team perspectives.' );
tt_assert_same( '904', $perspectives[0]['team_id'], 'Home perspective must preserve the numeric source ID as a string.' );
tt_assert_same( '963', $perspectives[0]['opponent_id'], 'Home perspective must point to the away team as opponent.' );
tt_assert_same( 'Bayer Leverkusen', $perspectives[0]['team_name'], 'Home perspective must preserve the home team name.' );
tt_assert_same( '963', $perspectives[1]['team_id'], 'Away perspective must preserve the numeric source ID as a string.' );
tt_assert_same( '904', $perspectives[1]['opponent_id'], 'Away perspective must point to the home team as opponent.' );
tt_assert_same( 'Augsburg', $perspectives[1]['team_name'], 'Away perspective must preserve the away team name.' );

tt_assert_same(
    array(),
    TactiTwin_Planning_Context::target_perspectives(
        array(
            'home_team_source_id' => '904',
            'away_team_source_id' => '904',
        )
    ),
    'A fixture cannot create perspectives when both source team IDs are identical.'
);

echo "Planning context perspective regression passed.\n";
