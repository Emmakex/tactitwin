<?php

function tt_v140_release_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$plugin = file_get_contents( __DIR__ . '/../wordpress-plugin/tactitwin.php' );
$workflow = file_get_contents( __DIR__ . '/../.github/workflows/test-candidate.yml' );
$db = file_get_contents( __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-db.php' );

tt_v140_release_assert( false !== $plugin, 'plugin bootstrap is readable' );
tt_v140_release_assert( false !== $workflow, 'test-candidate workflow is readable' );
tt_v140_release_assert( false !== $db, 'database contract is readable' );

preg_match( '/^\s*\* Version:\s*([^\r\n]+)/m', $plugin, $header_match );
preg_match( "/define\( 'TACTITWIN_VERSION', '([^']+)' \);/", $plugin, $constant_match );
preg_match( "/SCHEMA_VERSION = '([^']+)'/", $db, $schema_match );

$header_version = trim( (string) ( $header_match[1] ?? '' ) );
$constant_version = trim( (string) ( $constant_match[1] ?? '' ) );
$schema_version = trim( (string) ( $schema_match[1] ?? '' ) );

tt_v140_release_assert( '0.9.0-rc1' === $header_version, 'plugin header exposes the expected MVP RC version' );
tt_v140_release_assert( $header_version === $constant_version, 'plugin header and runtime version constant match' );
tt_v140_release_assert( ! str_contains( $header_version, 'dev' ), 'release candidate is not labelled dev' );
tt_v140_release_assert( '' !== $schema_version, 'schema version is explicit' );
tt_v140_release_assert( str_contains( $workflow, 'BUILD-MANIFEST.json' ), 'ZIP includes an internal build manifest' );
tt_v140_release_assert( str_contains( $workflow, 'release-manifest.json' ), 'artifact exposes a release manifest' );
tt_v140_release_assert( str_contains( $workflow, 'SHA256SUMS.txt' ), 'artifact exposes SHA-256 sums' );
tt_v140_release_assert( str_contains( $workflow, 'zip_sha256' ), 'release manifest binds the ZIP digest' );
tt_v140_release_assert( str_contains( $workflow, 'cannot be packaged with a -dev plugin version' ), 'workflow rejects dev versions' );

echo "v1.40 release candidate metadata tests passed.\n";
