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
class SYM_Travel_Trip_Manager_Page {

	public const MENU_SLUG          = 'sym-travel-trips';
	public const ACTION_SAVE_MANUAL = 'sym_travel_save_manual_fields';

	private SYM_Travel_Trip_Repository $trip_repository;

	/**
	 * Constructor.
	 *
	 * @param SYM_Travel_Trip_Repository $trip_repository Trip repository.
	 */
	public function __construct( SYM_Travel_Trip_Repository $trip_repository ) {
		$this->trip_repository = $trip_repository;
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

		add_settings_error(
			'sym_travel',
			'sym_travel_manual_fields',
			__( 'Manual fields updated.', 'sym-travel' ),
			'updated'
		);
		set_transient( 'settings_errors', get_settings_errors(), 30 );

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
		<textarea readonly rows="15" style="width:100%;font-family:monospace;"><?php echo esc_textarea( wp_json_encode( $trip_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></textarea>

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
}
