# Credits

Shim MCP builds its MCP protocol layer on two projects published by the WordPress project. Both are GPL-2.0, compatible with this plugin's GPL-2.0-or-later license.

## WordPress MCP Adapter

Source: <https://github.com/WordPress/mcp-adapter> (GPL-2.0)

Everything under `includes/Server/`, except `includes/Server/Cli/`, is derived from the MCP Adapter: the Streamable HTTP transport, JSON-RPC request routing, session management, protocol version negotiation, the tools/resources/prompts handlers, schema transformation, and the error and observability infrastructure. The code has been namespaced to `ShimMcp` and adapted to ship inside a self-contained plugin, but the architecture and the bulk of the implementation are upstream's.

Files derived from this project retain their original `@package McpAdapter` docblock tag so the provenance stays visible in the source.

## WordPress Abilities API

Source: <https://github.com/WordPress/abilities-api> (GPL-2.0)

`includes/Compat/AbilitiesApi.php` is a polyfill of the Abilities API for WordPress 6.7 and 6.8, which do not ship it. Every class and function is guarded so the file becomes a no-op once the site runs a WordPress version that provides the API natively. The public interface it reproduces (`WP_Ability`, `WP_Abilities_Registry`, `wp_register_ability()` and companions) is upstream's design, and a polyfill has to match it exactly to be useful.

## Original to this plugin

- `includes/Abilities/` — all 58 WordPress abilities, written directly against the WordPress core API
- `includes/Admin/` — the admin dashboard, application password management, and client config export
- `includes/Server/Cli/` — the WP-CLI command and stdio transport bridge
- The packaging that makes the MCP server, the abilities and the polyfill work as one install with no companion plugins
