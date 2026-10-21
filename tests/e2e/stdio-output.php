<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/harness.php';

$server = new StdioServer( array( '--user=admin' ) );

$initialize = $server->initialize();
same( 'Shim MCP', $initialize['result']['serverInfo']['name'] ?? null, 'without --server, the first stdout line is the initialize response' );

$tools = $server->request( 'tools/list' )['result']['tools'] ?? array();
same( 3, count( $tools ), 'without --server, tools/list answers on stdout' );

$echo = tool_payload( $server->execute( 'shim-mcp-tests/echo-to-stdout' ) );
same( true, $echo['data']['echoed'] ?? null, 'an ability that echoes still returns its result' );

$session = $server->close();
same( array(), $session['stdout'], 'only JSON-RPC responses reach stdout' );
check( false !== strpos( $session['stderr'], 'stray fixture output' ), "the echoing ability's output is reported on stderr", $session['stderr'] );

$debug = new StdioServer( array( '--user=admin', '--debug' ) );
$debug->initialize();
$debug_session = $debug->close();
check( false !== strpos( $debug_session['stderr'], 'Using server: shim-mcp-server' ), 'the selected-server diagnostic goes to stderr under --debug', $debug_session['stderr'] );
same( array(), $debug_session['stdout'], '--debug output stays off stdout' );
