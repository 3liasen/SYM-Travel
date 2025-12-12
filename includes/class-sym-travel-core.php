<?php
/**
 * Core bootstrap for SYM Travel.
 *
 * @package SYM_Travel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-sym-travel-settings.php';
require_once __DIR__ . '/class-sym-travel-trip-repository.php';
require_once __DIR__ . '/class-sym-travel-log-repository.php';

/**
 * Primary plugin orchestrator.
 */
class SYM_Travel_Core {

	/**
	 * Settings page handler.
	 *
	 * @var SYM_Travel_Settings_Page
	 */
	private SYM_Travel_Settings_Page $settings_page;

	/**
	 * Trip repository instance.
	 *
	 * @var SYM_Travel_Trip_Repository
	 */
	private SYM_Travel_Trip_Repository $trip_repository;

	/**
	 * Log repository instance.
	 *
	 * @var SYM_Travel_Log_Repository
	 */
	private SYM_Travel_Log_Repository $log_repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings_page  = new SYM_Travel_Settings_Page();
		$this->trip_repository = new SYM_Travel_Trip_Repository();
		$this->log_repository  = new SYM_Travel_Log_Repository();
	}

	/**
	 * Hook WordPress actions.
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_sym_travel_test_imap', array( $this->settings_page, 'handle_test_imap' ) );
		add_action( 'admin_post_sym_travel_test_openai', array( $this->settings_page, 'handle_test_openai' ) );
	}

	/**
	 * Register the custom post type for trips.
	 */
	public function register_post_type(): void {
		$labels = array(
			'name'               => __( 'Trips', 'sym-travel' ),
			'singular_name'      => __( 'Trip', 'sym-travel' ),
			'add_new_item'       => __( 'Add New Trip', 'sym-travel' ),
			'edit_item'          => __( 'Edit Trip', 'sym-travel' ),
			'new_item'           => __( 'New Trip', 'sym-travel' ),
			'view_item'          => __( 'View Trip', 'sym-travel' ),
			'search_items'       => __( 'Search Trips', 'sym-travel' ),
			'not_found'          => __( 'No trips found', 'sym-travel' ),
			'not_found_in_trash' => __( 'No trips found in Trash', 'sym-travel' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'supports'           => array( 'title', 'editor' ),
			'capability_type'    => 'post',
			'has_archive'        => false,
			'show_in_rest'       => false,
			'rewrite'            => false,
		);

		register_post_type( 'trips', $args );
	}

	/**
	 * Register admin menu entries.
	 */
	public function register_admin_menu(): void {
		$this->settings_page->register_menu();
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings(): void {
		$this->settings_page->register_settings();
	}

	/**
	 * Access the trip repository.
	 *
	 * @return SYM_Travel_Trip_Repository
	 */
	public function get_trip_repository(): SYM_Travel_Trip_Repository {
		return $this->trip_repository;
	}

	/**
	 * Access the log repository.
	 *
	 * @return SYM_Travel_Log_Repository
	 */
	public function get_log_repository(): SYM_Travel_Log_Repository {
		return $this->log_repository;
	}
}
