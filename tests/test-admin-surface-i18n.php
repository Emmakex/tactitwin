<?php

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

final class TactiTwin_I18n {
    public static string $language = 'es';

    public static function current_language(): string {
        return self::$language;
    }
}

require_once __DIR__ . '/../wordpress-plugin/includes/class-tactitwin-admin-translations.php';

function tt_i18n_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

TactiTwin_I18n::$language = 'es';
tt_i18n_assert(
    'TactiTwin · Plan de partido' === TactiTwin_Admin_Translations::gettext( 'TactiTwin Match Plan', 'TactiTwin Match Plan', 'tactitwin' ),
    'Match Plan title translates to Spanish.'
);
tt_i18n_assert(
    'Nuestro equipo' === TactiTwin_Admin_Translations::gettext( 'Our team', 'Our team', 'tactitwin' ),
    'Shared team label translates to Spanish.'
);
tt_i18n_assert(
    'Importar eventos del partido' === TactiTwin_Admin_Translations::gettext( 'Import match events', 'Import match events', 'tactitwin' ),
    'Data import action translates to Spanish.'
);
tt_i18n_assert(
    'Salida de balón vs presión alta' === TactiTwin_Admin_Translations::gettext( 'Build-up vs High Press', 'Build-up vs High Press', 'tactitwin' ),
    'Scenario option translates to Spanish.'
);
tt_i18n_assert(
    'Unrelated text' === TactiTwin_Admin_Translations::gettext( 'Unrelated text', 'Our team', 'other-domain' ),
    'Other text domains are never changed.'
);
tt_i18n_assert(
    'Unknown legacy string' === TactiTwin_Admin_Translations::gettext( 'Unknown legacy string', 'Unknown legacy string', 'tactitwin' ),
    'Unknown TactiTwin strings fall back unchanged.'
);

$notice = TactiTwin_Admin_Translations::translate_notice(
    array( 'type' => 'success', 'message' => 'Imported match 3895348: 4133 events, 32 players and 24 event types.' ),
    60
);
tt_i18n_assert(
    'Partido 3895348 importado: 4133 eventos, 32 jugadores y 24 tipos de evento.' === $notice['message'],
    'Imported-match notice translates with numeric values preserved.'
);

$competition_notice = TactiTwin_Admin_Translations::translate_notice(
    array( 'type' => 'success', 'message' => 'Imported 1. Bundesliga 2023/2024: 34 matches and 18 teams.' ),
    60
);
tt_i18n_assert(
    '1. Bundesliga 2023/2024 importada: 34 partidos y 18 equipos.' === $competition_notice['message'],
    'Competition notice translates while retaining provider names and counts.'
);

TactiTwin_I18n::$language = 'en';
tt_i18n_assert(
    'Our team' === TactiTwin_Admin_Translations::gettext( 'Our team', 'Our team', 'tactitwin' ),
    'English admin copy remains unchanged.'
);
$english_notice = TactiTwin_Admin_Translations::translate_notice(
    array( 'type' => 'success', 'message' => 'Imported match 1: 2 events, 3 players and 4 event types.' ),
    60
);
tt_i18n_assert(
    'Imported match 1: 2 events, 3 players and 4 event types.' === $english_notice['message'],
    'English notices remain unchanged.'
);

echo "Admin surface i18n tests passed.\n";
