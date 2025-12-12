<?php
/**
 * Trip persistence layer.
 *
 * @package SYM_Travel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-sym-travel-meta-mirror.php';

/**
 * Handles canonical table writes and CPT/meta mirroring.
 */
class SYM_Travel_Trip_Repository {

	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Meta mirror helper.
	 *
	 * @var SYM_Travel_Meta_Mirror
	 */
	private SYM_Travel_Meta_Mirror $meta_mirror;
	private const TRIPIT_LINK_META = '_sym_travel_tripit_link';
	private const TRIPIT_JSON_META = '_sym_travel_tripit_json';

	/**
	 * Constructor.
	 *
	 * @param wpdb|null                    $db          WPDB dependency (optional).
	 * @param SYM_Travel_Meta_Mirror|null  $meta_mirror Meta mirror helper (optional).
	 */
	public function __construct( ?wpdb $db = null, ?SYM_Travel_Meta_Mirror $meta_mirror = null ) {
		global $wpdb;
		$this->wpdb        = $db ?? $wpdb;
		$this->meta_mirror = $meta_mirror ?? new SYM_Travel_Meta_Mirror();
	}

	/**
	 * Upsert a trip by PNR, mirroring extracted/meta data.
	 *
	 * @param array $payload Associative structure with keys:
	 *                       - pnr (string, required)
	 *                       - status (string)
	 *                       - trip_data (array)
	 *                       - extracted_fields (array for mirroring)
	 *                       - manual_fields (array preserved across imports)
	 * @return int Trip post ID.
	 */
	public function upsert_trip( array $payload ): int {
		$pnr = isset( $payload['pnr'] ) ? sanitize_text_field( $payload['pnr'] ) : '';
		if ( '' === $pnr ) {
			throw new InvalidArgumentException( 'PNR is required for upsert.' );
		}

		$status     = isset( $payload['status'] ) ? sanitize_key( $payload['status'] ) : 'parsed';
		$trip_data  = wp_json_encode( $payload['trip_data'] ?? array() );
		$extracted  = is_array( $payload['extracted_fields'] ?? null ) ? $payload['extracted_fields'] : array();
		$manual     = is_array( $payload['manual_fields'] ?? null ) ? $payload['manual_fields'] : array();
		$row        = $this->get_trip_row( $pnr );
		$post_id    = $this->ensure_post( $pnr, $row ? (int) $row->post_id : 0 );
		$table      = $this->wpdb->prefix . 'sym_travel_trips';
		$timestamp  = current_time( 'mysql' );

		$data = array(
			'pnr'           => $pnr,
			'status'        => $status,
			'trip_data'     => $trip_data,
			'post_id'       => $post_id,
			'last_imported' => $timestamp,
			'updated_at'    => $timestamp,
		);

		if ( $row ) {
			$updated = $this->wpdb->update(
				$table,
				$data,
				array( 'pnr' => $pnr ),
				array( '%s', '%s', '%s', '%d', '%s', '%s' ),
				array( '%s' )
			);

			if ( false === $updated ) {
				throw new RuntimeException( 'Failed to update trip row.' );
			}
		} else {
			$data['created_at'] = $timestamp;
			$inserted           = $this->wpdb->insert(
				$table,
				$data,
				array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
			);

			if ( false === $inserted ) {
				throw new RuntimeException( 'Failed to insert trip row.' );
			}
		}

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $pnr,
			)
		);

		$this->meta_mirror->mirror_extracted_fields( $post_id, $extracted );
		$this->meta_mirror->store_manual_fields( $post_id, $manual );

		return $post_id;
	}

	/**
	 * Retrieve a trip row by PNR.
	 *
	 * @param string $pnr Booking reference.
	 * @return stdClass|null
	 */
	public function get_trip_row( string $pnr ): ?stdClass {
		$table = $this->wpdb->prefix . 'sym_travel_trips';
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$table} WHERE pnr = %s",
			$pnr
		);

		$row = $this->wpdb->get_row( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $row ?: null;
	}

	/**
	 * Ensure a CPT post exists for the provided PNR.
	 *
	 * @param string $pnr     Booking reference.
	 * @param int    $post_id Existing post ID (if any).
	 * @return int
	 */
	private function ensure_post( string $pnr, int $post_id ): int {
		$post = $post_id > 0 ? get_post( $post_id ) : null;

		if ( ! $post || 'trips' !== $post->post_type ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'trips',
					'post_title'  => $pnr,
					'post_status' => 'private',
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				throw new RuntimeException( 'Failed to create trip post.' );
			}
		}

		return (int) $post_id;
	}

	/**
	 * Retrieve latest trip entry.
	 *
	 * @return array|null
	 */
	public function get_latest_trip(): ?array {
		$table = $this->wpdb->prefix . 'sym_travel_trips';
		$query = "SELECT * FROM {$table} ORDER BY last_imported DESC LIMIT 1";

		$row = $this->wpdb->get_row( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( ! $row ) {
			return null;
		}

		$trip_data = json_decode( $row->trip_data ?? '', true );

		return array(
			'pnr'           => $row->pnr,
			'status'        => $row->status,
			'trip_data'     => is_array( $trip_data ) ? $trip_data : array(),
			'post_id'       => (int) $row->post_id,
			'last_imported' => $row->last_imported,
		);
	}

	/**
	 * Update stored trip data and mirrored meta.
	 *
	 * @param string $pnr       Booking reference.
	 * @param array  $trip_data Parsed payload.
	 */
	public function update_trip_data( string $pnr, array $trip_data ): void {
		$row = $this->get_trip_row( $pnr );
		if ( ! $row ) {
			throw new RuntimeException( 'Trip not found.' );
		}

		$table     = $this->wpdb->prefix . 'sym_travel_trips';
		$timestamp = current_time( 'mysql' );

		$updated = $this->wpdb->update(
			$table,
			array(
				'trip_data'  => wp_json_encode( $trip_data ),
				'updated_at' => $timestamp,
			),
			array( 'pnr' => $pnr ),
			array( '%s', '%s' ),
			array( '%s' )
		);

		if ( false === $updated ) {
			throw new RuntimeException( 'Failed to update trip data.' );
		}

		$this->meta_mirror->mirror_extracted_fields( (int) $row->post_id, $trip_data );
	}

	/**
	 * Synchronize extracted meta for all trips.
	 */
	public function sync_all_trip_meta(): void {
		$table = $this->wpdb->prefix . 'sym_travel_trips';
		$rows  = $this->wpdb->get_results( "SELECT post_id, trip_data FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( empty( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$post_id = (int) $row->post_id;
			if ( $post_id <= 0 ) {
				continue;
			}

			$trip_data = json_decode( $row->trip_data ?? '', true );
			if ( ! is_array( $trip_data ) ) {
				continue;
			}

			$this->meta_mirror->mirror_extracted_fields( $post_id, $trip_data );
		}
	}

	/**
	 * Persist manual fields for a trip post.
	 *
	 * @param int   $post_id       Trip post ID.
	 * @param array $manual_fields Key/value pairs.
	 */
	public function save_manual_fields( int $post_id, array $manual_fields ): void {
		$this->meta_mirror->replace_manual_fields( $post_id, $manual_fields );
	}

	/**
	 * Retrieve manual fields for a post.
	 *
	 * @param int $post_id Trip post ID.
	 * @return array
	 */
	public function get_manual_fields( int $post_id ): array {
		return $this->meta_mirror->get_manual_fields( $post_id );
	}

	/**
	 * Get a list of recent trips.
	 *
	 * @param int $limit Number of trips to return.
	 * @return array<int,stdClass>
	 */
	public function get_recent_trips( int $limit = 20 ): array {
		$table = $this->wpdb->prefix . 'sym_travel_trips';
		$query = $this->wpdb->prepare(
			"SELECT pnr, status, last_imported, post_id FROM {$table} ORDER BY last_imported DESC LIMIT %d",
			$limit
		);

		$rows = $this->wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Store TripIt link and payload for a trip.
	 *
	 * @param int    $post_id Trip CPT post ID.
	 * @param string $link    TripIt link.
	 * @param array  $payload Parsed TripIt payload.
	 */
	public function store_tripit_payload( int $post_id, string $link, array $payload ): void {
		update_post_meta( $post_id, self::TRIPIT_LINK_META, esc_url_raw( $link ) );
		update_post_meta( $post_id, self::TRIPIT_JSON_META, wp_json_encode( $payload ) );
	}

	/**
	 * Retrieve TripIt link.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function get_tripit_link( int $post_id ): string {
		return (string) get_post_meta( $post_id, self::TRIPIT_LINK_META, true );
	}

	/**
	 * Retrieve TripIt JSON payload.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function get_tripit_json( int $post_id ): array {
		$raw = get_post_meta( $post_id, self::TRIPIT_JSON_META, true );
		$val = json_decode( (string) $raw, true );
		return is_array( $val ) ? $val : array();
	}

	/**
	 * Create or update the JetEngine CPT entry for a trip.
	 *
	 * @param string $pnr Booking reference.
	 * @return int JetEngine post ID.
	 */
	public function sync_jetengine_trip( string $pnr ): int {
		$row = $this->get_trip_row( $pnr );
		if ( ! $row ) {
			throw new RuntimeException( 'Trip not found.' );
		}

		$trip_data = json_decode( $row->trip_data ?? '', true );
		if ( ! is_array( $trip_data ) ) {
			throw new RuntimeException( 'Trip data is invalid.' );
		}

		$existing_post_id = $this->find_jetengine_post_by_pnr( $pnr );
		$manual_fields    = $this->get_manual_fields( (int) $row->post_id );

		$post_args = array(
			'post_type'   => 'tjah-trips',
			'post_title'  => $pnr . ' – ' . ( $trip_data['airline'] ?? __( 'Trip', 'sym-travel' ) ),
			'post_status' => 'publish',
		);

		if ( $existing_post_id > 0 ) {
			$post_args['ID'] = $existing_post_id;
			$post_id         = wp_update_post( $post_args, true );
		} else {
			$post_id = wp_insert_post( $post_args, true );
		}

		if ( is_wp_error( $post_id ) ) {
			throw new RuntimeException( $post_id->get_error_message() );
		}

		$passengers = $trip_data['passengers'] ?? array();
		$segments   = array();
		if ( ! empty( $trip_data['journeys'][0]['segments'] ) && is_array( $trip_data['journeys'][0]['segments'] ) ) {
			$segments = $trip_data['journeys'][0]['segments'];
		}

		$segment_one = $segments[0] ?? array();

		$segment_from_to = '';
		if ( ! empty( $segment_one ) ) {
			$departure_code = $this->extract_airport_code( $segment_one['departure'] ?? '' );
			$arrival_code   = $this->extract_airport_code( $segment_one['arrival'] ?? '' );
			if ( $departure_code && $arrival_code ) {
				$segment_from_to = $departure_code . '-' . $arrival_code;
			}
		}

		$meta_map = array(
			'tjah_trips_pnr'                      => $trip_data['pnr'] ?? '',
			'tjah_trips_airline'                  => $trip_data['airline'] ?? '',
			'tjah_trips_passenger_1'              => $passengers[0]['name'] ?? '',
			'tjah_trips_passenger_2'              => $passengers[1]['name'] ?? '',
			'tjah_trips_segment_1_departure_airport' => $segment_one['departure'] ?? '',
			'tjah_trips_segment_1_arrival_airport'   => $segment_one['arrival'] ?? '',
			'tjah_trips_segment_1_departure_time'    => $segment_one['departure_time'] ?? '',
			'tjah_trips_segment_1_arrival_time'      => $segment_one['arrival_time'] ?? '',
			'tjah_trips_segment_1_flight_number'     => $segment_one['flight_number'] ?? '',
			'tjah_trips_segment_1_class'             => $segment_one['class'] ?? '',
			'tjah_trips_segment_1_passenger_1_seat'  => $manual_fields['passenger_1_seat'] ?? '',
			'tjah_trips_segment_1_passenger_2_seat'  => $manual_fields['passenger_2_seat'] ?? '',
			'tjah_trips_segment_1_from_to'           => $segment_from_to,
		);

		foreach ( $meta_map as $meta_key => $value ) {
			update_post_meta( $post_id, $meta_key, sanitize_text_field( (string) $value ) );
		}

		return (int) $post_id;
	}

	/**
	 * Locate an existing JetEngine trip post by PNR.
	 *
	 * @param string $pnr Booking reference.
	 * @return int Post ID or 0.
	 */
	private function find_jetengine_post_by_pnr( string $pnr ): int {
		$posts = get_posts(
			array(
				'post_type'      => 'tjah-trips',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_query'     => array(
					array(
						'key'   => 'tjah_trips_pnr',
						'value' => $pnr,
					),
				),
				'fields'         => 'ids',
			)
		);

		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}

	/**
	 * Extract three-letter airport code from a string.
	 *
	 * @param string $value Raw airport string.
	 * @return string
	 */
	private function extract_airport_code( string $value ): string {
		if ( preg_match( '/([A-Z]{3})\)/', $value, $matches ) ) {
			return $matches[1];
		}

		if ( preg_match( '/([A-Z]{3})$/', $value, $matches ) ) {
			return $matches[1];
		}

		return '';
	}
}
