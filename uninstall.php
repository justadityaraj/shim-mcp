<?php
/**
 * Uninstall routine for Shim MCP.
 *
 * @package ShimMcp
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

(
	static function (): void {
		delete_option( 'shim_mcp_settings' );
		delete_transient( 'shim_mcp_activated' );

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- runs once on uninstall to find the application passwords this plugin issued.
		$users = get_users( array( 'meta_key' => 'shim_mcp_app_password' ) );

		foreach ( $users as $user ) {
			$uuid = get_user_meta( $user->ID, 'shim_mcp_app_password', true );

			if ( $uuid && class_exists( 'WP_Application_Passwords' ) ) {
				WP_Application_Passwords::delete_application_password( $user->ID, $uuid );
			}

			delete_user_meta( $user->ID, 'shim_mcp_app_password' );
		}
	}
)();
