<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-i18n.php';
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-evidence-coverage.php';

final class TactiTwin_DB {
    public static function table( string $name ): string {
        return 'wp_tactitwin_' . $name;
    }
}

final class TactiTwin_StatsBomb_Normalizer {
    public const SOURCE = 'statsbomb';
}

final class TT_Coverage_WPDB {
    public array $last_args = array();

    public function prepare( string $sql, ...$args ): string {
        $this->last_args = $args;
        return $sql;
    }

    public function get_col( string $sql ): array {
        $dates = array(
            '101' => '2024-05-12',
            '102' => '2024-05-05',
            '103' => '2024-04-28',
            '104' => '2024-04-21',
            '105' => '2024-04-14',
            '201' => '2024-01-13',
        );
        $ids = array_slice( $this->last_args, 1 );
        $result = array();
        foreach ( $ids as $id ) {
            if ( isset( $dates[ (string) $id ] ) ) {
                $result[] = $dates[ (string) $id ];
            }
        }
        return $result;
    }
}

$GLOBALS['wpdb'] = new TT_Coverage_WPDB();
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-evidence-coverage-transient-bridge.php';

function tt_plan_coverage_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$value = array(
    'language' => 'es',
    'plan' => array(
        'plan_id' => 'match-plan',
        'language' => 'es',
        'team' => array( 'source_id' => '904', 'name' => 'Bayer Leverkusen' ),
        'opponent' => array( 'source_id' => '172', 'name' => 'Augsburg' ),
        'coverage' => array( 'effective_matches' => 1, 'status' => 'low' ),
        'summary' => array( 'average_risk' => 36.7 ),
        'priorities' => array( array( 'scenario_id' => 'build-up-vs-high-press', 'priority_score' => 71.3 ) ),
        'method' => array( 'type' => 'deterministic-match-plan-composition' ),
    ),
    'preparation' => array(
        'team' => array(
            'team_id' => 904,
            'target_matches' => 5,
            'ready_matches' => 5,
            'existing' => array( 101, 102, 103, 104, 105 ),
            'imported' => array(),
            'failed' => array(),
        ),
        'opponent' => array(
            'team_id' => 172,
            'target_matches' => 5,
            'ready_matches' => 1,
            'existing' => array( 201 ),
            'imported' => array(),
            'failed' => array(),
        ),
    ),
);

$source_summary = $value['plan']['summary'];
$source_priorities = $value['plan']['priorities'];
$enriched = TactiTwin_Evidence_Coverage_Transient_Bridge::enrich( $value, 600 );
$coverage = $enriched['plan']['coverage'];

tt_plan_coverage_assert( 5 === $coverage['team_matches'], 'Match Plan exposes our-team sample count' );
tt_plan_coverage_assert( 1 === $coverage['opponent_matches'], 'Match Plan exposes opponent sample count' );
tt_plan_coverage_assert( 1 === $coverage['effective_matches'], 'Match Plan keeps conservative effective coverage' );
tt_plan_coverage_assert( 'opponent' === $coverage['limiting_side'], 'Match Plan identifies opponent as limiting side' );
tt_plan_coverage_assert( '2024-04-14' === $coverage['team_sample']['oldest_match'], 'Match Plan exposes team oldest sample date' );
tt_plan_coverage_assert( '2024-05-12' === $coverage['team_sample']['newest_match'], 'Match Plan exposes team newest sample date' );
tt_plan_coverage_assert( array( '201' ) === $coverage['opponent_sample']['match_ids'], 'Match Plan exposes exact opponent match IDs' );
tt_plan_coverage_assert( $source_summary === $enriched['plan']['summary'], 'Coverage enrichment does not change Match Plan summary' );
tt_plan_coverage_assert( $source_priorities === $enriched['plan']['priorities'], 'Coverage enrichment does not change tactical priorities' );
tt_plan_coverage_assert( 'v1.35-per-side-auditable-samples' === $enriched['plan']['method']['coverage_transparency'], 'Match Plan declares coverage transparency method' );

echo "Match Plan coverage transparency tests passed.\n";
