<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Canonical TactiTwin submenu registry.
 *
 * Individual admin surfaces may continue to register their own callbacks. This
 * late normalizer owns only navigation presentation: one visible row per slug,
 * deterministic workflow order and bilingual menu labels. Existing slugs and
 * callbacks stay untouched.
 */
final class TactiTwin_Admin_Navigation {
    private const PARENT = 'tactitwin';

    public static function register(): void {
        add_action( 'admin_menu', array( self::class, 'normalize' ), 999 );
    }

    public static function catalog( string $language = 'en' ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $labels = self::labels( $language );

        return array(
            'tactitwin' => array( 'order' => 10, 'section' => 'workspace', 'label' => $labels['workspace'] ),
            'tactitwin-data' => array( 'order' => 20, 'section' => 'workspace', 'label' => $labels['data'] ),
            'tactitwin-scenario-lab' => array( 'order' => 100, 'section' => 'pre_match', 'label' => $labels['scenario'] ),
            'tactitwin-match-plan' => array( 'order' => 110, 'section' => 'pre_match', 'label' => $labels['match_plan'] ),
            'tactitwin-twin-lab' => array( 'order' => 120, 'section' => 'pre_match', 'label' => $labels['twin_lab'] ),
            'tactitwin-what-if' => array( 'order' => 130, 'section' => 'pre_match', 'label' => $labels['what_if'] ),
            'tactitwin-lineup-lab' => array( 'order' => 140, 'section' => 'pre_match', 'label' => $labels['lineup'] ),
            'tactitwin-personnel-lab' => array( 'order' => 150, 'section' => 'pre_match', 'label' => $labels['personnel'] ),
            'tactitwin-unit-lab' => array( 'order' => 160, 'section' => 'pre_match', 'label' => $labels['unit'] ),
            'tactitwin-staff-brief' => array( 'order' => 170, 'section' => 'pre_match', 'label' => $labels['staff_brief'] ),
            'tactitwin-live-playbook' => array( 'order' => 200, 'section' => 'match_day', 'label' => $labels['live_playbook'] ),
            'tactitwin-live-sessions' => array( 'order' => 210, 'section' => 'match_day', 'label' => $labels['live_sessions'] ),
            'tactitwin-session-review' => array( 'order' => 300, 'section' => 'post_match', 'label' => $labels['session_review'] ),
            'tactitwin-evidence-review' => array( 'order' => 310, 'section' => 'post_match', 'label' => $labels['evidence_review'] ),
            'tactitwin-segment-evidence' => array( 'order' => 320, 'section' => 'post_match', 'label' => $labels['segment_evidence'] ),
            'tactitwin-transition-deltas' => array( 'order' => 330, 'section' => 'post_match', 'label' => $labels['transition_deltas'] ),
            'tactitwin-intervention-library' => array( 'order' => 340, 'section' => 'post_match', 'label' => $labels['intervention_library'] ),
        );
    }

    /**
     * Pure normalization helper used by runtime and regression tests.
     * Unknown child pages are preserved after canonical pages in original order.
     */
    public static function normalize_items( array $items, string $language = 'en' ): array {
        $catalog = self::catalog( $language );
        $known = array();
        $unknown = array();
        $unknown_seen = array();

        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $slug = (string) ( $item[2] ?? '' );
            if ( '' === $slug ) {
                continue;
            }

            if ( isset( $catalog[ $slug ] ) ) {
                if ( isset( $known[ $slug ] ) ) {
                    continue;
                }
                $item[0] = $catalog[ $slug ]['label'];
                $known[ $slug ] = $item;
                continue;
            }

            if ( isset( $unknown_seen[ $slug ] ) ) {
                continue;
            }
            $unknown_seen[ $slug ] = true;
            $unknown[] = $item;
        }

        uasort(
            $known,
            static function ( array $a, array $b ) use ( $catalog ): int {
                $a_slug = (string) ( $a[2] ?? '' );
                $b_slug = (string) ( $b[2] ?? '' );
                return (int) ( $catalog[ $a_slug ]['order'] ?? PHP_INT_MAX ) <=> (int) ( $catalog[ $b_slug ]['order'] ?? PHP_INT_MAX );
            }
        );

        return array_values( array_merge( $known, $unknown ) );
    }

    public static function normalize(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $submenu;
        if ( ! isset( $submenu[ self::PARENT ] ) || ! is_array( $submenu[ self::PARENT ] ) ) {
            return;
        }

        $submenu[ self::PARENT ] = self::normalize_items(
            $submenu[ self::PARENT ],
            TactiTwin_I18n::current_language()
        );
    }

    private static function labels( string $language ): array {
        if ( 'es' === $language ) {
            return array(
                'workspace' => 'Espacio del partido',
                'data' => 'Datos e importaciones',
                'scenario' => 'Laboratorio de escenarios',
                'match_plan' => 'Plan de partido',
                'twin_lab' => 'Laboratorio Tactical Twin',
                'what_if' => 'Laboratorio What-if',
                'lineup' => 'Laboratorio de alineación',
                'personnel' => 'Laboratorio de jugadores',
                'unit' => 'Laboratorio de unidades',
                'staff_brief' => 'Brief del staff',
                'live_playbook' => 'Playbook en vivo',
                'live_sessions' => 'Sesiones en vivo',
                'session_review' => 'Revisión de sesión',
                'evidence_review' => 'Revisión de evidencia',
                'segment_evidence' => 'Evidencia por segmentos',
                'transition_deltas' => 'Deltas de transición',
                'intervention_library' => 'Biblioteca de intervenciones',
            );
        }

        return array(
            'workspace' => 'Match Workspace',
            'data' => 'Data & Imports',
            'scenario' => 'Scenario Lab',
            'match_plan' => 'Match Plan',
            'twin_lab' => 'Tactical Twin Lab',
            'what_if' => 'What-if Lab',
            'lineup' => 'Lineup Lab',
            'personnel' => 'Personnel Lab',
            'unit' => 'Unit Lab',
            'staff_brief' => 'Staff Brief',
            'live_playbook' => 'Live Playbook',
            'live_sessions' => 'Live Sessions',
            'session_review' => 'Session Review',
            'evidence_review' => 'Evidence Review',
            'segment_evidence' => 'Segment Evidence',
            'transition_deltas' => 'Transition Deltas',
            'intervention_library' => 'Intervention Library',
        );
    }
}
