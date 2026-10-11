<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Staff_Brief_Version_Diff {
    public static function snapshots( array $previous_snapshot, array $current_snapshot, string $language = 'en' ): array {
        if ( ! TactiTwin_Staff_Brief_Snapshot::verify( $previous_snapshot ) || ! TactiTwin_Staff_Brief_Snapshot::verify( $current_snapshot ) ) {
            throw new InvalidArgumentException( 'Staff Brief version comparison requires two integrity-valid approved snapshots.' );
        }
        self::assert_same_matchup( $previous_snapshot, $current_snapshot );

        return self::compare(
            TactiTwin_Staff_Brief_Snapshot::staff_brief( $previous_snapshot ),
            TactiTwin_Staff_Brief_Snapshot::staff_brief( $current_snapshot ),
            array(
                'kind' => 'approved-snapshot',
                'snapshot_uuid' => (string) ( $previous_snapshot['snapshot_uuid'] ?? '' ),
                'approved_at' => (string) ( $previous_snapshot['approved_at'] ?? '' ),
                'sha256' => (string) ( $previous_snapshot['content_sha256'] ?? '' ),
            ),
            array(
                'kind' => 'approved-snapshot',
                'snapshot_uuid' => (string) ( $current_snapshot['snapshot_uuid'] ?? '' ),
                'approved_at' => (string) ( $current_snapshot['approved_at'] ?? '' ),
                'sha256' => (string) ( $current_snapshot['content_sha256'] ?? '' ),
            ),
            $language
        );
    }

    public static function current_against_snapshot( array $current_brief, array $previous_snapshot, string $language = 'en' ): array {
        if ( 'match-day-staff-brief' !== (string) ( $current_brief['staff_brief_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'Current version comparison requires a Match-Day Staff Brief.' );
        }
        if ( ! TactiTwin_Staff_Brief_Snapshot::verify( $previous_snapshot ) ) {
            throw new InvalidArgumentException( 'Previous Staff Brief snapshot failed integrity verification.' );
        }

        $previous_brief = TactiTwin_Staff_Brief_Snapshot::staff_brief( $previous_snapshot );
        self::assert_same_brief_matchup( $previous_brief, $current_brief );
        $current = $current_brief;
        unset( $current['handoff_source'] );

        return self::compare(
            $previous_brief,
            $current,
            array(
                'kind' => 'approved-snapshot',
                'snapshot_uuid' => (string) ( $previous_snapshot['snapshot_uuid'] ?? '' ),
                'approved_at' => (string) ( $previous_snapshot['approved_at'] ?? '' ),
                'sha256' => (string) ( $previous_snapshot['content_sha256'] ?? '' ),
            ),
            array(
                'kind' => 'generated-current',
                'snapshot_uuid' => null,
                'approved_at' => null,
                'sha256' => null,
            ),
            $language
        );
    }

    private static function compare( array $previous, array $current, array $from, array $to, string $language ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        self::assert_same_brief_matchup( $previous, $current );
        $changes = array();

        self::scalar_change( $changes, 'headline-changed', 'summary', 'headline', (string) ( $previous['headline'] ?? '' ), (string) ( $current['headline'] ?? '' ), $language );
        self::coverage_changes( $changes, $previous['coverage'] ?? array(), $current['coverage'] ?? array(), $language );
        self::priority_changes( $changes, $previous['top_priorities'] ?? array(), $current['top_priorities'] ?? array(), $language );
        self::plan_changes( $changes, $previous['plans'] ?? array(), $current['plans'] ?? array(), $language );
        self::array_change( $changes, 'opening-checks-changed', 'phase_checks', 'opening_15_minutes', $previous['phase_checks']['opening_15_minutes'] ?? array(), $current['phase_checks']['opening_15_minutes'] ?? array(), $language );
        self::array_change( $changes, 'halftime-checks-changed', 'phase_checks', 'halftime_checks', $previous['phase_checks']['halftime_checks'] ?? array(), $current['phase_checks']['halftime_checks'] ?? array(), $language );
        self::array_change( $changes, 'decision-tree-changed', 'decision_tree', 'decision_tree', self::decision_tree( $previous['decision_tree'] ?? array() ), self::decision_tree( $current['decision_tree'] ?? array() ), $language );
        self::array_change( $changes, 'material-evidence-watch-changed', 'evidence', 'material_signals', self::material_signals( $previous ), self::material_signals( $current ), $language );

        $sections = array_values( array_unique( array_map( static fn( array $change ): string => (string) $change['section'], $changes ) ) );

        return array(
            'diff_id' => 'staff-brief-version-diff',
            'status' => empty( $changes ) ? 'unchanged' : 'changed',
            'language' => $language,
            'team' => $current['team'] ?? array(),
            'opponent' => $current['opponent'] ?? array(),
            'from' => $from,
            'to' => $to,
            'change_count' => count( $changes ),
            'changed_sections' => $sections,
            'changes' => $changes,
            'method' => array(
                'type' => 'deterministic-structural-staff-brief-version-diff',
                'creates_change_score' => false,
                'recalculates_tactical_models' => false,
                'changes_tactical_scores' => false,
                'changes_recommendations' => false,
                'changes_evidence_order' => false,
                'changes_plan_activation' => false,
                'note' => self::text( 'method', $language ),
            ),
        );
    }

    private static function coverage_changes( array &$changes, $before, $after, string $language ): void {
        $before = is_array( $before ) ? $before : array();
        $after = is_array( $after ) ? $after : array();
        $a = array(
            'effective_matches' => isset( $before['effective_matches'] ) ? (int) $before['effective_matches'] : null,
            'status' => (string) ( $before['status'] ?? '' ),
        );
        $b = array(
            'effective_matches' => isset( $after['effective_matches'] ) ? (int) $after['effective_matches'] : null,
            'status' => (string) ( $after['status'] ?? '' ),
        );
        self::array_change( $changes, 'coverage-changed', 'coverage', 'coverage', $a, $b, $language );
    }

    private static function priority_changes( array &$changes, $before, $after, string $language ): void {
        $before = self::priority_map( $before );
        $after = self::priority_map( $after );
        $before_order = array_keys( $before );
        $after_order = array_keys( $after );
        self::array_change( $changes, 'priority-order-changed', 'priorities', 'order', $before_order, $after_order, $language );

        $all = array_values( array_unique( array_merge( $before_order, $after_order ) ) );
        foreach ( $all as $scenario_id ) {
            $a = $before[ $scenario_id ] ?? null;
            $b = $after[ $scenario_id ] ?? null;
            if ( null === $a || null === $b ) {
                self::push( $changes, 'priority-membership-changed:' . $scenario_id, 'priorities', 'priority', $a, $b, $language, array( 'scenario_id'=>$scenario_id ) );
                continue;
            }
            $fields = array();
            foreach ( array( 'rank','priority_score','instruction','scenario_name' ) as $field ) {
                if ( ( $a[ $field ] ?? null ) !== ( $b[ $field ] ?? null ) ) {
                    $fields[] = $field;
                }
            }
            if ( ! empty( $fields ) ) {
                self::push( $changes, 'priority-changed:' . $scenario_id, 'priorities', 'priority', $a, $b, $language, array( 'scenario_id'=>$scenario_id, 'changed_fields'=>$fields ) );
            }
        }
    }

    private static function plan_changes( array &$changes, $before, $after, string $language ): void {
        $before = is_array( $before ) ? $before : array();
        $after = is_array( $after ) ? $after : array();
        foreach ( array( 'plan_a'=>'A','plan_b'=>'B','plan_c'=>'C' ) as $key => $plan_id ) {
            $a = self::plan_signature( is_array( $before[ $key ] ?? null ) ? $before[ $key ] : array() );
            $b = self::plan_signature( is_array( $after[ $key ] ?? null ) ? $after[ $key ] : array() );
            $fields = array();
            foreach ( array_keys( $a + $b ) as $field ) {
                if ( ( $a[ $field ] ?? null ) !== ( $b[ $field ] ?? null ) ) {
                    $fields[] = $field;
                }
            }
            if ( ! empty( $fields ) ) {
                self::push( $changes, 'plan-changed:' . strtolower( $plan_id ), 'plans', 'plan', $a, $b, $language, array( 'plan_id'=>$plan_id, 'changed_fields'=>$fields ) );
            }
        }
    }

    private static function plan_signature( array $plan ): array {
        $evidence = is_array( $plan['evidence'] ?? null ) ? $plan['evidence'] : array();
        $primary = is_array( $evidence['primary'] ?? null ) ? $evidence['primary'] : array();
        $alternative = is_array( $evidence['alternative'] ?? null ) ? $evidence['alternative'] : array();
        $conflicts = is_array( $evidence['conflict_signals'] ?? null ) ? $evidence['conflict_signals'] : array();
        $signal_ids = array();
        foreach ( $conflicts['signals'] ?? array() as $signal ) {
            if ( is_array( $signal ) && '' !== (string) ( $signal['id'] ?? '' ) ) {
                $signal_ids[] = (string) $signal['id'];
            }
        }
        return array(
            'status' => (string) ( $plan['status'] ?? '' ),
            'mode' => (string) ( $plan['mode'] ?? '' ),
            'scenario_id' => (string) ( $plan['scenario_id'] ?? '' ),
            'risk_score' => is_numeric( $plan['risk_score'] ?? null ) ? (float) $plan['risk_score'] : null,
            'trigger' => (string) ( $plan['trigger'] ?? '' ),
            'activation_rule' => (string) ( $plan['activation_rule'] ?? '' ),
            'instructions' => array_values( is_array( $plan['instructions'] ?? null ) ? $plan['instructions'] : array() ),
            'primary_group_key' => (string) ( $primary['group_key'] ?? '' ),
            'alternative_group_key' => (string) ( $alternative['group_key'] ?? '' ),
            'conflict_state' => (string) ( $conflicts['highest_severity'] ?? 'none' ),
            'conflict_signal_ids' => $signal_ids,
        );
    }

    private static function priority_map( $rows ): array {
        $result = array();
        if ( ! is_array( $rows ) ) {
            return $result;
        }
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $scenario_id = (string) ( $row['scenario_id'] ?? '' );
            if ( '' === $scenario_id ) {
                continue;
            }
            $result[ $scenario_id ] = array(
                'rank' => (int) ( $row['rank'] ?? 0 ),
                'scenario_id' => $scenario_id,
                'scenario_name' => (string) ( $row['scenario_name'] ?? $scenario_id ),
                'priority_score' => is_numeric( $row['priority_score'] ?? null ) ? (float) $row['priority_score'] : null,
                'instruction' => (string) ( $row['instruction'] ?? '' ),
            );
        }
        return $result;
    }

    private static function decision_tree( $rows ): array {
        $result = array();
        if ( ! is_array( $rows ) ) {
            return $result;
        }
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $result[] = array(
                'order' => (int) ( $row['order'] ?? 0 ),
                'condition' => (string) ( $row['condition'] ?? '' ),
                'target_plan' => (string) ( $row['target_plan'] ?? '' ),
                'mode' => (string) ( $row['mode'] ?? '' ),
            );
        }
        return $result;
    }

    private static function material_signals( array $brief ): array {
        $result = array();
        foreach ( $brief['evidence_watch']['material_signals'] ?? array() as $signal ) {
            if ( ! is_array( $signal ) ) {
                continue;
            }
            $result[] = array(
                'plan_id' => (string) ( $signal['plan_id'] ?? '' ),
                'id' => (string) ( $signal['id'] ?? '' ),
            );
        }
        return $result;
    }

    private static function scalar_change( array &$changes, string $id, string $section, string $field, $before, $after, string $language ): void {
        if ( $before === $after ) {
            return;
        }
        self::push( $changes, $id, $section, $field, $before, $after, $language );
    }

    private static function array_change( array &$changes, string $id, string $section, string $field, $before, $after, string $language ): void {
        $before = is_array( $before ) ? array_values( $before ) : $before;
        $after = is_array( $after ) ? array_values( $after ) : $after;
        if ( $before === $after ) {
            return;
        }
        self::push( $changes, $id, $section, $field, $before, $after, $language );
    }

    private static function push( array &$changes, string $id, string $section, string $field, $before, $after, string $language, array $context = array() ): void {
        $base_id = explode( ':', $id, 2 )[0];
        $changes[] = array(
            'id' => $id,
            'section' => $section,
            'field' => $field,
            'label' => self::text( $base_id, $language ),
            'before' => $before,
            'after' => $after,
            'context' => $context,
        );
    }

    private static function assert_same_matchup( array $a, array $b ): void {
        $a_brief = TactiTwin_Staff_Brief_Snapshot::staff_brief( $a );
        $b_brief = TactiTwin_Staff_Brief_Snapshot::staff_brief( $b );
        self::assert_same_brief_matchup( $a_brief, $b_brief );
    }

    private static function assert_same_brief_matchup( array $a, array $b ): void {
        $a_team = (string) ( $a['team']['source_id'] ?? '' );
        $a_opp = (string) ( $a['opponent']['source_id'] ?? '' );
        $b_team = (string) ( $b['team']['source_id'] ?? '' );
        $b_opp = (string) ( $b['opponent']['source_id'] ?? '' );
        if ( '' === $a_team || '' === $a_opp || $a_team !== $b_team || $a_opp !== $b_opp ) {
            throw new InvalidArgumentException( 'Staff Brief versions must describe the same team/opponent pairing.' );
        }
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'method'=>'v1.18 performs a deterministic structural comparison between Staff Brief versions. It reports changed fields only and does not create a change score, recalculate tactical models or alter either version.',
                'headline-changed'=>'Headline changed','coverage-changed'=>'Coverage changed','priority-order-changed'=>'Priority order changed','priority-membership-changed'=>'Priority membership changed','priority-changed'=>'Priority details changed','plan-changed'=>'Plan changed','opening-checks-changed'=>'Opening checks changed','halftime-checks-changed'=>'Halftime checks changed','decision-tree-changed'=>'Decision tree changed','material-evidence-watch-changed'=>'Material evidence warnings changed',
            ),
            'es' => array(
                'method'=>'v1.18 realiza una comparación estructural determinista entre versiones del Staff Brief. Solo informa campos cambiados y no crea un score de cambio, no recalcula modelos tácticos ni modifica ninguna versión.',
                'headline-changed'=>'Cambió el titular','coverage-changed'=>'Cambió la cobertura','priority-order-changed'=>'Cambió el orden de prioridades','priority-membership-changed'=>'Cambió la composición de prioridades','priority-changed'=>'Cambió el detalle de una prioridad','plan-changed'=>'Cambió el plan','opening-checks-changed'=>'Cambió el checklist inicial','halftime-checks-changed'=>'Cambió el checklist del descanso','decision-tree-changed'=>'Cambió el árbol de decisión','material-evidence-watch-changed'=>'Cambiaron las alertas materiales de evidencia',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
