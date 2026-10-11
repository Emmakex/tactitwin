<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Precedent_Quality_Attachment {
    public static function retrieval(
        array $retrieval,
        array $library,
        string $current_opponent_id,
        string $language = 'en',
        ?string $as_of_utc = null
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $groups = self::group_map( $library );
        $scores = array();

        if ( ! empty( $retrieval['items'] ) && is_array( $retrieval['items'] ) ) {
            foreach ( $retrieval['items'] as &$item ) {
                $key = (string) ( $item['group_key'] ?? '' );
                if ( ! isset( $groups[ $key ] ) ) {
                    continue;
                }
                $item['evidence_quality'] = TactiTwin_Evidence_Quality_Calibrator::calibrate(
                    $groups[ $key ],
                    $current_opponent_id,
                    $language,
                    $as_of_utc
                );
                $scores[] = (float) $item['evidence_quality']['quality_score'];
            }
            unset( $item );
        }

        $retrieval['quality_summary'] = self::summary( $scores, $language );
        if ( ! isset( $retrieval['method'] ) || ! is_array( $retrieval['method'] ) ) {
            $retrieval['method'] = array();
        }
        $retrieval['method']['evidence_quality_calibration'] = array(
            'attached' => true,
            'changes_tactical_scores' => false,
            'changes_recommendations' => false,
            'changes_active_plan' => false,
            'note' => self::text( 'method', $language ),
        );
        return $retrieval;
    }

    public static function catalog(
        array $catalog,
        array $library,
        string $current_opponent_id,
        string $language = 'en',
        ?string $as_of_utc = null
    ): array {
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
                    if ( ! isset( $groups[ $key ] ) ) {
                        continue;
                    }
                    $context['evidence_quality'] = TactiTwin_Evidence_Quality_Calibrator::calibrate(
                        $groups[ $key ],
                        $current_opponent_id,
                        $language,
                        $as_of_utc
                    );
                    $scores[] = (float) $context['evidence_quality']['quality_score'];
                }
                unset( $context );
            }
            unset( $scenario );
        }

        $catalog['quality_summary'] = self::summary( $scores, $language );
        if ( ! isset( $catalog['method'] ) || ! is_array( $catalog['method'] ) ) {
            $catalog['method'] = array();
        }
        $catalog['method']['evidence_quality_calibration'] = array(
            'attached' => true,
            'changes_tactical_scores' => false,
            'changes_recommendations' => false,
            'note' => self::text( 'method', $language ),
        );
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
                'mean_quality_score' => null,
                'highest_quality_score' => null,
                'lowest_quality_score' => null,
                'note' => self::text( 'none', $language ),
            );
        }
        return array(
            'calibrated_items' => count( $scores ),
            'mean_quality_score' => round( array_sum( $scores ) / count( $scores ), 1 ),
            'highest_quality_score' => round( max( $scores ), 1 ),
            'lowest_quality_score' => round( min( $scores ), 1 ),
            'note' => self::text( 'summary', $language ),
        );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'method' => 'v1.10 attaches a descriptive evidence-quality score to historical precedents without changing their tactical meaning, tactical scores, recommendations or active-plan logic.',
                'summary' => 'Historical evidence quality is calibrated independently from tactical relevance.',
                'none' => 'No returned historical precedent could be calibrated.',
            ),
            'es' => array(
                'method' => 'v1.10 adjunta un score descriptivo de calidad de evidencia a los precedentes históricos sin cambiar su significado táctico, scores tácticos, recomendaciones ni lógica del plan activo.',
                'summary' => 'La calidad de la evidencia histórica se calibra de forma independiente de la relevancia táctica.',
                'none' => 'No se pudo calibrar ninguno de los precedentes históricos devueltos.',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
