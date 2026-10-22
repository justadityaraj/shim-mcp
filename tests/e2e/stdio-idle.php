<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/harness.php';

if ( 'Windows' === PHP_OS_FAMILY ) {
	pass( 'skipped: PHP opens a socket stdin as a socket stream only outside Windows' );
	exit( 0 );
}

$server = new StdioServer( array( '--server=shim-mcp-server', '--user=admin' ), array( 'default_socket_timeout=1' ), true );
$server->initialize();

sleep( 3 );
check( $server->running(), 'the bridge survives idling past default_socket_timeout on a socket stdin', $server->stderr() );

$ping = $server->request( 'ping' );
same( array(), $ping['result'] ?? null, 'a ping sent after the idle period is answered' );

$started = microtime( true );
$session = $server->close();
$elapsed = microtime( true ) - $started;

same( 0, $session['exit'], 'closing the socket ends the bridge with exit status 0' );
check( $elapsed < 10, 'end of input stops the bridge promptly', sprintf( '%.1f seconds', $elapsed ) );
check( false === strpos( $session['stderr'], 'Reading from stdin failed' ), 'real end of input is not reported as a read failure', $session['stderr'] );
