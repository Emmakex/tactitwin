<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Reconstructs the append-only Tactical Twin response-decision history from
 * Live Session snapshots. It never infers decisions that were not recorded.
 */
final class TactiTwin_Live_Response_Decision_Timeline {
    public static function compose( array $snapshots, string $language = 'en' ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $rows = array();

        usort(
            $snapshots,
            static function( array $a, array $b ): int {
                $minute = (int) ( $a['minute'] ?? 0 ) <=> (int) ( $b['minute'] ?? 0 );
                if ( 0 !== $minute ) {
                    return $minute;
                }
                return (int) ( $a['id'] ?? 0 ) <=> (int) ( $b['id'] ?? 0 );
            }
        );

        foreach ( $snapshots as $snapshot ) {
            $playbook = is_array( $snapshot['playbook'] ?? null ) ? $snapshot['playbook'] : array();
            $review = is_array( $playbook['live_twin_switch_review'] ?? null ) ? $playbook['live_twin_switch_review'] : array();
            if ( 'live-twin-switch-confirmation' !== (string) ( $review['review_id'] ?? '' ) ) {
                continue;
            }

            $decision = is_array( $review['staff_decision'] ?? null ) ? $review['staff_decision'] : array();
            $resolution = is_array( $review['state_resolution'] ?? null ) ? $review['state_resolution'] : array();
            $prepared = is_array( $review['prepared_switch'] ?? null ) ? $review['prepared_switch'] : array();
            $selected = is_array( $decision['selected_response'] ?? null ) ? $decision['selected_response'] : array();
            $provenance = is_array( $review['provenance'] ?? null ) ? $review['provenance'] : array();

            $rows[] = array(
                'snapshot_id' => (int) ( $snapshot['id'] ?? 0 ),
                'minute' => (int) ( $snapshot['minute'] ?? 0 ),
                'score' => array(
                    'our_score' => (int) ( $snapshot['our_score'] ?? 0 ),
                    'opponent_score' => (int) ( $snapshot['opponent_score'] ?? 0 ),
                ),
                'manpower' => array(
                    'our_players' => (int) ( $snapshot['our_players'] ?? 11 ),
                    'opponent_players' => (int) ( $snapshot['opponent_players'] ?? 11 ),
                ),
                'branch' => array(
                    'branch_id' => $resolution['branch_id'] ?? null,
                    'branch_name' => $resolution['branch_name'] ?? null,
                    'match_type' => (string) ( $resolution['match_type'] ?? 'no-prepared-branch' ),
                    'exact_hypothesis' => ! empty( $resolution['exact_hypothesis'] ),
                ),
                'prepared_decision' => array(
                    'decision' => $prepared['decision'] ?? null,
                    'target_response_id' => $prepared['target_response']['response_id'] ?? null,
                    'target_response_name' => $prepared['target_response']['response_name'] ?? null,
                    'baseline_response_id' => $prepared['baseline_response']['response_id'] ?? null,
                    'baseline_response_name' => $prepared['baseline_response']['response_name'] ?? null,
                    'state_fit_margin' => is_numeric( $prepared['state_fit_margin'] ?? null ) ? (float) $prepared['state_fit_margin'] : null,
                    'coverage_caution' => ! empty( $prepared['coverage_caution'] ),
                ),
                'staff_decision' => array(
                    'decision' => (string) ( $decision['decision'] ?? 'defer' ),
                    'status' => (string) ( $decision['status'] ?? 'deferred' ),
                    'selected_response_id' => $selected['response_id'] ?? null,
                    'selected_response_name' => $selected['response_name'] ?? null,
                    'note' => (string) ( $decision['note'] ?? '' ),
                ),
                'active_plan' => array(
                    'plan' => strtoupper( (string) ( $snapshot['active_plan'] ?? 'A' ) ),
                    'mode' => (string) ( $snapshot['active_mode'] ?? '' ),
                ),
                'provenance' => array(
                    'staff_brief_snapshot_uuid' => (string) ( $provenance['staff_brief_snapshot_uuid'] ?? '' ),
                    'staff_brief_content_sha256' => (string) ( $provenance['staff_brief_content_sha256'] ?? '' ),
                    'frozen_twin_content_sha256' => (string) ( $provenance['frozen_twin_content_sha256'] ?? '' ),
                    'reviewed_by' => (int) ( $provenance['reviewed_by'] ?? 0 ),
                    'reviewed_at' => (string) ( $provenance['reviewed_at'] ?? $snapshot['created_at'] ?? '' ),
                ),
            );
        }

        return array(
            'timeline_id' => 'live-response-decision-timeline',
            'language' => $language,
            'decision_count' => count( $rows ),
            'summary' => self::summary( $rows ),
            'decisions' => $rows,
            'method' => array(
                'type' => 'append-only-live-response-decision-reconstruction',
                'source' => 'live-session-snapshots',
                'uses_recorded_staff_decisions_only' => true,
                'infers_missing_decisions' => false,
                'changes_active_plan' => false,
                'changes_plan_abc' => false,
                'changes_frozen_twin' => false,
                'predicts_outcome' => false,
                'note' => self::text( 'method', $language ),
            ),
        );
    }

    private static function summary( array $rows ): array {
        $counts = array(
            'confirmed-prepared-switch' => 0,
            'confirmed-prepared-baseline' => 0,
            'rejected-prepared-switch' => 0,
            'confirmed-baseline' => 0,
            'deferred' => 0,
            'no-prepared-branch' => 0,
        );
        $response_counts = array();
        $branch_counts = array();

        foreach ( $rows as $row ) {
            $status = (string) ( $row['staff_decision']['status'] ?? 'deferred' );
            $counts[ $status ] = ( $counts[ $status ] ?? 0 ) + 1;

            $response_id = (string) ( $row['staff_decision']['selected_response_id'] ?? '' );
            if ( '' !== $response_id ) {
                $response_counts[ $response_id ] = ( $response_counts[ $response_id ] ?? 0 ) + 1;
            }

            $branch_id = (string) ( $row['branch']['branch_id'] ?? '' );
            if ( '' !== $branch_id ) {
                $branch_counts[ $branch_id ] = ( $branch_counts[ $branch_id ] ?? 0 ) + 1;
            }
        }

        arsort( $response_counts );
        arsort( $branch_counts );
        $first = ! empty( $rows ) ? $rows[0] : array();
        $last = ! empty( $rows ) ? $rows[ count( $rows ) - 1 ] : array();

        return array(
            'status_counts' => $counts,
            'selected_response_counts' => $response_counts,
            'branch_review_counts' => $branch_counts,
            'first_decision_minute' => ! empty( $first ) ? (int) ( $first['minute'] ?? 0 ) : null,
            'last_decision_minute' => ! empty( $last ) ? (int) ( $last['minute'] ?? 0 ) : null,
            'latest_status' => ! empty( $last ) ? (string) ( $last['staff_decision']['status'] ?? '' ) : null,
            'latest_selected_response_id' => ! empty( $last ) ? ( $last['staff_decision']['selected_response_id'] ?? null ) : null,
        );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'method' => 'v1.34 reconstructs the Tactical Twin response-decision history from append-only Live Session snapshots. It reports only staff decisions that were actually recorded and never infers a response choice between submissions.',
            ),
            'es' => array(
                'method' => 'v1.34 reconstruye el historial de decisiones de respuesta del Tactical Twin a partir de snapshots append-only de la Live Session. Solo informa decisiones realmente registradas por el staff y nunca infiere una elección entre envíos.',
            ),
        );
        return (string) ( $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key );
    }
}
