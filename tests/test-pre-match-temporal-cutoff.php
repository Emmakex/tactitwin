<?php

define( 'ABSPATH', __DIR__ );
if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}

function tt_temporal_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

final class TactiTwin_DB {
    public static function table( string $name ): string {
        return 'wp_tactitwin_' . $name;
    }
}

final class TactiTwin_StatsBomb_Normalizer {
    public const SOURCE = 'statsbomb';
}

final class TactiTwin_StatsBomb_Importer {
    public function import_match_events( int $match_id ): array {
        throw new RuntimeException( 'Importer should not be reached in this regression.' );
    }
}

final class TactiTwin_Planning_Context {
    public static array $context = array();

    public static function current(): array {
        return self::$context;
    }
}

final class TT_Temporal_WPDB {
    public array $queries = array();

    public function prepare( string $query, ...$args ): string {
        foreach ( $args as $arg ) {
            $replacement = "'" . str_replace( "'", "''", (string) $arg ) . "'";
            $query = preg_replace( '/%s/', $replacement, $query, 1 );
        }
        return $query;
    }

    public function get_results( string $query, $output ): array {
        $this->queries[] = $query;
        return array();
    }
}

require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-data-preparation-service.php';

TactiTwin_Planning_Context::$context = array(
    'team_source_id' => '100',
    'opponent_source_id' => '200',
    'fixture_key' => 'fixture-test-key',
    'fixture' => array(
        'source' => 'statsbomb',
        'source_match_id' => '3895348',
        'match_date' => '2024-05-18',
        'fixture_key' => 'fixture-test-key',
    ),
    'match' => array(
        'source_match_id' => '3895348',
        'match_date' => '2024-05-18',
    ),
);

$wpdb = new TT_Temporal_WPDB();
$service = new TactiTwin_Data_Preparation_Service();

$thrown = false;
try {
    $service->prepare_matchup( 100, 200, 5 );
} catch ( RuntimeException $e ) {
    $thrown = true;
}
tt_temporal_assert( $thrown, 'empty catalogue still stops preparation after building the scoped query' );
tt_temporal_assert( 1 === count( $wpdb->queries ), 'one scoped team query is issued before the empty-catalogue stop' );
$scoped_query = $wpdb->queries[0];
tt_temporal_assert( str_contains( $scoped_query, "m.match_date < '2024-05-18'" ), 'active fixture date creates a strict historical cutoff' );
tt_temporal_assert( str_contains( $scoped_query, "m.source_match_id <> '3895348'" ), 'active fixture source match ID is explicitly excluded' );

$wpdb = new TT_Temporal_WPDB();
$service = new TactiTwin_Data_Preparation_Service();
$thrown = false;
try {
    $service->prepare_matchup( 300, 400, 5 );
} catch ( RuntimeException $e ) {
    $thrown = true;
}
tt_temporal_assert( $thrown, 'unrelated manual matchup still stops on an empty catalogue' );
tt_temporal_assert( 1 === count( $wpdb->queries ), 'one unrelated matchup query is issued' );
$unscoped_query = $wpdb->queries[0];
tt_temporal_assert( ! str_contains( $unscoped_query, 'm.match_date <' ), 'active fixture does not silently scope a different manual matchup' );
tt_temporal_assert( ! str_contains( $unscoped_query, 'm.source_match_id <>' ), 'active fixture exclusion does not leak into a different manual matchup' );

$wpdb = new TT_Temporal_WPDB();
$service = new TactiTwin_Data_Preparation_Service();
$thrown = false;
try {
    $service->ensure_team_sample( 100, 5, array( 'before_date' => '2024-99-99' ) );
} catch ( InvalidArgumentException $e ) {
    $thrown = true;
}
tt_temporal_assert( $thrown, 'invalid explicit cutoff dates are rejected' );
tt_temporal_assert( 0 === count( $wpdb->queries ), 'invalid cutoffs fail before any database query' );

echo "Pre-match temporal cutoff tests passed.\n";
