<?php
/**
 * Validates parsed trip payloads.
 *
 * @package SYM_Travel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enforces schema rules for parsed trips.
 */
class SYM_Travel_Schema_Validator {

	/**
	 * Ensure payload matches required schema.
	 *
	 * @param array $payload Parsed response.
	 * @return array Validated payload.
	 */
	public function validate( array $payload ): array {
		$required_top = array( 'pnr', 'airline', 'passengers', 'journeys' );
		foreach ( $required_top as $key ) {
			if ( empty( $payload[ $key ] ) ) {
				throw new InvalidArgumentException( sprintf( 'Missing required field: %s', $key ) );
			}
		}

		if ( ! is_array( $payload['passengers'] ) || empty( $payload['passengers'] ) ) {
			throw new InvalidArgumentException( 'Passengers list must be a non-empty array.' );
		}

		if ( ! is_array( $payload['journeys'] ) || empty( $payload['journeys'] ) ) {
			throw new InvalidArgumentException( 'Journeys list must be a non-empty array.' );
		}

		foreach ( $payload['passengers'] as $passenger ) {
			if ( empty( $passenger['name'] ) ) {
				throw new InvalidArgumentException( 'Passenger name is required.' );
			}
		}

		foreach ( $payload['journeys'] as $journey ) {
			if ( empty( $journey['segments'] ) || ! is_array( $journey['segments'] ) ) {
				throw new InvalidArgumentException( 'Journey segments missing or invalid.' );
			}

			foreach ( $journey['segments'] as $segment ) {
				$this->validate_segment( $segment );
			}
		}

		return $payload;
	}

	/**
	 * Validate a single segment.
	 *
	 * @param array $segment Segment data.
	 */
	private function validate_segment( array $segment ): void {
		$required = array( 'flight_number', 'departure', 'arrival', 'departure_time', 'arrival_time' );
		foreach ( $required as $field ) {
			if ( empty( $segment[ $field ] ) ) {
				throw new InvalidArgumentException( sprintf( 'Segment missing field: %s', $field ) );
			}
		}
	}
}
