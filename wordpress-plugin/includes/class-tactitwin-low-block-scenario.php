<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Low_Block_Scenario {
    public static function evaluate( array $team_profile, array $opponent_profile, string $language = 'en' ): array {
        $language         = TactiTwin_I18n::normalize_language( $language );
        $team_matches     = (int) ( $team_profile['coverage']['matches'] ?? 0 );
        $opponent_matches = (int) ( $opponent_profile['coverage']['matches'] ?? 0 );
        $effective        = min( $team_matches, $opponent_matches );
        $coverage_status  = self::coverage_status( $effective );

        $team_avg       = $team_profile['averages'] ?? array();
        $team_rates     = $team_profile['rates'] ?? array();
        $team_channels  = self::channels( $team_profile['final_third_channel_share'] ?? array() );
        $opp_rates      = $opponent_profile['rates'] ?? array();
        $opp_avg        = $opponent_profile['averages'] ?? array();
        $opp_concessions = $opponent_profile['opponent_context'] ?? array();
        $opp_channels_against = self::channels( $opponent_profile['final_third_channel_share_against'] ?? array() );

        $risk_components = array(
            'deep_block_proxy' => self::normalize(
                (float) ( $opp_rates['deep_defensive_action_share_pct'] ?? 0 ),
                45.0
            ),
            'box_access_denial' => self::inverse_normalize(
                (float) ( $opp_concessions['box_entries_per_match'] ?? 0 ),
                15.0
            ),
            'chance_suppression' => self::inverse_normalize(
                (float) ( $opp_concessions['xg_per_match'] ?? 0 ),
                1.50
            ),
            'shot_suppression' => self::inverse_normalize(
                (float) ( $opp_concessions['shots_per_match'] ?? 0 ),
                14.0
            ),
        );

        $risk_score = round(
            ( $risk_components['deep_block_proxy'] * 0.35 ) +
            ( $risk_components['box_access_denial'] * 0.25 ) +
            ( $risk_components['chance_suppression'] * 0.25 ) +
            ( $risk_components['shot_suppression'] * 0.15 ),
            1
        );

        $channel_balance = self::channel_balance( $team_channels );
        $opportunity_components = array(
            'box_access'       => self::normalize( (float) ( $team_avg['box_entries_per_match'] ?? 0 ), 15.0 ),
            'box_shot_share'   => self::clamp( (float) ( $team_rates['box_shot_share_pct'] ?? 0 ) ),
            'shot_quality'     => self::normalize( (float) ( $team_rates['xg_per_shot'] ?? 0 ), 0.14 ),
            'channel_balance'  => $channel_balance,
        );

        $opportunity_score = round(
            ( $opportunity_components['box_access'] * 0.30 ) +
            ( $opportunity_components['box_shot_share'] * 0.25 ) +
            ( $opportunity_components['shot_quality'] * 0.25 ) +
            ( $opportunity_components['channel_balance'] * 0.20 ),
            1
        );

        $best_attack_channel = self::max_channel( $team_channels );
        $best_concession_channel = self::max_channel( $opp_channels_against );

        return array(
            'scenario_id'   => 'attack-vs-low-block',
            'scenario_name' => TactiTwin_I18n::t( 'scenario.low_block.name', array(), $language ),
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
                'status'            => $coverage_status,
                'warning'           => 'low' === $coverage_status
                    ? TactiTwin_I18n::t( 'coverage.low', array(), $language )
                    : null,
            ),
            'risk' => array(
                'score'       => $risk_score,
                'level'       => self::level( $risk_score ),
                'level_label' => TactiTwin_I18n::level_label( self::level( $risk_score ), $language ),
                'components'  => $risk_components,
            ),
            'opportunity' => array(
                'score'       => $opportunity_score,
                'level'       => self::level( $opportunity_score ),
                'level_label' => TactiTwin_I18n::level_label( self::level( $opportunity_score ), $language ),
                'components'  => $opportunity_components,
            ),
            'evidence' => array(
                'opponent_deep_defensive_action_share_pct' => (float) ( $opp_rates['deep_defensive_action_share_pct'] ?? 0 ),
                'opponent_deep_defensive_actions_per_match'=> (float) ( $opp_avg['deep_defensive_actions_per_match'] ?? 0 ),
                'opponent_box_entries_conceded_per_match'   => (float) ( $opp_concessions['box_entries_per_match'] ?? 0 ),
                'opponent_shots_conceded_per_match'         => (float) ( $opp_concessions['shots_per_match'] ?? 0 ),
                'opponent_xg_conceded_per_match'            => (float) ( $opp_concessions['xg_per_match'] ?? 0 ),
                'our_final_third_entries_per_match'         => (float) ( $team_avg['final_third_entries_per_match'] ?? 0 ),
                'our_box_entries_per_match'                 => (float) ( $team_avg['box_entries_per_match'] ?? 0 ),
                'our_box_shot_share_pct'                    => (float) ( $team_rates['box_shot_share_pct'] ?? 0 ),
                'our_xg_per_shot'                           => (float) ( $team_rates['xg_per_shot'] ?? 0 ),
                'our_channel_balance_score'                 => $channel_balance,
                'our_dominant_attack_channel'               => $best_attack_channel,
                'opponent_most_conceded_channel'            => $best_concession_channel,
                'opponent_channel_share_conceded'           => $opp_channels_against,
            ),
            'recommendations' => self::recommendations(
                $risk_score,
                (float) ( $team_rates['box_shot_share_pct'] ?? 0 ),
                (float) ( $team_rates['xg_per_shot'] ?? 0 ),
                $channel_balance,
                $best_attack_channel,
                $best_concession_channel,
                $language
            ),
            'method' => array(
                'type' => 'event-data-low-block-proxy-heuristic',
                'note' => TactiTwin_I18n::t( 'scenario.low_block.method', array(), $language ),
                'tracking_required_for' => array( 'exact_defensive_line_height', 'inter-line_distance', 'team_compactness' ),
            ),
        );
    }

    private static function recommendations(
        float $risk,
        float $box_shot_share,
        float $xg_per_shot,
        float $channel_balance,
        string $dominant_channel,
        string $concession_channel,
        string $language
    ): array {
        $recommendations = array();
        $dominant_label  = TactiTwin_I18n::channel_label( $dominant_channel, $language );
        $concession_label = TactiTwin_I18n::channel_label( $concession_channel, $language );

        if ( $risk >= 65.0 ) {
            $recommendations['primary'] = TactiTwin_I18n::t( 'low_block.high.primary', array(), $language );
        } elseif ( $risk >= 45.0 ) {
            $recommendations['primary'] = TactiTwin_I18n::t( 'low_block.medium.primary', array(), $language );
        } else {
            $recommendations['primary'] = TactiTwin_I18n::t( 'low_block.low.primary', array(), $language );
        }

        $recommendations['channel_plan'] = TactiTwin_I18n::t(
            'low_block.channel_plan',
            array( $concession_label, $dominant_label ),
            $language
        );

        $recommendations['box_access'] = $box_shot_share < 60.0
            ? TactiTwin_I18n::t( 'low_block.box_access_low', array(), $language )
            : TactiTwin_I18n::t( 'low_block.box_access_good', array(), $language );

        $recommendations['shot_selection'] = $xg_per_shot < 0.10
            ? TactiTwin_I18n::t( 'low_block.shot_quality_low', array(), $language )
            : TactiTwin_I18n::t( 'low_block.shot_quality_good', array(), $language );

        if ( $channel_balance < 65.0 ) {
            $recommendations['circulation'] = TactiTwin_I18n::t( 'low_block.balance_low', array(), $language );
        } else {
            $recommendations['circulation'] = TactiTwin_I18n::t( 'low_block.balance_good', array(), $language );
        }

        $recommendations['rest_defence'] = TactiTwin_I18n::t( 'low_block.rest_defence', array(), $language );

        return $recommendations;
    }

    private static function channel_balance( array $channels ): float {
        if ( empty( $channels ) ) {
            return 0.0;
        }

        $max = max( $channels );
        $min = min( $channels );
        return self::clamp( 100.0 - ( ( $max - $min ) * 1.5 ) );
    }

    private static function channels( array $channels ): array {
        return array(
            'left'    => (float) ( $channels['left'] ?? 0 ),
            'central' => (float) ( $channels['central'] ?? 0 ),
            'right'   => (float) ( $channels['right'] ?? 0 ),
        );
    }

    private static function max_channel( array $channels ): string {
        if ( empty( $channels ) ) {
            return 'central';
        }
        arsort( $channels );
        return (string) array_key_first( $channels );
    }

    private static function inverse_normalize( float $value, float $reference ): float {
        return self::clamp( 100.0 - self::normalize( $value, $reference ) );
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
