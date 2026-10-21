<?php

declare(strict_types=1);

require __DIR__ . '/lib/harness.php';

const SQLITE_PLUGIN_ZIP = 'https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip';
const WP_CLI_PHAR_URL   = 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar';

function remove_tree( string $path ): void {
	if ( ! file_exists( $path ) && ! is_link( $path ) ) {
		return;
	}
	if ( is_file( $path ) || is_link( $path ) ) {
		unlink( $path );
		return;
	}
	foreach ( new FilesystemIterator( $path ) as $entry ) {
		remove_tree( $entry->getPathname() );
	}
	rmdir( $path );
}

function download( string $url, string $destination ): void {
	if ( is_file( $destination ) ) {
		return;
	}
	if ( ! copy( $url, $destination ) ) {
		fail( "download {$url}" );
	}
}

function extract_zip( string $archive, string $destination ): void {
	$zip = new ZipArchive();
	if ( true !== $zip->open( $archive ) || ! $zip->extractTo( $destination ) ) {
		fail( "extract {$archive}" );
	}
	$zip->close();
}

$root    = test_root();
$site    = site_path();
$content = $site . '/wp-content';

if ( ! is_dir( $root ) ) {
	mkdir( $root, 0777, true );
}

$build = run_command( array( PHP_BINARY, dirname( __DIR__ ) . '/bin/build.php', plugin_zip_path() ) );
check( 0 === $build['exit'], 'release zip builds', $build['stdout'] . $build['stderr'] );

download( WP_CLI_PHAR_URL, wp_cli_phar() );
download( SQLITE_PLUGIN_ZIP, $root . '/sqlite-database-integration.zip' );

remove_tree( $site );
mkdir( $site, 0777, true );
remove_tree( $root . '/debug.log' );

$version = getenv( 'WP_VERSION' );
wp_cli( 'core', 'download', '--skip-content', '--version=' . ( false === $version || '' === $version ? 'latest' : $version ) );

if ( ! is_dir( $content . '/plugins' ) ) {
	mkdir( $content . '/plugins', 0777, true );
}
extract_zip( $root . '/sqlite-database-integration.zip', $content . '/plugins' );
copy( $content . '/plugins/sqlite-database-integration/db.copy', $content . '/db.php' );

$config = run_command(
	wp_command( array( 'config', 'create', '--dbname=wordpress', '--dbuser=wordpress', '--skip-check', '--extra-php' ) ),
	implode(
		"\n",
		array(
			"define( 'WP_ENVIRONMENT_TYPE', 'local' );",
			"define( 'WP_DEBUG', true );",
			"define( 'WP_DEBUG_DISPLAY', false );",
			"define( 'WP_DEBUG_LOG', " . var_export( $root . '/debug.log', true ) . ' );',
		)
	)
);
check( 0 === $config['exit'], 'wp-config.php is written', $config['stdout'] . $config['stderr'] );

wp_cli( 'core', 'install', '--url=http://127.0.0.1:8899', '--title=Shim MCP Tests', '--admin_user=admin', '--admin_password=password', '--admin_email=admin@example.com', '--skip-email' );
pass( 'WordPress ' . wp_cli( 'core', 'version' ) . ' is installed on PHP ' . PHP_VERSION );

wp_cli( 'plugin', 'install', plugin_zip_path(), '--activate' );
check( 'active' === wp_cli( 'plugin', 'get', 'shim-mcp', '--field=status' ), 'the packaged plugin activates' );

wp_cli( 'user', 'create', 'author', 'author@example.com', '--role=author', '--user_pass=password' );
pass( 'fixture users exist' );
