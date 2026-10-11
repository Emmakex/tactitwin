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
require_once TACTITWIN_DIR . 'includes/class-tactitwin-personnel-what-if.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-unit-comparison.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-phase-unit-comparison.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-opponent-lineup-matchup.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-lineup-what-if.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-profile-service.php';
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
require_once TACTITWIN_DIR . 'includes/class-tactitwin-personnel-rest.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-unit-rest.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-phase-unit-rest.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-lineup-rest.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-workspace-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-match-plan-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-game-state-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-live-playbook-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-live-session-admin.php';
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
TactiTwin_Personnel_REST::register();
TactiTwin_Unit_REST::register();
TactiTwin_Phase_Unit_REST::register();
TactiTwin_Lineup_REST::register();
TactiTwin_Workspace_Admin::register();
TactiTwin_Match_Plan_Admin::register();
TactiTwin_Game_State_Admin::register();
TactiTwin_Live_Playbook_Admin::register();
TactiTwin_Live_Session_Admin::register();
TactiTwin_Personnel_Admin::register();
TactiTwin_Unit_Admin::register();
TactiTwin_Lineup_Admin::register();
TactiTwin::instance();
