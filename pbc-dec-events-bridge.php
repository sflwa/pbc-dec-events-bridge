<?php
/**
 * Plugin Name:       PBC DEC Events Bridge
 * Plugin URI:        https://southfloridawebadvisors.com
 * Description:       A multi-source API bridge for The Events Calendar, Solidarity Tech, and Mobilize.
 * Version:           1.1.5
 * Author:            South Florida Web Advisors
 * Author URI:        https://southfloridawebadvisors.com
 * License:           GPL-2.0+
 * Text Domain:       pbc-dec-bridge
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define Constants
define( 'PBC_DEC_BRIDGE_VERSION', '1.1.5' );
define( 'PBC_DEC_BRIDGE_PATH', plugin_dir_path( __FILE__ ) );
define( 'PBC_DEC_BRIDGE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Database Activation & Cron Setup
 */
function activate_pbc_dec_bridge() {
	require_once PBC_DEC_BRIDGE_PATH . 'includes/class-pbc-dec-activator.php';
	PBC_DEC_Activator::activate();

	// Schedule Daily Sync
	if ( ! wp_next_scheduled( 'pbc_dec_daily_sync_hook' ) ) {
		wp_schedule_event( time(), 'daily', 'pbc_dec_daily_sync_hook' );
	}
}
register_activation_hook( __FILE__, 'activate_pbc_dec_bridge' );

/**
 * Deactivation - Clean up Cron
 */
function deactivate_pbc_dec_bridge() {
	wp_clear_scheduled_hook( 'pbc_dec_daily_sync_hook' );
}
register_deactivation_hook( __FILE__, 'deactivate_pbc_dec_bridge' );

/**
 * Initialize Sync Engine for Cron
 */
add_action( 'pbc_dec_daily_sync_hook', 'pbc_dec_run_automated_sync' );
function pbc_dec_run_automated_sync() {
	require_once PBC_DEC_BRIDGE_PATH . 'includes/class-pbc-dec-sync.php';
	$sync = new PBC_DEC_Sync();
	$sync->run_full_sync();
}

/**
 * Initialize Admin
 */
if ( is_admin() ) {
	require_once PBC_DEC_BRIDGE_PATH . 'admin/class-pbc-dec-admin.php';
	$pbc_admin = new PBC_DEC_Admin();
	$pbc_admin->init();
}
