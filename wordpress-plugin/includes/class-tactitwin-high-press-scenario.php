<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_High_Press_Scenario {
    public static function evaluate( array $team_profile, array $opponent_profile ): array {
        $team_matches     = (int) ( $team_profile['coverage']['matches'] ?? 0 );
        $opponent_matches = (int) ( $opponent_profile['coverage']['matches'] ?? 0 );
        $coverage_matches = min( $team_matches, $opponent_matches );
        $coverage_status  = self::coverage_status( $coverage_matches );

        $team_rates = $team_profile['rates'] ?? array();
        $team_avg   = $team_profile['averages'] ?? array();
        $opp_avg    = $opponent_profile['averages'] ?? array();

        $components = array(
            'opponent_high_press_intensity' => self::normalize( (float) ( $opp_avg['high_pressures_per_match'] ?? 0 ), 18.0 ),
            'opponent_high_recovery_threat' => self::normalize( (float) ( $opp_avg['high_recoveries_per_match'] ?? 0 ), 4.0 ),
            'build_up_pass_failure'          => self::clamp( 100.0 - (float) ( $team_rates['build_up_completion_pct'] ?? 0 ) ),
            'own_third_turnover_exposure'   => self::normalize( (float) ( $team_avg['own_third_turnovers_per_match'] ?? 0 ), 6.0 ),
        );

        $risk_score = round(
            ( $components['opponent_high_press_intensity'] * 0.35 ) +
            ( $components['opponent_high_recovery_threat'] * 0.20 ) +
            ( $components['build_up_pass_failure'] * 0.25 ) +
            ( $components['own_third_turnover_exposure'] * 0.20 ),
            1
        );

        $opportunity_components = array(
            'build_up_escape_rate' => self::clamp( (float) ( $team_rates['build_up_escape_rate'] ?? 0 ) ),
            'build_up_security'    => self::clamp( (float) ( $team_rates['build_up_completion_pct'] ?? 0 ) ),
            'progression_capacity' => self::normalize( (float) ( $team_avg['progressive_actions_per_match'] ?? 0 ), 35.0 ),
        );

        $opportunity_score = round(
            ( $opportunity_components['build_up_escape_rate'] * 0.45 ) +
            ( $opportunity_components['build_up_security'] * 0.30 ) +
            ( $opportunity_components['progression_capacity'] * 0.25 ),
            1
        );

        return array(
            'scenario_id' => 'build-up-vs-high-press',
            'scenario_name' => 'Build-up vs High Press',
            'team' => array(
                'source_id' => (string) ( $team_profile['team_source_id'] ?? '' ),
                'name'      => (string) ( $team_profile['team_name'] ?? '' ),
            ),
            'opponent' => array(
                'source_id' => (string) ( $opponent_profile['team_source_id'] ?? '' ),
                'name'      => (string) ( $opponent_profile['team_name'] ?? '' ),
            ),
            'coverage' => array(
                'team_matches'     => $team_matches,
                'opponent_matches' => $opponent_matches,
                'effective_matches'=> $coverage_matches,
                'status'           => $coverage_status,
                'warning'          => 'low' === $coverage_status
                    ? 'Low evidence coverage: import at least three event-data matches per team before using this scenario for match planning.'
                    : null,
            ),
            'risk' => array(
                'score'      => $risk_score,
                'level'      => self::level( $risk_score ),
                'components' => $components,
            ),
            'opportunity' => array(
                'score'      => $opportunity_score,
                'level'      => self::level( $opportunity_score ),
                'components' => $opportunity_components,
            ),
            'evidence' => array(
                'our_build_up_completion_pct'       => (float) ( $team_rates['build_up_completion_pct'] ?? 0 ),
                'our_build_up_escape_rate'           => (float) ( $team_rates['build_up_escape_rate'] ?? 0 ),
                'our_own_third_turnovers_per_match'  => (float) ( $team_avg['own_third_turnovers_per_match'] ?? 0 ),
                'opponent_high_pressures_per_match'  => (float) ( $opp_avg['high_pressures_per_match'] ?? 0 ),
                'opponent_high_recoveries_per_match' => (float) ( $opp_avg['high_recoveries_per_match'] ?? 0 ),
            ),
            'recommendations' => self::recommendations( $risk_score, $opportunity_score, $team_profile ),
            'method' => array(
                'type' => 'explainable-weighted-heuristic',
                'note' => 'Scores are decision-support heuristics from normalized event data, not probabilities of match outcomes.',
            ),
        );
    }

    private static function recommendations( float $risk, float $opportunity, array $team_profile ): array {
        $channels = $team_profile['final_third_channel_share'] ?? array();
        $best_channel = self::best_channel( $channels );

        if ( $risk >= 65.0 ) {
            return array(
                'primary' => 'Use a mixed build-up: invite the first press selectively, then bypass the first line with a prepared third-man or direct outlet instead of repeating short circulation in the first third.',
                'secondary' => 'Protect the next action after the escape with a stable rest-defence structure; own-third turnover exposure is material in this matchup.',
                'attacking_outlet' => 'After escaping pressure, prioritize progression through the ' . $best_channel . ' channel, currently the strongest final-third route in the imported sample.',
            );
        }

        if ( $risk >= 45.0 ) {
            return array(
                'primary' => 'Keep short build-up available, but predefine an escape trigger when the opponent commits its first pressing line.',
                'secondary' => 'Create an extra support angle around the first receiver and avoid receiving with a closed body orientation near the touchline.',
                'attacking_outlet' => 'Use the ' . $best_channel . ' channel as the preferred next progression route once the press is broken.',
            );
        }

        return array(
            'primary' => 'The imported sample supports controlled short build-up; preserve width and at least one vertical outlet so the opponent cannot lock the first line.',
            'secondary' => $opportunity >= 60.0
                ? 'The current build-up profile shows useful escape capacity, so the priority is maintaining spacing rather than increasing directness.'
                : 'Keep a direct fallback available because the escape rate is not yet strong enough to rely exclusively on short progression.',
            'attacking_outlet' => 'The ' . $best_channel . ' channel is the strongest observed final-third route in the current sample.',
        );
    }

    private static function best_channel( array $channels ): string {
        if ( empty( $channels ) ) {
            return 'central';
        }
        arsort( $channels );
        return (string) array_key_first( $channels );
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
