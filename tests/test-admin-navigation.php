<?php

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

final class TactiTwin_I18n {
    public static function normalize_language( ?string $language ): string {
        $language = strtolower( trim( (string) $language ) );
        return 0 === strpos( $language, 'es' ) ? 'es' : 'en';
    }
}

require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-admin-navigation.php';

function tt_nav_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$items = array(
    array( 'Live Sessions', 'manage_options', 'tactitwin-live-sessions', 'Live Sessions' ),
    array( 'Session Review', 'manage_options', 'tactitwin-session-review', 'Session Review' ),
    array( 'Evidence Review', 'manage_options', 'tactitwin-evidence-review', 'Evidence Review' ),
    array( 'Segment Evidence', 'manage_options', 'tactitwin-segment-evidence', 'Segment Evidence' ),
    array( 'Transition Deltas', 'manage_options', 'tactitwin-transition-deltas', 'Transition Deltas' ),
    array( 'Scenario Lab', 'manage_options', 'tactitwin-scenario-lab', 'Scenario Lab' ),
    array( 'Match Plan', 'manage_options', 'tactitwin-match-plan', 'Match Plan' ),
    array( 'Twin Lab', 'manage_options', 'tactitwin-twin-lab', 'Twin Lab' ),
    array( 'Live Sessions duplicate', 'manage_options', 'tactitwin-live-sessions', 'Live Sessions' ),
    array( 'Session Review duplicate', 'manage_options', 'tactitwin-session-review', 'Session Review' ),
    array( 'Evidence Review duplicate', 'manage_options', 'tactitwin-evidence-review', 'Evidence Review' ),
    array( 'Segment Evidence duplicate', 'manage_options', 'tactitwin-segment-evidence', 'Segment Evidence' ),
    array( 'Transition Deltas duplicate', 'manage_options', 'tactitwin-transition-deltas', 'Transition Deltas' ),
    array( 'Intervention Library', 'manage_options', 'tactitwin-intervention-library', 'Intervention Library' ),
    array( 'Staff Brief', 'manage_options', 'tactitwin-staff-brief', 'Staff Brief' ),
    array( 'Personnel Lab', 'manage_options', 'tactitwin-personnel-lab', 'Personnel Lab' ),
    array( 'Unit Lab', 'manage_options', 'tactitwin-unit-lab', 'Unit Lab' ),
    array( 'Lineup Lab', 'manage_options', 'tactitwin-lineup-lab', 'Lineup Lab' ),
    array( 'Match Workspace', 'manage_options', 'tactitwin', 'TactiTwin' ),
    array( 'Data & Imports', 'manage_options', 'tactitwin-data', 'Data & Imports' ),
    array( 'Third-party extension', 'manage_options', 'tactitwin-third-party', 'Extension' ),
);

$es = TactiTwin_Admin_Navigation::normalize_items( $items, 'es' );
$en = TactiTwin_Admin_Navigation::normalize_items( $items, 'en' );

$es_slugs = array_map( static fn( array $item ): string => (string) $item[2], $es );
$en_slugs = array_map( static fn( array $item ): string => (string) $item[2], $en );

tt_nav_assert( count( $es_slugs ) === count( array_unique( $es_slugs ) ), 'Spanish menu contains no duplicate slugs.' );
tt_nav_assert( count( $en_slugs ) === count( array_unique( $en_slugs ) ), 'English menu contains no duplicate slugs.' );
tt_nav_assert( $es_slugs === $en_slugs, 'ES/EN menu order and slugs must be identical.' );

$expected_prefix = array(
    'tactitwin',
    'tactitwin-data',
    'tactitwin-scenario-lab',
    'tactitwin-match-plan',
    'tactitwin-twin-lab',
    'tactitwin-lineup-lab',
    'tactitwin-personnel-lab',
    'tactitwin-unit-lab',
    'tactitwin-staff-brief',
    'tactitwin-live-sessions',
    'tactitwin-session-review',
    'tactitwin-evidence-review',
    'tactitwin-segment-evidence',
    'tactitwin-transition-deltas',
    'tactitwin-intervention-library',
);

tt_nav_assert( $expected_prefix === array_slice( $es_slugs, 0, count( $expected_prefix ) ), 'Canonical pages follow deterministic workflow order.' );
tt_nav_assert( 'tactitwin-third-party' === end( $es_slugs ), 'Unknown extension page is preserved after canonical pages.' );

$es_by_slug = array();
foreach ( $es as $item ) {
    $es_by_slug[ $item[2] ] = $item[0];
}
$en_by_slug = array();
foreach ( $en as $item ) {
    $en_by_slug[ $item[2] ] = $item[0];
}

tt_nav_assert( 'Espacio del partido' === $es_by_slug['tactitwin'], 'Workspace has explicit Spanish label.' );
tt_nav_assert( 'Datos e importaciones' === $es_by_slug['tactitwin-data'], 'Data page has explicit Spanish label.' );
tt_nav_assert( 'Plan de partido' === $es_by_slug['tactitwin-match-plan'], 'Match Plan has explicit Spanish label.' );
tt_nav_assert( 'Sesiones en vivo' === $es_by_slug['tactitwin-live-sessions'], 'Live Sessions has explicit Spanish label.' );
tt_nav_assert( 'Revisión de evidencia' === $es_by_slug['tactitwin-evidence-review'], 'Evidence Review has explicit Spanish label.' );
tt_nav_assert( 'Match Workspace' === $en_by_slug['tactitwin'], 'Workspace English label remains stable.' );
tt_nav_assert( 'Live Sessions' === $en_by_slug['tactitwin-live-sessions'], 'Live Sessions English label remains stable.' );

$catalog_es = TactiTwin_Admin_Navigation::catalog( 'es' );
$catalog_en = TactiTwin_Admin_Navigation::catalog( 'en' );
tt_nav_assert( array_keys( $catalog_es ) === array_keys( $catalog_en ), 'ES/EN catalogs cover exactly the same slugs.' );
foreach ( $catalog_es as $slug => $entry ) {
    tt_nav_assert( '' !== (string) ( $entry['label'] ?? '' ), "Spanish label missing for {$slug}." );
    tt_nav_assert( '' !== (string) ( $catalog_en[ $slug ]['label'] ?? '' ), "English label missing for {$slug}." );
}

echo "Admin navigation tests passed.\n";
