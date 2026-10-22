<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/harness.php';

$server = new HttpServer();
$http   = new HttpClient( $server );
$http->initialize();

$accepted = array(
	'notifications/initialized'      => array(
		'jsonrpc' => '2.0',
		'method'  => 'notifications/initialized',
	),
	'a client response'              => array(
		'jsonrpc' => '2.0',
		'id'      => 'server-request-1',
		'result'  => new stdClass(),
	),
	'a batch of only notifications' => array(
		array(
			'jsonrpc' => '2.0',
			'method'  => 'notifications/initialized',
		),
		array(
			'jsonrpc' => '2.0',
			'method'  => 'notifications/cancelled',
			'params'  => array( 'requestId' => 1 ),
		),
	),
);
foreach ( $accepted as $label => $message ) {
	$response = $http->post( $message );
	check( 202 === $response['status'] && '' === $response['body'], "{$label} returns 202 with an empty body", $response );
}

$ping = $http->request( 'ping' );
check( 200 === $ping['status'] && array() === ( $ping['json']['result'] ?? null ), 'an ordinary request still returns 200 with a JSON-RPC result', $ping );

$stale             = new HttpClient( $server );
$stale->session_id = 'missing-or-expired';
$unknown           = $stale->request( 'tools/list' );
check( 404 === $unknown['status'] && -32005 === ( $unknown['json']['error']['code'] ?? null ), 'an unknown or expired Mcp-Session-Id returns 404', $unknown );

$missing = ( new HttpClient( $server ) )->request( 'tools/list' );
check( 400 === $missing['status'] && -32600 === ( $missing['json']['error']['code'] ?? null ), 'a missing Mcp-Session-Id header still returns 400', $missing );

$malformed = $http->post( '{"jsonrpc":' );
same( 400, $malformed['status'], 'a malformed body still returns 400' );
