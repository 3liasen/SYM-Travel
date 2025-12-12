<?php
/**
 * Structured logging storage.
 *
 * @package SYM_Travel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists log events to the sym_travel_logs table.
 */
class SYM_Travel_Log_Repository {

	/**
	 * WordPress DB handle.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param wpdb|null $db Optional dependency injection.
	 */
	public function __construct( ?wpdb $db = null ) {
		global $wpdb;
		$this->wpdb = $db ?? $wpdb;
	}

	/**
	 * Persist a log entry.
	 *
	 * @param string $context  Where the log originated (e.g., imap, openai, import).
	 * @param string $message  Human-readable description (no secrets).
	 * @param array  $meta     Optional associative meta: severity, pnr, message_id.
	 */
	public function log( string $context, string $message, array $meta = array() ): void {
		$table    = $this->wpdb->prefix . 'sym_travel_logs';
		$severity = isset( $meta['severity'] ) ? sanitize_key( $meta['severity'] ) : 'info';
		$pnr      = isset( $meta['pnr'] ) ? sanitize_text_field( $meta['pnr'] ) : null;
		$msg_id   = isset( $meta['message_id'] ) ? sanitize_text_field( $meta['message_id'] ) : null;

		$data = array(
			'context'    => sanitize_key( $context ),
			'message'    => wp_strip_all_tags( $message ),
			'severity'   => $severity,
			'pnr'        => $pnr,
			'message_id' => $msg_id,
			'created_at' => current_time( 'mysql' ),
		);

		$this->wpdb->insert(
			$table,
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Retrieve recent log entries for display.
	 *
	 * @param int $limit Number of entries to fetch.
	 * @return array<int,stdClass>
	 */
	public function get_recent_entries( int $limit = 25 ): array {
		$table = $this->wpdb->prefix . 'sym_travel_logs';
		$query = $this->wpdb->prepare(
			"SELECT context, pnr, severity, message, message_id, created_at FROM {$table} ORDER BY created_at DESC LIMIT %d",
			$limit
		);

		$results = $this->wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $results ) ? $results : array();
	}
}
