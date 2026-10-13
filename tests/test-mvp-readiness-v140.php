<?php

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-mvp-readiness.php';

function tt_v140_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$healthy_empty = TactiTwin_MVP_Readiness::summarize( array(
    'plugin_version' => '0.9.0-rc1',
    'php_version' => '8.1.20',
    'wordpress_version' => '6.8.0',
    'expected_schema' => '0.5.0',
    'installed_schema' => '0.5.0',
    'counts' => array( 'teams'=>0, 'matches'=>0, 'events'=>0 ),
    'database_error' => '',
) );
tt_v140_assert( 'ready' === $healthy_empty['status'], 'empty but healthy install remains runtime-ready' );
tt_v140_assert( 3 === count( $healthy_empty['data']['warnings'] ), 'missing football data is warning-only' );
tt_v140_assert( false === $healthy_empty['data']['minimum_matchup_data_present'], 'empty install is not matchup-data ready' );
tt_v140_assert( empty( $healthy_empty['blockers'] ), 'data warnings are not runtime blockers' );

$healthy_data = TactiTwin_MVP_Readiness::summarize( array(
    'plugin_version' => '0.9.0-rc1',
    'php_version' => '8.3.7',
    'wordpress_version' => '6.8.0',
    'expected_schema' => '0.5.0',
    'installed_schema' => '0.5.0',
    'counts' => array( 'teams'=>12, 'matches'=>34, 'events'=>42000 ),
    'database_error' => '',
) );
tt_v140_assert( 'ready' === $healthy_data['status'], 'healthy populated install is ready' );
tt_v140_assert( true === $healthy_data['data']['minimum_matchup_data_present'], 'basic matchup dataset is detected' );
tt_v140_assert( empty( $healthy_data['data']['warnings'] ), 'populated dataset has no basic data warnings' );

$old_php = TactiTwin_MVP_Readiness::summarize( array(
    'plugin_version' => '0.9.0-rc1',
    'php_version' => '8.0.30',
    'wordpress_version' => '6.8.0',
    'expected_schema' => '0.5.0',
    'installed_schema' => '0.5.0',
    'counts' => array( 'teams'=>2, 'matches'=>2, 'events'=>100 ),
) );
tt_v140_assert( 'needs_attention' === $old_php['status'], 'unsupported PHP blocks release-candidate readiness' );
tt_v140_assert( 'php-version' === $old_php['blockers'][0]['id'], 'PHP blocker is explicit' );

$schema_mismatch = TactiTwin_MVP_Readiness::summarize( array(
    'plugin_version' => '0.9.0-rc1',
    'php_version' => '8.2.0',
    'wordpress_version' => '6.8.0',
    'expected_schema' => '0.5.0',
    'installed_schema' => '0.4.0',
    'counts' => array( 'teams'=>2, 'matches'=>2, 'events'=>100 ),
) );
tt_v140_assert( 'needs_attention' === $schema_mismatch['status'], 'schema mismatch blocks readiness' );
tt_v140_assert( 'schema-version' === $schema_mismatch['blockers'][0]['id'], 'schema blocker is explicit' );

$db_error = TactiTwin_MVP_Readiness::summarize( array(
    'plugin_version' => '0.9.0-rc1',
    'php_version' => '8.2.0',
    'wordpress_version' => '6.8.0',
    'expected_schema' => '0.5.0',
    'installed_schema' => '0.5.0',
    'counts' => array(),
    'database_error' => 'table missing',
) );
tt_v140_assert( 'needs_attention' === $db_error['status'], 'database read errors block readiness' );
tt_v140_assert( true === $db_error['method']['operational_diagnostics_only'], 'diagnostics scope is explicit' );
tt_v140_assert( false === $db_error['method']['calculates_tactical_score'], 'preflight never calculates tactical scores' );
tt_v140_assert( false === $db_error['method']['mutates_product_state'], 'preflight is read-only' );

echo "v1.40 MVP readiness tests passed.\n";
