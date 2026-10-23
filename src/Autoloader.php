<?php
/** Loads Webd Repository classes from src. */

namespace Webd\Repository;

defined( 'ABSPATH' ) || exit;

final class Autoloader {
	const PREFIX = 'Webd\\Repository\\';

	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	public static function autoload( $class_name ) {
		if ( 0 !== strpos( $class_name, self::PREFIX ) ) {
			return;
		}

		$relative_class = substr( $class_name, strlen( self::PREFIX ) );
		if ( ! preg_match( '/^[A-Za-z0-9_\\\\]+$/', $relative_class ) ) {
			return;
		}

		$file = __DIR__ . '/' . str_replace( '\\', '/', $relative_class ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
