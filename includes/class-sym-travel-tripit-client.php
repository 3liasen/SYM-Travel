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
				'timeout' => 30,
				'headers' => array(
					'User-Agent'      => 'SYM-Travel/1.0 (+https://symclients.dk)',
					'Accept-Language' => 'en-US,en;q=0.8',
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
			'/window\.__PRELOADED_STATE__\s*=\s*(\{.+?\})\s*;?/s',
			'/var\s+tripJSON\s*=\s*(\{.+?\});/s',
			'/data-state=("|\')(.*?)\1/s',
		);

		foreach ( $candidates as $pattern ) {
			if ( preg_match( $pattern, $body, $matches ) ) {
				$encoded = $matches[ count( $matches ) - 1 ];
				$decoded = json_decode( html_entity_decode( $encoded ), true );
				if ( null !== $decoded && is_array( $decoded ) ) {
					return $decoded;
				}
			}
		}

		throw new RuntimeException( 'Unable to locate TripIt JSON payload.' );
	}
}
