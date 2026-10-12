<?php

$source = file_get_contents( __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-reviewed-twin-freeze-bridge.php' );
if ( false === $source ) {
    fwrite( STDERR, "Could not read Reviewed Twin freeze bridge.\n" );
    exit( 1 );
}

if ( false === strpos( $source, "array( self::class, 'attach_reviewed_twin' ), 15, 2" ) ) {
    fwrite( STDERR, "Reviewed Twin attachment filter must run at priority 15.\n" );
    exit( 1 );
}
if ( false === strpos( $source, "array( self::class, 'annotate_source' ), 25, 2" ) ) {
    fwrite( STDERR, "Twin source annotation filter must run at priority 25.\n" );
    exit( 1 );
}

$existing = file_get_contents( __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-frozen-twin-handoff-admin-bridge.php' );
if ( false === $existing || false === strpos( $existing, "array( self::class, 'attach_to_result' ),\n            20," ) ) {
    fwrite( STDERR, "Existing Frozen Twin fallback must remain at priority 20.\n" );
    exit( 1 );
}

echo "reviewed/fallback filter order ok\n";
