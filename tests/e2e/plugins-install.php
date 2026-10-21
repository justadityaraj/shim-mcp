<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/harness.php';

$installed = static fn() => 0 === run_command( wp_command( array( 'plugin', 'is-installed', 'hello-dolly' ) ) )['exit'];
$result    = static fn( array $response ) => tool_payload( $response )['data'] ?? array();

if ( $installed() ) {
	wp_cli( 'plugin', 'delete', 'hello-dolly' );
}

$admin = new StdioServer( array( '--server=shim-mcp-server', '--user=admin' ) );
$admin->initialize();

$install = $result( $admin->execute( 'shim-mcp/plugins-install', array( 'slug' => 'hello-dolly' ) ) );
check( true === ( $install['success'] ?? null ) && 'hello-dolly/hello.php' === ( $install['plugin_file'] ?? null ), 'a directory plugin installs by slug', $install );
same( 'inactive', wp_cli( 'plugin', 'get', 'hello-dolly', '--field=status' ), 'the installed plugin is left inactive' );

$again = $result( $admin->execute( 'shim-mcp/plugins-install', array( 'slug' => 'hello-dolly' ) ) );
check( false === ( $again['success'] ?? null ) && 'hello-dolly/hello.php' === ( $again['plugin_file'] ?? null ), 'installing an installed plugin is refused and names it', $again );

$unknown = $result( $admin->execute( 'shim-mcp/plugins-install', array( 'slug' => 'shim-mcp-no-such-plugin-' . bin2hex( random_bytes( 4 ) ) ) ) );
check( false === ( $unknown['success'] ?? null ) && '' !== ( $unknown['message'] ?? '' ), 'an unknown slug fails with a message', $unknown );

wp_cli( 'plugin', 'delete', 'hello-dolly' );

foreach ( array(
	'https://downloads.wordpress.org/plugin/hello-dolly.zip',
	'downloads.wordpress.org/plugin/hello-dolly',
	'../hello-dolly',
	'hello-dolly/hello.php',
	'hello-dolly.zip',
	'C:\\plugins\\hello-dolly',
	'/tmp/hello-dolly',
	'Hello-Dolly',
) as $source ) {
	$refused = $result( $admin->execute( 'shim-mcp/plugins-install', array( 'slug' => $source ) ) );
	check( true !== ( $refused['success'] ?? null ), "a non-directory source is refused: {$source}", $refused );
}
check( ! $installed(), 'none of the refused sources installed anything' );

$admin->close();

$author = new StdioServer( array( '--server=shim-mcp-server', '--user=author' ) );
$author->initialize();
$denied = $author->execute( 'shim-mcp/plugins-install', array( 'slug' => 'hello-dolly' ) );
check( true !== ( $result( $denied )['success'] ?? null ), 'a user without install_plugins cannot install', tool_payload( $denied ) );
check( ! $installed(), 'the denied install left nothing on disk' );
$author->close();
