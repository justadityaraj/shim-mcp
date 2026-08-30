<?php
declare(strict_types=1);

namespace ShimMcp\Abilities\Content;

class Taxonomy {

	public static function register(): void {
		$term_shape = array(
			'id'          => array( 'type' => 'integer' ),
			'name'        => array( 'type' => 'string' ),
			'slug'        => array( 'type' => 'string' ),
			'description' => array( 'type' => 'string' ),
			'parent'      => array( 'type' => 'integer' ),
			'count'       => array( 'type' => 'integer' ),
		);

		$collect = static function ( string $taxonomy, array $input ): array {
			$per_page = isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 50;
			if ( $per_page < 1 ) {
				$per_page = 1;
			}
			if ( $per_page > 200 ) {
				$per_page = 200;
			}

			$page   = isset( $input['page'] ) ? absint( $input['page'] ) : 1;
			$page   = $page > 0 ? $page : 1;
			$hide   = isset( $input['hide_empty'] ) ? (bool) $input['hide_empty'] : false;
			$search = isset( $input['search'] ) && is_string( $input['search'] ) ? sanitize_text_field( $input['search'] ) : '';

			$args = array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => $hide,
				'number'     => $per_page,
				'offset'     => ( $page - 1 ) * $per_page,
				'orderby'    => 'name',
				'order'      => 'ASC',
			);

			if ( '' !== $search ) {
				$args['search'] = $search;
			}

			$terms = get_terms( $args );

			if ( is_wp_error( $terms ) ) {
				return array(
					'success' => false,
					'message' => esc_html( $terms->get_error_message() ),
				);
			}

			$rows = array();

			foreach ( $terms as $term ) {
				$rows[] = array(
					'id'          => (int) $term->term_id,
					'name'        => esc_html( $term->name ),
					'slug'        => $term->slug,
					'description' => esc_html( $term->description ),
					'parent'      => (int) $term->parent,
					'count'       => (int) $term->count,
				);
			}

			$total = wp_count_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => $hide,
				)
			);

			return array(
				'success'  => true,
				'terms'    => $rows,
				'returned' => count( $rows ),
				'total'    => is_wp_error( $total ) ? count( $rows ) : (int) $total,
				'page'     => $page,
			);
		};

		$list_input = array(
			'type'       => 'object',
			'properties' => array(
				'search'     => array(
					'type'        => 'string',
					'description' => 'Restrict the results to terms whose name or slug contains this text.',
				),
				'hide_empty' => array(
					'type'        => 'boolean',
					'description' => 'Set to true to leave out terms that are not attached to any post.',
				),
				'per_page'   => array(
					'type'        => 'integer',
					'description' => 'How many terms to return in one response, from 1 to 200. Defaults to 50.',
				),
				'page'       => array(
					'type'        => 'integer',
					'description' => 'Which page of results to return, counting from 1.',
				),
			),
			'additionalProperties' => false,
		);

		$list_output = array(
			'type'       => 'object',
			'properties' => array(
				'success'  => array( 'type' => 'boolean' ),
				'terms'    => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => $term_shape,
					),
				),
				'returned' => array( 'type' => 'integer' ),
				'total'    => array( 'type' => 'integer' ),
				'page'     => array( 'type' => 'integer' ),
				'message'  => array( 'type' => 'string' ),
			),
		);

		wp_register_ability(
			'shim-mcp/taxonomy-list-categories',
			array(
				'label'               => 'List Categories',
				'description'         => 'Returns the blog categories on this site, each with its numeric id, display name, slug, description, parent id and the number of posts filed under it. Results can be searched and paged through.',
				'category'            => 'site',
				'input_schema'        => $list_input,
				'output_schema'       => $list_output,
				'execute_callback'    => function ( $input = array() ) use ( $collect ): array {
					if ( ! is_array( $input ) ) {
						$input = array();
					}

					return $collect( 'category', $input );
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
			'shim-mcp/taxonomy-list-tags',
			array(
				'label'               => 'List Tags',
				'description'         => 'Returns the post tags on this site with the same fields as the category listing: id, name, slug, description, parent id and post count. Tags are flat, so the parent id is normally zero.',
				'category'            => 'site',
				'input_schema'        => $list_input,
				'output_schema'       => $list_output,
				'execute_callback'    => function ( $input = array() ) use ( $collect ): array {
					if ( ! is_array( $input ) ) {
						$input = array();
					}

					return $collect( 'post_tag', $input );
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
			'shim-mcp/taxonomy-create-term',
			array(
				'label'               => 'Create Category or Tag',
				'description'         => 'Adds a new term to either the category or the post_tag taxonomy. A name is required; slug, description and a parent category may also be supplied.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'taxonomy', 'name' ),
					'properties'           => array(
						'taxonomy'    => array(
							'type'        => 'string',
							'enum'        => array( 'category', 'post_tag' ),
							'description' => 'Which taxonomy the new term belongs to, either category or post_tag.',
						),
						'name'        => array(
							'type'        => 'string',
							'description' => 'The display name of the term as readers will see it.',
						),
						'slug'        => array(
							'type'        => 'string',
							'description' => 'A URL friendly identifier. Leave it out and WordPress derives one from the name.',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'Explanatory text shown on archive pages by themes that support it.',
						),
						'parent'      => array(
							'type'        => 'integer',
							'description' => 'The id of an existing category to nest this term beneath. Only meaningful for categories.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'  => array( 'type' => 'boolean' ),
						'id'       => array( 'type' => 'integer' ),
						'name'     => array( 'type' => 'string' ),
						'slug'     => array( 'type' => 'string' ),
						'taxonomy' => array( 'type' => 'string' ),
						'parent'   => array( 'type' => 'integer' ),
						'message'  => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) ) {
						$input = array();
					}

					$taxonomy = isset( $input['taxonomy'] ) && is_string( $input['taxonomy'] ) ? sanitize_key( $input['taxonomy'] ) : '';

					if ( 'category' !== $taxonomy && 'post_tag' !== $taxonomy ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The taxonomy must be either category or post_tag.', 'shim-mcp' ),
						);
					}

					$name = isset( $input['name'] ) && is_string( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';

					if ( '' === $name ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'A term name is required and cannot be blank.', 'shim-mcp' ),
						);
					}

					$args = array();

					if ( isset( $input['slug'] ) && is_string( $input['slug'] ) && '' !== $input['slug'] ) {
						$args['slug'] = sanitize_title( $input['slug'] );
					}

					if ( isset( $input['description'] ) && is_string( $input['description'] ) ) {
						$args['description'] = wp_kses_post( $input['description'] );
					}

					if ( isset( $input['parent'] ) ) {
						$parent = absint( $input['parent'] );

						if ( $parent > 0 ) {
							if ( 'category' !== $taxonomy ) {
								return array(
									'success' => false,
									'message' => esc_html__( 'Tags cannot be nested, so a parent may only be given for categories.', 'shim-mcp' ),
								);
							}

							$parent_term = get_term( $parent, $taxonomy );

							if ( ! $parent_term instanceof \WP_Term ) {
								return array(
									'success' => false,
									'message' => esc_html__( 'No category exists with the parent id you supplied.', 'shim-mcp' ),
								);
							}

							$args['parent'] = (int) $parent_term->term_id;
						}
					}

					$created = wp_insert_term( $name, $taxonomy, $args );

					if ( is_wp_error( $created ) ) {
						return array(
							'success' => false,
							'message' => esc_html( $created->get_error_message() ),
						);
					}

					$term = get_term( (int) $created['term_id'], $taxonomy );

					if ( ! $term instanceof \WP_Term ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The term was saved but could not be read back.', 'shim-mcp' ),
						);
					}

					return array(
						'success'  => true,
						'id'       => (int) $term->term_id,
						'name'     => esc_html( $term->name ),
						'slug'     => $term->slug,
						'taxonomy' => $term->taxonomy,
						'parent'   => (int) $term->parent,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'manage_categories' );
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
			'shim-mcp/taxonomy-update-term',
			array(
				'label'               => 'Update Category or Tag',
				'description'         => 'Changes the name, slug, description or parent of an existing category or tag. Identify the term by its numeric id and send only the fields you want changed.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id'          => array(
							'type'        => 'integer',
							'description' => 'The numeric id of the term to modify.',
						),
						'taxonomy'    => array(
							'type'        => 'string',
							'enum'        => array( 'category', 'post_tag' ),
							'description' => 'Optionally state which taxonomy the term belongs to so the id is resolved unambiguously.',
						),
						'name'        => array(
							'type'        => 'string',
							'description' => 'A replacement display name for the term.',
						),
						'slug'        => array(
							'type'        => 'string',
							'description' => 'A replacement URL friendly identifier.',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'Replacement descriptive text for the term archive.',
						),
						'parent'      => array(
							'type'        => 'integer',
							'description' => 'The id of the category that should become the parent, or zero to move the term to the top level.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'  => array( 'type' => 'boolean' ),
						'id'       => array( 'type' => 'integer' ),
						'name'     => array( 'type' => 'string' ),
						'slug'     => array( 'type' => 'string' ),
						'taxonomy' => array( 'type' => 'string' ),
						'parent'   => array( 'type' => 'integer' ),
						'updated'  => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'message'  => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) ) {
						$input = array();
					}

					$id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;

					if ( $id < 1 ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'A positive term id is required.', 'shim-mcp' ),
						);
					}

					$taxonomy = isset( $input['taxonomy'] ) && is_string( $input['taxonomy'] ) ? sanitize_key( $input['taxonomy'] ) : '';

					if ( '' !== $taxonomy && 'category' !== $taxonomy && 'post_tag' !== $taxonomy ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Only the category and post_tag taxonomies can be edited here.', 'shim-mcp' ),
						);
					}

					$term = '' === $taxonomy ? get_term( $id ) : get_term( $id, $taxonomy );

					if ( ! $term instanceof \WP_Term ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'No term was found with that id.', 'shim-mcp' ),
						);
					}

					if ( 'category' !== $term->taxonomy && 'post_tag' !== $term->taxonomy ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'That term belongs to a taxonomy this ability does not manage.', 'shim-mcp' ),
						);
					}

					$taxonomy_object = get_taxonomy( $term->taxonomy );

					if ( ! $taxonomy_object || ! current_user_can( $taxonomy_object->cap->edit_terms ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You are not allowed to edit terms in this taxonomy.', 'shim-mcp' ),
						);
					}

					$args    = array();
					$changed = array();

					if ( isset( $input['name'] ) ) {
						if ( ! is_string( $input['name'] ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The name must be given as text.', 'shim-mcp' ),
							);
						}

						$name = sanitize_text_field( $input['name'] );

						if ( '' === $name ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The name cannot be set to an empty value.', 'shim-mcp' ),
							);
						}

						$args['name'] = $name;
						$changed[]    = 'name';
					}

					if ( isset( $input['slug'] ) ) {
						if ( ! is_string( $input['slug'] ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The slug must be given as text.', 'shim-mcp' ),
							);
						}

						$slug = sanitize_title( $input['slug'] );

						if ( '' === $slug ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The slug you supplied contains no usable characters.', 'shim-mcp' ),
							);
						}

						$args['slug'] = $slug;
						$changed[]    = 'slug';
					}

					if ( isset( $input['description'] ) ) {
						if ( ! is_string( $input['description'] ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The description must be given as text.', 'shim-mcp' ),
							);
						}

						$args['description'] = wp_kses_post( $input['description'] );
						$changed[]           = 'description';
					}

					if ( isset( $input['parent'] ) ) {
						$parent = absint( $input['parent'] );

						if ( $parent > 0 ) {
							if ( 'category' !== $term->taxonomy ) {
								return array(
									'success' => false,
									'message' => esc_html__( 'Only categories can be nested under a parent.', 'shim-mcp' ),
								);
							}

							if ( $parent === (int) $term->term_id ) {
								return array(
									'success' => false,
									'message' => esc_html__( 'A category cannot be its own parent.', 'shim-mcp' ),
								);
							}

							$parent_term = get_term( $parent, $term->taxonomy );

							if ( ! $parent_term instanceof \WP_Term ) {
								return array(
									'success' => false,
									'message' => esc_html__( 'No category exists with the parent id you supplied.', 'shim-mcp' ),
								);
							}

							if ( term_is_ancestor_of( (int) $term->term_id, (int) $parent_term->term_id, $term->taxonomy ) ) {
								return array(
									'success' => false,
									'message' => esc_html__( 'That move would place the category inside one of its own descendants.', 'shim-mcp' ),
								);
							}

							$args['parent'] = (int) $parent_term->term_id;
						} else {
							$args['parent'] = 0;
						}

						$changed[] = 'parent';
					}

					if ( empty( $args ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Supply at least one of name, slug, description or parent to change.', 'shim-mcp' ),
						);
					}

					$result = wp_update_term( (int) $term->term_id, $term->taxonomy, $args );

					if ( is_wp_error( $result ) ) {
						return array(
							'success' => false,
							'message' => esc_html( $result->get_error_message() ),
						);
					}

					$fresh = get_term( (int) $result['term_id'], $term->taxonomy );

					if ( ! $fresh instanceof \WP_Term ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The term was updated but could not be read back.', 'shim-mcp' ),
						);
					}

					return array(
						'success'  => true,
						'id'       => (int) $fresh->term_id,
						'name'     => esc_html( $fresh->name ),
						'slug'     => $fresh->slug,
						'taxonomy' => $fresh->taxonomy,
						'parent'   => (int) $fresh->parent,
						'updated'  => $changed,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'manage_categories' );
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
	}
}
