<?php

require_once __DIR__ . '/../lib/harness.php';

use ShimMcp\Server\Transport\Infrastructure\HttpSessionValidator;
use ShimMcp\Server\Transport\Infrastructure\SessionManager;

$user_id  = (int) get_user_by( 'login', 'admin' )->ID;
$meta_key = is_multisite() ? 'mcp_adapter_sessions_' . get_current_blog_id() : 'mcp_adapter_sessions';

$reset = static function () use ( $user_id ): void {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s", $user_id, 'mcp_adapter_sessions%' ) );
	wp_cache_delete( $user_id, 'user_meta' );
};

$sessions = static fn() => SessionManager::get_all_user_sessions( $user_id );

$after_next_read = static function ( callable $concurrent ): void {
	$pending = true;
	add_filter(
		'query',
		static function ( $query ) use ( &$pending, $concurrent ) {
			if ( $pending && false !== strpos( $query, 'mcp_adapter_sessions' ) && 0 !== stripos( ltrim( $query ), 'SELECT meta_value' ) ) {
				$pending = false;
				$concurrent();
			}
			return $query;
		}
	);
};

$reset();
$b = null;
$after_next_read(
	static function () use ( $user_id, &$b ): void {
		$b = SessionManager::create_session( $user_id, array( 'worker' => 'b' ) );
	}
);
$a      = SessionManager::create_session( $user_id, array( 'worker' => 'a' ) );
$stored = $sessions();
check( is_string( $a ) && is_string( $b ) && isset( $stored[ $a ], $stored[ $b ] ), 'two creates that read the same empty map both stay stored', $stored );
check( SessionManager::validate_session( $user_id, $a ) && SessionManager::validate_session( $user_id, $b ), 'both interleaved sessions validate' );

$c = null;
$after_next_read(
	static function () use ( $user_id, &$c ): void {
		$c = SessionManager::create_session( $user_id, array( 'worker' => 'c' ) );
	}
);
$d      = SessionManager::create_session( $user_id, array( 'worker' => 'd' ) );
$stored = $sessions();
check( isset( $stored[ $a ], $stored[ $b ], $stored[ $c ], $stored[ $d ] ), 'two creates that read the same non-empty map both stay stored', array_keys( $stored ) );

$after_next_read(
	static function () use ( $user_id, $a ): void {
		SessionManager::delete_session( $user_id, $a );
	}
);
$e      = SessionManager::create_session( $user_id, array( 'worker' => 'e' ) );
$stored = $sessions();
check( ! isset( $stored[ $a ] ) && isset( $stored[ $b ], $stored[ $e ] ), 'a create racing a delete neither resurrects nor loses a session', array_keys( $stored ) );

$stored                        = $sessions();
$stored[ $c ]['last_activity'] = time() - 120;
update_user_meta( $user_id, $meta_key, $stored );
$after_next_read(
	static function () use ( $user_id, $b ): void {
		SessionManager::delete_session( $user_id, $b );
	}
);
check( SessionManager::validate_session( $user_id, $c ), 'an activity update racing a delete still validates its own session' );
check( ! isset( $sessions()[ $b ] ), 'the activity update does not resurrect the deleted session' );
check( $sessions()[ $c ]['last_activity'] >= time() - 5, 'the activity update itself is stored' );

$reset();
$expired                             = SessionManager::create_session( $user_id );
$live                                = SessionManager::create_session( $user_id );
$stored                              = $sessions();
$stored[ $expired ]['last_activity'] = time() - 2 * DAY_IN_SECONDS;
update_user_meta( $user_id, $meta_key, $stored );
$late = null;
$after_next_read(
	static function () use ( $user_id, &$late ): void {
		$late = SessionManager::create_session( $user_id );
	}
);
SessionManager::cleanup_expired_sessions( $user_id );
$stored = $sessions();
check( ! isset( $stored[ $expired ] ) && isset( $stored[ $live ], $stored[ $late ] ), 'cleanup racing a create removes only the expired session', array_keys( $stored ) );

$stored                          = $sessions();
$stored[ $live ]['last_activity'] = time() - 2 * DAY_IN_SECONDS;
update_user_meta( $user_id, $meta_key, $stored );
same( 1, SessionManager::cleanup_expired_sessions( $user_id ), 'cleanup reports how many sessions it removed' );
check( array( $late ) === array_keys( $sessions() ), 'cleanup leaves the active session' );

$reset();
$inside          = false;
$always_conflict = static function ( $query ) use ( $user_id, &$inside ) {
	if ( ! $inside && false !== strpos( $query, 'mcp_adapter_sessions' ) && 0 !== stripos( ltrim( $query ), 'SELECT meta_value' ) ) {
		$inside = true;
		SessionManager::create_session( $user_id );
		$inside = false;
	}
	return $query;
};
SessionManager::create_session( $user_id );
add_filter( 'query', $always_conflict );
$contended = SessionManager::create_session( $user_id );
remove_filter( 'query', $always_conflict );
same( false, $contended, 'exhausting the retry limit reports failure instead of success' );

$reset();
$session = SessionManager::create_session( $user_id, array( 'clientInfo' => array( 'name' => 'tests' ) ) );
check( is_string( $session ) && SessionManager::validate_session( $user_id, $session ), 'a single session is created and validates' );
check( SessionManager::delete_session( $user_id, $session ) && ! SessionManager::validate_session( $user_id, $session ), 'a deleted session no longer validates' );
same( array(), get_user_meta( $user_id, $meta_key, false ), 'deleting the last session removes the row' );

$session = SessionManager::create_session( $user_id );
add_filter( 'mcp_adapter_session_inactivity_timeout', static fn() => -1 );
check( ! SessionManager::validate_session( $user_id, $session ), 'an inactive session expires' );
remove_all_filters( 'mcp_adapter_session_inactivity_timeout' );
check( ! isset( $sessions()[ $session ] ), 'an expired session is removed when it is checked' );

add_filter( 'mcp_adapter_session_max_per_user', static fn() => 2 );
$first  = SessionManager::create_session( $user_id );
$second = SessionManager::create_session( $user_id );
$third  = SessionManager::create_session( $user_id );
remove_all_filters( 'mcp_adapter_session_max_per_user' );
same( 2, count( $sessions() ), 'the per-user session limit still applies' );
check( ! isset( $sessions()[ $first ] ) || ! isset( $sessions()[ $second ] ), 'the limit evicts an older session to admit the new one' );
check( isset( $sessions()[ $third ] ), 'the newest session is kept' );

$reset();
update_user_meta(
	$user_id,
	$meta_key,
	array(
		'written-before-the-limit' => array(
			'created_at'    => time(),
			'last_activity' => time(),
			'client_params' => array( 'padding' => str_repeat( 'x', 20000 ) ),
		),
	)
);
same( array(), $sessions(), 'a stored session map over the size budget is discarded' );
same( array(), get_user_meta( $user_id, $meta_key, false ), 'the oversized row is deleted' );
check( is_string( SessionManager::create_session( $user_id ) ), 'a new session can be created after the discard' );

$reset();
same( false, SessionManager::create_session( $user_id, array( 'padding' => str_repeat( 'x', 5000 ) ) ), 'initialization parameters over 4 KiB are refused' );
wp_set_current_user( $user_id );
$with_icon = HttpSessionValidator::create_session(
	array(
		'clientInfo' => array(
			'name'  => 'icon-client',
			'icons' => array( array( 'src' => 'data:image/png;base64,' . str_repeat( 'A', 8000 ) ) ),
		),
	)
);
check( is_string( $with_icon ), 'client icons are dropped from stored parameters instead of failing the session', $with_icon );
same( array( 'name' => 'icon-client' ), $sessions()[ $with_icon ]['client_params']['clientInfo'] ?? null, 'the stored client info keeps everything but the icons' );
wp_set_current_user( 0 );

if ( ! is_multisite() ) {
	pass( 'skipped multisite isolation: this site is not a network' );
	$reset();
	return;
}

$reset();
add_user_meta(
	$user_id,
	'mcp_adapter_sessions',
	array(
		'legacy' => array(
			'created_at'    => time(),
			'last_activity' => time(),
			'client_params' => array(),
		),
	)
);
$main_session = SessionManager::create_session( $user_id );
check( ! metadata_exists( 'user', $user_id, 'mcp_adapter_sessions' ), 'the old network-wide session row is retired on multisite' );

$network     = get_network();
$second_blog = get_blog_id_from_url( $network->domain, $network->path . 'second/' );
if ( ! $second_blog ) {
	$second_blog = wpmu_create_blog( $network->domain, $network->path . 'second/', 'Second', $user_id );
}
switch_to_blog( $second_blog );
$second_session = SessionManager::create_session( $user_id );
check( SessionManager::validate_session( $user_id, $second_session ), 'a session on the second site validates there' );
check( ! SessionManager::validate_session( $user_id, $main_session ), 'a session from the main site is not valid on the second site' );
restore_current_blog();

check( SessionManager::validate_session( $user_id, $main_session ), 'the main-site session still validates on the main site' );
check( ! SessionManager::validate_session( $user_id, $second_session ), 'a session from the second site is not valid on the main site' );

$reset();
