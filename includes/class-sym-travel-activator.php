<?php
/**
 * Plugin activation routines.
 *
 * @package SYM_Travel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs on plugin activation.
 */
class SYM_Travel_Activator {

	/**
	 * Execute activation tasks.
	 */
	public function activate(): void {
		$this->create_tables();

		$core = new SYM_Travel_Core();
		$core->register_post_type();
		flush_rewrite_rules();
	}

	/**
	 * Create or upgrade database tables.
	 */
	private function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$trips_table     = $wpdb->prefix . 'sym_travel_trips';
		$logs_table      = $wpdb->prefix . 'sym_travel_logs';

		$trips_sql = "CREATE TABLE {$trips_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			pnr varchar(32) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			trip_data longtext NULL,
			post_id bigint(20) unsigned NULL,
			last_imported datetime NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY pnr (pnr),
			KEY post_id (post_id)
		) {$charset_collate};";

		$logs_sql = "CREATE TABLE {$logs_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			context varchar(50) NOT NULL,
			pnr varchar(32) NULL,
			severity varchar(20) NOT NULL DEFAULT 'info',
			message text NOT NULL,
			message_id varchar(255) NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY pnr (pnr),
			KEY severity (severity)
		) {$charset_collate};";

		dbDelta( $trips_sql );
		dbDelta( $logs_sql );
	}
}
