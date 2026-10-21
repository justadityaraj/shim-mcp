<?php

require_once __DIR__ . '/../lib/harness.php';

$meta_tools = array( 'shim-mcp/discover-abilities', 'shim-mcp/execute-ability', 'shim-mcp/get-ability-info' );
$names      = array_map( static fn( $ability ) => $ability->get_name(), wp_get_abilities() );
$shim       = array_filter( $names, static fn( $name ) => 0 === strpos( $name, 'shim-mcp/' ) );
$abilities  = array_values( array_diff( $shim, $meta_tools ) );
$registered = array_values( array_intersect( $meta_tools, $shim ) );

same( 56, count( $abilities ), 'all Shim MCP abilities register' );
same( $meta_tools, $registered, 'the three MCP meta-tool abilities register' );

$unexposed = array_filter(
	$abilities,
	static fn( $name ) => true !== ( wp_get_ability( $name )->get_meta()['mcp']['public'] ?? null )
);
same( array(), array_values( $unexposed ), 'every Shim MCP ability is exposed to MCP' );

$categories = array_unique( array_map( static fn( $name ) => wp_get_ability( $name )->get_category(), $abilities ) );
sort( $categories );
same( array( 'site', 'user' ), $categories, "every ability is filed under WordPress core's site and user categories" );
check( null !== wp_get_ability_category( 'site' ) && null !== wp_get_ability_category( 'user' ), 'the core site and user categories are registered' );
