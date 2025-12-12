<?php
/**
 * Trip manager admin page.
 *
 * @package SYM_Travel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides list/detail views for trips with manual field editing.
 */
require_once __DIR__ . '/class-sym-travel-meta-mirror.php';
class SYM_Travel_Trip_Manager_Page {

	public const MENU_SLUG           = 'sym-travel-trips';
	public const ACTION_SAVE_MANUAL  = 'sym_travel_save_manual_fields';
	public const ACTION_SAVE_EXTRACTED = 'sym_travel_save_extracted_trip';

	private SYM_Travel_Trip_Repository $trip_repository;
	private SYM_Travel_Schema_Validator $schema_validator;

	/**
	 * Constructor.
	 *
	 * @param SYM_Travel_Trip_Repository $trip_repository Trip repository.
	 */
	public function __construct( SYM_Travel_Trip_Repository $trip_repository, SYM_Travel_Schema_Validator $schema_validator ) {
		$this->trip_repository   = $trip_repository;
		$this->schema_validator  = $schema_validator;
	}

	/**
	 * Register submenu page.
	 */
	public function register_menu(): void {
		add_submenu_page(
			SYM_Travel_Settings_Page::MENU_SLUG,
			__( 'Trips', 'sym-travel' ),
			__( 'Trips', 'sym-travel' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle manual fields form submission.
	 */
	public function handle_manual_fields_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'sym-travel' ) );
		}

		check_admin_referer( self::ACTION_SAVE_MANUAL );

		$pnr     = sanitize_text_field( wp_unslash( $_POST['pnr'] ?? '' ) );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( empty( $pnr ) || 0 === $post_id ) {
			wp_die( esc_html__( 'Invalid trip reference.', 'sym-travel' ) );
		}

		$keys   = isset( $_POST['manual_field_keys'] ) ? (array) $_POST['manual_field_keys'] : array();
		$values = isset( $_POST['manual_field_values'] ) ? (array) $_POST['manual_field_values'] : array();

		$manual_fields = array();
		foreach ( $keys as $index => $key ) {
			$sanitized_key = sanitize_key( wp_unslash( $key ) );
			if ( '' === $sanitized_key ) {
				continue;
			}
			$value = isset( $values[ $index ] ) ? sanitize_text_field( wp_unslash( $values[ $index ] ) ) : '';
			$manual_fields[ $sanitized_key ] = $value;
		}

		$this->trip_repository->save_manual_fields( $post_id, $manual_fields );

		$this->queue_success( __( 'Manual fields updated.', 'sym-travel' ) );
		$this->redirect_to_trip( $pnr );
	}

	/**
	 * Handle parsed data edits.
	 */
	public function handle_trip_data_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'sym-travel' ) );
		}

		check_admin_referer( self::ACTION_SAVE_EXTRACTED );

		$pnr     = sanitize_text_field( wp_unslash( $_POST['pnr'] ?? '' ) );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( empty( $pnr ) || 0 === $post_id ) {
			wp_die( esc_html__( 'Invalid trip reference.', 'sym-travel' ) );
		}

		$paths  = isset( $_POST['trip_field_path'] ) ? (array) $_POST['trip_field_path'] : array();
		$values = isset( $_POST['trip_field_value'] ) ? (array) $_POST['trip_field_value'] : array();

		$flat = array();
		foreach ( $paths as $index => $raw_path ) {
			$path = sanitize_text_field( wp_unslash( (string) $raw_path ) );
			if ( '' === $path ) {
				continue;
			}

			$value_raw    = $values[ $index ] ?? '';
			$flat[ $path ] = sanitize_text_field( wp_unslash( (string) $value_raw ) );
		}

		try {
			$structured = $this->rebuild_trip_data( $flat );
			$validated  = $this->schema_validator->validate( $structured );
			$this->trip_repository->update_trip_data( $pnr, $validated );
			$this->queue_success( __( 'Trip data updated.', 'sym-travel' ) );
		} catch ( Exception $e ) {
			$this->queue_error( sprintf( __( 'Unable to save trip: %s', 'sym-travel' ), $e->getMessage() ) );
		}

		$this->redirect_to_trip( $pnr );
	}

	/**
	 * Render page content.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sym-travel' ) );
		}

		$pnr = isset( $_GET['pnr'] ) ? sanitize_text_field( wp_unslash( $_GET['pnr'] ) ) : '';

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Trips', 'sym-travel' ) . '</h1>';
		settings_errors( 'sym_travel' );

		if ( $pnr ) {
			$this->render_detail_view( $pnr );
		} else {
			$this->render_list_view();
		}

		echo '</div>';
	}

	/**
	 * Render recent trips list.
	 */
	private function render_list_view(): void {
		$trips = $this->trip_repository->get_recent_trips();
		?>
		<p><?php esc_html_e( 'Recent trips imported from airline emails.', 'sym-travel' ); ?></p>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'PNR', 'sym-travel' ); ?></th>
					<th><?php esc_html_e( 'Status', 'sym-travel' ); ?></th>
					<th><?php esc_html_e( 'Last Imported', 'sym-travel' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'sym-travel' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $trips ) ) : ?>
					<tr>
						<td colspan="4"><?php esc_html_e( 'No trips available yet.', 'sym-travel' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $trips as $trip ) : ?>
						<tr>
							<td><?php echo esc_html( $trip->pnr ); ?></td>
							<td><?php echo esc_html( ucfirst( $trip->status ) ); ?></td>
							<td><?php echo esc_html( $trip->last_imported ); ?></td>
							<td>
								<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'pnr' => $trip->pnr ), admin_url( 'admin.php' ) ) ); ?>">
									<?php esc_html_e( 'View / Edit', 'sym-travel' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render detail view for a single trip.
	 *
	 * @param string $pnr Booking reference.
	 */
	private function render_detail_view( string $pnr ): void {
		$row = $this->trip_repository->get_trip_row( $pnr );

		if ( ! $row ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Trip not found.', 'sym-travel' ) . '</p></div>';
			$this->render_list_view();
			return;
		}

		$trip_data     = json_decode( $row->trip_data ?? '', true );
		$manual_fields = $this->trip_repository->get_manual_fields( (int) $row->post_id );
		$trip_fields   = $this->format_trip_fields( is_array( $trip_data ) ? $trip_data : array() );

		?>
		<p>
			<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG ), admin_url( 'admin.php' ) ) ); ?>">
				<?php esc_html_e( 'Back to list', 'sym-travel' ); ?>
			</a>
		</p>

		<h2><?php echo esc_html( sprintf( __( 'Trip %s', 'sym-travel' ), $pnr ) ); ?></h2>
		<p>
			<strong><?php esc_html_e( 'Status:', 'sym-travel' ); ?></strong> <?php echo esc_html( ucfirst( $row->status ) ); ?><br />
			<strong><?php esc_html_e( 'Last Imported:', 'sym-travel' ); ?></strong> <?php echo esc_html( $row->last_imported ); ?>
		</p>

		<h3><?php esc_html_e( 'Extracted Trip Data', 'sym-travel' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::ACTION_SAVE_EXTRACTED ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SAVE_EXTRACTED ); ?>" />
			<input type="hidden" name="pnr" value="<?php echo esc_attr( $pnr ); ?>" />
			<input type="hidden" name="post_id" value="<?php echo esc_attr( $row->post_id ); ?>" />
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Meta Key', 'sym-travel' ); ?></th>
						<th><?php esc_html_e( 'Value', 'sym-travel' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $trip_fields as $index => $field ) : ?>
						<tr>
							<td style="width:30%;">
								<strong><?php echo esc_html( $field['meta_key'] ); ?></strong><br />
								<code><?php echo esc_html( $field['path'] ); ?></code>
								<input type="hidden" name="trip_field_path[<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( $field['path'] ); ?>" />
							</td>
							<td>
								<input type="text" class="regular-text" name="trip_field_value[<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( $field['value'] ); ?>" />
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( __( 'Save Trip Data', 'sym-travel' ) ); ?>
		</form>

		<h3><?php esc_html_e( 'Manual Fields', 'sym-travel' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::ACTION_SAVE_MANUAL ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SAVE_MANUAL ); ?>" />
			<input type="hidden" name="pnr" value="<?php echo esc_attr( $pnr ); ?>" />
			<input type="hidden" name="post_id" value="<?php echo esc_attr( $row->post_id ); ?>" />
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Field Key', 'sym-travel' ); ?></th>
						<th><?php esc_html_e( 'Value', 'sym-travel' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					if ( ! empty( $manual_fields ) ) :
						foreach ( $manual_fields as $key => $value ) :
							?>
							<tr>
								<td><input type="text" name="manual_field_keys[]" value="<?php echo esc_attr( $key ); ?>" class="regular-text" /></td>
								<td><input type="text" name="manual_field_values[]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" /></td>
							</tr>
							<?php
						endforeach;
					endif;
					?>
					<?php for ( $i = 0; $i < 3; $i++ ) : ?>
						<tr>
							<td><input type="text" name="manual_field_keys[]" value="" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., seat', 'sym-travel' ); ?>" /></td>
							<td><input type="text" name="manual_field_values[]" value="" class="regular-text" placeholder="<?php esc_attr_e( 'Value', 'sym-travel' ); ?>" /></td>
						</tr>
					<?php endfor; ?>
				</tbody>
			</table>
			<?php submit_button( __( 'Save Manual Fields', 'sym-travel' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Prepare trip fields for editing.
	 *
	 * @param array $trip_data Parsed trip data.
	 * @return array<int,array>
	 */
	private function format_trip_fields( array $trip_data ): array {
		$flat   = SYM_Travel_Meta_Mirror::flatten_fields( $trip_data );
		$fields = array();
		$index  = 0;

		foreach ( $flat as $path => $value ) {
			$fields[] = array(
				'path'     => $path,
				'meta_key' => SYM_Travel_Meta_Mirror::build_meta_key( $path ),
				'value'    => $value,
			);
			$index++;
		}

		return $fields;
	}

	/**
	 * Rebuild structured trip data from flattened values.
	 *
	 * @param array<string,string> $flat_values Path => value.
	 * @return array
	 */
	private function rebuild_trip_data( array $flat_values ): array {
		$structured = array();

		foreach ( $flat_values as $path => $value ) {
			$segments = array_map( 'trim', explode( '.', $path ) );
			$ref      = &$structured;

			foreach ( $segments as $segment ) {
				if ( '' === $segment ) {
					continue;
				}

				$is_numeric = ctype_digit( $segment );
				$segment    = $is_numeric ? (int) $segment : $segment;

				if ( ! isset( $ref[ $segment ] ) || ! is_array( $ref[ $segment ] ) ) {
					$ref[ $segment ] = array();
				}

				$ref = &$ref[ $segment ];
			}

			$ref = $value;
			unset( $ref );
		}

		return $structured;
	}

	/**
	 * Queue success notice.
	 *
	 * @param string $message Message.
	 */
	private function queue_success( string $message ): void {
		add_settings_error( 'sym_travel', 'sym_travel_trip_manager', $message, 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
	}

	/**
	 * Queue error notice.
	 *
	 * @param string $message Message.
	 */
	private function queue_error( string $message ): void {
		add_settings_error( 'sym_travel', 'sym_travel_trip_manager', $message, 'error' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
	}

	/**
	 * Redirect to trip detail view.
	 *
	 * @param string $pnr PNR.
	 */
	private function redirect_to_trip( string $pnr ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => self::MENU_SLUG,
					'pnr'  => $pnr,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
