<?php
/**
 * Initialize method handler for MCP requests.
 *
 * @package McpAdapter
 */

declare( strict_types=1 );

namespace ShimMcp\Server\Handlers\Initialize;

use ShimMcp\Server\McpServer;
use stdClass;

/**
 * Handles the initialize MCP method.
 */
class InitializeHandler {

	/**
	 * Supported MCP protocol versions in order of preference (newest first).
	 */
	private const SUPPORTED_VERSIONS = array(
		'2025-06-18',
		'2025-03-26',
		'2024-11-05',
	);

	/**
	 * The WordPress MCP instance.
	 *
	 * @var \ShimMcp\Server\McpServer
	 */
	private McpServer $mcp;

	/**
	 * The negotiated protocol version for the current session.
	 *
	 * @var string
	 */
	private string $negotiated_version = '';

	/**
	 * Constructor.
	 *
	 * @param \ShimMcp\Server\McpServer $mcp The WordPress MCP instance.
	 */
	public function __construct( McpServer $mcp ) {
		$this->mcp = $mcp;
	}

	/**
	 * Handles the initialize request with protocol version negotiation.
	 *
	 * Per MCP spec, the server should respond with a version both sides support.
	 * If the client requests a known version, we match it. Otherwise we fall back
	 * to the latest version the server supports.
	 *
	 * @param int   $request_id Optional. The request ID for JSON-RPC. Default 0.
	 * @param array $params     Optional. The initialize request params from the client.
	 *
	 * @return array Response with server capabilities and information.
	 */
	public function handle( int $request_id = 0, array $params = array() ): array {
		$server_info = array(
			'name'    => $this->mcp->get_server_name(),
			'version' => $this->mcp->get_server_version(),
		);

		// Negotiate protocol version with the client
		$this->negotiated_version = $this->negotiate_version( $params );

		// MCP capabilities (compatible across all supported versions)
		$capabilities = array(
			'tools'       => new stdClass(),
			'resources'   => new stdClass(),
			'prompts'     => new stdClass(),
			'logging'     => new stdClass(),
			'completions' => new stdClass(),
		);

		return array(
			'protocolVersion' => $this->negotiated_version,
			'serverInfo'      => $server_info,
			'capabilities'    => (object) $capabilities,
			'instructions'    => $this->mcp->get_server_description(),
		);
	}

	/**
	 * Negotiate the protocol version based on what the client requests.
	 *
	 * @param array $params The initialize request params.
	 *
	 * @return string The negotiated protocol version.
	 */
	private function negotiate_version( array $params ): string {
		$client_version = $params['protocolVersion'] ?? '';

		// If the client requests a version we support, use it
		if ( $client_version && in_array( $client_version, self::SUPPORTED_VERSIONS, true ) ) {
			return $client_version;
		}

		// Default to the latest version we support
		return self::SUPPORTED_VERSIONS[0];
	}

	/**
	 * Get the negotiated protocol version.
	 *
	 * @return string
	 */
	public function get_negotiated_version(): string {
		return $this->negotiated_version;
	}
}
