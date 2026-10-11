<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Profile_Service {
    public function team_profile( int $team_id, int $limit = 5 ): array {
        global $wpdb;

        $limit       = max( 1, min( 10, $limit ) );
        $match_table = TactiTwin_DB::table( 'matches' );
        $event_table = TactiTwin_DB::table( 'events' );
        $source      = TactiTwin_StatsBomb_Normalizer::SOURCE;

        $matches = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.source_match_id, m.match_date, m.home_team_source_id, m.away_team_source_id
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
        );

        if ( empty( $matches ) ) {
            throw new RuntimeException( 'No imported event-data matches are available for this team.' );
        }

        $analysis = new TactiTwin_Analysis_Service();
        $samples  = array();

        foreach ( $matches as $match ) {
            $summary  = $analysis->match_summary( (int) $match['source_match_id'] );
            $team_key = (string) $team_id;

            if ( ! isset( $summary['teams'][ $team_key ] ) ) {
                continue;
            }

            $opponent_id = (string) $match['home_team_source_id'] === $team_key
                ? (string) $match['away_team_source_id']
                : (string) $match['home_team_source_id'];

            $samples[] = array(
                'match_id'         => (string) $match['source_match_id'],
                'match_date'       => (string) $match['match_date'],
                'team_metrics'     => $summary['teams'][ $team_key ],
                'opponent_metrics' => $summary['teams'][ $opponent_id ] ?? array(),
            );
        }

        if ( empty( $samples ) ) {
            throw new RuntimeException( 'Imported matches do not contain normalized metrics for this team.' );
        }

        return TactiTwin_Team_Profile::aggregate( $samples );
    }

    public function build_up_vs_high_press( int $team_id, int $opponent_id, int $limit = 5, string $language = 'en' ): array {
        $profiles = $this->matchup_profiles( $team_id, $opponent_id, $limit );
        return TactiTwin_High_Press_Scenario::evaluate( $profiles['team'], $profiles['opponent'], $language );
    }

    public function opponent_side_bias( int $team_id, int $opponent_id, int $limit = 5, string $language = 'en' ): array {
        $profiles = $this->matchup_profiles( $team_id, $opponent_id, $limit );
        return TactiTwin_Side_Bias_Scenario::evaluate( $profiles['team'], $profiles['opponent'], $language );
    }

    public function defensive_transition_after_loss( int $team_id, int $opponent_id, int $limit = 5, string $language = 'en' ): array {
        $profiles = $this->matchup_profiles( $team_id, $opponent_id, $limit );
        return TactiTwin_Defensive_Transition_Scenario::evaluate( $profiles['team'], $profiles['opponent'], $language );
    }

    public function attack_vs_low_block( int $team_id, int $opponent_id, int $limit = 5, string $language = 'en' ): array {
        $profiles = $this->matchup_profiles( $team_id, $opponent_id, $limit );
        return TactiTwin_Low_Block_Scenario::evaluate( $profiles['team'], $profiles['opponent'], $language );
    }

    public function exploit_opponent_weak_side( int $team_id, int $opponent_id, int $limit = 5, string $language = 'en' ): array {
        $profiles = $this->matchup_profiles( $team_id, $opponent_id, $limit );
        return TactiTwin_Weak_Side_Scenario::evaluate( $profiles['team'], $profiles['opponent'], $language );
    }

    public function full_matchup_scan( int $team_id, int $opponent_id, int $limit = 5, string $language = 'en' ): array {
        $profiles = $this->matchup_profiles( $team_id, $opponent_id, $limit );
        return TactiTwin_Matchup_Scan::evaluate( $profiles['team'], $profiles['opponent'], $language );
    }

    public function match_plan( int $team_id, int $opponent_id, int $limit = 5, string $language = 'en' ): array {
        $scan = $this->full_matchup_scan( $team_id, $opponent_id, $limit, $language );
        return TactiTwin_Match_Plan::compose( $scan, $language );
    }

    public function game_state_what_if(
        int $team_id,
        int $opponent_id,
        int $our_score,
        int $opponent_score,
        int $minute,
        int $limit = 5,
        string $language = 'en',
        int $our_players = 11,
        int $opponent_players = 11
    ): array {
        $profiles = $this->matchup_profiles( $team_id, $opponent_id, $limit );
        $scan = TactiTwin_Matchup_Scan::evaluate( $profiles['team'], $profiles['opponent'], $language );

        return TactiTwin_Context_What_If::evaluate(
            $profiles['team'],
            $profiles['opponent'],
            $scan,
            $our_score,
            $opponent_score,
            $minute,
            $language,
            $our_players,
            $opponent_players
        );
    }

    public function contingency_playbook(
        int $team_id,
        int $opponent_id,
        int $our_score,
        int $opponent_score,
        int $minute,
        int $limit = 5,
        string $language = 'en',
        int $our_players = 11,
        int $opponent_players = 11
    ): array {
        $components = $this->contingency_components(
            $team_id,
            $opponent_id,
            $our_score,
            $opponent_score,
            $minute,
            $limit,
            $language,
            $our_players,
            $opponent_players
        );

        $playbook = TactiTwin_Contingency_Playbook::compose(
            $components['match_plan'],
            $components['context'],
            $components['scan'],
            $components['language']
        );
        return $this->attach_scenario_fingerprints( $playbook, $components['scan'] );
    }

    public function live_contingency_playbook(
        int $team_id,
        int $opponent_id,
        int $our_score,
        int $opponent_score,
        int $minute,
        array $observations,
        int $limit = 5,
        string $language = 'en',
        int $our_players = 11,
        int $opponent_players = 11
    ): array {
        $components = $this->contingency_components(
            $team_id,
            $opponent_id,
            $our_score,
            $opponent_score,
            $minute,
            $limit,
            $language,
            $our_players,
            $opponent_players
        );
        $playbook = TactiTwin_Contingency_Playbook::compose(
            $components['match_plan'],
            $components['context'],
            $components['scan'],
            $components['language']
        );
        $playbook = $this->attach_scenario_fingerprints( $playbook, $components['scan'] );

        return TactiTwin_Live_Observation::apply(
            $playbook,
            $components['scan'],
            $observations,
            $components['language']
        );
    }

    public function matchup_profiles( int $team_id, int $opponent_id, int $limit ): array {
        if ( $team_id === $opponent_id ) {
            throw new InvalidArgumentException( 'Team and opponent must be different.' );
        }

        return array(
            'team'     => $this->team_profile( $team_id, $limit ),
            'opponent' => $this->team_profile( $opponent_id, $limit ),
        );
    }

    private function contingency_components(
        int $team_id,
        int $opponent_id,
        int $our_score,
        int $opponent_score,
        int $minute,
        int $limit,
        string $language,
        int $our_players,
        int $opponent_players
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $profiles = $this->matchup_profiles( $team_id, $opponent_id, $limit );
        $scan = TactiTwin_Matchup_Scan::evaluate( $profiles['team'], $profiles['opponent'], $language );
        $match_plan = TactiTwin_Match_Plan::compose( $scan, $language );
        $context = TactiTwin_Context_What_If::evaluate(
            $profiles['team'],
            $profiles['opponent'],
            $scan,
            $our_score,
            $opponent_score,
            $minute,
            $language,
            $our_players,
            $opponent_players
        );

        return array(
            'language' => $language,
            'profiles' => $profiles,
            'scan' => $scan,
            'match_plan' => $match_plan,
            'context' => $context,
        );
    }

    private function attach_scenario_fingerprints( array $playbook, array $scan ): array {
        $fingerprints = array();
        foreach ( $scan['subscenarios'] ?? array() as $scenario ) {
            $scenario_id = (string) ( $scenario['scenario_id'] ?? '' );
            if ( '' === $scenario_id ) {
                continue;
            }

            $fingerprint = array(
                'risk_score' => round( (float) ( $scenario['risk']['score'] ?? 0.0 ), 1 ),
                'opportunity_score' => round( (float) ( $scenario['opportunity']['score'] ?? 0.0 ), 1 ),
            );

            if ( 'exploit-opponent-weak-side' === $scenario_id ) {
                $target = (string) ( $scenario['opportunity']['channel'] ?? $scenario['evidence']['best_matchup_channel'] ?? '' );
                if ( in_array( $target, array( 'left', 'central', 'right' ), true ) ) {
                    $fingerprint['target_channel'] = $target;
                }
            }
            if ( 'opponent-side-bias' === $scenario_id ) {
                $dominant = (string) ( $scenario['evidence']['opponent_dominant_channel'] ?? $scenario['evidence']['dominant_channel'] ?? '' );
                if ( in_array( $dominant, array( 'left', 'central', 'right' ), true ) ) {
                    $fingerprint['opponent_dominant_channel'] = $dominant;
                }
            }

            $fingerprints[ $scenario_id ] = $fingerprint;
        }

        if ( ! isset( $playbook['evidence'] ) || ! is_array( $playbook['evidence'] ) ) {
            $playbook['evidence'] = array();
        }
        $playbook['evidence']['scenario_fingerprints'] = $fingerprints;
        return $playbook;
    }
}
