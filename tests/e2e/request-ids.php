<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/harness.php';

$environment_call = array(
	'name'      => 'shim-mcp-execute-ability',
	'arguments' => array(
		'ability_name' => 'shim-mcp/system-environment',
		'parameters'   => new stdClass(),
	),
);
$invalid_ids      = array(
	'an array id'   => array( 1 ),
	'an object id'  => array( 'nested' => 1 ),
	'a boolean id'  => true,
	'a fraction id' => 1.5,
);

$stdio = new StdioServer( array( '--server=shim-mcp-server', '--user=admin' ) );

$initialize = $stdio->request(
	'initialize',
	array(
		'protocolVersion' => '2025-06-18',
		'capabilities'    => new stdClass(),
		'clientInfo'      => array(
			'name'    => 'string-id-test',
			'version' => '1.0',
		),
	),
	'init-1'
);
check( isset( $initialize['result']['serverInfo'] ), 'stdio: initialize succeeds with a string id', $initialize );
check( isset( $stdio->request( 'tools/list', array(), 'list-1' )['result']['tools'] ), 'stdio: tools/list succeeds with a string id' );
check( isset( $stdio->request( 'tools/call', $environment_call, 'call-1' )['result']['content'] ), 'stdio: tools/call succeeds with a string id' );
same( -32601, $stdio->request( 'no/such/method', array(), 'missing-1' )['error']['code'] ?? null, 'stdio: an error response keeps the string id' );
same( -32602, $stdio->request( 'tools/call', array(), 'call-2' )['error']['code'] ?? null, 'stdio: a handler error keeps the string id' );
same( 7, $stdio->request( 'ping', array(), 7 )['id'], 'stdio: integer ids are unchanged' );

foreach ( $invalid_ids as $label => $id ) {
	$stdio->send(
		array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'method'  => 'ping',
		)
	);
	$response = $stdio->receive();
	check( -32600 === ( $response['error']['code'] ?? null ) && null === $response['id'], "stdio: {$label} gets an invalid-request error with a null id", $response );
}
$stdio->close();

$server = new HttpServer();
$http   = new HttpClient( $server );

$initialize = $http->initialize( 'init-1' );
same( 'init-1', $initialize['json']['id'] ?? null, 'http: initialize succeeds with a string id' );
check( isset( $initialize['json']['result']['serverInfo'] ), 'http: initialize returns a result for a string id', $initialize );

$list = $http->request( 'tools/list', array(), 'list-1' );
check( 'list-1' === ( $list['json']['id'] ?? null ) && isset( $list['json']['result']['tools'] ), 'http: tools/list succeeds with a string id', $list );

$call = $http->request( 'tools/call', $environment_call, 'call-1' );
check( 'call-1' === ( $call['json']['id'] ?? null ) && isset( $call['json']['result']['content'] ), 'http: tools/call succeeds with a string id', $call );

$missing = $http->request( 'no/such/method', array(), 'missing-1' );
check( 'missing-1' === ( $missing['json']['id'] ?? null ) && -32601 === ( $missing['json']['error']['code'] ?? null ), 'http: an error response keeps the string id', $missing );

same( 7, $http->request( 'ping', array(), 7 )['json']['id'] ?? null, 'http: integer ids are unchanged' );

foreach ( $invalid_ids as $label => $id ) {
	$response = $http->post(
		array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'method'  => 'ping',
		)
	);
	check( -32600 === ( $response['json']['error']['code'] ?? null ) && array_key_exists( 'id', $response['json'] ) && null === $response['json']['id'], "http: {$label} gets an invalid-request error with a null id", $response );
}
