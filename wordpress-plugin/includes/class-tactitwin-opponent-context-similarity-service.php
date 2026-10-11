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
        $current = $profiles->team_profile( $current_opponent_id, $profile_limit );
        $cache = array( (string) $current_opponent_id => $current );

        foreach ( $library['groups'] ?? array() as $index => $group ) {
            $library['groups'][ $index ]['opponent_context_similarity'] = $this->group_similarity(
                $group,
                $current,
                $current_opponent_id,
                $profiles,
                $cache,
                $profile_limit,
                $language
            );
        }

        $library['method']['opponent_context_similarity'] = array(
            'attached' => true,
            'type' => 'historical-opponent-style-context-distance',
            'changes_tactical_scores' => false,
            'changes_recommendations' => false,
            'changes_active_plan' => false,
            'strength_model' => false,
            'note' => 'es' === $language
                ? 'v1.11 compara el rival actual con los rivales históricos de cada grupo mediante perfiles tácticos derivados de eventos. La similitud queda separada de la calidad v1.10 y no modifica decisiones.'
                : 'v1.11 compares the current opponent with historical opponents in each group using event-derived tactical profiles. Similarity stays separate from v1.10 quality and does not alter decisions.',
        );
        return $library;
    }

    private function group_similarity(
        array $group,
        array $current,
        int $current_opponent_id,
        TactiTwin_Profile_Service $profiles,
        array &$cache,
        int $profile_limit,
        string $language
    ): array {
        $sample = is_array( $group['sample'] ?? null ) ? $group['sample'] : array();
        $cases = $this->signal_cases( $group['cases'] ?? array(), (string) ( $sample['signal_basis'] ?? 'insufficient' ) );
        $counts = array();
        foreach ( $cases as $case ) {
            $id = trim( (string) ( $case['opponent_source_id'] ?? '' ) );
            if ( '' !== $id ) {
                $counts[ $id ] = ( $counts[ $id ] ?? 0 ) + 1;
            }
        }

        $rows = array();
        $weighted = 0.0;
        $available_cases = 0;
        $unavailable_cases = 0;
        foreach ( $counts as $opponent_id => $case_count ) {
            try {
                if ( ! isset( $cache[ $opponent_id ] ) ) {
                    $cache[ $opponent_id ] = $profiles->team_profile( (int) $opponent_id, $profile_limit );
                }
                $comparison = TactiTwin_Opponent_Context_Distance::compare( $current, $cache[ $opponent_id ], $language );
                $score = (float) ( $comparison['similarity_score'] ?? 0.0 );
                $weighted += $score * $case_count;
                $available_cases += $case_count;
                $rows[] = array(
                    'historical_opponent_source_id' => (string) $opponent_id,
                    'case_count' => $case_count,
                    'status' => 'available',
                    'comparison' => $comparison,
                );
            } catch ( Throwable $e ) {
                $unavailable_cases += $case_count;
                $rows[] = array(
                    'historical_opponent_source_id' => (string) $opponent_id,
                    'case_count' => $case_count,
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
                return (int) ( $b['case_count'] ?? 0 ) <=> (int) ( $a['case_count'] ?? 0 );
            }
        );

        $score = $available_cases > 0 ? round( $weighted / $available_cases, 1 ) : null;
        return array(
            'status' => $available_cases > 0 ? 'available' : 'unavailable',
            'current_opponent_source_id' => (string) $current_opponent_id,
            'similarity_score' => $score,
            'similarity_level' => null === $score ? 'unavailable' : $this->level( $score ),
            'similarity_label' => null === $score ? $this->text( 'unavailable', $language ) : $this->text( 'level_' . str_replace( '-', '_', $this->level( $score ) ), $language ),
            'signal_cases' => count( $cases ),
            'available_cases' => $available_cases,
            'unavailable_cases' => $unavailable_cases,
            'historical_opponents' => $rows,
            'method' => array(
                'aggregation' => 'case-count-weighted mean over reconstructable historical opponent profiles',
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
