# Shim MCP — WordPress MCP Server for Claude Code, Cursor, and any MCP client

![PHP >= 8.0](https://img.shields.io/badge/PHP-%3E%3D%208.0-777BB4?logo=php&logoColor=white)
![WordPress >= 6.7](https://img.shields.io/badge/WordPress-%3E%3D%206.7-21759B?logo=wordpress&logoColor=white)
![License GPL-2.0-or-later](https://img.shields.io/badge/License-GPL--2.0--or--later-blue)

A self-contained **Model Context Protocol (MCP) server for WordPress**, built for developers. Install one plugin and your WordPress site becomes an MCP server that Claude Code, Claude Desktop, Cursor, Windsurf, Cline, or any MCP-compatible AI client can drive — 58 abilities across posts, pages, media, users, plugins, menus, widgets, comments, options and system management.

No companion plugins. No cloud relay. No account anywhere. Your site talks to your AI client and nothing sits in between.

## Who this is for

Developers who live in a terminal or an editor.

If you run `wp` commands, keep VS Code open all day, and want Claude Code or Cursor to work on a local WordPress install the same way it works on the rest of your codebase — that's the case this was built for. It works fine as a remote server for a production site too, but the local-first path is the one that got the attention.

## Two ways to connect

**1. Local, over stdio (WP-CLI)** — the reason this plugin exists.

```bash
wp shim-mcp serve --user=admin
```

That runs the MCP server as a plain stdio process: JSON-RPC in on STDIN, responses out on STDOUT. No HTTP, no ports, no tunnel, no application password, no OAuth dance. Point Claude Code or Cursor at that command and it connects the way any other local MCP server does.

Worth being precise about what's different here, because several WordPress MCP plugins mention WP-CLI. In those, WP-CLI is a *tool the AI can call* — the model asks the remote server to run a `wp` command on your behalf, over an authenticated HTTP connection. Useful, but the connection is still remote, still HTTP, still needs a token.

Shim uses WP-CLI as the **transport itself**. The server runs as a local process on your machine and speaks MCP over a pipe. There is no HTTP request, no token to issue or revoke, and nothing listening on a port. For a site you have checked out locally, that removes the entire authentication surface rather than securing it.

**2. Remote, over HTTP** — for sites you aren't sitting in front of.

Generate an application password from **Tools → Shim MCP**, copy the config snippet, done. Streamable HTTP transport, MCP spec-compliant, capability-checked on every call.

## Why "Shim"

In systems programming, a **shim** is a thin layer that sits between two interfaces so they can work together without either side changing.

That is exactly what this is. WordPress speaks the [Abilities API](https://github.com/WordPress/abilities-api). AI clients speak MCP. Shim translates between the two and does nothing else.

The name is a promise about scope. It is not an AI product. It does not bundle a chatbot, a content generator, a credits system, or a dashboard that wants to become your workflow. It does not phone home. It is the adapter, not the appliance — and when WordPress core ships the Abilities API natively, the plugin gets *smaller*, not bigger.

## What makes it different

- **The MCP server runs over stdio.** Others expose WP-CLI as a tool reachable through a remote HTTP connection; here WP-CLI *is* the transport, running locally with no port, no token and no HTTP layer at all.
- **Genuinely self-contained.** The MCP server, the abilities, and an Abilities API polyfill for WordPress 6.7 and 6.8 all ship in one plugin. Nothing else to install, on any supported version.
- **Abilities API native.** Every ability is registered through WordPress's own `wp_register_ability()`. Nothing lives in a private tool registry, so abilities registered by *other* plugins are exposed too, automatically, with no adapter code.
- **Per-object permission checks, not just blanket ones.** Every ability declares a capability, and every ability that touches a specific object re-checks the per-object capability (`edit_post`, `delete_post`, `read_post`, `edit_user`, `delete_user`, `edit_comment`) against that object before reading or mutating it. A contributor's client cannot edit an editor's post. Holding `edit_posts` is not treated as permission to edit *any* post.
- **58 abilities, deliberately.** This is not a race to the largest tool count. Every ability is documented with its required capability in the [abilities reference](docs/ABILITIES.md).
- **Nothing leaves your server.** No proxy, no relay, no telemetry, no vendor account.

## Quick start

**Local development (stdio)**

```bash
git clone https://github.com/justadityaraj/shim-mcp.git wp-content/plugins/shim-mcp
wp plugin activate shim-mcp
wp shim-mcp serve --user=admin
```

Then register it with your client. For Claude Code:

```bash
claude mcp add shim -- wp shim-mcp serve --user=admin --path=/full/path/to/wordpress
```

**Remote site (HTTP)**

1. Install the plugin — upload the zip via **Plugins → Add New → Upload Plugin**, or clone into `wp-content/plugins/`.
2. Go to **Tools → Shim MCP** and click Generate to create an application password.
3. Copy the config snippet shown on the dashboard into your AI client's config.

Ready-made snippets for Claude Code, Claude Desktop and Cursor are on the dashboard.

## Available commands

```bash
wp shim-mcp serve [--server=<server-id>] [--user=<id|login|email>]   # run the MCP server over stdio
wp shim-mcp list  [--format=<format>]                                # list registered MCP servers
```

## Features

- 58 WordPress abilities across content, media, users, plugins, menus, widgets, comments, options and system management
- MCP protocol 2024-11-05, 2025-03-26 and 2025-06-18 with automatic version negotiation
- Two transports: stdio over WP-CLI, and Streamable HTTP
- Admin dashboard with application password generation and revocation
- Config export for Claude Code, Claude Desktop and Cursor
- Abilities API polyfill for WordPress 6.7 and 6.8
- Detects other MCP server plugins and warns when two would compete

## Requirements

- WordPress 6.7 or higher
- PHP 8.0 or higher
- WP-CLI, for the stdio transport only

## Documentation

- [Setup Guide](docs/SETUP.md) — installation, client configuration, troubleshooting
- [Abilities Reference](docs/ABILITIES.md) — all 58 abilities with their required capabilities
- [Security Policy](SECURITY.md) — the trust model, and how to report a vulnerability

## Status

Early release. The plugin is feature-complete and the abilities are documented, but it has not yet been through a broad range of hosting environments. Issues and reports are welcome.

## Maintainer

Built and maintained by [Aditya Raj Singh](https://adityarajsingh.com) at [BNCW Enterprises](https://bncw.in).

## Credits

Shim MCP builds its protocol layer on WordPress's own MCP work. The server layer under `includes/Server/` comes from the [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) (GPL-2.0), and `includes/Compat/AbilitiesApi.php` polyfills the [WordPress Abilities API](https://github.com/WordPress/abilities-api) (GPL-2.0) for WordPress 6.7 and 6.8. The 58 abilities, the admin dashboard, the WP-CLI stdio bridge and the packaging are original to this plugin. See [CREDITS.md](CREDITS.md) for the full breakdown.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE) for the full text.
