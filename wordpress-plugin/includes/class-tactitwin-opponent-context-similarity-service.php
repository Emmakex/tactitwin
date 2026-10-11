<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Opponent_Context_Similarity_Service {
    public function enrich_library(
        array $library,
        int $current_opponent_id,
        int $profile_limit = 5,
        string $language = 'en'
    ): array {
        if ( $current_opponent_id <= 0 ) {
            throw new InvalidArgumentException( 'A positive current opponent identifier is required.' );
        }
        $language = TactiTwin_I18n::normalize_language( $language );
        $profile_limit = max( 1, min( 10, $profile_limit ) );
        $profiles = new TactiTwin_Profile_Service();
        $historical_profiles = new TactiTwin_Historical_Team_Profile_Service();
        $current = $profiles->team_profile( $current_opponent_id, $profile_limit );
        $cache = array();

        foreach ( $library['groups'] ?? array() as $index => $group ) {
            $library['groups'][ $index ]['opponent_context_similarity'] = $this->group_similarity(
                $group,
                $current,
                $current_opponent_id,
                $historical_profiles,
                $cache,
                $profile_limit,
                $language
            );
        }

        $library['method']['opponent_context_similarity'] = array(
            'attached' => true,
            'type' => 'historical-as-of-opponent-style-context-distance',
            'historical_profile_rule' => 'only imported matches at or before each precedent match date',
            'changes_tactical_scores' => false,
            'changes_recommendations' => false,
            'changes_active_plan' => false,
            'strength_model' => false,
            'note' => 'es' === $language
                ? 'v1.11 compara el rival actual con el perfil que tenía cada rival histórico hasta la fecha del precedente. La similitud queda separada de la calidad v1.10 y no modifica decisiones.'
                : 'v1.11 compares the current opponent with each historical opponent profile reconstructed only up to the precedent date. Similarity stays separate from v1.10 quality and does not alter decisions.',
        );
        return $library;
    }

    private function group_similarity(
        array $group,
        array $current,
        int $current_opponent_id,
        TactiTwin_Historical_Team_Profile_Service $historical_profiles,
        array &$cache,
        int $profile_limit,
        string $language
    ): array {
        $sample = is_array( $group['sample'] ?? null ) ? $group['sample'] : array();
        $cases = $this->signal_cases( $group['cases'] ?? array(), (string) ( $sample['signal_basis'] ?? 'insufficient' ) );

        $rows = array();
        $score_sum = 0.0;
        $available_cases = 0;
        $unavailable_cases = 0;
        foreach ( $cases as $case ) {
            $opponent_id = trim( (string) ( $case['opponent_source_id'] ?? '' ) );
            $as_of_date = trim( (string) ( $case['source_match_date'] ?? '' ) );
            if ( '' === $opponent_id || '' === $as_of_date ) {
                ++$unavailable_cases;
                $rows[] = array(
                    'historical_opponent_source_id' => $opponent_id,
                    'source_match_id' => (string) ( $case['source_match_id'] ?? '' ),
                    'as_of_date' => $as_of_date,
                    'status' => 'unavailable',
                    'reason' => 'historical opponent identifier or source match date is unavailable',
                );
                continue;
            }

            $cache_key = $opponent_id . '|' . $as_of_date . '|' . $profile_limit;
            try {
                if ( ! isset( $cache[ $cache_key ] ) ) {
                    $cache[ $cache_key ] = $historical_profiles->team_profile_as_of(
                        (int) $opponent_id,
                        $as_of_date,
                        $profile_limit
                    );
                }
                $comparison = TactiTwin_Opponent_Context_Distance::compare( $current, $cache[ $cache_key ], $language );
                $score_sum += (float) ( $comparison['similarity_score'] ?? 0.0 );
                ++$available_cases;
                $rows[] = array(
                    'historical_opponent_source_id' => $opponent_id,
                    'source_match_id' => (string) ( $case['source_match_id'] ?? '' ),
                    'as_of_date' => $as_of_date,
                    'status' => 'available',
                    'comparison' => $comparison,
                );
            } catch ( Throwable $e ) {
                ++$unavailable_cases;
                $rows[] = array(
                    'historical_opponent_source_id' => $opponent_id,
                    'source_match_id' => (string) ( $case['source_match_id'] ?? '' ),
                    'as_of_date' => $as_of_date,
                    'status' => 'unavailable',
                    'reason' => $e->getMessage(),
                );
            }
        }

        usort(
            $rows,
            static function( array $a, array $b ): int {
                $a_score = (float) ( $a['comparison']['similarity_score'] ?? -1.0 );
                $b_score = (float) ( $b['comparison']['similarity_score'] ?? -1.0 );
                if ( $a_score !== $b_score ) return $b_score <=> $a_score;
                return strcmp( (string) ( $b['as_of_date'] ?? '' ), (string) ( $a['as_of_date'] ?? '' ) );
            }
        );

        $score = $available_cases > 0 ? round( $score_sum / $available_cases, 1 ) : null;
        return array(
            'status' => $available_cases > 0 ? 'available' : 'unavailable',
            'current_opponent_source_id' => (string) $current_opponent_id,
            'similarity_score' => $score,
            'similarity_level' => null === $score ? 'unavailable' : $this->level( $score ),
            'similarity_label' => null === $score ? $this->text( 'unavailable', $language ) : $this->text( 'level_' . str_replace( '-', '_', $this->level( $score ) ), $language ),
            'signal_cases' => count( $cases ),
            'available_cases' => $available_cases,
            'unavailable_cases' => $unavailable_cases,
            'historical_cases' => $rows,
            'method' => array(
                'aggregation' => 'mean similarity across reconstructable signal cases',
                'historical_profile_rule' => 'as-of source match date; future matches excluded',
                'missing_profile_rule' => 'exclude from similarity denominator and expose as unavailable',
                'strength_model' => false,
                'changes_tactical_scores' => false,
                'changes_recommendations' => false,
            ),
        );
    }

    private function signal_cases( array $cases, string $basis ): array {
        $comparable = array_values( array_filter(
            $cases,
            static fn( array $case ): bool => true === ( $case['context_comparable'] ?? false )
                && in_array( (string) ( $case['interpretability'] ?? '' ), array( 'cleaner', 'confounded' ), true )
        ) );
        $cleaner = array_values( array_filter(
            $comparable,
            static fn( array $case ): bool => 'cleaner' === (string) ( $case['interpretability'] ?? '' )
        ) );
        if ( 'cleaner' === $basis ) return $cleaner;
        if ( 'context-comparable' === $basis ) return $comparable;
        return ! empty( $cleaner ) ? $cleaner : $comparable;
    }

    private function level( float $score ): string {
        if ( $score >= 80.0 ) return 'very-high';
        if ( $score >= 65.0 ) return 'high';
        if ( $score >= 50.0 ) return 'medium';
        if ( $score >= 35.0 ) return 'low';
        return 'very-low';
    }

    private function text( string $key, string $language ): string {
        $labels = array(
            'en' => array(
                'level_very_high'=>'Very high','level_high'=>'High','level_medium'=>'Medium','level_low'=>'Low','level_very_low'=>'Very low','unavailable'=>'Unavailable',
            ),
            'es' => array(
                'level_very_high'=>'Muy alta','level_high'=>'Alta','level_medium'=>'Media','level_low'=>'Baja','level_very_low'=>'Muy baja','unavailable'=>'No disponible',
            ),
        );
        return $labels[ $language ][ $key ] ?? $labels['en'][ $key ] ?? $key;
    }
}
