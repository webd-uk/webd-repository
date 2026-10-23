<?php
/** Coordinates the repository's existing feature hooks. */

namespace Webd\Repository;

use Webd\Repository\API\PluginRepository;
use Webd\Repository\Commerce\WooCommerceIntegration;
use Webd\Repository\Reviews\Reviews;
use Webd\Repository\Site\SiteFeatures;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	private static $initialised = false;

	public static function init() {
		if ( self::$initialised || ! class_exists( 'WooCommerce', false ) ) {
			return;
		}

		self::$initialised = true;
		// Preserve the original global callables and hook callback identity.
		require_once WEBD_REPOSITORY_PATH . 'src/LegacyFunctions.php';

		SiteFeatures::register_hooks();
		PluginRepository::register_hooks();
		Reviews::register_hooks();
		WooCommerceIntegration::register_hooks();
	}
}
