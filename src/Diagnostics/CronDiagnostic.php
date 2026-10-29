<?php
/** Temporary, bounded diagnostics for callbacks dispatched by wp-cron.php. */

namespace Webd\Repository\Diagnostics;

defined( 'ABSPATH' ) || exit;

final class CronDiagnostic {
	const MAX_CALLBACKS = 200;
	const MAX_LOG_BYTES = 5242880;
	const LOG_FILE = 'webd-cron-diagnostic.log';

	private static $started_at;
	private static $cpu_started_at;
	private static $request_id;
	private static $request_type;
	private static $request_path;
	private static $next_callback_id = 0;
	private static $completed_callbacks = 0;
	private static $skipped_callbacks = 0;
	private static $limit_reported = false;
	private static $active_callbacks = array();
	private static $wrappers = array();
	private static $reference_skips = array();

	public static function maybe_start() {
		if ( ! WEBD_REPOSITORY_CRON_DIAGNOSTIC || ! defined( 'DOING_CRON' ) || ! DOING_CRON ) {
			return;
		}

		self::$started_at = microtime( true );
		self::$cpu_started_at = self::cpu_time();
		self::$request_id = (string) getmypid() . '-' . sprintf( '%.0F', self::$started_at * 1000000 );
		self::$request_type = PHP_SAPI === 'cli' ? 'cli' : 'http';
		// SCRIPT_NAME identifies the executing script without request query or client path data.
		$path = isset( $_SERVER['SCRIPT_NAME'] ) ? $_SERVER['SCRIPT_NAME'] : '/wp-cron.php';
		if ( 'cli' === self::$request_type ) {
			$path = '/' . basename( $path );
		}
		self::$request_path = self::safe_path( $path );

		register_shutdown_function( array( __CLASS__, 'finish_process' ) );
		add_action( 'all', array( __CLASS__, 'before_action' ), 0, 1 );
		self::write( 'process_start', array(
			'request_started_at' => isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? self::timestamp( (float) $_SERVER['REQUEST_TIME_FLOAT'] ) : null,
		) );
	}

	/** Wrap only a scheduled hook dispatched directly from wp-cron.php. */
	public static function before_action( $hook_name ) {
		if ( ! is_string( $hook_name ) || ! self::is_cron_dispatch() ) {
			return;
		}

		global $wp_filter;
		if ( ! isset( $wp_filter[ $hook_name ] ) || ! $wp_filter[ $hook_name ] instanceof \WP_Hook ) {
			return;
		}

		foreach ( $wp_filter[ $hook_name ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $key => $entry ) {
				$original = $entry['function'];
				if ( isset( self::$wrappers[ $hook_name ][ $priority ][ $key ] ) && self::$wrappers[ $hook_name ][ $priority ][ $key ] === $original ) {
					continue;
				}

				$label = self::callback_label( $original );
				if ( self::needs_reference_arguments( $original ) ) {
					if ( ! isset( self::$reference_skips[ $hook_name ][ $priority ][ $key ] ) ) {
						self::$reference_skips[ $hook_name ][ $priority ][ $key ] = true;
						++self::$skipped_callbacks;
						self::write( 'callback_skipped', array( 'hook' => self::safe_label( $hook_name ), 'callback' => $label, 'reason' => 'reference_arguments' ) );
					}
					continue;
				}

				$wrapper = function ( ...$args ) use ( $original, $hook_name, $label, $priority ) {
					return CronDiagnostic::run_callback( $original, $hook_name, $label, $priority, $args );
				};
				// Retain the original priority, key, accepted_args and order in WP_Hook.
				$wp_filter[ $hook_name ]->callbacks[ $priority ][ $key ]['function'] = $wrapper;
				self::$wrappers[ $hook_name ][ $priority ][ $key ] = $wrapper;
			}
		}
	}

	private static function is_cron_dispatch() {
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 9 ) as $frame ) {
			if ( isset( $frame['function'], $frame['file'] ) && 'do_action_ref_array' === $frame['function'] && 'wp-cron.php' === basename( $frame['file'] ) ) {
				return true;
			}
		}
		return false;
	}

	public static function run_callback( $original, $hook_name, $label, $priority, $args ) {
		if ( self::$next_callback_id >= self::MAX_CALLBACKS ) {
			++self::$skipped_callbacks;
			if ( ! self::$limit_reported ) {
				self::$limit_reported = true;
				self::write( 'callback_limit_reached', array( 'max_callbacks' => self::MAX_CALLBACKS ) );
			}
			return call_user_func_array( $original, $args );
		}

		$id = ++self::$next_callback_id;
		$started_at = microtime( true );
		self::$active_callbacks[ $id ] = array(
			'hook' => self::safe_label( $hook_name ),
			'callback' => $label,
			'priority' => $priority,
			'started_at' => $started_at,
			'cpu_started_at' => self::cpu_time(),
		);
		self::write( 'callback_start', array(
			'callback_id' => $id,
			'hook' => self::$active_callbacks[ $id ]['hook'],
			'callback' => $label,
			'priority' => $priority,
		) );

		$completed = false;
		try {
			$result = call_user_func_array( $original, $args );
			$completed = true;
			return $result;
		} finally {
			self::finish_callback( $id, $completed ? 'completed' : 'threw' );
		}
	}

	private static function finish_callback( $id, $status ) {
		if ( ! isset( self::$active_callbacks[ $id ] ) ) {
			return;
		}
		$active = self::$active_callbacks[ $id ];
		unset( self::$active_callbacks[ $id ] );
		++self::$completed_callbacks;
		self::write( 'callback_end', array(
			'callback_id' => $id,
			'hook' => $active['hook'],
			'callback' => $active['callback'],
			'priority' => $active['priority'],
			'status' => $status,
			'elapsed_ms' => self::elapsed_ms( $active['started_at'] ),
			'cpu_ms' => self::cpu_elapsed_ms( $active['cpu_started_at'] ),
		) );
	}

	public static function finish_process() {
		if ( ! self::$started_at ) {
			return;
		}
		foreach ( self::$active_callbacks as $id => $active ) {
			self::write( 'callback_unfinished', array(
				'callback_id' => $id,
				'hook' => $active['hook'],
				'callback' => $active['callback'],
				'priority' => $active['priority'],
				'elapsed_ms' => self::elapsed_ms( $active['started_at'] ),
				'cpu_ms' => self::cpu_elapsed_ms( $active['cpu_started_at'] ),
			) );
		}
		self::write( 'process_end', array(
			'elapsed_ms' => self::elapsed_ms( self::$started_at ),
			'cpu_ms' => self::cpu_elapsed_ms( self::$cpu_started_at ),
			'callbacks_started' => self::$next_callback_id,
			'callbacks_completed' => self::$completed_callbacks,
			'callbacks_unfinished' => count( self::$active_callbacks ),
			'callbacks_skipped' => self::$skipped_callbacks,
		) );
	}

	private static function needs_reference_arguments( $callback ) {
		try {
			if ( is_array( $callback ) ) {
				$reflection = new \ReflectionMethod( $callback[0], $callback[1] );
			} elseif ( is_object( $callback ) && ! $callback instanceof \Closure ) {
				$reflection = new \ReflectionMethod( $callback, '__invoke' );
			} elseif ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
				$reflection = new \ReflectionMethod( $callback );
			} else {
				$reflection = new \ReflectionFunction( $callback );
			}
			foreach ( $reflection->getParameters() as $parameter ) {
				if ( $parameter->isPassedByReference() ) {
					return true;
				}
			}
			return false;
		} catch ( \ReflectionException $error ) {
			return true;
		}
	}

	private static function callback_label( $callback ) {
		if ( is_string( $callback ) ) {
			return self::safe_label( $callback );
		}
		if ( is_array( $callback ) && 2 === count( $callback ) ) {
			$owner = is_object( $callback[0] ) ? get_class( $callback[0] ) : $callback[0];
			return self::safe_label( $owner . '::' . $callback[1] );
		}
		return self::safe_label( $callback instanceof \Closure ? 'Closure' : get_class( $callback ) . '::__invoke' );
	}

	private static function safe_label( $value ) {
		return substr( preg_replace( '/[^A-Za-z0-9_\\\\:.-]/', '_', (string) $value ), 0, 160 );
	}

	private static function safe_path( $path ) {
		$path = is_string( $path ) ? $path : '/wp-cron.php';
		return substr( preg_replace( '/[^A-Za-z0-9._~\/%-]/', '_', $path ), 0, 240 );
	}

	private static function cpu_time() {
		if ( ! function_exists( 'getrusage' ) ) {
			return null;
		}
		$usage = getrusage();
		if ( ! is_array( $usage ) ) {
			return null;
		}
		return ( $usage['ru_utime.tv_sec'] + $usage['ru_stime.tv_sec'] ) + ( $usage['ru_utime.tv_usec'] + $usage['ru_stime.tv_usec'] ) / 1000000;
	}

	private static function elapsed_ms( $started_at ) {
		return round( ( microtime( true ) - $started_at ) * 1000, 3 );
	}

	private static function cpu_elapsed_ms( $started_at ) {
		$now = self::cpu_time();
		return null === $started_at || null === $now ? null : round( ( $now - $started_at ) * 1000, 3 );
	}

	private static function timestamp( $time ) {
		return gmdate( 'Y-m-d\TH:i:s', (int) $time ) . sprintf( '.%06dZ', (int) ( ( $time - floor( $time ) ) * 1000000 ) );
	}

	private static function write( $event, $fields = array() ) {
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			return;
		}
		$record = array_merge( array(
			'event' => $event,
			'at' => self::timestamp( microtime( true ) ),
			'pid' => getmypid(),
			'request_id' => self::$request_id,
			'request_type' => self::$request_type,
			'path' => self::$request_path,
		), $fields );
		$line = '[webd-cron-diagnostic] ' . json_encode( $record, JSON_UNESCAPED_SLASHES ) . PHP_EOL;
		$file = @fopen( WP_CONTENT_DIR . '/' . self::LOG_FILE, 'c+' );
		if ( ! $file ) {
			return;
		}
		if ( @flock( $file, LOCK_EX ) ) {
			@fseek( $file, 0, SEEK_END );
			if ( ftell( $file ) + strlen( $line ) > self::MAX_LOG_BYTES ) {
				@ftruncate( $file, 0 );
				@rewind( $file );
			}
			@fwrite( $file, $line );
			@flock( $file, LOCK_UN );
		}
		@fclose( $file );
	}
}
