<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Precedent_Similarity_Attachment {
    public static function retrieval( array $retrieval, array $library, string $language = 'en' ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $groups = self::group_map( $library );
        $scores = array();

        if ( ! empty( $retrieval['items'] ) && is_array( $retrieval['items'] ) ) {
            foreach ( $retrieval['items'] as &$item ) {
                $key = (string) ( $item['group_key'] ?? '' );
                if ( ! isset( $groups[ $key ]['opponent_context_similarity'] ) ) {
                    continue;
                }
                $item['opponent_context_similarity'] = $groups[ $key ]['opponent_context_similarity'];
                $score = $item['opponent_context_similarity']['similarity_score'] ?? null;
                if ( is_numeric( $score ) ) {
                    $scores[] = (float) $score;
                }
            }
            unset( $item );
        }

        $retrieval['opponent_similarity_summary'] = self::summary( $scores, $language );
        if ( ! isset( $retrieval['method'] ) || ! is_array( $retrieval['method'] ) ) {
            $retrieval['method'] = array();
        }
        $retrieval['method']['opponent_context_similarity'] = self::method( $language );
        return $retrieval;
    }

    public static function catalog( array $catalog, array $library, string $language = 'en' ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $groups = self::group_map( $library );
        $scores = array();

        if ( ! empty( $catalog['scenarios'] ) && is_array( $catalog['scenarios'] ) ) {
            foreach ( $catalog['scenarios'] as &$scenario ) {
                if ( empty( $scenario['contexts'] ) || ! is_array( $scenario['contexts'] ) ) {
                    continue;
                }
                foreach ( $scenario['contexts'] as &$context ) {
                    $key = (string) ( $context['group_key'] ?? '' );
                    if ( ! isset( $groups[ $key ]['opponent_context_similarity'] ) ) {
                        continue;
                    }
                    $context['opponent_context_similarity'] = $groups[ $key ]['opponent_context_similarity'];
                    $score = $context['opponent_context_similarity']['similarity_score'] ?? null;
                    if ( is_numeric( $score ) ) {
                        $scores[] = (float) $score;
                    }
                }
                unset( $context );
            }
            unset( $scenario );
        }

        $catalog['opponent_similarity_summary'] = self::summary( $scores, $language );
        if ( ! isset( $catalog['method'] ) || ! is_array( $catalog['method'] ) ) {
            $catalog['method'] = array();
        }
        $catalog['method']['opponent_context_similarity'] = self::method( $language );
        return $catalog;
    }

    private static function group_map( array $library ): array {
        $map = array();
        foreach ( $library['groups'] ?? array() as $group ) {
            $key = (string) ( $group['key'] ?? '' );
            if ( '' !== $key ) {
                $map[ $key ] = $group;
            }
        }
        return $map;
    }

    private static function summary( array $scores, string $language ): array {
        if ( empty( $scores ) ) {
            return array(
                'calibrated_items' => 0,
                'mean_similarity_score' => null,
                'highest_similarity_score' => null,
                'lowest_similarity_score' => null,
                'note' => self::text( 'none', $language ),
            );
        }
        return array(
            'calibrated_items' => count( $scores ),
            'mean_similarity_score' => round( array_sum( $scores ) / count( $scores ), 1 ),
            'highest_similarity_score' => round( max( $scores ), 1 ),
            'lowest_similarity_score' => round( min( $scores ), 1 ),
            'note' => self::text( 'summary', $language ),
        );
    }

    private static function method( string $language ): array {
        return array(
            'attached' => true,
            'separate_from_evidence_quality' => true,
            'changes_tactical_scores' => false,
            'changes_recommendations' => false,
            'changes_active_plan' => false,
            'strength_model' => false,
            'note' => self::text( 'method', $language ),
        );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'method' => 'v1.11 attaches event-derived opponent-style similarity as a separate evidence axis. It does not combine similarity with v1.10 quality or alter tactical decisions.',
                'summary' => 'Opponent-context similarity is shown independently from historical evidence quality.',
                'none' => 'No historical opponent profile could be reconstructed for the returned precedents.',
            ),
            'es' => array(
                'method' => 'v1.11 adjunta similitud de estilo del rival derivada de eventos como un eje de evidencia separado. No combina similitud con la calidad v1.10 ni modifica decisiones tácticas.',
                'summary' => 'La similitud de contexto del rival se muestra de forma independiente de la calidad de evidencia histórica.',
                'none' => 'No se pudo reconstruir ningún perfil de rival histórico para los precedentes devueltos.',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
