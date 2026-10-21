<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/harness.php';

$markup = '<!-- wp:paragraph {"placeholder":"\u003cb\u003e"} --><p data-shim="1">back\slash</p><!-- /wp:paragraph -->'
	. '<!-- wp:html --><iframe src="https://example.com/embed" title="embed"></iframe><script>var shim = 1;</script><!-- /wp:html -->';

$stored  = static fn( $id ) => wp_cli( 'post', 'get', (string) $id, '--field=post_content' );
$created = static fn( array $payload ) => (string) ( $payload['data']['id'] ?? $payload['data']['post']['id'] ?? '' );

$admin = new StdioServer( array( '--server=shim-mcp-server', '--user=admin' ) );
$admin->initialize();

$post_id = $created( tool_payload( $admin->execute( 'shim-mcp/posts-create', array( 'title' => 'Markup by an admin', 'content' => $markup ) ) ) );
same( $markup, $stored( $post_id ), 'a user with unfiltered_html creates a post without losing markup, block JSON or backslashes' );

tool_payload( $admin->execute( 'shim-mcp/posts-update', array( 'id' => (int) $post_id, 'content' => $markup . '<p>updated</p>' ) ) );
same( $markup . '<p>updated</p>', $stored( $post_id ), 'a user with unfiltered_html updates a post without losing markup' );

tool_payload( $admin->execute( 'shim-mcp/posts-replace-text', array( 'id' => (int) $post_id, 'search' => 'updated', 'replace' => 'replaced' ) ) );
same( $markup . '<p>replaced</p>', $stored( $post_id ), 'search-and-replace changes only the requested text and keeps unrelated markup' );

$page_id = $created( tool_payload( $admin->execute( 'shim-mcp/pages-create', array( 'title' => 'Page markup by an admin', 'content' => $markup ) ) ) );
same( $markup, $stored( $page_id ), 'pages keep block JSON and backslashes too' );

$attachment_id = wp_cli( '--user=admin', 'post', 'create', '--post_type=attachment', '--post_mime_type=image/png', '--post_status=inherit', '--post_title=attachment', '--porcelain' );
tool_payload( $admin->execute( 'shim-mcp/media-update', array( 'id' => (int) $attachment_id, 'title' => 'back\slash' ) ) );
same( 'back\slash', wp_cli( 'post', 'get', $attachment_id, '--field=post_title' ), 'media updates keep backslashes' );

$admin->close();

$author = new StdioServer( array( '--server=shim-mcp-server', '--user=author' ) );
$author->initialize();

$unsafe = static fn( string $content ) => false !== stripos( $content, '<script' ) || false !== stripos( $content, '<iframe' );

$author_post = $created( tool_payload( $author->execute( 'shim-mcp/posts-create', array( 'title' => 'Markup by an author', 'content' => $markup ) ) ) );
check( '' !== $author_post && ! $unsafe( $stored( $author_post ) ), 'a user without unfiltered_html still has unsafe markup filtered on create', $stored( $author_post ) );

tool_payload( $author->execute( 'shim-mcp/posts-update', array( 'id' => (int) $author_post, 'content' => $markup ) ) );
check( ! $unsafe( $stored( $author_post ) ), 'a user without unfiltered_html still has unsafe markup filtered on update', $stored( $author_post ) );

$seeded = wp_cli( '--user=admin', 'post', 'create', '--post_author=' . wp_cli( 'user', 'get', 'author', '--field=ID' ), '--post_title=Seeded', '--post_content=' . $markup . '<p>needle</p>', '--porcelain' );
tool_payload( $author->execute( 'shim-mcp/posts-replace-text', array( 'id' => (int) $seeded, 'search' => 'needle', 'replace' => 'thread' ) ) );
$after = $stored( $seeded );
check( ! $unsafe( $after ) && false !== strpos( $after, 'thread' ), 'search-and-replace by a user without unfiltered_html saves filtered content', $after );

$author->close();
