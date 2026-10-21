<?php
/**
 * System method handlers for MCP requests.
 *
 * @package McpAdapter
 */

declare( strict_types=1 );

namespace ShimMcp\Server\Handlers\System;

use ShimMcp\Server\Infrastructure\ErrorHandling\McpErrorFactory;

/**
 * Handles system-related MCP methods.
 */
class SystemHandler {
	/**
	 * Handles the ping request.
	 *
	 * @param int|string $request_id Optional. The request ID for JSON-RPC. Default 0.
	 *
	 * @return array Empty result array per MCP specification.
	 */
	public function ping( int|string $request_id = 0 ): array {
		// According to MCP specification, ping returns an empty result.
		return array();
	}

	/**
	 * Handles the roots/list request.
	 *
	 * @param int|string $request_id Optional. The request ID for JSON-RPC. Default 0.
	 *
	 * @return array Response with roots list.
	 */
	public function list_roots( int|string $request_id = 0 ): array {
		// Implement roots listing logic here.
		$roots = array();

		return array(
			'roots' => $roots,
		);
	}

	/**
	 * Handles method not found errors.
	 *
	 * @param array $params     Request parameters.
	 * @param int|string   $request_id Optional. The request ID for JSON-RPC. Default 0.
	 *
	 * @return array Response with method not found error.
	 */
	public function method_not_found( array $params, int|string $request_id = 0 ): array {
		$method = $params['method'] ?? 'unknown';

		return array( 'error' => McpErrorFactory::method_not_found( $request_id, $method )['error'] );
	}
}
