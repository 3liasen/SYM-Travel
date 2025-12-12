<?php
/**
 * Admin page listing recent email retrieval statuses.
 *
 * @package SYM_Travel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays statuses for fetched emails/log entries.
 */
class SYM_Travel_Email_Status_Page {

	public const MENU_SLUG = 'sym-travel-email-status';
	private SYM_Travel_Log_Repository $log_repository;

	/**
	 * Constructor.
	 *
	 * @param SYM_Travel_Log_Repository $log_repository Log repository.
	 */
	public function __construct( SYM_Travel_Log_Repository $log_repository ) {
		$this->log_repository = $log_repository;
	}

	/**
	 * Register submenu page.
	 */
	public function register_menu(): void {
		add_submenu_page(
			SYM_Travel_Settings_Page::MENU_SLUG,
			__( 'Email Status', 'sym-travel' ),
			__( 'Email Status', 'sym-travel' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the status page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sym-travel' ) );
		}

		$entries = $this->log_repository->get_recent_entries();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Retrieved Email Status', 'sym-travel' ); ?></h1>
			<?php settings_errors( 'sym_travel' ); ?>
			<section>
				<h2><?php esc_html_e( 'Manual Fetch & Import', 'sym-travel' ); ?></h2>
				<p><?php esc_html_e( 'Connect to IMAP, parse airline emails, and store trips. Use this when new emails have arrived.', 'sym-travel' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( SYM_Travel_Settings_Page::ACTION_FETCH ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( SYM_Travel_Settings_Page::ACTION_FETCH ); ?>" />
					<?php submit_button( __( 'Fetch & Import Emails', 'sym-travel' ), 'primary', 'submit', false ); ?>
				</form>
			</section>
			<hr />
			<p><?php esc_html_e( 'Recent IMAP/OpenAI/import events help you track the status of processed emails.', 'sym-travel' ); ?></p>
			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Timestamp', 'sym-travel' ); ?></th>
						<th><?php esc_html_e( 'Context', 'sym-travel' ); ?></th>
						<th><?php esc_html_e( 'Severity', 'sym-travel' ); ?></th>
						<th><?php esc_html_e( 'PNR', 'sym-travel' ); ?></th>
						<th><?php esc_html_e( 'Message ID', 'sym-travel' ); ?></th>
						<th><?php esc_html_e( 'Message', 'sym-travel' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $entries ) ) : ?>
						<tr>
							<td colspan="6"><?php esc_html_e( 'No log entries found yet.', 'sym-travel' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $entries as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( $entry->created_at ); ?></td>
								<td><?php echo esc_html( strtoupper( $entry->context ) ); ?></td>
								<td><?php echo esc_html( strtoupper( $entry->severity ) ); ?></td>
								<td><?php echo esc_html( $entry->pnr ?? '-' ); ?></td>
								<td><?php echo esc_html( $entry->message_id ?? '-' ); ?></td>
								<td><?php echo esc_html( $entry->message ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
