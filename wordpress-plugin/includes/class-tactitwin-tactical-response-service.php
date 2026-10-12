<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Tactical_Response_Service {
    public function evaluate(
        int $team_id,
        int $opponent_id,
        int $limit = 5,
        string $language = 'en'
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $scan = ( new TactiTwin_Profile_Service() )->full_matchup_scan(
            $team_id,
            $opponent_id,
            $limit,
            $language
        );

        return TactiTwin_Tactical_Response_Matrix::compose( $scan, $language );
    }
}
