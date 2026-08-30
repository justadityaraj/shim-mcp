<?php
declare(strict_types=1);

namespace ShimMcp;

final class Autoloader {
	private static bool $registered = false;

	public static function register(): bool {
		if ( self::$registered ) {
			return true;
		}

		spl_autoload_register( [ self::class, 'autoload' ] );
		self::$registered = true;
		return true;
	}

	private static function autoload( string $class_name ): void {
		$prefix = 'ShimMcp\\';
		if ( 0 !== strncmp( $class_name, $prefix, strlen( $prefix ) ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = SHIM_MCP_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
