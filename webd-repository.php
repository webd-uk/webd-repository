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

define( 'WEBD_REPOSITORY_VERSION', '1.0.0' );
define( 'WEBD_REPOSITORY_FILE', __FILE__ );
define( 'WEBD_REPOSITORY_PATH', plugin_dir_path( __FILE__ ) );

require_once WEBD_REPOSITORY_PATH . 'src/Autoloader.php';
\Webd\Repository\Autoloader::register();

if ( class_exists( 'WooCommerce', false ) ) {
	\Webd\Repository\Plugin::init();
} else {
	add_action( 'plugins_loaded', array( \Webd\Repository\Plugin::class, 'init' ) );
}
