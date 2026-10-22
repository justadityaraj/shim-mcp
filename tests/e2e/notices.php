<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/harness.php';

$load = run_command( wp_command( array( 'eval', 'echo "loaded";' ) ) );
same( 'loaded', trim( $load['stdout'] ), 'WordPress loads the plugin with WP_DEBUG enabled' );
check( false === strpos( $load['stderr'], 'already registered' ), 'loading the plugin registers nothing twice', $load['stderr'] );

$plugin_diagnostics = preg_grep( '#(Notice|Warning|Deprecated|Fatal error).*plugins[/\\\\]shim-mcp#', explode( "\n", $load['stderr'] ) );
same( array(), array_values( $plugin_diagnostics ), 'loading the plugin raises no PHP diagnostics from its own files' );
check( false === strpos( $load['stderr'], 'shim-mcp</code> domain was triggered too early' ), 'translations for the plugin are not loaded before WordPress is ready', $load['stderr'] );
