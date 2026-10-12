<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Builds a deterministic pre-match branch catalog from the existing
 * score-state/manpower What-if engine.
 *
 * The tree does not assign probabilities to branches and does not infer that
 * one branch is more likely to happen than another.
 */
final class TactiTwin_Match_State_Scenario_Tree {
    public static function compose(
        array $team_profile,
        array $opponent_profile,
        array $scan,
        string $language = 'en'
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );

        if ( 'full-matchup-scan' !== (string) ( $scan['scenario_id'] ?? '' ) || empty( $scan['subscenarios'] ) ) {
            throw new InvalidArgumentException( 'A Full Matchup Scan with subscenarios is required.' );
        }

        $branches = array();
        foreach ( self::branch_definitions() as $definition ) {
            $context = TactiTwin_Context_What_If::evaluate(
                $team_profile,
                $opponent_profile,
                $scan,
                (int) $definition['our_score'],
                (int) $definition['opponent_score'],
                (int) $definition['minute'],
                $language,
                (int) $definition['our_players'],
                (int) $definition['opponent_players']
            );

            $risk        = (float) ( $context['risk']['score'] ?? 0 );
            $opportunity = (float) ( $context['opportunity']['score'] ?? 0 );
            $delta       = round( $risk - $opportunity, 1 );

            $branches[] = array(
                'branch_id'    => (string) $definition['branch_id'],
                'branch_name'  => self::branch_text( (string) $definition['branch_id'], 'name', $language ),
                'purpose'      => self::branch_text( (string) $definition['branch_id'], 'purpose', $language ),
                'phase'        => (string) $definition['phase'],
                'hypothesis'   => $context['hypothesis'] ?? array(),
                'risk'         => $context['risk'] ?? array(),
                'opportunity'  => $context['opportunity'] ?? array(),
                'stress_signature' => array(
                    'risk_minus_opportunity' => $delta,
                    'orientation'            => self::orientation( $delta ),
                    'orientation_label'      => self::orientation_label( self::orientation( $delta ), $language ),
                ),
                'coverage'             => $context['coverage'] ?? array(),
                'recommendations'      => $context['recommendations'] ?? array(),
                'decision_triggers'    => $context['decision_triggers'] ?? array(),
                'historical_behavior'  => $context['historical_behavior'] ?? array(),
                'scan_context'         => $context['scan_context'] ?? array(),
                'source_method'        => $context['method'] ?? array(),
            );
        }

        $response_matrix = TactiTwin_Tactical_Response_Matrix::compose( $scan, $language );

        return array(
            'tree_id'   => 'match-state-scenario-tree',
            'tree_name' => self::text( 'tree_name', $language ),
            'language'  => $language,
            'team'      => $response_matrix['team'] ?? array(),
            'opponent'  => $response_matrix['opponent'] ?? array(),
            'coverage'  => $response_matrix['coverage'] ?? array(),
            'branches'  => $branches,
            'summary'   => self::summary( $branches ),
            'response_matrix_reference' => array(
                'matrix_id'       => (string) ( $response_matrix['matrix_id'] ?? '' ),
                'top_response_id' => (string) ( $response_matrix['top_response_id'] ?? '' ),
                'responses'       => $response_matrix['responses'] ?? array(),
                'state_reranking_applied' => false,
            ),
            'method' => array(
                'type' => 'deterministic-match-state-scenario-tree',
                'branch_probability_model' => false,
                'response_state_reranking' => false,
                'changes_tactical_scores'  => false,
                'changes_recommendations'  => false,
                'activates_plan'            => false,
                'note' => self::text( 'method', $language ),
            ),
        );
    }

    public static function branch_definitions(): array {
        return array(
            array(
                'branch_id'        => 'opening-neutral',
                'phase'            => 'opening',
                'minute'           => 15,
                'our_score'        => 0,
                'opponent_score'   => 0,
                'our_players'      => 11,
                'opponent_players' => 11,
            ),
            array(
                'branch_id'        => 'second-half-neutral',
                'phase'            => 'second-half',
                'minute'           => 60,
                'our_score'        => 0,
                'opponent_score'   => 0,
                'our_players'      => 11,
                'opponent_players' => 11,
            ),
            array(
                'branch_id'        => 'protect-lead',
                'phase'            => 'late-second-half',
                'minute'           => 70,
                'our_score'        => 1,
                'opponent_score'   => 0,
                'our_players'      => 11,
                'opponent_players' => 11,
            ),
            array(
                'branch_id'        => 'chase-game',
                'phase'            => 'late-second-half',
                'minute'           => 70,
                'our_score'        => 0,
                'opponent_score'   => 1,
                'our_players'      => 11,
                'opponent_players' => 11,
            ),
            array(
                'branch_id'        => 'late-draw',
                'phase'            => 'closing',
                'minute'           => 80,
                'our_score'        => 0,
                'opponent_score'   => 0,
                'our_players'      => 11,
                'opponent_players' => 11,
            ),
            array(
                'branch_id'        => 'numerical-superiority',
                'phase'            => 'second-half',
                'minute'           => 60,
                'our_score'        => 0,
                'opponent_score'   => 0,
                'our_players'      => 11,
                'opponent_players' => 10,
            ),
            array(
                'branch_id'        => 'numerical-inferiority',
                'phase'            => 'second-half',
                'minute'           => 60,
                'our_score'        => 0,
                'opponent_score'   => 0,
                'our_players'      => 10,
                'opponent_players' => 11,
            ),
        );
    }

    private static function summary( array $branches ): array {
        $highest_risk        = array();
        $highest_opportunity = array();
        $risk_score          = -1.0;
        $opportunity_score   = -1.0;
        $low_coverage        = 0;
        $orientations        = array(
            'risk-dominant'        => 0,
            'balanced-pressure'    => 0,
            'opportunity-dominant' => 0,
        );

        foreach ( $branches as $branch ) {
            $branch_risk        = (float) ( $branch['risk']['score'] ?? 0 );
            $branch_opportunity = (float) ( $branch['opportunity']['score'] ?? 0 );

            if ( $branch_risk > $risk_score ) {
                $risk_score   = $branch_risk;
                $highest_risk = self::branch_ref( $branch, $branch_risk );
            }
            if ( $branch_opportunity > $opportunity_score ) {
                $opportunity_score   = $branch_opportunity;
                $highest_opportunity = self::branch_ref( $branch, $branch_opportunity );
            }

            $coverage_status = (string) ( $branch['coverage']['status'] ?? '' );
            $manpower_status = (string) ( $branch['coverage']['manpower']['status'] ?? '' );
            if ( 'low' === $coverage_status || 'low' === $manpower_status ) {
                $low_coverage++;
            }

            $orientation = (string) ( $branch['stress_signature']['orientation'] ?? 'balanced-pressure' );
            if ( isset( $orientations[ $orientation ] ) ) {
                $orientations[ $orientation ]++;
            }
        }

        return array(
            'branch_count'             => count( $branches ),
            'highest_risk_branch'      => $highest_risk,
            'highest_opportunity_branch' => $highest_opportunity,
            'low_coverage_branches'    => $low_coverage,
            'orientation_counts'       => $orientations,
        );
    }

    private static function branch_ref( array $branch, float $score ): array {
        return array(
            'branch_id'   => (string) ( $branch['branch_id'] ?? '' ),
            'branch_name' => (string) ( $branch['branch_name'] ?? '' ),
            'score'       => $score,
        );
    }

    private static function orientation( float $delta ): string {
        if ( $delta >= 10.0 ) {
            return 'risk-dominant';
        }
        if ( $delta <= -10.0 ) {
            return 'opportunity-dominant';
        }
        return 'balanced-pressure';
    }

    private static function orientation_label( string $orientation, string $language ): string {
        return self::text( 'orientation_' . str_replace( '-', '_', $orientation ), $language );
    }

    private static function branch_text( string $branch_id, string $field, string $language ): string {
        $catalog = array(
            'en' => array(
                'opening-neutral' => array(
                    'name' => 'Opening neutral',
                    'purpose' => 'Validate the pre-match assumptions before the game state has forced a tactical response.',
                ),
                'second-half-neutral' => array(
                    'name' => 'Second-half neutral',
                    'purpose' => 'Prepare the response if the match reaches the second half still level and numerically balanced.',
                ),
                'protect-lead' => array(
                    'name' => 'Protect a one-goal lead',
                    'purpose' => 'Prepare how risk, possession and defensive behaviour should be reviewed while leading late in the match.',
                ),
                'chase-game' => array(
                    'name' => 'Chase a one-goal deficit',
                    'purpose' => 'Prepare the higher-urgency branch when the team needs to recover a one-goal deficit.',
                ),
                'late-draw' => array(
                    'name' => 'Late draw',
                    'purpose' => 'Prepare the closing-stage trade-off between protecting the draw and increasing attacking ambition.',
                ),
                'numerical-superiority' => array(
                    'name' => 'Numerical superiority',
                    'purpose' => 'Prepare the structural response to playing 11v10 without assuming that the advantage guarantees control or scoring.',
                ),
                'numerical-inferiority' => array(
                    'name' => 'Numerical inferiority',
                    'purpose' => 'Prepare the structural response to playing 10v11 while preserving an outlet and explicit pressing triggers.',
                ),
            ),
            'es' => array(
                'opening-neutral' => array(
                    'name' => 'Inicio neutral',
                    'purpose' => 'Validar las hipótesis prepartido antes de que el estado del encuentro obligue a una respuesta táctica.',
                ),
                'second-half-neutral' => array(
                    'name' => 'Segunda parte neutral',
                    'purpose' => 'Preparar la respuesta si el partido llega a la segunda parte empatado y en igualdad numérica.',
                ),
                'protect-lead' => array(
                    'name' => 'Proteger un gol de ventaja',
                    'purpose' => 'Preparar cómo revisar riesgo, posesión y comportamiento defensivo cuando se gana por un gol en la parte final.',
                ),
                'chase-game' => array(
                    'name' => 'Remontar un gol de desventaja',
                    'purpose' => 'Preparar la rama de mayor urgencia cuando el equipo necesita recuperar un gol de desventaja.',
                ),
                'late-draw' => array(
                    'name' => 'Empate en el tramo final',
                    'purpose' => 'Preparar el equilibrio final entre proteger el empate y aumentar la ambición ofensiva.',
                ),
                'numerical-superiority' => array(
                    'name' => 'Superioridad numérica',
                    'purpose' => 'Preparar la respuesta estructural para jugar 11v10 sin asumir que la ventaja garantiza control o gol.',
                ),
                'numerical-inferiority' => array(
                    'name' => 'Inferioridad numérica',
                    'purpose' => 'Preparar la respuesta estructural para jugar 10v11 manteniendo una salida y disparadores de presión explícitos.',
                ),
            ),
        );

        return (string) ( $catalog[ $language ][ $branch_id ][ $field ] ?? $branch_id );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'tree_name' => 'Match-State Scenario Tree',
                'orientation_risk_dominant' => 'Risk-dominant context',
                'orientation_balanced_pressure' => 'Balanced risk/opportunity context',
                'orientation_opportunity_dominant' => 'Opportunity-dominant context',
                'method' => 'The scenario tree evaluates several explicit fictional match states through the existing score-state/manpower What-if engine. Branches are preparation cases, not forecasts, and receive no occurrence probability. The v1.27 response matrix is shown as a shared baseline reference and is not re-ranked per branch in v1.28.',
            ),
            'es' => array(
                'tree_name' => 'Árbol de escenarios por estado del partido',
                'orientation_risk_dominant' => 'Contexto dominado por el riesgo',
                'orientation_balanced_pressure' => 'Contexto equilibrado entre riesgo y oportunidad',
                'orientation_opportunity_dominant' => 'Contexto dominado por la oportunidad',
                'method' => 'El árbol de escenarios evalúa varios estados ficticios y explícitos del partido mediante el motor What-if existente de marcador y estado numérico. Las ramas son casos de preparación, no pronósticos, y no reciben probabilidad de ocurrencia. La matriz de respuestas v1.27 se muestra como referencia base compartida y no se reordena por rama en v1.28.',
            ),
        );

        return (string) ( $catalog[ $language ][ $key ] ?? $key );
    }
}
