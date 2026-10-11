<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Unit_Service {
    public function pair_profile( int $team_id, int $player_a_id, int $player_b_id, int $limit = 5 ): array {
        if ( $player_a_id === $player_b_id ) {
            throw new InvalidArgumentException( 'Pair profile requires two different players.' );
        }

        $profiles = $this->team_pair_profiles( $team_id, $limit );
        $key      = TactiTwin_Unit_Metrics::pair_key( (string) $player_a_id, (string) $player_b_id );
        if ( ! isset( $profiles[ $key ] ) ) {
            throw new RuntimeException( 'No recent shared-pitch sample is available for this pair.' );
        }
        return $profiles[ $key ];
    }

    public function lineup_network( int $team_id, array $player_ids, int $limit = 5 ): array {
        $profiles = $this->team_pair_profiles( $team_id, $limit );
        return self::network_from_profiles( $team_id, $player_ids, $profiles );
    }

    public function compare_lineup_networks( int $team_id, array $baseline_ids, array $candidate_ids, int $limit = 5 ): array {
        $baseline_ids  = self::normalize_ids( $baseline_ids );
        $candidate_ids = self::normalize_ids( $candidate_ids );
        if ( count( $baseline_ids ) !== count( $candidate_ids ) ) {
            throw new InvalidArgumentException( 'Baseline and candidate networks must contain the same number of players.' );
        }
        if ( count( $baseline_ids ) < 2 || count( $baseline_ids ) > 11 ) {
            throw new InvalidArgumentException( 'Network comparison requires between 2 and 11 players per variant.' );
        }

        $profiles  = $this->team_pair_profiles( $team_id, $limit );
        $baseline  = self::network_from_profiles( $team_id, $baseline_ids, $profiles );
        $candidate = self::network_from_profiles( $team_id, $candidate_ids, $profiles );
        $base_set  = array_fill_keys( array_map( 'strval', $baseline_ids ), true );
        $cand_set  = array_fill_keys( array_map( 'strval', $candidate_ids ), true );
        $outgoing  = array_keys( array_diff_key( $base_set, $cand_set ) );
        $incoming  = array_keys( array_diff_key( $cand_set, $base_set ) );

        $base_pair_keys = array_fill_keys( self::possible_pair_keys( array_keys( $base_set ) ), true );
        $cand_pair_keys = array_fill_keys( self::possible_pair_keys( array_keys( $cand_set ) ), true );
        $lost_keys       = array_keys( array_diff_key( $base_pair_keys, $cand_pair_keys ) );
        $gained_keys     = array_keys( array_diff_key( $cand_pair_keys, $base_pair_keys ) );

        $gained_evidence = self::pair_evidence_refs( $gained_keys, $profiles );
        $lost_evidence   = self::pair_evidence_refs( $lost_keys, $profiles );
        $low_new_pairs   = array_values(
            array_filter(
                $gained_evidence,
                static fn( array $row ): bool => (float) ( $row['shared_minutes'] ?? 0.0 ) < 90.0
            )
        );

        return array(
            'baseline' => $baseline,
            'candidate' => $candidate,
            'changes' => array(
                'outgoing_player_ids' => $outgoing,
                'incoming_player_ids' => $incoming,
                'lost_pair_evidence'  => $lost_evidence,
                'gained_pair_evidence'=> $gained_evidence,
                'low_evidence_new_pairs' => $low_new_pairs,
            ),
            'delta' => self::signal_delta( $baseline['signals'], $candidate['signals'] ),
            'method' => array(
                'type' => 'historical-shared-pitch-event-interaction-comparison',
                'note' => 'Pair signals describe observed event interactions while players shared the pitch. They do not estimate causal chemistry or guarantee the same interaction under a new role or formation.',
            ),
        );
    }

    public function team_pair_profiles( int $team_id, int $limit = 5 ): array {
        global $wpdb;

        $limit       = max( 1, min( 10, $limit ) );
        $match_table = TactiTwin_DB::table( 'matches' );
        $event_table = TactiTwin_DB::table( 'events' );
        $source      = TactiTwin_StatsBomb_Normalizer::SOURCE;

        $matches = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.source_match_id, m.match_date
                 FROM {$match_table} m
                 WHERE m.source = %s
                   AND (m.home_team_source_id = %s OR m.away_team_source_id = %s)
                   AND EXISTS (
                       SELECT 1 FROM {$event_table} e
                       WHERE e.source = m.source AND e.source_match_id = m.source_match_id
                       LIMIT 1
                   )
                 ORDER BY m.match_date DESC, m.id DESC
                 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $source,
                (string) $team_id,
                (string) $team_id,
                $limit
            ),
            ARRAY_A
        ) ?: array();

        if ( empty( $matches ) ) {
            throw new RuntimeException( 'No imported event-data matches are available for this team.' );
        }

        $samples_by_pair = array();
        foreach ( $matches as $match ) {
            $events = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT event_index, period, minute, second, possession, possession_team_source_id,
                            team_source_id, player_source_id, position_name, event_type_name, outcome_name, xg,
                            location_x, location_y, end_location_x, end_location_y, raw_json
                     FROM {$event_table}
                     WHERE source = %s AND source_match_id = %s
                     ORDER BY event_index ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $source,
                    (string) $match['source_match_id']
                ),
                ARRAY_A
            ) ?: array();

            if ( empty( $events ) ) {
                continue;
            }

            $match_units = TactiTwin_Unit_Metrics::calculate( $events, (string) $team_id );
            foreach ( $match_units['pairs'] ?? array() as $key => $metrics ) {
                if ( (float) ( $metrics['shared_seconds'] ?? 0.0 ) <= 0.0 ) {
                    continue;
                }
                $samples_by_pair[ $key ][] = array(
                    'match_id'   => (string) $match['source_match_id'],
                    'match_date' => (string) $match['match_date'],
                    'metrics'    => $metrics,
                );
            }
        }

        $name_map = array();
        foreach ( ( new TactiTwin_Personnel_Service() )->team_players( $team_id ) as $player ) {
            $name_map[ (string) $player['source_player_id'] ] = (string) $player['player_name'];
        }

        $profiles = array();
        foreach ( $samples_by_pair as $key => $samples ) {
            $first = $samples[0]['metrics'] ?? array();
            $a     = (string) ( $first['player_a_id'] ?? '' );
            $b     = (string) ( $first['player_b_id'] ?? '' );
            $profiles[ $key ] = TactiTwin_Unit_Profile::aggregate(
                $samples,
                array(
                    'pair_key'      => $key,
                    'player_a_id'   => $a,
                    'player_a_name' => $name_map[ $a ] ?? $a,
                    'player_b_id'   => $b,
                    'player_b_name' => $name_map[ $b ] ?? $b,
                )
            );
        }

        ksort( $profiles );
        return $profiles;
    }

    private static function network_from_profiles( int $team_id, array $player_ids, array $profiles ): array {
        $player_ids = self::normalize_ids( $player_ids );
        if ( count( $player_ids ) < 2 || count( $player_ids ) > 11 ) {
            throw new InvalidArgumentException( 'Interaction network requires between 2 and 11 unique players.' );
        }

        $selected = array_fill_keys( array_map( 'strval', $player_ids ), true );
        $pairs    = array();
        foreach ( $profiles as $key => $profile ) {
            $a = (string) ( $profile['player_a_id'] ?? '' );
            $b = (string) ( $profile['player_b_id'] ?? '' );
            if ( isset( $selected[ $a ], $selected[ $b ] ) ) {
                $pairs[ $key ] = $profile;
            }
        }

        $possible_keys  = self::possible_pair_keys( array_keys( $selected ) );
        $under_observed = array();
        foreach ( $possible_keys as $key ) {
            $minutes = (float) ( $pairs[ $key ]['coverage']['shared_minutes'] ?? 0.0 );
            if ( $minutes < 90.0 ) {
                $under_observed[] = array(
                    'pair_key'       => $key,
                    'shared_minutes' => round( $minutes, 1 ),
                );
            }
        }

        return array(
            'team_source_id' => (string) $team_id,
            'player_ids'     => array_keys( $selected ),
            'coverage'       => self::network_coverage( count( $possible_keys ), $pairs, $under_observed ),
            'signals'        => self::weighted_signals( $pairs ),
            'leaders'        => self::leaders( $pairs ),
            'under_observed_pairs' => $under_observed,
            'pairs'          => array_values( $pairs ),
        );
    }

    private static function network_coverage( int $possible_pairs, array $pairs, array $under_observed ): array {
        $observed = count( $pairs );
        $high = 0;
        $medium = 0;
        $low = 0;
        foreach ( $pairs as $pair ) {
            $confidence = (string) ( $pair['coverage']['confidence'] ?? 'low' );
            if ( 'high' === $confidence ) {
                ++$high;
            } elseif ( 'medium' === $confidence ) {
                ++$medium;
            } else {
                ++$low;
            }
        }

        return array(
            'possible_pairs'          => $possible_pairs,
            'observed_pairs'          => $observed,
            'observed_pct'            => $possible_pairs > 0 ? round( ( $observed / $possible_pairs ) * 100.0, 1 ) : 0.0,
            'high_confidence_pairs'   => $high,
            'medium_confidence_pairs' => $medium,
            'low_confidence_pairs'    => $low,
            'under_observed_pairs'    => count( $under_observed ),
        );
    }

    private static function weighted_signals( array $pairs ): array {
        $sum = array( 'connectivity'=>0.0, 'progressive_link'=>0.0, 'chance_chain'=>0.0, 'defensive_sequence'=>0.0 );
        $weight = 0.0;
        foreach ( $pairs as $pair ) {
            $minutes = (float) ( $pair['coverage']['shared_minutes'] ?? 0.0 );
            if ( $minutes <= 0.0 ) {
                continue;
            }
            $w = min( 360.0, $minutes );
            $weight += $w;
            foreach ( array_keys( $sum ) as $key ) {
                $sum[ $key ] += (float) ( $pair['signals'][ $key ] ?? 0.0 ) * $w;
            }
        }

        if ( $weight <= 0.0 ) {
            return array_fill_keys( array_keys( $sum ), 0.0 );
        }
        foreach ( $sum as $key => $value ) {
            $sum[ $key ] = round( $value / $weight, 'chance_chain' === $key ? 3 : 2 );
        }
        return $sum;
    }

    private static function leaders( array $pairs ): array {
        $leaders = array();
        $mapping = array(
            'connectivity'       => 'connectivity',
            'progression'        => 'progressive_link',
            'chance_chain'       => 'chance_chain',
            'defensive_sequence' => 'defensive_sequence',
        );
        foreach ( $mapping as $label => $signal ) {
            $best = null;
            $best_value = -INF;
            foreach ( $pairs as $pair ) {
                $value   = (float) ( $pair['signals'][ $signal ] ?? 0.0 );
                $minutes = (float) ( $pair['coverage']['shared_minutes'] ?? 0.0 );
                if ( $minutes < 90.0 || $value <= $best_value ) {
                    continue;
                }
                $best_value = $value;
                $best = self::pair_ref( $pair, $signal );
            }
            $leaders[ $label ] = $best;
        }
        return $leaders;
    }

    private static function pair_ref( array $pair, string $signal ): array {
        return array(
            'pair_key'       => (string) ( $pair['pair_key'] ?? '' ),
            'player_a_id'    => (string) ( $pair['player_a_id'] ?? '' ),
            'player_a_name'  => (string) ( $pair['player_a_name'] ?? '' ),
            'player_b_id'    => (string) ( $pair['player_b_id'] ?? '' ),
            'player_b_name'  => (string) ( $pair['player_b_name'] ?? '' ),
            'shared_minutes' => (float) ( $pair['coverage']['shared_minutes'] ?? 0.0 ),
            'confidence'     => (string) ( $pair['coverage']['confidence'] ?? 'low' ),
            'signal'         => $signal,
            'value'          => (float) ( $pair['signals'][ $signal ] ?? 0.0 ),
        );
    }

    private static function pair_evidence_refs( array $keys, array $profiles ): array {
        $rows = array();
        foreach ( $keys as $key ) {
            $profile = $profiles[ $key ] ?? array();
            $parts   = explode( ':', $key, 2 );
            $rows[] = array(
                'pair_key'       => $key,
                'player_a_id'    => (string) ( $profile['player_a_id'] ?? ( $parts[0] ?? '' ) ),
                'player_a_name'  => (string) ( $profile['player_a_name'] ?? ( $parts[0] ?? '' ) ),
                'player_b_id'    => (string) ( $profile['player_b_id'] ?? ( $parts[1] ?? '' ) ),
                'player_b_name'  => (string) ( $profile['player_b_name'] ?? ( $parts[1] ?? '' ) ),
                'shared_minutes' => (float) ( $profile['coverage']['shared_minutes'] ?? 0.0 ),
                'confidence'     => (string) ( $profile['coverage']['confidence'] ?? 'low' ),
                'signals'        => $profile['signals'] ?? array(),
            );
        }
        return $rows;
    }

    private static function signal_delta( array $baseline, array $candidate ): array {
        $result = array();
        foreach ( array_unique( array_merge( array_keys( $baseline ), array_keys( $candidate ) ) ) as $key ) {
            $precision = 'chance_chain' === $key ? 3 : 2;
            $result[ $key ] = round( (float) ( $candidate[ $key ] ?? 0.0 ) - (float) ( $baseline[ $key ] ?? 0.0 ), $precision );
        }
        return $result;
    }

    private static function possible_pair_keys( array $ids ): array {
        $ids = array_values( array_unique( array_map( 'strval', $ids ) ) );
        $keys = array();
        $count = count( $ids );
        for ( $i = 0; $i < $count; ++$i ) {
            for ( $j = $i + 1; $j < $count; ++$j ) {
                $keys[] = TactiTwin_Unit_Metrics::pair_key( $ids[ $i ], $ids[ $j ] );
            }
        }
        sort( $keys );
        return $keys;
    }

    private static function normalize_ids( array $ids ): array {
        return array_values(
            array_unique(
                array_filter(
                    array_map( 'absint', $ids ),
                    static fn( $id ): bool => (int) $id > 0
                )
            )
        );
    }
}
