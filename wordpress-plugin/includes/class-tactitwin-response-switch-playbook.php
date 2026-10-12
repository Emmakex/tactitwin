<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Turns v1.30 branch-by-branch response leaders into explicit staff-facing
 * switch candidates. It never activates a response automatically.
 */
final class TactiTwin_Response_Switch_Playbook {
    public static function compose(
        array $matrix,
        array $tree,
        array $robustness,
        string $language = 'en'
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );

        if ( 'tactical-response-matrix' !== (string) ( $matrix['matrix_id'] ?? '' ) || empty( $matrix['responses'] ) ) {
            throw new InvalidArgumentException( 'A Tactical Response Matrix with responses is required.' );
        }
        if ( 'match-state-scenario-tree' !== (string) ( $tree['tree_id'] ?? '' ) || empty( $tree['branches'] ) ) {
            throw new InvalidArgumentException( 'A Match-State Scenario Tree with branches is required.' );
        }
        if ( 'response-state-robustness' !== (string) ( $robustness['robustness_id'] ?? '' ) || empty( $robustness['branches'] ) ) {
            throw new InvalidArgumentException( 'Response-state robustness diagnostics are required.' );
        }

        $responses    = self::index_responses( $matrix['responses'] );
        $tree_branches = self::index_branches( $tree['branches'] );
        $baseline_id  = (string) ( $matrix['top_response_id'] ?? '' );

        if ( '' === $baseline_id || ! isset( $responses[ $baseline_id ] ) ) {
            throw new InvalidArgumentException( 'The baseline top response is missing from the Tactical Response Matrix.' );
        }

        $items = array();
        foreach ( $robustness['branches'] as $robust_branch ) {
            $branch_id = (string) ( $robust_branch['branch_id'] ?? '' );
            if ( '' === $branch_id || ! isset( $tree_branches[ $branch_id ] ) ) {
                throw new InvalidArgumentException( sprintf( 'Scenario-tree branch missing for robustness branch: %s', $branch_id ) );
            }

            $tree_branch = $tree_branches[ $branch_id ];
            $ranked      = $robust_branch['ranked_responses'] ?? array();
            if ( count( $ranked ) < 2 ) {
                throw new InvalidArgumentException( sprintf( 'Branch response ranking is incomplete: %s', $branch_id ) );
            }

            $leader       = $ranked[0];
            $runner_up    = $ranked[1];
            $leader_id    = (string) ( $leader['response_id'] ?? '' );
            $leader_change = $leader_id !== $baseline_id;
            $coverage     = $tree_branch['coverage'] ?? array();
            $coverage_caution = self::coverage_caution( $coverage );
            $margin       = round(
                (float) ( $leader['state_fit'] ?? 0 ) - (float) ( $runner_up['state_fit'] ?? 0 ),
                1
            );

            $items[] = array(
                'switch_id'   => 'response-switch-' . $branch_id,
                'branch_id'   => $branch_id,
                'branch_name' => (string) ( $tree_branch['branch_name'] ?? $branch_id ),
                'purpose'     => (string) ( $tree_branch['purpose'] ?? '' ),
                'hypothesis'  => $tree_branch['hypothesis'] ?? array(),
                'decision'    => $leader_change ? 'switch-candidate' : 'retain-baseline',
                'decision_label' => self::text( $leader_change ? 'switch_candidate' : 'retain_baseline', $language ),
                'baseline_response' => self::response_ref( $responses[ $baseline_id ] ),
                'target_response' => $leader_change
                    ? self::ranked_response_ref( $leader )
                    : self::response_ref( $responses[ $baseline_id ] ),
                'runner_up_response' => self::ranked_response_ref( $runner_up ),
                'state_fit_margin' => max( 0.0, $margin ),
                'state_fit_margin_meaning' => self::text( 'margin_meaning', $language ),
                'trigger' => array(
                    'headline' => (string) ( $tree_branch['hypothesis']['headline'] ?? self::trigger_headline( $tree_branch['hypothesis'] ?? array(), $language ) ),
                    'minute' => (int) ( $tree_branch['hypothesis']['minute'] ?? 0 ),
                    'our_score' => (int) ( $tree_branch['hypothesis']['our_score'] ?? 0 ),
                    'opponent_score' => (int) ( $tree_branch['hypothesis']['opponent_score'] ?? 0 ),
                    'our_players' => (int) ( $tree_branch['hypothesis']['our_players'] ?? 11 ),
                    'opponent_players' => (int) ( $tree_branch['hypothesis']['opponent_players'] ?? 11 ),
                    'score_state' => (string) ( $tree_branch['hypothesis']['state'] ?? '' ),
                    'manpower_state' => (string) ( $tree_branch['hypothesis']['manpower_state'] ?? '' ),
                ),
                'validation_signals' => array_slice( $tree_branch['decision_triggers'] ?? array(), 0, 3 ),
                'first_branch_recommendation' => (string) ( $tree_branch['recommendations'][0] ?? '' ),
                'coverage' => $coverage,
                'coverage_caution' => $coverage_caution,
                'coverage_caution_note' => $coverage_caution ? self::text( 'coverage_caution', $language ) : null,
                'automatic_activation' => false,
                'manual_confirmation_required' => true,
            );
        }

        $switch_candidates = array_values(
            array_filter(
                $items,
                static fn( array $item ): bool => 'switch-candidate' === (string) $item['decision']
            )
        );

        return array(
            'playbook_id'   => 'response-switch-playbook',
            'playbook_name' => self::text( 'name', $language ),
            'language'      => $language,
            'team'          => $matrix['team'] ?? array(),
            'opponent'      => $matrix['opponent'] ?? array(),
            'coverage'      => $matrix['coverage'] ?? array(),
            'baseline_response' => self::response_ref( $responses[ $baseline_id ] ),
            'items'         => $items,
            'summary'       => array(
                'branch_count' => count( $items ),
                'switch_candidate_count' => count( $switch_candidates ),
                'retain_baseline_count' => count( $items ) - count( $switch_candidates ),
                'coverage_caution_count' => count(
                    array_filter( $items, static fn( array $item ): bool => ! empty( $item['coverage_caution'] ) )
                ),
                'earliest_switch_candidate' => self::earliest_switch( $switch_candidates ),
                'target_response_frequency' => self::target_frequency( $switch_candidates ),
            ),
            'method' => array(
                'type' => 'manual-confirmation-response-switch-playbook',
                'source' => 'v1.30-response-state-robustness',
                'uses_existing_branch_triggers' => true,
                'creates_new_tactical_score' => false,
                'predicts_outcome' => false,
                'automatic_activation' => false,
                'manual_confirmation_required' => true,
                'changes_plan_abc' => false,
                'note' => self::text( 'method', $language ),
            ),
        );
    }

    private static function index_responses( array $responses ): array {
        $indexed = array();
        foreach ( $responses as $response ) {
            $id = (string) ( $response['response_id'] ?? '' );
            if ( '' !== $id ) {
                $indexed[ $id ] = $response;
            }
        }
        return $indexed;
    }

    private static function index_branches( array $branches ): array {
        $indexed = array();
        foreach ( $branches as $branch ) {
            $id = (string) ( $branch['branch_id'] ?? '' );
            if ( '' !== $id ) {
                $indexed[ $id ] = $branch;
            }
        }
        return $indexed;
    }

    private static function response_ref( array $response ): array {
        return array(
            'response_id' => (string) ( $response['response_id'] ?? '' ),
            'response_name' => (string) ( $response['response_name'] ?? '' ),
            'baseline_rank' => (int) ( $response['rank'] ?? 0 ),
            'baseline_fit' => (float) ( $response['fit_score'] ?? 0 ),
        );
    }

    private static function ranked_response_ref( array $response ): array {
        return array(
            'response_id' => (string) ( $response['response_id'] ?? '' ),
            'response_name' => (string) ( $response['response_name'] ?? '' ),
            'rank' => (int) ( $response['rank'] ?? 0 ),
            'state_fit' => (float) ( $response['state_fit'] ?? 0 ),
            'baseline_rank' => (int) ( $response['baseline_rank'] ?? 0 ),
        );
    }

    private static function coverage_caution( array $coverage ): bool {
        if ( 'low' === (string) ( $coverage['status'] ?? '' ) ) {
            return true;
        }
        return 'low' === (string) ( $coverage['manpower']['status'] ?? '' );
    }

    private static function earliest_switch( array $items ): array {
        if ( empty( $items ) ) {
            return array();
        }
        usort(
            $items,
            static fn( array $a, array $b ): int => (int) ( $a['trigger']['minute'] ?? 0 ) <=> (int) ( $b['trigger']['minute'] ?? 0 )
        );
        $first = $items[0];
        return array(
            'branch_id' => (string) ( $first['branch_id'] ?? '' ),
            'branch_name' => (string) ( $first['branch_name'] ?? '' ),
            'minute' => (int) ( $first['trigger']['minute'] ?? 0 ),
            'target_response_id' => (string) ( $first['target_response']['response_id'] ?? '' ),
            'target_response_name' => (string) ( $first['target_response']['response_name'] ?? '' ),
        );
    }

    private static function target_frequency( array $items ): array {
        $counts = array();
        foreach ( $items as $item ) {
            $id = (string) ( $item['target_response']['response_id'] ?? '' );
            if ( '' !== $id ) {
                $counts[ $id ] = ( $counts[ $id ] ?? 0 ) + 1;
            }
        }
        arsort( $counts );
        return $counts;
    }

    private static function trigger_headline( array $hypothesis, string $language ): string {
        return sprintf(
            self::text( 'trigger_fallback', $language ),
            (int) ( $hypothesis['minute'] ?? 0 ),
            (int) ( $hypothesis['our_score'] ?? 0 ),
            (int) ( $hypothesis['opponent_score'] ?? 0 ),
            (int) ( $hypothesis['our_players'] ?? 11 ),
            (int) ( $hypothesis['opponent_players'] ?? 11 )
        );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'name' => 'Response Switch Playbook',
                'switch_candidate' => 'Review switch candidate',
                'retain_baseline' => 'Retain baseline response',
                'margin_meaning' => 'Difference between the first- and second-ranked branch compatibility indexes. It is a separation diagnostic, not confidence or outcome probability.',
                'coverage_caution' => 'Historical coverage for this branch is limited. Treat the switch as a rehearsal candidate and validate the live state before changing response.',
                'trigger_fallback' => "Minute %d, score %d-%d, %dv%d.",
                'method' => 'The playbook converts v1.30 leader changes into explicit review points. A state match can suggest a response switch, but live staff confirmation remains mandatory and no response, Plan A/B/C or tactical action is activated automatically.',
            ),
            'es' => array(
                'name' => 'Playbook de cambios de respuesta',
                'switch_candidate' => 'Revisar candidato de cambio',
                'retain_baseline' => 'Mantener respuesta base',
                'margin_meaning' => 'Diferencia entre los índices de compatibilidad de la primera y segunda respuesta de la rama. Es un diagnóstico de separación, no confianza ni probabilidad de resultado.',
                'coverage_caution' => 'La cobertura histórica de esta rama es limitada. Trata el cambio como candidato ensayado y valida el estado real antes de cambiar de respuesta.',
                'trigger_fallback' => 'Minuto %d, marcador %d-%d, %dv%d.',
                'method' => 'El playbook convierte los cambios de líder de v1.30 en puntos explícitos de revisión. La coincidencia con un estado puede sugerir un cambio de respuesta, pero la confirmación del staff sigue siendo obligatoria y no se activa automáticamente ninguna respuesta, Plan A/B/C ni acción táctica.',
            ),
        );

        return (string) ( $catalog[ $language ][ $key ] ?? $key );
    }
}
