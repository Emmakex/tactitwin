<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Historical_Precedent_Retrieval {
    public static function retrieve(
        array $library,
        array $scan,
        array $hypothesis,
        string $language = 'en',
        int $limit = 5
    ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $limit = max( 1, min( 10, $limit ) );

        if ( 'context-matched-intervention-library' !== (string) ( $library['library_id'] ?? '' ) ) {
            throw new InvalidArgumentException( 'Historical precedent retrieval requires a v1.7 intervention library.' );
        }

        $context = array(
            'minute_band' => self::minute_band( (int) ( $hypothesis['minute'] ?? 0 ) ),
            'score_state' => self::score_state( $hypothesis ),
            'manpower_state' => self::manpower_state( $hypothesis ),
        );
        $scenario_map = self::scenario_map( $scan );
        $matches = array();

        foreach ( $library['groups'] ?? array() as $group ) {
            $dimensions = $group['dimensions'] ?? array();
            if (
                (string) ( $dimensions['minute_band'] ?? '' ) !== $context['minute_band']
                || (string) ( $dimensions['score_state'] ?? '' ) !== $context['score_state']
                || (string) ( $dimensions['manpower_state'] ?? '' ) !== $context['manpower_state']
            ) {
                continue;
            }

            $scenario_id = (string) ( $dimensions['scenario_id'] ?? '' );
            $scenario = $scenario_map[ $scenario_id ] ?? array();
            if ( empty( $scenario ) ) {
                continue;
            }

            $sample = $group['sample'] ?? array();
            $signal_count = self::signal_count( $sample );
            $relevance = max(
                (float) ( $scenario['risk_score'] ?? 0.0 ),
                (float) ( $scenario['opportunity_score'] ?? 0.0 )
            );
            $retrieval_score = self::retrieval_score(
                $relevance,
                (string) ( $sample['maturity'] ?? 'insufficient' ),
                $signal_count,
                (int) ( $sample['cleaner'] ?? 0 ),
                (int) ( $sample['context_comparable'] ?? 0 )
            );

            $matches[] = array(
                'group_key' => (string) ( $group['key'] ?? '' ),
                'scenario_id' => $scenario_id,
                'scenario_name' => (string) ( $scenario['scenario_name'] ?? $scenario_id ),
                'to_plan' => (string) ( $dimensions['to_plan'] ?? '' ),
                'to_mode' => (string) ( $dimensions['to_mode'] ?? '' ),
                'minute_band' => (string) ( $dimensions['minute_band'] ?? '' ),
                'score_state' => (string) ( $dimensions['score_state'] ?? '' ),
                'manpower_state' => (string) ( $dimensions['manpower_state'] ?? '' ),
                'scenario_relevance' => round( $relevance, 1 ),
                'current_scan' => array(
                    'risk_score' => round( (float) ( $scenario['risk_score'] ?? 0.0 ), 1 ),
                    'opportunity_score' => round( (float) ( $scenario['opportunity_score'] ?? 0.0 ), 1 ),
                    'rank' => (int) ( $scenario['rank'] ?? 0 ),
                ),
                'retrieval_score' => $retrieval_score,
                'sample' => $sample,
                'metrics' => is_array( $group['metrics'] ?? null ) ? $group['metrics'] : array(),
                'case_count' => count( $group['cases'] ?? array() ),
                'learning_review' => is_array( $group['learning_review'] ?? null ) ? $group['learning_review'] : array(
                    'status' => 'unsigned',
                    'scope' => (string) ( $library['learning_scope'] ?? 'all' ),
                    'signed_cases' => 0,
                    'unsigned_cases' => count( $group['cases'] ?? array() ),
                    'invalid_cases' => 0,
                    'total_cases' => count( $group['cases'] ?? array() ),
                    'signed_sessions' => 0,
                    'latest_signed_revision' => 0,
                    'affects_metrics' => false,
                    'affects_ranking' => false,
                ),
            );
        }

        usort(
            $matches,
            static function( array $a, array $b ): int {
                $score = (float) ( $b['retrieval_score'] ?? 0.0 ) <=> (float) ( $a['retrieval_score'] ?? 0.0 );
                if ( 0 !== $score ) return $score;
                $relevance = (float) ( $b['scenario_relevance'] ?? 0.0 ) <=> (float) ( $a['scenario_relevance'] ?? 0.0 );
                if ( 0 !== $relevance ) return $relevance;
                return strcmp( (string) ( $a['group_key'] ?? '' ), (string) ( $b['group_key'] ?? '' ) );
            }
        );

        $total = count( $matches );
        $items = array_slice( $matches, 0, $limit );
        $status = $total > 0 ? 'available' : 'no-match';

        return array(
            'precedent_id' => 'historical-precedent-retrieval',
            'status' => $status,
            'language' => $language,
            'context' => $context,
            'matched_group_count' => $total,
            'returned_group_count' => count( $items ),
            'items' => $items,
            'learning_review_coverage' => TactiTwin_Intervention_Library_Service::precedent_learning_coverage( $items ),
            'summary' => self::summary( $items, $context, $language ),
            'method' => array(
                'type' => 'exact-context-historical-precedent-retrieval',
                'exact_context_dimensions' => array( 'minute band', 'score state', 'manpower state' ),
                'ranking' => 'current scenario relevance + historical sample maturity/volume/cleaner share',
                'signed_review_provenance_exposed' => true,
                'signed_review_affects_retrieval_score' => false,
                'signed_review_affects_default_order' => false,
                'reweights_tactical_scores' => false,
                'changes_recommendations' => false,
                'note' => self::text( 'method', $language ),
                'limitations' => array(
                    self::text( 'limit_causality', $language ),
                    self::text( 'limit_exact', $language ),
                    self::text( 'limit_context', $language ),
                    self::text( 'limit_signoff', $language ),
                ),
            ),
        );
    }

    private static function scenario_map( array $scan ): array {
        $map = array();
        foreach ( $scan['evidence']['scenario_ranking'] ?? array() as $row ) {
            $id = (string) ( $row['scenario_id'] ?? '' );
            if ( '' === $id ) continue;
            $map[ $id ] = array(
                'scenario_name' => (string) ( $row['scenario_name'] ?? $id ),
                'risk_score' => (float) ( $row['risk_score'] ?? 0.0 ),
                'opportunity_score' => (float) ( $row['opportunity_score'] ?? 0.0 ),
                'rank' => (int) ( $row['rank'] ?? 0 ),
            );
        }
        return $map;
    }

    private static function retrieval_score( float $relevance, string $maturity, int $signal_count, int $cleaner, int $comparable ): float {
        $maturity_points = array(
            'insufficient' => 0.0,
            'emerging' => 35.0,
            'developing' => 70.0,
            'established' => 100.0,
        );
        $maturity_score = (float) ( $maturity_points[ $maturity ] ?? 0.0 );
        $volume_score = min( 100.0, $signal_count * 16.67 );
        $cleaner_share = $comparable > 0 ? min( 100.0, ( $cleaner / $comparable ) * 100.0 ) : 0.0;
        return round(
            ( min( 100.0, max( 0.0, $relevance ) ) * 0.60 )
            + ( $maturity_score * 0.20 )
            + ( $volume_score * 0.10 )
            + ( $cleaner_share * 0.10 ),
            1
        );
    }

    private static function signal_count( array $sample ): int {
        $basis = (string) ( $sample['signal_basis'] ?? 'insufficient' );
        if ( 'cleaner' === $basis ) return (int) ( $sample['cleaner'] ?? 0 );
        if ( 'context-comparable' === $basis ) return (int) ( $sample['context_comparable'] ?? 0 );
        return min( 1, (int) ( $sample['context_comparable'] ?? 0 ) );
    }

    private static function minute_band( int $minute ): string {
        $minute = max( 1, min( 120, $minute ) );
        if ( $minute <= 30 ) return '01-30';
        if ( $minute <= 60 ) return '31-60';
        if ( $minute <= 90 ) return '61-90';
        return '91-120';
    }

    private static function score_state( array $hypothesis ): string {
        $existing = (string) ( $hypothesis['state'] ?? '' );
        if ( in_array( $existing, array( 'leading', 'drawing', 'trailing' ), true ) ) return $existing;
        $ours = (int) ( $hypothesis['our_score'] ?? 0 );
        $theirs = (int) ( $hypothesis['opponent_score'] ?? 0 );
        if ( $ours > $theirs ) return 'leading';
        if ( $ours < $theirs ) return 'trailing';
        return 'drawing';
    }

    private static function manpower_state( array $hypothesis ): string {
        $existing = (string) ( $hypothesis['manpower_state'] ?? '' );
        if ( in_array( $existing, array( 'superior', 'equal', 'inferior' ), true ) ) return $existing;
        $ours = (int) ( $hypothesis['our_players'] ?? 11 );
        $theirs = (int) ( $hypothesis['opponent_players'] ?? 11 );
        if ( $ours > $theirs ) return 'superior';
        if ( $ours < $theirs ) return 'inferior';
        return 'equal';
    }

    private static function summary( array $items, array $context, string $language ): array {
        if ( empty( $items ) ) {
            return array(
                sprintf(
                    self::text( 'summary_none', $language ),
                    $context['minute_band'],
                    self::state_label( $context['score_state'], $language ),
                    self::manpower_label( $context['manpower_state'], $language )
                ),
            );
        }

        $top = $items[0];
        return array(
            sprintf( self::text( 'summary_count', $language ), count( $items ) ),
            sprintf(
                self::text( 'summary_top', $language ),
                (string) $top['scenario_name'],
                (string) ( $top['sample']['maturity_label'] ?? $top['sample']['maturity'] ?? '' ),
                (float) ( $top['scenario_relevance'] ?? 0.0 )
            ),
        );
    }

    private static function state_label( string $state, string $language ): string {
        $key = 'state_' . $state;
        return self::text( $key, $language );
    }

    private static function manpower_label( string $state, string $language ): string {
        return self::text( 'manpower_' . $state, $language );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'method' => 'v1.8 retrieves v1.7 intervention groups only when the current What-if minute band, score state and manpower state match exactly. Current matchup-scan relevance and historical sample quality order the precedents. v1.26 adds signed-review provenance without changing that ranking.',
                'limit_causality' => 'Historical precedents remain observational associations and do not prove intervention effects.',
                'limit_exact' => 'Exact matching can return no precedents even when nearby contexts exist; v1.8 deliberately avoids fuzzy fallback.',
                'limit_context' => 'Opponent quality, fatigue, exact scoreline, injuries and unobserved tactical structure can still differ across matched precedents.',
                'limit_signoff' => 'A staff-signed review confirms workflow review and provenance; it is not evidence that the intervention was effective or causal.',
                'summary_none' => 'No exact historical precedents were found for minute band %s, %s score state and %s manpower state.',
                'summary_count' => '%d exact-context historical precedents are available for this What-if.',
                'summary_top' => 'Highest-ranked precedent: %s (%s; current scenario relevance %.1f/100).',
                'state_leading' => 'leading', 'state_drawing' => 'drawing', 'state_trailing' => 'trailing',
                'manpower_superior' => 'superior', 'manpower_equal' => 'equal', 'manpower_inferior' => 'inferior',
            ),
            'es' => array(
                'method' => 'v1.8 recupera grupos de intervenciones v1.7 solo cuando coinciden exactamente la franja temporal, el estado del marcador y el estado numérico del What-if actual. La relevancia del matchup actual y la calidad de la muestra histórica ordenan los precedentes. v1.26 añade procedencia de revisión firmada sin cambiar ese ranking.',
                'limit_causality' => 'Los precedentes históricos siguen siendo asociaciones observacionales y no demuestran efectos de la intervención.',
                'limit_exact' => 'La coincidencia exacta puede no devolver precedentes aunque existan contextos cercanos; v1.8 evita deliberadamente el fallback difuso.',
                'limit_context' => 'La calidad del rival, fatiga, marcador exacto, lesiones y estructura táctica no observada todavía pueden diferir entre precedentes comparables.',
                'limit_signoff' => 'Una revisión firmada por el staff confirma revisión del flujo y procedencia; no demuestra que la intervención fuera eficaz ni causal.',
                'summary_none' => 'No se encontraron precedentes históricos exactos para la franja %s, estado de marcador %s y estado numérico %s.',
                'summary_count' => 'Hay %d precedentes históricos de contexto exacto para este What-if.',
                'summary_top' => 'Precedente mejor posicionado: %s (%s; relevancia del escenario actual %.1f/100).',
                'state_leading' => 'ganando', 'state_drawing' => 'empatando', 'state_trailing' => 'perdiendo',
                'manpower_superior' => 'superior', 'manpower_equal' => 'igual', 'manpower_inferior' => 'inferior',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
