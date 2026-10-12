<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Admin_Route {
    public static function register(): void {
        add_action( 'template_redirect', array( self::class, 'redirect_legacy_path' ), 1 );
    }

    public static function canonical_url( string $slug ): string {
        $slug = strtolower( trim( $slug ) );
        if ( 1 !== preg_match( '/^tactitwin(?:-[a-z0-9-]+)?$/', $slug ) ) {
            throw new InvalidArgumentException( 'Invalid TactiTwin admin page slug.' );
        }

        return add_query_arg( 'page', sanitize_key( $slug ), admin_url( 'admin.php' ) );
    }

    public static function legacy_slug_from_uri( string $request_uri ): string {
        $path = wp_parse_url( $request_uri, PHP_URL_PATH );
        if ( ! is_string( $path ) || '' === $path ) {
            return '';
        }

        if ( 1 !== preg_match( '#/wp-admin/(tactitwin(?:-[a-z0-9-]+)?)/?$#', $path, $matches ) ) {
            return '';
        }

        return sanitize_key( (string) $matches[1] );
    }

    public static function redirect_legacy_path(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '';
        $slug = self::legacy_slug_from_uri( $request_uri );
        if ( '' === $slug ) {
            return;
        }

        wp_safe_redirect( self::canonical_url( $slug ) );
        exit;
    }
}
