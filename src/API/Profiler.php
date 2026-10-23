<?php
/** Temporary timing of public premium plugin API requests. */

namespace Webd\Repository\API;

defined( 'ABSPATH' ) || exit;

final class Profiler {
	private static $action;
	private static $plugin_loaded_at;
	private static $handler_started_at;
	private static $handler_query_count;
	private static $sql_ms = 0.0;
	private static $sql_calls = 0;
	private static $sql_breakdown = array();
	private static $milestones = array();

	public static function maybe_start() {
		if ( ! WEBD_REPOSITORY_PROFILE_PREMIUM_API || ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			return;
		}

		$action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
		if ( ! in_array( $action, array( 'plugin_version', 'plugin_information' ), true ) ) {
			return;
		}

		self::$action = $action;
		self::$plugin_loaded_at = microtime( true );
		self::register_milestones( $action );
		register_shutdown_function( array( __CLASS__, 'write_log' ) );
	}

	private static function register_milestones( $action ) {
		$hooks = array(
			array( 'plugins_loaded', PHP_INT_MAX, 'plugins_loaded_end' ),
			array( 'init', -PHP_INT_MAX, 'init_start' ),
			array( 'init', PHP_INT_MAX, 'init_end' ),
			array( 'admin_init', -PHP_INT_MAX, 'admin_init_start' ),
			array( 'admin_init', PHP_INT_MAX, 'admin_init_end' ),
			array( 'wp_ajax_nopriv_' . $action, 0, 'ajax_dispatch' ),
		);

		foreach ( $hooks as $hook ) {
			$label = $hook[2];
			add_action( $hook[0], function () use ( $label ) {
				Profiler::mark_milestone( $label );
			}, $hook[1] );
		}
	}

	public static function mark_milestone( $label ) {
		global $wpdb;
		self::$milestones[ $label ] = array(
			'at' => microtime( true ),
			'queries' => isset( $wpdb->num_queries ) ? (int) $wpdb->num_queries : null,
		);
	}

	public static function begin_handler() {
		if ( ! self::$action ) {
			return;
		}

		global $wpdb;
		self::$handler_started_at = microtime( true );
		self::$handler_query_count = isset( $wpdb->num_queries ) ? (int) $wpdb->num_queries : null;
	}

	public static function begin_sql() {
		return self::$action ? microtime( true ) : null;
	}

	public static function end_sql( $label, $started_at ) {
		if ( null === $started_at ) {
			return;
		}

		$elapsed = ( microtime( true ) - $started_at ) * 1000;
		self::$sql_ms += $elapsed;
		++self::$sql_calls;
		if ( ! isset( self::$sql_breakdown[ $label ] ) ) {
			self::$sql_breakdown[ $label ] = array( 'calls' => 0, 'ms' => 0.0 );
		}
		++self::$sql_breakdown[ $label ]['calls'];
		self::$sql_breakdown[ $label ]['ms'] += $elapsed;
	}

	public static function write_log() {
		if ( ! self::$action || ! defined( 'WP_CONTENT_DIR' ) ) {
			return;
		}

		global $wpdb;
		$now = microtime( true );
		$breakdown = array();
		$timeline_ms = array();
		$timeline_queries = array();
		foreach ( self::$sql_breakdown as $label => $measurement ) {
			$breakdown[ $label ] = array(
				'calls' => $measurement['calls'],
				'ms' => round( $measurement['ms'], 3 ),
			);
		}
		foreach ( self::$milestones as $label => $milestone ) {
			$timeline_ms[ $label ] = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? round( ( $milestone['at'] - (float) $_SERVER['REQUEST_TIME_FLOAT'] ) * 1000, 3 ) : null;
			$timeline_queries[ $label ] = $milestone['queries'];
		}

		$record = array(
			'action' => self::$action,
			'request_ms' => isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? round( ( $now - (float) $_SERVER['REQUEST_TIME_FLOAT'] ) * 1000, 3 ) : null,
			'plugin_loaded_ms' => isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? round( ( self::$plugin_loaded_at - (float) $_SERVER['REQUEST_TIME_FLOAT'] ) * 1000, 3 ) : null,
			'pre_handler_ms' => self::$handler_started_at && isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? round( ( self::$handler_started_at - (float) $_SERVER['REQUEST_TIME_FLOAT'] ) * 1000, 3 ) : null,
			'handler_ms' => self::$handler_started_at ? round( ( $now - self::$handler_started_at ) * 1000, 3 ) : null,
			'sql_ms' => round( self::$sql_ms, 3 ),
			'sql_calls' => self::$sql_calls,
			'wpdb_queries' => null !== self::$handler_query_count && isset( $wpdb->num_queries ) ? (int) $wpdb->num_queries - self::$handler_query_count : null,
			'sql_breakdown' => $breakdown,
			'timeline_ms' => $timeline_ms,
			'timeline_queries' => $timeline_queries,
		);

		// Numeric timings and the fixed action name only; no client or licence data.
		error_log( '[webd-repository-profile] ' . json_encode( $record ) . PHP_EOL, 3, WP_CONTENT_DIR . '/debug.log' );
	}
}
