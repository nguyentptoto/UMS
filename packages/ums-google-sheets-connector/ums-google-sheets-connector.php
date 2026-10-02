<?php
/**
 * Plugin Name: UMS Google Sheets Connector
 * Description: Google Sheets organization, account and periodic allocation sync for UMS.
 * Version: 1.0.0
 * Requires PHP: 7.4
 * Author: UMS Team
 * Text Domain: ums-sheets-connector
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'UMS_SHEETS_CONNECTOR_DIR', plugin_dir_path( __FILE__ ) );
define( 'UMS_SHEETS_CONNECTOR_URL', plugin_dir_url( __FILE__ ) );
require_once UMS_SHEETS_CONNECTOR_DIR . 'includes/class-ums-sheets-connector.php';

// UMS publishes its API during plugins_loaded at priority 10.
add_action( 'plugins_loaded', array( 'UMS_Sheets_Connector', 'boot' ), 20 );
register_deactivation_hook( __FILE__, array( 'UMS_Sheets_Connector', 'deactivate' ) );
