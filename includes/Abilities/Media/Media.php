<?php
declare(strict_types=1);

namespace ShimMcp\Abilities\Media;

class Media {

	public static function register(): void {
		wp_register_ability(
			'shim-mcp/media-list',
			array(
				'label'               => 'List Media Library Items',
				'description'         => 'Returns a page of attachments from the media library, newest first. You can narrow the results to a single MIME type such as image/png or to a whole family such as image.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array(),
					'properties'           => array(
						'page'      => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'default'     => 1,
							'description' => 'Which page of results to return, counting from one.',
						),
						'per_page'  => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 100,
							'default'     => 20,
							'description' => 'How many attachments to include on the page, up to one hundred.',
						),
						'mime_type' => array(
							'type'        => 'string',
							'description' => 'Restrict the listing to attachments of this MIME type or MIME family, for example image/jpeg or audio.',
						),
						'search'    => array(
							'type'        => 'string',
							'description' => 'Free text matched against attachment titles and content.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'     => array( 'type' => 'boolean' ),
						'attachments' => array( 'type' => 'array' ),
						'total'       => array( 'type' => 'integer' ),
						'total_pages' => array( 'type' => 'integer' ),
						'page'        => array( 'type' => 'integer' ),
						'message'     => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => static function ( $input = array() ): array {
					if ( ! is_array( $input ) ) {
						$input = array();
					}

					$page     = isset( $input['page'] ) ? max( 1, absint( $input['page'] ) ) : 1;
					$per_page = isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 20;
					$per_page = min( 100, max( 1, $per_page ) );

					$args = array(
						'post_type'      => 'attachment',
						'post_status'    => 'inherit',
						'posts_per_page' => $per_page,
						'paged'          => $page,
						'orderby'        => 'date',
						'order'          => 'DESC',
					);

					if ( isset( $input['mime_type'] ) && is_string( $input['mime_type'] ) && '' !== trim( $input['mime_type'] ) ) {
						$args['post_mime_type'] = sanitize_text_field( $input['mime_type'] );
					}

					if ( isset( $input['search'] ) && is_string( $input['search'] ) && '' !== trim( $input['search'] ) ) {
						$args['s'] = sanitize_text_field( $input['search'] );
					}

					$query = new \WP_Query( $args );
					$items = array();

					foreach ( $query->posts as $attachment ) {
						if ( ! current_user_can( 'read_post', $attachment->ID ) ) {
							continue;
						}

						$items[] = array(
							'id'        => (int) $attachment->ID,
							'title'     => esc_html( get_the_title( $attachment ) ),
							'slug'      => (string) $attachment->post_name,
							'mime_type' => (string) $attachment->post_mime_type,
							'url'       => (string) wp_get_attachment_url( $attachment->ID ),
							'date'      => (string) $attachment->post_date_gmt,
							'author'    => (int) $attachment->post_author,
							'parent'    => (int) $attachment->post_parent,
						);
					}

					return array(
						'success'     => true,
						'attachments' => $items,
						'total'       => (int) $query->found_posts,
						'total_pages' => (int) $query->max_num_pages,
						'page'        => $page,
					);
				},
				'permission_callback' => static function (): bool {
					return current_user_can( 'upload_files' );
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
			'shim-mcp/media-get',
			array(
				'label'               => 'Read One Media Item',
				'description'         => 'Loads a single attachment by its numeric ID and reports its file URL, MIME type, pixel dimensions where the file is an image, alternative text, caption and description.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'The attachment post ID to load.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'    => array( 'type' => 'boolean' ),
						'attachment' => array( 'type' => 'object' ),
						'message'    => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => static function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['id'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'An attachment ID is required.', 'shim-mcp' ),
						);
					}

					$id = absint( $input['id'] );

					if ( $id < 1 ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The attachment ID must be a positive whole number.', 'shim-mcp' ),
						);
					}

					$attachment = get_post( $id );

					if ( ! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'No attachment exists with that ID.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'read_post', $id ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You are not allowed to view this attachment.', 'shim-mcp' ),
						);
					}

					$metadata = wp_get_attachment_metadata( $id );
					$width    = null;
					$height   = null;

					if ( is_array( $metadata ) ) {
						$width  = isset( $metadata['width'] ) ? (int) $metadata['width'] : null;
						$height = isset( $metadata['height'] ) ? (int) $metadata['height'] : null;
					}

					$alt = get_post_meta( $id, '_wp_attachment_image_alt', true );

					return array(
						'success'    => true,
						'attachment' => array(
							'id'          => $id,
							'title'       => esc_html( get_the_title( $attachment ) ),
							'slug'        => (string) $attachment->post_name,
							'url'         => (string) wp_get_attachment_url( $id ),
							'mime_type'   => (string) $attachment->post_mime_type,
							'width'       => $width,
							'height'      => $height,
							'alt_text'    => is_string( $alt ) ? esc_html( $alt ) : '',
							'caption'     => esc_html( (string) $attachment->post_excerpt ),
							'description' => esc_html( (string) $attachment->post_content ),
							'file_path'   => (string) get_attached_file( $id ),
							'date'        => (string) $attachment->post_date_gmt,
							'author'      => (int) $attachment->post_author,
							'parent'      => (int) $attachment->post_parent,
						),
					);
				},
				'permission_callback' => static function (): bool {
					return current_user_can( 'upload_files' );
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
			'shim-mcp/media-upload',
			array(
				'label'               => 'Add a File to the Media Library',
				'description'         => 'Decodes base64 file bytes, writes them into the WordPress uploads folder under the filename you give, creates the attachment record and builds its thumbnails and metadata. Files whose extension and contents are not allowed by the site are rejected.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'filename', 'data' ),
					'properties'           => array(
						'filename'  => array(
							'type'        => 'string',
							'description' => 'The name to save the file under, including its extension.',
						),
						'data'      => array(
							'type'        => 'string',
							'description' => 'The raw file contents encoded as base64.',
						),
						'title'     => array(
							'type'        => 'string',
							'description' => 'Title for the new attachment. The filename is used when this is left out.',
						),
						'caption'   => array(
							'type'        => 'string',
							'description' => 'Caption shown beneath the file.',
						),
						'alt_text'  => array(
							'type'        => 'string',
							'description' => 'Alternative text describing an image for screen readers.',
						),
						'parent_id' => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => 'Post ID to attach the file to. Zero leaves it unattached.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'   => array( 'type' => 'boolean' ),
						'id'        => array( 'type' => 'integer' ),
						'url'       => array( 'type' => 'string' ),
						'mime_type' => array( 'type' => 'string' ),
						'message'   => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => static function ( $input = array() ): array {
					if ( ! is_array( $input ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'A filename and base64 file contents are required.', 'shim-mcp' ),
						);
					}

					if ( ! isset( $input['filename'] ) || ! is_string( $input['filename'] ) || '' === trim( $input['filename'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Give the file a name, including its extension.', 'shim-mcp' ),
						);
					}

					if ( ! isset( $input['data'] ) || ! is_string( $input['data'] ) || '' === trim( $input['data'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The base64 file contents are empty.', 'shim-mcp' ),
						);
					}

					$filename = sanitize_file_name( wp_basename( $input['filename'] ) );

					if ( '' === $filename ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'That filename is not usable once sanitized.', 'shim-mcp' ),
						);
					}

					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- MCP carries binary uploads as base64; strict mode rejects malformed payloads.
					$bytes = base64_decode( $input['data'], true );

					if ( false === $bytes || '' === $bytes ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The file contents could not be decoded as base64.', 'shim-mcp' ),
						);
					}

					$parent_id = isset( $input['parent_id'] ) ? absint( $input['parent_id'] ) : 0;

					if ( $parent_id > 0 ) {
						$parent = get_post( $parent_id );

						if ( ! $parent instanceof \WP_Post ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The post you want to attach this file to does not exist.', 'shim-mcp' ),
							);
						}

						if ( ! current_user_can( 'edit_post', $parent_id ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'You are not allowed to attach files to that post.', 'shim-mcp' ),
							);
						}
					}

					$uploads = wp_upload_dir();

					if ( ! empty( $uploads['error'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The uploads directory is not writable right now.', 'shim-mcp' ),
						);
					}

					$unique      = wp_unique_filename( $uploads['path'], $filename );
					$destination = trailingslashit( $uploads['path'] ) . $unique;

					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writes decoded bytes to a path just resolved inside the uploads directory.
					$written = file_put_contents( $destination, $bytes );

					if ( false === $written ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The file could not be written to the uploads directory.', 'shim-mcp' ),
						);
					}

					$checked = wp_check_filetype_and_ext( $destination, $unique );

					if ( empty( $checked['type'] ) || empty( $checked['ext'] ) ) {
						wp_delete_file( $destination );

						return array(
							'success' => false,
							'message' => esc_html__( 'This site does not accept files of that type.', 'shim-mcp' ),
						);
					}

					if ( ! empty( $checked['proper_filename'] ) && is_string( $checked['proper_filename'] ) ) {
						$corrected = wp_unique_filename( $uploads['path'], $checked['proper_filename'] );
						$renamed   = trailingslashit( $uploads['path'] ) . $corrected;

						// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- deduplicates a filename inside the uploads directory.
						if ( rename( $destination, $renamed ) ) {
							$destination = $renamed;
							$unique      = $corrected;
						}
					}

					$title = $unique;

					if ( isset( $input['title'] ) && is_string( $input['title'] ) && '' !== trim( $input['title'] ) ) {
						$title = sanitize_text_field( $input['title'] );
					}

					$caption = '';

					if ( isset( $input['caption'] ) && is_string( $input['caption'] ) ) {
						$caption = sanitize_text_field( $input['caption'] );
					}

					$attachment_id = wp_insert_attachment(
						array(
							'post_mime_type' => $checked['type'],
							'post_title'     => $title,
							'post_excerpt'   => $caption,
							'post_content'   => '',
							'post_status'    => 'inherit',
						),
						$destination,
						$parent_id,
						true
					);

					if ( is_wp_error( $attachment_id ) ) {
						wp_delete_file( $destination );

						return array(
							'success' => false,
							'message' => esc_html( $attachment_id->get_error_message() ),
						);
					}

					$attachment_id = (int) $attachment_id;

					require_once ABSPATH . 'wp-admin/includes/image.php';

					$metadata = wp_generate_attachment_metadata( $attachment_id, $destination );

					if ( is_array( $metadata ) ) {
						wp_update_attachment_metadata( $attachment_id, $metadata );
					}

					if ( isset( $input['alt_text'] ) && is_string( $input['alt_text'] ) ) {
						update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt_text'] ) );
					}

					return array(
						'success'   => true,
						'id'        => $attachment_id,
						'url'       => (string) wp_get_attachment_url( $attachment_id ),
						'mime_type' => (string) $checked['type'],
						'message'   => esc_html__( 'The file was added to the media library.', 'shim-mcp' ),
					);
				},
				'permission_callback' => static function (): bool {
					return current_user_can( 'upload_files' );
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
			'shim-mcp/media-update',
			array(
				'label'               => 'Edit Media Item Details',
				'description'         => 'Changes the stored title, caption, description or alternative text on an existing attachment. Fields you leave out keep their current values, and the file itself is untouched.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id'          => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'The attachment post ID to edit.',
						),
						'title'       => array(
							'type'        => 'string',
							'description' => 'Replacement title for the attachment.',
						),
						'caption'     => array(
							'type'        => 'string',
							'description' => 'Replacement caption text.',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'Replacement long description stored as the attachment body.',
						),
						'alt_text'    => array(
							'type'        => 'string',
							'description' => 'Replacement alternative text for an image.',
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
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => static function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['id'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'An attachment ID is required.', 'shim-mcp' ),
						);
					}

					$id = absint( $input['id'] );

					if ( $id < 1 ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The attachment ID must be a positive whole number.', 'shim-mcp' ),
						);
					}

					$attachment = get_post( $id );

					if ( ! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'No attachment exists with that ID.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'edit_post', $id ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You are not allowed to edit this attachment.', 'shim-mcp' ),
						);
					}

					$changes = array( 'ID' => $id );
					$updated = array();

					if ( isset( $input['title'] ) && is_string( $input['title'] ) ) {
						$changes['post_title'] = sanitize_text_field( $input['title'] );
						$updated[]             = 'title';
					}

					if ( isset( $input['caption'] ) && is_string( $input['caption'] ) ) {
						$changes['post_excerpt'] = sanitize_text_field( $input['caption'] );
						$updated[]               = 'caption';
					}

					if ( isset( $input['description'] ) && is_string( $input['description'] ) ) {
						$changes['post_content'] = wp_kses_post( $input['description'] );
						$updated[]               = 'description';
					}

					if ( count( $changes ) > 1 ) {
						$result = wp_update_post( $changes, true );

						if ( is_wp_error( $result ) ) {
							return array(
								'success' => false,
								'message' => esc_html( $result->get_error_message() ),
							);
						}
					}

					if ( isset( $input['alt_text'] ) && is_string( $input['alt_text'] ) ) {
						update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt_text'] ) );
						$updated[] = 'alt_text';
					}

					if ( empty( $updated ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Nothing was changed because no editable fields were supplied.', 'shim-mcp' ),
						);
					}

					return array(
						'success' => true,
						'id'      => $id,
						'updated' => $updated,
						'message' => esc_html__( 'The attachment details were saved.', 'shim-mcp' ),
					);
				},
				'permission_callback' => static function (): bool {
					return current_user_can( 'upload_files' );
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
			'shim-mcp/media-delete',
			array(
				'label'               => 'Remove a Media Item',
				'description'         => 'Deletes an attachment together with the file and any generated image sizes on disk. Set force to false to send it to the trash instead, where trashing attachments is enabled.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id'    => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'The attachment post ID to remove.',
						),
						'force' => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => 'When true the file is erased outright rather than moved to the trash.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
						'forced'  => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => static function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['id'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'An attachment ID is required.', 'shim-mcp' ),
						);
					}

					$id = absint( $input['id'] );

					if ( $id < 1 ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The attachment ID must be a positive whole number.', 'shim-mcp' ),
						);
					}

					$attachment = get_post( $id );

					if ( ! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'No attachment exists with that ID.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'delete_post', $id ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You are not allowed to delete this attachment.', 'shim-mcp' ),
						);
					}

					$force = true;

					if ( isset( $input['force'] ) ) {
						$force = (bool) $input['force'];
					}

					$deleted = wp_delete_attachment( $id, $force );

					if ( ! $deleted instanceof \WP_Post ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'WordPress could not remove that attachment.', 'shim-mcp' ),
						);
					}

					return array(
						'success' => true,
						'id'      => $id,
						'forced'  => $force,
						'message' => $force
							? esc_html__( 'The attachment and its files were erased.', 'shim-mcp' )
							: esc_html__( 'The attachment was moved to the trash.', 'shim-mcp' ),
					);
				},
				'permission_callback' => static function (): bool {
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
	}
}
