<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_I18n {
    public const DEFAULT_LANGUAGE = 'en';
    public const SUPPORTED_LANGUAGES = array( 'en', 'es' );

    public static function register(): void {
        add_action(
            'init',
            static function (): void {
                if ( function_exists( 'load_plugin_textdomain' ) ) {
                    load_plugin_textdomain(
                        'tactitwin',
                        false,
                        dirname( plugin_basename( TACTITWIN_FILE ) ) . '/languages'
                    );
                }
            }
        );
    }

    public static function normalize_language( ?string $language ): string {
        $language = strtolower( trim( (string) $language ) );
        $language = str_replace( '-', '_', $language );
        $base = '' !== $language ? explode( '_', $language )[0] : '';

        return in_array( $base, self::SUPPORTED_LANGUAGES, true ) ? $base : self::DEFAULT_LANGUAGE;
    }

    public static function current_language(): string {
        if ( function_exists( 'determine_locale' ) ) {
            return self::normalize_language( determine_locale() );
        }
        if ( function_exists( 'get_locale' ) ) {
            return self::normalize_language( get_locale() );
        }

        return self::DEFAULT_LANGUAGE;
    }

    public static function supported(): array {
        return self::SUPPORTED_LANGUAGES;
    }

    public static function t( string $key, array $args = array(), ?string $language = null ): string {
        $language = self::normalize_language( $language ?? self::current_language() );
        $catalog  = self::catalog();
        $template = $catalog[ $language ][ $key ] ?? $catalog['en'][ $key ] ?? $key;

        if ( empty( $args ) ) {
            return $template;
        }

        return vsprintf( $template, array_values( $args ) );
    }

    public static function channel_label( string $channel, ?string $language = null ): string {
        $key = 'channel.' . $channel;
        return self::t( $key, array(), $language );
    }

    public static function zone_label( string $zone, ?string $language = null ): string {
        $key = 'zone.' . $zone;
        return self::t( $key, array(), $language );
    }

    public static function level_label( string $level, ?string $language = null ): string {
        return self::t( 'level.' . $level, array(), $language );
    }

    private static function catalog(): array {
        return array(
            'en' => array(
                'language.en' => 'English',
                'language.es' => 'Spanish',
                'level.high' => 'High',
                'level.medium' => 'Medium',
                'level.low' => 'Low',
                'channel.left' => 'left',
                'channel.central' => 'central',
                'channel.right' => 'right',
                'zone.own_third' => 'own third',
                'zone.middle_third' => 'middle third',
                'zone.final_third' => 'final third',
                'coverage.low' => 'Low evidence coverage: import at least three event-data matches per team before using this scenario for match planning.',
                'coverage.full_scan_low' => 'Low evidence coverage: import at least three event-data matches per team before using the full scan for match planning.',
                'scenario.high_press.name' => 'Build-up vs High Press',
                'scenario.side_bias.name' => 'Opponent Side Bias',
                'scenario.transition.name' => 'Defensive Transition After Loss',
                'scenario.full_scan.name' => 'Full Matchup Scan',
                'scenario.high_press.method' => 'Scores are decision-support heuristics from normalized event data, not probabilities of match outcomes.',
                'scenario.side_bias.method' => 'Channel scores combine the opponent attack distribution with our observed concession distribution; they are tactical decision-support signals, not outcome probabilities.',
                'scenario.transition.method' => 'The model analyzes normalized event sequences immediately after turnovers. Scores support tactical preparation and are not probabilities of conceding a goal.',
                'scenario.full_scan.method' => 'The headline risk is the highest current scenario risk; average risk and opportunity summarize the full scan. Each underlying scenario remains independently explainable.',
                'high_press.high.primary' => 'Use a mixed build-up: invite the first press selectively, then bypass the first line with a prepared third-man or direct outlet instead of repeating short circulation in the first third.',
                'high_press.high.secondary' => 'Protect the next action after the escape with a stable rest-defence structure; own-third turnover exposure is material in this matchup.',
                'high_press.high.outlet' => 'After escaping pressure, prioritize progression through the %s channel, currently the strongest final-third route in the imported sample.',
                'high_press.medium.primary' => 'Keep short build-up available, but predefine an escape trigger when the opponent commits its first pressing line.',
                'high_press.medium.secondary' => 'Create an extra support angle around the first receiver and avoid receiving with a closed body orientation near the touchline.',
                'high_press.medium.outlet' => 'Use the %s channel as the preferred next progression route once the press is broken.',
                'high_press.low.primary' => 'The imported sample supports controlled short build-up; preserve width and at least one vertical outlet so the opponent cannot lock the first line.',
                'high_press.low.secondary_good' => 'The current build-up profile shows useful escape capacity, so the priority is maintaining spacing rather than increasing directness.',
                'high_press.low.secondary_caution' => 'Keep a direct fallback available because the escape rate is not yet strong enough to rely exclusively on short progression.',
                'high_press.low.outlet' => 'The %s channel is the strongest observed final-third route in the current sample.',
                'side_bias.primary' => 'Prioritize protection of our %s defensive channel: the opponent sends %.1f%% of its observed final-third actions through its %s channel, while %.1f%% of the final-third actions we concede arrive through that same matchup channel.',
                'side_bias.overlap' => ' This is a direct overlap between their strongest route and our most exposed channel.',
                'side_bias.pressing' => 'Orient the first defensive pressure to discourage the opponent %s channel and make circulation continue toward its %s channel, the least-used route in the imported sample.',
                'side_bias.cover' => 'Keep the near-side full-back/wing-back protected by an inside midfielder and preserve cover behind the first duel instead of allowing an isolated wide 1v1.',
                'side_bias.training' => 'Rehearse defensive shifting toward our %s side, then train the transition after recovery from that zone so the opponent cannot immediately counter-press the same corridor.',
                'side_bias.secondary_other' => 'Our historically most exposed channel is %s, so do not over-shift so aggressively that this secondary weakness becomes open.',
                'side_bias.secondary_overlap' => 'The opponent preference and our defensive exposure align, so this channel should receive the clearest match-plan priority.',
                'transition.high.primary' => 'Prioritize rest-defence before adding another player to the attack: keep a stable central cover structure behind the ball and avoid simultaneous forward movements that leave the first counterattack pass unprotected.',
                'transition.medium.primary' => 'Use conditional attacking freedom: allow extra numbers forward only when central cover and the far-side defender are positioned to control the first transition action.',
                'transition.low.primary' => 'The recent sample shows manageable transition exposure; preserve the current rest-defence spacing and keep the nearest players ready to react immediately after loss.',
                'transition.counterpress_low' => 'Train an explicit 5–8 second reaction after loss: nearest player pressures the ball, second player blocks the first forward lane and the rest recover goal-side instead of all attacking the ball.',
                'transition.counterpress_good' => 'The counterpress recovery rate is useful enough to keep an aggressive immediate reaction, but only when the nearest players have compact distances around the loss.',
                'transition.loss_zone' => 'The most frequent loss zone in the imported sample is %s; rehearse the first defensive movements from that exact field position rather than treating every turnover identically.',
                'transition.build_up_protection' => 'A material share of losses occurs in our own third. Reduce unsupported central risk there and ensure the receiver has a backward or wide safety option before the pass is played.',
                'transition.opponent_threat_high' => 'The opponent creates meaningful immediate threat after rival turnovers. Deny the first forward action, protect the central lane first and force the transition toward a slower or wider route.',
                'transition.opponent_threat_low' => 'The opponent transition sample is not highly shot-heavy, so avoid overreacting: protect central access while keeping enough structure to regain possession rather than dropping automatically.',
            ),
            'es' => array(
                'language.en' => 'Inglés',
                'language.es' => 'Español',
                'level.high' => 'Alto',
                'level.medium' => 'Medio',
                'level.low' => 'Bajo',
                'channel.left' => 'izquierdo',
                'channel.central' => 'central',
                'channel.right' => 'derecho',
                'zone.own_third' => 'tercio propio',
                'zone.middle_third' => 'tercio medio',
                'zone.final_third' => 'último tercio',
                'coverage.low' => 'Cobertura de evidencia baja: importa al menos tres partidos con datos de eventos por equipo antes de utilizar este escenario para preparar el partido.',
                'coverage.full_scan_low' => 'Cobertura de evidencia baja: importa al menos tres partidos con datos de eventos por equipo antes de utilizar el análisis completo para preparar el partido.',
                'scenario.high_press.name' => 'Salida de balón vs presión alta',
                'scenario.side_bias.name' => 'Sesgo lateral del rival',
                'scenario.transition.name' => 'Transición defensiva tras pérdida',
                'scenario.full_scan.name' => 'Análisis completo del enfrentamiento',
                'scenario.high_press.method' => 'Las puntuaciones son heurísticas explicables de apoyo a la decisión basadas en datos de eventos normalizados; no son probabilidades del resultado del partido.',
                'scenario.side_bias.method' => 'Las puntuaciones por canal combinan la distribución ofensiva del rival con nuestra distribución observada de concesiones; son señales de apoyo táctico, no probabilidades de resultado.',
                'scenario.transition.method' => 'El modelo analiza secuencias de eventos normalizados inmediatamente después de las pérdidas. Las puntuaciones sirven para preparar el partido y no representan probabilidades de encajar un gol.',
                'scenario.full_scan.method' => 'El riesgo principal corresponde al escenario con mayor riesgo actual; el riesgo y la oportunidad medios resumen el análisis completo. Cada escenario subyacente mantiene su explicación y evidencia independiente.',
                'high_press.high.primary' => 'Utiliza una salida mixta: atrae selectivamente la primera presión y supera después la primera línea con un tercer hombre preparado o una salida directa, evitando repetir circulación corta en el primer tercio.',
                'high_press.high.secondary' => 'Protege la acción siguiente después de superar la presión con una estructura estable de defensa preventiva; la exposición a pérdidas en campo propio es relevante en este enfrentamiento.',
                'high_press.high.outlet' => 'Después de superar la presión, prioriza la progresión por el canal %s, actualmente la ruta más fuerte hacia el último tercio en la muestra importada.',
                'high_press.medium.primary' => 'Mantén disponible la salida corta, pero define previamente un disparador de escape cuando el rival comprometa su primera línea de presión.',
                'high_press.medium.secondary' => 'Crea un ángulo adicional de apoyo alrededor del primer receptor y evita recibir con orientación corporal cerrada cerca de la banda.',
                'high_press.medium.outlet' => 'Utiliza el canal %s como ruta preferente de progresión una vez superada la presión.',
                'high_press.low.primary' => 'La muestra importada respalda una salida corta controlada; conserva amplitud y al menos una salida vertical para impedir que el rival bloquee la primera línea.',
                'high_press.low.secondary_good' => 'El perfil actual de salida muestra una capacidad útil para superar la presión, por lo que la prioridad es mantener los espacios antes que aumentar la verticalidad.',
                'high_press.low.secondary_caution' => 'Mantén una alternativa directa porque la tasa de escape todavía no es suficientemente alta como para depender exclusivamente de la progresión corta.',
                'high_press.low.outlet' => 'El canal %s es la ruta observada más fuerte hacia el último tercio en la muestra actual.',
                'side_bias.primary' => 'Prioriza la protección de nuestro canal defensivo %s: el rival dirige el %.1f%% de sus acciones observadas en último tercio por su canal %s, mientras que el %.1f%% de las acciones de último tercio que concedemos llegan por ese mismo emparejamiento de canal.',
                'side_bias.overlap' => ' Existe una coincidencia directa entre su ruta más fuerte y nuestro canal más expuesto.',
                'side_bias.pressing' => 'Orienta la primera presión defensiva para desincentivar el canal %s del rival y llevar la circulación hacia su canal %s, la ruta menos utilizada en la muestra importada.',
                'side_bias.cover' => 'Protege al lateral/carrilero del lado cercano con un centrocampista interior y conserva cobertura detrás del primer duelo, evitando dejar un 1 contra 1 aislado en banda.',
                'side_bias.training' => 'Ensaya el desplazamiento defensivo hacia nuestro lado %s y entrena después la transición tras recuperar en esa zona para impedir que el rival contrapresione inmediatamente el mismo corredor.',
                'side_bias.secondary_other' => 'Nuestro canal históricamente más expuesto es el %s, por lo que no debemos bascular tanto como para abrir esta debilidad secundaria.',
                'side_bias.secondary_overlap' => 'La preferencia del rival coincide con nuestra exposición defensiva, por lo que este canal debe recibir la prioridad más clara en el plan de partido.',
                'transition.high.primary' => 'Prioriza la defensa preventiva antes de añadir otro jugador al ataque: conserva una estructura central estable de cobertura detrás del balón y evita movimientos simultáneos hacia delante que dejen libre el primer pase de contraataque.',
                'transition.medium.primary' => 'Utiliza libertad ofensiva condicionada: permite incorporar más jugadores solo cuando la cobertura central y el defensor del lado débil estén colocados para controlar la primera acción de transición.',
                'transition.low.primary' => 'La muestra reciente presenta una exposición manejable en transición; conserva las distancias actuales de defensa preventiva y mantén a los jugadores más cercanos preparados para reaccionar inmediatamente tras la pérdida.',
                'transition.counterpress_low' => 'Entrena una reacción explícita de 5–8 segundos tras pérdida: el jugador más cercano presiona balón, el segundo bloquea la primera línea de pase hacia delante y el resto recupera posición por detrás del balón en lugar de acudir todos al poseedor.',
                'transition.counterpress_good' => 'La tasa de recuperación mediante contrapresión permite mantener una reacción inmediata agresiva, pero solo cuando los jugadores próximos tienen distancias compactas alrededor de la pérdida.',
                'transition.loss_zone' => 'La zona de pérdida más frecuente en la muestra importada es el %s; ensaya los primeros movimientos defensivos desde esa posición exacta del campo en lugar de tratar todas las pérdidas de la misma forma.',
                'transition.build_up_protection' => 'Una proporción relevante de las pérdidas ocurre en nuestro tercio propio. Reduce el riesgo central sin apoyos y garantiza que el receptor tenga una opción de seguridad hacia atrás o hacia banda antes de jugar el pase.',
                'transition.opponent_threat_high' => 'El rival genera una amenaza inmediata relevante después de pérdidas del adversario. Niega la primera acción hacia delante, protege primero el carril central y fuerza la transición hacia una ruta más lenta o exterior.',
                'transition.opponent_threat_low' => 'La muestra de transiciones del rival no presenta un volumen alto de tiros, por lo que conviene no sobrerreaccionar: protege el acceso central manteniendo suficiente estructura para recuperar la posesión en lugar de replegar automáticamente.',
            ),
        );
    }
}
