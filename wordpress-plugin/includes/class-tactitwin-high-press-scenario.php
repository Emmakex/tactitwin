<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_High_Press_Scenario {
    public static function evaluate( array $team_profile, array $opponent_profile, string $language = 'en' ): array {
        $language         = TactiTwin_I18n::normalize_language( $language );
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
            'scenario_name' => TactiTwin_I18n::t( 'scenario.high_press.name', array(), $language ),
            'language' => $language,
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
                    ? TactiTwin_I18n::t( 'coverage.low', array(), $language )
                    : null,
            ),
            'risk' => array(
                'score'      => $risk_score,
                'level'      => self::level( $risk_score ),
                'level_label'=> TactiTwin_I18n::level_label( self::level( $risk_score ), $language ),
                'components' => $components,
            ),
            'opportunity' => array(
                'score'      => $opportunity_score,
                'level'      => self::level( $opportunity_score ),
                'level_label'=> TactiTwin_I18n::level_label( self::level( $opportunity_score ), $language ),
                'components' => $opportunity_components,
            ),
            'evidence' => array(
                'our_build_up_completion_pct'       => (float) ( $team_rates['build_up_completion_pct'] ?? 0 ),
                'our_build_up_escape_rate'           => (float) ( $team_rates['build_up_escape_rate'] ?? 0 ),
                'our_own_third_turnovers_per_match'  => (float) ( $team_avg['own_third_turnovers_per_match'] ?? 0 ),
                'opponent_high_pressures_per_match'  => (float) ( $opp_avg['high_pressures_per_match'] ?? 0 ),
                'opponent_high_recoveries_per_match' => (float) ( $opp_avg['high_recoveries_per_match'] ?? 0 ),
            ),
            'recommendations' => self::recommendations( $risk_score, $opportunity_score, $team_profile, $language ),
            'method' => array(
                'type' => 'explainable-weighted-heuristic',
                'note' => TactiTwin_I18n::t( 'scenario.high_press.method', array(), $language ),
            ),
        );
    }

    private static function recommendations( float $risk, float $opportunity, array $team_profile, string $language ): array {
        $channels          = $team_profile['final_third_channel_share'] ?? array();
        $best_channel      = self::best_channel( $channels );
        $best_channel_name = TactiTwin_I18n::channel_label( $best_channel, $language );

        if ( $risk >= 65.0 ) {
            return array(
                'primary' => TactiTwin_I18n::t( 'high_press.high.primary', array(), $language ),
                'secondary' => TactiTwin_I18n::t( 'high_press.high.secondary', array(), $language ),
                'attacking_outlet' => TactiTwin_I18n::t( 'high_press.high.outlet', array( $best_channel_name ), $language ),
            );
        }

        if ( $risk >= 45.0 ) {
            return array(
                'primary' => TactiTwin_I18n::t( 'high_press.medium.primary', array(), $language ),
                'secondary' => TactiTwin_I18n::t( 'high_press.medium.secondary', array(), $language ),
                'attacking_outlet' => TactiTwin_I18n::t( 'high_press.medium.outlet', array( $best_channel_name ), $language ),
            );
        }

        return array(
            'primary' => TactiTwin_I18n::t( 'high_press.low.primary', array(), $language ),
            'secondary' => $opportunity >= 60.0
                ? TactiTwin_I18n::t( 'high_press.low.secondary_good', array(), $language )
                : TactiTwin_I18n::t( 'high_press.low.secondary_caution', array(), $language ),
            'attacking_outlet' => TactiTwin_I18n::t( 'high_press.low.outlet', array( $best_channel_name ), $language ),
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
