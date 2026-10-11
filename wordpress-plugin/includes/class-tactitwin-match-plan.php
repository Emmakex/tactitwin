<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Match_Plan {
    public static function compose( array $scan, string $language = 'en' ): array {
        $language = TactiTwin_I18n::normalize_language( $language );

        if ( 'full-matchup-scan' !== (string) ( $scan['scenario_id'] ?? '' ) || empty( $scan['subscenarios'] ) ) {
            throw new InvalidArgumentException( 'A Full Matchup Scan with subscenarios is required.' );
        }

        $scenarios = self::index_scenarios( $scan['subscenarios'] );
        $priorities = self::priorities( $scan['subscenarios'] );
        $top_risk = self::top_by( $scan['subscenarios'], 'risk' );
        $top_opportunity = self::top_by( $scan['subscenarios'], 'opportunity' );

        $effective_matches = (int) ( $scan['coverage']['effective_matches'] ?? 0 );
        $coverage_status   = (string) ( $scan['coverage']['status'] ?? 'low' );

        return array(
            'plan_id'   => 'match-plan',
            'plan_name' => self::text( 'plan_name', $language ),
            'language'  => $language,
            'team'      => $scan['team'] ?? array(),
            'opponent'  => $scan['opponent'] ?? array(),
            'coverage'  => array(
                'effective_matches' => $effective_matches,
                'status'            => $coverage_status,
                'status_label'      => TactiTwin_I18n::level_label( $coverage_status, $language ),
                'warning'           => $scan['coverage']['warning'] ?? null,
            ),
            'summary' => array(
                'headline' => sprintf(
                    self::text( 'headline', $language ),
                    (string) ( $top_risk['scenario_name'] ?? '-' ),
                    self::score( $top_risk, 'risk' ),
                    (string) ( $top_opportunity['scenario_name'] ?? '-' ),
                    self::score( $top_opportunity, 'opportunity' )
                ),
                'highest_risk_scenario' => array(
                    'scenario_id'   => (string) ( $top_risk['scenario_id'] ?? '' ),
                    'scenario_name' => (string) ( $top_risk['scenario_name'] ?? '' ),
                    'score'         => self::score( $top_risk, 'risk' ),
                ),
                'best_opportunity_scenario' => array(
                    'scenario_id'   => (string) ( $top_opportunity['scenario_id'] ?? '' ),
                    'scenario_name' => (string) ( $top_opportunity['scenario_name'] ?? '' ),
                    'score'         => self::score( $top_opportunity, 'opportunity' ),
                ),
                'average_risk'        => (float) ( $scan['risk']['average_score'] ?? 0 ),
                'average_opportunity' => (float) ( $scan['opportunity']['score'] ?? 0 ),
            ),
            'priorities' => array_slice( $priorities, 0, 3 ),
            'phases' => array(
                'pre_match_training' => self::pre_match_training( $scenarios, $language ),
                'opening_15_minutes'  => self::opening_phase( $scenarios, $language ),
                'in_possession'       => self::in_possession( $scenarios ),
                'out_of_possession'   => self::out_of_possession( $scenarios ),
                'after_loss'          => self::after_loss( $scenarios ),
                'after_recovery'      => self::after_recovery( $scenarios ),
                'halftime_checks'     => self::halftime_checks( $scenarios, $language ),
            ),
            'decision_triggers' => self::decision_triggers( $scenarios, $language ),
            'evidence' => array(
                'scenario_count'   => count( $scan['subscenarios'] ),
                'scenario_ranking' => $scan['evidence']['scenario_ranking'] ?? array(),
                'source_plan'      => 'full-matchup-scan',
            ),
            'method' => array(
                'type' => 'deterministic-match-plan-composition',
                'priority_formula' => '0.60*risk + 0.40*opportunity',
                'note' => self::text( 'method', $language ),
            ),
        );
    }

    private static function priorities( array $scenarios ): array {
        $priorities = array();

        foreach ( $scenarios as $scenario ) {
            $risk        = self::score( $scenario, 'risk' );
            $opportunity = self::score( $scenario, 'opportunity' );
            $priority    = round( ( $risk * 0.60 ) + ( $opportunity * 0.40 ), 1 );
            $primary     = (string) ( $scenario['recommendations']['primary'] ?? reset( $scenario['recommendations'] ) ?? '' );

            $priorities[] = array(
                'scenario_id'       => (string) ( $scenario['scenario_id'] ?? '' ),
                'scenario_name'     => (string) ( $scenario['scenario_name'] ?? '' ),
                'priority_score'    => $priority,
                'risk_score'        => $risk,
                'opportunity_score' => $opportunity,
                'instruction'       => $primary,
            );
        }

        usort(
            $priorities,
            static fn( array $a, array $b ): int => $b['priority_score'] <=> $a['priority_score']
        );

        foreach ( $priorities as $index => &$priority ) {
            $priority['rank'] = $index + 1;
        }
        unset( $priority );

        return $priorities;
    }

    private static function pre_match_training( array $scenarios, string $language ): array {
        $items = array();

        self::append_recommendation( $items, $scenarios, 'defensive-transition-after-loss', 'counterpress' );
        self::append_recommendation( $items, $scenarios, 'defensive-transition-after-loss', 'loss_zone' );
        self::append_recommendation( $items, $scenarios, 'opponent-side-bias', 'training_focus' );

        if ( self::risk( $scenarios, 'attack-vs-low-block' ) >= 45.0 ) {
            self::append_recommendation( $items, $scenarios, 'attack-vs-low-block', 'circulation' );
        }

        if ( empty( $items ) ) {
            $items[] = self::text( 'training_default', $language );
        }

        return array_values( array_unique( $items ) );
    }

    private static function opening_phase( array $scenarios, string $language ): array {
        $items = array();

        $items[] = self::text( 'opening_observe', $language );
        self::append_recommendation( $items, $scenarios, 'exploit-opponent-weak-side', 'validation' );

        if ( self::risk( $scenarios, 'build-up-vs-high-press' ) >= 45.0 ) {
            self::append_recommendation( $items, $scenarios, 'build-up-vs-high-press', 'primary' );
        }

        if ( self::risk( $scenarios, 'opponent-side-bias' ) >= 45.0 ) {
            self::append_recommendation( $items, $scenarios, 'opponent-side-bias', 'primary' );
        }

        return array_values( array_unique( array_filter( $items ) ) );
    }

    private static function in_possession( array $scenarios ): array {
        $items = array();
        self::append_recommendation( $items, $scenarios, 'build-up-vs-high-press', 'primary' );
        self::append_recommendation( $items, $scenarios, 'attack-vs-low-block', 'primary' );
        self::append_recommendation( $items, $scenarios, 'exploit-opponent-weak-side', 'primary' );
        self::append_recommendation( $items, $scenarios, 'exploit-opponent-weak-side', 'creation_pattern' );
        return array_values( array_unique( array_filter( $items ) ) );
    }

    private static function out_of_possession( array $scenarios ): array {
        $items = array();
        self::append_recommendation( $items, $scenarios, 'opponent-side-bias', 'primary' );
        self::append_recommendation( $items, $scenarios, 'opponent-side-bias', 'pressing_orientation' );
        self::append_recommendation( $items, $scenarios, 'opponent-side-bias', 'cover_structure' );
        return array_values( array_unique( array_filter( $items ) ) );
    }

    private static function after_loss( array $scenarios ): array {
        $items = array();
        self::append_recommendation( $items, $scenarios, 'defensive-transition-after-loss', 'primary' );
        self::append_recommendation( $items, $scenarios, 'defensive-transition-after-loss', 'counterpress' );
        self::append_recommendation( $items, $scenarios, 'defensive-transition-after-loss', 'opponent_threat' );
        self::append_recommendation( $items, $scenarios, 'attack-vs-low-block', 'rest_defence' );
        return array_values( array_unique( array_filter( $items ) ) );
    }

    private static function after_recovery( array $scenarios ): array {
        $items = array();
        self::append_recommendation( $items, $scenarios, 'exploit-opponent-weak-side', 'creation_pattern' );
        self::append_recommendation( $items, $scenarios, 'exploit-opponent-weak-side', 'switch_trigger' );
        self::append_recommendation( $items, $scenarios, 'build-up-vs-high-press', 'attacking_outlet' );
        return array_values( array_unique( array_filter( $items ) ) );
    }

    private static function halftime_checks( array $scenarios, string $language ): array {
        return array(
            sprintf(
                self::text( 'halftime_press', $language ),
                self::scenario_name( $scenarios, 'build-up-vs-high-press' )
            ),
            sprintf(
                self::text( 'halftime_side', $language ),
                TactiTwin_I18n::channel_label(
                    (string) ( $scenarios['opponent-side-bias']['evidence']['opponent_dominant_channel'] ?? 'central' ),
                    $language
                )
            ),
            sprintf(
                self::text( 'halftime_transition', $language ),
                (string) ( $scenarios['defensive-transition-after-loss']['evidence']['our_dangerous_turnover_rate'] ?? 0 )
            ),
            sprintf(
                self::text( 'halftime_weak_side', $language ),
                TactiTwin_I18n::channel_label(
                    (string) ( $scenarios['exploit-opponent-weak-side']['evidence']['best_matchup_channel'] ?? 'central' ),
                    $language
                )
            ),
        );
    }

    private static function decision_triggers( array $scenarios, string $language ): array {
        return array(
            array(
                'trigger' => self::text( 'trigger_high_press', $language ),
                'action'  => (string) ( $scenarios['build-up-vs-high-press']['recommendations']['primary'] ?? '' ),
                'scenario_id' => 'build-up-vs-high-press',
            ),
            array(
                'trigger' => self::text( 'trigger_low_block', $language ),
                'action'  => (string) ( $scenarios['attack-vs-low-block']['recommendations']['primary'] ?? '' ),
                'scenario_id' => 'attack-vs-low-block',
            ),
            array(
                'trigger' => self::text( 'trigger_transition', $language ),
                'action'  => (string) ( $scenarios['defensive-transition-after-loss']['recommendations']['primary'] ?? '' ),
                'scenario_id' => 'defensive-transition-after-loss',
            ),
            array(
                'trigger' => self::text( 'trigger_weak_side', $language ),
                'action'  => (string) ( $scenarios['exploit-opponent-weak-side']['recommendations']['primary'] ?? '' ),
                'scenario_id' => 'exploit-opponent-weak-side',
            ),
        );
    }

    private static function index_scenarios( array $scenarios ): array {
        $indexed = array();
        foreach ( $scenarios as $scenario ) {
            $id = (string) ( $scenario['scenario_id'] ?? '' );
            if ( '' !== $id ) {
                $indexed[ $id ] = $scenario;
            }
        }
        return $indexed;
    }

    private static function top_by( array $scenarios, string $field ): array {
        $best = array();
        $best_score = -1.0;
        foreach ( $scenarios as $scenario ) {
            $score = self::score( $scenario, $field );
            if ( $score > $best_score ) {
                $best_score = $score;
                $best = $scenario;
            }
        }
        return $best;
    }

    private static function score( array $scenario, string $field ): float {
        return (float) ( $scenario[ $field ]['score'] ?? 0 );
    }

    private static function risk( array $scenarios, string $id ): float {
        return (float) ( $scenarios[ $id ]['risk']['score'] ?? 0 );
    }

    private static function scenario_name( array $scenarios, string $id ): string {
        return (string) ( $scenarios[ $id ]['scenario_name'] ?? $id );
    }

    private static function append_recommendation( array &$items, array $scenarios, string $scenario_id, string $key ): void {
        $value = (string) ( $scenarios[ $scenario_id ]['recommendations'][ $key ] ?? '' );
        if ( '' !== $value ) {
            $items[] = $value;
        }
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'plan_name' => 'Match Plan',
                'headline' => 'Primary concern: %s (%s/100 risk). Best current opportunity: %s (%s/100).',
                'method' => 'The Match Plan reorganizes existing scenario evidence into coach-facing phases and priorities. It does not create new probabilities or override live observation.',
                'training_default' => 'Rehearse the highest-ranked scenario and its first two corrective behaviours before match day.',
                'opening_observe' => 'Use the opening 10–15 minutes to validate the opponent press height, defensive block, preferred attack side and transition behaviour before increasing the weight of historical recommendations.',
                'halftime_press' => 'Check whether the opponent pressing behaviour matches the %s scenario assumptions; if not, reduce its priority for the second half.',
                'halftime_side' => 'Check whether the opponent is still progressing mainly through the %s channel or has changed its attacking orientation.',
                'halftime_transition' => 'Compare live turnover danger with the pre-match baseline (%.1f%% of sampled losses led to a shot within 10 seconds).',
                'halftime_weak_side' => 'Confirm whether the %s channel is still the best attacking matchup after personnel, full-back height and cover adjustments.',
                'trigger_high_press' => 'Opponent commits an aggressive first pressing line or locks short build-up.',
                'trigger_low_block' => 'Opponent settles with many defensive actions in its own third and reduces box access.',
                'trigger_transition' => 'Our losses repeatedly allow an immediate forward progression or shot threat.',
                'trigger_weak_side' => 'The opponent continues conceding access through the identified weak-side channel.',
            ),
            'es' => array(
                'plan_name' => 'Plan de partido',
                'headline' => 'Principal preocupación: %s (%s/100 de riesgo). Mejor oportunidad actual: %s (%s/100).',
                'method' => 'El Plan de Partido reorganiza la evidencia de los escenarios existentes en fases y prioridades útiles para el cuerpo técnico. No crea nuevas probabilidades ni sustituye la observación en directo.',
                'training_default' => 'Ensaya antes del partido el escenario con mayor prioridad y sus dos primeras conductas correctoras.',
                'opening_observe' => 'Utiliza los primeros 10–15 minutos para validar la altura de presión, el bloque defensivo, el lado preferido de ataque y el comportamiento en transición del rival antes de aumentar el peso de las recomendaciones históricas.',
                'halftime_press' => 'Comprueba si la presión del rival coincide con los supuestos del escenario %s; si no es así, reduce su prioridad para la segunda parte.',
                'halftime_side' => 'Comprueba si el rival sigue progresando principalmente por el canal %s o ha cambiado su orientación ofensiva.',
                'halftime_transition' => 'Compara el peligro real tras pérdida con la referencia previa (el %.1f%% de las pérdidas de la muestra acabó en tiro rival en 10 segundos).',
                'halftime_weak_side' => 'Confirma si el canal %s sigue siendo el mejor emparejamiento ofensivo después de cambios de jugadores, altura de los laterales y coberturas.',
                'trigger_high_press' => 'El rival compromete una primera línea de presión agresiva o bloquea nuestra salida corta.',
                'trigger_low_block' => 'El rival se instala con muchas acciones defensivas en su propio tercio y reduce nuestro acceso al área.',
                'trigger_transition' => 'Nuestras pérdidas permiten de forma repetida una progresión inmediata o amenaza de tiro.',
                'trigger_weak_side' => 'El rival continúa concediendo acceso por el canal identificado como lado débil.',
            ),
        );

        $language = TactiTwin_I18n::normalize_language( $language );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
