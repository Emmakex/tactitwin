<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_StatsBomb_Importer {
    private TactiTwin_StatsBomb_Client $client;

    public function __construct( ?TactiTwin_StatsBomb_Client $client = null ) {
        $this->client = $client ?: new TactiTwin_StatsBomb_Client();
    }

    public function import_competition_season( int $competition_id, int $season_id ): array {
        global $wpdb;

        $competition = null;
        foreach ( $this->client->competitions() as $candidate ) {
            if (
                (int) ( $candidate['competition_id'] ?? 0 ) === $competition_id &&
                (int) ( $candidate['season_id'] ?? 0 ) === $season_id
            ) {
                $competition = $candidate;
                break;
            }
        }

        if ( null === $competition ) {
            throw new RuntimeException( 'Competition/season not found in StatsBomb Open Data.' );
        }

        $matches = $this->client->matches( $competition_id, $season_id );
        $wpdb->query( 'START TRANSACTION' );

        try {
            $this->replace( 'competitions', TactiTwin_StatsBomb_Normalizer::competition( $competition ) );

            $team_ids = array();
            foreach ( $matches as $match ) {
                if ( ! empty( $match['home_team'] ) ) {
                    $team = TactiTwin_StatsBomb_Normalizer::team( $match['home_team'], 'home' );
                    $this->replace( 'teams', $team );
                    $team_ids[ $team['source_team_id'] ] = true;
                }

                if ( ! empty( $match['away_team'] ) ) {
                    $team = TactiTwin_StatsBomb_Normalizer::team( $match['away_team'], 'away' );
                    $this->replace( 'teams', $team );
                    $team_ids[ $team['source_team_id'] ] = true;
                }

                $this->replace( 'matches', TactiTwin_StatsBomb_Normalizer::match( $match ) );
            }

            $wpdb->query( 'COMMIT' );
        } catch ( Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            throw $e;
        }

        return array(
            'source'         => TactiTwin_StatsBomb_Normalizer::SOURCE,
            'competition_id' => $competition_id,
            'season_id'      => $season_id,
            'competition'    => (string) ( $competition['competition_name'] ?? '' ),
            'season'         => (string) ( $competition['season_name'] ?? '' ),
            'matches'        => count( $matches ),
            'teams'          => count( $team_ids ),
        );
    }

    public function import_match_events( int $match_id ): array {
        global $wpdb;

        $events = $this->client->events( $match_id );
        if ( empty( $events ) ) {
            throw new RuntimeException( 'No events found for this match.' );
        }

        $event_types = array();
        $players     = array();
        $teams       = array();

        $wpdb->query( 'START TRANSACTION' );

        try {
            foreach ( $events as $event ) {
                if ( ! empty( $event['team']['id'] ) && ! empty( $event['team']['name'] ) ) {
                    $team_id = (string) $event['team']['id'];
                    if ( ! isset( $teams[ $team_id ] ) ) {
                        $this->insert_team_if_missing( $event['team'] );
                        $teams[ $team_id ] = true;
                    }
                }

                $player = TactiTwin_StatsBomb_Normalizer::player( $event );
                if ( null !== $player ) {
                    $this->replace( 'players', $player );
                    $players[ $player['source_player_id'] ] = true;
                }

                $this->persist_tactics_players( $event, $players );

                $normalized = TactiTwin_StatsBomb_Normalizer::event( $match_id, $event );
                if ( '' === $normalized['source_event_id'] ) {
                    continue;
                }

                $this->replace( 'events', $normalized );
                $event_types[ $normalized['event_type_name'] ] = true;
            }

            $wpdb->query( 'COMMIT' );
        } catch ( Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            throw $e;
        }

        return array(
            'source'       => TactiTwin_StatsBomb_Normalizer::SOURCE,
            'match_id'     => $match_id,
            'events'       => count( $events ),
            'players'      => count( $players ),
            'teams'        => count( $teams ),
            'event_types'  => count( $event_types ),
        );
    }

    private function persist_tactics_players( array $event, array &$players ): void {
        if ( empty( $event['tactics']['lineup'] ) || ! is_array( $event['tactics']['lineup'] ) ) {
            return;
        }

        $team_id = (string) ( $event['team']['id'] ?? '' );
        foreach ( $event['tactics']['lineup'] as $slot ) {
            if ( empty( $slot['player']['id'] ) || empty( $slot['player']['name'] ) ) {
                continue;
            }

            $row = array(
                'source'               => TactiTwin_StatsBomb_Normalizer::SOURCE,
                'source_player_id'     => (string) $slot['player']['id'],
                'player_name'          => (string) $slot['player']['name'],
                'last_team_source_id'  => $team_id,
                'last_position_name'   => (string) ( $slot['position']['name'] ?? '' ),
                'raw_json'             => wp_json_encode( $slot['player'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
                'imported_at'          => current_time( 'mysql', true ),
            );

            $this->replace( 'players', $row );
            $players[ $row['source_player_id'] ] = true;
        }
    }

    private function insert_team_if_missing( array $team ): void {
        global $wpdb;

        $table = TactiTwin_DB::table( 'teams' );
        $id    = (string) $team['id'];
        $found = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE source = %s AND source_team_id = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                TactiTwin_StatsBomb_Normalizer::SOURCE,
                $id
            )
        );

        if ( $found ) {
            return;
        }

        $this->replace(
            'teams',
            array(
                'source'         => TactiTwin_StatsBomb_Normalizer::SOURCE,
                'source_team_id' => $id,
                'team_name'      => (string) $team['name'],
                'country_name'   => '',
                'raw_json'       => wp_json_encode( $team, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
                'imported_at'    => current_time( 'mysql', true ),
            )
        );
    }

    private function replace( string $entity, array $data ): void {
        global $wpdb;

        $result = $wpdb->replace( TactiTwin_DB::table( $entity ), $data );
        if ( false === $result ) {
            throw new RuntimeException( 'Database write failed for ' . $entity . ': ' . $wpdb->last_error );
        }
    }
}
