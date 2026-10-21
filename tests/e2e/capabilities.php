<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/harness.php';

$stdio = new StdioServer( array( '--server=shim-mcp-server', '--user=admin' ) );
$http  = new HttpClient( new HttpServer() );

$advertised = array(
	'stdio' => array_keys( $stdio->initialize()['result']['capabilities'] ?? array() ),
	'http'  => array_keys( $http->initialize()['json']['result']['capabilities'] ?? array() ),
);

foreach ( $advertised as $transport => $capabilities ) {
	sort( $capabilities );
	same( array( 'prompts', 'resources', 'tools' ), $capabilities, "{$transport}: initialize advertises exactly the implemented capabilities" );
}

same( 3, count( $stdio->request( 'tools/list' )['result']['tools'] ?? array() ), 'tools/list still works' );
check( is_array( $stdio->request( 'resources/list' )['result']['resources'] ?? null ), 'resources/list still works' );
check( is_array( $stdio->request( 'prompts/list' )['result']['prompts'] ?? null ), 'prompts/list still works' );

foreach ( array( 'logging/setLevel' => array( 'level' => 'info' ), 'completion/complete' => array() ) as $method => $params ) {
	same( -32601, $stdio->request( $method, $params )['error']['code'] ?? null, "{$method} is no longer answered with an empty success" );
}

$stdio->close();
