<?php

$route = file_get_contents( __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-admin-route.php' );
$admin = file_get_contents( __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-pre-match-readiness-admin.php' );

if ( false === $route || false === $admin ) {
    fwrite( STDERR, "Could not read v1.38 admin sources.\n" );
    exit( 1 );
}

$required_core = "require_once __DIR__ . '/class-tactitwin-pre-match-readiness.php';";
$required_admin = "require_once __DIR__ . '/class-tactitwin-pre-match-readiness-admin.php';";
if ( false === strpos( $route, $required_core ) || false === strpos( $route, $required_admin ) ) {
    fwrite( STDERR, "Admin route does not load v1.38 readiness classes.\n" );
    exit( 1 );
}
if ( false === strpos( $route, 'TactiTwin_Pre_Match_Readiness_Admin::register();' ) ) {
    fwrite( STDERR, "Admin route does not register v1.38 readiness UI.\n" );
    exit( 1 );
}
if ( false === strpos( $admin, "array( 'tactitwin', 'tactitwin-twin-lab' )" ) ) {
    fwrite( STDERR, "Readiness UI must remain scoped to Workspace and Twin Lab.\n" );
    exit( 1 );
}
if ( false === strpos( $admin, "page=tactitwin-twin-lab" ) ) {
    fwrite( STDERR, "Workspace readiness UI must expose Tactical Twin Lab as a workflow step.\n" );
    exit( 1 );
}
if ( false === strpos( $admin, "page=tactitwin-data" ) && false !== strpos( $admin, 'automatic_activation' ) ) {
    fwrite( STDERR, "Readiness UI unexpectedly contains tactical activation behavior.\n" );
    exit( 1 );
}

echo "Pre-match readiness wiring tests passed.\n";
