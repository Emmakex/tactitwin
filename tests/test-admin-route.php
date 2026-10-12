<?php

define( 'ABSPATH', __DIR__ );

function sanitize_key( $value ) {
    return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) );
}

function wp_parse_url( $url, $component = -1 ) {
    return parse_url( $url, $component );
}

function admin_url( $path = '' ) {
    return 'https://emmake.com/extensiones/wp-admin/' . ltrim( (string) $path, '/' );
}

function add_query_arg( $key, $value, $url ) {
    $separator = str_contains( $url, '?' ) ? '&' : '?';
    return $url . $separator . rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
}

require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-admin-route.php';

function tt_admin_route_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$legacy = '/extensiones/wp-admin/tactitwin-match-plan';
tt_admin_route_assert(
    'tactitwin-match-plan' === TactiTwin_Admin_Route::legacy_slug_from_uri( $legacy ),
    'subdirectory legacy Match Plan path is recognized'
);

tt_admin_route_assert(
    'tactitwin-twin-lab' === TactiTwin_Admin_Route::legacy_slug_from_uri( '/extensiones/wp-admin/tactitwin-twin-lab/' ),
    'trailing-slash TactiTwin path is recognized'
);

tt_admin_route_assert(
    '' === TactiTwin_Admin_Route::legacy_slug_from_uri( '/extensiones/wp-admin/admin.php?page=tactitwin-match-plan' ),
    'canonical admin.php route is not treated as legacy and cannot loop'
);

tt_admin_route_assert(
    '' === TactiTwin_Admin_Route::legacy_slug_from_uri( '/extensiones/wp-admin/edit.php' ),
    'unrelated WordPress admin route is ignored'
);

tt_admin_route_assert(
    'https://emmake.com/extensiones/wp-admin/admin.php?page=tactitwin-match-plan' === TactiTwin_Admin_Route::canonical_url( 'tactitwin-match-plan' ),
    'canonical Match Plan URL preserves the WordPress subdirectory'
);

$thrown = false;
try {
    TactiTwin_Admin_Route::canonical_url( 'tactitwin/evil' );
} catch ( InvalidArgumentException $e ) {
    $thrown = true;
}
tt_admin_route_assert( $thrown, 'invalid admin slug is rejected instead of normalized into a valid-looking route' );

echo "Admin route tests passed.\n";
