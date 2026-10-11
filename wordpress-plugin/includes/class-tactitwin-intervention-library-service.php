<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Intervention_Library_Service {
    public function build(
        int $team_id,
        ?string $language = null,
        string $scenario_filter = '',
        int $limit = 50
    ): array {
        if ( $team_id <= 0 ) {
            throw new InvalidArgumentException( 'A positive team identifier is required.' );
        }

        $language = TactiTwin_I18n::normalize_language( $language ?: TactiTwin_I18n::current_language() );
        $scenario_filter = sanitize_key( $scenario_filter );
        $limit = max( 1, min( 100, $limit ) );
        $session_service = new TactiTwin_Live_Session_Service();
        $review_service = new TactiTwin_Post_Match_Review_Service();
        $sessions = $session_service->recent_sessions( $limit, $team_id );
        $cases = array();
        $session_dates = array();
        $processed = 0;
        $skipped = 0;
        $failures = array();

        foreach ( $sessions as $session ) {
            if ( 'closed' !== (string) ( $session['status'] ?? '' ) || '' === (string) ( $session['source_match_id'] ?? '' ) ) {
                ++$skipped;
                continue;
            }

            $session_uuid = (string) ( $session['session_uuid'] ?? '' );
            if ( '' !== $session_uuid ) {
                $session_dates[ $session_uuid ] = (string) ( $session['closed_at'] ?: ( $session['updated_at'] ?? '' ) );
            }

            try {
                $review = $review_service->review( $session_uuid, $language );
                $context_review = $review['context_aware_transition_review'] ?? array();
                if ( 'available' !== (string) ( $context_review['status'] ?? '' ) ) {
                    ++$skipped;
                    continue;
                }
                ++$processed;
                foreach ( $context_review['transitions'] ?? array() as $transition ) {
                    $cases[] = array(
                        'session_uuid' => $session_uuid,
                        'source_match_id' => (string) $session['source_match_id'],
                        'opponent_source_id' => (string) $session['opponent_source_id'],
                        'transition' => $transition,
                    );
                }
            } catch ( Throwable $e ) {
                ++$skipped;
                $failures[] = array(
                    'session_uuid' => $session_uuid,
                    'message' => $e->getMessage(),
                );
            }
        }

        $library = TactiTwin_Context_Matched_Intervention_Library::build(
            $cases,
            (string) $team_id,
            $language,
            $scenario_filter
        );
        $library = $this->attach_session_dates( $library, $session_dates );
        $library['status'] = 'available';
        $library['coverage']['sessions_scanned'] = count( $sessions );
        $library['coverage']['sessions_processed'] = $processed;
        $library['coverage']['sessions_skipped'] = $skipped;
        $library['coverage']['transition_cases'] = count( $cases );
        $library['coverage']['review_failures'] = count( $failures );
        $library['failures'] = $failures;
        return $library;
    }

    private function attach_session_dates( array $library, array $session_dates ): array {
        if ( empty( $library['groups'] ) || ! is_array( $library['groups'] ) ) {
            return $library;
        }

        foreach ( $library['groups'] as &$group ) {
            if ( empty( $group['cases'] ) || ! is_array( $group['cases'] ) ) {
                continue;
            }
            foreach ( $group['cases'] as &$case ) {
                $uuid = (string) ( $case['session_uuid'] ?? '' );
                $case['session_closed_at'] = (string) ( $session_dates[ $uuid ] ?? '' );
            }
            unset( $case );
        }
        unset( $group );
        return $library;
    }
}
