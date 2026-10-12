<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Resolves the current live state against the frozen v1.31 Response Switch
 * Playbook and records an explicit staff decision without activating a
 * response or changing Plan A/B/C.
 */
final class TactiTwin_Live_Twin_Switch_Confirmation {
    public static function review(
        array $twin_lab,
        array $context,
        string $staff_decision = 'defer',
        string $note = '',
        string $language = 'en'
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        self::assert_twin_lab( $twin_lab );
        $context = self::normalize_context( $context );
        $resolution = self::resolve_prepared_branch( $twin_lab, $context, $language );
        $baseline = self::baseline_response( $twin_lab );
        $decision = self::normalize_decision( $staff_decision );

        if ( 'unavailable' === (string) ( $resolution['status'] ?? '' ) ) {
            return array(
                'review_id' => 'live-twin-switch-confirmation',
                'language' => $language,
                'context' => $context,
                'state_resolution' => $resolution,
                'prepared_switch' => null,
                'staff_decision' => array(
                    'decision' => 'defer',
                    'status' => 'no-prepared-branch',
                    'selected_response' => null,
                    'note' => trim( $note ),
                ),
                'method' => self::method( $language ),
            );
        }

        $prepared = is_array( $resolution['prepared_switch'] ?? null ) ? $resolution['prepared_switch'] : array();
        $prepared_target = is_array( $prepared['target_response'] ?? null ) ? $prepared['target_response'] : array();
        $is_switch = 'switch-candidate' === (string) ( $prepared['decision'] ?? '' );

        if ( 'confirm-prepared' === $decision ) {
            $selected = $prepared_target;
            $status = $is_switch ? 'confirmed-prepared-switch' : 'confirmed-prepared-baseline';
        } elseif ( 'retain-baseline' === $decision ) {
            $selected = $baseline;
            $status = $is_switch ? 'rejected-prepared-switch' : 'confirmed-baseline';
        } else {
            $selected = null;
            $status = 'deferred';
        }

        return array(
            'review_id' => 'live-twin-switch-confirmation',
            'language' => $language,
            'context' => $context,
            'state_resolution' => $resolution,
            'prepared_switch' => array(
                'switch_id' => (string) ( $prepared['switch_id'] ?? '' ),
                'branch_id' => (string) ( $prepared['branch_id'] ?? '' ),
                'branch_name' => (string) ( $prepared['branch_name'] ?? '' ),
                'decision' => (string) ( $prepared['decision'] ?? '' ),
                'decision_label' => (string) ( $prepared['decision_label'] ?? '' ),
                'baseline_response' => $baseline,
                'target_response' => $prepared_target,
                'runner_up_response' => $prepared['runner_up_response'] ?? array(),
                'state_fit_margin' => is_numeric( $prepared['state_fit_margin'] ?? null ) ? (float) $prepared['state_fit_margin'] : null,
                'validation_signals' => array_values( is_array( $prepared['validation_signals'] ?? null ) ? $prepared['validation_signals'] : array() ),
                'first_branch_recommendation' => (string) ( $prepared['first_branch_recommendation'] ?? '' ),
                'coverage_caution' => ! empty( $prepared['coverage_caution'] ),
                'coverage_caution_note' => $prepared['coverage_caution_note'] ?? null,
            ),
            'staff_decision' => array(
                'decision' => $decision,
                'status' => $status,
                'selected_response' => $selected,
                'note' => trim( $note ),
            ),
            'method' => self::method( $language ),
        );
    }

    public static function resolve_prepared_branch( array $twin_lab, array $context, string $language = 'en' ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        self::assert_twin_lab( $twin_lab );
        $context = self::normalize_context( $context );
        $branch_id = self::branch_id_for_context( $context );
        if ( '' === $branch_id ) {
            return array(
                'status' => 'unavailable',
                'branch_id' => null,
                'branch_name' => null,
                'match_type' => 'no-prepared-branch',
                'exact_hypothesis' => false,
                'note' => self::text( 'no_branch', $language ),
            );
        }

        $item = self::switch_item( $twin_lab, $branch_id );
        if ( empty( $item ) ) {
            throw new RuntimeException( sprintf( 'Frozen Tactical Twin has no prepared switch item for branch: %s', $branch_id ) );
        }
        $trigger = is_array( $item['trigger'] ?? null ) ? $item['trigger'] : array();
        $exact = self::exact_hypothesis( $context, $trigger );
        $match_type = in_array( $branch_id, array( 'numerical-superiority', 'numerical-inferiority' ), true )
            ? 'structural-manpower-analogy'
            : ( $exact ? 'exact-prepared-hypothesis' : 'windowed-prepared-state' );

        return array(
            'status' => 'prepared',
            'branch_id' => $branch_id,
            'branch_name' => (string) ( $item['branch_name'] ?? $branch_id ),
            'match_type' => $match_type,
            'exact_hypothesis' => $exact,
            'note' => self::text( 'prepared_branch', $language ),
            'prepared_switch' => $item,
        );
    }

    private static function branch_id_for_context( array $context ): string {
        $minute = (int) $context['minute'];
        $score_delta = (int) $context['our_score'] - (int) $context['opponent_score'];
        $player_delta = (int) $context['our_players'] - (int) $context['opponent_players'];

        if ( $player_delta > 0 ) {
            return 'numerical-superiority';
        }
        if ( $player_delta < 0 ) {
            return 'numerical-inferiority';
        }
        if ( 0 === $score_delta ) {
            if ( $minute <= 30 ) {
                return 'opening-neutral';
            }
            if ( $minute >= 75 ) {
                return 'late-draw';
            }
            if ( $minute >= 46 ) {
                return 'second-half-neutral';
            }
            return '';
        }
        if ( 1 === $score_delta && $minute >= 60 ) {
            return 'protect-lead';
        }
        if ( -1 === $score_delta && $minute >= 60 ) {
            return 'chase-game';
        }
        return '';
    }

    private static function switch_item( array $twin_lab, string $branch_id ): array {
        foreach ( $twin_lab['response_switch_playbook']['items'] ?? array() as $item ) {
            if ( is_array( $item ) && $branch_id === (string) ( $item['branch_id'] ?? '' ) ) {
                return $item;
            }
        }
        return array();
    }

    private static function baseline_response( array $twin_lab ): array {
        $baseline = $twin_lab['response_switch_playbook']['baseline_response'] ?? array();
        return is_array( $baseline ) ? $baseline : array();
    }

    private static function exact_hypothesis( array $context, array $trigger ): bool {
        foreach ( array( 'minute', 'our_score', 'opponent_score', 'our_players', 'opponent_players' ) as $field ) {
            if ( (int) ( $context[ $field ] ?? -1 ) !== (int) ( $trigger[ $field ] ?? -2 ) ) {
                return false;
            }
        }
        return true;
    }

    private static function normalize_context( array $context ): array {
        return array(
            'minute' => max( 1, min( 120, (int) ( $context['minute'] ?? 1 ) ) ),
            'our_score' => max( 0, min( 20, (int) ( $context['our_score'] ?? 0 ) ) ),
            'opponent_score' => max( 0, min( 20, (int) ( $context['opponent_score'] ?? 0 ) ) ),
            'our_players' => max( 7, min( 11, (int) ( $context['our_players'] ?? 11 ) ) ),
            'opponent_players' => max( 7, min( 11, (int) ( $context['opponent_players'] ?? 11 ) ) ),
        );
    }

    private static function normalize_decision( string $decision ): string {
        $decision = strtolower( trim( $decision ) );
        return in_array( $decision, array( 'confirm-prepared', 'retain-baseline', 'defer' ), true ) ? $decision : 'defer';
    }

    private static function assert_twin_lab( array $twin_lab ): void {
        if ( 'tactical-twin-lab' !== (string) ( $twin_lab['lab_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'Live Twin review requires a frozen Tactical Twin Lab result.' );
        }
        if ( 'response-switch-playbook' !== (string) ( $twin_lab['response_switch_playbook']['playbook_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'Live Twin review requires the frozen Response Switch Playbook.' );
        }
    }

    private static function method( string $language ): array {
        return array(
            'type' => 'manual-live-frozen-twin-switch-confirmation',
            'source' => 'approved-frozen-tactical-twin',
            'state_resolution_is_deterministic' => true,
            'unprepared_states_are_not_forced' => true,
            'automatic_activation' => false,
            'changes_active_plan' => false,
            'changes_plan_abc' => false,
            'changes_frozen_twin' => false,
            'predicts_outcome' => false,
            'note' => self::text( 'method', $language ),
        );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'no_branch' => 'The current live state does not match a prepared Twin branch. No response switch is inferred.',
                'prepared_branch' => 'The current state maps to one explicit prepared branch. Staff confirmation is still required before treating its response as the live choice.',
                'method' => 'v1.33 resolves the real live state against the approved frozen Twin and records a staff confirmation, rejection or deferral. It never recalculates the Twin, changes Plan A/B/C or activates a response automatically.',
            ),
            'es' => array(
                'no_branch' => 'El estado actual del partido no coincide con una rama preparada del Twin. No se infiere ningún cambio de respuesta.',
                'prepared_branch' => 'El estado actual se asigna a una rama preparada explícita. Sigue siendo necesaria la confirmación del staff antes de tratar su respuesta como elección en vivo.',
                'method' => 'v1.33 resuelve el estado real del partido contra el Twin congelado y aprobado y registra una confirmación, rechazo o aplazamiento del staff. Nunca recalcula el Twin, cambia Plan A/B/C ni activa una respuesta automáticamente.',
            ),
        );
        return (string) ( $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key );
    }
}
