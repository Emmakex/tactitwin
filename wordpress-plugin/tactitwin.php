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
require_once TACTITWIN_DIR . 'includes/class-tactitwin-statsbomb-client.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-statsbomb-normalizer.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-statsbomb-importer.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-match-metrics.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-transition-metrics.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-game-state-metrics.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-analysis-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-analysis-rest.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-team-profile.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-high-press-scenario.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-side-bias-scenario.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-defensive-transition-scenario.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-low-block-scenario.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-weak-side-scenario.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-matchup-scan.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-match-plan.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-game-state-what-if.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-profile-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-data-preparation-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-scenario-rest.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-match-plan-rest.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-game-state-rest.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-workspace-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-match-plan-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-game-state-admin.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin.php';

register_activation_hook( __FILE__, array( 'TactiTwin', 'activate' ) );

TactiTwin_I18n::register();
TactiTwin_Analysis_REST::register();
TactiTwin_Scenario_REST::register();
TactiTwin_Match_Plan_REST::register();
TactiTwin_Game_State_REST::register();
TactiTwin_Workspace_Admin::register();
TactiTwin_Match_Plan_Admin::register();
TactiTwin_Game_State_Admin::register();
TactiTwin::instance();
