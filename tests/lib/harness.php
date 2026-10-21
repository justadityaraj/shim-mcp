<?php

declare(strict_types=1);

function test_root(): string {
	$root = getenv( 'SHIM_TEST_ROOT' );
	if ( false === $root || '' === $root ) {
		$root = sys_get_temp_dir() . '/shim-mcp-test';
	}
	return rtrim( str_replace( '\\', '/', $root ), '/' );
}

function site_path(): string {
	return test_root() . '/site';
}

function plugin_zip_path(): string {
	return test_root() . '/shim-mcp.zip';
}

function wp_cli_phar(): string {
	$phar = getenv( 'WP_CLI_PHAR' );
	return false === $phar || '' === $phar ? test_root() . '/wp-cli.phar' : $phar;
}

function wp_command( array $args ): array {
	return array_merge( array( PHP_BINARY, wp_cli_phar(), '--path=' . site_path() ), $args );
}

function run_command( array $command, string $stdin = '' ): array {
	$out  = tempnam( sys_get_temp_dir(), 'shim' );
	$err  = tempnam( sys_get_temp_dir(), 'shim' );
	$proc = proc_open(
		$command,
		array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'file', $out, 'w' ),
			2 => array( 'file', $err, 'w' ),
		),
		$pipes
	);
	if ( ! is_resource( $proc ) ) {
		fail( 'start ' . implode( ' ', $command ) );
	}
	fwrite( $pipes[0], $stdin );
	fclose( $pipes[0] );
	$exit   = proc_close( $proc );
	$result = array(
		'exit'   => $exit,
		'stdout' => (string) file_get_contents( $out ),
		'stderr' => (string) file_get_contents( $err ),
	);
	unlink( $out );
	unlink( $err );
	return $result;
}

function wp_cli( string ...$args ): string {
	$result = run_command( wp_command( $args ) );
	if ( 0 !== $result['exit'] ) {
		fail( 'wp ' . implode( ' ', $args ), $result['stdout'] . $result['stderr'] );
	}
	return trim( $result['stdout'] );
}

function pass( string $label ): void {
	echo "ok - {$label}\n";
}

function fail( string $label, $detail = null ): void {
	echo "not ok - {$label}\n";
	if ( null !== $detail ) {
		echo is_string( $detail ) ? $detail : var_export( $detail, true ), "\n";
	}
	exit( 1 );
}

function check( bool $condition, string $label, $detail = null ): void {
	if ( $condition ) {
		pass( $label );
		return;
	}
	fail( $label, $detail );
}

function same( $expected, $actual, string $label ): void {
	check( $expected === $actual, $label, array( 'expected' => $expected, 'actual' => $actual ) );
}

function json_rpc_line( string $line ): ?array {
	$message = json_decode( $line, true );
	if ( ! is_array( $message ) || ( $message['jsonrpc'] ?? null ) !== '2.0' ) {
		return null;
	}
	return $message;
}

function tool_payload( array $response ): array {
	$text = $response['result']['content'][0]['text'] ?? null;
	if ( ! is_string( $text ) ) {
		fail( 'tool result has text content', $response );
	}
	$payload = json_decode( $text, true );
	return is_array( $payload ) ? $payload : array( 'text' => $text );
}

final class StdioServer {

	private $process;

	private array $pipes = array();

	private string $stderr_file;

	private int $next_id = 1;

	public function __construct( array $serve_args, array $php_settings = array(), bool $socket_stdin = false ) {
		$command = wp_command( array_merge( array( 'shim-mcp', 'serve' ), $serve_args ) );
		foreach ( $php_settings as $setting ) {
			array_splice( $command, 1, 0, array( '-d', $setting ) );
		}

		$stdin = array( 'pipe', 'r' );
		if ( $socket_stdin ) {
			$pair  = stream_socket_pair( STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP );
			$stdin = $pair[1];
		}

		$this->stderr_file = (string) tempnam( sys_get_temp_dir(), 'shim' );
		$this->process     = proc_open(
			$command,
			array(
				0 => $stdin,
				1 => array( 'pipe', 'w' ),
				2 => array( 'file', $this->stderr_file, 'w' ),
			),
			$this->pipes
		);
		if ( ! is_resource( $this->process ) ) {
			fail( 'start wp shim-mcp serve' );
		}

		if ( $socket_stdin ) {
			fclose( $pair[1] );
			$this->pipes[0] = $pair[0];
		}
	}

	public function running(): bool {
		return proc_get_status( $this->process )['running'];
	}

	public function send( array $message ): void {
		fwrite( $this->pipes[0], json_encode( $message, JSON_UNESCAPED_SLASHES ) . "\n" );
		fflush( $this->pipes[0] );
	}

	public function receive(): array {
		$line = fgets( $this->pipes[1] );
		if ( false === $line ) {
			fail( 'server wrote a response before closing stdout', $this->stderr() );
		}
		$message = json_rpc_line( $line );
		if ( null === $message ) {
			fail( 'stdout carries only JSON-RPC messages', $line );
		}
		return $message;
	}

	public function request( string $method, array $params = array(), $id = null ): array {
		$id = $id ?? $this->next_id++;
		$this->send(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'method'  => $method,
				'params'  => (object) $params,
			)
		);
		$response = $this->receive();
		if ( ! array_key_exists( 'id', $response ) || $response['id'] !== $id ) {
			fail( "{$method} response carries request id", $response );
		}
		return $response;
	}

	public function notify( string $method, array $params = array() ): void {
		$this->send(
			array(
				'jsonrpc' => '2.0',
				'method'  => $method,
				'params'  => (object) $params,
			)
		);
	}

	public function initialize(): array {
		$response = $this->request(
			'initialize',
			array(
				'protocolVersion' => '2025-06-18',
				'capabilities'    => new stdClass(),
				'clientInfo'      => array(
					'name'    => 'shim-mcp-tests',
					'version' => '1.0.0',
				),
			)
		);
		$this->notify( 'notifications/initialized' );
		return $response;
	}

	public function execute( string $ability, array $parameters = array() ): array {
		return $this->request(
			'tools/call',
			array(
				'name'      => 'shim-mcp-execute-ability',
				'arguments' => array(
					'ability_name' => $ability,
					'parameters'   => (object) $parameters,
				),
			)
		);
	}

	public function close(): array {
		fclose( $this->pipes[0] );
		$rest = stream_get_contents( $this->pipes[1] );
		fclose( $this->pipes[1] );
		$exit = proc_close( $this->process );
		return array(
			'exit'   => $exit,
			'stdout' => array_values( array_filter( explode( "\n", (string) $rest ), static fn( $line ) => '' !== trim( $line ) ) ),
			'stderr' => $this->stderr(),
		);
	}

	public function stderr(): string {
		return (string) file_get_contents( $this->stderr_file );
	}
}
