<?php
/**
 * Transport context object for dependency injection.
 *
 * @package McpAdapter
 */

declare( strict_types=1 );

namespace ShimMcp\Server\Transport\Infrastructure;

use ShimMcp\Server\McpServer;
use ShimMcp\Server\Handlers\Initialize\InitializeHandler;
use ShimMcp\Server\Handlers\Prompts\PromptsHandler;
use ShimMcp\Server\Handlers\Resources\ResourcesHandler;
use ShimMcp\Server\Handlers\System\SystemHandler;
use ShimMcp\Server\Handlers\Tools\ToolsHandler;
use ShimMcp\Server\Infrastructure\ErrorHandling\Contracts\McpErrorHandlerInterface;
use ShimMcp\Server\Infrastructure\Observability\Contracts\McpObservabilityHandlerInterface;

/**
 * Transport context object for dependency injection.
 *
 * Contains all dependencies needed by transport implementations,
 * promoting loose coupling and easier testing.
 *
 * Note: The request_router parameter is optional. If not provided,
 * a RequestRouter instance will be automatically created with this
 * context as its dependency.
 */
class McpTransportContext {

	/**
	 * Initialize the transport context.
	 *
	 * @param \ShimMcp\Server\McpServer             $mcp_server The MCP server instance.
	 * @param \ShimMcp\Server\Handlers\Initialize\InitializeHandler     $initialize_handler The initialize handler.
	 * @param \ShimMcp\Server\Handlers\Tools\ToolsHandler          $tools_handler The tools handler.
	 * @param \ShimMcp\Server\Handlers\Resources\ResourcesHandler      $resources_handler The resources handler.
	 * @param \ShimMcp\Server\Handlers\Prompts\PromptsHandler        $prompts_handler The prompts handler.
	 * @param \ShimMcp\Server\Handlers\System\SystemHandler         $system_handler The system handler.
	 * @param string                $observability_handler The observability handler class name.
	 * @param \ShimMcp\Server\Transport\Infrastructure\RequestRouter|null $request_router The request router service.
	 * @param callable|null         $transport_permission_callback Optional custom permission callback for transport-level authentication.
	 */
	/**
	 * The MCP server instance.
	 *
	 * @var \ShimMcp\Server\McpServer
	 */
	public McpServer $mcp_server;

	/**
	 * The initialize handler.
	 *
	 * @var \ShimMcp\Server\Handlers\Initialize\InitializeHandler
	 */
	public InitializeHandler $initialize_handler;

	/**
	 * The tools handler.
	 *
	 * @var \ShimMcp\Server\Handlers\Tools\ToolsHandler
	 */
	public ToolsHandler $tools_handler;

	/**
	 * The resources handler.
	 *
	 * @var \ShimMcp\Server\Handlers\Resources\ResourcesHandler
	 */
	public ResourcesHandler $resources_handler;

	/**
	 * The prompts handler.
	 *
	 * @var \ShimMcp\Server\Handlers\Prompts\PromptsHandler
	 */
	public PromptsHandler $prompts_handler;

	/**
	 * The system handler.
	 *
	 * @var \ShimMcp\Server\Handlers\System\SystemHandler
	 */
	public SystemHandler $system_handler;

	/**
	 * The observability handler instance.
	 *
	 * @var \ShimMcp\Server\Infrastructure\Observability\Contracts\McpObservabilityHandlerInterface
	 */
	public McpObservabilityHandlerInterface $observability_handler;

	/**
	 * The error handler instance.
	 *
	 * @var \ShimMcp\Server\Infrastructure\ErrorHandling\Contracts\McpErrorHandlerInterface
	 */
	public McpErrorHandlerInterface $error_handler;

	/**
	 * The request router service.
	 */
	public RequestRouter $request_router;

	/**
	 * Optional custom permission callback for transport-level authentication.
	 *
	 * @var callable|callable-string|null
	 */
	public $transport_permission_callback;

	/**
	 * Initialize the transport context.
	 *
	 * @param array{
	 *   mcp_server: \ShimMcp\Server\McpServer,
	 *   initialize_handler: \ShimMcp\Server\Handlers\Initialize\InitializeHandler,
	 *   tools_handler: \ShimMcp\Server\Handlers\Tools\ToolsHandler,
	 *   resources_handler: \ShimMcp\Server\Handlers\Resources\ResourcesHandler,
	 *   prompts_handler: \ShimMcp\Server\Handlers\Prompts\PromptsHandler,
	 *   system_handler: \ShimMcp\Server\Handlers\System\SystemHandler,
	 *   observability_handler: \ShimMcp\Server\Infrastructure\Observability\Contracts\McpObservabilityHandlerInterface,
	 *   request_router?: \ShimMcp\Server\Transport\Infrastructure\RequestRouter,
	 *   transport_permission_callback?: callable|null,
	 *   error_handler?: \ShimMcp\Server\Infrastructure\ErrorHandling\Contracts\McpErrorHandlerInterface
	 * } $properties Properties to set on the context.
	 * Note: request_router is optional and will be auto-created if not provided.
	 */
	public function __construct( array $properties ) {
		foreach ( $properties as $name => $value ) {
			$this->$name = $value;
		}

		// If request_router is provided, we're done
		if ( isset( $properties['request_router'] ) ) {
			return;
		}

		// Create request_router if not provided
		$this->request_router = new RequestRouter( $this );
	}
}
