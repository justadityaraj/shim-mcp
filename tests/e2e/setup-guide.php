<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/harness.php';

$repository = dirname( __DIR__, 2 );
$docs       = (string) file_get_contents( $repository . '/docs/SETUP.md' ) . (string) file_get_contents( $repository . '/README.md' );

$commands = array();

preg_match_all( '/```json\n(.*?)```/s', $docs, $json_blocks );
foreach ( $json_blocks[1] as $block ) {
	$server = json_decode( $block, true )['mcpServers']['shim-mcp'] ?? array();
	if ( isset( $server['command'] ) && in_array( 'serve', $server['args'] ?? array(), true ) ) {
		$commands[ 'JSON: ' . $server['command'] . ' ' . implode( ' ', $server['args'] ) ] = array_merge( array( $server['command'] ), $server['args'] );
	}
}

preg_match_all( "/^claude mcp add shim-mcp '?--'? (.+)$/m", $docs, $cli_lines );
foreach ( $cli_lines[1] as $line ) {
	$commands[ 'CLI: ' . trim( $line ) ] = preg_split( '/\s+/', trim( $line ) );
}

$placeholders = array(
	'/usr/local/bin/php'    => PHP_BINARY,
	'C:/php/php.exe'        => PHP_BINARY,
	'/usr/local/bin/wp'     => wp_cli_phar(),
	'C:/wp-cli/wp-cli.phar' => wp_cli_phar(),
);

$launchable = static function ( array $command ) use ( $placeholders ): array {
	if ( 'cmd' === $command[0] && in_array( $command[1] ?? '', array( '/c', '//c' ), true ) ) {
		$command = array_slice( $command, 2 );
	}
	if ( 'wp' === $command[0] ) {
		$command = array_merge( array( PHP_BINARY, wp_cli_phar() ), array_slice( $command, 1 ) );
	}
	return array_map(
		static fn( $part ) => preg_replace( '#^--path=(?:/path/to/wordpress|C:/path/to/wordpress)$#', '--path=' . site_path(), $placeholders[ $part ] ?? $part ),
		$command
	);
};

check( count( $commands ) >= 5, 'the setup guide and README publish at least five distinct stdio commands', array_keys( $commands ) );

foreach ( $commands as $label => $command ) {
	$server     = new StdioServer( array(), array(), false, $launchable( $command ) );
	$initialize = $server->initialize();
	$tools      = $server->request( 'tools/list' )['result']['tools'] ?? array();
	$session    = $server->close();
	check( 'Shim MCP' === ( $initialize['result']['serverInfo']['name'] ?? null ) && 3 === count( $tools ) && array() === $session['stdout'], "published example connects: {$label}", $session );
}
