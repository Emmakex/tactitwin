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

        $bundle = $this->linked_match_bundle( $session );
        $review['objective_evidence'] = $this->objective_evidence( $session, $review, $snapshots, $language, $bundle );
        $review['segment_evidence'] = $this->segment_evidence( $session, $snapshots, $language, $bundle );
        $review['transition_window_deltas'] = $this->transition_window_deltas( $session, $snapshots, $language, $bundle );

        return $review;
    }

    private function linked_match_bundle( array $session ): array {
        $match_id = (int) ( $session['source_match_id'] ?? 0 );
        if ( $match_id <= 0 ) {
            return array( 'status'=>'unlinked', 'match_id'=>0, 'summary'=>null, 'events'=>array(), 'error'=>null );
        }

        try {
            $analysis = new TactiTwin_Analysis_Service();
            return array(
                'status'=>'available',
                'match_id'=>$match_id,
                'summary'=>$analysis->match_summary( $match_id ),
                'events'=>$analysis->match_events( $match_id ),
                'error'=>null,
            );
        } catch ( Throwable $e ) {
            return array( 'status'=>'unavailable', 'match_id'=>$match_id, 'summary'=>null, 'events'=>array(), 'error'=>$e->getMessage() );
        }
    }

    private function objective_evidence( array $session, array $review, array $snapshots, string $language, array $bundle ): array {
        if ( 'unlinked' === (string) $bundle['status'] ) {
            return array(
                'status' => 'unlinked',
                'source_match_id' => null,
                'message' => 'es' === $language
                    ? 'La sesión todavía no está enlazada a un partido importado. La trazabilidad manual sigue disponible, pero no puede contrastarse con eventos del partido.'
                    : 'The session is not linked to an imported match yet. The manual audit trail remains available, but it cannot be compared with match events.',
            );
        }
        if ( 'available' !== (string) $bundle['status'] || ! is_array( $bundle['summary'] ) ) {
            return array(
                'status'=>'unavailable',
                'source_match_id'=>(string) ( $bundle['match_id'] ?? '' ),
                'message'=>'es' === $language
                    ? 'El partido está enlazado, pero la evidencia de eventos todavía no está disponible: ' . (string) ( $bundle['error'] ?? '' )
                    : 'The match is linked, but event evidence is not available yet: ' . (string) ( $bundle['error'] ?? '' ),
            );
        }

        try {
            $evidence = TactiTwin_Objective_Post_Match_Evidence::evaluate(
                $session,
                $review,
                $snapshots,
                $bundle['summary'],
                $language
            );
            $evidence['status'] = 'available';
            return $evidence;
        } catch ( Throwable $e ) {
            return array(
                'status'=>'unavailable',
                'source_match_id'=>(string) ( $bundle['match_id'] ?? '' ),
                'message'=>'es' === $language ? 'No se pudo construir la evidencia global: ' . $e->getMessage() : 'Could not build full-match evidence: ' . $e->getMessage(),
            );
        }
    }

    private function segment_evidence( array $session, array $snapshots, string $language, array $bundle ): array {
        if ( 'unlinked' === (string) $bundle['status'] ) {
            return array(
                'status'=>'unlinked','source_match_id'=>null,
                'message'=>'es' === $language ? 'Enlaza la sesión con un partido importado para analizar evidencia por intervalos A/B/C.' : 'Link the session to an imported match to analyse evidence across A/B/C intervals.',
            );
        }
        if ( 'available' !== (string) $bundle['status'] || ! is_array( $bundle['summary'] ) || empty( $bundle['events'] ) ) {
            return array(
                'status'=>'unavailable','source_match_id'=>(string) ( $bundle['match_id'] ?? '' ),
                'message'=>'es' === $language ? 'La evidencia temporal por segmentos no está disponible: ' . (string) ( $bundle['error'] ?? '' ) : 'Time-aligned segment evidence is not available: ' . (string) ( $bundle['error'] ?? '' ),
            );
        }

        try {
            $evidence = TactiTwin_Time_Aligned_Evidence::evaluate(
                $session,
                $snapshots,
                $bundle['events'],
                $bundle['summary'],
                $language
            );
            $evidence['status'] = 'available';
            return $evidence;
        } catch ( Throwable $e ) {
            return array(
                'status'=>'unavailable','source_match_id'=>(string) ( $bundle['match_id'] ?? '' ),
                'message'=>'es' === $language ? 'La evidencia temporal por segmentos no está disponible: ' . $e->getMessage() : 'Time-aligned segment evidence is not available: ' . $e->getMessage(),
            );
        }
    }

    private function transition_window_deltas( array $session, array $snapshots, string $language, array $bundle ): array {
        if ( 'unlinked' === (string) $bundle['status'] ) {
            return array(
                'status'=>'unlinked','source_match_id'=>null,
                'message'=>'es' === $language ? 'Enlaza la sesión con un partido importado para comparar ventanas antes/después de cada transición.' : 'Link the session to an imported match to compare before/after windows around each transition.',
            );
        }
        if ( 'available' !== (string) $bundle['status'] || ! is_array( $bundle['summary'] ) || empty( $bundle['events'] ) ) {
            return array(
                'status'=>'unavailable','source_match_id'=>(string) ( $bundle['match_id'] ?? '' ),
                'message'=>'es' === $language ? 'Las ventanas antes/después no están disponibles: ' . (string) ( $bundle['error'] ?? '' ) : 'Before/after transition windows are not available: ' . (string) ( $bundle['error'] ?? '' ),
            );
        }

        try {
            $evidence = TactiTwin_Transition_Window_Deltas::evaluate(
                $session,
                $snapshots,
                $bundle['events'],
                $bundle['summary'],
                $language
            );
            $evidence['status'] = 'available';
            return $evidence;
        } catch ( Throwable $e ) {
            return array(
                'status'=>'unavailable','source_match_id'=>(string) ( $bundle['match_id'] ?? '' ),
                'message'=>'es' === $language ? 'No se pudieron construir las ventanas antes/después: ' . $e->getMessage() : 'Could not build before/after transition windows: ' . $e->getMessage(),
            );
        }
    }
}
