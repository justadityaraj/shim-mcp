<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/harness.php';

$corpus = array(
	'a number as the whole body'                => array( '5', -32600, null ),
	'a string as the whole body'                => array( '"ping"', -32600, null ),
	'a boolean as the whole body'               => array( 'true', -32600, null ),
	'null as the whole body'                    => array( 'null', -32600, null ),
	'a missing jsonrpc member'                  => array( '{"id":"a","method":"ping"}', -32600, 'a' ),
	'a wrong jsonrpc version'                   => array( '{"jsonrpc":"1.0","id":"a","method":"ping"}', -32600, 'a' ),
	'a non-string method'                       => array( '{"jsonrpc":"2.0","id":"a","method":5}', -32600, 'a' ),
	'an empty method'                           => array( '{"jsonrpc":"2.0","id":"a","method":""}', -32600, 'a' ),
	'scalar params'                             => array( '{"jsonrpc":"2.0","id":"a","method":"tools/list","params":"all"}', -32600, 'a' ),
	'null params'                               => array( '{"jsonrpc":"2.0","id":"a","method":"tools/list","params":null}', -32600, 'a' ),
	'positional params'                         => array( '{"jsonrpc":"2.0","id":"a","method":"tools/call","params":["shim-mcp-discover-abilities"]}', -32602, 'a' ),
	'a null id'                                 => array( '{"jsonrpc":"2.0","id":null,"method":"ping"}', -32600, null ),
	'an array id'                               => array( '{"jsonrpc":"2.0","id":[1],"method":"ping"}', -32600, null ),
	'neither a method nor a result'             => array( '{"jsonrpc":"2.0","id":"a"}', -32600, 'a' ),
	'both a method and a result'                => array( '{"jsonrpc":"2.0","id":"a","method":"ping","result":{}}', -32600, 'a' ),
	'both a result and an error'                => array( '{"jsonrpc":"2.0","id":"a","result":{},"error":{"code":1,"message":"x"}}', -32600, 'a' ),
	'a response without an id'                  => array( '{"jsonrpc":"2.0","result":{}}', -32600, null ),
	'a notification with non-string method'     => array( '{"jsonrpc":"2.0","method":5}', -32600, null ),
);

$stdio = new StdioServer( array( '--server=shim-mcp-server', '--user=admin' ) );
$stdio->initialize();

$server = new HttpServer();
$http   = new HttpClient( $server );
$http->initialize();

foreach ( $corpus as $label => list( $raw, $code, $id ) ) {
	$stdio->send_raw( $raw );
	$over_stdio = $stdio->receive();
	$over_http  = $http->post( $raw );

	check(
		$code === ( $over_stdio['error']['code'] ?? null ) && array_key_exists( 'id', $over_stdio ) && $id === $over_stdio['id'],
		"stdio: {$label} gets {$code} with id " . var_export( $id, true ),
		$over_stdio
	);
	check(
		$code === ( $over_http['json']['error']['code'] ?? null ) && array_key_exists( 'id', $over_http['json'] ?? array() ) && $id === $over_http['json']['id'],
		"http: {$label} gets {$code} with id " . var_export( $id, true ),
		$over_http
	);
	same( -32600 === $code ? 400 : 200, $over_http['status'], "http: {$label} gets the matching status" );
}

$stdio->send_raw( '{"jsonrpc":"2.0","method":"notifications/initialized"}' );
same( 'after', $stdio->request( 'ping', array(), 'after' )['id'], 'stdio: a true notification still gets no response' );
same( 202, $http->post( '{"jsonrpc":"2.0","method":"notifications/initialized"}' )['status'], 'http: a true notification still gets 202' );

same( array(), $stdio->request( 'ping', array(), 'valid' )['result'] ?? null, 'stdio: a valid request still succeeds' );
same( array(), $http->request( 'ping', array(), 'valid' )['json']['result'] ?? null, 'http: a valid request still succeeds' );

$stdio->close();
