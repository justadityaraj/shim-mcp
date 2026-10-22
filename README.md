# Shim MCP — WordPress MCP Server for Claude Code, Cursor, and any MCP client

![PHP >= 8.0](https://img.shields.io/badge/PHP-%3E%3D%208.0-777BB4?logo=php&logoColor=white)
![WordPress >= 6.9](https://img.shields.io/badge/WordPress-%3E%3D%206.9-21759B?logo=wordpress&logoColor=white)
![License GPL-2.0-or-later](https://img.shields.io/badge/License-GPL--2.0--or--later-blue)
[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/shim-mcp)](https://wordpress.org/plugins/shim-mcp/)
[![CodeRabbit Pull Request Reviews](https://img.shields.io/coderabbit/prs/github/justadityaraj/shim-mcp?utm_source=oss&utm_medium=github&utm_campaign=justadityaraj%2Fshim-mcp&labelColor=171717&color=FF570A&link=https%3A%2F%2Fcoderabbit.ai&label=CodeRabbit+Reviews)](https://coderabbit.ai)

A self-contained **Model Context Protocol (MCP) server for WordPress**, built for developers. Install one plugin and your WordPress site becomes an MCP server that Claude Code, Claude Desktop, Cursor, Windsurf, Cline, or any MCP-compatible AI client can drive — 57 abilities across posts, pages, media, users, plugins, menus, widgets, comments, options and system management.

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

Some WordPress MCP plugins go further and let the AI run WP-CLI commands on your server through an authenticated HTTP connection. Shim never runs WP-CLI commands for the AI.

Shim uses WP-CLI only to start the MCP server itself, the same way the official MCP Adapter's `wp mcp-adapter serve` does. The server runs as a local process on your machine and speaks MCP over a pipe. There is no HTTP request, no token to issue or revoke, and nothing listening on a port. For a site you have checked out locally, that removes the entire authentication surface rather than securing it.

**2. Remote, over HTTP** — for sites you aren't sitting in front of.

Generate an application password from **Tools → Shim MCP**, copy the config snippet, done. Streamable HTTP transport, MCP spec-compliant, capability-checked on every call.

## Why "Shim"

In systems programming, a **shim** is a thin layer that sits between two interfaces so they can work together without either side changing.

That is exactly what this is. WordPress speaks the [Abilities API](https://github.com/WordPress/abilities-api). AI clients speak MCP. Shim translates between the two and does nothing else.

The name is a promise about scope. It is not an AI product. It does not bundle a chatbot, a content generator, a credits system, or a dashboard that wants to become your workflow. It does not phone home. It is the adapter, not the appliance — and when WordPress core ships the Abilities API natively, the plugin gets *smaller*, not bigger.

## What makes it different

- **Local use needs no password and no port.** `wp shim-mcp serve` runs the MCP server over stdio through WP-CLI, with no HTTP layer and no token, and Shim never runs WP-CLI commands on the AI's behalf.
- **Genuinely self-contained.** The MCP server and all 57 abilities ship in one plugin. No companion plugin, no framework, no service to sign up for.
- **Abilities API native.** Every ability is registered through WordPress's own `wp_register_ability()`. Nothing lives in a private tool registry, so abilities registered by *other* plugins are exposed too, automatically, with no adapter code.
- **Per-object permission checks, not just blanket ones.** Every ability declares a capability, and every ability that touches a specific object re-checks the per-object capability (`edit_post`, `delete_post`, `read_post`, `edit_user`, `delete_user`, `edit_comment`) against that object before reading or mutating it. A contributor's client cannot edit an editor's post. Holding `edit_posts` is not treated as permission to edit *any* post.
- **57 abilities, deliberately.** This is not a race to the largest tool count. Every ability is documented with its required capability in the [abilities reference](docs/ABILITIES.md).
- **Nothing leaves your server.** No proxy, no relay, no telemetry, no vendor account.
- **The one dangerous ability is off by default.** Rewriting `wp-config.php` stays disabled unless you opt in explicitly with `define( 'SHIM_MCP_ALLOW_CONFIG_WRITES', true );`.

## Quick start

**Local development (stdio)**

```bash
git clone https://github.com/justadityaraj/shim-mcp.git wp-content/plugins/shim-mcp
wp plugin activate shim-mcp
wp shim-mcp serve --user=admin
```

Then register it with your client. For Claude Code on macOS or Linux:

```bash
claude mcp add shim-mcp -- wp shim-mcp serve --user=admin --path=/path/to/wordpress
```

Windows, Claude Desktop and Cursor need a slightly different command; the [local setup guide](docs/SETUP.md#local-setup-stdio) has a tested configuration for each, plus a one-line check that the server answers before you connect a client.

**Remote site (HTTP)**

1. Install the plugin — search for **Shim MCP** under **Plugins → Add New**, or download it from the [WordPress.org listing](https://wordpress.org/plugins/shim-mcp/).
2. Go to **Tools → Shim MCP** and click Generate to create an application password.
3. Copy the config snippet shown on the dashboard into your AI client's config.

Ready-made snippets for Claude Code, Claude Desktop and Cursor are on the dashboard.

## Available commands

```bash
wp shim-mcp serve [--server=<server-id>] [--user=<id|login|email>]   # run the MCP server over stdio
wp shim-mcp list  [--format=<format>]                                # list registered MCP servers
```

## Features

- 57 WordPress abilities across content, media, users, plugins, menus, widgets, comments, options and system management
- Plugin abilities cover listing, activating, deactivating and deleting installed plugins, and installing from the WordPress.org directory by slug; installing from a URL or an uploaded archive is deliberately out of scope
- MCP protocol 2024-11-05, 2025-03-26 and 2025-06-18 with automatic version negotiation
- Two transports: stdio over WP-CLI, and Streamable HTTP
- Admin dashboard with application password generation and revocation
- Config export for Claude Code, Claude Desktop and Cursor
- Detects other MCP server plugins and warns when two would compete

## Requirements

- WordPress 6.9 or higher (for the Abilities API, which core ships from 6.9)
- PHP 8.0 or higher
- WP-CLI, for the stdio transport only

## Documentation

- [Setup Guide](docs/SETUP.md) — installation, client configuration, troubleshooting
- [Abilities Reference](docs/ABILITIES.md) — all 57 abilities with their required capabilities
- [Security Policy](SECURITY.md) — the trust model, and how to report a vulnerability

## Status

**Approved and live on the [WordPress.org Plugin Directory](https://wordpress.org/plugins/shim-mcp/).** Install it directly from Plugins → Add New in wp-admin, or download the zip from the [releases page](https://github.com/justadityaraj/shim-mcp/releases) here.

It is feature-complete and the abilities are documented, but it has not yet run across a broad range of hosting environments. Issues and reports are welcome.

### Verification

Every push and pull request runs the smoke tests in GitHub Actions on WordPress 6.9 with PHP 8.0, the latest WordPress with PHP 8.5, and a multisite network. They install the release zip on a fresh site and drive it over stdio and Streamable HTTP: all 57 abilities registering through core's native Abilities API, permission checks, session handling, protocol errors, and stored data coming back unchanged. See [Running the tests](#running-the-tests).

| Check | Result |
|---|---|
| WordPress Plugin Check (all categories, including experimental and low-severity checks) | 0 errors, 0 warnings |
| PHP_CodeSniffer, WordPress-Extra | 0 violations |
| PHPStan level 5 with WordPress stubs | 0 issues |
| `php -l`, all files | pass |
| PHPCompatibility, PHP 8.0 to 8.5 | 0 issues |

Plugin Check is an automated scan of the directory's coding requirements. Passing it says the code meets those standards; it is not a review outcome and implies no endorsement by WordPress.org.

## Development version

Stable releases come from `main` and are what the [WordPress.org listing](https://wordpress.org/plugins/shim-mcp/) serves. Work in progress lives on `dev`.

If you want to try unreleased changes, take the newest **pre-release** from the [releases page](https://github.com/justadityaraj/shim-mcp/releases) — those are built the same way as stable releases and install normally through **Plugins → Add New → Upload Plugin**.

To track `dev` directly instead:

```bash
git clone -b dev https://github.com/justadityaraj/shim-mcp.git wp-content/plugins/shim-mcp
```

Clone into a directory named exactly `shim-mcp`. GitHub's "Download ZIP" button produces a `shim-mcp-dev` folder, which WordPress treats as a second, separate plugin.

Pre-release builds are unreleased by definition: they have not been through the release checks, and they are not on WordPress.org. Do not run them on a production site.

### Running the tests

The smoke tests build the release zip, install it on a disposable WordPress site that runs on SQLite, and talk to the plugin over MCP. They need PHP 8.0+ with the `pdo_sqlite` and `zip` extensions, and network access for the first run. No database server or Docker is needed.

```bash
composer install          # PHPCS, PHPStan and the WordPress stubs
composer lint             # PHP_CodeSniffer
composer analyse          # PHPStan
composer test:setup       # build the zip and create the test site
composer test             # run every suite against that site
```

`php tests/run.php stdio` runs only the suites whose name contains `stdio`. Set `WP_VERSION` before `test:setup` to test another WordPress release, `WP_MULTISITE=1` to install a multisite network, and `SHIM_TEST_ROOT` to move the test site out of the system temp directory. The zip is built from `git ls-files`, so add a new file to git before rebuilding. GitHub Actions runs the same commands on every push and pull request, on the minimum supported pair (WordPress 6.9, PHP 8.0), on the latest WordPress with PHP 8.5, and on a latest-WordPress multisite network.

## Maintainer

Built and maintained by [Aditya Raj Singh](https://adityarajsingh.com) at [BNCW Enterprises](https://bncw.in).

## Credits

Shim MCP builds on other people's GPL work. Its first version was built by an autonomous fleet of coding agents that took a good part of their code from Daniel Agrici's [WP MCP Ultimate](https://github.com/AgriciDaniel/wp-mcp-ultimate) (GPL-2.0-or-later): the plugin bootstrap, the admin dashboard, the WP-CLI command and the layout of the ability classes. Everything under `includes/Server/` is derived from the [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) (GPL-2.0), and the abilities are registered through WordPress's own [Abilities API](https://github.com/WordPress/abilities-api), which core ships from 6.9. Most of the ability code, the tests and the 1.1.0 changes were written for this plugin. See [CREDITS.md](CREDITS.md) for the full breakdown.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE) for the full text.
