<?php
/**
 * Settings page for SYM Travel.
 *
 * @package SYM_Travel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin settings registration and rendering.
 */
class SYM_Travel_Settings_Page {

	private const OPTION_KEY          = 'sym_travel_settings';
	private const SETTINGS_GROUP      = 'sym_travel_settings_group';
	public const MENU_SLUG           = 'sym-travel';
	private const ACTION_TEST_IMAP    = 'sym_travel_test_imap';
	private const ACTION_TEST_OPENAI  = 'sym_travel_test_openai';
	public const ACTION_FETCH         = 'sym_travel_fetch_emails';

	/**
	 * Register menu pages.
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'SYM Travel', 'sym-travel' ),
			__( 'SYM Travel', 'sym-travel' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_settings_page' ),
			'dashicons-airplane',
			56
		);
	}

	/**
	 * Register settings, sections, and fields.
	 */
	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);

		add_settings_section(
			'sym_travel_imap_section',
			__( 'IMAP Settings', 'sym-travel' ),
			'__return_false',
			self::MENU_SLUG
		);

		$this->add_field(
			'imap_host',
			__( 'IMAP Host', 'sym-travel' ),
			array( $this, 'render_text_field' ),
			__( 'Hostname of the IMAP server.', 'sym-travel' ),
			'sym_travel_imap_section'
		);

		$this->add_field(
			'imap_port',
			__( 'IMAP Port', 'sym-travel' ),
			array( $this, 'render_number_field' ),
			__( 'Port number (e.g., 993).', 'sym-travel' ),
			'sym_travel_imap_section'
		);

		$this->add_field(
			'imap_encryption',
			__( 'Encryption', 'sym-travel' ),
			array( $this, 'render_encryption_field' ),
			__( 'TLS/SSL/STARTTLS as required by the server.', 'sym-travel' ),
			'sym_travel_imap_section'
		);

		$this->add_field(
			'imap_username',
			__( 'IMAP Username', 'sym-travel' ),
			array( $this, 'render_text_field' ),
			__( 'Username used for IMAP authentication.', 'sym-travel' ),
			'sym_travel_imap_section'
		);

		$this->add_field(
			'imap_password',
			__( 'IMAP Password', 'sym-travel' ),
			array( $this, 'render_password_field' ),
			__( 'Password is stored encrypted by WordPress.', 'sym-travel' ),
			'sym_travel_imap_section'
		);

		$this->add_field(
			'imap_mailbox',
			__( 'Mailbox', 'sym-travel' ),
			array( $this, 'render_text_field' ),
			__( 'Specific mailbox/folder to poll (e.g., INBOX).', 'sym-travel' ),
			'sym_travel_imap_section'
		);

		add_settings_section(
			'sym_travel_openai_section',
			__( 'OpenAI Settings', 'sym-travel' ),
			'__return_false',
			self::MENU_SLUG
		);

		$this->add_field(
			'openai_api_key',
			__( 'OpenAI API Key', 'sym-travel' ),
			array( $this, 'render_password_field' ),
			__( 'Stored securely in the database; never logged.', 'sym-travel' ),
			'sym_travel_openai_section'
		);
	}

	/**
	 * Utility to register a field.
	 *
	 * @param string        $key     Field key within the option array.
	 * @param string        $label   Field label.
	 * @param callable      $callback Render callback.
	 * @param string        $description Field description.
	 * @param string        $section Section slug.
	 */
	private function add_field( string $key, string $label, callable $callback, string $description, string $section ): void {
		add_settings_field(
			$key,
			$label,
			$callback,
			self::MENU_SLUG,
			$section,
			array(
				'key'         => $key,
				'description' => $description,
			)
		);
	}

	/**
	 * Sanitize incoming settings.
	 *
	 * @param array $settings Raw input.
	 * @return array
	 */
	public function sanitize_settings( array $settings ): array {
		$current = $this->get_settings();

		$sanitized = array(
			'imap_host'      => isset( $settings['imap_host'] ) ? sanitize_text_field( $settings['imap_host'] ) : '',
			'imap_port'      => isset( $settings['imap_port'] ) ? absint( $settings['imap_port'] ) : 0,
			'imap_encryption'=> isset( $settings['imap_encryption'] ) ? sanitize_text_field( $settings['imap_encryption'] ) : '',
			'imap_username'  => isset( $settings['imap_username'] ) ? sanitize_text_field( $settings['imap_username'] ) : '',
			'imap_password'  => isset( $settings['imap_password'] ) ? sanitize_text_field( $settings['imap_password'] ) : ( $current['imap_password'] ?? '' ),
			'imap_mailbox'   => isset( $settings['imap_mailbox'] ) ? sanitize_text_field( $settings['imap_mailbox'] ) : '',
			'openai_api_key' => isset( $settings['openai_api_key'] ) ? sanitize_text_field( $settings['openai_api_key'] ) : ( $current['openai_api_key'] ?? '' ),
		);

		$allowed_encryptions = array( 'ssl', 'tls', 'starttls', '' );
		if ( ! in_array( strtolower( $sanitized['imap_encryption'] ), $allowed_encryptions, true ) ) {
			$sanitized['imap_encryption'] = '';
		}

		return $sanitized;
	}

	/**
	 * Render the main settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sym-travel' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SYM Travel Settings', 'sym-travel' ); ?></h1>
			<?php settings_errors( 'sym_travel' ); ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::SETTINGS_GROUP );
				do_settings_sections( self::MENU_SLUG );
				submit_button();
				?>
			</form>
			<hr />
			<h2><?php esc_html_e( 'IMAP Diagnostics', 'sym-travel' ); ?></h2>
			<p><?php esc_html_e( 'Run a quick validation to ensure all IMAP fields have been provided and stored.', 'sym-travel' ); ?></p>
			<?php $this->render_test_form( self::ACTION_TEST_IMAP, __( 'Test IMAP Settings', 'sym-travel' ) ); ?>
			<h2><?php esc_html_e( 'OpenAI Diagnostics', 'sym-travel' ); ?></h2>
			<p><?php esc_html_e( 'Confirm the OpenAI API key has been saved before using it for parsing.', 'sym-travel' ); ?></p>
			<?php $this->render_test_form( self::ACTION_TEST_OPENAI, __( 'Test OpenAI Key', 'sym-travel' ) ); ?>
			<hr />
			<h2><?php esc_html_e( 'Manual Fetch & Import', 'sym-travel' ); ?></h2>
			<p><?php esc_html_e( 'When ready, click fetch to connect to IMAP and process new airline emails.', 'sym-travel' ); ?></p>
			<?php $this->render_fetch_form(); ?>
		</div>
		<?php
	}

	/**
	 * Render a text field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_text_field( array $args ): void {
		$value = $this->get_setting_value( $args['key'] );
		?>
		<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY . '[' . $args['key'] . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" />
		<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php
	}

	/**
	 * Render a number field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_number_field( array $args ): void {
		$value = $this->get_setting_value( $args['key'] );
		?>
		<input type="number" class="small-text" name="<?php echo esc_attr( self::OPTION_KEY . '[' . $args['key'] . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" min="0" />
		<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php
	}

	/**
	 * Render encryption select field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_encryption_field( array $args ): void {
		$value     = strtolower( (string) $this->get_setting_value( $args['key'] ) );
		$options   = array(
			''          => __( 'None (not recommended)', 'sym-travel' ),
			'ssl'       => __( 'SSL', 'sym-travel' ),
			'tls'       => __( 'TLS', 'sym-travel' ),
			'starttls'  => __( 'STARTTLS', 'sym-travel' ),
		);
		?>
		<select name="<?php echo esc_attr( self::OPTION_KEY . '[' . $args['key'] . ']' ); ?>">
			<?php foreach ( $options as $option_key => $label ) : ?>
				<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $value, $option_key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php
	}

	/**
	 * Render password field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_password_field( array $args ): void {
		$value = $this->get_setting_value( $args['key'] );
		?>
		<input type="password" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr( self::OPTION_KEY . '[' . $args['key'] . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" />
		<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php
	}

	/**
	 * Retrieve stored settings.
	 *
	 * @return array
	 */
	private function get_settings(): array {
		$settings = get_option( self::OPTION_KEY, array() );
		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Fetch a specific setting value.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private function get_setting_value( string $key ): string {
		$settings = $this->get_settings();
		return isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
	}

	/**
	 * Handle IMAP test action.
	 */
	public function handle_test_imap(): void {
		$this->guard_test_request( self::ACTION_TEST_IMAP );

		$settings = $this->get_settings();
		$required = array(
			'imap_host'     => __( 'IMAP Host', 'sym-travel' ),
			'imap_port'     => __( 'IMAP Port', 'sym-travel' ),
			'imap_username' => __( 'IMAP Username', 'sym-travel' ),
			'imap_password' => __( 'IMAP Password', 'sym-travel' ),
			'imap_mailbox'  => __( 'Mailbox', 'sym-travel' ),
		);

		$missing = array();
		foreach ( $required as $key => $label ) {
			if ( empty( $settings[ $key ] ) ) {
				$missing[] = $label;
			}
		}

		if ( empty( $missing ) ) {
			$this->persist_notice( __( 'IMAP settings look good. All required fields are saved.', 'sym-travel' ), 'updated' );
		} else {
			$message = sprintf(
				/* translators: %s list of missing settings */
				__( 'IMAP settings missing: %s', 'sym-travel' ),
				implode( ', ', array_map( 'esc_html', $missing ) )
			);
			$this->persist_notice( $message, 'error' );
		}

		$this->redirect_to_settings();
	}

	/**
	 * Handle OpenAI key test action.
	 */
	public function handle_test_openai(): void {
		$this->guard_test_request( self::ACTION_TEST_OPENAI );

		$settings = $this->get_settings();
		if ( empty( $settings['openai_api_key'] ) ) {
			$this->persist_notice( __( 'OpenAI API key is missing. Please add it above.', 'sym-travel' ), 'error' );
		} else {
			$this->persist_notice( __( 'OpenAI API key is stored.', 'sym-travel' ), 'updated' );
		}

		$this->redirect_to_settings();
	}

	/**
	 * Output a standalone test form.
	 *
	 * @param string $action Action slug.
	 * @param string $label  Button label.
	 */
	private function render_test_form( string $action, string $label ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( $action ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>" />
			<?php submit_button( $label, 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Render the manual fetch form.
	 */
	private function render_fetch_form(): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::ACTION_FETCH ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_FETCH ); ?>" />
			<?php submit_button( __( 'Fetch & Import Emails', 'sym-travel' ), 'primary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Ensure the current user can run diagnostics.
	 *
	 * @param string $nonce_action Nonce action.
	 */
	private function guard_test_request( string $nonce_action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'sym-travel' ) );
		}

		check_admin_referer( $nonce_action );
	}

	/**
	 * Persist a notice via the WordPress settings error mechanism.
	 *
	 * @param string $message Message content.
	 * @param string $type    Type: updated|error.
	 */
	private function persist_notice( string $message, string $type ): void {
		add_settings_error(
			'sym_travel',
			'sym_travel_notice',
			$message,
			$type
		);

		set_transient( 'settings_errors', get_settings_errors(), 30 );
	}

	/**
	 * Redirect back to the plugin settings.
	 */
	private function redirect_to_settings(): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => self::MENU_SLUG,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
