<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/harness.php';

$repository = dirname( __DIR__, 2 );
$pot        = tempnam( sys_get_temp_dir(), 'shim' ) . '.pot';

$make_pot = run_command( array( PHP_BINARY, wp_cli_phar(), 'i18n', 'make-pot', $repository, $pot, '--slug=shim-mcp', '--domain=shim-mcp', '--exclude=vendor,tests,bin' ) );
check( 0 === $make_pot['exit'], 'WordPress i18n tooling builds a POT file for the plugin', $make_pot['stdout'] . $make_pot['stderr'] );
$catalog = (string) file_get_contents( $pot );
unlink( $pot );

foreach ( array(
	'ability label'          => 'Browse Users',
	'ability description'    => 'Installs a plugin from the WordPress.org Plugin Directory by its directory slug',
	'input description'      => 'The plugin\'s WordPress.org directory slug',
	'ability result message' => 'The plugin was installed and is inactive. Activate it to switch it on.',
	'dashboard heading'      => 'Health Check',
	'dashboard script text'  => 'Copied!',
	'AJAX message'           => 'Insufficient permissions.',
	'WP-CLI error'           => 'No MCP servers are registered. Please register at least one server first.',
) as $kind => $text ) {
	check( false !== strpos( $catalog, 'msgid "' . addcslashes( $text, '"' ) ), "the POT file carries the {$kind}" );
}

$bare = array();
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $repository . '/includes/Abilities' ) ) as $file ) {
	if ( 'php' === $file->getExtension() && preg_match_all( "/'(?:label|description)'\s*=>\s*'([^']*\s[^']*)'/", (string) file_get_contents( $file->getPathname() ), $matches ) ) {
		$bare[ $file->getFilename() ] = $matches[1];
	}
}
same( array(), $bare, 'no ability label or description is a bare English string' );

preg_match_all( "/'[A-Z][a-z]+[^']*\s[^']*'/", (string) file_get_contents( $repository . '/assets/js/admin.js' ), $script_strings );
same( array(), $script_strings[0], 'the dashboard script takes its text from the translated strings' );

$view = (string) file_get_contents( $repository . '/includes/Admin/views/dashboard.php' );
$view = preg_replace( array( '/<\?php.*?\?>/s', '#<pre>.*?</pre>#s', '/<!--.*?-->/s' ), '', $view );
preg_match_all( '/>([^<>]*[A-Za-z]{2}[^<>]*)</', $view, $text_nodes );
same( array( 'Shim MCP', 'Claude Code', '~/.claude.json', 'Claude Desktop', 'Cursor' ), array_map( 'trim', $text_nodes[1] ), 'the dashboard prints no untranslated text besides product names' );
