<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Builds a deterministic comparison of tactical response packages from an
 * existing Full Matchup Scan.
 *
 * The response fit score is a decision-support prioritisation index. It is
 * not a probability, expected-goals model, scoreline forecast or causal
 * estimate of tactical effectiveness.
 */
final class TactiTwin_Tactical_Response_Matrix {
    public static function compose( array $scan, string $language = 'en' ): array {
        $language = TactiTwin_I18n::normalize_language( $language );

        if ( 'full-matchup-scan' !== (string) ( $scan['scenario_id'] ?? '' ) || empty( $scan['subscenarios'] ) ) {
            throw new InvalidArgumentException( 'A Full Matchup Scan with subscenarios is required.' );
        }

        $scenarios = self::index_scenarios( $scan['subscenarios'] );
        self::assert_required_scenarios( $scenarios );

        $scenario_priorities = self::scenario_priorities( $scenarios );
        $responses           = array();

        foreach ( self::response_profiles() as $response_id => $profile ) {
            $contributions = array();
            $fit_score     = 0.0;

            foreach ( $profile['weights'] as $scenario_id => $weight ) {
                $scenario     = $scenarios[ $scenario_id ];
                $priority     = (float) $scenario_priorities[ $scenario_id ];
                $contribution = round( $priority * $weight, 2 );
                $fit_score   += $contribution;

                $contributions[] = array(
                    'scenario_id'          => $scenario_id,
                    'scenario_name'        => (string) ( $scenario['scenario_name'] ?? $scenario_id ),
                    'response_weight'      => (float) $weight,
                    'scenario_priority'    => $priority,
                    'risk_score'           => self::score( $scenario, 'risk' ),
                    'opportunity_score'    => self::score( $scenario, 'opportunity' ),
                    'weighted_contribution'=> $contribution,
                );
            }

            usort(
                $contributions,
                static function ( array $a, array $b ): int {
                    $by_contribution = $b['weighted_contribution'] <=> $a['weighted_contribution'];
                    return 0 !== $by_contribution
                        ? $by_contribution
                        : strcmp( $a['scenario_id'], $b['scenario_id'] );
                }
            );

            $responses[] = array(
                'response_id'    => $response_id,
                'response_name'  => self::response_text( $response_id, 'name', $language ),
                'description'    => self::response_text( $response_id, 'description', $language ),
                'fit_score'      => round( $fit_score, 1 ),
                'dominant_driver'=> array(
                    'scenario_id'   => (string) ( $contributions[0]['scenario_id'] ?? '' ),
                    'scenario_name' => (string) ( $contributions[0]['scenario_name'] ?? '' ),
                    'contribution'  => (float) ( $contributions[0]['weighted_contribution'] ?? 0 ),
                ),
                'scenario_contributions' => $contributions,
                'instructions'     => self::instructions( $contributions, $scenarios ),
                'trade_offs'       => self::trade_offs( $profile['weights'], $scenario_priorities, $scenarios, $language ),
                'guardrails'       => array(
                    'outcome_prediction'      => false,
                    'causal_effectiveness'    => false,
                    'changes_tactical_scores' => false,
                    'changes_recommendations' => false,
                    'activates_plan'          => false,
                ),
            );
        }

        usort(
            $responses,
            static function ( array $a, array $b ): int {
                $by_score = $b['fit_score'] <=> $a['fit_score'];
                return 0 !== $by_score
                    ? $by_score
                    : strcmp( $a['response_id'], $b['response_id'] );
            }
        );

        $top_score = (float) ( $responses[0]['fit_score'] ?? 0 );
        foreach ( $responses as $index => &$response ) {
            $response['rank']          = $index + 1;
            $response['gap_to_top']    = round( $top_score - (float) $response['fit_score'], 1 );
            $response['is_top_fit']    = 0 === $index;
        }
        unset( $response );

        return array(
            'matrix_id'   => 'tactical-response-matrix',
            'matrix_name' => self::text( 'matrix_name', $language ),
            'language'    => $language,
            'team'        => $scan['team'] ?? array(),
            'opponent'    => $scan['opponent'] ?? array(),
            'coverage'    => array(
                'effective_matches' => (int) ( $scan['coverage']['effective_matches'] ?? 0 ),
                'status'            => (string) ( $scan['coverage']['status'] ?? 'low' ),
                'warning'           => $scan['coverage']['warning'] ?? null,
            ),
            'scenario_priorities' => $scenario_priorities,
            'responses'           => $responses,
            'top_response_id'     => (string) ( $responses[0]['response_id'] ?? '' ),
            'method' => array(
                'type' => 'deterministic-tactical-response-comparison',
                'scenario_priority_formula' => '0.60*risk + 0.40*opportunity',
                'response_fit_formula'       => 'sum(response_weight * scenario_priority)',
                'response_weights_sum_to'    => 1.0,
                'note' => self::text( 'method', $language ),
                'not_a_probability'            => true,
                'not_a_scoreline_prediction'   => true,
                'not_a_causal_effect_estimate' => true,
            ),
        );
    }

    private static function response_profiles(): array {
        return array(
            'balanced-control' => array(
                'weights' => array(
                    'build-up-vs-high-press'          => 0.25,
                    'opponent-side-bias'              => 0.15,
                    'defensive-transition-after-loss' => 0.25,
                    'attack-vs-low-block'             => 0.15,
                    'exploit-opponent-weak-side'      => 0.20,
                ),
            ),
            'press-resistant-build-up' => array(
                'weights' => array(
                    'build-up-vs-high-press'          => 0.45,
                    'opponent-side-bias'              => 0.10,
                    'defensive-transition-after-loss' => 0.20,
                    'attack-vs-low-block'             => 0.10,
                    'exploit-opponent-weak-side'      => 0.15,
                ),
            ),
            'transition-security' => array(
                'weights' => array(
                    'build-up-vs-high-press'          => 0.15,
                    'opponent-side-bias'              => 0.15,
                    'defensive-transition-after-loss' => 0.45,
                    'attack-vs-low-block'             => 0.10,
                    'exploit-opponent-weak-side'      => 0.15,
                ),
            ),
            'width-and-switch' => array(
                'weights' => array(
                    'build-up-vs-high-press'          => 0.10,
                    'opponent-side-bias'              => 0.15,
                    'defensive-transition-after-loss' => 0.10,
                    'attack-vs-low-block'             => 0.30,
                    'exploit-opponent-weak-side'      => 0.35,
                ),
            ),
        );
    }

    private static function scenario_priorities( array $scenarios ): array {
        $priorities = array();
        foreach ( $scenarios as $scenario_id => $scenario ) {
            $priorities[ $scenario_id ] = round(
                ( self::score( $scenario, 'risk' ) * 0.60 ) +
                ( self::score( $scenario, 'opportunity' ) * 0.40 ),
                1
            );
        }
        return $priorities;
    }

    private static function trade_offs(
        array $weights,
        array $priorities,
        array $scenarios,
        string $language
    ): array {
        $trade_offs = array();

        foreach ( $priorities as $scenario_id => $priority ) {
            $weight = (float) ( $weights[ $scenario_id ] ?? 0 );
            if ( $priority < 55.0 || $weight > 0.15 ) {
                continue;
            }

            $trade_offs[] = array(
                'scenario_id'       => $scenario_id,
                'scenario_name'     => (string) ( $scenarios[ $scenario_id ]['scenario_name'] ?? $scenario_id ),
                'scenario_priority' => (float) $priority,
                'response_weight'   => $weight,
                'note'              => sprintf(
                    self::text( 'trade_off', $language ),
                    (string) ( $scenarios[ $scenario_id ]['scenario_name'] ?? $scenario_id )
                ),
            );
        }

        usort(
            $trade_offs,
            static fn( array $a, array $b ): int => $b['scenario_priority'] <=> $a['scenario_priority']
        );

        return $trade_offs;
    }

    private static function instructions( array $contributions, array $scenarios ): array {
        $instructions = array();

        foreach ( array_slice( $contributions, 0, 3 ) as $contribution ) {
            $scenario_id = (string) ( $contribution['scenario_id'] ?? '' );
            $primary = (string) ( $scenarios[ $scenario_id ]['recommendations']['primary'] ?? '' );
            if ( '' !== $primary ) {
                $instructions[] = $primary;
            }
            if ( 2 === count( $instructions ) ) {
                break;
            }
        }

        return array_values( array_unique( $instructions ) );
    }

    private static function assert_required_scenarios( array $scenarios ): void {
        foreach ( array_keys( self::response_profiles()['balanced-control']['weights'] ) as $scenario_id ) {
            if ( ! isset( $scenarios[ $scenario_id ] ) ) {
                throw new InvalidArgumentException( sprintf( 'Required scenario missing from Full Matchup Scan: %s', $scenario_id ) );
            }
        }
    }

    private static function index_scenarios( array $scenarios ): array {
        $indexed = array();
        foreach ( $scenarios as $scenario ) {
            $scenario_id = (string) ( $scenario['scenario_id'] ?? '' );
            if ( '' !== $scenario_id ) {
                $indexed[ $scenario_id ] = $scenario;
            }
        }
        return $indexed;
    }

    private static function score( array $scenario, string $dimension ): float {
        return (float) ( $scenario[ $dimension ]['score'] ?? 0 );
    }

    private static function response_text( string $response_id, string $field, string $language ): string {
        $catalog = array(
            'en' => array(
                'balanced-control' => array(
                    'name' => 'Balanced Control',
                    'description' => 'Spread attention across build-up security, transition protection and the main attacking opportunity without overcommitting to one tactical hypothesis.',
                ),
                'press-resistant-build-up' => array(
                    'name' => 'Press-Resistant Build-up',
                    'description' => 'Prioritise escaping or manipulating the opponent first press while keeping enough transition protection behind the ball.',
                ),
                'transition-security' => array(
                    'name' => 'Transition Security',
                    'description' => 'Protect the team against dangerous losses and first-forward-pass threats before increasing attacking risk.',
                ),
                'width-and-switch' => array(
                    'name' => 'Width & Switch',
                    'description' => 'Prioritise low-block manipulation, weak-side access and changes of orientation to attack the best available channel.',
                ),
            ),
            'es' => array(
                'balanced-control' => array(
                    'name' => 'Control equilibrado',
                    'description' => 'Reparte la atención entre seguridad en salida, protección de la transición y la principal oportunidad ofensiva sin sobrerreaccionar a una sola hipótesis táctica.',
                ),
                'press-resistant-build-up' => array(
                    'name' => 'Salida resistente a la presión',
                    'description' => 'Prioriza superar o manipular la primera presión rival manteniendo suficiente protección de la transición por detrás del balón.',
                ),
                'transition-security' => array(
                    'name' => 'Seguridad en transición',
                    'description' => 'Protege al equipo ante pérdidas peligrosas y amenazas de primer pase vertical antes de aumentar el riesgo ofensivo.',
                ),
                'width-and-switch' => array(
                    'name' => 'Amplitud y cambio de orientación',
                    'description' => 'Prioriza manipular el bloque bajo, acceder al lado débil y cambiar la orientación para atacar el mejor canal disponible.',
                ),
            ),
        );

        return (string) ( $catalog[ $language ][ $response_id ][ $field ] ?? $response_id );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'matrix_name' => 'Tactical Response Matrix',
                'trade_off'   => '%s remains a high-priority matchup issue but receives limited emphasis in this response package.',
                'method'      => 'The matrix stress-tests several tactical response packages against the same existing matchup evidence. Fit ranks response emphasis, not expected match outcome, win probability or causal effectiveness.',
            ),
            'es' => array(
                'matrix_name' => 'Matriz de respuestas tácticas',
                'trade_off'   => '%s sigue siendo un problema prioritario del enfrentamiento, pero recibe un énfasis limitado en este paquete de respuesta.',
                'method'      => 'La matriz contrasta varios paquetes de respuesta táctica contra la misma evidencia existente del enfrentamiento. El encaje ordena el énfasis de cada respuesta, no el resultado esperado, la probabilidad de victoria ni la efectividad causal.',
            ),
        );

        return (string) ( $catalog[ $language ][ $key ] ?? $key );
    }
}
