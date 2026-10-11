<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Phase_Unit_Comparison {
    public static function interpret( array $comparison, string $language = 'en' ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $delta = $comparison['delta'] ?? array();
        $items = array();

        self::append( $items, 'build_up', (float) ( $delta['build_up'] ?? 0.0 ), 1.5, $language );
        self::append( $items, 'progression', (float) ( $delta['progression'] ?? 0.0 ), 0.75, $language );
        self::append( $items, 'final_third', (float) ( $delta['final_third'] ?? 0.0 ), 0.08, $language );
        self::append( $items, 'high_press', (float) ( $delta['high_press'] ?? 0.0 ), 0.35, $language );

        $candidate_under = (int) ( $comparison['candidate']['coverage']['under_observed_pairs'] ?? 0 );
        $baseline_under  = (int) ( $comparison['baseline']['coverage']['under_observed_pairs'] ?? 0 );
        if ( $candidate_under > $baseline_under ) {
            $items[] = self::text( 'more_unknown', $language );
        }

        $new_low = array_values(
            array_filter(
                $comparison['changes']['gained_pairs'] ?? array(),
                static fn( array $pair ): bool => (float) ( $pair['shared_minutes'] ?? 0.0 ) < 90.0
            )
        );
        if ( ! empty( $new_low ) ) {
            $items[] = sprintf( self::text( 'new_pairs', $language ), count( $new_low ) );
        }

        if ( empty( $items ) ) {
            $items[] = self::text( 'neutral', $language );
        }

        return array(
            'language'=>$language,
            'delta'=>$delta,
            'baseline'=>self::public_network( $comparison['baseline'] ?? array() ),
            'candidate'=>self::public_network( $comparison['candidate'] ?? array() ),
            'changes'=>$comparison['changes'] ?? array(),
            'recommendations'=>array_values( array_unique( $items ) ),
            'method'=>array(
                'type'=>'phase-conditioned-unit-network-interpretation',
                'note'=>self::text( 'method', $language ),
                'limitations'=>array(
                    self::text( 'limit_shape', $language ),
                    self::text( 'limit_causal', $language ),
                ),
            ),
        );
    }

    private static function append( array &$items, string $phase, float $delta, float $threshold, string $language ): void {
        if ( $delta >= $threshold ) {
            $items[] = sprintf( self::text( 'gain', $language ), self::phase_label( $phase, $language ) );
        } elseif ( $delta <= -$threshold ) {
            $items[] = sprintf( self::text( 'tradeoff', $language ), self::phase_label( $phase, $language ) );
        }
    }

    private static function public_network( array $network ): array {
        return array(
            'coverage'=>$network['coverage'] ?? array(),
            'phase_signals'=>$network['phase_signals'] ?? array(),
            'phase_leaders'=>$network['phase_leaders'] ?? array(),
            'under_observed_pairs'=>$network['under_observed_pairs'] ?? array(),
        );
    }

    private static function phase_label( string $phase, string $language ): string {
        return self::text( 'phase_' . $phase, $language );
    }

    private static function text( string $key, string $language ): string {
        $catalog = array(
            'en'=>array(
                'gain'=>'Phase interaction gain: the candidate XI shows stronger observed pair connections in %s.',
                'tradeoff'=>'Phase interaction trade-off: the candidate XI shows weaker observed pair connections in %s.',
                'more_unknown'=>'The candidate XI contains more under-observed pair relationships across the phase network; lower confidence until those units are rehearsed or observed.',
                'new_pairs'=>'%d new pair relationship(s) in the candidate XI have fewer than 90 shared observed minutes.',
                'neutral'=>'Phase-conditioned unit signals are broadly similar; use opponent-specific roles and the individual/formation evidence to decide the XI.',
                'phase_build_up'=>'build-up',
                'phase_progression'=>'progression',
                'phase_final_third'=>'the final third',
                'phase_high_press'=>'high pressing',
                'method'=>'Phase-conditioned interaction signals use explicit event-location rules and historical shared-pitch evidence. They remain separate from nominal formation structure and do not infer tracking-level team shape.',
                'limit_shape'=>'Event locations classify observable actions, not the team’s true block height, line distances or synchronized positional structure.',
                'limit_causal'=>'Historical phase connections do not prove that the candidate XI will reproduce the same behavior under a new opponent, role or tactical instruction.',
            ),
            'es'=>array(
                'gain'=>'Ganancia de interacción por fase: el XI candidato muestra conexiones observadas más fuertes en %s.',
                'tradeoff'=>'Coste de interacción por fase: el XI candidato muestra conexiones observadas más débiles en %s.',
                'more_unknown'=>'El XI candidato contiene más relaciones entre parejas poco observadas en la red por fases; reduce la confianza hasta ensayarlas u observarlas.',
                'new_pairs'=>'%d relación(es) nueva(s) del XI candidato tienen menos de 90 minutos compartidos observados.',
                'neutral'=>'Las señales por fase son similares entre variantes; decide el XI usando los roles contra el rival y la evidencia individual/estructural.',
                'phase_build_up'=>'la salida de balón',
                'phase_progression'=>'la progresión',
                'phase_final_third'=>'el último tercio',
                'phase_high_press'=>'la presión alta',
                'method'=>'Las señales condicionadas por fase usan reglas explícitas de localización de eventos y evidencia histórica de minutos compartidos. Se mantienen separadas de la estructura nominal de la formación y no infieren la forma colectiva propia de un tracking.',
                'limit_shape'=>'Las localizaciones de eventos clasifican acciones observables, no la altura real del bloque, las distancias entre líneas ni la estructura posicional sincronizada.',
                'limit_causal'=>'Las conexiones históricas por fase no demuestran que el XI candidato vaya a reproducir el mismo comportamiento ante otro rival, rol o instrucción táctica.',
            ),
        );
        return $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;
    }
}
