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
		foreach ( $extracted_data as $key => $value ) {
			$meta_key = self::EXTRACTED_PREFIX . sanitize_key( $key );
			update_post_meta( $post_id, $meta_key, maybe_serialize( $value ) );
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
	 * Retrieve previously stored manual fields.
	 *
	 * @param int $post_id Trip post ID.
	 * @return array
	 */
	public function get_manual_fields( int $post_id ): array {
		$stored = get_post_meta( $post_id, self::MANUAL_META_KEY, true );
		return is_array( $stored ) ? $stored : array();
	}
}
