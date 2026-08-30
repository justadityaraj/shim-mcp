<?php
declare(strict_types=1);

namespace ShimMcp\Abilities\Content;

final class Pages {

	public static function register(): void {

		wp_register_ability(
			'shim-mcp/pages-list',
			array(
				'label'               => 'List Pages',
				'description'         => 'Returns a paginated list of pages on the site. The list can be narrowed by keyword, by status or by parent page, and both the sort field and the sort direction can be chosen.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array(),
					'properties'           => array(
						'search'   => array(
							'type'        => 'string',
							'description' => 'Keyword matched against page titles and page bodies.',
						),
						'status'   => array(
							'type'        => 'string',
							'description' => 'Restrict the list to a single status such as publish, draft, pending, private or future. Every status is returned when this is left out.',
						),
						'parent'   => array(
							'type'        => 'integer',
							'description' => 'Return only the direct children of this page identifier. Pass 0 to get the pages that sit at the top level.',
						),
						'per_page' => array(
							'type'        => 'integer',
							'description' => 'How many pages to return in one response, between 1 and 100. Twenty come back when this is omitted.',
						),
						'page'     => array(
							'type'        => 'integer',
							'description' => 'Which slice of the result set to return, counting from 1.',
						),
						'orderby'  => array(
							'type'        => 'string',
							'description' => 'Field to sort on. One of date, title, menu_order, modified, ID or parent. Sorting falls back to menu order and then title.',
						),
						'order'    => array(
							'type'        => 'string',
							'description' => 'Sort direction, either ASC or DESC.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'     => array( 'type' => 'boolean' ),
						'message'     => array( 'type' => 'string' ),
						'total'       => array( 'type' => 'integer' ),
						'total_pages' => array( 'type' => 'integer' ),
						'count'       => array( 'type' => 'integer' ),
						'pages'       => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'         => array( 'type' => 'integer' ),
									'title'      => array( 'type' => 'string' ),
									'slug'       => array( 'type' => 'string' ),
									'status'     => array( 'type' => 'string' ),
									'parent'     => array( 'type' => 'integer' ),
									'menu_order' => array( 'type' => 'integer' ),
									'template'   => array( 'type' => 'string' ),
									'author'     => array( 'type' => 'integer' ),
									'url'        => array( 'type' => 'string' ),
									'modified'   => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) ) {
						$input = array();
					}

					$args = array(
						'post_type'        => 'page',
						'post_status'      => 'any',
						'posts_per_page'   => 20,
						'paged'            => 1,
						'orderby'          => 'menu_order title',
						'order'            => 'ASC',
						'suppress_filters' => false,
					);

					if ( isset( $input['status'] ) && is_string( $input['status'] ) && '' !== $input['status'] ) {
						$status = sanitize_key( $input['status'] );

						if ( ! in_array( $status, array( 'publish', 'draft', 'pending', 'private', 'future', 'trash', 'any' ), true ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'That is not a status this site recognises for pages.', 'shim-mcp' ),
							);
						}

						$args['post_status'] = $status;
					}

					if ( isset( $input['search'] ) && is_string( $input['search'] ) && '' !== trim( $input['search'] ) ) {
						$args['s'] = sanitize_text_field( $input['search'] );
					}

					if ( isset( $input['parent'] ) && is_numeric( $input['parent'] ) ) {
						$args['post_parent'] = absint( $input['parent'] );
					}

					if ( isset( $input['per_page'] ) && is_numeric( $input['per_page'] ) ) {
						$args['posts_per_page'] = max( 1, min( 100, absint( $input['per_page'] ) ) );
					}

					if ( isset( $input['page'] ) && is_numeric( $input['page'] ) ) {
						$args['paged'] = max( 1, absint( $input['page'] ) );
					}

					if ( isset( $input['orderby'] ) && is_string( $input['orderby'] ) ) {
						$sortable = array( 'date', 'title', 'menu_order', 'modified', 'ID', 'parent' );

						if ( in_array( $input['orderby'], $sortable, true ) ) {
							$args['orderby'] = $input['orderby'];
						}
					}

					if ( isset( $input['order'] ) && is_string( $input['order'] ) ) {
						$direction = strtoupper( sanitize_key( $input['order'] ) );

						if ( 'ASC' === $direction || 'DESC' === $direction ) {
							$args['order'] = $direction;
						}
					}

					$query = new \WP_Query( $args );
					$rows  = array();

					foreach ( $query->posts as $page ) {
						if ( ! $page instanceof \WP_Post || ! current_user_can( 'read_post', $page->ID ) ) {
							continue;
						}

						$permalink = get_permalink( $page->ID );

						$rows[] = array(
							'id'         => (int) $page->ID,
							'title'      => esc_html( get_the_title( $page ) ),
							'slug'       => esc_html( $page->post_name ),
							'status'     => esc_html( $page->post_status ),
							'parent'     => (int) $page->post_parent,
							'menu_order' => (int) $page->menu_order,
							'template'   => esc_html( (string) get_page_template_slug( $page->ID ) ),
							'author'     => (int) $page->post_author,
							'url'        => is_string( $permalink ) ? esc_url_raw( $permalink ) : '',
							'modified'   => esc_html( (string) $page->post_modified_gmt ),
						);
					}

					return array(
						'success'     => true,
						'total'       => (int) $query->found_posts,
						'total_pages' => (int) $query->max_num_pages,
						'count'       => count( $rows ),
						'pages'       => $rows,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_pages' );
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
			'shim-mcp/pages-get',
			array(
				'label'               => 'Get A Page',
				'description'         => 'Loads one page by its numeric identifier and reports its title, slug, status, parent, menu order and assigned template, along with the page body unless the body is explicitly left out.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id'              => array(
							'type'        => 'integer',
							'description' => 'Numeric identifier of the page to load.',
						),
						'include_content' => array(
							'type'        => 'boolean',
							'description' => 'Set this to false to leave the page body out of the response and keep it small.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'    => array( 'type' => 'boolean' ),
						'message'    => array( 'type' => 'string' ),
						'id'         => array( 'type' => 'integer' ),
						'title'      => array( 'type' => 'string' ),
						'slug'       => array( 'type' => 'string' ),
						'status'     => array( 'type' => 'string' ),
						'parent'     => array( 'type' => 'integer' ),
						'menu_order' => array( 'type' => 'integer' ),
						'template'   => array( 'type' => 'string' ),
						'author'     => array( 'type' => 'integer' ),
						'excerpt'    => array( 'type' => 'string' ),
						'content'    => array( 'type' => 'string' ),
						'url'        => array( 'type' => 'string' ),
						'created'    => array( 'type' => 'string' ),
						'modified'   => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['id'] ) || ! is_numeric( $input['id'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'A numeric page identifier is required.', 'shim-mcp' ),
						);
					}

					$page = get_post( absint( $input['id'] ) );

					if ( ! $page instanceof \WP_Post ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Nothing on this site carries that identifier.', 'shim-mcp' ),
						);
					}

					if ( 'page' !== $page->post_type ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'That identifier belongs to another post type, not a page.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'read_post', $page->ID ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You are not allowed to read this page.', 'shim-mcp' ),
						);
					}

					$permalink = get_permalink( $page->ID );

					$result = array(
						'success'    => true,
						'id'         => (int) $page->ID,
						'title'      => esc_html( get_the_title( $page ) ),
						'slug'       => esc_html( $page->post_name ),
						'status'     => esc_html( $page->post_status ),
						'parent'     => (int) $page->post_parent,
						'menu_order' => (int) $page->menu_order,
						'template'   => esc_html( (string) get_page_template_slug( $page->ID ) ),
						'author'     => (int) $page->post_author,
						'excerpt'    => (string) $page->post_excerpt,
						'url'        => is_string( $permalink ) ? esc_url_raw( $permalink ) : '',
						'created'    => esc_html( (string) $page->post_date_gmt ),
						'modified'   => esc_html( (string) $page->post_modified_gmt ),
					);

					if ( ! isset( $input['include_content'] ) || false !== $input['include_content'] ) {
						$result['content'] = (string) $page->post_content;
					}

					return $result;
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_pages' );
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
			'shim-mcp/pages-create',
			array(
				'label'               => 'Create A Page',
				'description'         => 'Adds a new page to the site. Only a title is required; the body, parent page, position in the menu order and page template file can all be supplied as well.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'title' ),
					'properties'           => array(
						'title'      => array(
							'type'        => 'string',
							'description' => 'Title shown at the top of the page and in navigation.',
						),
						'content'    => array(
							'type'        => 'string',
							'description' => 'Body of the page, either block markup or plain HTML.',
						),
						'excerpt'    => array(
							'type'        => 'string',
							'description' => 'Short hand written summary of the page.',
						),
						'status'     => array(
							'type'        => 'string',
							'description' => 'Publication state of the new page: publish, draft, pending, private or future. A draft is created when this is omitted.',
						),
						'slug'       => array(
							'type'        => 'string',
							'description' => 'Preferred URL segment. WordPress derives one from the title when this is omitted.',
						),
						'parent'     => array(
							'type'        => 'integer',
							'description' => 'Identifier of the page this one should sit underneath. Pass 0 to keep it at the top level.',
						),
						'menu_order' => array(
							'type'        => 'integer',
							'description' => 'Sort position among sibling pages, where lower numbers come first.',
						),
						'template'   => array(
							'type'        => 'string',
							'description' => 'Template file from the active theme that should render this page, for example template-full-width.php. Pass default to use the theme default.',
						),
						'author'     => array(
							'type'        => 'integer',
							'description' => 'Identifier of the user to record as the author. The acting user is used when this is omitted.',
						),
						'date'       => array(
							'type'        => 'string',
							'description' => 'Publication moment in site local time, written for example as 2026-09-01 09:30:00. A future status needs one of these.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
						'id'      => array( 'type' => 'integer' ),
						'title'   => array( 'type' => 'string' ),
						'slug'    => array( 'type' => 'string' ),
						'status'  => array( 'type' => 'string' ),
						'url'     => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['title'] ) || ! is_string( $input['title'] ) || '' === trim( $input['title'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'A page needs a title before it can be created.', 'shim-mcp' ),
						);
					}

					$postarr = array(
						'post_type'   => 'page',
						'post_title'  => sanitize_text_field( $input['title'] ),
						'post_status' => 'draft',
					);

					if ( isset( $input['status'] ) && is_string( $input['status'] ) && '' !== $input['status'] ) {
						$status = sanitize_key( $input['status'] );

						if ( ! in_array( $status, array( 'publish', 'draft', 'pending', 'private', 'future' ), true ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The status must be publish, draft, pending, private or future.', 'shim-mcp' ),
							);
						}

						$postarr['post_status'] = $status;
					}

					if ( isset( $input['content'] ) && is_string( $input['content'] ) ) {
						$postarr['post_content'] = current_user_can( 'unfiltered_html' ) ? $input['content'] : wp_kses_post( $input['content'] );
					}

					if ( isset( $input['excerpt'] ) && is_string( $input['excerpt'] ) ) {
						$postarr['post_excerpt'] = sanitize_textarea_field( $input['excerpt'] );
					}

					if ( isset( $input['slug'] ) && is_string( $input['slug'] ) && '' !== trim( $input['slug'] ) ) {
						$postarr['post_name'] = sanitize_title( $input['slug'] );
					}

					if ( isset( $input['menu_order'] ) && is_numeric( $input['menu_order'] ) ) {
						$postarr['menu_order'] = (int) $input['menu_order'];
					}

					if ( isset( $input['parent'] ) && is_numeric( $input['parent'] ) ) {
						$parent_id = absint( $input['parent'] );

						if ( $parent_id > 0 ) {
							$parent = get_post( $parent_id );

							if ( ! $parent instanceof \WP_Post || 'page' !== $parent->post_type ) {
								return array(
									'success' => false,
									'message' => esc_html__( 'The parent identifier does not point at an existing page.', 'shim-mcp' ),
								);
							}
						}

						$postarr['post_parent'] = $parent_id;
					}

					if ( isset( $input['author'] ) && is_numeric( $input['author'] ) ) {
						$author_id = absint( $input['author'] );

						if ( ! get_userdata( $author_id ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'No user carries the author identifier that was supplied.', 'shim-mcp' ),
							);
						}

						$postarr['post_author'] = $author_id;
					}

					if ( isset( $input['date'] ) && is_string( $input['date'] ) && '' !== trim( $input['date'] ) ) {
						$stamp = strtotime( sanitize_text_field( $input['date'] ) );

						if ( false === $stamp ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The date could not be read. Write it as 2026-09-01 09:30:00.', 'shim-mcp' ),
							);
						}

						$postarr['post_date']     = gmdate( 'Y-m-d H:i:s', $stamp );
						$postarr['post_date_gmt'] = get_gmt_from_date( $postarr['post_date'] );
					}

					$template = '';

					if ( isset( $input['template'] ) && is_string( $input['template'] ) && '' !== trim( $input['template'] ) ) {
						$template = sanitize_text_field( $input['template'] );
						$known    = wp_get_theme()->get_page_templates( null, 'page' );

						if ( 'default' !== $template && ! isset( $known[ $template ] ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The active theme has no page template with that file name.', 'shim-mcp' ),
							);
						}
					}

					$new_id = wp_insert_post( $postarr, true );

					if ( is_wp_error( $new_id ) ) {
						return array(
							'success' => false,
							'message' => esc_html( $new_id->get_error_message() ),
						);
					}

					$new_id = (int) $new_id;

					if ( '' !== $template && 'default' !== $template ) {
						update_post_meta( $new_id, '_wp_page_template', $template );
					}

					$created   = get_post( $new_id );
					$permalink = get_permalink( $new_id );

					return array(
						'success' => true,
						'message' => esc_html__( 'The page was created.', 'shim-mcp' ),
						'id'      => $new_id,
						'title'   => $created instanceof \WP_Post ? esc_html( get_the_title( $created ) ) : '',
						'slug'    => $created instanceof \WP_Post ? esc_html( $created->post_name ) : '',
						'status'  => $created instanceof \WP_Post ? esc_html( $created->post_status ) : '',
						'url'     => is_string( $permalink ) ? esc_url_raw( $permalink ) : '',
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'publish_pages' );
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
			'shim-mcp/pages-update',
			array(
				'label'               => 'Update A Page',
				'description'         => 'Changes an existing page. Only the fields that are supplied get touched, so a page can be restructured by sending nothing but a new parent, a new menu order or a new template.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id'         => array(
							'type'        => 'integer',
							'description' => 'Numeric identifier of the page to change.',
						),
						'title'      => array(
							'type'        => 'string',
							'description' => 'Replacement title for the page.',
						),
						'content'    => array(
							'type'        => 'string',
							'description' => 'Replacement body, which overwrites whatever the page holds now.',
						),
						'excerpt'    => array(
							'type'        => 'string',
							'description' => 'Replacement hand written summary.',
						),
						'status'     => array(
							'type'        => 'string',
							'description' => 'New publication state: publish, draft, pending, private or future.',
						),
						'slug'       => array(
							'type'        => 'string',
							'description' => 'New URL segment for the page.',
						),
						'parent'     => array(
							'type'        => 'integer',
							'description' => 'Identifier of the page to move this one underneath. Pass 0 to lift it back to the top level.',
						),
						'menu_order' => array(
							'type'        => 'integer',
							'description' => 'New sort position among sibling pages.',
						),
						'template'   => array(
							'type'        => 'string',
							'description' => 'Template file from the active theme to render this page with. Pass default to go back to the theme default.',
						),
						'author'     => array(
							'type'        => 'integer',
							'description' => 'Identifier of the user to record as the author.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
						'id'      => array( 'type' => 'integer' ),
						'title'   => array( 'type' => 'string' ),
						'slug'    => array( 'type' => 'string' ),
						'status'  => array( 'type' => 'string' ),
						'url'     => array( 'type' => 'string' ),
						'changed' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['id'] ) || ! is_numeric( $input['id'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'A numeric page identifier is required.', 'shim-mcp' ),
						);
					}

					$page = get_post( absint( $input['id'] ) );

					if ( ! $page instanceof \WP_Post ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Nothing on this site carries that identifier.', 'shim-mcp' ),
						);
					}

					if ( 'page' !== $page->post_type ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'That identifier belongs to another post type, not a page.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'edit_post', $page->ID ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You are not allowed to edit this page.', 'shim-mcp' ),
						);
					}

					$postarr = array( 'ID' => $page->ID );
					$changed = array();

					if ( isset( $input['title'] ) && is_string( $input['title'] ) ) {
						if ( '' === trim( $input['title'] ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The title cannot be emptied.', 'shim-mcp' ),
							);
						}

						$postarr['post_title'] = sanitize_text_field( $input['title'] );
						$changed[]             = 'title';
					}

					if ( isset( $input['content'] ) && is_string( $input['content'] ) ) {
						$postarr['post_content'] = current_user_can( 'unfiltered_html' ) ? $input['content'] : wp_kses_post( $input['content'] );
						$changed[]               = 'content';
					}

					if ( isset( $input['excerpt'] ) && is_string( $input['excerpt'] ) ) {
						$postarr['post_excerpt'] = sanitize_textarea_field( $input['excerpt'] );
						$changed[]               = 'excerpt';
					}

					if ( isset( $input['status'] ) && is_string( $input['status'] ) && '' !== $input['status'] ) {
						$status = sanitize_key( $input['status'] );

						if ( ! in_array( $status, array( 'publish', 'draft', 'pending', 'private', 'future' ), true ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The status must be publish, draft, pending, private or future.', 'shim-mcp' ),
							);
						}

						$postarr['post_status'] = $status;
						$changed[]              = 'status';
					}

					if ( isset( $input['slug'] ) && is_string( $input['slug'] ) && '' !== trim( $input['slug'] ) ) {
						$postarr['post_name'] = sanitize_title( $input['slug'] );
						$changed[]            = 'slug';
					}

					if ( isset( $input['menu_order'] ) && is_numeric( $input['menu_order'] ) ) {
						$postarr['menu_order'] = (int) $input['menu_order'];
						$changed[]             = 'menu_order';
					}

					if ( isset( $input['parent'] ) && is_numeric( $input['parent'] ) ) {
						$parent_id = absint( $input['parent'] );

						if ( $parent_id === (int) $page->ID ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'A page cannot be made its own parent.', 'shim-mcp' ),
							);
						}

						if ( $parent_id > 0 ) {
							$parent = get_post( $parent_id );

							if ( ! $parent instanceof \WP_Post || 'page' !== $parent->post_type ) {
								return array(
									'success' => false,
									'message' => esc_html__( 'The parent identifier does not point at an existing page.', 'shim-mcp' ),
								);
							}

							if ( in_array( (int) $page->ID, array_map( 'intval', get_post_ancestors( $parent_id ) ), true ) ) {
								return array(
									'success' => false,
									'message' => esc_html__( 'That move would place the page inside one of its own descendants.', 'shim-mcp' ),
								);
							}
						}

						$postarr['post_parent'] = $parent_id;
						$changed[]              = 'parent';
					}

					if ( isset( $input['author'] ) && is_numeric( $input['author'] ) ) {
						$author_id = absint( $input['author'] );

						if ( ! get_userdata( $author_id ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'No user carries the author identifier that was supplied.', 'shim-mcp' ),
							);
						}

						$postarr['post_author'] = $author_id;
						$changed[]              = 'author';
					}

					$template     = '';
					$has_template = isset( $input['template'] ) && is_string( $input['template'] );

					if ( $has_template ) {
						$template = sanitize_text_field( $input['template'] );

						if ( '' !== $template && 'default' !== $template ) {
							$known = wp_get_theme()->get_page_templates( $page, 'page' );

							if ( ! isset( $known[ $template ] ) ) {
								return array(
									'success' => false,
									'message' => esc_html__( 'The active theme has no page template with that file name.', 'shim-mcp' ),
								);
							}
						}
					}

					if ( count( $postarr ) < 2 && ! $has_template ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Nothing was supplied to change on this page.', 'shim-mcp' ),
						);
					}

					if ( count( $postarr ) > 1 ) {
						$saved = wp_update_post( $postarr, true );

						if ( is_wp_error( $saved ) ) {
							return array(
								'success' => false,
								'message' => esc_html( $saved->get_error_message() ),
							);
						}
					}

					if ( $has_template ) {
						if ( '' === $template || 'default' === $template ) {
							delete_post_meta( $page->ID, '_wp_page_template' );
						} else {
							update_post_meta( $page->ID, '_wp_page_template', $template );
						}

						$changed[] = 'template';
					}

					$fresh     = get_post( $page->ID );
					$permalink = get_permalink( $page->ID );

					return array(
						'success' => true,
						'message' => esc_html__( 'The page was updated.', 'shim-mcp' ),
						'id'      => (int) $page->ID,
						'title'   => $fresh instanceof \WP_Post ? esc_html( get_the_title( $fresh ) ) : '',
						'slug'    => $fresh instanceof \WP_Post ? esc_html( $fresh->post_name ) : '',
						'status'  => $fresh instanceof \WP_Post ? esc_html( $fresh->post_status ) : '',
						'url'     => is_string( $permalink ) ? esc_url_raw( $permalink ) : '',
						'changed' => $changed,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_pages' );
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
			'shim-mcp/pages-delete',
			array(
				'label'               => 'Delete A Page',
				'description'         => 'Moves a page to the trash so it can be restored later, or erases it outright when permanent removal is asked for. A page that still has child pages is refused until the caller confirms the children may be left behind.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id'              => array(
							'type'        => 'integer',
							'description' => 'Numeric identifier of the page to remove.',
						),
						'permanent'       => array(
							'type'        => 'boolean',
							'description' => 'Set this to true to erase the page straight away instead of trashing it. There is no way back from that.',
						),
						'orphan_children' => array(
							'type'        => 'boolean',
							'description' => 'Set this to true to confirm that any child pages may be left behind. Without it a page that has children is kept.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'   => array( 'type' => 'boolean' ),
						'message'   => array( 'type' => 'string' ),
						'id'        => array( 'type' => 'integer' ),
						'title'     => array( 'type' => 'string' ),
						'permanent' => array( 'type' => 'boolean' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['id'] ) || ! is_numeric( $input['id'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'A numeric page identifier is required.', 'shim-mcp' ),
						);
					}

					$page = get_post( absint( $input['id'] ) );

					if ( ! $page instanceof \WP_Post ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Nothing on this site carries that identifier.', 'shim-mcp' ),
						);
					}

					if ( 'page' !== $page->post_type ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'That identifier belongs to another post type, not a page.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'delete_post', $page->ID ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You are not allowed to delete this page.', 'shim-mcp' ),
						);
					}

					$permanent = isset( $input['permanent'] ) && true === $input['permanent'];

					if ( ! isset( $input['orphan_children'] ) || true !== $input['orphan_children'] ) {
						$children = get_children(
							array(
								'post_parent' => $page->ID,
								'post_type'   => 'page',
								'post_status' => 'any',
								'numberposts' => 1,
								'fields'      => 'ids',
							)
						);

						if ( ! empty( $children ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'This page has child pages. Confirm with orphan_children to remove it anyway.', 'shim-mcp' ),
							);
						}
					}

					$title = esc_html( get_the_title( $page ) );

					$outcome = $permanent ? wp_delete_post( $page->ID, true ) : wp_trash_post( $page->ID );

					if ( ! $outcome ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'WordPress refused to remove this page.', 'shim-mcp' ),
						);
					}

					return array(
						'success'   => true,
						'message'   => $permanent
							? esc_html__( 'The page was permanently deleted.', 'shim-mcp' )
							: esc_html__( 'The page was moved to the trash.', 'shim-mcp' ),
						'id'        => (int) $page->ID,
						'title'     => $title,
						'permanent' => $permanent,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'delete_pages' );
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
			'shim-mcp/pages-replace-text',
			array(
				'label'               => 'Replace Text In A Page',
				'description'         => 'Finds every occurrence of a string inside one page body and swaps it for another. Matching can be literal or by regular expression, can ignore letter case, and can be previewed without saving anything.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id', 'search', 'replace' ),
					'properties'           => array(
						'id'          => array(
							'type'        => 'integer',
							'description' => 'Numeric identifier of the page whose body should be rewritten.',
						),
						'search'      => array(
							'type'        => 'string',
							'description' => 'Text to look for. With regular expression matching turned on this is read as a pattern written without delimiters.',
						),
						'replace'     => array(
							'type'        => 'string',
							'description' => 'Text written in place of each match. An empty string simply removes the matches, and backreferences such as $1 work while regular expression matching is on.',
						),
						'regex'       => array(
							'type'        => 'boolean',
							'description' => 'Set this to true to read the search value as a regular expression instead of literal text.',
						),
						'ignore_case' => array(
							'type'        => 'boolean',
							'description' => 'Set this to true to match regardless of upper or lower case.',
						),
						'dry_run'     => array(
							'type'        => 'boolean',
							'description' => 'Set this to true to count the matches and return the rewritten body without storing anything.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'      => array( 'type' => 'boolean' ),
						'message'      => array( 'type' => 'string' ),
						'id'           => array( 'type' => 'integer' ),
						'replacements' => array( 'type' => 'integer' ),
						'saved'        => array( 'type' => 'boolean' ),
						'content'      => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['id'] ) || ! is_numeric( $input['id'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'A numeric page identifier is required.', 'shim-mcp' ),
						);
					}

					if ( ! isset( $input['search'] ) || ! is_string( $input['search'] ) || '' === $input['search'] ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Supply the text to search for.', 'shim-mcp' ),
						);
					}

					if ( ! isset( $input['replace'] ) || ! is_string( $input['replace'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Supply the replacement text, using an empty string to strip the matches out.', 'shim-mcp' ),
						);
					}

					$page = get_post( absint( $input['id'] ) );

					if ( ! $page instanceof \WP_Post ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Nothing on this site carries that identifier.', 'shim-mcp' ),
						);
					}

					if ( 'page' !== $page->post_type ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'That identifier belongs to another post type, not a page.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'edit_post', $page->ID ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You are not allowed to edit this page.', 'shim-mcp' ),
						);
					}

					$search      = $input['search'];
					$replace     = $input['replace'];
					$ignore_case = isset( $input['ignore_case'] ) && true === $input['ignore_case'];
					$original    = (string) $page->post_content;
					$total       = 0;

					if ( isset( $input['regex'] ) && true === $input['regex'] ) {
						$delimiter = '';

						foreach ( array( '~', '#', '%', '!', '@', '|' ) as $candidate ) {
							if ( false === strpos( $search, $candidate ) ) {
								$delimiter = $candidate;
								break;
							}
						}

						if ( '' === $delimiter ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The pattern already uses every character available as a delimiter. Rewrite it without one of ~ # % ! @ or the pipe.', 'shim-mcp' ),
							);
						}

						$pattern = $delimiter . $search . $delimiter . ( $ignore_case ? 'i' : '' );

						if ( false === @preg_match( $pattern, '' ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'That regular expression does not compile, so nothing was run against the page.', 'shim-mcp' ),
							);
						}

						$updated = preg_replace( $pattern, $replace, $original, -1, $total );

						if ( ! is_string( $updated ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The regular expression failed while running against this page.', 'shim-mcp' ),
							);
						}
					} elseif ( $ignore_case ) {
						$updated = str_ireplace( $search, $replace, $original, $total );
					} else {
						$updated = str_replace( $search, $replace, $original, $total );
					}

					$total = (int) $total;

					if ( 0 === $total ) {
						return array(
							'success'      => true,
							'message'      => esc_html__( 'Nothing matched, so the page was left untouched.', 'shim-mcp' ),
							'id'           => (int) $page->ID,
							'replacements' => 0,
							'saved'        => false,
						);
					}

					if ( ! current_user_can( 'unfiltered_html' ) ) {
						$updated = wp_kses_post( (string) $updated );
					}

					if ( isset( $input['dry_run'] ) && true === $input['dry_run'] ) {
						return array(
							'success'      => true,
							'message'      => esc_html__( 'Preview only, nothing was written to the database.', 'shim-mcp' ),
							'id'           => (int) $page->ID,
							'replacements' => $total,
							'saved'        => false,
							'content'      => (string) $updated,
						);
					}

					$saved = wp_update_post(
						array(
							'ID'           => $page->ID,
							'post_content' => (string) $updated,
						),
						true
					);

					if ( is_wp_error( $saved ) ) {
						return array(
							'success' => false,
							'message' => esc_html( $saved->get_error_message() ),
						);
					}

					return array(
						'success'      => true,
						'message'      => esc_html__( 'The page body was rewritten.', 'shim-mcp' ),
						'id'           => (int) $page->ID,
						'replacements' => $total,
						'saved'        => true,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_pages' );
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
	}
}
