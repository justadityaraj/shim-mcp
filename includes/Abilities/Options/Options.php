<?php
declare(strict_types=1);

namespace ShimMcp\Abilities\Options;

final class Options {

	public static function register(): void {

		wp_register_ability(
			'shim-mcp/options-get',
			array(
				'label'               => 'Read Site Option',
				'description'         => 'Returns the stored value of one WordPress option looked up by its exact name, together with a flag saying whether the option exists at all.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'name' ),
					'properties'           => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Exact option name as stored in the options table, such as blogname or timezone_string.',
							'minLength'   => 1,
							'maxLength'   => 191,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'  => array( 'type' => 'boolean' ),
						'name'     => array( 'type' => 'string' ),
						'exists'   => array( 'type' => 'boolean' ),
						'autoload' => array( 'type' => 'string' ),
						'value'    => array( 'type' => array( 'string', 'number', 'boolean', 'object', 'array', 'null' ) ),
						'message'  => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => static function ( $input = array() ): array {
					$name = self::clean_option_name( $input );

					if ( '' === $name ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Provide the option name you want to read as a non-empty string of at most 191 characters.', 'shim-mcp' ),
						);
					}

					$absent = self::absence_marker();
					$value  = get_option( $name, $absent );

					if ( $absent === $value ) {
						return array(
							'success'  => true,
							'name'     => $name,
							'exists'   => false,
							'autoload' => '',
							'value'    => null,
							'message'  => esc_html__( 'Nothing is stored under that option name.', 'shim-mcp' ),
						);
					}

					return array(
						'success'  => true,
						'name'     => $name,
						'exists'   => true,
						'autoload' => self::autoload_flag( $name ),
						'value'    => $value,
					);
				},
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
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
			'shim-mcp/options-search',
			array(
				'label'               => 'Search Option Names',
				'description'         => 'Finds every option whose name contains a given fragment and returns one page of matches, each with a shortened preview of the stored value so long or serialised values do not flood the response.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'contains' ),
					'properties'           => array(
						'contains' => array(
							'type'        => 'string',
							'description' => 'Text to look for anywhere inside the option name. This is a plain substring match, not a regular expression.',
							'minLength'   => 1,
							'maxLength'   => 191,
						),
						'page'     => array(
							'type'        => 'integer',
							'description' => 'Which page of matches to return, counting from one.',
							'minimum'     => 1,
							'default'     => 1,
						),
						'per_page' => array(
							'type'        => 'integer',
							'description' => 'How many matches to return on a page, up to one hundred.',
							'minimum'     => 1,
							'maximum'     => 100,
							'default'     => 20,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'     => array( 'type' => 'boolean' ),
						'total'       => array( 'type' => 'integer' ),
						'page'        => array( 'type' => 'integer' ),
						'per_page'    => array( 'type' => 'integer' ),
						'total_pages' => array( 'type' => 'integer' ),
						'options'     => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'name'     => array( 'type' => 'string' ),
									'autoload' => array( 'type' => 'string' ),
									'preview'  => array( 'type' => 'string' ),
									'length'   => array( 'type' => 'integer' ),
								),
							),
						),
						'message'     => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => static function ( $input = array() ): array {
					global $wpdb;

					$fragment = '';

					if ( is_array( $input ) && isset( $input['contains'] ) && is_string( $input['contains'] ) ) {
						$fragment = trim( sanitize_text_field( $input['contains'] ) );
					}

					if ( '' === $fragment || strlen( $fragment ) > 191 ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Provide a fragment of one to 191 characters to search option names for.', 'shim-mcp' ),
						);
					}

					$page     = is_array( $input ) && isset( $input['page'] ) ? absint( $input['page'] ) : 1;
					$per_page = is_array( $input ) && isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 20;
					$page     = $page > 0 ? $page : 1;
					$per_page = $per_page > 0 ? min( $per_page, 100 ) : 20;
					$offset   = ( $page - 1 ) * $per_page;

					$like = '%' . $wpdb->esc_like( $fragment ) . '%';

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- searching option names by pattern has no core API, and the result must be live rather than cached.
					$total = (int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
							$like
						)
					);

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- searching option names by pattern has no core API, and the result must be live rather than cached.
					$rows = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name ASC LIMIT %d OFFSET %d",
							$like,
							$per_page,
							$offset
						),
						ARRAY_A
					);

					$matches = array();

					if ( is_array( $rows ) ) {
						foreach ( $rows as $row ) {
							$raw = isset( $row['option_value'] ) && is_string( $row['option_value'] ) ? $row['option_value'] : '';

							$matches[] = array(
								'name'     => isset( $row['option_name'] ) ? (string) $row['option_name'] : '',
								'autoload' => isset( $row['autoload'] ) ? (string) $row['autoload'] : '',
								'preview'  => self::shorten( $raw ),
								'length'   => strlen( $raw ),
							);
						}
					}

					return array(
						'success'     => true,
						'total'       => $total,
						'page'        => $page,
						'per_page'    => $per_page,
						'total_pages' => (int) ceil( $total / $per_page ),
						'options'     => $matches,
					);
				},
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
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
			'shim-mcp/options-update',
			array(
				'label'               => 'Write Site Option',
				'description'         => 'Stores a new value for an option and creates it when it does not exist yet. Supply a key to replace a single entry inside an option that already holds an array rather than overwriting the whole option. Options that govern the site addresses, who may register and at what role, the active plugin set, or role capability definitions are refused outright.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'name', 'value' ),
					'properties'           => array(
						'name'  => array(
							'type'        => 'string',
							'description' => 'Exact option name to write.',
							'minLength'   => 1,
							'maxLength'   => 191,
						),
						'value' => array(
							'type'        => array( 'string', 'number', 'boolean', 'object', 'array', 'null' ),
							'description' => 'The value to store. When a key is also given this becomes the value of that one array entry instead of the whole option.',
						),
						'key'   => array(
							'type'        => 'string',
							'description' => 'Optional array key inside the option. The option must already hold an array, or not exist yet, for a key to be accepted.',
							'minLength'   => 1,
							'maxLength'   => 191,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'   => array( 'type' => 'boolean' ),
						'name'      => array( 'type' => 'string' ),
						'key'       => array( 'type' => 'string' ),
						'created'   => array( 'type' => 'boolean' ),
						'unchanged' => array( 'type' => 'boolean' ),
						'value'     => array( 'type' => array( 'string', 'number', 'boolean', 'object', 'array', 'null' ) ),
						'message'   => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => static function ( $input = array() ): array {
					$name = self::clean_option_name( $input );

					if ( '' === $name ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Provide the option name you want to write as a non-empty string of at most 191 characters.', 'shim-mcp' ),
						);
					}

					if ( self::is_protected( $name ) ) {
						return array(
							'success' => false,
							'name'    => $name,
							'message' => esc_html__( 'That option is protected. Changing it can lock people out of the site or hand out capabilities, so this ability will not write it.', 'shim-mcp' ),
						);
					}

					if ( ! is_array( $input ) || ! array_key_exists( 'value', $input ) ) {
						return array(
							'success' => false,
							'name'    => $name,
							'message' => esc_html__( 'Include a value to store, even when that value is null.', 'shim-mcp' ),
						);
					}

					$absent  = self::absence_marker();
					$current = get_option( $name, $absent );
					$created = ( $absent === $current );
					$key     = '';

					if ( isset( $input['key'] ) && is_string( $input['key'] ) ) {
						$key = trim( sanitize_text_field( $input['key'] ) );
					}

					if ( '' !== $key ) {
						if ( strlen( $key ) > 191 ) {
							return array(
								'success' => false,
								'name'    => $name,
								'message' => esc_html__( 'The array key is longer than 191 characters.', 'shim-mcp' ),
							);
						}

						if ( ! $created && ! is_array( $current ) ) {
							return array(
								'success' => false,
								'name'    => $name,
								'key'     => $key,
								'message' => esc_html__( 'That option does not hold an array, so a single key inside it cannot be replaced. Write the whole option instead.', 'shim-mcp' ),
							);
						}

						$new_value         = $created ? array() : $current;
						$new_value[ $key ] = $input['value'];
					} else {
						$new_value = $input['value'];
					}

					if ( ! $created && $new_value === $current ) {
						return array(
							'success'   => true,
							'name'      => $name,
							'key'       => $key,
							'created'   => false,
							'unchanged' => true,
							'value'     => $new_value,
							'message'   => esc_html__( 'The option already held that value, so nothing was written.', 'shim-mcp' ),
						);
					}

					if ( ! update_option( $name, $new_value ) ) {
						return array(
							'success' => false,
							'name'    => $name,
							'key'     => $key,
							'message' => esc_html__( 'WordPress declined to save the option. Check that the value can be serialised and that no filter is blocking the write.', 'shim-mcp' ),
						);
					}

					return array(
						'success'   => true,
						'name'      => $name,
						'key'       => $key,
						'created'   => $created,
						'unchanged' => false,
						'value'     => get_option( $name ),
					);
				},
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
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

	private static function absence_marker(): string {
		return '__shim_mcp_option_not_set__';
	}

	private static function clean_option_name( $input ): string {
		if ( ! is_array( $input ) || ! isset( $input['name'] ) || ! is_string( $input['name'] ) ) {
			return '';
		}

		$name = trim( sanitize_text_field( $input['name'] ) );

		if ( strlen( $name ) > 191 ) {
			return '';
		}

		return $name;
	}

	private static function is_protected( string $name ): bool {
		global $wpdb;

		$lower = strtolower( $name );

		$blocked = array(
			'siteurl',
			'home',
			'admin_email',
			'new_admin_email',
			'users_can_register',
			'default_role',
			'active_plugins',
			'active_sitewide_plugins',
			'recently_activated',
			'template',
			'stylesheet',
			'db_version',
			'initial_db_version',
			'cron',
		);

		if ( in_array( $lower, $blocked, true ) ) {
			return true;
		}

		if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'get_blog_prefix' ) ) {
			if ( strtolower( $wpdb->get_blog_prefix() . 'user_roles' ) === $lower ) {
				return true;
			}
		}

		return 'user_roles' === substr( $lower, -10 );
	}

	private static function autoload_flag( string $name ): string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- reads the autoload flag, which core exposes no accessor for.
		$flag = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				$name
			)
		);

		return is_string( $flag ) ? $flag : '';
	}

	private static function shorten( string $raw ): string {
		$flat = preg_replace( '/\s+/', ' ', $raw );

		if ( ! is_string( $flat ) ) {
			$flat = $raw;
		}

		$flat = trim( $flat );

		if ( strlen( $flat ) <= 160 ) {
			return $flat;
		}

		return substr( $flat, 0, 160 ) . '...';
	}
}
