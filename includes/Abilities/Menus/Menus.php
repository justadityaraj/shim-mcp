<?php
declare(strict_types=1);

namespace ShimMcp\Abilities\Menus;

class Menus {

	public static function register(): void {
		wp_register_ability(
			'shim-mcp/menus-list',
			array(
				'label'               => 'List Navigation Menus',
				'description'         => 'Returns every navigation menu on the site together with its numeric id, display name, slug and how many items it currently holds. Takes no input.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array(),
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'count'   => array( 'type' => 'integer' ),
						'menus'   => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'         => array( 'type' => 'integer' ),
									'name'       => array( 'type' => 'string' ),
									'slug'       => array( 'type' => 'string' ),
									'item_count' => array( 'type' => 'integer' ),
								),
							),
						),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$menus = wp_get_nav_menus();

					if ( ! is_array( $menus ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Navigation menus could not be read from this site.', 'shim-mcp' ),
						);
					}

					$rows = array();

					foreach ( $menus as $menu ) {
						$rows[] = array(
							'id'         => (int) $menu->term_id,
							'name'       => esc_html( $menu->name ),
							'slug'       => esc_html( $menu->slug ),
							'item_count' => (int) $menu->count,
						);
					}

					return array(
						'success' => true,
						'count'   => count( $rows ),
						'menus'   => $rows,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_theme_options' );
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
			'shim-mcp/menus-create',
			array(
				'label'               => 'Create Navigation Menu',
				'description'         => 'Creates a new empty navigation menu under the name you supply. The name has to be unique, because WordPress rejects a second menu carrying an existing name.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'name' ),
					'properties'           => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Display name for the new menu, for example Footer Links.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
						'name'    => array( 'type' => 'string' ),
						'slug'    => array( 'type' => 'string' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['name'] ) || ! is_string( $input['name'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Supply a menu name as a string.', 'shim-mcp' ),
						);
					}

					$name = sanitize_text_field( $input['name'] );

					if ( '' === trim( $name ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The menu name cannot be blank.', 'shim-mcp' ),
						);
					}

					$created = wp_create_nav_menu( $name );

					if ( is_wp_error( $created ) ) {
						return array(
							'success' => false,
							'message' => esc_html( $created->get_error_message() ),
						);
					}

					$menu = wp_get_nav_menu_object( (int) $created );

					if ( ! $menu ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The menu was created but could not be read back.', 'shim-mcp' ),
						);
					}

					return array(
						'success' => true,
						'id'      => (int) $menu->term_id,
						'name'    => esc_html( $menu->name ),
						'slug'    => esc_html( $menu->slug ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_theme_options' );
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
			'shim-mcp/menus-list-items',
			array(
				'label'               => 'List Menu Items',
				'description'         => 'Reads back the items inside one navigation menu, each with its item id, title, resolved url, the kind of thing it points at, its parent item id and its position in the menu order. Identify the menu by numeric id or by slug.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'menu' ),
					'properties'           => array(
						'menu' => array(
							'type'        => 'string',
							'description' => 'Numeric id or slug of the menu whose items you want.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'menu_id' => array( 'type' => 'integer' ),
						'count'   => array( 'type' => 'integer' ),
						'items'   => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'        => array( 'type' => 'integer' ),
									'title'     => array( 'type' => 'string' ),
									'url'       => array( 'type' => 'string' ),
									'type'      => array( 'type' => 'string' ),
									'object'    => array( 'type' => 'string' ),
									'object_id' => array( 'type' => 'integer' ),
									'parent'    => array( 'type' => 'integer' ),
									'order'     => array( 'type' => 'integer' ),
									'target'    => array( 'type' => 'string' ),
								),
							),
						),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['menu'] ) || ! is_scalar( $input['menu'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Supply the menu id or slug.', 'shim-mcp' ),
						);
					}

					$locator = sanitize_text_field( (string) $input['menu'] );
					$menu    = wp_get_nav_menu_object( ctype_digit( $locator ) ? (int) $locator : $locator );

					if ( ! $menu ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'No navigation menu matches that id or slug.', 'shim-mcp' ),
						);
					}

					$items = wp_get_nav_menu_items( $menu->term_id );

					if ( ! is_array( $items ) ) {
						$items = array();
					}

					$rows = array();

					foreach ( $items as $item ) {
						$rows[] = array(
							'id'        => (int) $item->ID,
							'title'     => esc_html( $item->title ),
							'url'       => esc_url_raw( (string) $item->url ),
							'type'      => esc_html( (string) $item->type ),
							'object'    => esc_html( (string) $item->object ),
							'object_id' => (int) $item->object_id,
							'parent'    => (int) $item->menu_item_parent,
							'order'     => (int) $item->menu_order,
							'target'    => esc_html( (string) $item->target ),
						);
					}

					return array(
						'success' => true,
						'menu_id' => (int) $menu->term_id,
						'count'   => count( $rows ),
						'items'   => $rows,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_theme_options' );
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
			'shim-mcp/menus-add-item',
			array(
				'label'               => 'Add Menu Item',
				'description'         => 'Appends an entry to a navigation menu. Give it a custom title and url for a plain link, or point it at existing content by naming the object type (post_type or taxonomy) plus the object id and its post type or taxonomy name.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'menu' ),
					'properties'           => array(
						'menu'        => array(
							'type'        => 'string',
							'description' => 'Numeric id or slug of the menu receiving the new item.',
						),
						'title'       => array(
							'type'        => 'string',
							'description' => 'Label shown in the menu. Leave it out for a content reference and WordPress falls back to the object title.',
						),
						'url'         => array(
							'type'        => 'string',
							'description' => 'Destination address, used when the item is a plain custom link.',
						),
						'item_type'   => array(
							'type'        => 'string',
							'description' => 'One of custom, post_type or taxonomy. Defaults to custom.',
							'enum'        => array( 'custom', 'post_type', 'taxonomy' ),
						),
						'object'      => array(
							'type'        => 'string',
							'description' => 'Post type name such as page, or taxonomy name such as category, when the item references content.',
						),
						'object_id'   => array(
							'type'        => 'integer',
							'description' => 'Id of the post, page or term the item should link to.',
						),
						'parent_id'   => array(
							'type'        => 'integer',
							'description' => 'Menu item id to nest this entry beneath. Omit for a top level entry.',
						),
						'position'    => array(
							'type'        => 'integer',
							'description' => 'Position in the menu order. Omit to append at the end.',
						),
						'target'      => array(
							'type'        => 'string',
							'description' => 'Set to _blank to open the link in a new tab.',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'Short descriptive text some themes render under the label.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'item_id' => array( 'type' => 'integer' ),
						'menu_id' => array( 'type' => 'integer' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['menu'] ) || ! is_scalar( $input['menu'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Supply the menu id or slug.', 'shim-mcp' ),
						);
					}

					$locator = sanitize_text_field( (string) $input['menu'] );
					$menu    = wp_get_nav_menu_object( ctype_digit( $locator ) ? (int) $locator : $locator );

					if ( ! $menu ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'No navigation menu matches that id or slug.', 'shim-mcp' ),
						);
					}

					$item_type = 'custom';

					if ( isset( $input['item_type'] ) && is_string( $input['item_type'] ) ) {
						$candidate = sanitize_key( $input['item_type'] );

						if ( ! in_array( $candidate, array( 'custom', 'post_type', 'taxonomy' ), true ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The item type must be custom, post_type or taxonomy.', 'shim-mcp' ),
							);
						}

						$item_type = $candidate;
					}

					$args = array(
						'menu-item-type'   => $item_type,
						'menu-item-status' => 'publish',
					);

					if ( isset( $input['title'] ) && is_string( $input['title'] ) ) {
						$args['menu-item-title'] = sanitize_text_field( $input['title'] );
					}

					if ( isset( $input['description'] ) && is_string( $input['description'] ) ) {
						$args['menu-item-description'] = sanitize_text_field( $input['description'] );
					}

					if ( isset( $input['target'] ) && is_string( $input['target'] ) ) {
						$args['menu-item-target'] = '_blank' === trim( $input['target'] ) ? '_blank' : '';
					}

					if ( isset( $input['parent_id'] ) ) {
						$args['menu-item-parent-id'] = absint( $input['parent_id'] );
					}

					if ( isset( $input['position'] ) ) {
						$args['menu-item-position'] = absint( $input['position'] );
					}

					if ( 'custom' === $item_type ) {
						if ( ! isset( $input['url'] ) || ! is_string( $input['url'] ) || '' === trim( $input['url'] ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'A custom link needs a url.', 'shim-mcp' ),
							);
						}

						$url = esc_url_raw( trim( $input['url'] ) );

						if ( '' === $url ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'That url is not in a form WordPress accepts.', 'shim-mcp' ),
							);
						}

						$args['menu-item-url'] = $url;

						if ( ! isset( $args['menu-item-title'] ) || '' === trim( (string) $args['menu-item-title'] ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'A custom link needs a title.', 'shim-mcp' ),
							);
						}
					} else {
						if ( ! isset( $input['object'] ) || ! is_string( $input['object'] ) || '' === trim( $input['object'] ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'Naming the post type or taxonomy is required for a content reference.', 'shim-mcp' ),
							);
						}

						$object    = sanitize_key( $input['object'] );
						$object_id = isset( $input['object_id'] ) ? absint( $input['object_id'] ) : 0;

						if ( 0 === $object_id ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'Supply the id of the post or term this item should link to.', 'shim-mcp' ),
							);
						}

						if ( 'post_type' === $item_type ) {
							if ( ! post_type_exists( $object ) ) {
								return array(
									'success' => false,
									'message' => esc_html__( 'That post type is not registered on this site.', 'shim-mcp' ),
								);
							}

							$linked = get_post( $object_id );

							if ( ! $linked || $linked->post_type !== $object ) {
								return array(
									'success' => false,
									'message' => esc_html__( 'No content of that type exists with the given id.', 'shim-mcp' ),
								);
							}

							if ( ! current_user_can( 'read_post', $object_id ) ) {
								return array(
									'success' => false,
									'message' => esc_html__( 'You are not allowed to read the content you asked to link.', 'shim-mcp' ),
								);
							}
						} else {
							if ( ! taxonomy_exists( $object ) ) {
								return array(
									'success' => false,
									'message' => esc_html__( 'That taxonomy is not registered on this site.', 'shim-mcp' ),
								);
							}

							$term = get_term( $object_id, $object );

							if ( ! $term || is_wp_error( $term ) ) {
								return array(
									'success' => false,
									'message' => esc_html__( 'No term in that taxonomy exists with the given id.', 'shim-mcp' ),
								);
							}
						}

						$args['menu-item-object']    = $object;
						$args['menu-item-object-id'] = $object_id;
					}

					$item_id = wp_update_nav_menu_item( (int) $menu->term_id, 0, $args );

					if ( is_wp_error( $item_id ) ) {
						return array(
							'success' => false,
							'message' => esc_html( $item_id->get_error_message() ),
						);
					}

					if ( 0 === (int) $item_id ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'WordPress did not create the menu item.', 'shim-mcp' ),
						);
					}

					return array(
						'success' => true,
						'item_id' => (int) $item_id,
						'menu_id' => (int) $menu->term_id,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_theme_options' );
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
			'shim-mcp/menus-update-item',
			array(
				'label'               => 'Update Menu Item',
				'description'         => 'Changes an entry that already sits in a navigation menu. Anything you leave out keeps the value it has now, so you can retitle an item or move it under a different parent without restating the rest.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'item_id' ),
					'properties'           => array(
						'item_id'     => array(
							'type'        => 'integer',
							'description' => 'Id of the menu item to change.',
						),
						'title'       => array(
							'type'        => 'string',
							'description' => 'New label for the item.',
						),
						'url'         => array(
							'type'        => 'string',
							'description' => 'New destination, meaningful for custom link items.',
						),
						'parent_id'   => array(
							'type'        => 'integer',
							'description' => 'Menu item id to nest this entry beneath. Pass zero to lift it back to the top level.',
						),
						'position'    => array(
							'type'        => 'integer',
							'description' => 'New position within the menu order.',
						),
						'target'      => array(
							'type'        => 'string',
							'description' => 'Set to _blank to open in a new tab, or an empty string to open in the same tab.',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'New descriptive text for the item.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'item_id' => array( 'type' => 'integer' ),
						'menu_id' => array( 'type' => 'integer' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['item_id'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Supply the id of the menu item to change.', 'shim-mcp' ),
						);
					}

					$item_id = absint( $input['item_id'] );

					if ( 0 === $item_id ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The menu item id must be a positive number.', 'shim-mcp' ),
						);
					}

					$post = get_post( $item_id );

					if ( ! $post || 'nav_menu_item' !== $post->post_type ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'That id does not belong to a navigation menu item.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'edit_post', $item_id ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You are not allowed to edit this menu item.', 'shim-mcp' ),
						);
					}

					$menus = wp_get_object_terms( $item_id, 'nav_menu' );

					if ( is_wp_error( $menus ) || empty( $menus ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'This item is not attached to any navigation menu.', 'shim-mcp' ),
						);
					}

					$menu_id  = (int) $menus[0]->term_id;
					$existing = wp_setup_nav_menu_item( $post );

					$args = array(
						'menu-item-db-id'       => $item_id,
						'menu-item-object-id'   => (int) $existing->object_id,
						'menu-item-object'      => (string) $existing->object,
						'menu-item-type'        => (string) $existing->type,
						'menu-item-status'      => 'publish',
						'menu-item-title'       => (string) $existing->title,
						'menu-item-url'         => (string) $existing->url,
						'menu-item-description' => (string) $existing->description,
						'menu-item-attr-title'  => (string) $existing->attr_title,
						'menu-item-target'      => (string) $existing->target,
						'menu-item-classes'     => is_array( $existing->classes ) ? implode( ' ', $existing->classes ) : '',
						'menu-item-xfn'         => (string) $existing->xfn,
						'menu-item-parent-id'   => (int) $existing->menu_item_parent,
						'menu-item-position'    => (int) $existing->menu_order,
					);

					if ( isset( $input['title'] ) ) {
						if ( ! is_string( $input['title'] ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The title must be a string.', 'shim-mcp' ),
							);
						}

						$args['menu-item-title'] = sanitize_text_field( $input['title'] );
					}

					if ( isset( $input['description'] ) ) {
						if ( ! is_string( $input['description'] ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The description must be a string.', 'shim-mcp' ),
							);
						}

						$args['menu-item-description'] = sanitize_text_field( $input['description'] );
					}

					if ( isset( $input['url'] ) ) {
						if ( ! is_string( $input['url'] ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The url must be a string.', 'shim-mcp' ),
							);
						}

						$url = esc_url_raw( trim( $input['url'] ) );

						if ( '' === $url ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'That url is not in a form WordPress accepts.', 'shim-mcp' ),
							);
						}

						$args['menu-item-url'] = $url;
					}

					if ( isset( $input['target'] ) ) {
						if ( ! is_string( $input['target'] ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The target must be a string.', 'shim-mcp' ),
							);
						}

						$args['menu-item-target'] = '_blank' === trim( $input['target'] ) ? '_blank' : '';
					}

					if ( isset( $input['parent_id'] ) ) {
						$parent_id = absint( $input['parent_id'] );

						if ( $parent_id === $item_id ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'A menu item cannot be its own parent.', 'shim-mcp' ),
							);
						}

						if ( $parent_id > 0 ) {
							$parent = get_post( $parent_id );

							if ( ! $parent || 'nav_menu_item' !== $parent->post_type ) {
								return array(
									'success' => false,
									'message' => esc_html__( 'The parent id does not belong to a navigation menu item.', 'shim-mcp' ),
								);
							}
						}

						$args['menu-item-parent-id'] = $parent_id;
					}

					if ( isset( $input['position'] ) ) {
						$args['menu-item-position'] = absint( $input['position'] );
					}

					$updated = wp_update_nav_menu_item( $menu_id, $item_id, $args );

					if ( is_wp_error( $updated ) ) {
						return array(
							'success' => false,
							'message' => esc_html( $updated->get_error_message() ),
						);
					}

					return array(
						'success' => true,
						'item_id' => (int) $updated,
						'menu_id' => $menu_id,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_theme_options' );
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
			'shim-mcp/menus-delete-item',
			array(
				'label'               => 'Delete Menu Item',
				'description'         => 'Permanently removes one entry from a navigation menu. The post or term the entry pointed at is untouched, only the menu entry itself goes away.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'item_id' ),
					'properties'           => array(
						'item_id' => array(
							'type'        => 'integer',
							'description' => 'Id of the menu item to remove.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'item_id' => array( 'type' => 'integer' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['item_id'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Supply the id of the menu item to remove.', 'shim-mcp' ),
						);
					}

					$item_id = absint( $input['item_id'] );

					if ( 0 === $item_id ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The menu item id must be a positive number.', 'shim-mcp' ),
						);
					}

					$post = get_post( $item_id );

					if ( ! $post || 'nav_menu_item' !== $post->post_type ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'That id does not belong to a navigation menu item.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'delete_post', $item_id ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You are not allowed to delete this menu item.', 'shim-mcp' ),
						);
					}

					$deleted = wp_delete_post( $item_id, true );

					if ( ! $deleted ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The menu item could not be removed.', 'shim-mcp' ),
						);
					}

					return array(
						'success' => true,
						'item_id' => $item_id,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_theme_options' );
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
			'shim-mcp/menus-assign-location',
			array(
				'label'               => 'Assign Menu To Theme Location',
				'description'         => 'Hooks a navigation menu up to one of the display slots the active theme registers, such as the primary header slot. Pass a menu of zero to clear the slot instead.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'location', 'menu' ),
					'properties'           => array(
						'location' => array(
							'type'        => 'string',
							'description' => 'Identifier of the theme location, as registered by the active theme.',
						),
						'menu'     => array(
							'type'        => 'string',
							'description' => 'Numeric id or slug of the menu to show there, or 0 to leave the location empty.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'   => array( 'type' => 'boolean' ),
						'location'  => array( 'type' => 'string' ),
						'menu_id'   => array( 'type' => 'integer' ),
						'locations' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'message'   => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['location'] ) || ! is_string( $input['location'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Supply the theme location identifier as a string.', 'shim-mcp' ),
						);
					}

					if ( ! isset( $input['menu'] ) || ! is_scalar( $input['menu'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Supply a menu id or slug, or 0 to empty the location.', 'shim-mcp' ),
						);
					}

					$location   = sanitize_key( $input['location'] );
					$registered = get_registered_nav_menus();

					if ( ! is_array( $registered ) || ! array_key_exists( $location, $registered ) ) {
						return array(
							'success'   => false,
							'message'   => esc_html__( 'The active theme does not register a location by that name.', 'shim-mcp' ),
							'locations' => is_array( $registered ) ? array_map( 'esc_html', array_keys( $registered ) ) : array(),
						);
					}

					$locator = sanitize_text_field( (string) $input['menu'] );
					$menu_id = 0;

					if ( '0' !== $locator && '' !== $locator ) {
						$menu = wp_get_nav_menu_object( ctype_digit( $locator ) ? (int) $locator : $locator );

						if ( ! $menu ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'No navigation menu matches that id or slug.', 'shim-mcp' ),
							);
						}

						$menu_id = (int) $menu->term_id;
					}

					$assignments = get_theme_mod( 'nav_menu_locations' );

					if ( ! is_array( $assignments ) ) {
						$assignments = array();
					}

					$assignments[ $location ] = $menu_id;

					set_theme_mod( 'nav_menu_locations', $assignments );

					return array(
						'success'  => true,
						'location' => esc_html( $location ),
						'menu_id'  => $menu_id,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_theme_options' );
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
