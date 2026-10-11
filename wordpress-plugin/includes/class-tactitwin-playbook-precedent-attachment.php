<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-tactitwin-evidence-decision-brief.php';
require_once __DIR__ . '/class-tactitwin-evidence-conflict-signals.php';

final class TactiTwin_Playbook_Precedent_Attachment {
    public static function attach( array $playbook, array $precedents, string $language = 'en', int $per_plan_limit = 3 ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $per_plan_limit = max( 1, min( 10, $per_plan_limit ) );

        if ( 'contingency-playbook' !== (string) ( $playbook['playbook_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'Playbook precedent attachment requires a TactiTwin Contingency Playbook.' );
        }
        if ( 'historical-precedent-retrieval' !== (string) ( $precedents['precedent_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'Playbook precedent attachment requires v1.8 historical precedent retrieval.' );
        }

        $items = is_array( $precedents['items'] ?? null ) ? $precedents['items'] : array();
        $result = $playbook;
        $active = is_array( $playbook['active_plan'] ?? null ) ? $playbook['active_plan'] : array();
        $active_b_scenario = 'B' === (string) ( $active['id'] ?? '' )
            ? (string) ( $active['scenario_id'] ?? '' )
            : '';

        $distribution = array();
        foreach ( array( 'plan_a'=>'A', 'plan_b'=>'B', 'plan_c'=>'C' ) as $plan_key => $plan_id ) {
            if ( ! isset( $result['plans'][ $plan_key ] ) || ! is_array( $result['plans'][ $plan_key ] ) ) {
                continue;
            }

            $scenario_filter = '';
            if ( 'B' === $plan_id ) {
                $scenario_filter = '' !== $active_b_scenario
                    ? $active_b_scenario
                    : (string) ( $result['plans'][ $plan_key ]['scenario_id'] ?? '' );
            }

            $matched = array_values(
                array_filter(
                    $items,
                    static function( array $item ) use ( $plan_id, $scenario_filter ): bool {
                        if ( strtoupper( (string) ( $item['to_plan'] ?? '' ) ) !== $plan_id ) {
                            return false;
                        }
                        if ( 'B' === $plan_id && '' !== $scenario_filter ) {
                            return (string) ( $item['scenario_id'] ?? '' ) === $scenario_filter;
                        }
                        return true;
                    }
                )
            );

            $total = count( $matched );
            $returned = array_slice( $matched, 0, $per_plan_limit );
            $decision_brief = TactiTwin_Evidence_Decision_Brief::from_items(
                $returned,
                array(),
                $language,
                'plan-' . strtolower( $plan_id ) . '-filtered-evidence'
            );
            $decision_brief = TactiTwin_Evidence_Conflict_Signals::brief(
                $decision_brief,
                $language,
                'plan-' . strtolower( $plan_id ) . '-filtered-evidence'
            );
            $result['plans'][ $plan_key ]['historical_precedents'] = array(
                'status' => $total > 0 ? 'available' : 'no-match',
                'plan_id' => $plan_id,
                'scenario_filter' => '' !== $scenario_filter ? $scenario_filter : null,
                'matched_count' => $total,
                'returned_count' => count( $returned ),
                'items' => $returned,
                'decision_brief' => $decision_brief,
                'summary' => self::plan_summary( $plan_id, $scenario_filter, $total, $language ),
                'method' => array(
                    'source' => 'v1.8 exact-context historical precedent retrieval enriched through v1.15',
                    'destination_plan_match_required' => true,
                    'plan_b_scenario_match_required' => 'B' === $plan_id,
                    'decision_brief_after_plan_filter' => true,
                    'conflict_signals_after_plan_filter' => true,
                    'decision_brief_creates_new_score' => false,
                    'decision_brief_changes_evidence_order' => false,
                    'conflict_signals_create_score' => false,
                    'conflict_signals_change_evidence_order' => false,
                    'reweights_tactical_scores' => false,
                    'changes_recommendations' => false,
                ),
            );
            $distribution[ $plan_id ] = $total;
        }

        $result['historical_precedents'] = $precedents;
        $result['historical_precedents']['plan_distribution'] = $distribution;
        $result['method']['historical_precedents'] = array(
            'attached' => true,
            'exact_context_required' => true,
            'destination_plan_match_required' => true,
            'plan_b_scenario_match_required' => true,
            'decision_brief_after_plan_filter' => true,
            'conflict_signals_after_plan_filter' => true,
            'decision_brief_creates_new_score' => false,
            'decision_brief_changes_evidence_order' => false,
            'conflict_signals_create_score' => false,
            'conflict_signals_change_evidence_order' => false,
            'reweights_tactical_scores' => false,
            'changes_recommendations' => false,
            'note' => self::text( 'method', $language ),
        );

        return $result;
    }

    private static function plan_summary( string $plan_id, string $scenario, int $count, string $language ): array {
        if ( 0 === $count ) {
            return array(
                'B' === $plan_id && '' !== $scenario
                    ? sprintf( self::text( 'none_b', $language ), $scenario )
                    : sprintf( self::text( 'none_plan', $language ), $plan_id ),
            );
        }

        return array(
            'B' === $plan_id && '' !== $scenario
                ? sprintf( self::text( 'count_b', $language ), $count, $scenario )
                : sprintf( self::text( 'count_plan', $language ), $count, $plan_id ),
        );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'method' => 'v1.15 keeps the v1.9 Plan A/B/C destination filters, builds the v1.14 brief inside each filtered shortlist, then evaluates evidence-conflict signals only inside that same filtered plan evidence. No layer changes the underlying v1.12 evidence order, tactical scores or recommendations.',
                'none_plan' => 'No exact-context historical transitions into Plan %s were found.',
                'none_b' => 'No exact-context historical transitions into Plan B were found for scenario %s.',
                'count_plan' => '%d exact-context historical transitions into Plan %s are available.',
                'count_b' => '%d exact-context historical transitions into Plan B are available for scenario %s.',
            ),
            'es' => array(
                'method' => 'v1.15 mantiene los filtros de destino Plan A/B/C de v1.9, construye el brief v1.14 dentro de cada shortlist filtrada y después evalúa las señales de conflicto solo dentro de esa misma evidencia del plan. Ninguna capa cambia el orden v1.12, los scores tácticos ni las recomendaciones.',
                'none_plan' => 'No se encontraron transiciones históricas de contexto exacto hacia el Plan %s.',
                'none_b' => 'No se encontraron transiciones históricas de contexto exacto hacia el Plan B para el escenario %s.',
                'count_plan' => 'Hay %d transiciones históricas de contexto exacto hacia el Plan %s.',
                'count_b' => 'Hay %d transiciones históricas de contexto exacto hacia el Plan B para el escenario %s.',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
