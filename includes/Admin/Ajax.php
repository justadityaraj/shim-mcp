<?php
declare(strict_types=1);

namespace ShimMcp\Admin;

use WP_Application_Passwords;

class Ajax {
	public static function init(): void {
		add_action( 'wp_ajax_shim_mcp_generate_key', [ self::class, 'generate_key' ] );
		add_action( 'wp_ajax_shim_mcp_revoke_key', [ self::class, 'revoke_key' ] );
		add_action( 'wp_ajax_shim_mcp_test_connection', [ self::class, 'test_connection' ] );
	}

	public static function generate_key(): void {
		check_ajax_referer( 'shim-mcp', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'shim-mcp' ) ] );
		}

		$user_id = get_current_user_id();

		// Remove existing app password if one exists.
		$existing_uuid = get_user_meta( $user_id, 'shim_mcp_app_password', true );
		if ( $existing_uuid ) {
			WP_Application_Passwords::delete_application_password( $user_id, $existing_uuid );
			delete_user_meta( $user_id, 'shim_mcp_app_password' );
		}

		$result = WP_Application_Passwords::create_new_application_password(
			$user_id,
			[
				'name' => 'Shim MCP',
			]
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		[$password, $item] = $result;

		// Store the UUID for tracking and revocation.
		update_user_meta( $user_id, 'shim_mcp_app_password', $item['uuid'] );

		$user = wp_get_current_user();

		wp_send_json_success(
			[
				'password' => $password,
				'username' => $user->user_login,
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- builds the HTTP Basic credential shown in the client config snippet.
				'base64'   => base64_encode( $user->user_login . ':' . $password ),
			]
		);
	}

	public static function revoke_key(): void {
		check_ajax_referer( 'shim-mcp', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'shim-mcp' ) ] );
		}

		$user_id = get_current_user_id();
		$uuid    = get_user_meta( $user_id, 'shim_mcp_app_password', true );

		if ( ! $uuid ) {
			wp_send_json_error( [ 'message' => __( 'No API key found.', 'shim-mcp' ) ] );
		}

		$deleted = WP_Application_Passwords::delete_application_password( $user_id, $uuid );

		if ( is_wp_error( $deleted ) ) {
			wp_send_json_error( [ 'message' => $deleted->get_error_message() ] );
		}

		delete_user_meta( $user_id, 'shim_mcp_app_password' );

		wp_send_json_success( [ 'message' => __( 'API key revoked.', 'shim-mcp' ) ] );
	}

	public static function test_connection(): void {
		check_ajax_referer( 'shim-mcp', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'shim-mcp' ) ] );
		}

		$user_id = get_current_user_id();
		$uuid    = get_user_meta( $user_id, 'shim_mcp_app_password', true );

		if ( ! $uuid ) {
			wp_send_json_error( [ 'message' => __( 'No API key configured. Generate one first.', 'shim-mcp' ) ] );
		}

		$passwords = WP_Application_Passwords::get_user_application_passwords( $user_id );
		$app_pass  = null;

		foreach ( $passwords as $pass ) {
			if ( $pass['uuid'] === $uuid ) {
				$app_pass = $pass;
				break;
			}
		}

		if ( ! $app_pass ) {
			delete_user_meta( $user_id, 'shim_mcp_app_password' );
			wp_send_json_error( [ 'message' => __( 'Application password not found. Please generate a new key.', 'shim-mcp' ) ] );
		}

		$response = wp_remote_get( rest_url( 'mcp/shim-mcp' ), [ 'timeout' => 10 ] );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [ 'message' => sprintf( /* translators: %s: error message returned by the HTTP request. */ __( 'Endpoint unreachable: %s', 'shim-mcp' ), $response->get_error_message() ) ] );
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 401 === $code || 403 === $code ) {
			wp_send_json_success(
				[
					'message' => __( 'Endpoint reachable and rejecting unauthenticated requests. Client credentials were not verified by this check.', 'shim-mcp' ),
					'status'  => $code,
				]
			);
		}

		if ( 404 === $code ) {
			wp_send_json_error(
				[
					'message' => __( 'Endpoint not found. Re-save Settings > Permalinks, then try again.', 'shim-mcp' ),
					'status'  => $code,
				]
			);
		}

		if ( $code >= 200 && $code < 300 ) {
			wp_send_json_error(
				[
					'message' => __( 'Endpoint answered an unauthenticated request. Something is bypassing REST authentication.', 'shim-mcp' ),
					'status'  => $code,
				]
			);
		}

		wp_send_json_error(
			[
				'message' => sprintf( /* translators: %d: HTTP status code. */ __( 'Unexpected response (HTTP %d).', 'shim-mcp' ), $code ),
				'status'  => $code,
			]
		);
	}
}
