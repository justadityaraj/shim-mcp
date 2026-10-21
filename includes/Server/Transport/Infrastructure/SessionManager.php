<?php
/**
 * MCP Session Manager using User Meta
 *
 *
 * @package McpAdapter
 */

declare( strict_types=1 );

namespace ShimMcp\Server\Transport\Infrastructure;

use ShimMcp\Server\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler;

/**
 * MCP Session Manager
 *
 * Handles session creation, validation, and cleanup using user meta storage.
 * Sessions are tied to authenticated users to prevent anonymous session flooding.
 */
final class SessionManager {

	/**
	 * User meta key for storing sessions
	 *
	 * @var string
	 */
	private const SESSION_META_KEY = 'mcp_adapter_sessions';

	/**
	 * Maximum sessions per user.
	 *
	 * @var int
	 */
	private const DEFAULT_MAX_SESSIONS = 32;

	/**
	 * Session inactivity timeout in seconds (24 hours).
	 *
	 * @var int
	 */
	private const DEFAULT_INACTIVITY_TIMEOUT = DAY_IN_SECONDS;

	private const DEFAULT_ACTIVITY_UPDATE_INTERVAL = 60;

	private const MAX_UPDATE_ATTEMPTS = 5;

	private const MAX_CLIENT_PARAMS_BYTES = 4096;

	private const SESSION_ENVELOPE_BYTES = 256;

	/**
	 * Get configuration values.
	 *
	 * @return array<string, int> Configuration array.
	 */
	private static function get_config(): array {
		$inactivity_timeout       = (int) apply_filters( 'mcp_adapter_session_inactivity_timeout', self::DEFAULT_INACTIVITY_TIMEOUT );
		$activity_update_interval = (int) apply_filters( 'mcp_adapter_session_activity_update_interval', self::DEFAULT_ACTIVITY_UPDATE_INTERVAL );

		return array(
			'max_sessions'             => (int) apply_filters( 'mcp_adapter_session_max_per_user', self::DEFAULT_MAX_SESSIONS ),
			'inactivity_timeout'       => $inactivity_timeout,
			'activity_update_interval' => max( 0, min( $activity_update_interval, intdiv( $inactivity_timeout, 2 ) ) ),
		);
	}

	public static function validate_client_params( array $params ): bool {
		return strlen( maybe_serialize( $params ) ) <= self::MAX_CLIENT_PARAMS_BYTES;
	}

	/**
	 * Create a new session for a user
	 *
	 * @param int $user_id The user ID.
	 * @param array $params Client parameters from initialize request.
	 *
	 * @return string|false The session ID on success, false on failure.
	 */
	public static function create_session( int $user_id, array $params = array() ) {
		if ( ! $user_id || ! get_user_by( 'id', $user_id ) || ! self::validate_client_params( $params ) ) {
			return false;
		}

		$session_id = wp_generate_uuid4();
		$now        = time();
		$config     = self::get_config();

		$created = self::mutate_sessions(
			$user_id,
			static function ( array $sessions ) use ( $config, $now, $params, $session_id ): array {
				$sessions = self::without_expired( $sessions, $config['inactivity_timeout'], $now );

				if ( count( $sessions ) >= $config['max_sessions'] ) {
					uasort(
						$sessions,
						static function ( $a, $b ) {
							return $a['created_at'] <=> $b['created_at'];
						}
					);

					array_shift( $sessions );
				}

				$sessions[ $session_id ] = array(
					'created_at'    => $now,
					'last_activity' => $now,
					'client_params' => $params,
				);

				return $sessions;
			}
		);

		return $created ? $session_id : false;
	}

	/**
	 * Get a specific session for a user
	 *
	 * @param int $user_id The user ID.
	 * @param string $session_id The session ID.
	 *
	 * @return array|\WP_Error|false Session data on success, WP_Error on invalid input, false if not found or inactive.
	 */
	public static function get_session( int $user_id, string $session_id ) {
		if ( ! $user_id || ! $session_id ) {
			return new \WP_Error( 403, 'Invalid user ID or session ID.' );
		}

		$sessions = self::get_all_user_sessions( $user_id );

		if ( ! isset( $sessions[ $session_id ] ) ) {
			return false;
		}

		if ( array() === self::without_expired( array( $sessions[ $session_id ] ), self::get_config()['inactivity_timeout'], time() ) ) {
			self::delete_session( $user_id, $session_id );

			return false;
		}

		return $sessions[ $session_id ];
	}

	/**
	 * Validate a session and update last activity
	 *
	 * @param int $user_id The user ID.
	 * @param string $session_id The session ID.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public static function validate_session( int $user_id, string $session_id ): bool {
		if ( ! $user_id || ! $session_id ) {
			return false;
		}

		$config   = self::get_config();
		$now      = time();
		$is_valid = false;

		$stored = self::mutate_sessions(
			$user_id,
			static function ( array $sessions ) use ( $config, $now, $session_id, &$is_valid ): array {
				$sessions = self::without_expired( $sessions, $config['inactivity_timeout'], $now );
				$is_valid = isset( $sessions[ $session_id ] );

				if ( $is_valid && $now - $sessions[ $session_id ]['last_activity'] >= $config['activity_update_interval'] ) {
					$sessions[ $session_id ]['last_activity'] = $now;
				}

				return $sessions;
			}
		);

		return $stored && $is_valid;
	}

	/**
	 * Delete a specific session
	 *
	 * @param int $user_id The user ID.
	 * @param string $session_id The session ID.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function delete_session( int $user_id, string $session_id ): bool {
		if ( ! $user_id || ! $session_id ) {
			return false;
		}

		$found = false;

		$stored = self::mutate_sessions(
			$user_id,
			static function ( array $sessions ) use ( $session_id, &$found ): array {
				$found = isset( $sessions[ $session_id ] );
				unset( $sessions[ $session_id ] );

				return $sessions;
			}
		);

		return $stored && $found;
	}

	/**
	 * Cleanup inactive sessions for a user
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return int Number of sessions removed.
	 */
	public static function cleanup_expired_sessions( int $user_id ): int {
		if ( ! $user_id ) {
			return 0;
		}

		$timeout = self::get_config()['inactivity_timeout'];
		$now     = time();
		$removed = 0;

		$stored = self::mutate_sessions(
			$user_id,
			static function ( array $sessions ) use ( $timeout, $now, &$removed ): array {
				$active  = self::without_expired( $sessions, $timeout, $now );
				$removed = count( $sessions ) - count( $active );

				return $active;
			}
		);

		return $stored ? $removed : 0;
	}

	/**
	 * Get all sessions for a user
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return array Array of sessions.
	 */
	public static function get_all_user_sessions( int $user_id ): array {
		return $user_id ? self::read_sessions( $user_id )['sessions'] : array();
	}

	private static function session_meta_key(): string {
		$blog_id = is_multisite() ? (int) get_current_blog_id() : 0;

		return $blog_id > 0 ? self::SESSION_META_KEY . '_' . $blog_id : self::SESSION_META_KEY;
	}

	private static function without_expired( array $sessions, int $inactivity_timeout, int $now ): array {
		return array_filter(
			$sessions,
			static fn( $session ) => is_array( $session ) && (int) ( $session['last_activity'] ?? 0 ) + $inactivity_timeout >= $now
		);
	}

	private static function session_map_budget_bytes( int $session_count ): int {
		return max( 1, $session_count ) * ( self::MAX_CLIENT_PARAMS_BYTES + self::SESSION_ENVELOPE_BYTES );
	}

	private static function read_sessions( int $user_id ): array {
		global $wpdb;

		$meta_key = self::session_meta_key();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Compare-and-swap needs the stored row exactly as the database holds it, not a cached copy.
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s ORDER BY umeta_id ASC LIMIT 1", $user_id, $meta_key ) );

		if ( null === $raw ) {
			return array(
				'raw'      => null,
				'sessions' => array(),
			);
		}

		$sessions = maybe_unserialize( $raw );
		$budget   = self::session_map_budget_bytes( is_array( $sessions ) ? count( $sessions ) : 0 );

		if ( is_array( $sessions ) && strlen( $raw ) <= $budget ) {
			return array(
				'raw'      => $raw,
				'sessions' => $sessions,
			);
		}

		if ( is_array( $sessions ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Deletes an oversized row without sending its value back in the query.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s AND LENGTH( meta_value ) > %d", $user_id, $meta_key, $budget ) );
		} else {
			delete_user_meta( $user_id, $meta_key, $sessions );
		}
		wp_cache_delete( $user_id, 'user_meta' );

		( new ErrorLogMcpErrorHandler() )->log(
			'Discarded an unusable MCP session map.',
			array(
				'user_id' => $user_id,
				'bytes'   => strlen( $raw ),
				'budget'  => $budget,
			)
		);

		return array(
			'raw'      => null,
			'sessions' => array(),
		);
	}

	private static function forget_network_wide_sessions( int $user_id ): void {
		if ( is_multisite() && metadata_exists( 'user', $user_id, self::SESSION_META_KEY ) ) {
			delete_user_meta( $user_id, self::SESSION_META_KEY );
		}
	}

	private static function mutate_sessions( int $user_id, callable $mutation ): bool {
		global $wpdb;

		self::forget_network_wide_sessions( $user_id );
		$meta_key = self::session_meta_key();

		for ( $attempt = 0; $attempt < self::MAX_UPDATE_ATTEMPTS; ++$attempt ) {
			$current = self::read_sessions( $user_id );
			$updated = $mutation( $current['sessions'] );

			if ( $updated === $current['sessions'] ) {
				return true;
			}

			if ( null === $current['raw'] ) {
				if ( array() === $updated || add_user_meta( $user_id, $meta_key, $updated, true ) ) {
					return true;
				}

				continue;
			}

			$row = array(
				'user_id'    => $user_id,
				'meta_key'   => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Targets one user's row through the user_id index.
				'meta_value' => $current['raw'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- The previous value is the compare-and-swap guard.
			);

			$written = array() === $updated
				? $wpdb->delete( $wpdb->usermeta, $row ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Conditional delete; the meta API cannot delete only while the row still holds the value that was read.
				: $wpdb->update( $wpdb->usermeta, array( 'meta_value' => maybe_serialize( $updated ) ), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- update_user_meta() ignores an empty previous value and inserts when the row has gone, so it cannot guard every write.

			wp_cache_delete( $user_id, 'user_meta' );

			if ( $written ) {
				return true;
			}
		}

		( new ErrorLogMcpErrorHandler() )->log(
			'Failed to persist MCP sessions after exhausting update retries.',
			array(
				'user_id'  => $user_id,
				'attempts' => self::MAX_UPDATE_ATTEMPTS,
			)
		);

		return false;
	}
}
