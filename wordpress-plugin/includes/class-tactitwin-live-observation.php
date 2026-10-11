<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Live_Observation {
    public static function apply(
        array $playbook,
        array $matchup_scan,
        array $observations,
        string $language = 'en'
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        if ( 'contingency-playbook' !== (string) ( $playbook['playbook_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'A TactiTwin Contingency Playbook is required.' );
        }
        if ( 'full-matchup-scan' !== (string) ( $matchup_scan['scenario_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'A Full Matchup Scan is required.' );
        }

        $normalized = self::normalize_observations( $observations );
        $scenarios  = self::scenario_map( $matchup_scan['subscenarios'] ?? array() );
        $candidates = self::observed_candidates( $normalized, $scenarios );
        $selected   = self::select_candidate( $candidates );
        $base_active = $playbook['active_plan'] ?? array( 'id'=>'A', 'mode'=>'baseline' );

        $activation = self::activation( $base_active, $selected, $language );
        $result = $playbook;
        $result['live_observation'] = array(
            'observations' => $normalized,
            'observed_count' => count( array_filter( $normalized, static fn( $value, $key ): bool => 'note' !== $key && true === $value, ARRAY_FILTER_USE_BOTH ) ),
            'candidate_scenarios' => $candidates,
            'selected_scenario' => $selected,
            'activation' => $activation,
        );
        $result['active_plan'] = $activation['active_plan'];
        $result['recommendations'] = array_values(
            array_unique(
                array_filter(
                    array_merge(
                        $playbook['recommendations'] ?? array(),
                        self::recommendations( $activation, $selected, $language )
                    )
                )
            )
        );
        $result['method']['type'] = 'explicit-live-observation-contingency-playbook';
        $result['method']['live_observation'] = array(
            'source' => 'manual-staff-input',
            'rule' => 'Plan C keeps precedence. Otherwise a checked live observation activates the highest-risk matching Plan B scenario.',
            'note' => self::text( 'method', $language ),
        );
        $result['method']['limitations'][] = self::text( 'limit_manual', $language );

        return $result;
    }

    public static function supported_observations(): array {
        return array(
            'high_press' => 'build-up-vs-high-press',
            'low_block' => 'attack-vs-low-block',
            'transition_threat' => 'defensive-transition-after-loss',
            'side_bias' => 'opponent-side-bias',
            'weak_side_open' => 'exploit-opponent-weak-side',
        );
    }

    private static function normalize_observations( array $observations ): array {
        $result = array();
        foreach ( self::supported_observations() as $key => $_scenario_id ) {
            $result[ $key ] = self::bool_value( $observations[ $key ] ?? false );
        }
        $result['note'] = trim( (string) ( $observations['note'] ?? '' ) );
        return $result;
    }

    private static function observed_candidates( array $observations, array $scenarios ): array {
        $rows = array();
        foreach ( self::supported_observations() as $key => $scenario_id ) {
            if ( empty( $observations[ $key ] ) || ! isset( $scenarios[ $scenario_id ] ) ) {
                continue;
            }
            $scenario = $scenarios[ $scenario_id ];
            $rows[] = array(
                'observation' => $key,
                'scenario_id' => $scenario_id,
                'scenario_name' => (string) ( $scenario['scenario_name'] ?? $scenario_id ),
                'risk_score' => round( (float) ( $scenario['risk']['score'] ?? 0.0 ), 1 ),
                'opportunity_score' => round( (float) ( $scenario['opportunity']['score'] ?? 0.0 ), 1 ),
                'mode' => self::scenario_mode( $scenario_id ),
                'instructions' => array_values( array_filter( array_map( 'strval', $scenario['recommendations'] ?? array() ) ) ),
            );
        }
        usort(
            $rows,
            static function( array $a, array $b ): int {
                $risk = (float) $b['risk_score'] <=> (float) $a['risk_score'];
                if ( 0 !== $risk ) {
                    return $risk;
                }
                return (float) $b['opportunity_score'] <=> (float) $a['opportunity_score'];
            }
        );
        return $rows;
    }

    private static function select_candidate( array $candidates ): ?array {
        return ! empty( $candidates ) ? $candidates[0] : null;
    }

    private static function activation( array $base_active, ?array $selected, string $language ): array {
        if ( 'C' === (string) ( $base_active['id'] ?? '' ) ) {
            return array(
                'status' => null === $selected ? 'no_live_plan_b' : 'plan_b_suppressed_by_plan_c',
                'active_plan' => $base_active,
                'selected_plan_b' => $selected,
                'reason' => null === $selected
                    ? self::text( 'no_observation', $language )
                    : self::text( 'c_precedence', $language ),
            );
        }

        if ( null === $selected ) {
            return array(
                'status' => 'no_live_plan_b',
                'active_plan' => $base_active,
                'selected_plan_b' => null,
                'reason' => self::text( 'no_observation', $language ),
            );
        }

        return array(
            'status' => 'plan_b_activated',
            'active_plan' => array(
                'id' => 'B',
                'mode' => (string) $selected['mode'],
                'label' => self::text( 'plan_b', $language ),
                'reason' => sprintf(
                    self::text( 'b_activated_reason', $language ),
                    self::observation_label( (string) $selected['observation'], $language ),
                    (string) $selected['scenario_name']
                ),
                'confidence' => (string) ( $base_active['confidence'] ?? 'low' ),
                'scenario_id' => (string) $selected['scenario_id'],
                'scenario_name' => (string) $selected['scenario_name'],
            ),
            'selected_plan_b' => $selected,
            'reason' => self::text( 'b_activated', $language ),
        );
    }

    private static function recommendations( array $activation, ?array $selected, string $language ): array {
        $items = array();
        $status = (string) ( $activation['status'] ?? '' );
        if ( 'plan_b_activated' === $status && null !== $selected ) {
            $items[] = sprintf(
                self::text( 'recommend_activate', $language ),
                (string) $selected['scenario_name']
            );
            foreach ( array_slice( $selected['instructions'] ?? array(), 0, 3 ) as $instruction ) {
                $items[] = (string) $instruction;
            }
        } elseif ( 'plan_b_suppressed_by_plan_c' === $status && null !== $selected ) {
            $items[] = sprintf(
                self::text( 'recommend_suppressed', $language ),
                (string) $selected['scenario_name']
            );
        } else {
            $items[] = self::text( 'recommend_no_change', $language );
        }
        return $items;
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

    private static function scenario_mode( string $scenario_id ): string {
        $map = array(
            'build-up-vs-high-press' => 'escape_high_press',
            'attack-vs-low-block' => 'break_low_block',
            'defensive-transition-after-loss' => 'secure_transition',
            'opponent-side-bias' => 'protect_dominant_side',
            'exploit-opponent-weak-side' => 'attack_weak_side',
        );
        return $map[ $scenario_id ] ?? 'adaptation';
    }

    private static function bool_value( $value ): bool {
        if ( is_bool( $value ) ) {
            return $value;
        }
        if ( is_int( $value ) || is_float( $value ) ) {
            return 1 === (int) $value;
        }
        return in_array( strtolower( trim( (string) $value ) ), array( '1', 'true', 'yes', 'on', 'si', 'sí' ), true );
    }

    private static function observation_label( string $key, string $language ): string {
        return self::text( 'observation_' . $key, $language );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'plan_b' => 'Plan B — Opponent adaptation',
                'no_observation' => 'No explicit live opponent-behaviour observation requires Plan B, so the existing automatic plan remains active.',
                'c_precedence' => 'A live opponent behaviour was observed, but Plan C keeps precedence because the current score/manpower contingency is already active.',
                'b_activated' => 'A staff-confirmed live observation activates Plan B.',
                'b_activated_reason' => 'Staff observed %s, activating the %s adaptation.',
                'recommend_activate' => 'Activate Plan B for %s and use the prepared scenario instructions.',
                'recommend_suppressed' => 'The live %s adaptation is relevant, but keep Plan C as the active plan until the score/manpower contingency clears.',
                'recommend_no_change' => 'No live Plan B trigger is checked; keep the current automatic Plan A/C selection.',
                'method' => 'Live observations are explicit human inputs. They may activate a matching Plan B branch only when Plan C is not already active.',
                'limit_manual' => 'Manual observations can be incomplete, late or subjective; they are not equivalent to automated tracking-derived detection.',
                'observation_high_press' => 'an aggressive high press',
                'observation_low_block' => 'a persistent low/compact block',
                'observation_transition_threat' => 'repeated immediate transition threat after our losses',
                'observation_side_bias' => 'a persistent opponent side bias',
                'observation_weak_side_open' => 'continued access to the identified weak side',
            ),
            'es' => array(
                'plan_b' => 'Plan B — Adaptación al rival',
                'no_observation' => 'Ninguna observación explícita del comportamiento rival exige Plan B, por lo que se mantiene el plan automático existente.',
                'c_precedence' => 'Se ha observado un comportamiento rival relevante, pero Plan C mantiene la prioridad porque ya existe una contingencia activa de marcador/superioridad.',
                'b_activated' => 'Una observación en vivo confirmada por el cuerpo técnico activa el Plan B.',
                'b_activated_reason' => 'El cuerpo técnico observa %s, activando la adaptación %s.',
                'recommend_activate' => 'Activar Plan B para %s y aplicar las instrucciones preparadas del escenario.',
                'recommend_suppressed' => 'La adaptación en vivo %s es relevante, pero mantener Plan C activo hasta que desaparezca la contingencia de marcador/superioridad.',
                'recommend_no_change' => 'No hay ningún disparador en vivo de Plan B marcado; mantener la selección automática actual Plan A/C.',
                'method' => 'Las observaciones en vivo son entradas humanas explícitas. Solo pueden activar una rama Plan B cuando Plan C no está ya activo.',
                'limit_manual' => 'Las observaciones manuales pueden ser incompletas, tardías o subjetivas; no equivalen a una detección automatizada derivada de tracking.',
                'observation_high_press' => 'una presión alta agresiva',
                'observation_low_block' => 'un bloque bajo/compacto persistente',
                'observation_transition_threat' => 'amenaza inmediata repetida en transición tras nuestras pérdidas',
                'observation_side_bias' => 'un sesgo lateral persistente del rival',
                'observation_weak_side_open' => 'acceso continuado al lado débil identificado',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
