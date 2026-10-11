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
        $language = TactiTwin_I18n::normalize_language( $language );

        $review = TactiTwin_Post_Match_Review::build(
            $session,
            $snapshots,
            $language
        );
        $review['objective_evidence'] = $this->objective_evidence(
            $session,
            $review,
            $snapshots,
            $language
        );

        return $review;
    }

    private function objective_evidence( array $session, array $review, array $snapshots, string $language ): array {
        $match_id = (int) ( $session['source_match_id'] ?? 0 );
        if ( $match_id <= 0 ) {
            return array(
                'status' => 'unlinked',
                'source_match_id' => null,
                'message' => 'es' === $language
                    ? 'La sesión todavía no está enlazada a un partido importado. La trazabilidad manual sigue disponible, pero no puede contrastarse con eventos del partido.'
                    : 'The session is not linked to an imported match yet. The manual audit trail remains available, but it cannot be compared with match events.',
            );
        }

        try {
            $match_summary = ( new TactiTwin_Analysis_Service() )->match_summary( $match_id );
            $evidence = TactiTwin_Objective_Post_Match_Evidence::evaluate(
                $session,
                $review,
                $snapshots,
                $match_summary,
                $language
            );
            $evidence['status'] = 'available';
            return $evidence;
        } catch ( Throwable $e ) {
            return array(
                'status' => 'unavailable',
                'source_match_id' => (string) $match_id,
                'message' => 'es' === $language
                    ? 'El partido está enlazado, pero la evidencia de eventos todavía no está disponible: ' . $e->getMessage()
                    : 'The match is linked, but event evidence is not available yet: ' . $e->getMessage(),
            );
        }
    }
}
