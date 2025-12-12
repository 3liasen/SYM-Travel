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
			$messages = $this->imap_client->fetch_messages( $settings, 10 );

			if ( empty( $messages ) ) {
				$this->persist_notice(
					__( 'No new airline emails found.', 'sym-travel' ),
					'updated'
				);
				$this->log_repository->log(
					'imap',
					'Manual fetch completed. No new messages.',
					array( 'severity' => 'info' )
				);
				$this->redirect_back();
			}

			$processed_uids = array();
			$summary        = array(
				'success' => 0,
				'failed'  => 0,
			);

			foreach ( $messages as $message ) {
				$parsed = null;
				try {
					$parsed = $this->openai_client->parse_email(
						$message['body'],
						array(
							'message_id' => $message['message_id'],
							'date'       => $message['date'],
							'from'       => $message['from'],
						)
					);

					$this->trip_repository->upsert_trip(
						array(
							'pnr'              => $parsed['pnr'],
							'status'           => 'parsed',
							'trip_data'        => $parsed,
							'extracted_fields' => $parsed,
							'manual_fields'    => array(),
						)
					);

					$this->log_repository->log(
						'import',
						sprintf( 'Imported trip %s from %s', $parsed['pnr'], $message['message_id'] ?: 'unknown message' ),
						array(
							'severity'   => 'info',
							'pnr'        => $parsed['pnr'],
							'message_id' => $message['message_id'],
						)
					);

					$processed_uids[] = $message['uid'];
					$summary['success']++;
				} catch ( Exception $parse_exception ) {
					$this->log_repository->log(
						'import',
						'Failed to import email: ' . $parse_exception->getMessage(),
						array(
							'severity'   => 'error',
							'pnr'        => is_array( $parsed ) ? ( $parsed['pnr'] ?? '' ) : '',
							'message_id' => $message['message_id'],
						)
					);
					$summary['failed']++;
				}
			}

			$this->imap_client->mark_messages_seen( $settings, $processed_uids );

			$this->persist_notice(
				sprintf(
					/* translators: 1: success count, 2: failure count */
					__( 'Manual fetch completed. %1$d imported, %2$d failed.', 'sym-travel' ),
					$summary['success'],
					$summary['failed']
				),
				$summary['failed'] ? 'error' : 'updated'
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

		$this->redirect_back();
	}

	/**
	 * Helper to redirect back to email status page.
	 */
	private function redirect_back(): void {
		wp_safe_redirect(
			add_query_arg(
				array( 'page' => SYM_Travel_Email_Status_Page::MENU_SLUG ),
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
