<?php

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

$GLOBALS['tactitwin_test_actions'] = array();

if ( ! function_exists( 'add_action' ) ) {
    function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
        $GLOBALS['tactitwin_test_actions'][] = array(
            'hook'          => (string) $hook,
            'callback'      => $callback,
            'priority'      => (int) $priority,
            'accepted_args' => (int) $accepted_args,
        );
        return true;
    }
}

require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin.php';

TactiTwin::instance();

$admin_menu_hooks = array_values(
    array_filter(
        $GLOBALS['tactitwin_test_actions'],
        static fn( array $item ): bool => 'admin_menu' === $item['hook']
    )
);

if ( 1 !== count( $admin_menu_hooks ) ) {
    fwrite( STDERR, "Expected exactly one TactiTwin parent admin_menu hook.\n" );
    exit( 1 );
}

$parent = $admin_menu_hooks[0];
if ( 5 !== $parent['priority'] ) {
    fwrite( STDERR, 'Expected TactiTwin parent menu priority 5, got ' . $parent['priority'] . ".\n" );
    exit( 1 );
}

if ( ! is_array( $parent['callback'] ) || 'register_admin_menu' !== ( $parent['callback'][1] ?? '' ) ) {
    fwrite( STDERR, "Unexpected parent menu callback.\n" );
    exit( 1 );
}

echo "admin menu registration order ok\n";
