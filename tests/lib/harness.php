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

		$this->stderr_file = (string) tempnam( sys_get_temp_dir(), 'shim' );
		$this->process     = proc_open(
			$command,
			array(
				0 => $socket_stdin ? array( 'socket' ) : array( 'pipe', 'r' ),
				1 => array( 'pipe', 'w' ),
				2 => array( 'file', $this->stderr_file, 'w' ),
			),
			$this->pipes
		);
		if ( ! is_resource( $this->process ) ) {
			fail( 'start wp shim-mcp serve' );
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
		if ( 'Windows' !== PHP_OS_FAMILY ) {
			$read   = array( $this->pipes[1] );
			$write  = null;
			$except = null;
			if ( 0 === stream_select( $read, $write, $except, 30 ) ) {
				fail( 'the server responds within 30 seconds', $this->stderr() );
			}
		}
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
		$deadline = microtime( true ) + 30;
		$status   = proc_get_status( $this->process );
		while ( $status['running'] && microtime( true ) < $deadline ) {
			usleep( 50000 );
			$status = proc_get_status( $this->process );
		}
		if ( $status['running'] ) {
			proc_terminate( $this->process );
			fail( 'the server exits within 30 seconds of end of input', $this->stderr() );
		}
		$rest = stream_get_contents( $this->pipes[1] );
		fclose( $this->pipes[1] );
		proc_close( $this->process );
		return array(
			'exit'   => $status['exitcode'],
			'stdout' => array_values( array_filter( explode( "\n", (string) $rest ), static fn( $line ) => '' !== trim( $line ) ) ),
			'stderr' => $this->stderr(),
		);
	}

	public function stderr(): string {
		return (string) file_get_contents( $this->stderr_file );
	}
}

function app_password_path(): string {
	return test_root() . '/admin-application-password';
}

final class HttpServer {

	private $process;

	private string $log_file;

	public string $endpoint;

	public function __construct( int $port = 8899 ) {
		$this->log_file = (string) tempnam( sys_get_temp_dir(), 'shim' );
		$this->endpoint = "http://127.0.0.1:{$port}/index.php?rest_route=/mcp/shim-mcp";
		$this->process  = proc_open(
			array( PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', site_path() ),
			array(
				0 => array( 'pipe', 'r' ),
				1 => array( 'file', $this->log_file, 'w' ),
				2 => array( 'file', $this->log_file, 'a' ),
			),
			$pipes
		);
		for ( $attempt = 0; $attempt < 100; $attempt++ ) {
			$socket = @fsockopen( '127.0.0.1', $port, $errno, $errstr, 0.1 );
			if ( $socket ) {
				fclose( $socket );
				return;
			}
			usleep( 100000 );
		}
		fail( "start the PHP built-in server on port {$port}", (string) file_get_contents( $this->log_file ) );
	}

	public function __destruct() {
		proc_terminate( $this->process );
		proc_close( $this->process );
	}
}

final class HttpClient {

	private HttpServer $server;

	private string $endpoint;

	private string $authorization;

	public ?string $session_id = null;

	public function __construct( HttpServer $server, string $user = 'admin', ?string $password = null ) {
		$this->server        = $server;
		$this->endpoint      = $server->endpoint;
		$this->authorization = 'Basic ' . base64_encode( $user . ':' . ( $password ?? trim( (string) file_get_contents( app_password_path() ) ) ) );
	}

	public function post( $body, array $headers = array() ): array {
		$headers = array_merge(
			array(
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json, text/event-stream',
				'Authorization' => $this->authorization,
			),
			null === $this->session_id ? array() : array( 'Mcp-Session-Id' => $this->session_id ),
			$headers
		);
		$lines = array();
		foreach ( $headers as $name => $value ) {
			$lines[] = "{$name}: {$value}";
		}
		$context  = stream_context_create(
			array(
				'http' => array(
					'method'        => 'POST',
					'header'        => implode( "\r\n", $lines ),
					'content'       => is_string( $body ) ? $body : json_encode( $body, JSON_UNESCAPED_SLASHES ),
					'ignore_errors' => true,
					'timeout'       => 30,
				),
			)
		);
		$raw      = file_get_contents( $this->endpoint, false, $context );
		$response = array(
			'status'  => 0,
			'headers' => array(),
			'body'    => false === $raw ? '' : $raw,
		);
		foreach ( $http_response_header ?? array() as $line ) {
			if ( preg_match( '#^HTTP/\S+ (\d{3})#', $line, $match ) ) {
				$response['status'] = (int) $match[1];
			} elseif ( false !== strpos( $line, ':' ) ) {
				list( $name, $value )                              = explode( ':', $line, 2 );
				$response['headers'][ strtolower( trim( $name ) ) ] = trim( $value );
			}
		}
		$response['json'] = json_decode( $response['body'], true );
		return $response;
	}

	public function request( string $method, array $params = array(), $id = 1 ): array {
		return $this->post(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'method'  => $method,
				'params'  => (object) $params,
			)
		);
	}

	public function initialize( $id = 1 ): array {
		$response         = $this->request(
			'initialize',
			array(
				'protocolVersion' => '2025-06-18',
				'capabilities'    => new stdClass(),
				'clientInfo'      => array(
					'name'    => 'shim-mcp-tests',
					'version' => '1.0.0',
				),
			),
			$id
		);
		$this->session_id = $response['headers']['mcp-session-id'] ?? null;
		if ( null === $this->session_id ) {
			fail( 'initialize over HTTP returns an Mcp-Session-Id header', $response );
		}
		return $response;
	}
}
