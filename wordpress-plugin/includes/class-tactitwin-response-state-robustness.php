<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Stress-tests v1.27 tactical responses across the explicit v1.28 match-state
 * branches without assigning branch probabilities or claiming outcome impact.
 *
 * Robustness is expressed through rank stability, not a new 0-100 score.
 */
final class TactiTwin_Response_State_Robustness {
    public static function compose( array $matrix, array $tree, string $language = 'en' ): array {
        $language = TactiTwin_I18n::normalize_language( $language );

        if ( 'tactical-response-matrix' !== (string) ( $matrix['matrix_id'] ?? '' ) || empty( $matrix['responses'] ) ) {
            throw new InvalidArgumentException( 'A Tactical Response Matrix with responses is required.' );
        }
        if ( 'match-state-scenario-tree' !== (string) ( $tree['tree_id'] ?? '' ) || empty( $tree['branches'] ) ) {
            throw new InvalidArgumentException( 'A Match-State Scenario Tree with branches is required.' );
        }

        $priorities = is_array( $matrix['scenario_priorities'] ?? null ) ? $matrix['scenario_priorities'] : array();
        self::assert_required_scenarios( $priorities );

        $responses = self::response_maps( $matrix['responses'] );
        $branches  = self::index_branches( $tree['branches'] );
        $profiles  = self::branch_emphasis_profiles();

        foreach ( array_keys( $profiles ) as $branch_id ) {
            if ( ! isset( $branches[ $branch_id ] ) ) {
                throw new InvalidArgumentException( sprintf( 'Required scenario-tree branch missing: %s', $branch_id ) );
            }
        }

        $baseline_top = (string) ( $matrix['top_response_id'] ?? '' );
        $branch_results = array();
        $rank_history   = array();
        $leader_changes = array();

        foreach ( $profiles as $branch_id => $multipliers ) {
            $ranked = array();

            foreach ( $responses as $response_id => $response ) {
                $numerator   = 0.0;
                $denominator = 0.0;
                $components  = array();

                foreach ( $multipliers as $scenario_id => $multiplier ) {
                    $response_weight = (float) ( $response['weights'][ $scenario_id ] ?? 0 );
                    $priority        = (float) ( $priorities[ $scenario_id ] ?? 0 );
                    $effective_weight = $response_weight * (float) $multiplier;
                    $weighted_value   = $priority * $effective_weight;

                    $numerator   += $weighted_value;
                    $denominator += $effective_weight;

                    $components[] = array(
                        'scenario_id'         => $scenario_id,
                        'scenario_priority'   => $priority,
                        'response_weight'     => $response_weight,
                        'state_multiplier'    => (float) $multiplier,
                        'effective_weight'    => round( $effective_weight, 4 ),
                        'weighted_value'      => round( $weighted_value, 2 ),
                    );
                }

                $state_fit = $denominator > 0.0 ? round( $numerator / $denominator, 1 ) : 0.0;

                $ranked[] = array(
                    'response_id'       => $response_id,
                    'response_name'     => (string) $response['name'],
                    'baseline_rank'     => (int) $response['baseline_rank'],
                    'baseline_fit'      => (float) $response['baseline_fit'],
                    'state_fit'         => $state_fit,
                    'state_fit_delta'   => round( $state_fit - (float) $response['baseline_fit'], 1 ),
                    'components'        => $components,
                );
            }

            usort(
                $ranked,
                static function ( array $a, array $b ): int {
                    $by_fit = $b['state_fit'] <=> $a['state_fit'];
                    return 0 !== $by_fit ? $by_fit : strcmp( $a['response_id'], $b['response_id'] );
                }
            );

            foreach ( $ranked as $index => &$item ) {
                $item['rank'] = $index + 1;
                $item['rank_change_vs_baseline'] = (int) $item['baseline_rank'] - (int) $item['rank'];
                $rank_history[ $item['response_id'] ][ $branch_id ] = (int) $item['rank'];
            }
            unset( $item );

            $leader_id = (string) ( $ranked[0]['response_id'] ?? '' );
            $leader_changed = '' !== $baseline_top && $leader_id !== $baseline_top;
            if ( $leader_changed ) {
                $leader_changes[] = array(
                    'branch_id'            => $branch_id,
                    'branch_name'          => (string) ( $branches[ $branch_id ]['branch_name'] ?? $branch_id ),
                    'baseline_response_id' => $baseline_top,
                    'state_response_id'    => $leader_id,
                    'state_response_name'  => (string) ( $ranked[0]['response_name'] ?? $leader_id ),
                );
            }

            $branch_results[] = array(
                'branch_id'       => $branch_id,
                'branch_name'     => (string) ( $branches[ $branch_id ]['branch_name'] ?? $branch_id ),
                'purpose'         => (string) ( $branches[ $branch_id ]['purpose'] ?? '' ),
                'hypothesis'      => $branches[ $branch_id ]['hypothesis'] ?? array(),
                'risk'            => $branches[ $branch_id ]['risk'] ?? array(),
                'opportunity'     => $branches[ $branch_id ]['opportunity'] ?? array(),
                'coverage'        => $branches[ $branch_id ]['coverage'] ?? array(),
                'emphasis_profile'=> $multipliers,
                'ranked_responses'=> $ranked,
                'top_response_id' => $leader_id,
                'leader_changed_from_baseline' => $leader_changed,
            );
        }

        $robustness = self::aggregate_robustness( $responses, $rank_history, count( $profiles ), $language );

        return array(
            'robustness_id'   => 'response-state-robustness',
            'robustness_name' => self::text( 'name', $language ),
            'language'        => $language,
            'team'            => $matrix['team'] ?? array(),
            'opponent'        => $matrix['opponent'] ?? array(),
            'coverage'        => $matrix['coverage'] ?? array(),
            'baseline_top_response_id' => $baseline_top,
            'branches'        => $branch_results,
            'responses'       => $robustness,
            'summary'         => array(
                'branch_count'         => count( $branch_results ),
                'response_count'       => count( $robustness ),
                'leader_change_count'  => count( $leader_changes ),
                'leader_changes'       => $leader_changes,
                'most_robust_response' => $robustness[0] ?? array(),
            ),
            'method' => array(
                'type' => 'deterministic-response-rank-robustness-across-match-states',
                'state_fit_formula' => 'sum(scenario_priority * response_weight * state_multiplier) / sum(response_weight * state_multiplier)',
                'robustness_score_created' => false,
                'branch_probabilities' => false,
                'outcome_prediction' => false,
                'causal_effectiveness' => false,
                'changes_upstream_scores' => false,
                'changes_upstream_recommendations' => false,
                'activates_plan' => false,
                'note' => self::text( 'method', $language ),
            ),
        );
    }

    public static function branch_emphasis_profiles(): array {
        return array(
            'opening-neutral' => array(
                'build-up-vs-high-press' => 1.0,
                'opponent-side-bias' => 1.0,
                'defensive-transition-after-loss' => 1.0,
                'attack-vs-low-block' => 1.0,
                'exploit-opponent-weak-side' => 1.0,
            ),
            'second-half-neutral' => array(
                'build-up-vs-high-press' => 1.0,
                'opponent-side-bias' => 1.0,
                'defensive-transition-after-loss' => 1.0,
                'attack-vs-low-block' => 1.0,
                'exploit-opponent-weak-side' => 1.0,
            ),
            'protect-lead' => array(
                'build-up-vs-high-press' => 0.9,
                'opponent-side-bias' => 1.0,
                'defensive-transition-after-loss' => 1.5,
                'attack-vs-low-block' => 0.7,
                'exploit-opponent-weak-side' => 0.9,
            ),
            'chase-game' => array(
                'build-up-vs-high-press' => 0.9,
                'opponent-side-bias' => 0.8,
                'defensive-transition-after-loss' => 0.8,
                'attack-vs-low-block' => 1.2,
                'exploit-opponent-weak-side' => 1.3,
            ),
            'late-draw' => array(
                'build-up-vs-high-press' => 1.0,
                'opponent-side-bias' => 1.0,
                'defensive-transition-after-loss' => 1.1,
                'attack-vs-low-block' => 0.9,
                'exploit-opponent-weak-side' => 1.0,
            ),
            'numerical-superiority' => array(
                'build-up-vs-high-press' => 0.8,
                'opponent-side-bias' => 0.9,
                'defensive-transition-after-loss' => 1.0,
                'attack-vs-low-block' => 1.2,
                'exploit-opponent-weak-side' => 1.1,
            ),
            'numerical-inferiority' => array(
                'build-up-vs-high-press' => 1.0,
                'opponent-side-bias' => 1.1,
                'defensive-transition-after-loss' => 1.5,
                'attack-vs-low-block' => 0.6,
                'exploit-opponent-weak-side' => 0.8,
            ),
        );
    }

    private static function response_maps( array $responses ): array {
        $mapped = array();
        foreach ( $responses as $response ) {
            $response_id = (string) ( $response['response_id'] ?? '' );
            if ( '' === $response_id ) {
                continue;
            }
            $weights = array();
            foreach ( $response['scenario_contributions'] ?? array() as $component ) {
                $scenario_id = (string) ( $component['scenario_id'] ?? '' );
                if ( '' !== $scenario_id ) {
                    $weights[ $scenario_id ] = (float) ( $component['response_weight'] ?? 0 );
                }
            }
            $mapped[ $response_id ] = array(
                'name'          => (string) ( $response['response_name'] ?? $response_id ),
                'baseline_rank' => (int) ( $response['rank'] ?? 0 ),
                'baseline_fit'  => (float) ( $response['fit_score'] ?? 0 ),
                'weights'       => $weights,
            );
        }
        return $mapped;
    }

    private static function index_branches( array $branches ): array {
        $indexed = array();
        foreach ( $branches as $branch ) {
            $branch_id = (string) ( $branch['branch_id'] ?? '' );
            if ( '' !== $branch_id ) {
                $indexed[ $branch_id ] = $branch;
            }
        }
        return $indexed;
    }

    private static function aggregate_robustness(
        array $responses,
        array $rank_history,
        int $branch_count,
        string $language
    ): array {
        $result = array();

        foreach ( $responses as $response_id => $response ) {
            $ranks = array_values( $rank_history[ $response_id ] ?? array() );
            if ( count( $ranks ) !== $branch_count ) {
                throw new RuntimeException( sprintf( 'Incomplete rank history for response: %s', $response_id ) );
            }

            $first_place = count( array_filter( $ranks, static fn( int $rank ): bool => 1 === $rank ) );
            $top_two     = count( array_filter( $ranks, static fn( int $rank ): bool => $rank <= 2 ) );
            $best_rank   = min( $ranks );
            $worst_rank  = max( $ranks );
            $mean_rank   = round( array_sum( $ranks ) / max( 1, count( $ranks ) ), 2 );
            $label       = self::robustness_label( $first_place, $top_two, $worst_rank, $branch_count );

            $result[] = array(
                'response_id'       => $response_id,
                'response_name'     => (string) $response['name'],
                'baseline_rank'     => (int) $response['baseline_rank'],
                'first_place_count' => $first_place,
                'top_two_count'     => $top_two,
                'mean_rank'         => $mean_rank,
                'best_rank'         => $best_rank,
                'worst_rank'        => $worst_rank,
                'rank_range'        => $worst_rank - $best_rank,
                'rank_by_branch'    => $rank_history[ $response_id ],
                'robustness'        => $label,
                'robustness_label'  => self::text( 'robustness_' . str_replace( '-', '_', $label ), $language ),
            );
        }

        usort(
            $result,
            static function ( array $a, array $b ): int {
                $by_mean = $a['mean_rank'] <=> $b['mean_rank'];
                if ( 0 !== $by_mean ) {
                    return $by_mean;
                }
                $by_top_two = $b['top_two_count'] <=> $a['top_two_count'];
                if ( 0 !== $by_top_two ) {
                    return $by_top_two;
                }
                $by_first = $b['first_place_count'] <=> $a['first_place_count'];
                return 0 !== $by_first ? $by_first : strcmp( $a['response_id'], $b['response_id'] );
            }
        );

        foreach ( $result as $index => &$item ) {
            $item['robustness_rank'] = $index + 1;
            $item['is_most_robust']  = 0 === $index;
        }
        unset( $item );

        return $result;
    }

    private static function robustness_label( int $first, int $top_two, int $worst, int $branch_count ): string {
        if ( $first >= 4 && $worst <= 2 ) {
            return 'stable-leading';
        }
        if ( $top_two >= max( 5, $branch_count - 2 ) && $worst <= 3 ) {
            return 'stable-competitive';
        }
        return 'state-sensitive';
    }

    private static function assert_required_scenarios( array $priorities ): void {
        foreach ( array_keys( self::branch_emphasis_profiles()['opening-neutral'] ) as $scenario_id ) {
            if ( ! array_key_exists( $scenario_id, $priorities ) ) {
                throw new InvalidArgumentException( sprintf( 'Scenario priority missing from Tactical Response Matrix: %s', $scenario_id ) );
            }
        }
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'name' => 'Response Robustness Across Match States',
                'robustness_stable_leading' => 'Stable leader',
                'robustness_stable_competitive' => 'Stable competitor',
                'robustness_state_sensitive' => 'State-sensitive',
                'method' => 'Each explicit match-state branch applies a documented tactical-emphasis multiplier to the same five scenario priorities and the same four response packages. Robustness is then described only by rank stability across branches. The multipliers are authored preparation assumptions, not learned probabilities, treatment effects or claims that a branch will occur.',
            ),
            'es' => array(
                'name' => 'Robustez de respuestas entre estados del partido',
                'robustness_stable_leading' => 'Líder estable',
                'robustness_stable_competitive' => 'Competidor estable',
                'robustness_state_sensitive' => 'Sensible al estado',
                'method' => 'Cada rama explícita del partido aplica un multiplicador documentado de énfasis táctico sobre las mismas cinco prioridades de escenario y los mismos cuatro paquetes de respuesta. La robustez se describe únicamente mediante estabilidad de ranking entre ramas. Los multiplicadores son supuestos de preparación definidos de forma explícita, no probabilidades aprendidas, efectos causales ni afirmaciones de que una rama vaya a ocurrir.',
            ),
        );

        return (string) ( $catalog[ $language ][ $key ] ?? $key );
    }
}
