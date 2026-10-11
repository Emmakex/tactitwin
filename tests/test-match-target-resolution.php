<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-fixture-identity.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-match-target-service.php';

function tt_v121_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$match = array(
    'id' => 77,
    'source' => 'statsbomb',
    'source_match_id' => '3895292',
    'source_competition_id' => '9',
    'source_season_id' => '281',
    'match_date' => '2026-10-21',
    'kick_off' => '20:00:00',
    'home_team_source_id' => '100',
    'away_team_source_id' => '200',
    'home_team_name' => 'Home FC',
    'away_team_name' => 'Away FC',
    'match_week' => '10',
    'match_status' => 'scheduled',
);

$home = TactiTwin_Match_Target_Service::resolve_row( $match, '100' );
tt_v121_assert( 77 === $home['target_match_row_id'], 'target keeps the internal imported-match row ID for UI resolution' );
tt_v121_assert( 'home' === $home['perspective'], 'home participant resolves to home perspective' );
tt_v121_assert( '100' === $home['team_source_id'], 'home participant remains our team' );
tt_v121_assert( '200' === $home['opponent_source_id'], 'away participant becomes the opponent' );
tt_v121_assert( 'provider-match-id' === $home['fixture']['identity_mode'], 'imported provider target uses provider-match-id fixture identity' );
tt_v121_assert( 'statsbomb' === $home['fixture']['source'], 'fixture inherits provider source from the imported target' );
tt_v121_assert( '3895292' === $home['fixture']['source_match_id'], 'fixture inherits provider match ID from the imported target' );
tt_v121_assert( '9' === $home['fixture']['source_competition_id'], 'fixture carries competition context for display and audit' );
tt_v121_assert( '281' === $home['fixture']['source_season_id'], 'fixture carries season context for display and audit' );
tt_v121_assert( '2026-10-21' === $home['fixture']['match_date'], 'fixture carries imported match date' );
tt_v121_assert( '20:00:00' === $home['fixture']['kick_off'], 'fixture carries imported kick-off' );
tt_v121_assert( '10' === $home['fixture']['match_week'], 'fixture carries imported match week' );

$away = TactiTwin_Match_Target_Service::resolve_row( $match, '200' );
tt_v121_assert( 'away' === $away['perspective'], 'away participant resolves to away perspective' );
tt_v121_assert( '100' === $away['opponent_source_id'], 'home participant becomes opponent from away perspective' );
tt_v121_assert( $home['fixture']['fixture_key'] !== $away['fixture']['fixture_key'], 'opposite team perspectives remain separate approval scopes' );

$metadata_changed = $match;
$metadata_changed['match_date'] = '2026-10-22';
$metadata_changed['kick_off'] = '21:00:00';
$metadata_changed['match_week'] = '11';
$home_after_metadata_change = TactiTwin_Match_Target_Service::resolve_row( $metadata_changed, '100' );
tt_v121_assert(
    $home['fixture']['fixture_key'] === $home_after_metadata_change['fixture']['fixture_key'],
    'provider match identity remains stable when descriptive scheduling metadata changes'
);

$thrown = false;
try {
    TactiTwin_Match_Target_Service::resolve_row( $match, '999' );
} catch ( InvalidArgumentException $e ) {
    $thrown = true;
}
tt_v121_assert( true === $thrown, 'target resolution rejects a team that is not a match participant' );

$missing_match_id = $match;
$missing_match_id['source_match_id'] = '';
$thrown = false;
try {
    TactiTwin_Match_Target_Service::resolve_row( $missing_match_id, '100' );
} catch ( InvalidArgumentException $e ) {
    $thrown = true;
}
tt_v121_assert( true === $thrown, 'canonical imported target requires a provider match ID' );

$missing_participant = $match;
$missing_participant['away_team_source_id'] = '';
$thrown = false;
try {
    TactiTwin_Match_Target_Service::resolve_row( $missing_participant, '100' );
} catch ( InvalidArgumentException $e ) {
    $thrown = true;
}
tt_v121_assert( true === $thrown, 'canonical imported target requires both participants' );

echo "Match target resolution tests passed.\n";
