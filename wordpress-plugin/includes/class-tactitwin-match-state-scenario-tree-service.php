<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Match_State_Scenario_Tree_Service {
    public function evaluate(
        int $team_id,
        int $opponent_id,
        int $limit = 5,
        string $language = 'en'
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $profile_service = new TactiTwin_Profile_Service();
        $profiles = $profile_service->matchup_profiles( $team_id, $opponent_id, $limit );
        $scan = TactiTwin_Matchup_Scan::evaluate(
            $profiles['team'],
            $profiles['opponent'],
            $language
        );

        return TactiTwin_Match_State_Scenario_Tree::compose(
            $profiles['team'],
            $profiles['opponent'],
            $scan,
            $language
        );
    }
}
