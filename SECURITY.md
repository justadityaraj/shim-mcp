# Security Policy

## Reporting a Vulnerability

Please report security issues privately. Do **not** open a public issue.

- Open a [GitHub Security Advisory](https://github.com/justadityaraj/shim-mcp/security/advisories/new)

We aim to acknowledge reports within 72 hours and to ship a fix or a mitigation plan within 14 days.

## Supported Versions

Only the latest released version receives security updates.

## Security Model

Shim MCP exposes WordPress operations to MCP clients over an authenticated REST endpoint. Understanding the trust boundaries matters before you install it.

- **Authentication** is handled by WordPress Application Passwords. Anyone holding a valid application password acts as that user.
- **Authorization** is enforced per ability. Every ability declares a `permission_callback` checking a WordPress capability, and abilities that act on a specific object additionally check the per-object capability (`edit_post`, `delete_post`, `edit_user`) before mutating it.
- **The MCP endpoint grants no privileges of its own.** A subscriber's application password cannot perform an editor's actions. The plugin never elevates, never runs as another user, and never bypasses `current_user_can()`.
- **`options/update` refuses a blocklist** of settings that could be used to escalate or lock out (`active_plugins`, `siteurl`, `home`, `default_role`, `users_can_register`, `wp_user_roles`, and others). This is defense in depth against a misbehaving AI client, not a privilege boundary — the caller already holds `manage_options`.
- **Plugin install/activate/delete abilities** are gated on the corresponding core capabilities, which WordPress itself removes when `DISALLOW_FILE_MODS` is set.
- **Writing to `wp-config.php` is opt-in.** The ability that changes the debug constants refuses unless `SHIM_MCP_ALLOW_CONFIG_WRITES` is defined as true, and refuses again under `DISALLOW_FILE_EDIT` / `DISALLOW_FILE_MODS`, on a non-writable file, or if the rewritten contents fail a sanity check. No backup copy is written inside the web root, because a readable copy of `wp-config.php` leaks database credentials.

## Operational Guidance

- Serve the site over HTTPS. Application passwords are sent as HTTP Basic credentials.
- Issue the application password to a user whose role carries the least privilege the client actually needs. Do not hand an AI client an administrator password by default.
- Revoke the application password from **Tools > Shim MCP** when a client is no longer in use.
- Treat any MCP client with write abilities as having the same reach as a logged-in user of that role. Review what it does.
