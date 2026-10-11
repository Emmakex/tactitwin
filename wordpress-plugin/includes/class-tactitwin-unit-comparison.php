<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Unit_Comparison {
    public static function interpret( array $comparison, string $language = 'en' ): array {
        $language  = TactiTwin_I18n::normalize_language( $language );
        $delta     = $comparison['delta'] ?? array();
        $candidate = $comparison['candidate'] ?? array();
        $baseline  = $comparison['baseline'] ?? array();
        $changes   = $comparison['changes'] ?? array();

        $recommendations = array();
        self::append_signal( $recommendations, 'connectivity', (float) ( $delta['connectivity'] ?? 0.0 ), 1.5, $language );
        self::append_signal( $recommendations, 'progressive_link', (float) ( $delta['progressive_link'] ?? 0.0 ), 0.75, $language );
        self::append_signal( $recommendations, 'chance_chain', (float) ( $delta['chance_chain'] ?? 0.0 ), 0.08, $language );
        self::append_signal( $recommendations, 'defensive_sequence', (float) ( $delta['defensive_sequence'] ?? 0.0 ), 0.35, $language );

        $low_new = $changes['low_evidence_new_pairs'] ?? array();
        if ( ! empty( $low_new ) ) {
            $recommendations[] = sprintf(
                self::text( 'new_pairs_low', $language ),
                count( $low_new )
            );
        }

        $base_under = (int) ( $baseline['coverage']['under_observed_pairs'] ?? 0 );
        $cand_under = (int) ( $candidate['coverage']['under_observed_pairs'] ?? 0 );
        if ( $cand_under > $base_under ) {
            $recommendations[] = self::text( 'coverage_down', $language );
        }

        if ( empty( $recommendations ) ) {
            $recommendations[] = self::text( 'neutral', $language );
        }

        return array(
            'language' => $language,
            'delta'    => $delta,
            'baseline' => self::public_network( $baseline ),
            'candidate'=> self::public_network( $candidate ),
            'changes'  => $changes,
            'recommendations' => array_values( array_unique( $recommendations ) ),
            'method' => array(
                'type' => 'historical-shared-pitch-unit-interaction-interpretation',
                'note' => self::text( 'method', $language ),
                'limitations' => array(
                    self::text( 'limit_causal', $language ),
                    self::text( 'limit_role', $language ),
                    self::text( 'limit_tracking', $language ),
                ),
            ),
        );
    }

    private static function append_signal( array &$items, string $key, float $delta, float $threshold, string $language ): void {
        if ( $delta >= $threshold ) {
            $items[] = sprintf( self::text( 'gain', $language ), self::label( $key, $language ) );
        } elseif ( $delta <= -$threshold ) {
            $items[] = sprintf( self::text( 'tradeoff', $language ), self::label( $key, $language ) );
        }
    }

    private static function public_network( array $network ): array {
        return array(
            'coverage' => $network['coverage'] ?? array(),
            'signals'  => $network['signals'] ?? array(),
            'leaders'  => $network['leaders'] ?? array(),
            'under_observed_pairs' => $network['under_observed_pairs'] ?? array(),
        );
    }

    private static function label( string $key, string $language ): string {
        return self::text( 'signal_' . $key, $language );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en' => array(
                'gain' => 'Historical unit-interaction gain: %s is stronger across the candidate XI pair network.',
                'tradeoff' => 'Historical unit-interaction trade-off: %s is weaker across the candidate XI pair network.',
                'new_pairs_low' => '%d new candidate pair(s) have fewer than 90 observed shared minutes. Rehearse those connections in training before treating the network delta as stable.',
                'coverage_down' => 'The candidate XI contains more under-observed pair relationships than the baseline. Increase the uncertainty weight of the lineup recommendation.',
                'neutral' => 'The observed pair network is broadly similar between variants; let opponent-specific roles and individual-profile trade-offs decide the change.',
                'signal_connectivity' => 'direct passing connectivity',
                'signal_progressive_link' => 'progressive connection activity',
                'signal_chance_chain' => 'shared chance-chain involvement',
                'signal_defensive_sequence' => 'pressure-to-recovery sequences',
                'method' => 'The unit layer compares historical event interactions observed while each pair shared the pitch. Signals are normalized by shared minutes and are kept separate from individual player-profile and nominal formation evidence.',
                'limit_causal' => 'Observed pair interaction is not causal chemistry and does not prove that the same relationship will persist in a new tactical context.',
                'limit_role' => 'Changing formation or role can materially change passing options and defensive responsibilities even when the same two players remain on the pitch.',
                'limit_tracking' => 'Event data cannot fully measure synchronized movements, spacing, cover shadows or off-ball coordination; tracking/video is required for those interactions.',
            ),
            'es' => array(
                'gain' => 'Ganancia histórica de interacción: mejora %s en la red de parejas del XI candidato.',
                'tradeoff' => 'Coste histórico de interacción: empeora %s en la red de parejas del XI candidato.',
                'new_pairs_low' => '%d pareja(s) nueva(s) del XI candidato tienen menos de 90 minutos compartidos observados. Ensaya esas conexiones antes de considerar estable el delta de red.',
                'coverage_down' => 'El XI candidato contiene más relaciones entre parejas poco observadas que el XI base. Aumenta la incertidumbre de la recomendación de alineación.',
                'neutral' => 'La red observada de parejas es similar entre variantes; deja que los roles específicos contra el rival y los costes/ganancias individuales decidan el cambio.',
                'signal_connectivity' => 'la conectividad directa de pase',
                'signal_progressive_link' => 'la actividad de conexiones progresivas',
                'signal_chance_chain' => 'la participación compartida en cadenas de ocasión',
                'signal_defensive_sequence' => 'las secuencias presión→recuperación',
                'method' => 'La capa de unidades compara interacciones históricas de eventos observadas mientras cada pareja compartía campo. Las señales se normalizan por minutos compartidos y permanecen separadas de los perfiles individuales y de la estructura nominal de la formación.',
                'limit_causal' => 'La interacción observada no es química causal y no demuestra que la misma relación se mantenga en un contexto táctico nuevo.',
                'limit_role' => 'Cambiar de formación o rol puede modificar de forma importante las líneas de pase y responsabilidades defensivas aunque los mismos dos jugadores sigan en campo.',
                'limit_tracking' => 'Los eventos no miden completamente movimientos sincronizados, distancias, sombras de cobertura ni coordinación sin balón; para ello hace falta tracking o vídeo.',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
