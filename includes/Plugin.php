<?php
declare(strict_types=1);

namespace ShimMcp;

use ShimMcp\Server\McpAdapter;
use ShimMcp\Abilities\Registry;
use ShimMcp\Admin\Ajax;
use ShimMcp\Admin\Dashboard;

final class Plugin {
	private static self $instance;

	public static function instance(): self {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
			self::$instance->setup();
		}
		return self::$instance;
	}

	private function setup(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			add_action( 'admin_notices', array( $this, 'render_unsupported_notice' ) );
			return;
		}

		// Register all WordPress abilities
		if ( class_exists( Registry::class ) ) {
			Registry::init();
		}

		// Initialize MCP server
		if ( class_exists( McpAdapter::class ) ) {
			McpAdapter::instance();
		}

		// Admin dashboard and AJAX handlers
		if ( is_admin() ) {
			if ( class_exists( Dashboard::class ) ) {
				Dashboard::init();
			}
			if ( class_exists( Ajax::class ) ) {
				Ajax::init();
			}
		}

		// Conflict detection
		add_action( 'admin_init', [ $this, 'check_conflicts' ] );

		// First-time activation redirect
	}

	public function render_unsupported_notice(): void {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Shim MCP needs the WordPress Abilities API, which ships with WordPress 6.9 and later. Please update WordPress to use this plugin.', 'shim-mcp' )
		);
	}

	public function check_conflicts(): void {
		$conflicts = [
			'mcp-adapter/mcp-adapter.php'     => 'MCP Adapter',
			'mcp-expose-abilities/mcp-expose-abilities.php' => 'MCP Expose Abilities',
			'abilities-api/abilities-api.php' => 'Abilities API',
		];

		foreach ( $conflicts as $plugin_file => $plugin_name ) {
			if ( is_plugin_active( $plugin_file ) ) {
				add_action(
					'admin_notices',
					function () use ( $plugin_name ) {
						printf(
							'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
							sprintf(
								/* translators: %s: name of the other active MCP plugin. */
								esc_html__( 'Shim MCP runs its own MCP server, so running it alongside %s can produce competing abilities and endpoints. Consider keeping only one active.', 'shim-mcp' ),
								'<strong>' . esc_html( $plugin_name ) . '</strong>'
							)
						);
					}
				);
			}
		}
	}

	public function __clone() {
		_doing_it_wrong( __FUNCTION__, 'Cloning is not allowed.', '1.0.0' );
	}

	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, 'Deserializing is not allowed.', '1.0.0' );
	}
}
