<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Live_Timeline {
    public static function summarize( array $snapshots ): array {
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

        $transitions = array();
        $segments = array();
        $plan_counts = array( 'A'=>0, 'B'=>0, 'C'=>0 );
        $scenario_counts = array();
        $previous = null;
        $segment = null;

        foreach ( $snapshots as $snapshot ) {
            $plan = strtoupper( (string) ( $snapshot['active_plan'] ?? 'A' ) );
            if ( ! isset( $plan_counts[ $plan ] ) ) {
                $plan_counts[ $plan ] = 0;
            }
            ++$plan_counts[ $plan ];

            $scenario = (string) ( $snapshot['selected_scenario'] ?? '' );
            if ( '' !== $scenario ) {
                $scenario_counts[ $scenario ] = ( $scenario_counts[ $scenario ] ?? 0 ) + 1;
            }

            $minute = (int) ( $snapshot['minute'] ?? 0 );
            $mode = (string) ( $snapshot['active_mode'] ?? '' );

            if ( null === $segment || $segment['plan'] !== $plan || $segment['mode'] !== $mode || $segment['scenario_id'] !== $scenario ) {
                if ( null !== $segment ) {
                    $segment['to_minute'] = $minute;
                    $segments[] = $segment;
                }
                $segment = array(
                    'plan' => $plan,
                    'mode' => $mode,
                    'scenario_id' => $scenario,
                    'from_minute' => $minute,
                    'to_minute' => null,
                    'snapshot_ids' => array(),
                );
            }
            $segment['snapshot_ids'][] = (int) ( $snapshot['id'] ?? 0 );

            if ( null !== $previous ) {
                $previous_plan = strtoupper( (string) ( $previous['active_plan'] ?? 'A' ) );
                $previous_mode = (string) ( $previous['active_mode'] ?? '' );
                $previous_scenario = (string) ( $previous['selected_scenario'] ?? '' );
                if ( $previous_plan !== $plan || $previous_mode !== $mode || $previous_scenario !== $scenario ) {
                    $transitions[] = array(
                        'minute' => $minute,
                        'from_plan' => $previous_plan,
                        'to_plan' => $plan,
                        'from_mode' => $previous_mode,
                        'to_mode' => $mode,
                        'from_scenario' => $previous_scenario,
                        'to_scenario' => $scenario,
                        'snapshot_id' => (int) ( $snapshot['id'] ?? 0 ),
                    );
                }
            }

            $previous = $snapshot;
        }

        if ( null !== $segment ) {
            $segment['to_minute'] = (int) ( $previous['minute'] ?? $segment['from_minute'] );
            $segments[] = $segment;
        }

        arsort( $scenario_counts );
        $first = ! empty( $snapshots ) ? $snapshots[0] : array();
        $last = ! empty( $snapshots ) ? $snapshots[ count( $snapshots ) - 1 ] : array();

        return array(
            'snapshot_count' => count( $snapshots ),
            'first_minute' => ! empty( $snapshots ) ? (int) ( $first['minute'] ?? 0 ) : null,
            'last_minute' => ! empty( $snapshots ) ? (int) ( $last['minute'] ?? 0 ) : null,
            'current_plan' => ! empty( $snapshots ) ? strtoupper( (string) ( $last['active_plan'] ?? 'A' ) ) : null,
            'current_mode' => ! empty( $snapshots ) ? (string) ( $last['active_mode'] ?? '' ) : null,
            'plan_snapshot_counts' => $plan_counts,
            'scenario_snapshot_counts' => $scenario_counts,
            'transitions' => $transitions,
            'segments' => $segments,
            'method' => array(
                'type' => 'snapshot-timeline-reconstruction',
                'note' => 'Segments represent intervals between staff submissions. They do not imply that TactiTwin continuously observed the match between snapshots.',
            ),
        );
    }
}
