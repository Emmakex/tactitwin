<?php

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}

final class TactiTwin_DB {
    public static function table( string $name ): string {
        return 'tt_' . $name;
    }
}

final class TactiTwin_StatsBomb_Normalizer {
    public const SOURCE = 'statsbomb';
}

final class TactiTwin_Planning_Context {
    public static function current(): array {
        return array(
            'team_source_id' => '904',
            'opponent_source_id' => '901',
            'fixture_key' => 'fixture-test',
            'match' => array(
                'match_date' => '2024-05-18',
                'source_match_id' => '3895348',
            ),
            'fixture' => array(
                'match_date' => '2024-05-18',
                'source_match_id' => '3895348',
                'fixture_key' => 'fixture-test',
            ),
        );
    }
}

final class TactiTwin_Analysis_Service {
    public function match_summary( int $match_id ): array {
        return array(
            'teams' => array(
                '904' => array( 'passes' => 10 ),
                '901' => array( 'passes' => 8 ),
            ),
        );
    }
}

final class TactiTwin_Team_Profile {
    public static function aggregate( array $samples ): array {
        return array(
            'sample_count' => count( $samples ),
            'match_ids' => array_values( array_column( $samples, 'match_id' ) ),
        );
    }
}

final class TactiTwin_Test_WPDB {
    public array $queries = array();
    private string $prepared_sql = '';
    private array $prepared_args = array();

    public function prepare( string $sql, ...$args ): string {
        $this->prepared_sql = $sql;
        $this->prepared_args = $args;
        return $sql;
    }

    public function get_results( string $sql, $output ): array {
        $this->queries[] = array(
            'sql' => $this->prepared_sql,
            'args' => $this->prepared_args,
        );

        $team_id = (string) ( $this->prepared_args[1] ?? '' );
        if ( '904' === $team_id ) {
            return array(
                array(
                    'source_match_id' => '3895200',
                    'match_date' => '2024-05-11',
                    'home_team_source_id' => '904',
                    'away_team_source_id' => '901',
                ),
            );
        }

        return array(
            array(
                'source_match_id' => '3895199',
                'match_date' => '2024-05-10',
                'home_team_source_id' => '901',
                'away_team_source_id' => '904',
            ),
        );
    }
}

$GLOBALS['wpdb'] = new TactiTwin_Test_WPDB();

require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-data-preparation-service.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-profile-service.php';

$profiles = ( new TactiTwin_Profile_Service() )->matchup_profiles( 904, 901, 5 );

if ( 2 !== count( $GLOBALS['wpdb']->queries ) ) {
    fwrite( STDERR, "Expected two scoped profile queries.\n" );
    exit( 1 );
}

foreach ( $GLOBALS['wpdb']->queries as $query ) {
    if ( false === strpos( $query['sql'], 'm.match_date < %s' ) ) {
        fwrite( STDERR, "Profile query is missing the pre-match cutoff.\n" );
        exit( 1 );
    }
    if ( false === strpos( $query['sql'], 'm.source_match_id <> %s' ) ) {
        fwrite( STDERR, "Profile query is missing target-fixture exclusion.\n" );
        exit( 1 );
    }
    if ( ! in_array( '2024-05-18', $query['args'], true ) ) {
        fwrite( STDERR, "Profile query did not receive the fixture cutoff date.\n" );
        exit( 1 );
    }
    if ( ! in_array( '3895348', $query['args'], true ) ) {
        fwrite( STDERR, "Profile query did not receive the target match exclusion.\n" );
        exit( 1 );
    }
}

$scope = $profiles['temporal_scope'] ?? array();
if ( '2024-05-18' !== ( $scope['before_date'] ?? '' ) || '3895348' !== ( $scope['exclude_match_id'] ?? '' ) ) {
    fwrite( STDERR, "Matchup profiles did not retain the normalized temporal scope.\n" );
    exit( 1 );
}
if ( empty( $scope['target_fixture_excluded'] ) || empty( $scope['future_matches_excluded'] ) ) {
    fwrite( STDERR, "Temporal-scope guard flags are missing.\n" );
    exit( 1 );
}

echo "profile temporal scope ok\n";
