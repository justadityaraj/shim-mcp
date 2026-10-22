# Setup Guide

Shim MCP speaks MCP over two transports. Pick one before you configure a client; the two never share credentials or configuration formats.

| | Local (stdio) | Remote (HTTP) |
|---|---|---|
| Runs | `wp shim-mcp serve` as a process on your machine | inside WordPress, at `/wp-json/mcp/shim-mcp` |
| Use it for | a site you have checked out locally | a site you reach over the network |
| Needs | WP-CLI and PHP on the machine running the client | an application password, HTTPS |
| URL, port, password | none | yes |

## Requirements

- PHP 8.0 or higher
- WordPress 6.9 or higher
- Local (stdio): WP-CLI 2.x on the same machine as your MCP client
- Remote (HTTP): HTTPS in production, because the credentials travel with every request

## Installation

### Option A: Upload via WordPress Admin

1. Install from the [WordPress.org Plugin Directory](https://wordpress.org/plugins/shim-mcp/), or download the latest release zip from the [Releases page](https://github.com/justadityaraj/shim-mcp/releases)
2. In WordPress admin, go to **Plugins > Add New**, search for **Shim MCP**, and install it directly (or use **Upload Plugin** with a zip from [GitHub Releases](https://github.com/justadityaraj/shim-mcp/releases))
3. Choose the zip file and click **Install Now**
4. Click **Activate Plugin**

### Option B: Clone Repository

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/justadityaraj/shim-mcp.git
```

Then activate the plugin in **Plugins > Installed Plugins**, or with `wp plugin activate shim-mcp`.

---

## Local setup (stdio)

The client starts `wp shim-mcp serve` as a child process and exchanges JSON-RPC with it over STDIN and STDOUT. There is no URL, no port, no application password, and the HTTP endpoint does not need to be reachable. The server acts as the WordPress user you name with `--user`, with exactly that user's capabilities.

### Check the prerequisites

```bash
wp --info
wp --path=/path/to/wordpress plugin is-active shim-mcp && echo active
```

`wp --info` must report PHP 8.0 or newer. `/path/to/wordpress` is the folder that holds `wp-config.php`.

### Check the server before you configure a client

Send one `initialize` request by hand. The first and only line on STDOUT must be the JSON response.

macOS and Linux:

```bash
echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"check","version":"1"}}}' | wp shim-mcp serve --user=admin --path=/path/to/wordpress
```

Windows PowerShell:

```powershell
'{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"check","version":"1"}}}' | wp shim-mcp serve --user=admin --path=C:/path/to/wordpress
```

The response starts with `{"jsonrpc":"2.0","id":1,"result":{"protocolVersion":"2025-06-18","serverInfo":{"name":"Shim MCP"`. Status lines such as `[MCP STDIO Bridge] MCP STDIO Bridge started` go to STDERR and are expected.

### The command and its options

```text
wp shim-mcp serve --user=<login|id|email> --path=<WordPress folder> [--server=<server-id>]
```

- `--path` is the absolute path of the WordPress folder. Always set it in a client configuration: clients start the server from a working directory you do not control.
- `--user` picks the WordPress account the server acts as. Without it every ability refuses to run, because abilities require a logged-in user. Use a login, a numeric ID or an email address.
- `--server` is only needed when another plugin registers its own MCP server. Without it the first registered server is used, which is Shim MCP's `shim-mcp-server` unless another plugin got there first. `wp shim-mcp list` shows the IDs.

### Claude Code

Add the server from your terminal.

macOS and Linux:

```bash
claude mcp add shim-mcp -- wp shim-mcp serve --user=admin --path=/path/to/wordpress
```

Windows. `wp` is a `.bat` file on Windows, which Claude Code cannot start directly, so run it through `cmd /c`. In Command Prompt:

```bat
claude mcp add shim-mcp -- cmd /c wp shim-mcp serve --user=admin --path=C:/path/to/wordpress
```

In PowerShell, quote the `--`. PowerShell consumes a bare `--` itself, and Claude Code then reads `--user` as one of its own options (`error: unknown option '--user=admin'`):

```powershell
claude mcp add shim-mcp '--' cmd /c wp shim-mcp serve --user=admin --path=C:/path/to/wordpress
```

In Git Bash, write `cmd //c`: Git Bash rewrites a lone `/c` into the path `C:/`.

Everything after `--` is the server command. Add `--scope project` to write it to `.mcp.json` in the current folder so the rest of the team gets it; that file looks like this:

```json
{
  "mcpServers": {
    "shim-mcp": {
      "type": "stdio",
      "command": "wp",
      "args": ["shim-mcp", "serve", "--user=admin", "--path=/path/to/wordpress"]
    }
  }
}
```

Run `claude mcp list` to see the server's status, or `/mcp` inside a session.

### Claude Desktop

Edit `claude_desktop_config.json` (**Settings > Developer > Edit Config**), then restart Claude Desktop.

- macOS: `~/Library/Application Support/Claude/claude_desktop_config.json`
- Windows: `%APPDATA%\Claude\claude_desktop_config.json`

Desktop apps do not load your shell profile, so `wp` and `php` are usually not on their `PATH`. Give absolute paths and start the WP-CLI phar with PHP directly. Find them with `which php` and `which wp` (macOS and Linux; Homebrew on Apple silicon uses `/opt/homebrew/bin`) or `where php` and `where wp` (Windows, where `wp-cli.phar` sits next to `wp.bat`).

macOS and Linux:

```json
{
  "mcpServers": {
    "shim-mcp": {
      "command": "/usr/local/bin/php",
      "args": ["/usr/local/bin/wp", "shim-mcp", "serve", "--user=admin", "--path=/path/to/wordpress"]
    }
  }
}
```

Windows:

```json
{
  "mcpServers": {
    "shim-mcp": {
      "command": "C:/php/php.exe",
      "args": ["C:/wp-cli/wp-cli.phar", "shim-mcp", "serve", "--user=admin", "--path=C:/path/to/wordpress"]
    }
  }
}
```

Use forward slashes in JSON on Windows, or double every backslash.

### Cursor

Add the same entry to `.cursor/mcp.json` in your project, or to `~/.cursor/mcp.json` for every project. Cursor also starts servers without your shell profile, so use absolute paths as for Claude Desktop.

macOS and Linux:

```json
{
  "mcpServers": {
    "shim-mcp": {
      "command": "/usr/local/bin/php",
      "args": ["/usr/local/bin/wp", "shim-mcp", "serve", "--user=admin", "--path=/path/to/wordpress"]
    }
  }
}
```

Windows:

```json
{
  "mcpServers": {
    "shim-mcp": {
      "command": "C:/php/php.exe",
      "args": ["C:/wp-cli/wp-cli.phar", "shim-mcp", "serve", "--user=admin", "--path=C:/path/to/wordpress"]
    }
  }
}
```

### Troubleshooting stdio

**`wp: command not found`, `'wp' is not recognized`, or `spawn wp ENOENT`.** The client cannot find WP-CLI on its `PATH`. Use the absolute-path form above, which starts PHP directly and works in every client. On Windows, `wp` is a `.bat` file, and a client that starts programs without a shell, as Claude Code does, fails with `spawn wp ENOENT` even when `wp` works in your terminal; run it through `cmd /c` or use the absolute-path form.

**`Warning: No WordPress installation found` followed by `Error: 'shim-mcp' is not a registered wp command`.** `--path` does not point at the folder that holds `wp-config.php`, so WP-CLI never loaded the plugin. Check it with `wp --path=/path/to/wordpress core version`.

**`Error: Invalid user ID, email or login: 'admin'`.** `--user` must be an existing login, ID or email on that site. List them with `wp --path=/path/to/wordpress user list --fields=ID,user_login`.

**Every tool call fails with `Permission denied: User must be authenticated to access this ability`.** `--user` is missing from the configuration.

**The client reports a parse error, `Unexpected token`, or a server that exits straight after starting.** Something other than JSON-RPC reached STDOUT. Run the check above and look at what comes before the JSON response:
- A PHP warning or notice: your PHP CLI prints errors to STDOUT. Set `display_errors = stderr` in the CLI `php.ini`, or fix the code that raises it.
- Text from a theme or another plugin that echoes while WordPress loads: add `--skip-themes`, or `--skip-plugins=<that-plugin>`, to the command.
- `Using server: shim-mcp-server`: Shim MCP before 1.1.0 printed this when `--server` was left out. Update, or pass `--server=shim-mcp-server`.

Add `--debug` to the check command to see WP-CLI's own diagnostics. They go to STDERR and never break the protocol.

---

## Remote setup (HTTP)

The MCP server endpoint is:

```
https://YOUR-SITE.com/wp-json/mcp/shim-mcp
```

Authentication is HTTP Basic: your WordPress username and an application password.

1. Go to **Tools > Shim MCP** in your WordPress admin
2. Click **Generate API Key** to create an application password
3. Copy the configuration for your client. It is filled in with the new credential and can only be copied right after a key is generated, because WordPress never shows an application password twice.

### Claude Code

```bash
claude mcp add --transport http shim-mcp https://YOUR-SITE.com/wp-json/mcp/shim-mcp --header "Authorization: Basic BASE64_ENCODED_CREDENTIALS"
```

Or add it to `.mcp.json` in your project:

```json
{
  "mcpServers": {
    "shim-mcp": {
      "type": "http",
      "url": "https://YOUR-SITE.com/wp-json/mcp/shim-mcp",
      "headers": {
        "Authorization": "Basic BASE64_ENCODED_CREDENTIALS"
      }
    }
  }
}
```

### Claude Desktop

Add to your `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "shim-mcp": {
      "command": "npx",
      "args": [
        "-y",
        "mcp-remote",
        "https://YOUR-SITE.com/wp-json/mcp/shim-mcp",
        "--header",
        "Authorization: Basic BASE64_ENCODED_CREDENTIALS"
      ]
    }
  }
}
```

On Windows, if the client reports `spawn npx ENOENT`, start it through the shell: set `"command"` to `"cmd"` and put `"/c", "npx"` at the front of `args`.

### Cursor

Add to `.cursor/mcp.json`:

```json
{
  "mcpServers": {
    "shim-mcp": {
      "command": "npx",
      "args": [
        "-y",
        "mcp-remote",
        "https://YOUR-SITE.com/wp-json/mcp/shim-mcp",
        "--header",
        "Authorization: Basic BASE64_ENCODED_CREDENTIALS"
      ]
    }
  }
}
```

### Generating the Base64 Credentials

The `BASE64_ENCODED_CREDENTIALS` value is `base64(username:application_password)`. You can generate it with:

```bash
echo -n "your_username:xxxx xxxx xxxx xxxx xxxx xxxx" | base64
```

The admin dashboard generates this for you automatically.

### Troubleshooting HTTP

#### "Unauthorized" or 401 errors

- Verify your Application Password is correct (spaces in the password are normal)
- Ensure your WordPress user has administrator privileges
- Check that pretty permalinks are enabled (**Settings > Permalinks** -- choose anything except "Plain")

#### Connection refused or timeout

- Ensure your site is accessible from the internet (not just localhost)
- Check that HTTPS is configured if your AI client requires it
- Verify the URL includes the full path: `/wp-json/mcp/shim-mcp`

#### REST API disabled

The plugin requires the WordPress REST API. If it's disabled by a security plugin, whitelist the `/wp-json/mcp/` endpoint.

---

## Conflict warnings

Running two MCP servers at once lets both register abilities and endpoints, which confuses the connected AI client. Shim MCP warns when it detects one of:
- MCP Adapter
- MCP Expose Abilities
- Abilities API

Shim MCP bundles its own MCP server and uses the Abilities API that ships with WordPress 6.9 and later, so it does not need any of them alongside it. Deactivate whichever you are not using.
