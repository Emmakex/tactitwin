<?php

$route = file_get_contents( __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-admin-route.php' );
$navigation = file_get_contents( __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-admin-navigation.php' );

if ( false === $route || false === $navigation ) {
    fwrite( STDERR, "Could not read admin navigation sources.\n" );
    exit( 1 );
}

$required = "require_once __DIR__ . '/class-tactitwin-admin-navigation.php';";
if ( false === strpos( $route, $required ) ) {
    fwrite( STDERR, "Admin route does not load canonical navigation.\n" );
    exit( 1 );
}
if ( false === strpos( $route, 'TactiTwin_Admin_Navigation::register();' ) ) {
    fwrite( STDERR, "Admin route does not register canonical navigation.\n" );
    exit( 1 );
}
if ( false === strpos( $navigation, "add_action( 'admin_menu', array( self::class, 'normalize' ), 999 );" ) ) {
    fwrite( STDERR, "Navigation normalizer must run after child menus at priority 999.\n" );
    exit( 1 );
}
if ( false === strpos( $navigation, "private const PARENT = 'tactitwin';" ) ) {
    fwrite( STDERR, "Navigation normalizer must remain scoped to TactiTwin parent.\n" );
    exit( 1 );
}

echo "Admin navigation wiring tests passed.\n";
