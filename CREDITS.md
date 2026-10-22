# Credits

Shim MCP builds on three GPL projects. All are compatible with this plugin's GPL-2.0-or-later license.

## WP MCP Ultimate

Source: <https://github.com/AgriciDaniel/wp-mcp-ultimate> by Daniel Agrici (GPL-2.0-or-later)

The first version of Shim MCP was built by an autonomous fleet of coding agents that worked through the existing WordPress MCP plugins looking for the best approach. They took a good part of their code from WP MCP Ultimate, and much of it is still here:

- `includes/Plugin.php`, `includes/Autoloader.php` and `includes/Abilities/Registry.php`: the plugin bootstrap, autoloader and ability registry
- `includes/Admin/`, `assets/css/admin.css` and `assets/js/admin.js`: the admin dashboard, application password handling and client config export
- `includes/Server/Cli/`: the WP-CLI command and stdio bridge
- `includes/Server/DefaultServerFactory.php` and `includes/Server/McpAdapter.php`, by way of WP MCP Ultimate's copy of the MCP Adapter
- `uninstall.php`
- the layout of the ability classes in `includes/Abilities/` and parts of their code
- the idea of shipping the MCP server and the abilities together as one plugin

Thank you, Daniel.

## WordPress MCP Adapter

Source: <https://github.com/WordPress/mcp-adapter> (GPL-2.0)

Everything under `includes/Server/` is derived from the MCP Adapter, partly by way of WP MCP Ultimate: the Streamable HTTP transport, JSON-RPC request routing, session management, protocol version negotiation, the tools/resources/prompts handlers, schema transformation, the error and observability infrastructure, and the WP-CLI command. The code has been namespaced to `ShimMcp` and adapted to ship inside a self-contained plugin, but the architecture and the bulk of the implementation are upstream's.

Files derived from this project retain their original `@package McpAdapter` docblock tag so the provenance stays visible in the source.

## WordPress Abilities API

Source: <https://github.com/WordPress/abilities-api> (GPL-2.0)

Every ability in this plugin is registered through the Abilities API (`wp_register_ability()` and companions), which WordPress ships as part of core from 6.9. Shim MCP requires WordPress 6.9 for that reason and carries no copy of the API itself.

## Written for this plugin

- Most of the code of the 57 abilities in `includes/Abilities/`, which has been largely rewritten from its starting point
- The test harness and CI in `tests/` and `.github/`
- The 1.1.0 changes: protocol and session fixes in `includes/Server/`, plugin installs by WordPress.org slug, translations, and the stdio setup guide
