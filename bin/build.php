<?php
/**
 * Builds the distributable plugin zip.
 *
 * Everything listed in .distignore stays in the repository but is left out of
 * the package WordPress.org receives.
 *
 * Usage: php bin/build.php [output.zip]
 *
 * @package ShimMcp
 */

declare(strict_types=1);

$slug = 'shim-mcp';
$root = dirname( __DIR__ );
$out  = $argv[1] ?? $root . '/' . $slug . '.zip';

$ignored = array_values(
	array_filter(
		array_map( 'trim', file( $root . '/.distignore', FILE_IGNORE_NEW_LINES ) ),
		static fn( $line ) => '' !== $line && '#' !== $line[0]
	)
);

exec( 'git -C ' . escapeshellarg( $root ) . ' ls-files', $tracked, $status );
if ( 0 !== $status ) {
	fwrite( STDERR, "git ls-files failed\n" );
	exit( 1 );
}

$skip = static function ( string $path ) use ( $ignored ): bool {
	foreach ( $ignored as $entry ) {
		if ( $path === $entry || str_starts_with( $path, $entry . '/' ) ) {
			return true;
		}
	}
	return false;
};

if ( file_exists( $out ) ) {
	unlink( $out );
}

$zip = new ZipArchive();
if ( true !== $zip->open( $out, ZipArchive::CREATE ) ) {
	fwrite( STDERR, "cannot create {$out}\n" );
	exit( 1 );
}

$count = 0;
foreach ( $tracked as $path ) {
	if ( $skip( $path ) ) {
		continue;
	}
	$zip->addFile( $root . '/' . $path, $slug . '/' . $path );
	++$count;
}
$zip->close();

echo "built: {$out}\n";
echo "files: {$count}\n";
