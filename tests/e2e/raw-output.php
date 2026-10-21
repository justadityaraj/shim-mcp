<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/harness.php';

$value = "Rock & Roll <draft> \"quoted\" O'Brien " . bin2hex( random_bytes( 3 ) );

$post_id       = wp_cli( '--user=admin', 'post', 'create', '--post_title=' . $value, '--post_status=publish', '--porcelain' );
$attachment_id = wp_cli( '--user=admin', 'post', 'create', '--post_type=attachment', '--post_mime_type=image/png', '--post_status=inherit', '--post_title=' . $value, '--post_excerpt=' . $value, '--post_content=' . $value, '--porcelain' );
wp_cli( 'post', 'meta', 'add', $attachment_id, '_wp_attachment_image_alt', $value );
$comment_id = wp_cli( '--user=admin', 'comment', 'create', '--comment_post_ID=' . $post_id, '--comment_author=' . $value, '--comment_content=' . $value, '--porcelain' );
$term_id    = wp_cli( '--user=admin', 'term', 'create', 'post_tag', $value, '--description=' . $value, '--porcelain' );

$stored = array(
	'post title'             => wp_cli( 'post', 'get', $post_id, '--field=post_title' ),
	'attachment title'       => wp_cli( 'post', 'get', $attachment_id, '--field=post_title' ),
	'attachment caption'     => wp_cli( 'post', 'get', $attachment_id, '--field=post_excerpt' ),
	'attachment description' => wp_cli( 'post', 'get', $attachment_id, '--field=post_content' ),
	'attachment alt text'    => wp_cli( 'post', 'meta', 'get', $attachment_id, '_wp_attachment_image_alt' ),
	'comment author'         => wp_cli( 'comment', 'get', $comment_id, '--field=comment_author' ),
	'comment content'        => wp_cli( 'comment', 'get', $comment_id, '--field=comment_content' ),
	'tag name'               => wp_cli( 'term', 'get', 'post_tag', $term_id, '--field=name' ),
	'tag description'        => wp_cli( 'term', 'get', 'post_tag', $term_id, '--field=description' ),
);
same( $value, $stored['post title'], 'the fixture title is stored exactly as written' );

$server = new StdioServer( array( '--server=shim-mcp-server', '--user=admin' ) );
$server->initialize();

$find = static function ( array $items, string $id ): array {
	foreach ( $items as $item ) {
		if ( (string) ( $item['id'] ?? '' ) === $id ) {
			return $item;
		}
	}
	return array();
};

$reads = array(
	'posts-get'          => array( 'shim-mcp/posts-get', array( 'id' => (int) $post_id ), static fn( $data ) => array( 'post title' => $data['post']['title'] ?? null ) ),
	'posts-list'         => array( 'shim-mcp/posts-list', array(), static fn( $data ) => array( 'post title' => $find( $data['posts'] ?? array(), $post_id )['title'] ?? null ) ),
	'media-get'          => array(
		'shim-mcp/media-get',
		array( 'id' => (int) $attachment_id ),
		static fn( $data ) => array(
			'attachment title'       => $data['attachment']['title'] ?? null,
			'attachment caption'     => $data['attachment']['caption'] ?? null,
			'attachment description' => $data['attachment']['description'] ?? null,
			'attachment alt text'    => $data['attachment']['alt_text'] ?? null,
		),
	),
	'comments-get'       => array(
		'shim-mcp/comments-get',
		array( 'id' => (int) $comment_id ),
		static fn( $data ) => array(
			'comment author'  => $data['comment']['author_name'] ?? null,
			'comment content' => $data['comment']['content'] ?? null,
		),
	),
	'taxonomy-list-tags' => array(
		'shim-mcp/taxonomy-list-tags',
		array( 'hide_empty' => false ),
		static fn( $data ) => array(
			'tag name'        => $find( $data['terms'] ?? array(), $term_id )['name'] ?? null,
			'tag description' => $find( $data['terms'] ?? array(), $term_id )['description'] ?? null,
		),
	),
);

foreach ( $reads as $ability_label => list( $ability, $parameters, $pick ) ) {
	$response   = $server->execute( $ability, $parameters );
	$text       = tool_payload( $response )['data'] ?? array();
	$structured = $response['result']['structuredContent']['data'] ?? array();
	foreach ( $pick( $text ) as $field => $returned ) {
		same( $stored[ $field ], $returned, "{$ability_label} returns the {$field} exactly as stored, in the text content" );
	}
	same( $pick( $text ), $pick( $structured ), "{$ability_label} returns the same values in structuredContent" );
}

$server->close();

$escaping = array();
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( dirname( __DIR__, 2 ) . '/includes/Abilities' ) ) as $file ) {
	if ( 'php' === $file->getExtension() && preg_match_all( '/\b(esc_html|esc_attr|esc_textarea|get_the_title)\w*\s*\(/', (string) file_get_contents( $file->getPathname() ), $matches ) ) {
		$escaping[ $file->getFilename() ] = array_unique( $matches[1] );
	}
}
same( array(), $escaping, 'no ability applies HTML display escaping or display filters to its output' );
