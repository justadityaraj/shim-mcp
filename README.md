# Shim MCP

![PHP >= 8.0](https://img.shields.io/badge/PHP-%3E%3D%208.0-777BB4?logo=php&logoColor=white)
![WordPress >= 6.7](https://img.shields.io/badge/WordPress-%3E%3D%206.7-21759B?logo=wordpress&logoColor=white)
![License GPL-2.0-or-later](https://img.shields.io/badge/License-GPL--2.0--or--later-blue)

Connect WordPress to AI in one click. A self-contained MCP (Model Context Protocol) server plugin with 58 WordPress abilities -- manage posts, pages, media, users, plugins, menus, comments, and more through any MCP-compatible AI client. No other plugins needed.

## Quick Start

1. **Install the plugin**: Upload the zip file via Plugins > Add New > Upload Plugin, or clone this repository into `wp-content/plugins/`.
2. **Generate an API key**: Go to Tools > Shim MCP in your WordPress admin and click Generate to create an Application Password.
3. **Configure your AI client**: Copy the MCP server config snippet shown on the dashboard into your AI client's configuration file.

## Features

- 58 WordPress abilities covering content, media, users, plugins, menus, widgets, comments, options, and system management
- MCP protocol compliance (2024-11-05, 2025-03-26, 2025-06-18 with auto-negotiation)
- Streamable HTTP transport
- Admin dashboard with API key management and one-click generation
- Config export snippets for Claude Code, Claude Desktop, and Cursor
- Detects other MCP server plugins and warns when two would compete
- Abilities API polyfill for WordPress < 6.9

## Requirements

- PHP 8.0 or higher
- WordPress 6.7 or higher

## Documentation

- [Setup Guide](docs/SETUP.md) -- Detailed installation and configuration instructions
- [Abilities Reference](docs/ABILITIES.md) -- Complete list of all 58 abilities with descriptions and required capabilities

## Maintainer

Built and maintained by [Aditya Raj Singh](https://adityarajsingh.com) at [BNCW Enterprises](https://bncw.in).

## Credits

Shim MCP builds its protocol layer on WordPress's own MCP work. The server layer under `includes/Server/` comes from the [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) (GPL-2.0), and `includes/Compat/AbilitiesApi.php` polyfills the [WordPress Abilities API](https://github.com/WordPress/abilities-api) (GPL-2.0) for WordPress 6.7 and 6.8. The 58 abilities, the admin dashboard, the WP-CLI stdio bridge and the packaging are original to this plugin. See [CREDITS.md](CREDITS.md) for the full breakdown.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE) for the full text.
