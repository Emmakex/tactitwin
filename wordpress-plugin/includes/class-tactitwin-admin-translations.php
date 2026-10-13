<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Runtime translation bridge for legacy admin copy that already uses the
 * `tactitwin` text domain but has no shipped MO catalog yet.
 *
 * New tactical-domain strings should continue using TactiTwin_I18n. This class
 * exists only to make the WordPress admin shell coherent in ES/EN while older
 * surfaces are migrated incrementally.
 */
final class TactiTwin_Admin_Translations {
    public static function register(): void {
        add_filter( 'gettext', array( self::class, 'gettext' ), 20, 3 );
        add_action( 'admin_init', array( self::class, 'register_notice_filter' ), 20 );
    }

    public static function gettext( string $translated, string $text, string $domain ): string {
        if ( 'tactitwin' !== $domain || 'es' !== TactiTwin_I18n::current_language() ) {
            return $translated;
        }
        $catalog = self::spanish_catalog();
        return (string) ( $catalog[ $text ] ?? $translated );
    }

    public static function register_notice_filter(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $user_id = get_current_user_id();
        if ( $user_id <= 0 ) {
            return;
        }
        add_filter(
            'pre_set_transient_tactitwin_notice_' . $user_id,
            array( self::class, 'translate_notice' ),
            20,
            2
        );
    }

    public static function translate_notice( $value, $expiration = 0 ) {
        if ( 'es' !== TactiTwin_I18n::current_language() || ! is_array( $value ) || empty( $value['message'] ) ) {
            return $value;
        }

        $message = (string) $value['message'];
        if ( preg_match( '/^Imported match (\d+): (\d+) events, (\d+) players and (\d+) event types\.$/', $message, $matches ) ) {
            $value['message'] = sprintf(
                'Partido %d importado: %d eventos, %d jugadores y %d tipos de evento.',
                (int) $matches[1],
                (int) $matches[2],
                (int) $matches[3],
                (int) $matches[4]
            );
            return $value;
        }

        if ( preg_match( '/^Imported (.+) (.+): (\d+) matches and (\d+) teams\.$/', $message, $matches ) ) {
            $value['message'] = sprintf(
                '%s %s importada: %d partidos y %d equipos.',
                (string) $matches[1],
                (string) $matches[2],
                (int) $matches[3],
                (int) $matches[4]
            );
        }

        return $value;
    }

    public static function spanish_catalog(): array {
        return array(
            // Global/admin safety.
            'You are not allowed to manage TactiTwin.' => 'No tienes permisos para gestionar TactiTwin.',

            // Core navigation/page names.
            'Match Workspace' => 'Espacio del partido',
            'Data & Imports' => 'Datos e importaciones',
            'Scenario Lab' => 'Laboratorio de escenarios',
            'Match Plan' => 'Plan de partido',

            // Data & Imports.
            'TactiTwin Data & Imports' => 'TactiTwin · Datos e importaciones',
            'Manage the open-data layer used by the Match Workspace and tactical engines.' => 'Gestiona la capa de datos abiertos utilizada por el Espacio del partido y los motores tácticos.',
            'Back to Match Workspace' => 'Volver al Espacio del partido',
            'Data layer' => 'Capa de datos',
            'StatsBomb Open Data import' => 'Importación de StatsBomb Open Data',
            'Import the competition/season first, then import events for a selected match.' => 'Importa primero la competición y la temporada; después importa los eventos del partido seleccionado.',
            '1. Import competition and season' => '1. Importar competición y temporada',
            'Competition ID' => 'ID de competición',
            'Season ID' => 'ID de temporada',
            'Import competition' => 'Importar competición',
            '2. Import match events' => '2. Importar eventos del partido',
            'Match ID' => 'ID del partido',
            'Import match events' => 'Importar eventos del partido',
            'Open-data source: StatsBomb / Hudl. Any analysis or published insight based on this dataset must retain the required source attribution.' => 'Fuente de datos abiertos: StatsBomb / Hudl. Todo análisis o publicación basada en este dataset debe conservar la atribución exigida por la fuente.',

            // Shared preparation form copy.
            'Language' => 'Idioma',
            'Our team' => 'Nuestro equipo',
            'Opponent' => 'Rival',
            'Recent matches' => 'Partidos recientes',
            'Import a competition/season first so at least two teams are available.' => 'Importa primero una competición/temporada para disponer de al menos dos equipos.',
            'Missing event-data matches are imported automatically where available.' => 'Los partidos sin eventos se importan automáticamente cuando la fuente los tenga disponibles.',

            // Match Plan.
            'TactiTwin Match Plan' => 'TactiTwin · Plan de partido',
            'Turn the full tactical scan into a phase-by-phase match plan for the coaching staff.' => 'Convierte el análisis táctico completo en un plan de partido por fases para el staff técnico.',
            'Generate match plan' => 'Generar plan de partido',

            // Scenario Lab.
            'TactiTwin Scenario Lab' => 'TactiTwin · Laboratorio de escenarios',
            'Prepare a data-backed matchup, choose a tactical question and receive an explainable scenario assessment.' => 'Prepara el enfrentamiento con datos, elige una pregunta táctica y recibe una evaluación explicable del escenario.',
            'Controls scenario names, explanations and tactical recommendations.' => 'Controla el idioma de los escenarios, explicaciones y recomendaciones tácticas.',
            'Scenario' => 'Escenario',
            'Full Matchup Scan' => 'Análisis completo del enfrentamiento',
            'Build-up vs High Press' => 'Salida de balón vs presión alta',
            'Opponent Side Bias' => 'Preferencia lateral del rival',
            'Defensive Transition After Loss' => 'Transición defensiva tras pérdida',
            'Attack vs Low Block' => 'Ataque vs bloque bajo',
            'Exploit Opponent Weak Side' => 'Explotar el lado débil rival',
            'TactiTwin will automatically import missing event data until this sample is reached where possible.' => 'TactiTwin importará automáticamente los eventos que falten hasta alcanzar esta muestra cuando sea posible.',
            'Prepare and simulate' => 'Preparar y simular',
            'Scenario:' => 'Escenario:',
            'Risk' => 'Riesgo',
            'Opportunity' => 'Oportunidad',
            'Coverage' => 'Cobertura',
            'Recommended response' => 'Respuesta recomendada',
            'Technical evidence' => 'Evidencia técnica',
            'Scenario priority ranking' => 'Ranking de prioridad de escenarios',
        );
    }
}
