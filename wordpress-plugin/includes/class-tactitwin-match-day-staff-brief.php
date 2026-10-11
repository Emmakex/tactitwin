<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Match_Day_Staff_Brief {
    public static function compose( array $match_plan, array $playbook, string $language = 'en' ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        if ( 'match-plan' !== (string) ( $match_plan['plan_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'Match-day staff brief requires a TactiTwin Match Plan.' );
        }
        if ( 'contingency-playbook' !== (string) ( $playbook['playbook_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'Match-day staff brief requires a TactiTwin Contingency Playbook.' );
        }

        $team_id = (string) ( $match_plan['team']['source_id'] ?? '' );
        $opponent_id = (string) ( $match_plan['opponent']['source_id'] ?? '' );
        if (
            $team_id !== (string) ( $playbook['team']['source_id'] ?? '' )
            || $opponent_id !== (string) ( $playbook['opponent']['source_id'] ?? '' )
        ) {
            throw new InvalidArgumentException( 'Match Plan and Contingency Playbook must describe the same matchup.' );
        }

        $plans = array();
        foreach ( array( 'plan_a'=>'A', 'plan_b'=>'B', 'plan_c'=>'C' ) as $key => $id ) {
            $source = is_array( $playbook['plans'][ $key ] ?? null ) ? $playbook['plans'][ $key ] : array();
            $plans[ $key ] = self::plan_card( $source, $id );
        }

        $material_tensions = self::material_tensions( $plans );

        return array(
            'staff_brief_id' => 'match-day-staff-brief',
            'language' => $language,
            'team' => $match_plan['team'] ?? array(),
            'opponent' => $match_plan['opponent'] ?? array(),
            'headline' => (string) ( $match_plan['summary']['headline'] ?? '' ),
            'coverage' => $match_plan['coverage'] ?? array(),
            'reference_context' => $playbook['current_context'] ?? array(),
            'active_plan' => $playbook['active_plan'] ?? array(),
            'top_priorities' => self::priorities( $match_plan['priorities'] ?? array(), 3 ),
            'plans' => $plans,
            'decision_tree' => array_slice( is_array( $playbook['decision_tree'] ?? null ) ? $playbook['decision_tree'] : array(), 0, 6 ),
            'phase_checks' => array(
                'opening_15_minutes' => self::string_items( $match_plan['phases']['opening_15_minutes'] ?? array(), 4 ),
                'halftime_checks' => self::string_items( $match_plan['phases']['halftime_checks'] ?? array(), 4 ),
            ),
            'evidence_watch' => array(
                'material_signal_count' => count( $material_tensions ),
                'material_signals' => $material_tensions,
                'note' => self::text( empty( $material_tensions ) ? 'watch_clear' : 'watch_review', $language ),
            ),
            'handoff_source' => array(
                'match_plan' => $match_plan,
                'playbook' => $playbook,
            ),
            'method' => array(
                'type' => 'presentation-only-match-day-staff-brief',
                'sources' => array( 'precedent-aware-match-plan', 'precedent-aware-contingency-playbook' ),
                'pre_match_reference_context' => true,
                'handoff_source_included' => true,
                'handoff_source_recalculated_on_approval' => false,
                'creates_new_tactical_score' => false,
                'reorders_priorities' => false,
                'changes_plan_activation' => false,
                'changes_evidence_order' => false,
                'changes_recommendations' => false,
                'note' => self::text( 'method', $language ),
            ),
        );
    }

    private static function plan_card( array $source, string $fallback_id ): array {
        $historical = is_array( $source['historical_precedents'] ?? null ) ? $source['historical_precedents'] : array();
        $brief = is_array( $historical['decision_brief'] ?? null ) ? $historical['decision_brief'] : array();
        return array(
            'id' => (string) ( $source['id'] ?? $fallback_id ),
            'name' => (string) ( $source['name'] ?? 'Plan ' . $fallback_id ),
            'status' => (string) ( $source['status'] ?? '' ),
            'mode' => (string) ( $source['mode'] ?? '' ),
            'purpose' => (string) ( $source['purpose'] ?? '' ),
            'scenario_id' => $source['scenario_id'] ?? null,
            'scenario_name' => $source['scenario_name'] ?? null,
            'risk_score' => is_numeric( $source['risk_score'] ?? null ) ? (float) $source['risk_score'] : null,
            'trigger' => (string) ( $source['trigger'] ?? '' ),
            'activation_rule' => (string) ( $source['activation_rule'] ?? '' ),
            'instructions' => self::string_items( $source['instructions'] ?? array(), 5 ),
            'exit_conditions' => self::string_items( $source['exit_conditions'] ?? array(), 4 ),
            'evidence' => array(
                'status' => (string) ( $historical['status'] ?? 'unavailable' ),
                'matched_count' => (int) ( $historical['matched_count'] ?? 0 ),
                'primary' => is_array( $brief['primary'] ?? null ) ? $brief['primary'] : null,
                'alternative' => is_array( $brief['alternative'] ?? null ) ? $brief['alternative'] : null,
                'comparison' => is_array( $brief['comparison'] ?? null ) ? $brief['comparison'] : null,
                'conflict_signals' => is_array( $brief['conflict_signals'] ?? null ) ? $brief['conflict_signals'] : array(),
            ),
        );
    }

    private static function priorities( $priorities, int $limit ): array {
        if ( ! is_array( $priorities ) ) {
            return array();
        }
        $result = array();
        foreach ( array_slice( $priorities, 0, $limit ) as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $result[] = array(
                'rank' => (int) ( $row['rank'] ?? count( $result ) + 1 ),
                'scenario_id' => (string) ( $row['scenario_id'] ?? '' ),
                'scenario_name' => (string) ( $row['scenario_name'] ?? $row['scenario_id'] ?? '' ),
                'priority_score' => is_numeric( $row['priority_score'] ?? null ) ? (float) $row['priority_score'] : null,
                'instruction' => (string) ( $row['instruction'] ?? '' ),
            );
        }
        return $result;
    }

    private static function material_tensions( array $plans ): array {
        $result = array();
        foreach ( $plans as $plan ) {
            $signals = $plan['evidence']['conflict_signals']['signals'] ?? array();
            if ( ! is_array( $signals ) ) {
                continue;
            }
            foreach ( $signals as $signal ) {
                if ( ! is_array( $signal ) || 'material' !== (string) ( $signal['severity'] ?? '' ) ) {
                    continue;
                }
                $result[] = array(
                    'plan_id' => (string) ( $plan['id'] ?? '' ),
                    'id' => (string) ( $signal['id'] ?? '' ),
                    'title' => (string) ( $signal['title'] ?? $signal['id'] ?? '' ),
                    'detail' => (string) ( $signal['detail'] ?? '' ),
                );
            }
        }
        return $result;
    }

    private static function string_items( $items, int $limit ): array {
        if ( ! is_array( $items ) ) {
            return array();
        }
        $result = array();
        foreach ( $items as $item ) {
            $value = trim( (string) $item );
            if ( '' === $value || in_array( $value, $result, true ) ) {
                continue;
            }
            $result[] = $value;
            if ( count( $result ) >= $limit ) {
                break;
            }
        }
        return $result;
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'method' => 'v1.17 keeps v1.16 presentation behavior and additionally carries the exact Match Plan and Contingency Playbook source payload required for explicit approval and immutable Live Session handoff. It still creates no tactical score and changes no recommendation or plan activation.',
                'watch_clear' => 'No material evidence tension is present in the visible Plan A/B/C briefs.',
                'watch_review' => 'One or more material evidence tensions should be reviewed by staff before relying on the historical analogy.',
            ),
            'es' => array(
                'method' => 'v1.17 mantiene la presentación de v1.16 y además conserva el payload exacto de Match Plan y Contingency Playbook necesario para aprobación explícita y handoff inmutable a Live Session. Sigue sin crear scores tácticos ni cambiar recomendaciones o activación de planes.',
                'watch_clear' => 'No hay tensiones materiales de evidencia en los briefs visibles de Plan A/B/C.',
                'watch_review' => 'Hay una o más tensiones materiales de evidencia que el staff debería revisar antes de apoyarse en la analogía histórica.',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
