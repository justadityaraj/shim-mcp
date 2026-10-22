<?php
declare(strict_types=1);

namespace ShimMcp\Admin;

class Dashboard {
	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}

	public static function add_menu_page(): void {
		add_management_page(
			'Shim MCP',
			'Shim MCP',
			'manage_options',
			'shim-mcp',
			[ self::class, 'render_page' ]
		);
	}

	public static function render_page(): void {
		include SHIM_MCP_DIR . 'includes/Admin/views/dashboard.php';
	}

	public static function enqueue_assets( string $hook ): void {
		if ( 'tools_page_shim-mcp' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'shim-mcp-admin',
			SHIM_MCP_URL . 'assets/css/admin.css',
			[],
			SHIM_MCP_VERSION
		);

		wp_enqueue_script(
			'shim-mcp-admin',
			SHIM_MCP_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			SHIM_MCP_VERSION,
			true
		);

		wp_localize_script(
			'shim-mcp-admin',
			'shimMcp',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'shim-mcp' ),
				'restUrl' => rest_url( 'mcp/shim-mcp' ),
				'siteUrl' => site_url(),
				'i18n'    => [
					'apiKeyActive'      => __( 'API Key Active', 'shim-mcp' ),
					'generateNewKey'    => __( 'Generate New Key', 'shim-mcp' ),
					'generateKey'       => __( 'Generate API Key', 'shim-mcp' ),
					'revokeKey'         => __( 'Revoke Key', 'shim-mcp' ),
					'generating'        => __( 'Generating…', 'shim-mcp' ),
					'revoking'          => __( 'Revoking…', 'shim-mcp' ),
					'checking'          => __( 'Checking…', 'shim-mcp' ),
					'checkEndpoint'     => __( 'Check Endpoint', 'shim-mcp' ),
					'copied'            => __( 'Copied!', 'shim-mcp' ),
					'keyConfigured'     => __( 'Key configured — run the endpoint check', 'shim-mcp' ),
					'noKey'             => __( 'No API key configured', 'shim-mcp' ),
					'confirmRegenerate' => __( 'Generating a new key revokes the current one. MCP clients using it will stop working.', 'shim-mcp' ),
					'confirmRevoke'     => __( 'Are you sure you want to revoke the API key? MCP clients will stop working.', 'shim-mcp' ),
					'requestFailed'     => __( 'Request failed. Please try again.', 'shim-mcp' ),
					'unknownError'      => __( 'Unknown error', 'shim-mcp' ),
					'endpointUnchecked' => __( 'Endpoint reachable; client credentials were not verified.', 'shim-mcp' ),
					'connectionFailed'  => __( 'Connection failed', 'shim-mcp' ),
					'checkFailed'       => __( 'Request failed', 'shim-mcp' ),
					/* translators: %s: error message from the server. */
					'error'             => __( 'Error: %s', 'shim-mcp' ),
				],
			]
		);
	}
}
