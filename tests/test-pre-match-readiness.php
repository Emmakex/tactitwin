<?php

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}

final class TactiTwin_DB {
    public static function table( string $name ): string {
        return 'wp_tactitwin_' . $name;
    }
}
final class TactiTwin_StatsBomb_Normalizer {
    public const SOURCE = 'statsbomb';
}
final class TT_Readiness_WPDB {
    public array $prepared_calls = array();
    private array $last_args = array();

    public function prepare( string $sql, ...$args ): string {
        $this->last_args = $args;
        $this->prepared_calls[] = array( 'sql' => $sql, 'args' => $args );
        return $sql;
    }

    public function get_results( string $sql, $mode = null ): array {
        $team = (string) ( $this->last_args[1] ?? '' );
        if ( '904' === $team ) {
            return array(
                array( 'source_match_id' => '3895338', 'match_date' => '2024-05-12' ),
                array( 'source_match_id' => '3895328', 'match_date' => '2024-05-05' ),
                array( 'source_match_id' => '3895318', 'match_date' => '2024-04-28' ),
                array( 'source_match_id' => '3895308', 'match_date' => '2024-04-21' ),
                array( 'source_match_id' => '3895298', 'match_date' => '2024-04-14' ),
            );
        }
        if ( '172' === $team ) {
            return array(
                array( 'source_match_id' => '3895220', 'match_date' => '2024-01-13' ),
            );
        }
        return array();
    }
}

$GLOBALS['wpdb'] = new TT_Readiness_WPDB();
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-pre-match-readiness.php';

function tt_readiness_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$context = array(
    'team_source_id' => '904',
    'opponent_source_id' => '172',
    'fixture_key' => str_repeat( 'a', 64 ),
    'perspective' => 'home',
    'match' => array(
        'source' => 'statsbomb',
        'source_match_id' => '3895348',
        'match_date' => '2024-05-18',
        'home_team_source_id' => '904',
        'away_team_source_id' => '172',
    ),
    'fixture' => array(
        'source' => 'statsbomb',
        'source_match_id' => '3895348',
        'match_date' => '2024-05-18',
    ),
);

$result = TactiTwin_Pre_Match_Readiness::inspect( $context );

tt_readiness_assert( 5 === $result['team_matches'], 'our-team eligible sample is five' );
tt_readiness_assert( 1 === $result['opponent_matches'], 'opponent eligible sample is one' );
tt_readiness_assert( 1 === $result['effective_matches'], 'effective readiness is conservative minimum' );
tt_readiness_assert( 'opponent' === $result['limiting_side'], 'opponent is limiting side' );
tt_readiness_assert( 'low' === $result['status'], 'one effective match is low evidence volume' );
tt_readiness_assert( '2024-04-14' === $result['team']['oldest_match'], 'team oldest eligible date exposed' );
tt_readiness_assert( '2024-05-12' === $result['team']['newest_match'], 'team newest eligible date exposed' );
tt_readiness_assert( array( '3895220' ) === $result['opponent']['match_ids'], 'exact opponent evidence IDs exposed' );
tt_readiness_assert( '2024-05-18' === $result['temporal_scope']['before_date'], 'fixture date is explicit cutoff' );
tt_readiness_assert( '3895348' === $result['temporal_scope']['exclude_match_id'], 'target fixture is explicitly excluded' );
tt_readiness_assert( true === $result['temporal_scope']['future_matches_excluded'], 'future-match exclusion declared' );
tt_readiness_assert( true === $result['temporal_scope']['target_fixture_excluded'], 'target-match exclusion declared' );
tt_readiness_assert( false === $result['method']['imports_data'], 'readiness inspection does not import data' );
tt_readiness_assert( false === $result['method']['calculates_tactical_score'], 'readiness inspection creates no tactical score' );

tt_readiness_assert( 2 === count( $GLOBALS['wpdb']->prepared_calls ), 'one read-only sample query runs per side' );
foreach ( $GLOBALS['wpdb']->prepared_calls as $call ) {
    tt_readiness_assert( false !== strpos( $call['sql'], 'm.match_date < %s' ), 'query enforces fixture-date cutoff' );
    tt_readiness_assert( false !== strpos( $call['sql'], 'm.source_match_id <> %s' ), 'query explicitly excludes target fixture' );
    tt_readiness_assert( 'statsbomb' === $call['args'][0], 'query remains source-scoped' );
    tt_readiness_assert( '2024-05-18' === $call['args'][3], 'query receives exact cutoff date' );
    tt_readiness_assert( '3895348' === end( $call['args'] ), 'query receives exact excluded match ID' );
}

$bad = $context;
$bad['team_source_id'] = '172';
$bad['opponent_source_id'] = '172';
$thrown = false;
try {
    TactiTwin_Pre_Match_Readiness::inspect( $bad );
} catch ( InvalidArgumentException $e ) {
    $thrown = true;
}
tt_readiness_assert( $thrown, 'same-team readiness context is rejected' );

echo "Pre-match readiness tests passed.\n";
