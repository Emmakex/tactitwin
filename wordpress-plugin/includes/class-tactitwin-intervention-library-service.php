<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Intervention_Library_Service {
    public function build(
        int $team_id,
        ?string $language = null,
        string $scenario_filter = '',
        int $limit = 50,
        string $learning_scope = 'all'
    ): array {
        if ( $team_id <= 0 ) {
            throw new InvalidArgumentException( 'A positive team identifier is required.' );
        }

        $language = TactiTwin_I18n::normalize_language( $language ?: TactiTwin_I18n::current_language() );
        $scenario_filter = sanitize_key( $scenario_filter );
        $limit = max( 1, min( 100, $limit ) );
        $learning_scope = self::normalize_learning_scope( $learning_scope );
        $session_service = new TactiTwin_Live_Session_Service();
        $review_service = new TactiTwin_Post_Match_Review_Service();
        $signoff_service = new TactiTwin_Post_Match_Review_Signoff_Service();
        $sessions = $session_service->recent_sessions( $limit, $team_id );
        $cases = array();
        $session_dates = array();
        $match_ids = array();
        $review_markers = array();
        $processed = 0;
        $skipped = 0;
        $scope_filtered = 0;
        $signed_sessions = 0;
        $unsigned_sessions = 0;
        $invalid_signed_reviews = 0;
        $failures = array();

        foreach ( $sessions as $session ) {
            if ( 'closed' !== (string) ( $session['status'] ?? '' ) || '' === (string) ( $session['source_match_id'] ?? '' ) ) {
                ++$skipped;
                continue;
            }

            $session_uuid = (string) ( $session['session_uuid'] ?? '' );
            $source_match_id = (string) ( $session['source_match_id'] ?? '' );
            $marker = self::unsigned_review_marker( $session_uuid );
            try {
                $signed_review = $signoff_service->latest_for_session( $session_uuid );
                if ( ! empty( $signed_review ) ) {
                    $marker = self::review_marker( $signed_review, $session_uuid );
                }
            } catch ( Throwable $e ) {
                $marker = self::invalid_review_marker( $session_uuid );
                ++$invalid_signed_reviews;
                $failures[] = array(
                    'session_uuid' => $session_uuid,
                    'type' => 'signed-review-integrity',
                    'message' => $e->getMessage(),
                );
            }
            $review_markers[ $session_uuid ] = $marker;

            if ( 'signed' === (string) $marker['status'] ) {
                ++$signed_sessions;
            } elseif ( 'invalid' !== (string) $marker['status'] ) {
                ++$unsigned_sessions;
            }

            if ( 'signed' === $learning_scope && 'signed' !== (string) $marker['status'] ) {
                ++$scope_filtered;
                ++$skipped;
                continue;
            }

            if ( '' !== $session_uuid ) {
                $session_dates[ $session_uuid ] = (string) ( $session['closed_at'] ?: ( $session['updated_at'] ?? '' ) );
            }
            if ( '' !== $source_match_id ) {
                $match_ids[ $source_match_id ] = true;
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
                        'source_match_id' => $source_match_id,
                        'opponent_source_id' => (string) $session['opponent_source_id'],
                        'transition' => $transition,
                    );
                }
            } catch ( Throwable $e ) {
                ++$skipped;
                $failures[] = array(
                    'session_uuid' => $session_uuid,
                    'type' => 'review-build',
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
        $library = $this->attach_case_dates(
            $library,
            $session_dates,
            $this->match_dates( array_keys( $match_ids ) )
        );
        $library = self::attach_signed_review_provenance( $library, $review_markers, $learning_scope );
        $library['status'] = 'available';
        $library['coverage']['sessions_scanned'] = count( $sessions );
        $library['coverage']['sessions_processed'] = $processed;
        $library['coverage']['sessions_skipped'] = $skipped;
        $library['coverage']['transition_cases'] = count( $cases );
        $library['coverage']['review_failures'] = count( $failures );
        $library['coverage']['signed_review_sessions'] = $signed_sessions;
        $library['coverage']['unsigned_review_sessions'] = $unsigned_sessions;
        $library['coverage']['invalid_signed_reviews'] = $invalid_signed_reviews;
        $library['coverage']['learning_scope_filtered_sessions'] = $scope_filtered;
        $library['learning_scope'] = $learning_scope;
        $library['failures'] = $failures;
        $library['method']['signed_review_learning'] = array(
            'scope' => $learning_scope,
            'signed_review_is_provenance_not_effectiveness' => true,
            'signed_review_changes_grouping_dimensions' => false,
            'signed_review_changes_aggregate_weighting' => false,
            'signed_review_changes_precedent_ranking' => false,
        );
        return $library;
    }

    public static function normalize_learning_scope( string $scope ): string {
        $scope = strtolower( trim( $scope ) );
        return 'signed' === $scope ? 'signed' : 'all';
    }

    public static function review_marker( array $snapshot, string $session_uuid = '' ): array {
        $review_uuid = trim( (string) ( $snapshot['review_uuid'] ?? '' ) );
        $snapshot_session = trim( (string) ( $snapshot['session_uuid'] ?? '' ) );
        if ( '' === $review_uuid || '' === $snapshot_session || ( '' !== $session_uuid && $session_uuid !== $snapshot_session ) ) {
            return self::invalid_review_marker( $session_uuid ?: $snapshot_session );
        }
        return array(
            'status' => 'signed',
            'review_uuid' => $review_uuid,
            'revision_number' => max( 1, (int) ( $snapshot['revision_number'] ?? 1 ) ),
            'signed_at' => (string) ( $snapshot['signed_at'] ?? '' ),
            'signed_by' => max( 0, (int) ( $snapshot['signed_by'] ?? 0 ) ),
            'content_sha256' => strtolower( trim( (string) ( $snapshot['content_sha256'] ?? '' ) ) ),
        );
    }

    public static function attach_signed_review_provenance( array $library, array $review_markers, string $scope = 'all' ): array {
        $scope = self::normalize_learning_scope( $scope );
        $library_signed_cases = 0;
        $library_unsigned_cases = 0;
        $library_invalid_cases = 0;
        $signed_session_ids = array();

        if ( ! empty( $library['groups'] ) && is_array( $library['groups'] ) ) {
            foreach ( $library['groups'] as &$group ) {
                $signed_cases = 0;
                $unsigned_cases = 0;
                $invalid_cases = 0;
                $group_signed_sessions = array();
                $latest_revision = 0;

                if ( ! empty( $group['cases'] ) && is_array( $group['cases'] ) ) {
                    foreach ( $group['cases'] as &$case ) {
                        $session_uuid = (string) ( $case['session_uuid'] ?? '' );
                        $marker = is_array( $review_markers[ $session_uuid ] ?? null )
                            ? $review_markers[ $session_uuid ]
                            : self::unsigned_review_marker( $session_uuid );
                        $case['learning_review'] = $marker;
                        if ( 'signed' === (string) ( $marker['status'] ?? '' ) ) {
                            ++$signed_cases;
                            ++$library_signed_cases;
                            $group_signed_sessions[ $session_uuid ] = true;
                            $signed_session_ids[ $session_uuid ] = true;
                            $latest_revision = max( $latest_revision, (int) ( $marker['revision_number'] ?? 0 ) );
                        } elseif ( 'invalid' === (string) ( $marker['status'] ?? '' ) ) {
                            ++$invalid_cases;
                            ++$library_invalid_cases;
                        } else {
                            ++$unsigned_cases;
                            ++$library_unsigned_cases;
                        }
                    }
                    unset( $case );
                }

                $total = $signed_cases + $unsigned_cases + $invalid_cases;
                $group['learning_review'] = array(
                    'status' => self::coverage_status( $signed_cases, $unsigned_cases, $invalid_cases ),
                    'scope' => $scope,
                    'signed_cases' => $signed_cases,
                    'unsigned_cases' => $unsigned_cases,
                    'invalid_cases' => $invalid_cases,
                    'total_cases' => $total,
                    'signed_sessions' => count( $group_signed_sessions ),
                    'latest_signed_revision' => $latest_revision,
                    'affects_metrics' => 'signed' === $scope,
                    'affects_ranking' => false,
                );
            }
            unset( $group );
        }

        $library['learning_review'] = array(
            'scope' => $scope,
            'status' => self::coverage_status( $library_signed_cases, $library_unsigned_cases, $library_invalid_cases ),
            'signed_cases' => $library_signed_cases,
            'unsigned_cases' => $library_unsigned_cases,
            'invalid_cases' => $library_invalid_cases,
            'signed_sessions' => count( $signed_session_ids ),
            'filter_semantics' => 'signed' === $scope ? 'signed-review-sessions-only' : 'all-eligible-sessions',
            'signed_review_is_validation_provenance' => true,
            'signed_review_is_not_tactical_effectiveness' => true,
        );
        return $library;
    }

    public static function precedent_learning_coverage( array $items ): array {
        $counts = array( 'all-signed' => 0, 'mixed' => 0, 'unsigned' => 0, 'invalid' => 0 );
        foreach ( $items as $item ) {
            $status = (string) ( $item['learning_review']['status'] ?? 'unsigned' );
            if ( ! isset( $counts[ $status ] ) ) {
                $status = 'unsigned';
            }
            ++$counts[ $status ];
        }
        return array(
            'returned_groups' => count( $items ),
            'all_signed_groups' => $counts['all-signed'],
            'mixed_groups' => $counts['mixed'],
            'unsigned_groups' => $counts['unsigned'],
            'invalid_groups' => $counts['invalid'],
            'affects_retrieval_score' => false,
        );
    }

    private static function coverage_status( int $signed, int $unsigned, int $invalid ): string {
        if ( $invalid > 0 ) {
            return 'invalid';
        }
        if ( $signed > 0 && 0 === $unsigned ) {
            return 'all-signed';
        }
        if ( $signed > 0 ) {
            return 'mixed';
        }
        return 'unsigned';
    }

    private static function unsigned_review_marker( string $session_uuid ): array {
        return array(
            'status' => 'unsigned',
            'review_uuid' => '',
            'revision_number' => 0,
            'signed_at' => '',
            'signed_by' => 0,
            'content_sha256' => '',
            'session_uuid' => $session_uuid,
        );
    }

    private static function invalid_review_marker( string $session_uuid ): array {
        $marker = self::unsigned_review_marker( $session_uuid );
        $marker['status'] = 'invalid';
        return $marker;
    }

    private function attach_case_dates( array $library, array $session_dates, array $match_dates ): array {
        if ( empty( $library['groups'] ) || ! is_array( $library['groups'] ) ) {
            return $library;
        }

        foreach ( $library['groups'] as &$group ) {
            if ( empty( $group['cases'] ) || ! is_array( $group['cases'] ) ) {
                continue;
            }
            foreach ( $group['cases'] as &$case ) {
                $uuid = (string) ( $case['session_uuid'] ?? '' );
                $match_id = (string) ( $case['source_match_id'] ?? '' );
                $case['session_closed_at'] = (string) ( $session_dates[ $uuid ] ?? '' );
                $case['source_match_date'] = (string) ( $match_dates[ $match_id ] ?? '' );
            }
            unset( $case );
        }
        unset( $group );
        return $library;
    }

    private function match_dates( array $match_ids ): array {
        global $wpdb;
        $match_ids = array_values( array_unique( array_filter( array_map( 'strval', $match_ids ) ) ) );
        if ( empty( $match_ids ) ) {
            return array();
        }

        $table = TactiTwin_DB::table( 'matches' );
        $placeholders = implode( ',', array_fill( 0, count( $match_ids ), '%s' ) );
        $sql = $wpdb->prepare(
            "SELECT source_match_id, match_date FROM {$table} WHERE source_match_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ...$match_ids
        );
        $rows = $wpdb->get_results( $sql, ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $result = array();
        foreach ( $rows as $row ) {
            $result[ (string) $row['source_match_id'] ] = (string) ( $row['match_date'] ?? '' );
        }
        return $result;
    }
}
