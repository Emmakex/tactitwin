<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_StatsBomb_Client {
    private const BASE_URL = 'https://raw.githubusercontent.com/hudl/open-data/master/data';

    public function competitions(): array {
        return $this->get_json( self::BASE_URL . '/competitions.json' );
    }

    public function matches( int $competition_id, int $season_id ): array {
        return $this->get_json(
            sprintf(
                '%s/matches/%d/%d.json',
                self::BASE_URL,
                $competition_id,
                $season_id
            )
        );
    }

    public function events( int $match_id ): array {
        return $this->get_json(
            sprintf( '%s/events/%d.json', self::BASE_URL, $match_id )
        );
    }

    private function get_json( string $url ): array {
        $cache_key = 'tactitwin_' . md5( $url );
        $cached    = get_transient( $cache_key );

        if ( is_array( $cached ) ) {
            return $cached;
        }

        $response = wp_remote_get(
            $url,
            array(
                'timeout'    => 30,
                'redirection'=> 3,
                'headers'    => array(
                    'Accept'     => 'application/json',
                    'User-Agent' => 'TactiTwin/' . TACTITWIN_VERSION . '; ' . home_url( '/' ),
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            throw new RuntimeException( 'StatsBomb request failed: ' . $response->get_error_message() );
        }

        $status = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status ) {
            throw new RuntimeException( sprintf( 'StatsBomb request returned HTTP %d.', $status ) );
        }

        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $decoded ) ) {
            throw new RuntimeException( 'StatsBomb response is not valid JSON.' );
        }

        set_transient( $cache_key, $decoded, 6 * HOUR_IN_SECONDS );

        return $decoded;
    }
}
