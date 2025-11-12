<?php
/**
 * Performance Monitoring Class
 *
 * Tracks and logs performance metrics, identifies slow queries, and provides
 * performance statistics for monitoring and optimization.
 *
 * @package FanfictionManager
 * @since 1.0.15
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Performance_Monitor
 *
 * Provides comprehensive performance monitoring including:
 * - Slow query tracking
 * - Database table size monitoring
 * - Memory usage tracking
 * - Performance statistics dashboard
 *
 * @since 1.0.15
 */
class Fanfic_Performance_Monitor {

	/**
	 * Default slow query threshold in seconds
	 *
	 * @var float
	 */
	const DEFAULT_THRESHOLD = 0.5;

	/**
	 * Maximum number of slow queries to store
	 *
	 * @var int
	 */
	const MAX_SLOW_QUERIES = 100;

	/**
	 * Option name for slow query log
	 *
	 * @var string
	 */
	const SLOW_QUERY_OPTION = 'fanfic_slow_queries';

	/**
	 * Option name for monitoring settings
	 *
	 * @var string
	 */
	const SETTINGS_OPTION = 'fanfic_performance_settings';

	/**
	 * Active timers
	 *
	 * @var array
	 */
	private static $timers = array();

	/**
	 * Initialize performance monitoring
	 *
	 * @since 1.0.15
	 * @return void
	 */
	public static function init() {
		// Hook for logging page metrics
		add_action( 'wp_footer', array( __CLASS__, 'log_page_metrics' ), 999 );
		add_action( 'admin_footer', array( __CLASS__, 'log_page_metrics' ), 999 );

		// Hook for query monitoring (if enabled)
		if ( self::is_monitoring_enabled() ) {
			add_filter( 'query', array( __CLASS__, 'monitor_query' ) );
		}
	}

	/**
	 * Check if monitoring is enabled
	 *
	 * @since 1.0.15
	 * @return bool True if monitoring is enabled.
	 */
	public static function is_monitoring_enabled() {
		$settings = get_option( self::SETTINGS_OPTION, array() );
		return isset( $settings['enabled'] ) ? (bool) $settings['enabled'] : true;
	}

	/**
	 * Get slow query threshold
	 *
	 * @since 1.0.15
	 * @return float Threshold in seconds.
	 */
	public static function get_threshold() {
		$settings = get_option( self::SETTINGS_OPTION, array() );
		return isset( $settings['threshold'] ) ? (float) $settings['threshold'] : self::DEFAULT_THRESHOLD;
	}

	/**
	 * Start timing an operation
	 *
	 * @since 1.0.15
	 * @param string $label Label for the timer.
	 * @return string Timer ID.
	 */
	public static function start_timer( $label ) {
		$timer_id = uniqid( 'timer_', true );

		self::$timers[ $timer_id ] = array(
			'label'      => sanitize_text_field( $label ),
			'start_time' => microtime( true ),
			'start_mem'  => memory_get_usage(),
		);

		return $timer_id;
	}

	/**
	 * End timing and log if slow
	 *
	 * @since 1.0.15
	 * @param string $timer_id Timer ID from start_timer().
	 * @param array  $context  Optional. Additional context data.
	 * @return float|false Elapsed time in seconds, or false if timer not found.
	 */
	public static function end_timer( $timer_id, $context = array() ) {
		if ( ! isset( self::$timers[ $timer_id ] ) ) {
			return false;
		}

		$timer = self::$timers[ $timer_id ];
		$elapsed = microtime( true ) - $timer['start_time'];
		$memory_used = memory_get_usage() - $timer['start_mem'];

		// Log if over threshold
		if ( $elapsed > self::get_threshold() ) {
			self::log_slow_query(
				$timer['label'],
				$elapsed,
				0,
				array_merge( $context, array(
					'memory_used' => $memory_used,
					'type'        => 'operation',
				) )
			);
		}

		// Cleanup
		unset( self::$timers[ $timer_id ] );

		return $elapsed;
	}

	/**
	 * Monitor query execution
	 *
	 * @since 1.0.15
	 * @param string $query SQL query.
	 * @return string The query (unchanged).
	 */
	public static function monitor_query( $query ) {
		// Only monitor fanfic queries
		if ( strpos( $query, 'fanfic' ) === false ) {
			return $query;
		}

		$start = microtime( true );

		// Store query start time
		add_filter( 'posts_results', function( $posts ) use ( $query, $start ) {
			$elapsed = microtime( true ) - $start;

			if ( $elapsed > self::get_threshold() ) {
				self::log_slow_query(
					$query,
					$elapsed,
					is_array( $posts ) ? count( $posts ) : 0,
					array( 'type' => 'query' )
				);
			}

			return $posts;
		}, 10, 1 );

		return $query;
	}

	/**
	 * Log a slow query
	 *
	 * @since 1.0.15
	 * @param string $query        SQL query or operation label.
	 * @param float  $time         Execution time in seconds.
	 * @param int    $result_count Number of results returned.
	 * @param array  $context      Optional. Additional context.
	 * @return void
	 */
	public static function log_slow_query( $query, $time, $result_count = 0, $context = array() ) {
		// Only log if over threshold
		if ( $time < self::get_threshold() ) {
			return;
		}

		$slow_queries = get_option( self::SLOW_QUERY_OPTION, array() );

		// Generate backtrace (limit to 5 frames)
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 5 );
		$backtrace_clean = array();

		foreach ( $backtrace as $frame ) {
			if ( isset( $frame['file'], $frame['line'] ) ) {
				$backtrace_clean[] = array(
					'file' => str_replace( ABSPATH, '', $frame['file'] ),
					'line' => $frame['line'],
					'function' => $frame['function'] ?? 'unknown',
				);
			}
		}

		// Create log entry
		$entry = array(
			'query'        => substr( $query, 0, 1000 ), // Limit query length
			'time'         => round( $time, 4 ),
			'result_count' => absint( $result_count ),
			'timestamp'    => current_time( 'mysql' ),
			'page'         => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( $_SERVER['REQUEST_URI'] ) : '',
			'backtrace'    => $backtrace_clean,
			'context'      => $context,
		);

		// Add to beginning of array
		array_unshift( $slow_queries, $entry );

		// Keep only last MAX_SLOW_QUERIES
		if ( count( $slow_queries ) > self::MAX_SLOW_QUERIES ) {
			$slow_queries = array_slice( $slow_queries, 0, self::MAX_SLOW_QUERIES );
		}

		update_option( self::SLOW_QUERY_OPTION, $slow_queries, false );
	}

	/**
	 * Get slow queries
	 *
	 * @since 1.0.15
	 * @param int $limit Maximum number of queries to return.
	 * @return array Array of slow queries.
	 */
	public static function get_slow_queries( $limit = 50 ) {
		$slow_queries = get_option( self::SLOW_QUERY_OPTION, array() );
		$limit = absint( $limit );

		if ( $limit > 0 && count( $slow_queries ) > $limit ) {
			return array_slice( $slow_queries, 0, $limit );
		}

		return $slow_queries;
	}

	/**
	 * Clear slow query log
	 *
	 * @since 1.0.15
	 * @return bool True on success.
	 */
	public static function clear_slow_queries() {
		return delete_option( self::SLOW_QUERY_OPTION );
	}

	/**
	 * Get performance statistics
	 *
	 * @since 1.0.15
	 * @return array Performance statistics.
	 */
	public static function get_performance_stats() {
		global $wpdb;

		$stats = array(
			'total_slow_queries'     => count( get_option( self::SLOW_QUERY_OPTION, array() ) ),
			'monitoring_enabled'     => self::is_monitoring_enabled(),
			'slow_query_threshold'   => self::get_threshold(),
		);

		// Count database records
		$tables = array(
			'stories'        => $wpdb->posts,
			'chapters'       => $wpdb->posts,
			'ratings'        => $wpdb->prefix . 'fanfic_ratings',
			'likes'          => $wpdb->prefix . 'fanfic_likes',
			'follows'        => $wpdb->prefix . 'fanfic_follows',
			'bookmarks'      => $wpdb->prefix . 'fanfic_bookmarks',
			'subscriptions'  => $wpdb->prefix . 'fanfic_email_subscriptions',
		);

		// Count stories
		$stats['total_stories'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
			'fanfiction_story'
		) );

		// Count chapters
		$stats['total_chapters'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
			'fanfiction_chapter'
		) );

		// Count ratings
		$stats['total_ratings'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}fanfic_ratings"
		);

		// Count likes
		$stats['total_likes'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}fanfic_likes"
		);

		// Count follows
		$stats['total_follows'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}fanfic_follows"
		);

		// Count bookmarks
		$stats['total_bookmarks'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}fanfic_bookmarks"
		);

		// Count subscriptions
		$stats['total_subscriptions'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}fanfic_email_subscriptions WHERE confirmed = 1"
		);

		// Count active users in last 30 days
		$thirty_days_ago = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
		$stats['active_users_30d'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta}
			WHERE meta_key = %s AND meta_value > %s",
			'last_activity',
			$thirty_days_ago
		) );

		return $stats;
	}

	/**
	 * Get database table sizes
	 *
	 * @since 1.0.15
	 * @return array Table sizes in bytes.
	 */
	public static function get_table_sizes() {
		global $wpdb;

		$tables = array(
			'fanfic_ratings',
			'fanfic_likes',
			'fanfic_follows',
			'fanfic_bookmarks',
			'fanfic_email_subscriptions',
			'fanfic_email_queue',
			'fanfic_notifications',
			'fanfic_reading_progress',
			'fanfic_views',
		);

		$sizes = array();

		foreach ( $tables as $table ) {
			$full_table = $wpdb->prefix . $table;

			$result = $wpdb->get_row( $wpdb->prepare(
				"SELECT
					data_length + index_length as size,
					table_rows as rows
				FROM information_schema.TABLES
				WHERE table_schema = %s
				AND table_name = %s",
				DB_NAME,
				$full_table
			) );

			if ( $result ) {
				$sizes[ $table ] = array(
					'size_bytes' => absint( $result->size ),
					'size_human' => size_format( $result->size ),
					'rows'       => absint( $result->rows ),
				);
			}
		}

		return $sizes;
	}

	/**
	 * Log memory usage
	 *
	 * @since 1.0.15
	 * @return void
	 */
	public static function log_memory_usage() {
		$memory_log = get_option( 'fanfic_memory_log', array() );

		$entry = array(
			'timestamp'      => current_time( 'mysql' ),
			'current_memory' => memory_get_usage(),
			'peak_memory'    => memory_get_peak_usage(),
			'page'           => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( $_SERVER['REQUEST_URI'] ) : '',
		);

		array_unshift( $memory_log, $entry );

		// Keep only last 50 entries
		if ( count( $memory_log ) > 50 ) {
			$memory_log = array_slice( $memory_log, 0, 50 );
		}

		update_option( 'fanfic_memory_log', $memory_log, false );
	}

	/**
	 * Get memory statistics
	 *
	 * @since 1.0.15
	 * @return array Memory statistics.
	 */
	public static function get_memory_stats() {
		$limit = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );

		return array(
			'current'       => memory_get_usage(),
			'current_human' => size_format( memory_get_usage() ),
			'peak'          => memory_get_peak_usage(),
			'peak_human'    => size_format( memory_get_peak_usage() ),
			'limit'         => $limit,
			'limit_human'   => size_format( $limit ),
			'usage_percent' => round( ( memory_get_usage() / $limit ) * 100, 2 ),
		);
	}

	/**
	 * Log page metrics on page load
	 *
	 * @since 1.0.15
	 * @return void
	 */
	public static function log_page_metrics() {
		if ( ! self::is_monitoring_enabled() ) {
			return;
		}

		// Only log on fanfic pages
		if ( ! is_singular( array( 'fanfiction_story', 'fanfiction_chapter' ) ) ) {
			return;
		}

		global $wpdb;

		$metrics = array(
			'page_load_time'  => timer_stop( 0, 3 ),
			'query_count'     => $wpdb->num_queries,
			'memory_usage'    => memory_get_peak_usage(),
			'timestamp'       => current_time( 'mysql' ),
			'page'            => get_the_ID(),
			'page_type'       => get_post_type(),
		);

		// Store in option (keep last 20)
		$page_metrics = get_option( 'fanfic_page_metrics', array() );
		array_unshift( $page_metrics, $metrics );

		if ( count( $page_metrics ) > 20 ) {
			$page_metrics = array_slice( $page_metrics, 0, 20 );
		}

		update_option( 'fanfic_page_metrics', $page_metrics, false );
	}

	/**
	 * Update monitoring settings
	 *
	 * @since 1.0.15
	 * @param array $settings Settings array.
	 * @return bool True on success.
	 */
	public static function update_settings( $settings ) {
		$defaults = array(
			'enabled'   => true,
			'threshold' => self::DEFAULT_THRESHOLD,
		);

		$settings = wp_parse_args( $settings, $defaults );

		return update_option( self::SETTINGS_OPTION, $settings );
	}

	/**
	 * Get monitoring settings
	 *
	 * @since 1.0.15
	 * @return array Settings.
	 */
	public static function get_settings() {
		return get_option( self::SETTINGS_OPTION, array(
			'enabled'   => true,
			'threshold' => self::DEFAULT_THRESHOLD,
		) );
	}
}
