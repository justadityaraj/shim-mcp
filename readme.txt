=== Shim MCP ===
Contributors: justadityaraj
Tags: mcp, ai, claude, model-context-protocol, automation
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 1.1.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Connect WordPress to AI in one click. Full MCP server with 57 abilities.

== Description ==

Shim MCP is a self-contained MCP (Model Context Protocol) server plugin for WordPress. It provides 57 abilities that let any MCP-compatible AI client manage your WordPress site -- posts, pages, media, users, plugins, menus, widgets, comments, options, and system settings. The MCP server and every ability ship in the one plugin, so no companion plugins are required.

The MCP protocol layer is derived from the WordPress project's own MCP Adapter (GPL-2.0), and abilities are registered through WordPress's Abilities API, which core ships from 6.9. See the Credits section below.

Key features:

* 57 WordPress abilities organized across 13 domains
* MCP protocol 2025-06-18 over two transports: Streamable HTTP, and stdio through WP-CLI (`wp shim-mcp serve`)
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

The 57 abilities, the admin dashboard, the WP-CLI stdio bridge and the packaging are original to this plugin. Full breakdown in CREDITS.md.

== Frequently Asked Questions ==

= What is MCP? =

MCP (Model Context Protocol) is an open standard that allows AI clients like Claude to interact with external systems through a structured protocol. It defines how AI tools discover and use capabilities (called "abilities" in WordPress) provided by a server.

= Do I need MCP Adapter or the Abilities API installed alongside this? =

No. Shim MCP is self-contained: it bundles its own MCP server, and the Abilities API it registers against is part of WordPress from 6.9 onwards. Nothing else is required.

= Can it edit wp-config.php? =

Only if you let it. The ability that rewrites the WP_DEBUG constants is switched off until you add `define( 'SHIM_MCP_ALLOW_CONFIG_WRITES', true );` to wp-config.php yourself. Without that line the ability refuses, and it is refused again if DISALLOW_FILE_EDIT or DISALLOW_FILE_MODS is set, if the file is not writable, or if the rewritten contents fail a sanity check.

= It warns me about another MCP plugin. Why? =

Two MCP server plugins running at once can register competing abilities and endpoints, which produces confusing results for the connected AI client. If Shim MCP detects another MCP server or a standalone Abilities API plugin, it shows a notice suggesting you run only one. This is a compatibility warning, not a judgement about the other plugin.

= Can I connect without an application password? =

Yes, on a site you have locally. `wp shim-mcp serve --user=admin --path=/path/to/wordpress` runs the MCP server over WP-CLI and stdio, with no URL, port or password. The setup guide in docs/SETUP.md has tested configurations for Claude Code, Claude Desktop and Cursor.

= What AI clients are supported? =

Any MCP-compatible client works. The admin dashboard provides ready-made config snippets for Claude Code, Claude Desktop, and Cursor.

= Do I need HTTPS? =

HTTPS is strongly recommended for production use since API credentials are transmitted with each request. The plugin will work over HTTP for local development.

= What WordPress capabilities are required? =

Different abilities require different WordPress capabilities. For example, content abilities require `edit_posts`, user abilities require `list_users` or `edit_users`, and system abilities require `manage_options`. See the full abilities reference in docs/ABILITIES.md.

== Changelog ==

= 1.1.0 =

New

* Install plugins from the WordPress.org Plugin Directory by slug with `shim-mcp/plugins-install`. URLs, file paths and uploaded archives are still refused, and the plugin is left inactive until you activate it.
* Every ability label, description and message, the dashboard and the WP-CLI command can now be translated.
* A complete stdio setup guide for Claude Code, Claude Desktop and Cursor on macOS, Linux and Windows.

Fixed

* Ability results return values exactly as WordPress stores them. They were HTML-escaped, so `Rock & Roll` came back as `Rock &amp; Roll`.
* Posts keep iframes, embeds, scripts and block markup for users allowed to post unfiltered HTML, and search-and-replace no longer re-filters the whole post. Backslashes in post, page and media content are no longer stripped, so block attributes survive.
* The stdio transport keeps STDOUT for JSON-RPC only: no `Using server` line, and output from an ability is moved to STDERR. It also no longer exits after an idle socket timeout.
* String JSON-RPC request ids work over both transports.
* Both transports validate JSON-RPC messages the same way, and malformed requests get `-32600` or `-32602` instead of a server error.
* Streamable HTTP answers notifications with 202 and an unknown or expired session with 404, so clients reconnect on their own.
* HTTP sessions are no longer lost when requests arrive at the same moment, are kept separate per site on multisite, and refuse oversized initialization data.
* `initialize` only advertises capabilities the server implements, and `resources/templates/list` is answered.
* `tools/list` descriptors match the MCP Tool schema. Contributed by @mikemikimike.
* The dashboard never exports a revoked or placeholder credential, and an active key can be replaced with Generate New Key.
* No more "already registered" notices for the site and user ability categories.

Development

* Smoke tests and GitHub Actions CI on WordPress 6.9 with PHP 8.0, the latest WordPress with PHP 8.5, and a multisite network.
* The release build fails instead of skipping a file it cannot archive.

= 1.0.2 =

* The dashboard's endpoint probe is labelled and reported honestly: it confirms the REST route is reachable and protected, not that an exported client configuration can authenticate. A 401 or 403 response is never shown as "Connected".


= 1.0.1 =

* Option abilities refuse to read or write options whose names look like stored credentials, and the option-name search no longer returns values at all
* Core admin files are loaded only where a function from them is used
* Removed the redirect to the settings page after activation


= 1.0.0 =
* Initial release with 56 WordPress abilities
* Admin dashboard with API key generation
* Config export for Claude Code, Claude Desktop, Cursor
* Conflict detection for legacy MCP plugins
* MCP protocol 2025-06-18 over Streamable HTTP and WP-CLI stdio

== Upgrade Notice ==

= 1.1.0 =
Ability results and saved post content are no longer altered, stdio and HTTP follow the MCP spec more strictly, and plugins can be installed from WordPress.org by slug.
