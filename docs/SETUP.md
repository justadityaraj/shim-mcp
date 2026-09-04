# Setup Guide

## Requirements

- PHP 8.0 or higher
- WordPress 6.7 or higher
- HTTPS recommended for production (API credentials are transmitted with requests)

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

Then activate the plugin in **Plugins > Installed Plugins**.

## Configuration

1. After activation, go to **Tools > Shim MCP** in your WordPress admin
2. Click **Generate** to create an Application Password API key
3. Copy the config snippet for your AI client (Claude Code, Claude Desktop, or Cursor)
4. Paste the snippet into your AI client's configuration file

## Client Configuration

The MCP server endpoint is:

```
https://YOUR-SITE.com/wp-json/mcp/shim-mcp
```

Authentication uses HTTP Basic Auth with your WordPress username and the generated Application Password.

### Claude Code

Add to `.claude/claude.json` in your project (or `~/.claude/settings.json` for global):

```json
{
  "mcpServers": {
    "shim-mcp": {
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

## Troubleshooting

### "Unauthorized" or 401 errors

- Verify your Application Password is correct (spaces in the password are normal)
- Ensure your WordPress user has administrator privileges
- Check that pretty permalinks are enabled (**Settings > Permalinks** -- choose anything except "Plain")

### Connection refused or timeout

- Ensure your site is accessible from the internet (not just localhost)
- Check that HTTPS is configured if your AI client requires it
- Verify the URL includes the full path: `/wp-json/mcp/shim-mcp`

### Conflict warnings

Running two MCP servers at once lets both register abilities and endpoints, which confuses the connected AI client. Shim MCP warns when it detects one of:
- MCP Adapter
- MCP Expose Abilities
- Abilities API

Shim MCP bundles its own MCP server and uses the Abilities API that ships with WordPress 6.9 and later, so it does not need any of them alongside it. Deactivate whichever you are not using.

### REST API disabled

The plugin requires the WordPress REST API. If it's disabled by a security plugin, whitelist the `/wp-json/mcp/` endpoint.
