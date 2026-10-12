<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-fixture-identity.php';

function tt_kickoff_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$provider_fractional = TactiTwin_Fixture_Identity::normalize(
    array(
        'source' => 'statsbomb',
        'source_match_id' => '3895292',
        'match_date' => '2026-10-20',
        'kick_off' => '20:00:00.000',
    ),
    '100',
    '200'
);
$provider_plain = TactiTwin_Fixture_Identity::normalize(
    array(
        'source' => 'statsbomb',
        'source_match_id' => '3895292',
        'match_date' => '2026-10-20',
        'kick_off' => '20:00:00',
    ),
    '100',
    '200'
);

// StatsBomb Open Data may expose kick_off with fractional seconds. The provider
// match ID remains the identity anchor, while descriptive time metadata is
// normalized to the same canonical clock representation.
tt_kickoff_assert( '20:00:00' === $provider_fractional['kick_off'], 'fractional provider kick-off normalizes to HH:MM:SS' );
tt_kickoff_assert( $provider_fractional['fixture_key'] === $provider_plain['fixture_key'], 'fractional seconds do not alter provider fixture identity' );

$scheduled_fractional = TactiTwin_Fixture_Identity::normalize(
    array(
        'source' => 'manual',
        'match_date' => '2026-10-20',
        'kick_off' => '7:05:09.123456',
        'source_competition_id' => 'liga',
        'source_season_id' => '2026',
        'match_week' => '8',
    ),
    '100',
    '200'
);
$scheduled_plain = TactiTwin_Fixture_Identity::normalize(
    array(
        'source' => 'manual',
        'match_date' => '2026-10-20',
        'kick_off' => '07:05:09',
        'source_competition_id' => 'liga',
        'source_season_id' => '2026',
        'match_week' => '8',
    ),
    '100',
    '200'
);

tt_kickoff_assert( '07:05:09' === $scheduled_fractional['kick_off'], 'fractional clock is canonicalized deterministically' );
tt_kickoff_assert( $scheduled_fractional['fixture_key'] === $scheduled_plain['fixture_key'], 'fractional seconds canonicalize to the same scheduled fixture identity' );

$thrown = false;
try {
    TactiTwin_Fixture_Identity::normalize(
        array( 'source'=>'manual', 'match_date'=>'2026-10-20', 'kick_off'=>'25:00:00.000' ),
        '100',
        '200'
    );
} catch ( InvalidArgumentException $e ) {
    $thrown = true;
}
tt_kickoff_assert( $thrown, 'invalid fractional provider clock is still rejected' );

echo "StatsBomb kick-off normalization tests passed.\n";
