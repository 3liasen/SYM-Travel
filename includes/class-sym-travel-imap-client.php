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
	 * Fetch unseen messages from the configured mailbox.
	 *
	 * @param array $settings Plugin settings.
	 * @param int   $limit    Max number of messages to retrieve.
	 * @return array<int,array>
	 */
	public function fetch_messages( array $settings, int $limit = 10 ): array {
		$this->validate_settings( $settings );

		$mailbox = $this->build_mailbox_string( $settings );
		$stream  = @imap_open( $mailbox, $settings['imap_username'], $settings['imap_password'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( false === $stream ) {
			$error = imap_last_error();
			$this->logger->log(
				'imap',
				'Unable to open mailbox: ' . $error,
				array( 'severity' => 'error' )
			);
			throw new RuntimeException( 'Unable to open IMAP mailbox.' );
		}

		$search = imap_search( $stream, 'UNSEEN' );
		if ( false === $search || empty( $search ) ) {
			imap_close( $stream );
			return array();
		}

		$messages = array();
		$count    = 0;

		foreach ( $search as $msgno ) {
			$overview = imap_fetch_overview( $stream, (string) $msgno, 0 );
			$body     = imap_body( $stream, (int) $msgno, FT_PEEK );
			$uid      = imap_uid( $stream, (int) $msgno );

			$messages[] = array(
				'uid'        => $uid,
				'msgno'      => (int) $msgno,
				'message_id' => isset( $overview[0]->message_id ) ? trim( $overview[0]->message_id ) : '',
				'subject'    => isset( $overview[0]->subject ) ? imap_utf8( $overview[0]->subject ) : '',
				'from'       => isset( $overview[0]->from ) ? imap_utf8( $overview[0]->from ) : '',
				'date'       => isset( $overview[0]->date ) ? $overview[0]->date : '',
				'body'       => $body ?: '',
			);

			$count++;
			if ( $count >= $limit ) {
				break;
			}
		}

		imap_close( $stream );

		return $messages;
	}

	/**
	 * Provide metadata previews of unseen messages without altering flags.
	 *
	 * @param array $settings Plugin settings.
	 * @param int   $limit    Max number of previews.
	 * @return array<int,array>
	 */
	public function preview_unseen_messages( array $settings, int $limit = 10 ): array {
		$messages = $this->fetch_messages( $settings, $limit );

		$previews = array();
		foreach ( $messages as $message ) {
			$previews[] = array(
				'message_id' => $message['message_id'],
				'subject'    => $message['subject'],
				'from'       => $message['from'],
				'date'       => $message['date'],
				'uid'        => $message['uid'],
				'snippet'    => $this->build_snippet( $message['body'] ),
			);
		}

		return $previews;
	}

	/**
	 * Mark provided message UIDs as seen.
	 *
	 * @param array $settings Plugin settings.
	 * @param array $uids     List of IMAP UIDs.
	 */
	public function mark_messages_seen( array $settings, array $uids ): void {
		if ( empty( $uids ) ) {
			return;
		}

		$this->validate_settings( $settings );

		$mailbox = $this->build_mailbox_string( $settings );
		$stream  = @imap_open( $mailbox, $settings['imap_username'], $settings['imap_password'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( false === $stream ) {
			return;
		}

		imap_setflag_full( $stream, implode( ',', array_map( 'intval', $uids ) ), '\\Seen', ST_UID );
		imap_close( $stream );
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

	/**
	 * Generate a short snippet from the raw body.
	 *
	 * @param string $body Message body.
	 * @return string
	 */
	private function build_snippet( string $body ): string {
		$text = wp_strip_all_tags( $body );
		$text = preg_replace( '/\s+/', ' ', $text );
		return mb_substr( trim( $text ), 0, 140 );
	}
}
