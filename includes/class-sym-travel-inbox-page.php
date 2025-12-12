<?php
/**
 * IMAP inbox preview admin page.
 *
 * @package SYM_Travel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays unseen airline emails for verification.
 */
class SYM_Travel_Inbox_Page {

	private const MENU_SLUG = 'sym-travel-inbox';

	private SYM_Travel_IMAP_Client $imap_client;

	/**
	 * Constructor.
	 *
	 * @param SYM_Travel_IMAP_Client $imap_client IMAP adapter.
	 */
	public function __construct( SYM_Travel_IMAP_Client $imap_client ) {
		$this->imap_client = $imap_client;
	}

	/**
	 * Register submenu.
	 */
	public function register_menu(): void {
		add_submenu_page(
			SYM_Travel_Settings_Page::MENU_SLUG,
			__( 'Inbox Preview', 'sym-travel' ),
			__( 'Inbox Preview', 'sym-travel' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render inbox preview.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sym-travel' ) );
		}

		$settings = get_option( 'sym_travel_settings', array() );
		$messages = array();
		$error    = '';

		if ( isset( $_POST['sym_travel_refresh_inbox'] ) ) {
			check_admin_referer( 'sym_travel_refresh_inbox' );
		}

		try {
			$messages = $this->imap_client->preview_unseen_messages( $settings, 10 );
		} catch ( Exception $e ) {
			$error = $e->getMessage();
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'IMAP Inbox Preview', 'sym-travel' ); ?></h1>
			<p><?php esc_html_e( 'Review unseen airline emails before running the full import. This preview does not mark emails as read.', 'sym-travel' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'sym_travel_refresh_inbox' ); ?>
				<input type="hidden" name="sym_travel_refresh_inbox" value="1" />
				<?php submit_button( __( 'Refresh Preview', 'sym-travel' ), 'secondary', 'submit', false ); ?>
			</form>
			<?php if ( $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>
			<table class="widefat fixed striped" style="margin-top:20px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'sym-travel' ); ?></th>
						<th><?php esc_html_e( 'From', 'sym-travel' ); ?></th>
						<th><?php esc_html_e( 'Subject', 'sym-travel' ); ?></th>
						<th><?php esc_html_e( 'Message ID', 'sym-travel' ); ?></th>
						<th><?php esc_html_e( 'Snippet', 'sym-travel' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $messages ) ) : ?>
						<tr>
							<td colspan="5"><?php esc_html_e( 'No unseen emails found.', 'sym-travel' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $messages as $message ) : ?>
							<tr>
								<td><?php echo esc_html( $message['date'] ); ?></td>
								<td><?php echo esc_html( $message['from'] ); ?></td>
								<td><?php echo esc_html( $message['subject'] ); ?></td>
								<td><?php echo esc_html( $message['message_id'] ?: '-' ); ?></td>
								<td><?php echo esc_html( $message['snippet'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
