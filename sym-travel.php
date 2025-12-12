<?php
/**
 * Plugin Name:       SYM Travel
 * Description:       IMAP ingestion of airline emails, OpenAI parsing, and Trip persistence for private display.
 * Version:           0.0.13
 * Author:            SYM Travel Team
 * Requires at least: 6.5
 * Requires PHP:      8.2
 *
 * @package SYM_Travel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SYM_TRAVEL_VERSION', '0.0.13' );
define( 'SYM_TRAVEL_PATH', plugin_dir_path( __FILE__ ) );
define( 'SYM_TRAVEL_URL', plugin_dir_url( __FILE__ ) );

$autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

require_once SYM_TRAVEL_PATH . 'includes/class-sym-travel-core.php';
require_once SYM_TRAVEL_PATH . 'includes/class-sym-travel-activator.php';

register_activation_hook(
	__FILE__,
	static function () {
		$activator = new SYM_Travel_Activator();
		$activator->activate();
	}
);

add_action(
	'plugins_loaded',
	static function () {
		$core = new SYM_Travel_Core();
		$core->init();
	}
);
