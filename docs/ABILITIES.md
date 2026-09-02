# Abilities Reference

Shim MCP registers **56 abilities** across **13 domains**.

Every ability is exposed to MCP clients through the three meta-tools (`discover-abilities`, `get-ability-info`, `execute-ability`), so a client discovers the full set at runtime rather than needing one tool per operation.

Each ability declares a blanket capability in its permission callback. Abilities that act on a specific object additionally check the per-object capability (`edit_post`, `delete_post`, `read_post`, `edit_user`, `delete_user`, `edit_comment`) before reading or mutating it.

## Summary

| Domain | Abilities |
|--------|-----------|
| Posts | 6 |
| Pages | 6 |
| Taxonomy | 4 |
| Search | 1 |
| Revisions | 2 |
| Media | 5 |
| Users | 6 |
| Plugins | 4 |
| Menus | 7 |
| Widgets | 3 |
| Comments | 6 |
| Options | 3 |
| System | 3 |
| **Total** | **56** |

---

## Posts

| Ability | Capability | Read-only | Description |
|---------|-----------|-----------|-------------|
| `shim-mcp/posts-list` | `edit_posts` | yes | Returns a page of blog posts. You may narrow the results by publication status, author, a free-text keyword, a category or a tag, and you control the page size and which page you land on. |
| `shim-mcp/posts-get` | `edit_posts` | yes | Loads one post by its numeric ID and hands back the full body, the excerpt, the status and the taxonomy terms it belongs to. |
| `shim-mcp/posts-create` | `publish_posts` | no | Adds a new blog post. Only the title is mandatory; everything else, including the body, excerpt, status, URL slug, categories, tags and author, is optional and falls back to sensible defaults. |
| `shim-mcp/posts-update` | `edit_posts` | no | Changes one or more fields on a post that already exists. Fields you leave out keep their current values. Reassigning the post to a different author is only permitted for users who can edit other people's posts. |
| `shim-mcp/posts-delete` | `delete_posts` | no | Sends a post to the trash so it can be restored later, or erases it for good when you ask for a permanent removal. |
| `shim-mcp/posts-replace-text` | `edit_posts` | no | Swaps text inside a post body. By default the search string is treated literally; turn on the regex option to interpret it as a PCRE pattern instead. The reply tells you how many substitutions were made. |

---

## Pages

| Ability | Capability | Read-only | Description |
|---------|-----------|-----------|-------------|
| `shim-mcp/pages-list` | `edit_pages` | yes | Returns a paginated list of pages on the site. The list can be narrowed by keyword, by status or by parent page, and both the sort field and the sort direction can be chosen. |
| `shim-mcp/pages-get` | `edit_pages` | yes | Loads one page by its numeric identifier and reports its title, slug, status, parent, menu order and assigned template, along with the page body unless the body is explicitly left out. |
| `shim-mcp/pages-create` | `publish_pages` | no | Adds a new page to the site. Only a title is required; the body, parent page, position in the menu order and page template file can all be supplied as well. |
| `shim-mcp/pages-update` | `edit_pages` | no | Changes an existing page. Only the fields that are supplied get touched, so a page can be restructured by sending nothing but a new parent, a new menu order or a new template. |
| `shim-mcp/pages-delete` | `delete_pages` | no | Moves a page to the trash so it can be restored later, or erases it outright when permanent removal is asked for. A page that still has child pages is refused until the caller confirms the children may be left behind. |
| `shim-mcp/pages-replace-text` | `edit_pages` | no | Finds every occurrence of a string inside one page body and swaps it for another. Matching can be literal or by regular expression, can ignore letter case, and can be previewed without saving anything. |

---

## Taxonomy

| Ability | Capability | Read-only | Description |
|---------|-----------|-----------|-------------|
| `shim-mcp/taxonomy-list-categories` | `edit_posts` | yes | Returns the blog categories on this site, each with its numeric id, display name, slug, description, parent id and the number of posts filed under it. Results can be searched and paged through. |
| `shim-mcp/taxonomy-list-tags` | `edit_posts` | yes | Returns the post tags on this site with the same fields as the category listing: id, name, slug, description, parent id and post count. Tags are flat, so the parent id is normally zero. |
| `shim-mcp/taxonomy-create-term` | `manage_categories` | no | Adds a new term to either the category or the post_tag taxonomy. A name is required; slug, description and a parent category may also be supplied. |
| `shim-mcp/taxonomy-update-term` | `manage_categories` | no | Changes the name, slug, description or parent of an existing category or tag. Identify the term by its numeric id and send only the fields you want changed. |

---

## Search

| Ability | Capability | Read-only | Description |
|---------|-----------|-----------|-------------|
| `shim-mcp/content-search` | `read` | yes | Finds posts and pages whose title or body matches a keyword. By default it looks at posts and pages, but a caller can name any set of registered post types instead. Each hit comes back with its numeric id, post type, title, permalink and a trimmed excerpt, and the number of hits is capped by a limit the caller can raise or lower. |

---

## Revisions

| Ability | Capability | Read-only | Description |
|---------|-----------|-----------|-------------|
| `shim-mcp/revisions-list` | `edit_posts` | yes | Returns the saved revisions of a single post or page, most recent first. Give it the numeric post ID; each entry comes back with the revision ID, who saved it, and when. |
| `shim-mcp/revisions-restore` | `edit_posts` | no | Rolls a post back to one of its stored revisions. Give it the revision ID; the post it belongs to is overwritten with that revision content, and the version being replaced is itself saved as a new revision first. |

---

## Media

| Ability | Capability | Read-only | Description |
|---------|-----------|-----------|-------------|
| `shim-mcp/media-list` | `upload_files` | yes | Returns a page of attachments from the media library, newest first. You can narrow the results to a single MIME type such as image/png or to a whole family such as image. |
| `shim-mcp/media-get` | `upload_files` | yes | Loads a single attachment by its numeric ID and reports its file URL, MIME type, pixel dimensions where the file is an image, alternative text, caption and description. |
| `shim-mcp/media-upload` | `upload_files` | no | Decodes base64 file bytes, writes them into the WordPress uploads folder under the filename you give, creates the attachment record and builds its thumbnails and metadata. Files whose extension and contents are not allowed by the site are rejected. |
| `shim-mcp/media-update` | `upload_files` | no | Changes the stored title, caption, description or alternative text on an existing attachment. Fields you leave out keep their current values, and the file itself is untouched. |
| `shim-mcp/media-delete` | `delete_posts` | no | Deletes an attachment together with the file and any generated image sizes on disk. Set force to false to send it to the trash instead, where trashing attachments is enabled. |

---

## Users

| Ability | Capability | Read-only | Description |
|---------|-----------|-----------|-------------|
| `shim-mcp/users-list` | `list_users` | yes | Returns a page of user accounts on this site. You can narrow the result to a single role or to accounts matching a search term, and you control how many accounts come back per page. |
| `shim-mcp/users-get` | `list_users` | yes | Returns the full profile of one account, including its roles, contact fields and registration date. Give it the numeric user ID. |
| `shim-mcp/users-list-roles` | `list_users` | yes | Lists every role defined on this site with its slug, display name and capability count, and flags the ones the calling account is allowed to hand out. Takes no arguments. |
| `shim-mcp/users-create` | `create_users` | no | Creates a new account from a username, an email address and a password. Profile fields and a starting role are optional, and the site default role is used when none is given. A role you cannot grant yourself is rejected. |
| `shim-mcp/users-update` | `edit_users` | no | Changes an existing account. Give it the numeric user ID plus any of the email address, password, profile fields or role you want replaced. Roles you cannot grant yourself are rejected, and you cannot change your own role. |
| `shim-mcp/users-delete` | `delete_users` | no | Permanently deletes an account. Posts and links owned by that account are destroyed with it unless you name another account to inherit them. You cannot delete yourself. |

---

## Plugins

| Ability | Capability | Read-only | Description |
|---------|-----------|-----------|-------------|
| `shim-mcp/plugins-list` | `activate_plugins` | yes | Returns every plugin present in the plugins directory together with its name, version, author, whether it is currently active and whether a newer version is waiting to be installed. Optionally narrows the result to a text match on the plugin name or folder. |
| `shim-mcp/plugins-activate` | `activate_plugins` | no | Switches on an installed plugin identified by its file path relative to the plugins directory, for example akismet/akismet.php. On multisite the plugin can be turned on for the whole network. |
| `shim-mcp/plugins-deactivate` | `activate_plugins` | no | Turns off a running plugin identified by its file path relative to the plugins directory. The plugin files stay on disk and its settings are untouched. |
| `shim-mcp/plugins-delete` | `delete_plugins` | no | Removes an installed plugin and its folder from the server permanently. An active plugin is refused unless you ask for it to be deactivated first, and the caller must confirm the removal. |

---

## Menus

| Ability | Capability | Read-only | Description |
|---------|-----------|-----------|-------------|
| `shim-mcp/menus-list` | `edit_theme_options` | yes | Returns every navigation menu on the site together with its numeric id, display name, slug and how many items it currently holds. Takes no input. |
| `shim-mcp/menus-create` | `edit_theme_options` | no | Creates a new empty navigation menu under the name you supply. The name has to be unique, because WordPress rejects a second menu carrying an existing name. |
| `shim-mcp/menus-list-items` | `edit_theme_options` | yes | Reads back the items inside one navigation menu, each with its item id, title, resolved url, the kind of thing it points at, its parent item id and its position in the menu order. Identify the menu by numeric id or by slug. |
| `shim-mcp/menus-add-item` | `edit_theme_options` | no | Appends an entry to a navigation menu. Give it a custom title and url for a plain link, or point it at existing content by naming the object type (post_type or taxonomy) plus the object id and its post type or taxonomy name. |
| `shim-mcp/menus-update-item` | `edit_theme_options` | no | Changes an entry that already sits in a navigation menu. Anything you leave out keeps the value it has now, so you can retitle an item or move it under a different parent without restating the rest. |
| `shim-mcp/menus-delete-item` | `edit_theme_options` | no | Permanently removes one entry from a navigation menu. The post or term the entry pointed at is untouched, only the menu entry itself goes away. |
| `shim-mcp/menus-assign-location` | `edit_theme_options` | no | Hooks a navigation menu up to one of the display slots the active theme registers, such as the primary header slot. Pass a menu of zero to clear the slot instead. |

---

## Widgets

| Ability | Capability | Read-only | Description |
|---------|-----------|-----------|-------------|
| `shim-mcp/widgets-list-sidebars` | `edit_theme_options` | yes | Returns every widget area the current theme has registered, giving the identifier, display name and description for each one. Takes no input. |
| `shim-mcp/widgets-list-types` | `edit_theme_options` | yes | Returns the catalogue of widget types this installation offers, so a caller can see what kinds of widgets could be added to a widget area. Takes no input. |
| `shim-mcp/widgets-list-in-sidebar` | `edit_theme_options` | yes | Reports which widgets are currently sitting in one widget area, in the order they will render. Requires the sidebar identifier, which you can obtain from the sidebar listing ability. |

---

## Comments

| Ability | Capability | Read-only | Description |
|---------|-----------|-----------|-------------|
| `shim-mcp/comments-list` | `moderate_comments` | yes | Returns a page of comments. Narrow the results by approval state, by the post they belong to, by the author email address, or by a phrase to look for in the comment body. |
| `shim-mcp/comments-get` | `moderate_comments` | yes | Loads a single comment by its numeric identifier and returns its author details, body, moderation state and parent comment. |
| `shim-mcp/comments-create` | `moderate_comments` | no | Adds a comment to a post. Supply the post identifier and the body text, plus an author name and email when the comment is not attributed to a logged in account. Set a parent comment identifier to file it as a reply. |
| `shim-mcp/comments-set-status` | `moderate_comments` | no | Moves a comment between moderation states so it can be published, sent back to the queue, flagged as spam, or thrown in the trash. |
| `shim-mcp/comments-update` | `moderate_comments` | no | Replaces the body of an existing comment, and optionally the author name, email or website shown alongside it. |
| `shim-mcp/comments-delete` | `moderate_comments` | no | Removes a comment. By default it goes to the trash and can be restored; pass the permanent flag to erase it from the database instead. |

---

## Options

| Ability | Capability | Read-only | Description |
|---------|-----------|-----------|-------------|
| `shim-mcp/options-get` | `manage_options` | yes | Returns the stored value of one WordPress option looked up by its exact name, together with a flag saying whether the option exists at all. Options whose names look like credentials, such as API keys, secrets, tokens or passwords, are refused. |
| `shim-mcp/options-search` | `manage_options` | yes | Finds every option whose name contains a given fragment and returns one page of matching names with their autoload flags. Values are never included; read a specific non-credential option with the read ability. |
| `shim-mcp/options-update` | `manage_options` | no | Stores a new value for an option and creates it when it does not exist yet. Supply a key to replace a single entry inside an option that already holds an array rather than overwriting the whole option. Options that govern the site addresses, who may register and at what role, the active plugin set, or role capability definitions are refused outright. |

---

## System

| Ability | Capability | Read-only | Description |
|---------|-----------|-----------|-------------|
| `shim-mcp/system-environment` | `manage_options` | yes | Reports the WordPress and PHP versions, the active theme, the site and home addresses, whether multisite is enabled, the configured PHP memory limit, and how many plugins are currently active. It takes no arguments. |
| `shim-mcp/system-read-debug-log` | `manage_options` | yes | Returns the most recent lines of the WordPress debug log. You may choose how many lines to return and supply a plain substring that a line must contain to be included. It reports a clear failure when debug logging is switched off or the log file does not exist. |
| `shim-mcp/system-set-debug-constants` | `manage_options` | no | Rewrites the WP_DEBUG, WP_DEBUG_LOG and WP_DEBUG_DISPLAY constants inside wp-config.php. Switched off unless SHIM_MCP_ALLOW_CONFIG_WRITES is defined as true in wp-config.php. Send only the constants you want changed; anything left out keeps its current value. The write is also refused when file editing has been locked down, when the file is not writable, or when the rewritten contents fail a safety check. |

