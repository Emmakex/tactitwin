<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Post_Match_Review_Service {
    public function review( string $session_uuid, ?string $language = null ): array {
        $timeline = ( new TactiTwin_Live_Session_Service() )->timeline( $session_uuid );
        $session = $timeline['session'] ?? array();
        $snapshots = $timeline['snapshots'] ?? array();
        $language = null === $language || '' === trim( $language )
            ? (string) ( $session['language'] ?? TactiTwin_I18n::current_language() )
            : $language;

        return TactiTwin_Post_Match_Review::build(
            $session,
            $snapshots,
            TactiTwin_I18n::normalize_language( $language )
        );
    }
}
