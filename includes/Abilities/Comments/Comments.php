<?php
declare(strict_types=1);

namespace ShimMcp\Abilities\Comments;

class Comments {

	public static function register(): void {
		wp_register_ability(
			'shim-mcp/comments-list',
			array(
				'label'               => 'Browse Comments',
				'description'         => 'Returns a page of comments. Narrow the results by approval state, by the post they belong to, by the author email address, or by a phrase to look for in the comment body.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array(),
					'properties'           => array(
						'status'       => array(
							'type'        => 'string',
							'enum'        => array( 'all', 'approve', 'hold', 'spam', 'trash' ),
							'description' => 'Which moderation bucket to read from. Defaults to all buckets except spam and trash.',
						),
						'post_id'      => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'Only return comments attached to this post.',
						),
						'author_email' => array(
							'type'        => 'string',
							'description' => 'Only return comments left by this email address.',
						),
						'search'       => array(
							'type'        => 'string',
							'description' => 'Free text to look for inside the comment content and author fields.',
						),
						'per_page'     => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 100,
							'description' => 'How many comments to return in one page. Caps at one hundred.',
						),
						'page'         => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'Which page of results to return, counting from one.',
						),
						'order'        => array(
							'type'        => 'string',
							'enum'        => array( 'ASC', 'DESC' ),
							'description' => 'Sort direction by submission date. Newest first by default.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'  => array( 'type' => 'boolean' ),
						'comments' => array( 'type' => 'array' ),
						'total'    => array( 'type' => 'integer' ),
						'page'     => array( 'type' => 'integer' ),
						'per_page' => array( 'type' => 'integer' ),
						'message'  => array( 'type' => 'string' ),
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

					$args = array(
						'number'  => $per_page,
						'offset'  => ( $page - 1 ) * $per_page,
						'orderby' => 'comment_date_gmt',
						'order'   => 'DESC',
					);

					if ( isset( $input['order'] ) && is_string( $input['order'] ) && 'ASC' === strtoupper( $input['order'] ) ) {
						$args['order'] = 'ASC';
					}

					$status = isset( $input['status'] ) && is_string( $input['status'] ) ? sanitize_key( $input['status'] ) : 'all';
					if ( ! in_array( $status, array( 'all', 'approve', 'hold', 'spam', 'trash' ), true ) ) {
						$status = 'all';
					}
					$args['status'] = $status;

					if ( isset( $input['post_id'] ) ) {
						$post_id = absint( $input['post_id'] );
						if ( $post_id > 0 ) {
							$args['post_id'] = $post_id;
						}
					}

					if ( isset( $input['author_email'] ) && is_string( $input['author_email'] ) ) {
						$email = sanitize_email( $input['author_email'] );
						if ( '' !== $email && is_email( $email ) ) {
							$args['author_email'] = $email;
						}
					}

					if ( isset( $input['search'] ) && is_string( $input['search'] ) ) {
						$search = sanitize_text_field( $input['search'] );
						if ( '' !== $search ) {
							$args['search'] = $search;
						}
					}

					$found = get_comments( array_merge( $args, array( 'count' => true, 'number' => 0, 'offset' => 0, 'orderby' => '' ) ) );

					$comments = get_comments( $args );
					$rows     = array();

					foreach ( $comments as $comment ) {
						$rows[] = self::shape( $comment );
					}

					return array(
						'success'  => true,
						'comments' => $rows,
						'total'    => (int) $found,
						'page'     => $page,
						'per_page' => $per_page,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'moderate_comments' );
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
			'shim-mcp/comments-get',
			array(
				'label'               => 'Read One Comment',
				'description'         => 'Loads a single comment by its numeric identifier and returns its author details, body, moderation state and parent comment.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'The identifier of the comment to load.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'comment' => array( 'type' => 'object' ),
						'message' => array( 'type' => 'string' ),
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
							'message' => esc_html__( 'Provide a positive comment identifier.', 'shim-mcp' ),
						);
					}

					$comment = get_comment( $id );
					if ( ! $comment ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'No comment exists with that identifier.', 'shim-mcp' ),
						);
					}

					$post_id = (int) $comment->comment_post_ID;
					if ( $post_id > 0 && ! current_user_can( 'read_post', $post_id ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You are not allowed to read the post this comment belongs to.', 'shim-mcp' ),
						);
					}

					return array(
						'success' => true,
						'comment' => self::shape( $comment ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'moderate_comments' );
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
			'shim-mcp/comments-create',
			array(
				'label'               => 'Post a Comment',
				'description'         => 'Adds a comment to a post. Supply the post identifier and the body text, plus an author name and email when the comment is not attributed to a logged in account. Set a parent comment identifier to file it as a reply.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'post_id', 'content' ),
					'properties'           => array(
						'post_id'      => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'The post that should receive the comment.',
						),
						'content'      => array(
							'type'        => 'string',
							'minLength'   => 1,
							'description' => 'The body of the comment.',
						),
						'parent_id'    => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => 'Identifier of the comment being replied to. Leave out or pass zero for a top level comment.',
						),
						'author_name'  => array(
							'type'        => 'string',
							'description' => 'Display name shown beside the comment.',
						),
						'author_email' => array(
							'type'        => 'string',
							'description' => 'Email address of the comment author.',
						),
						'author_url'   => array(
							'type'        => 'string',
							'description' => 'Website address to link the author name to.',
						),
						'user_id'      => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => 'Attribute the comment to this registered account. Doing so for anyone other than yourself requires permission to edit that account.',
						),
						'status'       => array(
							'type'        => 'string',
							'enum'        => array( 'approve', 'hold' ),
							'description' => 'Whether the new comment goes live immediately or waits in the moderation queue.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'comment' => array( 'type' => 'object' ),
						'message' => array( 'type' => 'string' ),
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
							'message' => esc_html__( 'Provide the identifier of the post to comment on.', 'shim-mcp' ),
						);
					}

					$post = get_post( $post_id );
					if ( ! $post ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'No post exists with that identifier.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'read_post', $post_id ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You are not allowed to read that post.', 'shim-mcp' ),
						);
					}

					if ( ! isset( $input['content'] ) || ! is_string( $input['content'] ) || '' === trim( $input['content'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The comment body cannot be empty.', 'shim-mcp' ),
						);
					}

					$parent_id = isset( $input['parent_id'] ) ? absint( $input['parent_id'] ) : 0;
					if ( $parent_id > 0 ) {
						$parent = get_comment( $parent_id );
						if ( ! $parent ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The comment being replied to does not exist.', 'shim-mcp' ),
							);
						}
						if ( (int) $parent->comment_post_ID !== $post_id ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The comment being replied to belongs to a different post.', 'shim-mcp' ),
							);
						}
					}

					$current_user_id = get_current_user_id();
					$user_id         = isset( $input['user_id'] ) ? absint( $input['user_id'] ) : 0;

					if ( $user_id > 0 && $user_id !== $current_user_id ) {
						if ( ! current_user_can( 'edit_user', $user_id ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'You are not allowed to attribute a comment to that account.', 'shim-mcp' ),
							);
						}
					}

					$author_name  = '';
					$author_email = '';
					$author_url   = '';

					if ( $user_id > 0 ) {
						$user = get_userdata( $user_id );
						if ( ! $user ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'No account exists with that identifier.', 'shim-mcp' ),
							);
						}
						$author_name  = $user->display_name;
						$author_email = $user->user_email;
						$author_url   = $user->user_url;
					}

					if ( isset( $input['author_name'] ) && is_string( $input['author_name'] ) ) {
						$supplied = sanitize_text_field( $input['author_name'] );
						if ( '' !== $supplied ) {
							$author_name = $supplied;
						}
					}

					if ( isset( $input['author_email'] ) && is_string( $input['author_email'] ) ) {
						$supplied = sanitize_email( $input['author_email'] );
						if ( '' !== $supplied ) {
							if ( ! is_email( $supplied ) ) {
								return array(
									'success' => false,
									'message' => esc_html__( 'The author email address is not valid.', 'shim-mcp' ),
								);
							}
							$author_email = $supplied;
						}
					}

					if ( isset( $input['author_url'] ) && is_string( $input['author_url'] ) ) {
						$author_url = esc_url_raw( $input['author_url'] );
					}

					if ( '' === $author_name ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'An author name is required when the comment is not tied to an account.', 'shim-mcp' ),
						);
					}

					$approved = 1;
					if ( isset( $input['status'] ) && is_string( $input['status'] ) && 'hold' === sanitize_key( $input['status'] ) ) {
						$approved = 0;
					}

					$comment_id = wp_insert_comment(
						array(
							'comment_post_ID'      => $post_id,
							'comment_parent'       => $parent_id,
							'comment_content'      => wp_kses_post( $input['content'] ),
							'comment_author'       => $author_name,
							'comment_author_email' => $author_email,
							'comment_author_url'   => $author_url,
							'user_id'              => $user_id,
							'comment_approved'     => $approved,
							'comment_type'         => 'comment',
						)
					);

					if ( ! $comment_id ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The comment could not be saved.', 'shim-mcp' ),
						);
					}

					$comment = get_comment( $comment_id );

					return array(
						'success' => true,
						'comment' => $comment ? self::shape( $comment ) : array( 'id' => (int) $comment_id ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'moderate_comments' );
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
			'shim-mcp/comments-set-status',
			array(
				'label'               => 'Moderate a Comment',
				'description'         => 'Moves a comment between moderation states so it can be published, sent back to the queue, flagged as spam, or thrown in the trash.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id', 'status' ),
					'properties'           => array(
						'id'     => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'The comment to moderate.',
						),
						'status' => array(
							'type'        => 'string',
							'enum'        => array( 'approve', 'hold', 'spam', 'trash' ),
							'description' => 'The state the comment should end up in.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'comment' => array( 'type' => 'object' ),
						'message' => array( 'type' => 'string' ),
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
							'message' => esc_html__( 'Provide a positive comment identifier.', 'shim-mcp' ),
						);
					}

					$status = isset( $input['status'] ) && is_string( $input['status'] ) ? sanitize_key( $input['status'] ) : '';
					if ( ! in_array( $status, array( 'approve', 'hold', 'spam', 'trash' ), true ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'That moderation state is not one this ability understands.', 'shim-mcp' ),
						);
					}

					$comment = get_comment( $id );
					if ( ! $comment ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'No comment exists with that identifier.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'edit_comment', $id ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You are not allowed to moderate this comment.', 'shim-mcp' ),
						);
					}

					$changed = wp_set_comment_status( $id, $status, true );
					if ( is_wp_error( $changed ) || false === $changed ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The moderation state could not be changed.', 'shim-mcp' ),
						);
					}

					clean_comment_cache( $id );
					$updated = get_comment( $id );

					return array(
						'success' => true,
						'comment' => $updated ? self::shape( $updated ) : array( 'id' => $id ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'moderate_comments' );
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
			'shim-mcp/comments-update',
			array(
				'label'               => 'Rewrite Comment Text',
				'description'         => 'Replaces the body of an existing comment, and optionally the author name, email or website shown alongside it.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id'           => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'The comment to edit.',
						),
						'content'      => array(
							'type'        => 'string',
							'minLength'   => 1,
							'description' => 'The replacement body text.',
						),
						'author_name'  => array(
							'type'        => 'string',
							'description' => 'A new display name for the author.',
						),
						'author_email' => array(
							'type'        => 'string',
							'description' => 'A new email address for the author.',
						),
						'author_url'   => array(
							'type'        => 'string',
							'description' => 'A new website address for the author.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'comment' => array( 'type' => 'object' ),
						'message' => array( 'type' => 'string' ),
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
							'message' => esc_html__( 'Provide a positive comment identifier.', 'shim-mcp' ),
						);
					}

					$comment = get_comment( $id );
					if ( ! $comment ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'No comment exists with that identifier.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'edit_comment', $id ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You are not allowed to edit this comment.', 'shim-mcp' ),
						);
					}

					$fields = array( 'comment_ID' => $id );

					if ( isset( $input['content'] ) ) {
						if ( ! is_string( $input['content'] ) || '' === trim( $input['content'] ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The replacement body cannot be empty.', 'shim-mcp' ),
							);
						}
						$fields['comment_content'] = wp_kses_post( $input['content'] );
					}

					if ( isset( $input['author_name'] ) && is_string( $input['author_name'] ) ) {
						$name = sanitize_text_field( $input['author_name'] );
						if ( '' === $name ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The author name cannot be blank.', 'shim-mcp' ),
							);
						}
						$fields['comment_author'] = $name;
					}

					if ( isset( $input['author_email'] ) && is_string( $input['author_email'] ) ) {
						$email = sanitize_email( $input['author_email'] );
						if ( '' !== $email && ! is_email( $email ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The author email address is not valid.', 'shim-mcp' ),
							);
						}
						$fields['comment_author_email'] = $email;
					}

					if ( isset( $input['author_url'] ) && is_string( $input['author_url'] ) ) {
						$fields['comment_author_url'] = esc_url_raw( $input['author_url'] );
					}

					if ( 1 === count( $fields ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Supply at least one field to change.', 'shim-mcp' ),
						);
					}

					$updated = wp_update_comment( $fields, true );
					if ( is_wp_error( $updated ) ) {
						return array(
							'success' => false,
							'message' => esc_html( $updated->get_error_message() ),
						);
					}

					if ( 0 === $updated ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The comment could not be saved.', 'shim-mcp' ),
						);
					}

					$fresh = get_comment( $id );

					return array(
						'success' => true,
						'comment' => $fresh ? self::shape( $fresh ) : array( 'id' => $id ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'moderate_comments' );
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
			'shim-mcp/comments-delete',
			array(
				'label'               => 'Delete a Comment',
				'description'         => 'Removes a comment. By default it goes to the trash and can be restored; pass the permanent flag to erase it from the database instead.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id'        => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'The comment to remove.',
						),
						'permanent' => array(
							'type'        => 'boolean',
							'description' => 'Set to true to erase the comment outright rather than trashing it.',
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
					if ( ! is_array( $input ) ) {
						$input = array();
					}

					$id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
					if ( $id < 1 ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Provide a positive comment identifier.', 'shim-mcp' ),
						);
					}

					$comment = get_comment( $id );
					if ( ! $comment ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'No comment exists with that identifier.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'edit_comment', $id ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You are not allowed to remove this comment.', 'shim-mcp' ),
						);
					}

					$permanent = ! empty( $input['permanent'] ) && true === filter_var( $input['permanent'], FILTER_VALIDATE_BOOLEAN );

					$removed = wp_delete_comment( $id, $permanent );
					if ( ! $removed ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The comment could not be removed.', 'shim-mcp' ),
						);
					}

					return array(
						'success'   => true,
						'id'        => $id,
						'permanent' => $permanent,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'moderate_comments' );
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

	private static function shape( $comment ): array {
		return array(
			'id'           => (int) $comment->comment_ID,
			'post_id'      => (int) $comment->comment_post_ID,
			'parent_id'    => (int) $comment->comment_parent,
			'user_id'      => (int) $comment->user_id,
			'author_name'  => esc_html( $comment->comment_author ),
			'author_email' => esc_html( $comment->comment_author_email ),
			'author_url'   => esc_url_raw( $comment->comment_author_url ),
			'content'      => $comment->comment_content,
			'status'       => esc_html( wp_get_comment_status( $comment->comment_ID ) ),
			'type'         => esc_html( $comment->comment_type ),
			'date_gmt'     => esc_html( $comment->comment_date_gmt ),
			'link'         => esc_url_raw( get_comment_link( $comment ) ),
		);
	}
}
