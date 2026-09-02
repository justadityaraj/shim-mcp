=== Shim MCP ===
Contributors: justadityaraj
Tags: mcp, ai, claude, model-context-protocol, automation
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 1.0.1
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Connect WordPress to AI in one click. Full MCP server with 56 abilities.

== Description ==

Shim MCP is a self-contained MCP (Model Context Protocol) server plugin for WordPress. It provides 56 abilities that let any MCP-compatible AI client manage your WordPress site -- posts, pages, media, users, plugins, menus, widgets, comments, options, and system settings. The MCP server and every ability ship in the one plugin, so no companion plugins are required.

The MCP protocol layer is derived from the WordPress project's own MCP Adapter (GPL-2.0), and abilities are registered through WordPress's Abilities API, which core ships from 6.9. See the Credits section below.

Key features:

* 56 WordPress abilities organized across 9 domains
* MCP protocol v2025-06-18 with SSE transport
* Admin dashboard with API key generation
* Config export for Claude Code, Claude Desktop, and Cursor
* Conflict detection for legacy MCP plugins

== Installation ==

1. Upload the plugin zip file via Plugins > Add New > Upload Plugin, or clone the repository into `wp-content/plugins/shim-mcp/`.
2. Activate the plugin through the Plugins menu.
3. Go to Tools > Shim MCP, generate an API key, and copy the config snippet into your AI client.

== Credits ==

Shim MCP builds its MCP protocol layer on two GPL-2.0 projects published by the WordPress project:

* [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) - everything under `includes/Server/` (transport, JSON-RPC routing, session management, tools/resources/prompts handlers, schema transformation, error and observability infrastructure) is derived from it.
* [WordPress Abilities API](https://github.com/WordPress/abilities-api) - every ability is registered through this core API, which WordPress ships from 6.9.

The 56 abilities, the admin dashboard, the WP-CLI stdio bridge and the packaging are original to this plugin. Full breakdown in CREDITS.md.

== Frequently Asked Questions ==

= What is MCP? =

MCP (Model Context Protocol) is an open standard that allows AI clients like Claude to interact with external systems through a structured protocol. It defines how AI tools discover and use capabilities (called "abilities" in WordPress) provided by a server.

= Do I need MCP Adapter or the Abilities API installed alongside this? =

No. Shim MCP is self-contained: it bundles its own MCP server, and the Abilities API it registers against is part of WordPress from 6.9 onwards. Nothing else is required.

= Can it edit wp-config.php? =

Only if you let it. The ability that rewrites the WP_DEBUG constants is switched off until you add `define( 'SHIM_MCP_ALLOW_CONFIG_WRITES', true );` to wp-config.php yourself. Without that line the ability refuses, and it is refused again if DISALLOW_FILE_EDIT or DISALLOW_FILE_MODS is set, if the file is not writable, or if the rewritten contents fail a sanity check.

= It warns me about another MCP plugin. Why? =

Two MCP server plugins running at once can register competing abilities and endpoints, which produces confusing results for the connected AI client. If Shim MCP detects another MCP server or a standalone Abilities API plugin, it shows a notice suggesting you run only one. This is a compatibility warning, not a judgement about the other plugin.

= What AI clients are supported? =

Any MCP-compatible client works. The admin dashboard provides ready-made config snippets for Claude Code, Claude Desktop, and Cursor.

= Do I need HTTPS? =

HTTPS is strongly recommended for production use since API credentials are transmitted with each request. The plugin will work over HTTP for local development.

= What WordPress capabilities are required? =

Different abilities require different WordPress capabilities. For example, content abilities require `edit_posts`, user abilities require `list_users` or `edit_users`, and system abilities require `manage_options`. See the full abilities reference in docs/ABILITIES.md.

== Changelog ==

= 1.0.1 =

* Option abilities refuse to read or write options whose names look like stored credentials, and the option-name search no longer returns values at all
* Core admin files are loaded only where a function from them is used
* Removed the redirect to the settings page after activation


= 1.0.0 =
* Initial release with 56 WordPress abilities
* Admin dashboard with API key generation
* Config export for Claude Code, Claude Desktop, Cursor
* Conflict detection for legacy MCP plugins
* SSE transport with MCP protocol v2025-06-18
