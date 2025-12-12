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
		$response = wp_remote_get(
			esc_url_raw( $url ),
			array(
				'timeout'     => 30,
				'redirection' => 5,
				'headers'     => array(
					'User-Agent'      => 'SYM-Travel/1.0 (+https://symclients.dk)',
					'Accept-Language' => 'en-US,en;q=0.8',
					'Referer'         => 'https://www.google.com/',
				),
				'cookies'     => array(
					new WP_Http_Cookie(
						array(
							'name'   => 'notice_preferences',
							'value'  => '2:',
							'domain' => '.tripit.com',
						)
					),
					new WP_Http_Cookie(
						array(
							'name'   => 'notice_gdpr_prefs',
							'value'  => '0:',
							'domain' => '.tripit.com',
						)
					),
					new WP_Http_Cookie(
						array(
							'name'   => 'notice_welcome',
							'value'  => 'true',
							'domain' => '.tripit.com',
						)
					),
				),
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

		throw new RuntimeException( 'Unable to locate TripIt JSON payload.' );
	}
}
