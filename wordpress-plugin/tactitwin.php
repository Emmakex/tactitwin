<?php
/**
 * Plugin Name: TactiTwin
 * Plugin URI:  https://github.com/Emmakex/tactitwin
 * Description: Football tactical intelligence and match-scenario simulation powered by public/open data.
 * Version:     0.1.0-dev
 * Author:      Emmakex
 * Text Domain: tactitwin
 * Requires PHP: 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TACTITWIN_VERSION', '0.1.0-dev' );
define( 'TACTITWIN_FILE', __FILE__ );
define( 'TACTITWIN_DIR', plugin_dir_path( __FILE__ ) );

require_once TACTITWIN_DIR . 'includes/class-tactitwin-db.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-statsbomb-client.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-statsbomb-normalizer.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-statsbomb-importer.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-match-metrics.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-analysis-service.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin-analysis-rest.php';
require_once TACTITWIN_DIR . 'includes/class-tactitwin.php';

register_activation_hook( __FILE__, array( 'TactiTwin', 'activate' ) );

TactiTwin_Analysis_REST::register();
TactiTwin::instance();
