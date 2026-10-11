<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Schema_Guard {
    public static function register(): void {
        add_action( 'init', array( self::class, 'maybe_upgrade' ), 1 );
    }

    public static function maybe_upgrade(): void {
        if ( TactiTwin_DB::SCHEMA_VERSION !== (string) get_option( 'tactitwin_schema_version', '' ) ) {
            TactiTwin_DB::install();
        }
    }
}
