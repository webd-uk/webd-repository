<?php
/**
 * Plugin Name: webd.uk Repository
 * Plugin URI: https://webd.uk/
 * Description: Plugin sales, licensing, updates, and related site features for webd.uk.
 * Version: 1.0.0
 * Author: webd.uk
 * Author URI: https://webd.uk/
 * Text Domain: webd-repository
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WebdRepository
 */

defined( 'ABSPATH' ) || exit;

/**
 * Initialize the repository after WooCommerce has loaded.
 *
 * WordPress uses the Requires Plugins header to manage activation. This check
 * also keeps the plugin inactive if WooCommerce is unavailable at runtime.
 *
 * @return void
 */
function webd_repository_init() {
	if ( ! class_exists( 'WooCommerce', false ) ) {
		return;
	}

	// Keep the original hook registration order while loading each feature area.
	require_once __DIR__ . '/includes/functions-site.php';
	require_once __DIR__ . '/includes/functions-plugin-repository.php';
	require_once __DIR__ . '/includes/functions-reviews.php';
	require_once __DIR__ . '/includes/functions-woocommerce.php';
}

if ( class_exists( 'WooCommerce', false ) ) {
	webd_repository_init();
} else {
	add_action( 'plugins_loaded', 'webd_repository_init' );
}
