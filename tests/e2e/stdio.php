<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/harness.php';

$post_id = (int) wp_cli( 'post', 'create', '--post_title=Smoke test post', '--post_status=publish', '--post_author=1', '--porcelain' );

$admin = new StdioServer( array( '--server=shim-mcp-server', '--user=admin' ) );

$initialize = $admin->initialize();
same( 'Shim MCP', $initialize['result']['serverInfo']['name'] ?? null, 'initialize returns the server info' );
same( '2025-06-18', $initialize['result']['protocolVersion'] ?? null, 'initialize negotiates the requested protocol version' );

$tools = array_column( $admin->request( 'tools/list' )['result']['tools'] ?? array(), 'name' );
sort( $tools );
same( array( 'shim-mcp-discover-abilities', 'shim-mcp-execute-ability', 'shim-mcp-get-ability-info' ), $tools, 'tools/list exposes the three meta-tools' );

$environment = tool_payload( $admin->execute( 'shim-mcp/system-environment' ) );
check( isset( $environment['data']['wp_version'] ), 'a no-input ability executes', $environment );

$post = tool_payload( $admin->execute( 'shim-mcp/posts-get', array( 'id' => $post_id ) ) );
same( 'Smoke test post', $post['data']['post']['title'] ?? null, 'an object-specific ability returns the requested post' );

$session = $admin->close();
same( 0, $session['exit'], 'the admin session exits cleanly at end of input' );
same( array(), $session['stdout'], 'the admin session leaves nothing else on stdout' );

$author = new StdioServer( array( '--server=shim-mcp-server', '--user=author' ) );
$author->initialize();

$denied = tool_payload( $author->execute( 'shim-mcp/posts-update', array( 'id' => $post_id, 'title' => 'Changed by author' ) ) );
same( false, $denied['data']['success'] ?? null, "an author is denied an update to another user's post" );
same( 'Smoke test post', wp_cli( 'post', 'get', (string) $post_id, '--field=post_title' ), 'the denied update leaves the post unchanged' );

$author->close();
