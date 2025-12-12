<?php
/**
 * Handles manual fetch admin action.
 *
 * @package SYM_Travel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manual fetch controller.
 */
class SYM_Travel_Manual_Fetch {

	private SYM_Travel_IMAP_Client $imap_client;
	private SYM_Travel_OpenAI_Client $openai_client;
	private SYM_Travel_Trip_Repository $trip_repository;
	private SYM_Travel_Log_Repository $log_repository;

	/**
	 * Constructor.
	 *
	 * @param SYM_Travel_IMAP_Client     $imap_client   IMAP adapter.
	 * @param SYM_Travel_OpenAI_Client   $openai_client OpenAI adapter.
	 * @param SYM_Travel_Trip_Repository $trip_repo     Trip repository.
	 * @param SYM_Travel_Log_Repository  $log_repo      Logger.
	 */
	public function __construct(
		SYM_Travel_IMAP_Client $imap_client,
		SYM_Travel_OpenAI_Client $openai_client,
		SYM_Travel_Trip_Repository $trip_repo,
		SYM_Travel_Log_Repository $log_repo
	) {
		$this->imap_client   = $imap_client;
		$this->openai_client = $openai_client;
		$this->trip_repository = $trip_repo;
		$this->log_repository  = $log_repo;
	}

	/**
	 * Handle admin-post request for manual fetch.
	 */
	public function handle_request(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'sym-travel' ) );
		}

		check_admin_referer( SYM_Travel_Settings_Page::ACTION_FETCH );

		$settings = get_option( 'sym_travel_settings', array() );

		try {
			$this->imap_client->test_connection( $settings );
			// Placeholder: Future implementation will iterate through messages and persist trips.
			$this->log_repository->log(
				'imap',
				'Manual fetch executed (connection successful).',
				array( 'severity' => 'info' )
			);
			$this->persist_notice(
				__( 'IMAP connection succeeded. Message processing not yet implemented.', 'sym-travel' ),
				'updated'
			);
		} catch ( Exception $e ) {
			$this->log_repository->log(
				'imap',
				'Manual fetch failed: ' . $e->getMessage(),
				array( 'severity' => 'error' )
			);
			$this->persist_notice(
				sprintf(
					/* translators: %s error message */
					__( 'Manual fetch failed: %s', 'sym-travel' ),
					$e->getMessage()
				),
				'error'
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => SYM_Travel_Settings_Page::MENU_SLUG ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Persist admin notice for display on settings page.
	 *
	 * @param string $message Message text.
	 * @param string $type    Notice type.
	 */
	private function persist_notice( string $message, string $type ): void {
		add_settings_error(
			'sym_travel',
			'sym_travel_manual_fetch',
			$message,
			$type
		);

		set_transient( 'settings_errors', get_settings_errors(), 30 );
	}
}
