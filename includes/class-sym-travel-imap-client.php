<?php
/**
 * IMAP client wrapper.
 *
 * @package SYM_Travel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles IMAP connectivity checks and message retrieval.
 */
class SYM_Travel_IMAP_Client {

	private SYM_Travel_Log_Repository $logger;

	/**
	 * Constructor.
	 *
	 * @param SYM_Travel_Log_Repository $logger Logger dependency.
	 */
	public function __construct( SYM_Travel_Log_Repository $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Validate settings and ensure IMAP functions available.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function validate_settings( array $settings ): void {
		$required = array( 'imap_host', 'imap_port', 'imap_username', 'imap_password', 'imap_mailbox' );
		foreach ( $required as $key ) {
			if ( empty( $settings[ $key ] ) ) {
				throw new RuntimeException( sprintf( 'Missing IMAP setting: %s', $key ) );
			}
		}

		if ( ! function_exists( 'imap_open' ) ) {
			throw new RuntimeException( 'PHP IMAP extension is not installed.' );
		}
	}

	/**
	 * Attempt to open a connection to verify credentials.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function test_connection( array $settings ): void {
		$this->validate_settings( $settings );

		$mailbox = $this->build_mailbox_string( $settings );
		$stream  = @imap_open( $mailbox, $settings['imap_username'], $settings['imap_password'], OP_HALFOPEN ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( false === $stream ) {
			$error = imap_last_error();
			$this->logger->log(
				'imap',
				'IMAP connection failed: ' . $error,
				array( 'severity' => 'error' )
			);
			throw new RuntimeException( 'Unable to connect to IMAP server.' );
		}

		imap_close( $stream );
	}

	/**
	 * Fetch message stubs for future parsing (placeholder).
	 *
	 * @param array $settings Plugin settings.
	 * @return array
	 */
	public function fetch_messages( array $settings ): array {
		$this->validate_settings( $settings );
		// Placeholder for full IMAP retrieval logic.
		return array();
	}

	/**
	 * Build mailbox string with encryption flags.
	 *
	 * @param array $settings Plugin settings.
	 * @return string
	 */
	private function build_mailbox_string( array $settings ): string {
		$host       = $settings['imap_host'];
		$port       = (int) $settings['imap_port'];
		$mailbox    = $settings['imap_mailbox'] ?: 'INBOX';
		$encryption = strtolower( (string) ( $settings['imap_encryption'] ?? '' ) );

		$flags = '/imap';
		if ( 'ssl' === $encryption ) {
			$flags .= '/ssl';
		} elseif ( 'tls' === $encryption || 'starttls' === $encryption ) {
			$flags .= '/tls';
		}

		return sprintf( '{%s:%d%s}%s', $host, $port, $flags, $mailbox );
	}
}
