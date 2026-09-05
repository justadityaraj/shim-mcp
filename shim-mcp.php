<?php
/**
 * Shim MCP
 *
 * @package     ShimMcp
 * @author      Aditya Raj Singh
 * @copyright   2026
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Shim MCP
 * Plugin URI:        https://dev.adityarajsingh.com/shim-mcp/
 * Description:       Connect WordPress to AI in one click. Full MCP server with 56 WordPress abilities, no other plugins needed.
 * Requires at least: 6.9
 * Version:           1.0.3-dev.1
 * Requires PHP:      8.0
 * Author:            Aditya Raj Singh
 * Author URI:        https://adityarajsingh.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain:       shim-mcp
 */

declare(strict_types=1);

namespace ShimMcp;

defined( 'ABSPATH' ) || exit();

define( 'SHIM_MCP_DIR', plugin_dir_path( __FILE__ ) );
define( 'SHIM_MCP_URL', plugin_dir_url( __FILE__ ) );
define( 'SHIM_MCP_VERSION', '1.0.3-dev.1' );

require_once __DIR__ . '/includes/Autoloader.php';

register_activation_hook(
	__FILE__,
	function (): void {
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	function (): void {
		flush_rewrite_rules();
	}
);

if ( Autoloader::register() ) {
	Plugin::instance();
}
