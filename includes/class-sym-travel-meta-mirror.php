<?php
/**
 * Handles mirroring extracted trip fields into post meta.
 *
 * @package SYM_Travel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Syncs parsed data and preserves manual-only fields.
 */
class SYM_Travel_Meta_Mirror {

	private const EXTRACTED_PREFIX = '_sym_travel_field_';
	private const MANUAL_META_KEY  = '_sym_travel_manual_fields';

	/**
	 * Mirror extracted data into Elementor-friendly meta.
	 *
	 * @param int   $post_id        Trip post ID.
	 * @param array $extracted_data Parsed fields keyed by identifier.
	 */
	public function mirror_extracted_fields( int $post_id, array $extracted_data ): void {
		$this->wipe_existing_meta( $post_id );

		$flat_fields = self::flatten_fields( $extracted_data );

		foreach ( $flat_fields as $path => $value ) {
			$meta_key = self::build_meta_key( $path );
			update_post_meta( $post_id, $meta_key, $value );
		}
	}

	/**
	 * Store manual-only fields without letting imports overwrite them.
	 *
	 * @param int   $post_id      Trip post ID.
	 * @param array $manual_fields Associative array of manual fields.
	 */
	public function store_manual_fields( int $post_id, array $manual_fields ): void {
		if ( empty( $manual_fields ) ) {
			return;
		}

		$existing      = $this->get_manual_fields( $post_id );
		$merged        = array_merge( $existing, $manual_fields );
		$sanitized_map = array();

		foreach ( $merged as $key => $value ) {
			$sanitized_map[ sanitize_key( $key ) ] = sanitize_text_field( (string) $value );
		}

		update_post_meta( $post_id, self::MANUAL_META_KEY, $sanitized_map );
	}

	/**
	 * Replace manual fields with a new sanitized map.
	 *
	 * @param int   $post_id       Trip post ID.
	 * @param array $manual_fields Manual field map.
	 */
	public function replace_manual_fields( int $post_id, array $manual_fields ): void {
		$sanitized_map = array();
		foreach ( $manual_fields as $key => $value ) {
			$sanitized_key = sanitize_key( $key );
			if ( '' === $sanitized_key ) {
				continue;
			}
			$sanitized_map[ $sanitized_key ] = sanitize_text_field( (string) $value );
		}

		update_post_meta( $post_id, self::MANUAL_META_KEY, $sanitized_map );
	}

	/**
	 * Retrieve previously stored manual fields.
	 *
	 * @param int $post_id Trip post ID.
	 * @return array
	 */
	public function get_manual_fields( int $post_id ): array {
		$stored = get_post_meta( $post_id, self::MANUAL_META_KEY, true );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Convert structured trip data to a flat map of paths => values.
	 *
	 * @param array $data Trip data.
	 * @return array<string,string>
	 */
	public static function flatten_fields( array $data ): array {
		$flat = array();

		foreach ( $data as $key => $value ) {
			self::flatten_value( (string) $key, $value, $flat );
		}

		return $flat;
	}

	/**
	 * Build the meta key for a flattened path.
	 *
	 * @param string $path Field path (e.g., passengers.0.name).
	 * @return string
	 */
	public static function build_meta_key( string $path ): string {
		$normalized = strtolower( preg_replace( '/[^a-z0-9]+/i', '_', $path ) );
		$normalized = trim( $normalized, '_' );

		if ( '' === $normalized ) {
			$normalized = 'value';
		}

		return self::EXTRACTED_PREFIX . $normalized;
	}

	/**
	 * Recursively flatten trip data.
	 *
	 * @param string $path Current path.
	 * @param mixed  $value Value to flatten.
	 * @param array  $flat  Reference to flat map.
	 */
	private static function flatten_value( string $path, $value, array &$flat ): void {
		if ( is_array( $value ) ) {
			foreach ( $value as $child_key => $child_value ) {
				$child_path = '' === $path ? (string) $child_key : $path . '.' . $child_key;
				self::flatten_value( (string) $child_path, $child_value, $flat );
			}
			return;
		}

		if ( is_bool( $value ) ) {
			$flat[ $path ] = $value ? '1' : '0';
		} elseif ( null === $value ) {
			$flat[ $path ] = '';
		} else {
			$flat[ $path ] = (string) $value;
		}
	}

	/**
	 * Remove existing extracted meta keys for a post.
	 *
	 * @param int $post_id Post ID.
	 */
	private function wipe_existing_meta( int $post_id ): void {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
				$post_id,
				$wpdb->esc_like( self::EXTRACTED_PREFIX ) . '%'
			)
		);
	}
}
