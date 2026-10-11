<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Defensive_Transition_Scenario {
    public static function evaluate( array $team_profile, array $opponent_profile ): array {
        $team_matches     = (int) ( $team_profile['coverage']['matches'] ?? 0 );
        $opponent_matches = (int) ( $opponent_profile['coverage']['matches'] ?? 0 );
        $effective        = min( $team_matches, $opponent_matches );

        $rates       = $team_profile['rates'] ?? array();
        $team_avg    = $team_profile['averages'] ?? array();
        $opponent_avg = $opponent_profile['averages'] ?? array();
        $zones       = $team_profile['turnover_zone_share'] ?? array();

        $risk_components = array(
            'dangerous_turnover_rate' => self::normalize( (float) ( $rates['dangerous_turnover_rate'] ?? 0 ), 12.0 ),
            'progression_after_loss'   => self::normalize( (float) ( $rates['transition_progression_rate'] ?? 0 ), 30.0 ),
            'counterpress_failure'     => self::clamp( 100.0 - (float) ( $rates['counterpress_recovery_rate'] ?? 0 ) ),
            'opponent_transition_shots'=> self::normalize( (float) ( $opponent_avg['transition_shots_created_within_10s_per_match'] ?? 0 ), 2.0 ),
            'opponent_transition_xg'   => self::normalize( (float) ( $opponent_avg['transition_xg_created_within_10s_per_match'] ?? 0 ), 0.50 ),
        );

        $risk_score = round(
            ( $risk_components['dangerous_turnover_rate'] * 0.25 ) +
            ( $risk_components['progression_after_loss'] * 0.20 ) +
            ( $risk_components['counterpress_failure'] * 0.20 ) +
            ( $risk_components['opponent_transition_shots'] * 0.20 ) +
            ( $risk_components['opponent_transition_xg'] * 0.15 ),
            1
        );

        $opportunity_components = array(
            'counterpress_recovery'   => self::clamp( (float) ( $rates['counterpress_recovery_rate'] ?? 0 ) ),
            'final_third_loss_share'  => self::clamp( (float) ( $zones['final_third'] ?? 0 ) ),
            'avoid_own_third_losses'  => self::clamp( 100.0 - (float) ( $zones['own_third'] ?? 0 ) ),
        );

        $opportunity_score = round(
            ( $opportunity_components['counterpress_recovery'] * 0.60 ) +
            ( $opportunity_components['final_third_loss_share'] * 0.25 ) +
            ( $opportunity_components['avoid_own_third_losses'] * 0.15 ),
            1
        );

        $dominant_loss_zone = self::max_key(
            array(
                'own_third'    => (float) ( $zones['own_third'] ?? 0 ),
                'middle_third' => (float) ( $zones['middle_third'] ?? 0 ),
                'final_third'  => (float) ( $zones['final_third'] ?? 0 ),
            )
        );

        return array(
            'scenario_id'   => 'defensive-transition-after-loss',
            'scenario_name' => 'Defensive Transition After Loss',
            'team' => array(
                'source_id' => (string) ( $team_profile['team_source_id'] ?? '' ),
                'name'      => (string) ( $team_profile['team_name'] ?? '' ),
            ),
            'opponent' => array(
                'source_id' => (string) ( $opponent_profile['team_source_id'] ?? '' ),
                'name'      => (string) ( $opponent_profile['team_name'] ?? '' ),
            ),
            'coverage' => array(
                'team_matches'      => $team_matches,
                'opponent_matches'  => $opponent_matches,
                'effective_matches' => $effective,
                'status'            => self::coverage_status( $effective ),
                'warning'           => $effective < 3
                    ? 'Low evidence coverage: import at least three event-data matches per team before using this scenario for match planning.'
                    : null,
            ),
            'risk' => array(
                'score'      => $risk_score,
                'level'      => self::level( $risk_score ),
                'components' => $risk_components,
            ),
            'opportunity' => array(
                'score'      => $opportunity_score,
                'level'      => self::level( $opportunity_score ),
                'components' => $opportunity_components,
            ),
            'evidence' => array(
                'our_turnovers_per_match'                  => (float) ( $team_avg['transition_turnovers_per_match'] ?? 0 ),
                'our_dangerous_turnover_rate'              => (float) ( $rates['dangerous_turnover_rate'] ?? 0 ),
                'our_transition_progression_rate'          => (float) ( $rates['transition_progression_rate'] ?? 0 ),
                'our_counterpress_recovery_rate'           => (float) ( $rates['counterpress_recovery_rate'] ?? 0 ),
                'our_turnover_zone_share'                  => $zones,
                'dominant_loss_zone'                       => $dominant_loss_zone,
                'opponent_transition_progressions_per_match'=> (float) ( $opponent_avg['transition_progressions_created_within_10s_per_match'] ?? 0 ),
                'opponent_transition_shots_per_match'      => (float) ( $opponent_avg['transition_shots_created_within_10s_per_match'] ?? 0 ),
                'opponent_transition_xg_per_match'         => (float) ( $opponent_avg['transition_xg_created_within_10s_per_match'] ?? 0 ),
            ),
            'recommendations' => self::recommendations(
                $risk_score,
                (float) ( $rates['counterpress_recovery_rate'] ?? 0 ),
                $dominant_loss_zone,
                (float) ( $zones['own_third'] ?? 0 ),
                (float) ( $opponent_avg['transition_shots_created_within_10s_per_match'] ?? 0 ),
                (float) ( $opponent_avg['transition_xg_created_within_10s_per_match'] ?? 0 )
            ),
            'method' => array(
                'type' => 'temporal-transition-window-heuristic',
                'windows' => array(
                    'opponent_threat_seconds' => 10,
                    'counterpress_recovery_seconds' => 8,
                ),
                'note' => 'The model analyzes normalized event sequences immediately after turnovers. Scores support tactical preparation and are not probabilities of conceding a goal.',
            ),
        );
    }

    private static function recommendations(
        float $risk,
        float $counterpress_rate,
        string $loss_zone,
        float $own_third_share,
        float $opponent_transition_shots,
        float $opponent_transition_xg
    ): array {
        $recommendations = array();

        if ( $risk >= 65.0 ) {
            $recommendations['primary'] = 'Prioritize rest-defence before adding another player to the attack: keep a stable central cover structure behind the ball and avoid simultaneous forward movements that leave the first counterattack pass unprotected.';
        } elseif ( $risk >= 45.0 ) {
            $recommendations['primary'] = 'Use conditional attacking freedom: allow extra numbers forward only when central cover and the far-side defender are positioned to control the first transition action.';
        } else {
            $recommendations['primary'] = 'The recent sample shows manageable transition exposure; preserve the current rest-defence spacing and keep the nearest players ready to react immediately after loss.';
        }

        $recommendations['counterpress'] = $counterpress_rate < 25.0
            ? 'Train an explicit 5–8 second reaction after loss: nearest player pressures the ball, second player blocks the first forward lane and the rest recover goal-side instead of all attacking the ball.'
            : 'The counterpress recovery rate is useful enough to keep an aggressive immediate reaction, but only when the nearest players have compact distances around the loss.';

        $recommendations['loss_zone'] = 'The most frequent loss zone in the imported sample is ' . str_replace( '_', ' ', $loss_zone ) . '; rehearse the first defensive movements from that exact field position rather than treating every turnover identically.';

        if ( $own_third_share >= 25.0 ) {
            $recommendations['build_up_protection'] = 'A material share of losses occurs in our own third. Reduce unsupported central risk there and ensure the receiver has a backward or wide safety option before the pass is played.';
        }

        if ( $opponent_transition_shots >= 1.0 || $opponent_transition_xg >= 0.25 ) {
            $recommendations['opponent_threat'] = 'The opponent creates meaningful immediate threat after rival turnovers. Deny the first forward action, protect the central lane first and force the transition toward a slower or wider route.';
        } else {
            $recommendations['opponent_threat'] = 'The opponent transition sample is not highly shot-heavy, so avoid overreacting: protect central access while keeping enough structure to regain possession rather than dropping automatically.';
        }

        return $recommendations;
    }

    private static function max_key( array $values ): string {
        arsort( $values );
        return (string) array_key_first( $values );
    }

    private static function normalize( float $value, float $reference ): float {
        return $reference > 0 ? self::clamp( ( $value / $reference ) * 100.0 ) : 0.0;
    }

    private static function clamp( float $value ): float {
        return max( 0.0, min( 100.0, round( $value, 1 ) ) );
    }

    private static function level( float $score ): string {
        if ( $score >= 65.0 ) {
            return 'high';
        }
        if ( $score >= 45.0 ) {
            return 'medium';
        }
        return 'low';
    }

    private static function coverage_status( int $matches ): string {
        if ( $matches >= 5 ) {
            return 'high';
        }
        if ( $matches >= 3 ) {
            return 'medium';
        }
        return 'low';
    }
}
