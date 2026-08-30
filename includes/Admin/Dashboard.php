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
			]
		);
	}
}
