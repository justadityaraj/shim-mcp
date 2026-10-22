<?php
declare(strict_types=1);

namespace ShimMcp\Abilities\Content;

class Posts {

	public static function register(): void {

		wp_register_ability(
			'shim-mcp/posts-list',
			array(
				'label'               => __( 'Browse Blog Posts', 'shim-mcp' ),
				'description'         => __( 'Returns a page of blog posts. You may narrow the results by publication status, author, a free-text keyword, a category or a tag, and you control the page size and which page you land on.', 'shim-mcp' ),
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array(),
					'properties'           => array(
						'status'   => array(
							'type'        => 'string',
							'description' => __( 'Publication status to match, such as publish, draft, pending, future, private, trash, or any.', 'shim-mcp' ),
						),
						'author'   => array(
							'type'        => 'integer',
							'description' => __( 'Numeric user ID of the author whose posts you want.', 'shim-mcp' ),
						),
						'search'   => array(
							'type'        => 'string',
							'description' => __( 'Keyword matched against post titles and bodies.', 'shim-mcp' ),
						),
						'category' => array(
							'type'        => 'string',
							'description' => __( 'A category slug to restrict the results to.', 'shim-mcp' ),
						),
						'tag'      => array(
							'type'        => 'string',
							'description' => __( 'A tag slug to restrict the results to.', 'shim-mcp' ),
						),
						'per_page' => array(
							'type'        => 'integer',
							'description' => __( 'How many posts to return, from 1 up to 100. Defaults to 20.', 'shim-mcp' ),
						),
						'page'     => array(
							'type'        => 'integer',
							'description' => __( 'Which page of results to return, starting at 1.', 'shim-mcp' ),
						),
						'orderby'  => array(
							'type'        => 'string',
							'description' => __( 'Sort field: date, modified, title, ID, or menu_order.', 'shim-mcp' ),
						),
						'order'    => array(
							'type'        => 'string',
							'description' => __( 'Sort direction, either ASC or DESC.', 'shim-mcp' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'     => array( 'type' => 'boolean' ),
						'posts'       => array( 'type' => 'array' ),
						'total'       => array( 'type' => 'integer' ),
						'total_pages' => array( 'type' => 'integer' ),
						'page'        => array( 'type' => 'integer' ),
						'message'     => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) ) {
						$input = array();
					}

					$per_page = isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 20;
					if ( $per_page < 1 ) {
						$per_page = 20;
					}
					if ( $per_page > 100 ) {
						$per_page = 100;
					}

					$page = isset( $input['page'] ) ? absint( $input['page'] ) : 1;
					if ( $page < 1 ) {
						$page = 1;
					}

					$allowed_orderby = array( 'date', 'modified', 'title', 'ID', 'menu_order' );
					$orderby         = 'date';
					if ( isset( $input['orderby'] ) && is_string( $input['orderby'] ) && in_array( $input['orderby'], $allowed_orderby, true ) ) {
						$orderby = $input['orderby'];
					}

					$order = 'DESC';
					if ( isset( $input['order'] ) && is_string( $input['order'] ) && 'ASC' === strtoupper( $input['order'] ) ) {
						$order = 'ASC';
					}

					$args = array(
						'post_type'      => 'post',
						'post_status'    => 'any',
						'posts_per_page' => $per_page,
						'paged'          => $page,
						'orderby'        => $orderby,
						'order'          => $order,
					);

					if ( isset( $input['status'] ) && is_string( $input['status'] ) && '' !== $input['status'] ) {
						$args['post_status'] = sanitize_key( $input['status'] );
					}

					if ( isset( $input['author'] ) ) {
						$author_id = absint( $input['author'] );
						if ( $author_id > 0 ) {
							$args['author'] = $author_id;
						}
					}

					if ( isset( $input['search'] ) && is_string( $input['search'] ) && '' !== $input['search'] ) {
						$args['s'] = sanitize_text_field( $input['search'] );
					}

					if ( isset( $input['category'] ) && is_string( $input['category'] ) && '' !== $input['category'] ) {
						$args['category_name'] = sanitize_title( $input['category'] );
					}

					if ( isset( $input['tag'] ) && is_string( $input['tag'] ) && '' !== $input['tag'] ) {
						$args['tag'] = sanitize_title( $input['tag'] );
					}

					$query = new \WP_Query( $args );
					$posts = array();

					foreach ( $query->posts as $post ) {
						if ( ! current_user_can( 'read_post', $post->ID ) ) {
							continue;
						}

						$posts[] = array(
							'id'          => (int) $post->ID,
							'title'       => $post->post_title,
							'status'      => $post->post_status,
							'date'        => $post->post_date,
							'link'        => esc_url_raw( (string) get_permalink( $post ) ),
							'author_id'   => (int) $post->post_author,
							'author_name' => (string) get_the_author_meta( 'display_name', (int) $post->post_author ),
						);
					}

					return array(
						'success'     => true,
						'posts'       => $posts,
						'total'       => (int) $query->found_posts,
						'total_pages' => (int) $query->max_num_pages,
						'page'        => $page,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_posts' );
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

		wp_register_ability(
			'shim-mcp/posts-get',
			array(
				'label'               => __( 'Read A Single Post', 'shim-mcp' ),
				'description'         => __( 'Loads one post by its numeric ID and hands back the full body, the excerpt, the status and the taxonomy terms it belongs to.', 'shim-mcp' ),
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id' => array(
							'type'        => 'integer',
							'description' => __( 'Numeric ID of the post to load.', 'shim-mcp' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'post'    => array( 'type' => 'object' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['id'] ) ) {
						return array(
							'success' => false,
							'message' => __( 'A numeric post ID is required.', 'shim-mcp' ),
						);
					}

					$post_id = absint( $input['id'] );
					$post    = $post_id > 0 ? get_post( $post_id ) : null;

					if ( ! $post || 'post' !== $post->post_type ) {
						return array(
							'success' => false,
							'message' => __( 'No post exists with that ID.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'read_post', $post->ID ) ) {
						return array(
							'success' => false,
							'message' => __( 'You are not allowed to read this post.', 'shim-mcp' ),
						);
					}

					$categories = array();
					$cat_terms  = get_the_terms( $post->ID, 'category' );
					if ( is_array( $cat_terms ) ) {
						foreach ( $cat_terms as $term ) {
							$categories[] = array(
								'id'   => (int) $term->term_id,
								'name' => $term->name,
								'slug' => $term->slug,
							);
						}
					}

					$tags      = array();
					$tag_terms = get_the_terms( $post->ID, 'post_tag' );
					if ( is_array( $tag_terms ) ) {
						foreach ( $tag_terms as $term ) {
							$tags[] = array(
								'id'   => (int) $term->term_id,
								'name' => $term->name,
								'slug' => $term->slug,
							);
						}
					}

					return array(
						'success' => true,
						'post'    => array(
							'id'          => (int) $post->ID,
							'title'       => $post->post_title,
							'content'     => $post->post_content,
							'excerpt'     => $post->post_excerpt,
							'status'      => $post->post_status,
							'slug'        => $post->post_name,
							'date'        => $post->post_date,
							'modified'    => $post->post_modified,
							'link'        => esc_url_raw( (string) get_permalink( $post ) ),
							'author_id'   => (int) $post->post_author,
							'author_name' => (string) get_the_author_meta( 'display_name', (int) $post->post_author ),
							'categories'  => $categories,
							'tags'        => $tags,
						),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_posts' );
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

		wp_register_ability(
			'shim-mcp/posts-create',
			array(
				'label'               => __( 'Publish A New Post', 'shim-mcp' ),
				'description'         => __( 'Adds a new blog post. Only the title is mandatory; everything else, including the body, excerpt, status, URL slug, categories, tags and author, is optional and falls back to sensible defaults.', 'shim-mcp' ),
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'title' ),
					'properties'           => array(
						'title'      => array(
							'type'        => 'string',
							'description' => __( 'Headline of the new post.', 'shim-mcp' ),
						),
						'content'    => array(
							'type'        => 'string',
							'description' => __( 'Body of the post, either block markup or plain HTML.', 'shim-mcp' ),
						),
						'excerpt'    => array(
							'type'        => 'string',
							'description' => __( 'Short summary shown in listings and feeds.', 'shim-mcp' ),
						),
						'status'     => array(
							'type'        => 'string',
							'description' => __( 'Where the post should land: draft, publish, pending, private or future. Draft is used when omitted.', 'shim-mcp' ),
						),
						'slug'       => array(
							'type'        => 'string',
							'description' => __( 'Preferred URL slug. WordPress derives one from the title if you skip it.', 'shim-mcp' ),
						),
						'categories' => array(
							'type'        => 'array',
							'description' => __( 'Category names or slugs to file the post under.', 'shim-mcp' ),
							'items'       => array( 'type' => 'string' ),
						),
						'tags'       => array(
							'type'        => 'array',
							'description' => __( 'Tag names to attach to the post.', 'shim-mcp' ),
							'items'       => array( 'type' => 'string' ),
						),
						'author'     => array(
							'type'        => 'integer',
							'description' => __( 'User ID to credit as the author. Requires permission to write on behalf of other users.', 'shim-mcp' ),
						),
						'date'       => array(
							'type'        => 'string',
							'description' => __( 'Publication timestamp in Y-m-d H:i:s form, in site local time.', 'shim-mcp' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
						'link'    => array( 'type' => 'string' ),
						'status'  => array( 'type' => 'string' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['title'] ) || ! is_string( $input['title'] ) || '' === trim( $input['title'] ) ) {
						return array(
							'success' => false,
							'message' => __( 'A post title is required.', 'shim-mcp' ),
						);
					}

					$allowed_status = array( 'draft', 'publish', 'pending', 'private', 'future' );
					$status         = 'draft';
					if ( isset( $input['status'] ) && is_string( $input['status'] ) && in_array( $input['status'], $allowed_status, true ) ) {
						$status = $input['status'];
					}

					if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) {
						return array(
							'success' => false,
							'message' => __( 'You are not allowed to publish posts on this site.', 'shim-mcp' ),
						);
					}

					$postarr = array(
						'post_type'    => 'post',
						'post_title'   => sanitize_text_field( $input['title'] ),
						'post_status'  => $status,
						'post_content' => isset( $input['content'] ) && is_string( $input['content'] ) ? self::permitted_markup( $input['content'] ) : '',
						'post_excerpt' => isset( $input['excerpt'] ) && is_string( $input['excerpt'] ) ? self::permitted_markup( $input['excerpt'] ) : '',
					);

					if ( isset( $input['slug'] ) && is_string( $input['slug'] ) && '' !== $input['slug'] ) {
						$postarr['post_name'] = sanitize_title( $input['slug'] );
					}

					if ( isset( $input['date'] ) && is_string( $input['date'] ) && '' !== $input['date'] ) {
						$timestamp = strtotime( $input['date'] );
						if ( false === $timestamp ) {
							return array(
								'success' => false,
								'message' => __( 'The publication date could not be understood.', 'shim-mcp' ),
							);
						}
						$postarr['post_date'] = gmdate( 'Y-m-d H:i:s', $timestamp );
					}

					if ( isset( $input['author'] ) ) {
						$author_id = absint( $input['author'] );
						if ( $author_id > 0 ) {
							if ( get_current_user_id() !== $author_id && ! current_user_can( 'edit_others_posts' ) ) {
								return array(
									'success' => false,
									'message' => __( 'You are not allowed to assign posts to another user.', 'shim-mcp' ),
								);
							}
							if ( ! get_userdata( $author_id ) ) {
								return array(
									'success' => false,
									'message' => __( 'No user exists with that ID.', 'shim-mcp' ),
								);
							}
							$postarr['post_author'] = $author_id;
						}
					}

					$new_id = wp_insert_post( wp_slash( $postarr ), true );

					if ( is_wp_error( $new_id ) ) {
						return array(
							'success' => false,
							'message' => $new_id->get_error_message(),
						);
					}

					$new_id = (int) $new_id;

					if ( isset( $input['categories'] ) && is_array( $input['categories'] ) ) {
						$category_ids = array();
						foreach ( $input['categories'] as $category ) {
							if ( ! is_string( $category ) || '' === trim( $category ) ) {
								continue;
							}
							$name = sanitize_text_field( $category );
							$term = get_term_by( 'slug', sanitize_title( $name ), 'category' );
							if ( ! $term ) {
								$term = get_term_by( 'name', $name, 'category' );
							}
							if ( $term ) {
								$category_ids[] = (int) $term->term_id;
								continue;
							}
							$created = wp_insert_term( $name, 'category' );
							if ( ! is_wp_error( $created ) && isset( $created['term_id'] ) ) {
								$category_ids[] = (int) $created['term_id'];
							}
						}
						if ( $category_ids ) {
							wp_set_post_terms( $new_id, $category_ids, 'category' );
						}
					}

					if ( isset( $input['tags'] ) && is_array( $input['tags'] ) ) {
						$tag_names = array();
						foreach ( $input['tags'] as $tag ) {
							if ( is_string( $tag ) && '' !== trim( $tag ) ) {
								$tag_names[] = sanitize_text_field( $tag );
							}
						}
						if ( $tag_names ) {
							wp_set_post_terms( $new_id, $tag_names, 'post_tag' );
						}
					}

					return array(
						'success' => true,
						'id'      => $new_id,
						'link'    => esc_url_raw( (string) get_permalink( $new_id ) ),
						'status'  => (string) get_post_status( $new_id ),
						'message' => __( 'The post was created.', 'shim-mcp' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'publish_posts' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);

		wp_register_ability(
			'shim-mcp/posts-update',
			array(
				'label'               => __( 'Edit An Existing Post', 'shim-mcp' ),
				'description'         => __( 'Changes one or more fields on a post that already exists. Fields you leave out keep their current values. Reassigning the post to a different author is only permitted for users who can edit other people\'s posts.', 'shim-mcp' ),
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id'         => array(
							'type'        => 'integer',
							'description' => __( 'Numeric ID of the post being edited.', 'shim-mcp' ),
						),
						'title'      => array(
							'type'        => 'string',
							'description' => __( 'Replacement headline.', 'shim-mcp' ),
						),
						'content'    => array(
							'type'        => 'string',
							'description' => __( 'Replacement body for the post.', 'shim-mcp' ),
						),
						'excerpt'    => array(
							'type'        => 'string',
							'description' => __( 'Replacement summary.', 'shim-mcp' ),
						),
						'status'     => array(
							'type'        => 'string',
							'description' => __( 'New publication status: draft, publish, pending, private or future.', 'shim-mcp' ),
						),
						'slug'       => array(
							'type'        => 'string',
							'description' => __( 'New URL slug for the post.', 'shim-mcp' ),
						),
						'categories' => array(
							'type'        => 'array',
							'description' => __( 'Category names or slugs that replace the current set.', 'shim-mcp' ),
							'items'       => array( 'type' => 'string' ),
						),
						'tags'       => array(
							'type'        => 'array',
							'description' => __( 'Tag names that replace the current set.', 'shim-mcp' ),
							'items'       => array( 'type' => 'string' ),
						),
						'author'     => array(
							'type'        => 'integer',
							'description' => __( 'User ID of the new author. Needs the edit_others_posts capability.', 'shim-mcp' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
						'updated' => array( 'type' => 'array' ),
						'link'    => array( 'type' => 'string' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['id'] ) ) {
						return array(
							'success' => false,
							'message' => __( 'A numeric post ID is required.', 'shim-mcp' ),
						);
					}

					$post_id = absint( $input['id'] );
					$post    = $post_id > 0 ? get_post( $post_id ) : null;

					if ( ! $post || 'post' !== $post->post_type ) {
						return array(
							'success' => false,
							'message' => __( 'No post exists with that ID.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'edit_post', $post->ID ) ) {
						return array(
							'success' => false,
							'message' => __( 'You are not allowed to edit this post.', 'shim-mcp' ),
						);
					}

					$postarr = array( 'ID' => $post->ID );
					$changed = array();

					if ( isset( $input['title'] ) && is_string( $input['title'] ) ) {
						$postarr['post_title'] = sanitize_text_field( $input['title'] );
						$changed[]             = 'title';
					}

					if ( isset( $input['content'] ) && is_string( $input['content'] ) ) {
						$postarr['post_content'] = self::permitted_markup( $input['content'] );
						$changed[]               = 'content';
					}

					if ( isset( $input['excerpt'] ) && is_string( $input['excerpt'] ) ) {
						$postarr['post_excerpt'] = self::permitted_markup( $input['excerpt'] );
						$changed[]               = 'excerpt';
					}

					if ( isset( $input['slug'] ) && is_string( $input['slug'] ) && '' !== $input['slug'] ) {
						$postarr['post_name'] = sanitize_title( $input['slug'] );
						$changed[]            = 'slug';
					}

					if ( isset( $input['status'] ) && is_string( $input['status'] ) ) {
						$allowed_status = array( 'draft', 'publish', 'pending', 'private', 'future' );
						if ( ! in_array( $input['status'], $allowed_status, true ) ) {
							return array(
								'success' => false,
								'message' => __( 'That publication status is not supported.', 'shim-mcp' ),
							);
						}
						if ( 'publish' === $input['status'] && ! current_user_can( 'publish_posts' ) ) {
							return array(
								'success' => false,
								'message' => __( 'You are not allowed to publish posts on this site.', 'shim-mcp' ),
							);
						}
						$postarr['post_status'] = $input['status'];
						$changed[]              = 'status';
					}

					if ( isset( $input['author'] ) ) {
						$author_id = absint( $input['author'] );
						if ( $author_id > 0 && $author_id !== (int) $post->post_author ) {
							if ( ! current_user_can( 'edit_others_posts' ) ) {
								return array(
									'success' => false,
									'message' => __( 'Changing the author requires the ability to edit other users\' posts.', 'shim-mcp' ),
								);
							}
							if ( ! get_userdata( $author_id ) ) {
								return array(
									'success' => false,
									'message' => __( 'No user exists with that ID.', 'shim-mcp' ),
								);
							}
							$postarr['post_author'] = $author_id;
							$changed[]              = 'author';
						}
					}

					if ( count( $postarr ) > 1 ) {
						$result = wp_update_post( wp_slash( $postarr ), true );
						if ( is_wp_error( $result ) ) {
							return array(
								'success' => false,
								'message' => $result->get_error_message(),
							);
						}
					}

					if ( isset( $input['categories'] ) && is_array( $input['categories'] ) ) {
						$category_ids = array();
						foreach ( $input['categories'] as $category ) {
							if ( ! is_string( $category ) || '' === trim( $category ) ) {
								continue;
							}
							$name = sanitize_text_field( $category );
							$term = get_term_by( 'slug', sanitize_title( $name ), 'category' );
							if ( ! $term ) {
								$term = get_term_by( 'name', $name, 'category' );
							}
							if ( $term ) {
								$category_ids[] = (int) $term->term_id;
								continue;
							}
							$created = wp_insert_term( $name, 'category' );
							if ( ! is_wp_error( $created ) && isset( $created['term_id'] ) ) {
								$category_ids[] = (int) $created['term_id'];
							}
						}
						wp_set_post_terms( $post->ID, $category_ids, 'category' );
						$changed[] = 'categories';
					}

					if ( isset( $input['tags'] ) && is_array( $input['tags'] ) ) {
						$tag_names = array();
						foreach ( $input['tags'] as $tag ) {
							if ( is_string( $tag ) && '' !== trim( $tag ) ) {
								$tag_names[] = sanitize_text_field( $tag );
							}
						}
						wp_set_post_terms( $post->ID, $tag_names, 'post_tag' );
						$changed[] = 'tags';
					}

					if ( ! $changed ) {
						return array(
							'success' => false,
							'message' => __( 'Nothing was supplied to change on this post.', 'shim-mcp' ),
						);
					}

					return array(
						'success' => true,
						'id'      => (int) $post->ID,
						'updated' => $changed,
						'link'    => esc_url_raw( (string) get_permalink( $post->ID ) ),
						'message' => __( 'The post was updated.', 'shim-mcp' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'shim-mcp/posts-delete',
			array(
				'label'               => __( 'Remove A Post', 'shim-mcp' ),
				'description'         => __( 'Sends a post to the trash so it can be restored later, or erases it for good when you ask for a permanent removal.', 'shim-mcp' ),
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id'    => array(
							'type'        => 'integer',
							'description' => __( 'Numeric ID of the post to remove.', 'shim-mcp' ),
						),
						'force' => array(
							'type'        => 'boolean',
							'description' => __( 'Set this to true to erase the post outright instead of trashing it. This cannot be undone.', 'shim-mcp' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'   => array( 'type' => 'boolean' ),
						'id'        => array( 'type' => 'integer' ),
						'permanent' => array( 'type' => 'boolean' ),
						'message'   => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['id'] ) ) {
						return array(
							'success' => false,
							'message' => __( 'A numeric post ID is required.', 'shim-mcp' ),
						);
					}

					$post_id = absint( $input['id'] );
					$post    = $post_id > 0 ? get_post( $post_id ) : null;

					if ( ! $post || 'post' !== $post->post_type ) {
						return array(
							'success' => false,
							'message' => __( 'No post exists with that ID.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'delete_post', $post->ID ) ) {
						return array(
							'success' => false,
							'message' => __( 'You are not allowed to delete this post.', 'shim-mcp' ),
						);
					}

					$force = isset( $input['force'] ) && true === filter_var( $input['force'], FILTER_VALIDATE_BOOLEAN );

					$result = $force ? wp_delete_post( $post->ID, true ) : wp_trash_post( $post->ID );

					if ( ! $result ) {
						return array(
							'success' => false,
							'message' => __( 'WordPress refused to remove this post.', 'shim-mcp' ),
						);
					}

					return array(
						'success'   => true,
						'id'        => (int) $post->ID,
						'permanent' => $force,
						'message'   => $force
							? __( 'The post was permanently erased.', 'shim-mcp' )
							: __( 'The post was moved to the trash.', 'shim-mcp' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'delete_posts' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
				),
			)
		);

		wp_register_ability(
			'shim-mcp/posts-replace-text',
			array(
				'label'               => __( 'Find And Replace In Post Body', 'shim-mcp' ),
				'description'         => __( 'Swaps text inside a post body. By default the search string is treated literally; turn on the regex option to interpret it as a PCRE pattern instead. The reply tells you how many substitutions were made.', 'shim-mcp' ),
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id', 'search', 'replace' ),
					'properties'           => array(
						'id'          => array(
							'type'        => 'integer',
							'description' => __( 'Numeric ID of the post whose body should be edited.', 'shim-mcp' ),
						),
						'search'      => array(
							'type'        => 'string',
							'description' => __( 'Text to look for, or a regular expression body when regex mode is on.', 'shim-mcp' ),
						),
						'replace'     => array(
							'type'        => 'string',
							'description' => __( 'Text that takes the place of each match. In regex mode backreferences such as $1 are honoured.', 'shim-mcp' ),
						),
						'regex'       => array(
							'type'        => 'boolean',
							'description' => __( 'Treat the search value as a regular expression rather than plain text. Off by default.', 'shim-mcp' ),
						),
						'ignore_case' => array(
							'type'        => 'boolean',
							'description' => __( 'Match without regard to letter case. Off by default.', 'shim-mcp' ),
						),
						'dry_run'     => array(
							'type'        => 'boolean',
							'description' => __( 'Count the matches and report them without saving any change.', 'shim-mcp' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'      => array( 'type' => 'boolean' ),
						'id'           => array( 'type' => 'integer' ),
						'replacements' => array( 'type' => 'integer' ),
						'saved'        => array( 'type' => 'boolean' ),
						'message'      => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['id'], $input['search'], $input['replace'] ) ) {
						return array(
							'success' => false,
							'message' => __( 'A post ID, a search value and a replacement value are all required.', 'shim-mcp' ),
						);
					}

					if ( ! is_string( $input['search'] ) || ! is_string( $input['replace'] ) ) {
						return array(
							'success' => false,
							'message' => __( 'The search and replacement values must both be text.', 'shim-mcp' ),
						);
					}

					if ( '' === $input['search'] ) {
						return array(
							'success' => false,
							'message' => __( 'The search value cannot be empty.', 'shim-mcp' ),
						);
					}

					$post_id = absint( $input['id'] );
					$post    = $post_id > 0 ? get_post( $post_id ) : null;

					if ( ! $post || 'post' !== $post->post_type ) {
						return array(
							'success' => false,
							'message' => __( 'No post exists with that ID.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'edit_post', $post->ID ) ) {
						return array(
							'success' => false,
							'message' => __( 'You are not allowed to edit this post.', 'shim-mcp' ),
						);
					}

					$use_regex   = isset( $input['regex'] ) && true === filter_var( $input['regex'], FILTER_VALIDATE_BOOLEAN );
					$ignore_case = isset( $input['ignore_case'] ) && true === filter_var( $input['ignore_case'], FILTER_VALIDATE_BOOLEAN );
					$dry_run     = isset( $input['dry_run'] ) && true === filter_var( $input['dry_run'], FILTER_VALIDATE_BOOLEAN );

					$original = (string) $post->post_content;
					$count    = 0;

					if ( $use_regex ) {
						$modifiers = 's' . ( $ignore_case ? 'i' : '' );
						$pattern   = '#' . str_replace( '#', '\\#', $input['search'] ) . '#' . $modifiers;

						// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a malformed user pattern must fail as a return value, not a PHP warning.
						$compiles = @preg_match( $pattern, '' );

						if ( false === $compiles ) {
							return array(
								'success' => false,
								'message' => __( 'That regular expression is not valid and was not run.', 'shim-mcp' ),
							);
						}

						$updated = preg_replace( $pattern, $input['replace'], $original, -1, $count );

						if ( null === $updated ) {
							return array(
								'success' => false,
								'message' => __( 'The regular expression failed while running against this post.', 'shim-mcp' ),
							);
						}
					} else {
						$updated = $ignore_case
							? str_ireplace( $input['search'], $input['replace'], $original, $count )
							: str_replace( $input['search'], $input['replace'], $original, $count );
					}

					if ( 0 === $count ) {
						return array(
							'success'      => true,
							'id'           => (int) $post->ID,
							'replacements' => 0,
							'saved'        => false,
							'message'      => __( 'Nothing in this post matched the search value.', 'shim-mcp' ),
						);
					}

					if ( $dry_run ) {
						return array(
							'success'      => true,
							'id'           => (int) $post->ID,
							'replacements' => (int) $count,
							'saved'        => false,
							'message'      => __( 'Matches were counted only; the post was left untouched.', 'shim-mcp' ),
						);
					}

					$result = wp_update_post(
						wp_slash(
							array(
								'ID'           => $post->ID,
								'post_content' => self::permitted_markup( (string) $updated ),
							)
						),
						true
					);

					if ( is_wp_error( $result ) ) {
						return array(
							'success' => false,
							'message' => $result->get_error_message(),
						);
					}

					return array(
						'success'      => true,
						'id'           => (int) $post->ID,
						'replacements' => (int) $count,
						'saved'        => true,
						'message'      => __( 'The post body was rewritten.', 'shim-mcp' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
				),
			)
		);
	}

	private static function permitted_markup( string $markup ): string {
		return current_user_can( 'unfiltered_html' ) ? $markup : wp_kses_post( $markup );
	}
}
