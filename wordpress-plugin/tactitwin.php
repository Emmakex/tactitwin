<?php
/**
 * Plugin Name: TactiTwin
 * Plugin URI:  https://github.com/Emmakex/tactitwin
 * Description: Football tactical intelligence and match-scenario simulation powered by public/open data.
 * Version:     0.1.0-dev
 * Author:      Emmakex
 * Text Domain: tactitwin
 * Domain Path: /languages
 * Requires PHP: 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TACTITWIN_VERSION', '0.1.0-dev' );
define( 'TACTITWIN_FILE', __FILE__ );
define( 'TACTITWIN_DIR', plugin_dir_path( __FILE__ ) );

require_once TACTITWIN_DIR . 'includes/class-tactitwin-i18n.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-db.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-schema-guard.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-statsbomb-client.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-statsbomb-normalizer.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-statsbomb-importer.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-match-metrics.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-transition-metrics.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-game-state-metrics.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-manpower-metrics.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-player-metrics.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-unit-metrics.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-phase-unit-metrics.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-analysis-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-analysis-rest.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-team-profile.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-player-profile.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-unit-profile.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-phase-unit-profile.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-formation-model.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-high-press-scenario.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-side-bias-scenario.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-defensive-transition-scenario.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-low-block-scenario.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-weak-side-scenario.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-matchup-scan.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-match-plan.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-game-state-what-if.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-context-what-if.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-contingency-playbook.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-live-observation.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-live-timeline.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-live-session-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-session-match-link-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-post-match-review.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-objective-post-match-evidence.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-time-aligned-evidence.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-transition-window-deltas.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-context-aware-transition-review.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-post-match-review-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-context-matched-intervention-library.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-intervention-library-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-historical-precedent-retrieval.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-evidence-quality-calibrator.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-precedent-quality-attachment.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-pre-match-precedent-catalog.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-playbook-precedent-attachment.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-personnel-what-if.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-unit-comparison.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-phase-unit-comparison.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-opponent-lineup-matchup.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-lineup-what-if.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-profile-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-historical-team-profile-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-opponent-context-distance.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-opponent-context-similarity-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-precedent-similarity-attachment.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-evidence-suitability-ranker.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-evidence-ranking-robustness.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-evidence-decision-brief.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-evidence-conflict-signals.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-fixture-identity.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-match-target-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-planning-context.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-match-day-staff-brief.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-staff-brief-snapshot.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-staff-brief-version-diff.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-staff-brief-revision-chain.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-staff-brief-snapshot-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-precedent-aware-what-if-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-precedent-aware-match-plan-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-precedent-aware-contingency-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-personnel-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-unit-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-phase-unit-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-lineup-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-data-preparation-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-scenario-rest.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-match-plan-rest.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-game-state-rest.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-contingency-rest.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-live-observation-rest.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-live-session-rest.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-session-match-link-rest.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-post-match-review-rest.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-intervention-library-rest.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-personnel-rest.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-unit-rest.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-phase-unit-rest.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-lineup-rest.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-workspace-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-match-plan-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-game-state-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-live-playbook-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-live-session-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-post-match-review-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-objective-evidence-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-segment-evidence-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-transition-delta-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-intervention-library-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-precedent-planning-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-staff-brief-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-staff-brief-handoff-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-fixture-identity-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-personnel-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-unit-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-lineup-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin.php';

register_activation_hook( __FILE__, array( 'TactiTwin', 'activate' ) );

TactiTwin_Schema_Guard::register();
TactiTwin_I18n::register();
TactiTwin_Analysis_REST::register();
TactiTwin_Scenario_REST::register();
TactiTwin_Match_Plan_REST::register();
TactiTwin_Game_State_REST::register();
TactiTwin_Contingency_REST::register();
TactiTwin_Live_Observation_REST::register();
TactiTwin_Live_Session_REST::register();
TactiTwin_Session_Match_Link_REST::register();
TactiTwin_Post_Match_Review_REST::register();
TactiTwin_Intervention_Library_REST::register();
TactiTwin_Personnel_REST::register();
TactiTwin_Unit_REST::register();
TactiTwin_Phase_Unit_REST::register();
TactiTwin_Lineup_REST::register();
TactiTwin_Planning_Context::register();
TactiTwin_Workspace_Admin::register();
TactiTwin_Match_Plan_Admin::register();
TactiTwin_Game_State_Admin::register();
TactiTwin_Live_Playbook_Admin::register();
TactiTwin_Live_Session_Admin::register();
TactiTwin_Post_Match_Review_Admin::register();
TactiTwin_Objective_Evidence_Admin::register();
TactiTwin_Segment_Evidence_Admin::register();
TactiTwin_Transition_Delta_Admin::register();
TactiTwin_Intervention_Library_Admin::register();
TactiTwin_Precedent_Planning_Admin::register();
TactiTwin_Staff_Brief_Admin::register();
TactiTwin_Staff_Brief_Handoff_Admin::register();
TactiTwin_Fixture_Identity_Admin::register();
TactiTwin_Personnel_Admin::register();
TactiTwin_Unit_Admin::register();
TactiTwin_Lineup_Admin::register();
TactiTwin::instance();
