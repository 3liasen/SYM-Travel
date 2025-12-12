<?php
/**
 * TripIt scraping helper.
 *
 * @package SYM_Travel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches and parses publicly shared TripIt itineraries.
 */
class SYM_Travel_TripIt_Client {

	/**
	 * Fetch and parse a TripIt public link.
	 *
	 * @param string $url TripIt public URL.
	 * @return array Parsed payload.
	 */
	public function fetch_trip( string $url ): array {
		$this->write_debug_log( 'Fetching TripIt URL: ' . $url );
		$cookie_jar = $this->perform_consent_request();

		$response = wp_remote_get(
			esc_url_raw( $url ),
			array(
				'timeout'     => 30,
				'redirection' => 5,
				'headers'     => array(
					'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
					'Accept-Language' => 'en-US,en;q=0.8',
					'Referer'         => 'https://www.google.com/',
					'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				),
				'cookies'     => $cookie_jar,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->write_debug_log( 'TripIt request error: ' . $response->get_error_message() );
			throw new RuntimeException( $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			$this->write_debug_log( 'TripIt response body empty.' );
			throw new RuntimeException( 'TripIt response was empty.' );
		}

		$this->write_debug_payload( $body, wp_remote_retrieve_response_code( $response ) );

		$json = $this->extract_json_payload( $body );

		return $json;
	}

	/**
	 * Attempt to find JSON blob inside TripIt markup.
	 *
	 * @param string $body HTML body.
	 * @return array
	 */
	private function extract_json_payload( string $body ): array {
		$candidates = array(
			array(
				'pattern'  => '/<script[^>]+id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s',
				'callback' => static function ( $match ) {
					return $match[1];
				},
			),
			array(
				'pattern'  => '/window\.__PRELOADED_STATE__\s*=\s*(\{.+?\})\s*;?/s',
				'callback' => static function ( $match ) {
					return $match[1];
				},
			),
			array(
				'pattern'  => '/var\s+tripJSON\s*=\s*(\{.+?\});/s',
				'callback' => static function ( $match ) {
					return $match[1];
				},
			),
			array(
				'pattern'  => '/data-state=("|\')(.*?)\1/s',
				'callback' => static function ( $match ) {
					return html_entity_decode( $match[2] );
				},
			),
		);

		foreach ( $candidates as $candidate ) {
			if ( preg_match( $candidate['pattern'], $body, $matches ) ) {
				$json_string = call_user_func( $candidate['callback'], $matches );
				$decoded     = json_decode( $json_string, true );
				if ( null !== $decoded && is_array( $decoded ) ) {
					return $decoded;
				}
			}
		}

		$this->write_debug_log( 'Unable to locate TripIt JSON payload.' );
		throw new RuntimeException( 'Unable to locate TripIt JSON payload.' );
	}

	/**
	 * Log trimmed body for debugging purposes.
	 *
	 * @param string $body HTML body.
	 * @param int    $status HTTP status.
	 */
	private function write_debug_payload( string $body, int $status = 0 ): void {
		$excerpt = substr( $body, 0, 2000 );
		$this->write_debug_log( sprintf( 'TripIt response (%d): %s', $status, $excerpt ) );
	}

	/**
	 * Write debug log line.
	 *
	 * @param string $message Message.
	 */
	private function write_debug_log( string $message ): void {
		$line     = sprintf( '[SYM Travel] %s', $message ) . PHP_EOL;
		$log_file = trailingslashit( WP_CONTENT_DIR ) . 'debug.log';
		error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $line, 3, $log_file ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Perform TrustArc consent request and return cookies.
	 *
	 * @return array<WP_Http_Cookie>
	 */
	private function perform_consent_request(): array {
		$cookies = array();

		$consent_response = wp_remote_post(
			'https://consent.trustarc.com/v2/notice/accept',
			array(
				'timeout' => 15,
				'headers' => array(
					'User-Agent'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'publisher'     => 'tripit.com',
						'noticeId'      => 'aWwfbXl2',
						'siteId'        => 'tripit.com',
						'consentType'   => 'accept',
						'country'       => 'DK',
						'language'      => 'da',
						'cookieVersion' => '1.0.0',
					)
				),
			)
		);

		if ( ! is_wp_error( $consent_response ) ) {
			$raw_cookies = wp_remote_retrieve_cookies( $consent_response );
			if ( is_array( $raw_cookies ) ) {
				$cookies = array_merge( $cookies, $raw_cookies );
				$this->write_debug_log( 'Received consent cookies from TrustArc.' );
			}
		} else {
			$this->write_debug_log( 'Consent request error: ' . $consent_response->get_error_message() );
		}

		$cookies[] = new WP_Http_Cookie(
			array(
				'name'   => 'notice_welcome',
				'value'  => 'true',
				'domain' => '.tripit.com',
			)
		);

		return $cookies;
	}
}
