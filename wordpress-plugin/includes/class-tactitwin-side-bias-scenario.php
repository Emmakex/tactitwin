<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Side_Bias_Scenario {
    public static function evaluate( array $team_profile, array $opponent_profile ): array {
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

        return array(
            'scenario_id'   => 'opponent-side-bias',
            'scenario_name' => 'Opponent Side Bias',
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
                    ? 'Low evidence coverage: import at least three event-data matches per team before using this scenario for match planning.'
                    : null,
            ),
            'risk' => array(
                'score'              => $risk_score,
                'level'              => self::level( $risk_score ),
                'highest_risk_channel' => $risk_channel,
                'channel_scores'     => $channel_risk,
                'dominant_overlap'   => $overlap,
            ),
            'opportunity' => array(
                'score'        => $opportunity_score,
                'level'        => self::level( $opportunity_score ),
                'channel'      => $weak_channel,
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
                $our_concessions
            ),
            'method' => array(
                'type' => 'explainable-channel-matchup-heuristic',
                'note' => 'Channel scores combine the opponent attack distribution with our observed concession distribution; they are tactical decision-support signals, not outcome probabilities.',
            ),
        );
    }

    private static function recommendations(
        string $dominant,
        string $weak,
        string $vulnerable,
        bool $overlap,
        array $opponent_channels,
        array $our_concessions
    ): array {
        $our_side = self::defensive_side( $dominant );
        $dominant_share = $opponent_channels[ $dominant ];
        $conceded_share = $our_concessions[ $dominant ];

        $primary = sprintf(
            'Prioritize protection of our %s defensive channel: the opponent sends %.1f%% of its observed final-third actions through its %s channel, while %.1f%% of the final-third actions we concede arrive through that same matchup channel.',
            $our_side,
            $dominant_share,
            $dominant,
            $conceded_share
        );

        if ( $overlap ) {
            $primary .= ' This is a direct overlap between their strongest route and our most exposed channel.';
        }

        return array(
            'primary' => $primary,
            'pressing_orientation' => sprintf(
                'Orient the first defensive pressure to discourage the opponent %s channel and make circulation continue toward its %s channel, the least-used route in the imported sample.',
                $dominant,
                $weak
            ),
            'cover_structure' => 'Keep the near-side full-back/wing-back protected by an inside midfielder and preserve cover behind the first duel instead of allowing an isolated wide 1v1.',
            'training_focus' => sprintf(
                'Rehearse defensive shifting toward our %s side, then train the transition after recovery from that zone so the opponent cannot immediately counter-press the same corridor.',
                $our_side
            ),
            'secondary_alert' => $vulnerable !== $dominant
                ? sprintf( 'Our historically most exposed channel is %s, so do not over-shift so aggressively that this secondary weakness becomes open.', $vulnerable )
                : 'The opponent preference and our defensive exposure align, so this channel should receive the clearest match-plan priority.',
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
