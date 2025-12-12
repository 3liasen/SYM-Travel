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
require_once __DIR__ . '/class-sym-travel-openai-client.php';
require_once __DIR__ . '/class-sym-travel-schema-validator.php';
require_once __DIR__ . '/class-sym-travel-imap-client.php';
require_once __DIR__ . '/class-sym-travel-manual-fetch.php';
require_once __DIR__ . '/class-sym-travel-email-status-page.php';
require_once __DIR__ . '/class-sym-travel-latest-json-page.php';
require_once __DIR__ . '/class-sym-travel-inbox-page.php';
require_once __DIR__ . '/class-sym-travel-trip-manager-page.php';

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
	 * OpenAI adapter.
	 *
	 * @var SYM_Travel_OpenAI_Client
	 */
	private SYM_Travel_OpenAI_Client $openai_client;

	/**
	 * Schema validator.
	 *
	 * @var SYM_Travel_Schema_Validator
	 */
	private SYM_Travel_Schema_Validator $schema_validator;

	/**
	 * IMAP adapter.
	 *
	 * @var SYM_Travel_IMAP_Client
	 */
	private SYM_Travel_IMAP_Client $imap_client;

	/**
	 * Manual fetch handler.
	 *
	 * @var SYM_Travel_Manual_Fetch
	 */
	private SYM_Travel_Manual_Fetch $manual_fetch;

	/**
	 * Email status admin page.
	 *
	 * @var SYM_Travel_Email_Status_Page
	 */
	private SYM_Travel_Email_Status_Page $email_status_page;

	/**
	 * Latest JSON admin page.
	 *
	 * @var SYM_Travel_Latest_JSON_Page
	 */
	private SYM_Travel_Latest_JSON_Page $latest_json_page;

	/**
	 * Inbox preview admin page.
	 *
	 * @var SYM_Travel_Inbox_Page
	 */
	private SYM_Travel_Inbox_Page $inbox_page;

	/**
	 * Trip manager admin page.
	 *
	 * @var SYM_Travel_Trip_Manager_Page
	 */
	private SYM_Travel_Trip_Manager_Page $trip_manager_page;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings_page    = new SYM_Travel_Settings_Page();
		$this->trip_repository  = new SYM_Travel_Trip_Repository();
		$this->log_repository   = new SYM_Travel_Log_Repository();
		$this->schema_validator = new SYM_Travel_Schema_Validator();
		$this->openai_client    = new SYM_Travel_OpenAI_Client( $this->schema_validator, $this->log_repository );
		$this->imap_client      = new SYM_Travel_IMAP_Client( $this->log_repository );
		$this->manual_fetch     = new SYM_Travel_Manual_Fetch(
			$this->imap_client,
			$this->openai_client,
			$this->trip_repository,
			$this->log_repository
		);
		$this->email_status_page = new SYM_Travel_Email_Status_Page( $this->log_repository );
		$this->latest_json_page  = new SYM_Travel_Latest_JSON_Page( $this->trip_repository );
		$this->inbox_page        = new SYM_Travel_Inbox_Page( $this->imap_client );
		$this->trip_manager_page = new SYM_Travel_Trip_Manager_Page( $this->trip_repository, $this->schema_validator );
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
		add_action( 'admin_post_' . SYM_Travel_Settings_Page::ACTION_FETCH, array( $this->manual_fetch, 'handle_request' ) );
		add_action( 'admin_post_' . SYM_Travel_Trip_Manager_Page::ACTION_SAVE_MANUAL, array( $this->trip_manager_page, 'handle_manual_fields_save' ) );
		add_action( 'admin_post_' . SYM_Travel_Trip_Manager_Page::ACTION_SAVE_EXTRACTED, array( $this->trip_manager_page, 'handle_trip_data_save' ) );
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
		$this->email_status_page->register_menu();
		$this->latest_json_page->register_menu();
		$this->inbox_page->register_menu();
		$this->trip_manager_page->register_menu();
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

	/**
	 * Access the OpenAI client.
	 *
	 * @return SYM_Travel_OpenAI_Client
	 */
	public function get_openai_client(): SYM_Travel_OpenAI_Client {
		return $this->openai_client;
	}

	/**
	 * Access the schema validator.
	 *
	 * @return SYM_Travel_Schema_Validator
	 */
	public function get_schema_validator(): SYM_Travel_Schema_Validator {
		return $this->schema_validator;
	}

	/**
	 * Access manual fetch handler.
	 *
	 * @return SYM_Travel_Manual_Fetch
	 */
	public function get_manual_fetch(): SYM_Travel_Manual_Fetch {
		return $this->manual_fetch;
	}
}
