<?php
/**
 * Baseline contract checks for premium plugin requests.
 *
 * Run: php tests/premium-api-compatibility.php
 * Uses fixture data only. No WordPress installation or database is loaded.
 */

define( 'ABSPATH', __DIR__ . '/' );
date_default_timezone_set( 'UTC' );

class WooCommerce {}

class JsonResponse extends Exception {
	public $body;

	public function __construct( $body ) {
		$this->body = $body;
	}
}

class FakeWpdb {
	public $prefix = 'wp_';
	public $queries = array();
	public $writes = array();
	public $stats = array();
	public $releases = array();
	public $licences = array();

	public function prepare( $sql, $arguments ) {
		return array( $sql, is_array( $arguments ) ? $arguments : array( $arguments ) );
	}

	public function get_var( $prepared ) {
		$this->queries[] = $prepared;
		return $prepared[1][0]; // Both fixture tables already exist.
	}

	public function get_row( $prepared ) {
		$this->queries[] = $prepared;
		$sql = $prepared[0];
		$args = $prepared[1];

		if ( strpos( $sql, 'FROM wp_webd_plugin_stats' ) !== false ) {
			foreach ( array_reverse( $this->stats ) as $row ) {
				if ( $row->plugin_slug === $args[0] && $row->http_host === $args[1] ) {
					return $row;
				}
			}
			return null;
		}

		if ( strpos( $sql, 'FROM wp_webd_plugin_licences' ) !== false ) {
			foreach ( $this->licences as $row ) {
				if ( $row->plugin_slug === $args[0] && ( $row->licenced_http_host === $args[1] || $row->licenced_http_host === $args[2] ) ) {
					return $row;
				}
			}
			return null;
		}

		if ( strpos( $sql, 'FROM wp_webd_plugin_updates' ) !== false ) {
			$releases = $this->releases_for( $args[0] );
			return $releases ? $releases[0] : null;
		}

		throw new Exception( 'Unexpected get_row query: ' . $sql );
	}

	public function get_results( $prepared ) {
		$this->queries[] = $prepared;
		if ( strpos( $prepared[0], 'FROM wp_webd_plugin_updates' ) === false ) {
			throw new Exception( 'Unexpected get_results query' );
		}
		return $this->releases_for( $prepared[1][0] );
	}

	private function releases_for( $slug ) {
		$rows = array_filter( $this->releases, function ( $row ) use ( $slug ) {
			return $row->plugin_slug === $slug;
		} );
		usort( $rows, function ( $a, $b ) {
			return $b->ID - $a->ID;
		} );
		return array_values( $rows );
	}

	public function update( $table, $values, $where ) {
		$this->writes[] = array( 'update', $table, $values, $where );
		return 1;
	}

	public function insert( $table, $values, $formats ) {
		$this->writes[] = array( 'insert', $table, $values, $formats );
		return 1;
	}
}

$hooks = array();
$hook_details = array();
$shortcodes = array();

function add_action( $name, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['hooks'][ $name ] = $callback;
	$GLOBALS['hook_details'][ $name ] = array( $callback, $priority, $accepted_args );
}
function add_filter( $name, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['hooks'][ $name ] = $callback;
	$GLOBALS['hook_details'][ $name ] = array( $callback, $priority, $accepted_args );
}
function add_shortcode( $name, $callback ) {
	$GLOBALS['shortcodes'][ $name ] = $callback;
}
function is_admin() {
	return false;
}
function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}
function sanitize_title( $value ) {
	return trim( preg_replace( '/[^a-z0-9-]+/', '-', strtolower( $value ) ), '-' );
}
function absint( $value ) {
	return abs( (int) $value );
}
function esc_url_raw( $value ) {
	return $value;
}
function wp_send_json( $value ) {
	throw new JsonResponse( json_encode( $value ) );
}
function wp_send_json_error() {
	throw new JsonResponse( '{"success":false}' );
}
function wp_die() {
	throw new Exception( 'wp_die reached without a response' );
}

require dirname( __DIR__ ) . '/webd-repository.php';

function check( $actual, $expected, $label ) {
	if ( $actual !== $expected ) {
		throw new Exception( $label . "\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) );
	}
}

check( $hook_details['wp_ajax_nopriv_plugin_version'], array( 'webd_plugin_version', 10, 1 ), 'Public version hook' );
check( $hook_details['wp_ajax_nopriv_plugin_information'], array( 'webd_plugin_information', 10, 1 ), 'Public information hook' );
check( $hook_details['woocommerce_order_status_completed'], array( 'webd_activate_plugin', 10, 1 ), 'Completed order hook' );
check( $hook_details['woocommerce_add_to_cart_validation'], array( 'webd_prevent_duplicate_orders', 10, 5 ), 'Cart validation hook' );
check( count( $hooks ), 18, 'Registered hook count' );
check( array_keys( $shortcodes ), array( 'webd-recent-contributions', 'packagelink', 'show_reviews' ), 'Shortcode names and order' );

function fixture_db( $stats = array(), $licences = array(), $releases = null ) {
	$db = new FakeWpdb();
	$db->stats = $stats;
	$db->licences = $licences;
	$db->releases = $releases === null ? array(
		(object) array( 'ID' => 2, 'plugin_slug' => 'sidemenu-premium', 'plugin_version' => '2.0.0', 'tested' => '7.1', 'requires' => '7.0', 'changelog' => 'Latest changes', 'updated_date' => '2026-10-20 12:00:00' ),
		(object) array( 'ID' => 1, 'plugin_slug' => 'sidemenu-premium', 'plugin_version' => '1.9.0', 'tested' => '7.0', 'requires' => '6.9', 'changelog' => 'Earlier changes', 'updated_date' => '2026-09-01 12:00:00' ),
	) : $releases;
	return $db;
}

function request( $action, $parameters, $db, $user_agent = 'Premium Client' ) {
	$GLOBALS['wpdb'] = $db;
	$_REQUEST = $parameters;
	$_SERVER['HTTP_USER_AGENT'] = $user_agent;
	$hook = 'wp_ajax_nopriv_' . $action;
	if ( ! isset( $GLOBALS['hooks'][ $hook ] ) ) {
		throw new Exception( 'Missing public action: ' . $hook );
	}
	try {
		call_user_func( $GLOBALS['hooks'][ $hook ] );
	} catch ( JsonResponse $response ) {
		return json_decode( $response->body, true );
	}
	throw new Exception( 'No JSON response from ' . $action );
}

function query_count( $db ) {
	return count( $db->queries );
}

// Existing exact licence, retained trial date and latest version response.
$db = fixture_db(
	array( (object) array( 'ID' => 9, 'plugin_slug' => 'sidemenu-premium', 'http_host' => 'example.com', 'plugin_version' => '1.9.0', 'trial_date' => 12345, 'purchased' => 0 ) ),
	array( (object) array( 'plugin_slug' => 'sidemenu', 'licenced_http_host' => 'example.com' ) )
);
$version = request( 'plugin_version', array( 'plugin' => 'sidemenu-premium', 'version' => '2.0.0', 'trial_date' => '99999', 'url' => 'https://www.example.com/path' ), $db );
check( $version, array( 'slug' => 'sidemenu-premium', 'new_version' => '2.0.0', 'tested' => '7.1', 'changelog' => 'Latest changes', 'trial_date' => 12345, 'purchased' => 1 ), 'Licensed version response' );
check( array_keys( $db->writes[0][2] ), array( 'request_date', 'plugin_version' ), 'Existing stats update fields' );
check( query_count( $db ), 5, 'Version request read count with existing tables' );

// New unlicensed host: client-supplied purchase status is recorded, not treated as entitlement.
$db = fixture_db();
$version = request( 'plugin_version', array( 'plugin' => 'sidemenu-premium', 'version' => '1.9.0', 'trial_date' => '12345', 'purchased' => '1', 'url' => 'https://new.example.com/' ), $db );
check( $version['purchased'], 0, 'Unlicensed response' );
check( $version['trial_date'], 0, 'New stats trial response' );
check( $db->writes[0][2], array( 'plugin_slug' => 'sidemenu-premium', 'plugin_version' => '1.9.0', 'trial_date' => 12345, 'purchased' => true, 'http_host' => 'new.example.com' ), 'New stats insert' );

// Wildcard licence and complete information history.
$db = fixture_db(
	array( (object) array( 'ID' => 10, 'plugin_slug' => 'sidemenu-premium', 'http_host' => 'shop.example.com', 'plugin_version' => '1.9.0', 'trial_date' => 0, 'purchased' => 0 ) ),
	array( (object) array( 'plugin_slug' => 'sidemenu', 'licenced_http_host' => '*.example.com' ) )
);
$info = request( 'plugin_information', array( 'plugin' => 'sidemenu-premium', 'version' => '1.9.0', 'trial_date' => '22222', 'url' => 'https://shop.example.com/' ), $db );
check( $info, array(
	'slug' => 'sidemenu-premium',
	'last_updated' => '2026-10-20 12:00pm GMT',
	'version' => '2.0.0',
	'tested' => '7.1',
	'requires' => '7.0',
	'sections' => array( 'changelog' => "\n<h4>2.0.0</h4>\n<ul>\n<li>Latest changes</li>\n</ul>\n<h4>1.9.0</h4>\n<ul>\n<li>Earlier changes</li>\n</ul>" ),
	'trial_date' => 22222,
	'purchased' => 1,
), 'Information response and full history' );
check( array_keys( $db->writes[0][2] ), array( 'request_date', 'trial_date' ), 'New trial date update fields' );
check( query_count( $db ), 5, 'Information request read count with existing tables' );

// Local hosts omit licence and trial fields while still recording the request.
$db = fixture_db();
$version = request( 'plugin_version', array( 'plugin' => 'sidemenu-premium', 'url' => 'http://localhost/' ), $db );
check( array_keys( $version ), array( 'slug', 'new_version', 'tested', 'changelog' ), 'Local response fields' );
check( $db->writes[0][2]['http_host'], 'localhost', 'Local host stats' );

// Bad slug does not access the database. Missing release returns the current error shape.
$db = fixture_db();
check( request( 'plugin_version', array( 'plugin' => '!!!' ), $db ), array( 'success' => false ), 'Invalid slug response' );
check( query_count( $db ), 0, 'Invalid slug database access' );
$db = fixture_db( array(), array(), array() );
check( request( 'plugin_information', array( 'plugin' => 'sidemenu-premium', 'url' => 'https://example.com/' ), $db ), array( 'success' => false ), 'Missing release response' );
check( count( $db->writes ), 1, 'Missing release still records stats' );

echo "Premium API compatibility baseline: 6 scenarios passed.\n";
