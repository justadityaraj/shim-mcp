<?php
declare(strict_types=1);

namespace ShimMcp\Abilities\Content;

class Revisions {

	public static function register(): void {
		wp_register_ability(
			'shim-mcp/revisions-list',
			array(
				'label'               => 'List Post Revisions',
				'description'         => 'Returns the saved revisions of a single post or page, most recent first. Give it the numeric post ID; each entry comes back with the revision ID, who saved it, and when.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'post_id' ),
					'properties'           => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'Numeric ID of the parent post whose revision history you want.',
						),
						'limit'   => array(
							'type'        => 'integer',
							'description' => 'How many revisions to return, from 1 to 100. Defaults to 20.',
							'minimum'     => 1,
							'maximum'     => 100,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'   => array( 'type' => 'boolean' ),
						'message'   => array( 'type' => 'string' ),
						'post_id'   => array( 'type' => 'integer' ),
						'count'     => array( 'type' => 'integer' ),
						'revisions' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'            => array( 'type' => 'integer' ),
									'title'         => array( 'type' => 'string' ),
									'author_id'     => array( 'type' => 'integer' ),
									'author_name'   => array( 'type' => 'string' ),
									'modified'      => array( 'type' => 'string' ),
									'modified_gmt'  => array( 'type' => 'string' ),
									'is_autosave'   => array( 'type' => 'boolean' ),
								),
							),
						),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) ) {
						$input = array();
					}

					$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;

					if ( $post_id < 1 ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Supply a positive numeric post ID.', 'shim-mcp' ),
						);
					}

					$parent = get_post( $post_id );

					if ( ! $parent instanceof \WP_Post ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'No post exists with that ID.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'edit_post', $parent->ID ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Your account cannot edit this post, so its revisions are unavailable.', 'shim-mcp' ),
						);
					}

					$limit = isset( $input['limit'] ) ? absint( $input['limit'] ) : 20;

					if ( $limit < 1 ) {
						$limit = 20;
					}

					if ( $limit > 100 ) {
						$limit = 100;
					}

					$revisions = wp_get_post_revisions(
						$parent->ID,
						array(
							'orderby'        => 'date',
							'order'          => 'DESC',
							'posts_per_page' => $limit,
						)
					);

					$rows = array();

					foreach ( $revisions as $revision ) {
						$author_id = (int) $revision->post_author;
						$author    = $author_id > 0 ? get_userdata( $author_id ) : false;

						$rows[] = array(
							'id'           => (int) $revision->ID,
							'title'        => esc_html( get_the_title( $revision->ID ) ),
							'author_id'    => $author_id,
							'author_name'  => $author ? esc_html( $author->display_name ) : '',
							'modified'     => (string) $revision->post_modified,
							'modified_gmt' => (string) $revision->post_modified_gmt,
							'is_autosave'  => (bool) wp_is_post_autosave( $revision->ID ),
						);
					}

					return array(
						'success'   => true,
						'post_id'   => (int) $parent->ID,
						'count'     => count( $rows ),
						'revisions' => $rows,
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
			'shim-mcp/revisions-restore',
			array(
				'label'               => 'Restore Post Revision',
				'description'         => 'Rolls a post back to one of its stored revisions. Give it the revision ID; the post it belongs to is overwritten with that revision content, and the version being replaced is itself saved as a new revision first.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'revision_id' ),
					'properties'           => array(
						'revision_id' => array(
							'type'        => 'integer',
							'description' => 'Numeric ID of the revision to roll the post back to.',
						),
						'post_id'     => array(
							'type'        => 'integer',
							'description' => 'Optional numeric ID of the parent post. When present it must match the revision parent, which guards against restoring onto the wrong post.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'     => array( 'type' => 'boolean' ),
						'message'     => array( 'type' => 'string' ),
						'post_id'     => array( 'type' => 'integer' ),
						'revision_id' => array( 'type' => 'integer' ),
						'title'       => array( 'type' => 'string' ),
						'modified'    => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) ) {
						$input = array();
					}

					$revision_id = isset( $input['revision_id'] ) ? absint( $input['revision_id'] ) : 0;

					if ( $revision_id < 1 ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Supply a positive numeric revision ID.', 'shim-mcp' ),
						);
					}

					$revision = wp_get_post_revision( $revision_id );

					if ( ! $revision instanceof \WP_Post ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'That ID does not belong to a stored revision.', 'shim-mcp' ),
						);
					}

					$parent_id = (int) $revision->post_parent;
					$parent    = $parent_id > 0 ? get_post( $parent_id ) : null;

					if ( ! $parent instanceof \WP_Post ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The post this revision belongs to no longer exists.', 'shim-mcp' ),
						);
					}

					if ( isset( $input['post_id'] ) ) {
						$expected_parent = absint( $input['post_id'] );

						if ( $expected_parent !== $parent_id ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'This revision belongs to a different post than the one you named.', 'shim-mcp' ),
							);
						}
					}

					if ( ! current_user_can( 'edit_post', $parent->ID ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Your account cannot edit this post, so it cannot be rolled back.', 'shim-mcp' ),
						);
					}

					$restored = wp_restore_post_revision( $revision->ID );

					if ( is_wp_error( $restored ) ) {
						return array(
							'success' => false,
							'message' => esc_html( $restored->get_error_message() ),
						);
					}

					if ( ! $restored ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'WordPress did not apply the rollback. The revision may already match the current post.', 'shim-mcp' ),
						);
					}

					$updated = get_post( $parent->ID );

					return array(
						'success'     => true,
						'post_id'     => (int) $parent->ID,
						'revision_id' => (int) $revision->ID,
						'title'       => $updated instanceof \WP_Post ? esc_html( get_the_title( $updated->ID ) ) : '',
						'modified'    => $updated instanceof \WP_Post ? (string) $updated->post_modified : '',
						'message'     => esc_html__( 'The post now matches the selected revision.', 'shim-mcp' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					),
				),
			)
		);
	}
}
