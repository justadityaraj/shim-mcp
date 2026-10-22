<?php

declare( strict_types=1 );

namespace ShimMcp\Server\Transport\Infrastructure;

use ShimMcp\Server\Infrastructure\ErrorHandling\McpErrorFactory;

final class JsonRpcMessageValidator {

	public static function validate( $message ): ?array {
		if ( ! is_array( $message ) || self::is_list( $message ) ) {
			return McpErrorFactory::invalid_request( null, 'A message must be a JSON object.' );
		}

		$has_id = array_key_exists( 'id', $message );
		$id     = $has_id && ( is_int( $message['id'] ) || is_string( $message['id'] ) ) ? $message['id'] : null;

		if ( $has_id && null === $id ) {
			return McpErrorFactory::invalid_request( null, 'The id must be a string or an integer.' );
		}

		if ( '2.0' !== ( $message['jsonrpc'] ?? null ) ) {
			return McpErrorFactory::invalid_request( $id, 'The jsonrpc member must be "2.0".' );
		}

		$has_method = array_key_exists( 'method', $message );
		$has_result = array_key_exists( 'result', $message );
		$has_error  = array_key_exists( 'error', $message );

		if ( $has_method ? ( $has_result || $has_error ) : $has_result === $has_error ) {
			return McpErrorFactory::invalid_request( $id, 'A message must carry either a method, or exactly one of result and error.' );
		}

		if ( ! $has_method ) {
			return $has_id ? null : McpErrorFactory::invalid_request( null, 'A response must carry an id.' );
		}

		if ( ! is_string( $message['method'] ) || '' === $message['method'] ) {
			return McpErrorFactory::invalid_request( $id, 'The method must be a non-empty string.' );
		}

		if ( ! array_key_exists( 'params', $message ) ) {
			return null;
		}

		if ( ! is_array( $message['params'] ) ) {
			return McpErrorFactory::invalid_request( $id, 'The params member must be an object.' );
		}

		if ( $has_id && self::is_list( $message['params'] ) ) {
			return McpErrorFactory::invalid_params( $id, 'MCP takes params by name, as an object, not as an array.' );
		}

		return null;
	}

	public static function is_request( array $message ): bool {
		return array_key_exists( 'method', $message ) && array_key_exists( 'id', $message );
	}

	private static function is_list( array $value ): bool {
		return array() !== $value && array_values( $value ) === $value;
	}
}
