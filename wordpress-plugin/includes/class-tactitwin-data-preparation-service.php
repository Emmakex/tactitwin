<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Data_Preparation_Service {
    public function ensure_team_sample( int $team_id, int $target = 5 ): array {
        global $wpdb;

        $target      = max( 1, min( 10, $target ) );
        $match_table = TactiTwin_DB::table( 'matches' );
        $event_table = TactiTwin_DB::table( 'events' );
        $source      = TactiTwin_StatsBomb_Normalizer::SOURCE;

        $matches = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.source_match_id, m.match_date,
                        EXISTS(
                            SELECT 1 FROM {$event_table} e
                            WHERE e.source = m.source AND e.source_match_id = m.source_match_id
                            LIMIT 1
                        ) AS has_events
                 FROM {$match_table} m
                 WHERE m.source = %s
                   AND (m.home_team_source_id = %s OR m.away_team_source_id = %s)
                 ORDER BY m.match_date DESC, m.id DESC
                 LIMIT 30", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $source,
                (string) $team_id,
                (string) $team_id
            ),
            ARRAY_A
        );

        if ( empty( $matches ) ) {
            throw new RuntimeException( 'No match catalogue is available for this team. Import its competition/season first.' );
        }

        $ready    = 0;
        $imported = array();
        $existing = array();
        $failed   = array();
        $importer = new TactiTwin_StatsBomb_Importer();

        foreach ( $matches as $match ) {
            if ( $ready >= $target ) {
                break;
            }

            $match_id = (int) $match['source_match_id'];
            if ( ! empty( $match['has_events'] ) ) {
                ++$ready;
                $existing[] = $match_id;
                continue;
            }

            try {
                $result = $importer->import_match_events( $match_id );
                ++$ready;
                $imported[] = array(
                    'match_id' => $match_id,
                    'events'   => (int) ( $result['events'] ?? 0 ),
                );
            } catch ( Throwable $e ) {
                $failed[] = array(
                    'match_id' => $match_id,
                    'message'  => $e->getMessage(),
                );
            }
        }

        return array(
            'team_id'        => $team_id,
            'target_matches' => $target,
            'ready_matches'  => $ready,
            'existing'       => $existing,
            'imported'       => $imported,
            'failed'         => $failed,
            'coverage'       => $ready >= $target ? 'target-met' : 'partial',
        );
    }

    public function prepare_matchup( int $team_id, int $opponent_id, int $target = 5 ): array {
        if ( $team_id === $opponent_id ) {
            throw new InvalidArgumentException( 'Team and opponent must be different.' );
        }

        return array(
            'team'     => $this->ensure_team_sample( $team_id, $target ),
            'opponent' => $this->ensure_team_sample( $opponent_id, $target ),
        );
    }
}
