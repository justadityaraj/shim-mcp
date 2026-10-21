<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/harness.php';

$stdio = new StdioServer( array( '--server=shim-mcp-server', '--user=admin' ) );
$stdio->initialize();

$http = new HttpClient( new HttpServer() );
$http->initialize();

$templates = array(
	'stdio' => $stdio->request( 'resources/templates/list' ),
	'http'  => $http->request( 'resources/templates/list' ),
);
$templates['http'] = ( $templates['http']['json'] ?? array() ) + array( 'body' => $templates['http']['body'] );

foreach ( $templates as $transport => $response ) {
	same( array(), $response['result']['resourceTemplates'] ?? null, "{$transport}: resources/templates/list returns an empty resourceTemplates list" );
}
check( false !== strpos( $templates['http']['body'], '"resourceTemplates":[]' ), 'http: resourceTemplates is serialised as a JSON array', $templates['http']['body'] );

check( is_array( $stdio->request( 'resources/list' )['result']['resources'] ?? null ), 'resources/list is unchanged' );
same( -32602, $stdio->request( 'resources/read' )['error']['code'] ?? null, 'resources/read without a uri is unchanged' );

$stdio->close();
