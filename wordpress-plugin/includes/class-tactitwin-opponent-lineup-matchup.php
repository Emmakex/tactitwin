<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Opponent_Lineup_Matchup {
    public static function evaluate(
        array $lineup_result,
        array $team_profile,
        array $opponent_profile,
        array $matchup_scan,
        string $language = 'en'
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $phase_delta = $lineup_result['phase_unit_interactions']['delta'] ?? array();
        $unit_delta = $lineup_result['unit_interactions']['delta'] ?? array();
        $dimension_delta = $lineup_result['delta']['dimensions'] ?? array();
        $structure_delta = $lineup_result['delta']['structure'] ?? array();

        $scenarios = self::scenario_map( $matchup_scan['subscenarios'] ?? array() );
        $high_press_risk = self::scenario_risk( $scenarios, 'build-up-vs-high-press' );
        $low_block_risk = self::scenario_risk( $scenarios, 'attack-vs-low-block' );
        $transition_risk = self::scenario_risk( $scenarios, 'defensive-transition-after-loss' );

        $opponent_rates = $opponent_profile['rates'] ?? array();
        $opponent_avg = $opponent_profile['averages'] ?? array();
        $high_press_opportunity = self::clamp(
            ( 0.60 * ( 100.0 - self::clamp( (float) ( $opponent_rates['build_up_escape_rate'] ?? 0.0 ) ) ) ) +
            ( 0.40 * self::normalize( (float) ( $opponent_avg['own_third_turnovers_per_match'] ?? 0.0 ), 6.0 ) )
        );
        $progression_demand = round( max( $high_press_risk, $low_block_risk ) * 0.75, 1 );

        $priorities = array(
            self::priority(
                'build_up_resilience',
                $high_press_risk,
                self::single_delta_effect( (float) ( $phase_delta['build_up'] ?? 0.0 ), 1.5 ),
                array(
                    'candidate_delta' => round( (float) ( $phase_delta['build_up'] ?? 0.0 ), 2 ),
                    'opponent_scenario' => 'build-up-vs-high-press',
                    'opponent_risk' => $high_press_risk,
                ),
                $language
            ),
            self::priority(
                'progression',
                $progression_demand,
                self::single_delta_effect( (float) ( $phase_delta['progression'] ?? 0.0 ), 0.75 ),
                array(
                    'candidate_delta' => round( (float) ( $phase_delta['progression'] ?? 0.0 ), 2 ),
                    'high_press_risk' => $high_press_risk,
                    'low_block_risk' => $low_block_risk,
                ),
                $language
            ),
            self::priority(
                'final_third_creation',
                $low_block_risk,
                self::single_delta_effect( (float) ( $phase_delta['final_third'] ?? 0.0 ), 0.08 ),
                array(
                    'candidate_delta' => round( (float) ( $phase_delta['final_third'] ?? 0.0 ), 3 ),
                    'opponent_scenario' => 'attack-vs-low-block',
                    'opponent_risk' => $low_block_risk,
                ),
                $language
            ),
            self::priority(
                'transition_security',
                $transition_risk,
                self::multi_signal_effect(
                    array(
                        self::single_delta_effect( (float) ( $unit_delta['defensive_sequence'] ?? 0.0 ), 0.35 ),
                        self::single_delta_effect( (float) ( $structure_delta['rest_defence'] ?? 0.0 ), 8.0 ),
                        self::single_delta_effect( (float) ( $dimension_delta['ball_security'] ?? 0.0 ), 0.75 ),
                    )
                ),
                array(
                    'defensive_sequence_delta' => round( (float) ( $unit_delta['defensive_sequence'] ?? 0.0 ), 2 ),
                    'rest_defence_delta' => round( (float) ( $structure_delta['rest_defence'] ?? 0.0 ), 1 ),
                    'ball_security_delta' => round( (float) ( $dimension_delta['ball_security'] ?? 0.0 ), 2 ),
                    'opponent_scenario' => 'defensive-transition-after-loss',
                    'opponent_risk' => $transition_risk,
                ),
                $language
            ),
            self::priority(
                'high_press_exploitation',
                $high_press_opportunity,
                self::single_delta_effect( (float) ( $phase_delta['high_press'] ?? 0.0 ), 0.35 ),
                array(
                    'candidate_delta' => round( (float) ( $phase_delta['high_press'] ?? 0.0 ), 2 ),
                    'opponent_build_up_escape_rate' => round( (float) ( $opponent_rates['build_up_escape_rate'] ?? 0.0 ), 1 ),
                    'opponent_own_third_turnovers_per_match' => round( (float) ( $opponent_avg['own_third_turnovers_per_match'] ?? 0.0 ), 2 ),
                ),
                $language
            ),
        );

        usort(
            $priorities,
            static fn( array $a, array $b ): int => (float) $b['demand']['score'] <=> (float) $a['demand']['score']
        );

        $coverage = self::coverage( $lineup_result, $matchup_scan, $language );
        $decision = self::decision( $priorities, $coverage, $language );

        return array(
            'language' => $language,
            'team' => array(
                'source_id' => (string) ( $team_profile['team_source_id'] ?? '' ),
                'name' => (string) ( $team_profile['team_name'] ?? '' ),
            ),
            'opponent' => array(
                'source_id' => (string) ( $opponent_profile['team_source_id'] ?? '' ),
                'name' => (string) ( $opponent_profile['team_name'] ?? '' ),
            ),
            'priorities' => $priorities,
            'decision' => $decision,
            'coverage' => $coverage,
            'recommendations' => self::recommendations( $priorities, $decision, $coverage, $language ),
            'method' => array(
                'type' => 'opponent-conditioned-relative-lineup-decision-support',
                'note' => self::text( 'method', $language ),
                'limitations' => array(
                    self::text( 'limit_relative', $language ),
                    self::text( 'limit_tracking', $language ),
                    self::text( 'limit_causal', $language ),
                ),
            ),
        );
    }

    private static function priority( string $id, float $demand, string $effect, array $evidence, string $language ): array {
        $demand = self::clamp( $demand );
        return array(
            'id' => $id,
            'label' => self::text( 'priority_' . $id, $language ),
            'demand' => array(
                'score' => round( $demand, 1 ),
                'level' => self::level( $demand ),
                'level_label' => TactiTwin_I18n::level_label( self::level( $demand ), $language ),
            ),
            'relative_effect' => $effect,
            'relative_effect_label' => self::text( 'effect_' . $effect, $language ),
            'evidence' => $evidence,
        );
    }

    private static function decision( array $priorities, array $coverage, string $language ): array {
        if ( 'low' === (string) ( $coverage['status'] ?? 'low' ) ) {
            return array(
                'lean' => 'inconclusive',
                'label' => self::text( 'lean_inconclusive', $language ),
                'candidate_votes' => 0,
                'baseline_votes' => 0,
                'reason' => self::text( 'decision_low_coverage', $language ),
            );
        }

        $candidate = 0;
        $baseline = 0;
        foreach ( $priorities as $priority ) {
            $level = (string) ( $priority['demand']['level'] ?? 'low' );
            if ( 'low' === $level ) {
                continue;
            }
            $weight = 'high' === $level ? 2 : 1;
            $effect = (string) ( $priority['relative_effect'] ?? 'neutral' );
            if ( 'candidate' === $effect ) {
                $candidate += $weight;
            } elseif ( 'baseline' === $effect ) {
                $baseline += $weight;
            }
        }

        $lean = 'inconclusive';
        if ( $candidate >= $baseline + 2 ) {
            $lean = 'candidate';
        } elseif ( $baseline >= $candidate + 2 ) {
            $lean = 'baseline';
        }

        return array(
            'lean' => $lean,
            'label' => self::text( 'lean_' . $lean, $language ),
            'candidate_votes' => $candidate,
            'baseline_votes' => $baseline,
            'reason' => self::text( 'decision_' . $lean, $language ),
        );
    }

    private static function coverage( array $lineup_result, array $matchup_scan, string $language ): array {
        $scan_status = (string) ( $matchup_scan['coverage']['status'] ?? 'low' );
        $lineup_status = (string) ( $lineup_result['coverage']['status'] ?? 'low' );
        $phase_coverage = $lineup_result['phase_unit_interactions']['candidate']['coverage'] ?? array();
        $possible = max( 1, (int) ( $phase_coverage['possible_pairs'] ?? 0 ) );
        $under = max( 0, (int) ( $phase_coverage['under_observed_pairs'] ?? 0 ) );
        $under_pct = round( ( $under / $possible ) * 100.0, 1 );

        $status = 'high';
        if ( 'low' === $scan_status || 'low' === $lineup_status || $under_pct > 25.0 ) {
            $status = 'low';
        } elseif ( 'medium' === $scan_status || 'medium' === $lineup_status || $under_pct > 15.0 ) {
            $status = 'medium';
        }

        return array(
            'status' => $status,
            'status_label' => TactiTwin_I18n::level_label( $status, $language ),
            'matchup_scan_status' => $scan_status,
            'lineup_profile_status' => $lineup_status,
            'candidate_under_observed_pairs' => $under,
            'candidate_possible_pairs' => (int) ( $phase_coverage['possible_pairs'] ?? 0 ),
            'candidate_under_observed_pct' => $under_pct,
            'warning' => 'low' === $status ? self::text( 'coverage_low', $language ) : null,
        );
    }

    private static function recommendations( array $priorities, array $decision, array $coverage, string $language ): array {
        $items = array();
        foreach ( $priorities as $priority ) {
            if ( 'low' === (string) ( $priority['demand']['level'] ?? 'low' ) ) {
                continue;
            }
            $effect = (string) ( $priority['relative_effect'] ?? 'neutral' );
            if ( 'candidate' === $effect ) {
                $items[] = sprintf( self::text( 'recommend_candidate_priority', $language ), (string) $priority['label'] );
            } elseif ( 'baseline' === $effect ) {
                $items[] = sprintf( self::text( 'recommend_baseline_priority', $language ), (string) $priority['label'] );
            }
        }
        if ( 'low' === (string) ( $coverage['status'] ?? 'low' ) ) {
            $items[] = self::text( 'coverage_low', $language );
        }
        $items[] = (string) ( $decision['reason'] ?? '' );
        return array_values( array_unique( array_filter( $items ) ) );
    }

    private static function scenario_map( array $scenarios ): array {
        $map = array();
        foreach ( $scenarios as $scenario ) {
            $id = (string) ( $scenario['scenario_id'] ?? '' );
            if ( '' !== $id ) {
                $map[ $id ] = $scenario;
            }
        }
        return $map;
    }

    private static function scenario_risk( array $scenarios, string $id ): float {
        return self::clamp( (float) ( $scenarios[ $id ]['risk']['score'] ?? 0.0 ) );
    }

    private static function single_delta_effect( float $delta, float $threshold ): string {
        if ( $delta >= $threshold ) {
            return 'candidate';
        }
        if ( $delta <= -$threshold ) {
            return 'baseline';
        }
        return 'neutral';
    }

    private static function multi_signal_effect( array $effects ): string {
        $candidate = count( array_filter( $effects, static fn( string $effect ): bool => 'candidate' === $effect ) );
        $baseline = count( array_filter( $effects, static fn( string $effect ): bool => 'baseline' === $effect ) );
        if ( $candidate > $baseline ) {
            return 'candidate';
        }
        if ( $baseline > $candidate ) {
            return 'baseline';
        }
        return 'neutral';
    }

    private static function normalize( float $value, float $reference ): float {
        return $reference > 0.0 ? self::clamp( ( $value / $reference ) * 100.0 ) : 0.0;
    }

    private static function clamp( float $value ): float {
        return max( 0.0, min( 100.0, round( $value, 1 ) ) );
    }

    private static function level( float $score ): string {
        if ( $score >= 65.0 ) {
            return 'high';
        }
        if ( $score >= 45.0 ) {
            return 'medium';
        }
        return 'low';
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'priority_build_up_resilience' => 'build-up resilience against pressure',
                'priority_progression' => 'territorial progression',
                'priority_final_third_creation' => 'final-third creation against a compact defence',
                'priority_transition_security' => 'defensive transition security after loss',
                'priority_high_press_exploitation' => 'high-press opportunity against opponent build-up',
                'effect_candidate' => 'Candidate XI edge',
                'effect_baseline' => 'Baseline XI edge',
                'effect_neutral' => 'No material relative edge',
                'lean_candidate' => 'Candidate XI',
                'lean_baseline' => 'Baseline XI',
                'lean_inconclusive' => 'Inconclusive',
                'decision_candidate' => 'The candidate XI has the clearer edge across the opponent-relevant medium/high-priority demands. Treat this as relative decision support, not a predicted match outcome.',
                'decision_baseline' => 'The baseline XI retains the clearer edge across the opponent-relevant medium/high-priority demands. Treat this as relative decision support, not a predicted match outcome.',
                'decision_inconclusive' => 'The opponent-conditioned evidence is mixed; keep the decision open and use role-specific coaching intent or additional evidence rather than forcing a lineup ranking.',
                'decision_low_coverage' => 'Evidence coverage is too low for an opponent-conditioned lineup lean. The priority evidence is still shown, but no lineup preference is asserted.',
                'recommend_candidate_priority' => 'Opponent-conditioned edge for the candidate XI: %s.',
                'recommend_baseline_priority' => 'Opponent-conditioned trade-off for the candidate XI: the baseline remains stronger for %s.',
                'coverage_low' => 'Opponent-conditioned lineup confidence is low because matchup or lineup-network evidence is limited; rehearse or observe the under-sampled units before treating this as a selection signal.',
                'method' => 'This layer conditions relative lineup deltas on opponent demands already detected by TactiTwin. It does not invent an absolute lineup score and keeps phase, unit and formation evidence auditable.',
                'limit_relative' => 'The output compares the two supplied XI variants; it does not claim either XI is globally optimal.',
                'limit_tracking' => 'Without tracking/video, the model cannot verify spacing, line distances, synchronized rotations, cover shadows or true block geometry.',
                'limit_causal' => 'Historical interactions and opponent tendencies do not prove causal performance in the future match.',
            ),
            'es' => array(
                'priority_build_up_resilience' => 'resistencia de la salida ante presión',
                'priority_progression' => 'progresión territorial',
                'priority_final_third_creation' => 'creación en último tercio ante defensa compacta',
                'priority_transition_security' => 'seguridad en transición defensiva tras pérdida',
                'priority_high_press_exploitation' => 'oportunidad de presión alta contra la salida rival',
                'effect_candidate' => 'Ventaja del XI candidato',
                'effect_baseline' => 'Ventaja del XI base',
                'effect_neutral' => 'Sin ventaja relativa material',
                'lean_candidate' => 'XI candidato',
                'lean_baseline' => 'XI base',
                'lean_inconclusive' => 'No concluyente',
                'decision_candidate' => 'El XI candidato presenta la ventaja más clara en las demandas de prioridad media/alta que impone este rival. Debe leerse como apoyo relativo a la decisión, no como predicción del resultado.',
                'decision_baseline' => 'El XI base conserva la ventaja más clara en las demandas de prioridad media/alta que impone este rival. Debe leerse como apoyo relativo a la decisión, no como predicción del resultado.',
                'decision_inconclusive' => 'La evidencia condicionada por el rival es mixta; conviene mantener abierta la decisión y usar la intención táctica por roles o más evidencia en lugar de forzar un ranking del XI.',
                'decision_low_coverage' => 'La cobertura es insuficiente para inclinar la decisión hacia un XI. Se muestran las prioridades, pero no se afirma una preferencia de alineación.',
                'recommend_candidate_priority' => 'Ventaja condicionada por el rival para el XI candidato: %s.',
                'recommend_baseline_priority' => 'Coste condicionado por el rival para el XI candidato: el XI base sigue siendo más fuerte en %s.',
                'coverage_low' => 'La confianza del cruce XI–rival es baja porque la evidencia del matchup o de la red del XI es limitada; conviene ensayar u observar las unidades con poca muestra antes de usarlo como señal de selección.',
                'method' => 'Esta capa condiciona los deltas relativos entre alineaciones con las demandas del rival ya detectadas por TactiTwin. No inventa una nota absoluta del XI y mantiene auditables las evidencias de fase, unidad y formación.',
                'limit_relative' => 'El resultado compara únicamente los dos XI suministrados; no afirma que ninguno sea el XI óptimo global.',
                'limit_tracking' => 'Sin tracking/vídeo, el modelo no puede verificar espacios, distancias entre líneas, rotaciones sincronizadas, sombras de cobertura ni la geometría real del bloque.',
                'limit_causal' => 'Las interacciones históricas y tendencias del rival no demuestran rendimiento causal en el partido futuro.',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
