<?php
declare(strict_types=1);

namespace ShimMcp\Abilities\Content;

class Search {

	public static function register(): void {
		wp_register_ability(
			'shim-mcp/content-search',
			array(
				'label'               => 'Search Content',
				'description'         => 'Finds posts and pages whose title or body matches a keyword. By default it looks at posts and pages, but a caller can name any set of registered post types instead. Each hit comes back with its numeric id, post type, title, permalink and a trimmed excerpt, and the number of hits is capped by a limit the caller can raise or lower.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'keyword' ),
					'properties'           => array(
						'keyword'    => array(
							'type'        => 'string',
							'description' => 'The words to look for in post titles and content.',
							'minLength'   => 1,
						),
						'post_types' => array(
							'type'        => 'array',
							'description' => 'Restrict the search to these registered post types. Leave it out to search posts and pages.',
							'items'       => array( 'type' => 'string' ),
						),
						'limit'      => array(
							'type'        => 'integer',
							'description' => 'How many matches to return at most. Defaults to twenty and cannot exceed one hundred.',
							'minimum'     => 1,
							'maximum'     => 100,
						),
						'status'     => array(
							'type'        => 'string',
							'description' => 'Only return content in this status, for example publish, draft or pending. Leave it out to include every status the current user is allowed to see.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
						'keyword' => array( 'type' => 'string' ),
						'count'   => array( 'type' => 'integer' ),
						'results' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'        => array( 'type' => 'integer' ),
									'post_type' => array( 'type' => 'string' ),
									'title'     => array( 'type' => 'string' ),
									'status'    => array( 'type' => 'string' ),
									'link'      => array( 'type' => 'string' ),
									'excerpt'   => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The search request was not shaped as an object.', 'shim-mcp' ),
						);
					}

					$raw_keyword = $input['keyword'] ?? '';

					if ( ! is_string( $raw_keyword ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The keyword has to be given as text.', 'shim-mcp' ),
						);
					}

					$keyword = sanitize_text_field( $raw_keyword );

					if ( '' === trim( $keyword ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Give a keyword with at least one visible character.', 'shim-mcp' ),
						);
					}

					$post_types = array( 'post', 'page' );

					if ( isset( $input['post_types'] ) ) {
						if ( ! is_array( $input['post_types'] ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The post_types value has to be a list of post type names.', 'shim-mcp' ),
							);
						}

						$requested = array();

						foreach ( $input['post_types'] as $candidate ) {
							if ( ! is_string( $candidate ) ) {
								continue;
							}

							$name = sanitize_key( $candidate );

							if ( '' === $name || ! post_type_exists( $name ) ) {
								continue;
							}

							$requested[] = $name;
						}

						$requested = array_values( array_unique( $requested ) );

						if ( empty( $requested ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'None of the post types you named are registered on this site.', 'shim-mcp' ),
							);
						}

						$post_types = $requested;
					}

					$limit = 20;

					if ( isset( $input['limit'] ) ) {
						$limit = absint( $input['limit'] );

						if ( $limit < 1 ) {
							$limit = 20;
						}

						if ( $limit > 100 ) {
							$limit = 100;
						}
					}

					$status = 'any';

					if ( isset( $input['status'] ) ) {
						if ( ! is_string( $input['status'] ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The status value has to be given as text.', 'shim-mcp' ),
							);
						}

						$requested_status = sanitize_key( $input['status'] );

						if ( '' !== $requested_status ) {
							$status = $requested_status;
						}
					}

					$query = new \WP_Query(
						array(
							's'                      => $keyword,
							'post_type'              => $post_types,
							'post_status'            => $status,
							'posts_per_page'         => $limit * 3,
							'ignore_sticky_posts'    => true,
							'no_found_rows'          => true,
							'suppress_filters'       => false,
							'update_post_meta_cache' => false,
							'update_post_term_cache' => false,
						)
					);

					$results = array();

					foreach ( $query->posts as $post ) {
						if ( count( $results ) >= $limit ) {
							break;
						}

						if ( ! current_user_can( 'read_post', $post->ID ) ) {
							continue;
						}

						$excerpt = $post->post_excerpt;

						if ( '' === trim( (string) $excerpt ) ) {
							$excerpt = $post->post_content;
						}

						$excerpt = wp_strip_all_tags( strip_shortcodes( (string) $excerpt ) );
						$excerpt = wp_trim_words( $excerpt, 40, '…' );

						$permalink = get_permalink( $post );

						$results[] = array(
							'id'        => (int) $post->ID,
							'post_type' => esc_html( (string) $post->post_type ),
							'title'     => esc_html( get_the_title( $post ) ),
							'status'    => esc_html( (string) $post->post_status ),
							'link'      => is_string( $permalink ) ? esc_url_raw( $permalink ) : '',
							'excerpt'   => esc_html( $excerpt ),
						);
					}

					return array(
						'success' => true,
						'keyword' => esc_html( $keyword ),
						'count'   => count( $results ),
						'results' => $results,
						'message' => empty( $results )
							? esc_html__( 'Nothing readable matched that keyword.', 'shim-mcp' )
							: esc_html__( 'Matching content found.', 'shim-mcp' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'read' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);
	}
}
