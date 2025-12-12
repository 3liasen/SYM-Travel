<?php
/**
 * Admin page that displays the latest parsed trip JSON.
 *
 * @package SYM_Travel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows latest parsed JSON payload for verification.
 */
class SYM_Travel_Latest_JSON_Page {

	private const MENU_SLUG = 'sym-travel-latest-json';

	private SYM_Travel_Trip_Repository $trip_repository;

	/**
	 * Constructor.
	 *
	 * @param SYM_Travel_Trip_Repository $trip_repository Repo instance.
	 */
	public function __construct( SYM_Travel_Trip_Repository $trip_repository ) {
		$this->trip_repository = $trip_repository;
	}

	/**
	 * Register submenu.
	 */
	public function register_menu(): void {
		add_submenu_page(
			SYM_Travel_Settings_Page::MENU_SLUG,
			__( 'Latest JSON Result', 'sym-travel' ),
			__( 'Latest JSON Result', 'sym-travel' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render content.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sym-travel' ) );
		}

		$latest = $this->trip_repository->get_latest_trip();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Latest JSON Result', 'sym-travel' ); ?></h1>
			<?php if ( ! $latest ) : ?>
				<p><?php esc_html_e( 'No trips have been imported yet.', 'sym-travel' ); ?></p>
			<?php else : ?>
				<p>
					<strong><?php esc_html_e( 'PNR:', 'sym-travel' ); ?></strong>
					<?php echo esc_html( $latest['pnr'] ); ?>
					&nbsp;|&nbsp;
					<strong><?php esc_html_e( 'Last Imported:', 'sym-travel' ); ?></strong>
					<?php echo esc_html( $latest['last_imported'] ); ?>
				</p>
				<?php if ( $latest['post_id'] ) : ?>
					<p>
						<a class="button" href="<?php echo esc_url( get_edit_post_link( $latest['post_id'] ) ); ?>">
							<?php esc_html_e( 'Edit Trip Post', 'sym-travel' ); ?>
						</a>
					</p>
				<?php endif; ?>
				<textarea readonly rows="20" style="width:100%; font-family: monospace;"><?php echo esc_textarea( wp_json_encode( $latest['trip_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></textarea>
			<?php endif; ?>
		</div>
		<?php
	}
}
