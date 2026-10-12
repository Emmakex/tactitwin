<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Tactical_Twin_Lab_Service {
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

        return TactiTwin_Tactical_Twin_Lab::compose(
            $profiles['team'],
            $profiles['opponent'],
            $scan,
            $language
        );
    }
}
