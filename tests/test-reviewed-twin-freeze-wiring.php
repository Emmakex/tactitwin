<?php

$plugin = file_get_contents( __DIR__ . '/../wordpress-plugin/tactitwin.php' );
if ( false === $plugin ) {
    fwrite( STDERR, "Could not read plugin bootstrap.\n" );
    exit( 1 );
}

$required = "require_once TACTITWIN_DIR . 'includes/class-tactitwin-reviewed-twin-freeze-bridge.php';";
$registered = 'TactiTwin_Reviewed_Twin_Freeze_Bridge::register();';

if ( false === strpos( $plugin, $required ) ) {
    fwrite( STDERR, "Reviewed Twin freeze bridge is not required by plugin bootstrap.\n" );
    exit( 1 );
}
if ( false === strpos( $plugin, $registered ) ) {
    fwrite( STDERR, "Reviewed Twin freeze bridge is not registered by plugin bootstrap.\n" );
    exit( 1 );
}

$reviewed_pos = strpos( $plugin, $registered );
$fallback_pos = strpos( $plugin, 'TactiTwin_Frozen_Twin_Handoff_Admin_Bridge::register();' );
if ( false === $fallback_pos || $reviewed_pos > $fallback_pos ) {
    fwrite( STDERR, "Reviewed Twin freeze bridge must register before fallback bridge.\n" );
    exit( 1 );
}

echo "reviewed Twin freeze bootstrap wiring ok\n";
