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
			throw new RuntimeException( $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			throw new RuntimeException( 'TripIt response was empty.' );
		}

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

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->log_debug_payload( $body );
		}

		throw new RuntimeException( 'Unable to locate TripIt JSON payload.' );
	}

	/**
	 * Log trimmed body for debugging purposes.
	 *
	 * @param string $body HTML body.
	 */
	private function log_debug_payload( string $body ): void {
		$excerpt = substr( $body, 0, 2000 );
		error_log( '[SYM Travel] TripIt raw excerpt: ' . $excerpt ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
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
			}
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
