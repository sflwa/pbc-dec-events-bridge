<?php
/**
 * Fired during plugin activation.
 * Creates the custom bridge tables.
 */
class PBC_DEC_Activator {

	public static function activate() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Table 1: Sources (API Credentials)
		$table_sources = $wpdb->prefix . 'pbc_dec_sources';
		$sql_sources = "CREATE TABLE $table_sources (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			source_name varchar(100) NOT NULL,
			platform varchar(50) DEFAULT 'solidarity' NOT NULL,
			api_key text NOT NULL,
			org_id varchar(50) DEFAULT '' NOT NULL,
			agent_id varchar(50) DEFAULT '' NOT NULL,
			is_active tinyint(1) DEFAULT 1 NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// Table 2: Event Bridge (The Staging Area)
		$table_bridge = $wpdb->prefix . 'pbc_dec_event_bridge';
		$sql_bridge = "CREATE TABLE $table_bridge (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			source_id mediumint(9) NOT NULL,
			external_event_id varchar(100) NOT NULL,
			external_session_id varchar(100) NOT NULL,
			wp_post_id bigint(20) DEFAULT NULL,
			event_status varchar(20) DEFAULT 'pending' NOT NULL,
			event_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			sync_date timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
			raw_json longtext,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_session (source_id, external_event_id, external_session_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_sources );
		dbDelta( $sql_bridge );
	}
}
