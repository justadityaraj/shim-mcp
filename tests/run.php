<?php

declare(strict_types=1);

require __DIR__ . '/lib/harness.php';

$suites = array();
foreach ( glob( __DIR__ . '/wp/*.php' ) as $file ) {
	$suites[ 'wp/' . basename( $file ) ] = wp_command( array( 'eval-file', $file ) );
}
foreach ( glob( __DIR__ . '/e2e/*.php' ) as $file ) {
	$suites[ 'e2e/' . basename( $file ) ] = array( PHP_BINARY, $file );
}

$filter = $argv[1] ?? '';
$failed = array();
foreach ( $suites as $name => $command ) {
	if ( '' !== $filter && false === strpos( $name, $filter ) ) {
		continue;
	}
	echo "# {$name}\n";
	$result = run_command( $command );
	echo $result['stdout'];
	if ( 0 !== $result['exit'] ) {
		echo $result['stderr'];
		$failed[] = $name;
	}
}

if ( $failed ) {
	echo "\nFAILED: " . implode( ', ', $failed ) . "\n";
	exit( 1 );
}
echo "\nall suites passed\n";
