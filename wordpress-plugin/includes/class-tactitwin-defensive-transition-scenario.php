<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Defensive_Transition_Scenario {
    public static function evaluate( array $team_profile, array $opponent_profile, string $language = 'en' ): array {
        $language         = TactiTwin_I18n::normalize_language( $language );
        $team_matches     = (int) ( $team_profile['coverage']['matches'] ?? 0 );
        $opponent_matches = (int) ( $opponent_profile['coverage']['matches'] ?? 0 );
        $effective        = min( $team_matches, $opponent_matches );

        $rates        = $team_profile['rates'] ?? array();
        $team_avg     = $team_profile['averages'] ?? array();
        $opponent_avg = $opponent_profile['averages'] ?? array();
        $zones        = $team_profile['turnover_zone_share'] ?? array();

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
            'counterpress_recovery'  => self::clamp( (float) ( $rates['counterpress_recovery_rate'] ?? 0 ) ),
            'final_third_loss_share' => self::clamp( (float) ( $zones['final_third'] ?? 0 ) ),
            'avoid_own_third_losses' => self::clamp( 100.0 - (float) ( $zones['own_third'] ?? 0 ) ),
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
        $risk_level = self::level( $risk_score );
        $opp_level  = self::level( $opportunity_score );

        return array(
            'scenario_id'   => 'defensive-transition-after-loss',
            'scenario_name' => TactiTwin_I18n::t( 'scenario.transition.name', array(), $language ),
            'language'      => $language,
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
                    ? TactiTwin_I18n::t( 'coverage.low', array(), $language )
                    : null,
            ),
            'risk' => array(
                'score'       => $risk_score,
                'level'       => $risk_level,
                'level_label' => TactiTwin_I18n::level_label( $risk_level, $language ),
                'components'  => $risk_components,
            ),
            'opportunity' => array(
                'score'       => $opportunity_score,
                'level'       => $opp_level,
                'level_label' => TactiTwin_I18n::level_label( $opp_level, $language ),
                'components'  => $opportunity_components,
            ),
            'evidence' => array(
                'our_turnovers_per_match'                   => (float) ( $team_avg['transition_turnovers_per_match'] ?? 0 ),
                'our_dangerous_turnover_rate'               => (float) ( $rates['dangerous_turnover_rate'] ?? 0 ),
                'our_transition_progression_rate'           => (float) ( $rates['transition_progression_rate'] ?? 0 ),
                'our_counterpress_recovery_rate'            => (float) ( $rates['counterpress_recovery_rate'] ?? 0 ),
                'our_turnover_zone_share'                   => $zones,
                'dominant_loss_zone'                        => $dominant_loss_zone,
                'opponent_transition_progressions_per_match'=> (float) ( $opponent_avg['transition_progressions_created_within_10s_per_match'] ?? 0 ),
                'opponent_transition_shots_per_match'       => (float) ( $opponent_avg['transition_shots_created_within_10s_per_match'] ?? 0 ),
                'opponent_transition_xg_per_match'          => (float) ( $opponent_avg['transition_xg_created_within_10s_per_match'] ?? 0 ),
            ),
            'recommendations' => self::recommendations(
                $risk_score,
                (float) ( $rates['counterpress_recovery_rate'] ?? 0 ),
                $dominant_loss_zone,
                (float) ( $zones['own_third'] ?? 0 ),
                (float) ( $opponent_avg['transition_shots_created_within_10s_per_match'] ?? 0 ),
                (float) ( $opponent_avg['transition_xg_created_within_10s_per_match'] ?? 0 ),
                $language
            ),
            'method' => array(
                'type' => 'temporal-transition-window-heuristic',
                'windows' => array(
                    'opponent_threat_seconds' => 10,
                    'counterpress_recovery_seconds' => 8,
                ),
                'note' => TactiTwin_I18n::t( 'scenario.transition.method', array(), $language ),
            ),
        );
    }

    private static function recommendations(
        float $risk,
        float $counterpress_rate,
        string $loss_zone,
        float $own_third_share,
        float $opponent_transition_shots,
        float $opponent_transition_xg,
        string $language
    ): array {
        $recommendations = array();

        if ( $risk >= 65.0 ) {
            $recommendations['primary'] = TactiTwin_I18n::t( 'transition.high.primary', array(), $language );
        } elseif ( $risk >= 45.0 ) {
            $recommendations['primary'] = TactiTwin_I18n::t( 'transition.medium.primary', array(), $language );
        } else {
            $recommendations['primary'] = TactiTwin_I18n::t( 'transition.low.primary', array(), $language );
        }

        $recommendations['counterpress'] = $counterpress_rate < 25.0
            ? TactiTwin_I18n::t( 'transition.counterpress_low', array(), $language )
            : TactiTwin_I18n::t( 'transition.counterpress_good', array(), $language );

        $recommendations['loss_zone'] = TactiTwin_I18n::t(
            'transition.loss_zone',
            array( TactiTwin_I18n::zone_label( $loss_zone, $language ) ),
            $language
        );

        if ( $own_third_share >= 25.0 ) {
            $recommendations['build_up_protection'] = TactiTwin_I18n::t( 'transition.build_up_protection', array(), $language );
        }

        $recommendations['opponent_threat'] = $opponent_transition_shots >= 1.0 || $opponent_transition_xg >= 0.25
            ? TactiTwin_I18n::t( 'transition.opponent_threat_high', array(), $language )
            : TactiTwin_I18n::t( 'transition.opponent_threat_low', array(), $language );

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
