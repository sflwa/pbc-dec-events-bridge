<?php
/**
 * Plugin Name:       PBC DEC Events Bridge
 * Plugin URI:        https://southfloridawebadvisors.com
 * Description:       A multi-source API bridge for The Events Calendar, Solidarity Tech, and Mobilize.
 * Version:           1.1.4
 * Author:            South Florida Web Advisors
 * Author URI:        https://southfloridawebadvisors.com
 * License:           GPL-2.0+
 * Text Domain:       pbc-dec-bridge
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define Constants
define( 'PBC_DEC_BRIDGE_VERSION', '1.1.4' );
define( 'PBC_DEC_BRIDGE_PATH', plugin_dir_path( __FILE__ ) );
define( 'PBC_DEC_BRIDGE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Database Activation
 */
function activate_pbc_dec_bridge() {
	require_once PBC_DEC_BRIDGE_PATH . 'includes/class-pbc-dec-activator.php';
	PBC_DEC_Activator::activate();
}
register_activation_hook( __FILE__, 'activate_pbc_dec_bridge' );

/**
 * Initialize Admin
 */
if ( is_admin() ) {
	require_once PBC_DEC_BRIDGE_PATH . 'admin/class-pbc-dec-admin.php';
	$pbc_admin = new PBC_DEC_Admin();
	$pbc_admin->init();
}
