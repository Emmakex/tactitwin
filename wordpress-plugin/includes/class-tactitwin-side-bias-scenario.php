<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Side_Bias_Scenario {
    public static function evaluate( array $team_profile, array $opponent_profile, string $language = 'en' ): array {
        $language         = TactiTwin_I18n::normalize_language( $language );
        $team_matches     = (int) ( $team_profile['coverage']['matches'] ?? 0 );
        $opponent_matches = (int) ( $opponent_profile['coverage']['matches'] ?? 0 );
        $coverage_matches = min( $team_matches, $opponent_matches );

        $opponent_channels = self::channels( $opponent_profile['final_third_channel_share'] ?? array() );
        $our_concessions   = self::channels( $team_profile['final_third_channel_share_against'] ?? array() );

        $channel_risk = array();
        foreach ( array( 'left', 'central', 'right' ) as $channel ) {
            $channel_risk[ $channel ] = round(
                ( $opponent_channels[ $channel ] * 0.60 ) + ( $our_concessions[ $channel ] * 0.40 ),
                1
            );
        }

        $dominant_channel   = self::max_channel( $opponent_channels );
        $weak_channel       = self::min_channel( $opponent_channels );
        $vulnerable_channel = self::max_channel( $our_concessions );
        $risk_channel       = self::max_channel( $channel_risk );
        $risk_raw           = $channel_risk[ $risk_channel ];
        $risk_score         = self::clamp( ( $risk_raw - 33.3 ) * 3.0 );
        $opportunity_score  = self::clamp( 100.0 - ( $channel_risk[ $weak_channel ] * 1.5 ) );
        $overlap            = $dominant_channel === $vulnerable_channel;
        $risk_level         = self::level( $risk_score );
        $opp_level          = self::level( $opportunity_score );

        return array(
            'scenario_id'   => 'opponent-side-bias',
            'scenario_name' => TactiTwin_I18n::t( 'scenario.side_bias.name', array(), $language ),
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
                'effective_matches' => $coverage_matches,
                'status'            => self::coverage_status( $coverage_matches ),
                'warning'           => $coverage_matches < 3
                    ? TactiTwin_I18n::t( 'coverage.low', array(), $language )
                    : null,
            ),
            'risk' => array(
                'score'                => $risk_score,
                'level'                => $risk_level,
                'level_label'          => TactiTwin_I18n::level_label( $risk_level, $language ),
                'highest_risk_channel' => $risk_channel,
                'channel_scores'       => $channel_risk,
                'dominant_overlap'     => $overlap,
            ),
            'opportunity' => array(
                'score'       => $opportunity_score,
                'level'       => $opp_level,
                'level_label' => TactiTwin_I18n::level_label( $opp_level, $language ),
                'channel'     => $weak_channel,
            ),
            'evidence' => array(
                'opponent_final_third_channel_share' => $opponent_channels,
                'our_channel_share_conceded'          => $our_concessions,
                'opponent_dominant_channel'           => $dominant_channel,
                'opponent_weak_channel'               => $weak_channel,
                'our_most_vulnerable_channel'         => $vulnerable_channel,
            ),
            'recommendations' => self::recommendations(
                $dominant_channel,
                $weak_channel,
                $vulnerable_channel,
                $overlap,
                $opponent_channels,
                $our_concessions,
                $language
            ),
            'method' => array(
                'type' => 'explainable-channel-matchup-heuristic',
                'note' => TactiTwin_I18n::t( 'scenario.side_bias.method', array(), $language ),
            ),
        );
    }

    private static function recommendations(
        string $dominant,
        string $weak,
        string $vulnerable,
        bool $overlap,
        array $opponent_channels,
        array $our_concessions,
        string $language
    ): array {
        $our_side        = self::defensive_side( $dominant );
        $dominant_share  = $opponent_channels[ $dominant ];
        $conceded_share  = $our_concessions[ $dominant ];
        $our_side_label  = TactiTwin_I18n::channel_label( $our_side, $language );
        $dominant_label  = TactiTwin_I18n::channel_label( $dominant, $language );
        $weak_label      = TactiTwin_I18n::channel_label( $weak, $language );
        $vulnerable_label= TactiTwin_I18n::channel_label( $vulnerable, $language );

        $primary = TactiTwin_I18n::t(
            'side_bias.primary',
            array( $our_side_label, $dominant_share, $dominant_label, $conceded_share ),
            $language
        );

        if ( $overlap ) {
            $primary .= TactiTwin_I18n::t( 'side_bias.overlap', array(), $language );
        }

        return array(
            'primary' => $primary,
            'pressing_orientation' => TactiTwin_I18n::t(
                'side_bias.pressing',
                array( $dominant_label, $weak_label ),
                $language
            ),
            'cover_structure' => TactiTwin_I18n::t( 'side_bias.cover', array(), $language ),
            'training_focus' => TactiTwin_I18n::t(
                'side_bias.training',
                array( $our_side_label ),
                $language
            ),
            'secondary_alert' => $vulnerable !== $dominant
                ? TactiTwin_I18n::t( 'side_bias.secondary_other', array( $vulnerable_label ), $language )
                : TactiTwin_I18n::t( 'side_bias.secondary_overlap', array(), $language ),
        );
    }

    private static function defensive_side( string $opponent_channel ): string {
        if ( 'left' === $opponent_channel ) {
            return 'right';
        }
        if ( 'right' === $opponent_channel ) {
            return 'left';
        }
        return 'central';
    }

    private static function channels( array $channels ): array {
        return array(
            'left'    => (float) ( $channels['left'] ?? 0 ),
            'central' => (float) ( $channels['central'] ?? 0 ),
            'right'   => (float) ( $channels['right'] ?? 0 ),
        );
    }

    private static function max_channel( array $channels ): string {
        arsort( $channels );
        return (string) array_key_first( $channels );
    }

    private static function min_channel( array $channels ): string {
        asort( $channels );
        return (string) array_key_first( $channels );
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
