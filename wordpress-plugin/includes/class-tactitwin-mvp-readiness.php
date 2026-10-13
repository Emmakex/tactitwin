<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Release-candidate preflight. This is operational diagnostics only: it does
 * not calculate tactical readiness, import data or mutate product state.
 */
final class TactiTwin_MVP_Readiness {
    public const MIN_PHP = '8.1.0';

    public static function register(): void {
        add_action( 'rest_api_init', array( self::class, 'register_rest_route' ) );
    }

    public static function register_rest_route(): void {
        register_rest_route(
            'tactitwin/v1',
            '/system/readiness',
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array( self::class, 'rest_readiness' ),
                'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
            )
        );
    }

    public static function rest_readiness(): WP_REST_Response {
        return new WP_REST_Response( self::inspect_runtime(), 200 );
    }

    public static function inspect_runtime(): array {
        global $wpdb, $wp_version;

        $database_error = '';
        $counts = array();
        try {
            $counts = TactiTwin_DB::counts();
            $database_error = trim( (string) ( $wpdb->last_error ?? '' ) );
        } catch ( Throwable $e ) {
            $database_error = $e->getMessage();
        }

        return self::summarize(
            array(
                'plugin_version' => defined( 'TACTITWIN_VERSION' ) ? TACTITWIN_VERSION : 'unknown',
                'php_version' => PHP_VERSION,
                'wordpress_version' => isset( $wp_version ) ? (string) $wp_version : 'unknown',
                'expected_schema' => TactiTwin_DB::SCHEMA_VERSION,
                'installed_schema' => function_exists( 'get_option' ) ? (string) get_option( 'tactitwin_schema_version', '' ) : '',
                'counts' => $counts,
                'database_error' => $database_error,
            )
        );
    }

    public static function summarize( array $signals ): array {
        $plugin_version = trim( (string) ( $signals['plugin_version'] ?? 'unknown' ) );
        $php_version = trim( (string) ( $signals['php_version'] ?? '' ) );
        $wordpress_version = trim( (string) ( $signals['wordpress_version'] ?? 'unknown' ) );
        $expected_schema = trim( (string) ( $signals['expected_schema'] ?? '' ) );
        $installed_schema = trim( (string) ( $signals['installed_schema'] ?? '' ) );
        $database_error = trim( (string) ( $signals['database_error'] ?? '' ) );
        $counts = is_array( $signals['counts'] ?? null ) ? $signals['counts'] : array();

        $blockers = array();
        if ( '' === $php_version || version_compare( $php_version, self::MIN_PHP, '<' ) ) {
            $blockers[] = array(
                'id' => 'php-version',
                'message' => 'PHP ' . self::MIN_PHP . ' or newer is required.',
            );
        }
        if ( '' === $expected_schema || $installed_schema !== $expected_schema ) {
            $blockers[] = array(
                'id' => 'schema-version',
                'message' => 'Installed TactiTwin schema does not match the plugin schema.',
            );
        }
        if ( '' !== $database_error ) {
            $blockers[] = array(
                'id' => 'database-query',
                'message' => 'TactiTwin database tables could not be read cleanly.',
            );
        }

        $warnings = array();
        $teams = max( 0, (int) ( $counts['teams'] ?? 0 ) );
        $matches = max( 0, (int) ( $counts['matches'] ?? 0 ) );
        $events = max( 0, (int) ( $counts['events'] ?? 0 ) );
        if ( $teams < 2 ) {
            $warnings[] = array(
                'id' => 'insufficient-teams',
                'message' => 'Import at least two teams before running matchup workflows.',
            );
        }
        if ( $matches < 1 ) {
            $warnings[] = array(
                'id' => 'no-matches',
                'message' => 'No normalized match catalogue is available yet.',
            );
        }
        if ( $events < 1 ) {
            $warnings[] = array(
                'id' => 'no-events',
                'message' => 'No normalized event data is available yet.',
            );
        }

        return array(
            'status' => empty( $blockers ) ? 'ready' : 'needs_attention',
            'release_stage' => 'mvp-release-candidate',
            'plugin_version' => $plugin_version,
            'environment' => array(
                'php_version' => $php_version,
                'minimum_php' => self::MIN_PHP,
                'php_supported' => '' !== $php_version && ! version_compare( $php_version, self::MIN_PHP, '<' ),
                'wordpress_version' => $wordpress_version,
            ),
            'schema' => array(
                'expected' => $expected_schema,
                'installed' => $installed_schema,
                'matches' => '' !== $expected_schema && $installed_schema === $expected_schema,
            ),
            'data' => array(
                'counts' => $counts,
                'warnings' => $warnings,
                'minimum_matchup_data_present' => $teams >= 2 && $matches >= 1 && $events >= 1,
            ),
            'blockers' => $blockers,
            'database_error' => $database_error,
            'method' => array(
                'name' => 'mvp-runtime-readiness-v1.40',
                'operational_diagnostics_only' => true,
                'imports_data' => false,
                'calculates_tactical_score' => false,
                'mutates_product_state' => false,
                'data_warnings_are_not_runtime_blockers' => true,
            ),
        );
    }
}
