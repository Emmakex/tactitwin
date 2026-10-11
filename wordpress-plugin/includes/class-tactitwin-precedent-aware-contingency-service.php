<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Precedent_Aware_Contingency_Service {
    public function evaluate(
        int $team_id,
        int $opponent_id,
        int $our_score,
        int $opponent_score,
        int $minute,
        int $limit = 5,
        string $language = 'en',
        int $our_players = 11,
        int $opponent_players = 11,
        int $precedent_limit = 5
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $profile_service = new TactiTwin_Profile_Service();
        $playbook = $profile_service->contingency_playbook(
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

        $precedents = $this->precedents(
            $team_id,
            $opponent_id,
            $our_score,
            $opponent_score,
            $minute,
            $limit,
            $language,
            $our_players,
            $opponent_players,
            $precedent_limit
        );

        return TactiTwin_Playbook_Precedent_Attachment::attach(
            $playbook,
            $precedents,
            $language,
            $precedent_limit
        );
    }

    public function live_evaluate(
        int $team_id,
        int $opponent_id,
        int $our_score,
        int $opponent_score,
        int $minute,
        array $observations,
        int $limit = 5,
        string $language = 'en',
        int $our_players = 11,
        int $opponent_players = 11,
        int $precedent_limit = 5
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $profile_service = new TactiTwin_Profile_Service();
        $playbook = $profile_service->live_contingency_playbook(
            $team_id,
            $opponent_id,
            $our_score,
            $opponent_score,
            $minute,
            $observations,
            $limit,
            $language,
            $our_players,
            $opponent_players
        );

        $precedents = $this->precedents(
            $team_id,
            $opponent_id,
            $our_score,
            $opponent_score,
            $minute,
            $limit,
            $language,
            $our_players,
            $opponent_players,
            $precedent_limit
        );

        return TactiTwin_Playbook_Precedent_Attachment::attach(
            $playbook,
            $precedents,
            $language,
            $precedent_limit
        );
    }

    private function precedents(
        int $team_id,
        int $opponent_id,
        int $our_score,
        int $opponent_score,
        int $minute,
        int $limit,
        string $language,
        int $our_players,
        int $opponent_players,
        int $precedent_limit
    ): array {
        try {
            $what_if = ( new TactiTwin_Precedent_Aware_What_If_Service() )->evaluate(
                $team_id,
                $opponent_id,
                $our_score,
                $opponent_score,
                $minute,
                $limit,
                $language,
                $our_players,
                $opponent_players,
                $precedent_limit
            );
            $precedents = $what_if['historical_precedents'] ?? array();
            if ( 'historical-precedent-retrieval' === (string) ( $precedents['precedent_id'] ?? '' ) ) {
                return $precedents;
            }
        } catch ( Throwable $e ) {
            return $this->unavailable( $language, $e->getMessage() );
        }

        return $this->unavailable( $language, 'historical precedent retrieval returned no valid payload' );
    }

    private function unavailable( string $language, string $reason ): array {
        return array(
            'precedent_id' => 'historical-precedent-retrieval',
            'status' => 'unavailable',
            'language' => $language,
            'matched_group_count' => 0,
            'returned_group_count' => 0,
            'items' => array(),
            'summary' => array(
                'es' === $language
                    ? 'Los precedentes históricos no están disponibles para este Plan A/B/C: ' . $reason
                    : 'Historical precedents are unavailable for this Plan A/B/C: ' . $reason,
            ),
            'method' => array(
                'type' => 'exact-context-historical-precedent-retrieval',
                'reweights_tactical_scores' => false,
                'changes_recommendations' => false,
            ),
        );
    }
}
