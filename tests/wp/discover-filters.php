<?php

require_once __DIR__ . '/../lib/harness.php';

wp_set_current_user( 1 );

$discover = 'ShimMcp\Server\Abilities\DiscoverAbilitiesAbility';

$expected = array();
foreach ( wp_get_abilities() as $ability ) {
	$meta = $ability->get_meta();
	if ( true !== ( $meta['mcp']['public'] ?? false ) ) {
		continue;
	}
	if ( ( $meta['mcp']['type'] ?? 'tool' ) !== 'tool' ) {
		continue;
	}
	$expected[] = $ability;
}

$all = $discover::execute( array() );
same( count( $expected ), count( $all['abilities'] ), 'a call with no input returns every public tool ability' );
same(
	array_map( static fn( $a ) => $a->get_name(), $expected ),
	array_column( $all['abilities'], 'name' ),
	'a call with no input matches the 1.1.0 output'
);
same(
	array( 'name', 'label', 'description' ),
	array_keys( $all['abilities'][0] ),
	'ability entries keep the name, label and description shape'
);

$schema = wp_get_ability( 'shim-mcp/discover-abilities' )->get_input_schema();
same(
	array( 'category', 'search', 'limit' ),
	array_keys( $schema['properties'] ),
	'the input schema declares the category, search and limit filters'
);
same( array(), $schema['required'] ?? array(), 'every filter is optional' );
same( array(), $schema['default'] ?? null, 'omitted input defaults to an empty array' );
check( ! isset( $schema['properties']['search']['minLength'] ), 'search has no minLength bound, so an empty string reaches the callback' );
check( ! isset( $schema['properties']['limit']['minimum'] ), 'limit has no minimum bound, so non-positive values reach the callback' );

foreach ( array( 'site', 'user', 'shim-mcp' ) as $category ) {
	$in_category = array_values(
		array_filter(
			$expected,
			static fn( $a ) => $a->get_category() === $category
		)
	);
	$result = $discover::execute( array( 'category' => $category ) );
	same(
		array_map( static fn( $a ) => $a->get_name(), $in_category ),
		array_column( $result['abilities'], 'name' ),
		"category={$category} returns only that category's abilities"
	);
}

$unknown = $discover::execute( array( 'category' => 'no-such-category' ) );
same( array(), $unknown['abilities'], 'an unknown category returns an empty list' );
check( ! isset( $unknown['error'] ), 'an unknown category is not an error' );

$lower = $discover::execute( array( 'search' => 'search' ) );
$upper = $discover::execute( array( 'search' => 'SEARCH' ) );
same(
	array_column( $lower['abilities'], 'name' ),
	array_column( $upper['abilities'], 'name' ),
	'search is case-insensitive'
);
$matching = array_values(
	array_filter(
		$expected,
		static function ( $a ) {
			$haystack = $a->get_name() . "\n" . $a->get_label() . "\n" . $a->get_description();
			return false !== stripos( $haystack, 'search' );
		}
	)
);
same(
	array_map( static fn( $a ) => $a->get_name(), $matching ),
	array_column( $lower['abilities'], 'name' ),
	'search matches names, labels and descriptions'
);
$none = $discover::execute( array( 'search' => 'qx-no-match-zz' ) );
same( array(), $none['abilities'], 'a search with no matches returns an empty list' );

$three = $discover::execute( array( 'limit' => 3 ) );
same( 3, count( $three['abilities'] ), 'limit caps the number of results' );
same(
	array_slice( array_column( $all['abilities'], 'name' ), 0, 3 ),
	array_column( $three['abilities'], 'name' ),
	'limit keeps the first abilities of the unfiltered list'
);

$combined = $discover::execute(
	array(
		'category' => 'site',
		'search'   => 'search',
		'limit'    => 2,
	)
);
check( count( $combined['abilities'] ) <= 2, 'combined filters respect the limit' );
foreach ( $combined['abilities'] as $entry ) {
	$ability = wp_get_ability( $entry['name'] );
	$haystack = $entry['name'] . "\n" . $entry['label'] . "\n" . $entry['description'];
	same( 'site', $ability->get_category(), "combined filters keep only site abilities ({$entry['name']})" );
	check( false !== stripos( $haystack, 'search' ), "combined filters keep only matches ({$entry['name']})" );
}

$tolerant = $discover::execute(
	array(
		'category' => 42,
		'search'   => array( 'not a string' ),
		'limit'    => 'three',
	)
);
same(
	array_column( $all['abilities'], 'name' ),
	array_column( $tolerant['abilities'], 'name' ),
	'wrong-typed filter values are ignored'
);

$registered = wp_get_ability( 'shim-mcp/discover-abilities' );
check( false !== $registered, 'the discover ability is registered' );
$no_args = $registered->execute();
check( ! is_wp_error( $no_args ), 'a no-argument execute() call is not rejected' );
same(
	array_column( $all['abilities'], 'name' ),
	array_column( $no_args['abilities'], 'name' ),
	'a no-argument execute() call returns the unfiltered list'
);

$ignored = $registered->execute(
	array(
		'search' => '',
		'limit'  => 0,
	)
);
check( ! is_wp_error( $ignored ), 'an empty search and a zero limit are not rejected' );
same(
	array_column( $all['abilities'], 'name' ),
	array_column( $ignored['abilities'], 'name' ),
	'an empty search and a zero limit are ignored, matching the unfiltered list'
);
