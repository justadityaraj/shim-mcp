<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/harness.php';

$zip = new ZipArchive();
check( true === $zip->open( plugin_zip_path() ), 'the release zip opens' );

$entries = array();
for ( $i = 0; $i < $zip->numFiles; $i++ ) {
	$entries[] = $zip->getNameIndex( $i );
}
$zip->close();

$outside = array_filter( $entries, static fn( $entry ) => 0 !== strpos( $entry, 'shim-mcp/' ) );
same( array(), array_values( $outside ), 'every entry sits inside a shim-mcp/ folder' );

foreach ( array( 'shim-mcp/shim-mcp.php', 'shim-mcp/readme.txt', 'shim-mcp/uninstall.php', 'shim-mcp/LICENSE' ) as $required ) {
	check( in_array( $required, $entries, true ), "the zip contains {$required}" );
}

$development = array_filter(
	$entries,
	static fn( $entry ) => 1 === preg_match( '#(^|/)\.|^shim-mcp/(tests|bin|vendor|node_modules)/|^shim-mcp/(composer\.(json|lock)|package\.json|phpcs\.xml\.dist|phpstan\.neon\.dist|CREDITS\.md)$#', $entry )
);
same( array(), array_values( $development ), 'the zip ships no hidden or development files' );
