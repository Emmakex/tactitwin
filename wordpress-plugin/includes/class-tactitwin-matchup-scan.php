<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Matchup_Scan {
    public static function evaluate( array $team_profile, array $opponent_profile, string $language = 'en' ): array {
        $language = TactiTwin_I18n::normalize_language( $language );
        $scenarios = array(
            TactiTwin_High_Press_Scenario::evaluate( $team_profile, $opponent_profile, $language ),
            TactiTwin_Side_Bias_Scenario::evaluate( $team_profile, $opponent_profile, $language ),
            TactiTwin_Defensive_Transition_Scenario::evaluate( $team_profile, $opponent_profile, $language ),
            TactiTwin_Low_Block_Scenario::evaluate( $team_profile, $opponent_profile, $language ),
            TactiTwin_Weak_Side_Scenario::evaluate( $team_profile, $opponent_profile, $language ),
        );

        usort(
            $scenarios,
            static fn( array $a, array $b ): int => (float) ( $b['risk']['score'] ?? 0 ) <=> (float) ( $a['risk']['score'] ?? 0 )
        );

        $risk_scores        = array_map( static fn( array $scenario ): float => (float) ( $scenario['risk']['score'] ?? 0 ), $scenarios );
        $opportunity_scores = array_map( static fn( array $scenario ): float => (float) ( $scenario['opportunity']['score'] ?? 0 ), $scenarios );
        $top                = $scenarios[0];
        $effective_matches  = min(
            (int) ( $team_profile['coverage']['matches'] ?? 0 ),
            (int) ( $opponent_profile['coverage']['matches'] ?? 0 )
        );

        $ranking = array();
        $recommendations = array();
        foreach ( $scenarios as $index => $scenario ) {
            $ranking[] = array(
                'rank'              => $index + 1,
                'scenario_id'       => (string) $scenario['scenario_id'],
                'scenario_name'     => (string) $scenario['scenario_name'],
                'risk_score'        => (float) $scenario['risk']['score'],
                'risk_level'        => (string) $scenario['risk']['level'],
                'risk_level_label'  => (string) ( $scenario['risk']['level_label'] ?? $scenario['risk']['level'] ),
                'opportunity_score' => (float) $scenario['opportunity']['score'],
            );

            $primary = (string) ( $scenario['recommendations']['primary'] ?? reset( $scenario['recommendations'] ) ?? '' );
            if ( '' !== $primary ) {
                $recommendations[ $scenario['scenario_id'] ] = sprintf(
                    '%s — %s',
                    $scenario['scenario_name'],
                    $primary
                );
            }
        }

        $average_risk        = round( array_sum( $risk_scores ) / max( 1, count( $risk_scores ) ), 1 );
        $average_opportunity = round( array_sum( $opportunity_scores ) / max( 1, count( $opportunity_scores ) ), 1 );
        $opportunity_level   = self::level( $average_opportunity );

        return array(
            'scenario_id'   => 'full-matchup-scan',
            'scenario_name' => TactiTwin_I18n::t( 'scenario.full_scan.name', array(), $language ),
            'language'      => $language,
            'team'          => $top['team'],
            'opponent'      => $top['opponent'],
            'coverage'      => array(
                'team_matches'      => (int) ( $team_profile['coverage']['matches'] ?? 0 ),
                'opponent_matches'  => (int) ( $opponent_profile['coverage']['matches'] ?? 0 ),
                'effective_matches' => $effective_matches,
                'status'            => self::coverage_status( $effective_matches ),
                'warning'           => $effective_matches < 3
                    ? TactiTwin_I18n::t( 'coverage.full_scan_low', array(), $language )
                    : null,
            ),
            'risk' => array(
                'score'          => (float) $top['risk']['score'],
                'level'          => (string) $top['risk']['level'],
                'level_label'    => (string) ( $top['risk']['level_label'] ?? TactiTwin_I18n::level_label( (string) $top['risk']['level'], $language ) ),
                'average_score'  => $average_risk,
                'top_scenario'   => (string) $top['scenario_name'],
            ),
            'opportunity' => array(
                'score'       => $average_opportunity,
                'level'       => $opportunity_level,
                'level_label' => TactiTwin_I18n::level_label( $opportunity_level, $language ),
            ),
            'evidence' => array(
                'scenario_count'   => count( $scenarios ),
                'scenario_ranking' => $ranking,
            ),
            'recommendations' => $recommendations,
            'subscenarios'     => $scenarios,
            'method' => array(
                'type' => 'scenario-orchestration',
                'note' => TactiTwin_I18n::t( 'scenario.full_scan.method', array(), $language ),
            ),
        );
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

    private static function level( float $score ): string {
        if ( $score >= 65.0 ) {
            return 'high';
        }
        if ( $score >= 45.0 ) {
            return 'medium';
        }
        return 'low';
    }
}
