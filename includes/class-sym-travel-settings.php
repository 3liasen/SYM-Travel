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

	/**
	 * Option key used to store settings.
	 */
	private const OPTION_KEY = 'sym_travel_settings';

	/**
	 * Settings group slug.
	 */
	private const SETTINGS_GROUP = 'sym_travel_settings_group';

	/**
	 * Register menu pages.
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'SYM Travel', 'sym-travel' ),
			__( 'SYM Travel', 'sym-travel' ),
			'manage_options',
			'sym-travel',
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
			'sym-travel'
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
			'sym-travel'
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
			'sym-travel',
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
			<form method="post" action="options.php">
				<?php
				settings_fields( self::SETTINGS_GROUP );
				do_settings_sections( 'sym-travel' );
				submit_button();
				?>
			</form>
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
}
