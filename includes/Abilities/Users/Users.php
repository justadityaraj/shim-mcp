<?php
declare(strict_types=1);

namespace ShimMcp\Abilities\Users;

final class Users {

	public static function register(): void {
		$shape_user = static function ( \WP_User $user ): array {
			return array(
				'id'            => (int) $user->ID,
				'username'      => (string) $user->user_login,
				'email'         => (string) $user->user_email,
				'display_name'  => (string) $user->display_name,
				'first_name'    => (string) $user->first_name,
				'last_name'     => (string) $user->last_name,
				'nickname'      => (string) $user->nickname,
				'url'           => (string) $user->user_url,
				'description'   => (string) $user->description,
				'registered_at' => (string) $user->user_registered,
				'roles'         => array_values( array_map( 'strval', (array) $user->roles ) ),
				'avatar_url'    => (string) get_avatar_url( $user->ID ),
				'archive_url'   => (string) get_author_posts_url( $user->ID ),
			);
		};

		$load_admin_user_api = static function (): void {
			if ( ! function_exists( 'get_editable_roles' ) || ! function_exists( 'wp_delete_user' ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
			}
		};

		$may_grant_role = static function ( string $role_slug ) use ( $load_admin_user_api ): bool {
			if ( '' === $role_slug ) {
				return false;
			}
			if ( ! current_user_can( 'promote_users' ) ) {
				return false;
			}

			$role = get_role( $role_slug );
			if ( ! $role instanceof \WP_Role ) {
				return false;
			}

			$load_admin_user_api();
			$editable = get_editable_roles();
			if ( ! isset( $editable[ $role_slug ] ) ) {
				return false;
			}

			foreach ( (array) $role->capabilities as $capability => $is_granted ) {
				if ( $is_granted && ! current_user_can( (string) $capability ) ) {
					return false;
				}
			}

			return true;
		};

		$profile_fields = array(
			'first_name'   => 'sanitize_text_field',
			'last_name'    => 'sanitize_text_field',
			'nickname'     => 'sanitize_text_field',
			'display_name' => 'sanitize_text_field',
			'description'  => 'sanitize_textarea_field',
			'url'          => 'esc_url_raw',
		);

		wp_register_ability(
			'shim-mcp/users-list',
			array(
				'label'               => __( 'Browse Users', 'shim-mcp' ),
				'description'         => __( 'Returns a page of user accounts on this site. You can narrow the result to a single role or to accounts matching a search term, and you control how many accounts come back per page.', 'shim-mcp' ),
				'category'            => 'user',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array(),
					'properties'           => array(
						'page'     => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'default'     => 1,
							'description' => __( 'Which page of results to return, counting from one.', 'shim-mcp' ),
						),
						'per_page' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 100,
							'default'     => 20,
							'description' => __( 'How many accounts to return on this page, capped at one hundred.', 'shim-mcp' ),
						),
						'role'     => array(
							'type'        => 'string',
							'description' => __( 'Limit the result to accounts holding this role slug, for example editor.', 'shim-mcp' ),
						),
						'search'   => array(
							'type'        => 'string',
							'description' => __( 'Free text matched against usernames, email addresses and display names.', 'shim-mcp' ),
						),
						'orderby'  => array(
							'type'        => 'string',
							'enum'        => array( 'ID', 'login', 'display_name', 'registered', 'email' ),
							'default'     => 'ID',
							'description' => __( 'Which field the results are sorted by.', 'shim-mcp' ),
						),
						'order'    => array(
							'type'        => 'string',
							'enum'        => array( 'ASC', 'DESC' ),
							'default'     => 'ASC',
							'description' => __( 'Sort direction, ascending or descending.', 'shim-mcp' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'     => array( 'type' => 'boolean' ),
						'users'       => array( 'type' => 'array' ),
						'total'       => array( 'type' => 'integer' ),
						'page'        => array( 'type' => 'integer' ),
						'per_page'    => array( 'type' => 'integer' ),
						'total_pages' => array( 'type' => 'integer' ),
						'message'     => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ) use ( $shape_user ): array {
					if ( ! is_array( $input ) ) {
						$input = array();
					}

					$page     = isset( $input['page'] ) ? max( 1, absint( $input['page'] ) ) : 1;
					$per_page = isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 20;
					if ( $per_page < 1 ) {
						$per_page = 20;
					}
					if ( $per_page > 100 ) {
						$per_page = 100;
					}

					$allowed_orderby = array( 'ID', 'login', 'display_name', 'registered', 'email' );
					$orderby         = isset( $input['orderby'] ) && is_string( $input['orderby'] ) ? $input['orderby'] : 'ID';
					if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
						$orderby = 'ID';
					}

					$order = isset( $input['order'] ) && is_string( $input['order'] ) && 'DESC' === strtoupper( $input['order'] ) ? 'DESC' : 'ASC';

					$args = array(
						'number'  => $per_page,
						'paged'   => $page,
						'orderby' => $orderby,
						'order'   => $order,
						'fields'  => 'all',
					);

					if ( isset( $input['role'] ) && is_string( $input['role'] ) && '' !== $input['role'] ) {
						$role_slug = sanitize_key( $input['role'] );
						if ( ! get_role( $role_slug ) instanceof \WP_Role ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'That role slug does not exist on this site.', 'shim-mcp' ),
							);
						}
						$args['role'] = $role_slug;
					}

					if ( isset( $input['search'] ) && is_string( $input['search'] ) && '' !== trim( $input['search'] ) ) {
						$args['search']         = '*' . sanitize_text_field( $input['search'] ) . '*';
						$args['search_columns'] = array( 'user_login', 'user_email', 'user_nicename', 'display_name' );
					}

					$query = new \WP_User_Query( $args );
					$found = (int) $query->get_total();

					$users = array();
					foreach ( (array) $query->get_results() as $user ) {
						if ( $user instanceof \WP_User ) {
							$users[] = $shape_user( $user );
						}
					}

					return array(
						'success'     => true,
						'users'       => $users,
						'total'       => $found,
						'page'        => $page,
						'per_page'    => $per_page,
						'total_pages' => $per_page > 0 ? (int) ceil( $found / $per_page ) : 0,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'list_users' );
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
			'shim-mcp/users-get',
			array(
				'label'               => __( 'Read User', 'shim-mcp' ),
				'description'         => __( 'Returns the full profile of one account, including its roles, contact fields and registration date. Give it the numeric user ID.', 'shim-mcp' ),
				'category'            => 'user',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Numeric ID of the account to read.', 'shim-mcp' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'user'    => array( 'type' => 'object' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ) use ( $shape_user ): array {
					if ( ! is_array( $input ) || ! isset( $input['id'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'A numeric user ID is required.', 'shim-mcp' ),
						);
					}

					$user_id = absint( $input['id'] );
					if ( $user_id < 1 ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'A numeric user ID is required.', 'shim-mcp' ),
						);
					}

					$user = get_userdata( $user_id );
					if ( ! $user instanceof \WP_User ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'No account exists with that ID.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'edit_user', $user_id ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You are not allowed to view this account.', 'shim-mcp' ),
						);
					}

					return array(
						'success' => true,
						'user'    => $shape_user( $user ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'list_users' );
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
			'shim-mcp/users-list-roles',
			array(
				'label'               => __( 'Read User Roles', 'shim-mcp' ),
				'description'         => __( 'Lists every role defined on this site with its slug, display name and capability count, and flags the ones the calling account is allowed to hand out. Takes no arguments.', 'shim-mcp' ),
				'category'            => 'user',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array(),
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'      => array( 'type' => 'boolean' ),
						'roles'        => array( 'type' => 'array' ),
						'default_role' => array( 'type' => 'string' ),
						'message'      => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ) use ( $may_grant_role ): array {
					unset( $input );

					$names = wp_roles()->get_names();
					if ( ! is_array( $names ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The role list could not be read from this site.', 'shim-mcp' ),
						);
					}

					$roles = array();
					foreach ( $names as $slug => $label ) {
						$role  = get_role( (string) $slug );
						$caps  = $role instanceof \WP_Role ? array_keys( array_filter( (array) $role->capabilities ) ) : array();
						$roles[] = array(
							'slug'             => (string) $slug,
							'name'             => esc_html( translate_user_role( (string) $label ) ),
							'capability_count' => count( $caps ),
							'grantable_by_you' => $may_grant_role( (string) $slug ),
						);
					}

					return array(
						'success'      => true,
						'roles'        => $roles,
						'default_role' => (string) get_option( 'default_role', 'subscriber' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'list_users' );
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
			'shim-mcp/users-create',
			array(
				'label'               => __( 'Add User', 'shim-mcp' ),
				'description'         => __( 'Creates a new account from a username, an email address and a password. Profile fields and a starting role are optional, and the site default role is used when none is given. A role you cannot grant yourself is rejected.', 'shim-mcp' ),
				'category'            => 'user',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'username', 'email', 'password' ),
					'properties'           => array(
						'username'          => array(
							'type'        => 'string',
							'minLength'   => 1,
							'description' => __( 'Login name for the new account. It must not already be taken.', 'shim-mcp' ),
						),
						'email'             => array(
							'type'        => 'string',
							'minLength'   => 3,
							'description' => __( 'Email address for the new account. It must not already be in use.', 'shim-mcp' ),
						),
						'password'          => array(
							'type'        => 'string',
							'minLength'   => 6,
							'description' => __( 'Plain text password to set on the new account.', 'shim-mcp' ),
						),
						'role'              => array(
							'type'        => 'string',
							'description' => __( 'Role slug to assign. Leave it out to use the site default role.', 'shim-mcp' ),
						),
						'first_name'        => array(
							'type'        => 'string',
							'description' => __( 'Given name shown on the profile.', 'shim-mcp' ),
						),
						'last_name'         => array(
							'type'        => 'string',
							'description' => __( 'Family name shown on the profile.', 'shim-mcp' ),
						),
						'nickname'          => array(
							'type'        => 'string',
							'description' => __( 'Nickname stored alongside the profile.', 'shim-mcp' ),
						),
						'display_name'      => array(
							'type'        => 'string',
							'description' => __( 'Name shown publicly next to this account.', 'shim-mcp' ),
						),
						'description'       => array(
							'type'        => 'string',
							'description' => __( 'Biographical text shown on author pages.', 'shim-mcp' ),
						),
						'url'               => array(
							'type'        => 'string',
							'description' => __( 'Website address to store on the profile.', 'shim-mcp' ),
						),
						'notify_new_user'   => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'Send WordPress the standard welcome email to the new account.', 'shim-mcp' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'user'    => array( 'type' => 'object' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ) use ( $shape_user, $may_grant_role, $profile_fields ): array {
					if ( ! is_array( $input ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'A username, an email address and a password are required.', 'shim-mcp' ),
						);
					}

					$raw_username = isset( $input['username'] ) && is_string( $input['username'] ) ? $input['username'] : '';
					$raw_email    = isset( $input['email'] ) && is_string( $input['email'] ) ? $input['email'] : '';
					$password     = isset( $input['password'] ) && is_string( $input['password'] ) ? $input['password'] : '';

					$username = sanitize_user( $raw_username, true );
					$email    = sanitize_email( $raw_email );

					if ( '' === $username || '' === $password ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'A username, an email address and a password are required.', 'shim-mcp' ),
						);
					}

					if ( ! is_email( $email ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'That email address is not valid.', 'shim-mcp' ),
						);
					}

					if ( username_exists( $username ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'That username is already taken.', 'shim-mcp' ),
						);
					}

					if ( email_exists( $email ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'That email address already belongs to an account.', 'shim-mcp' ),
						);
					}

					$role = isset( $input['role'] ) && is_string( $input['role'] ) && '' !== $input['role']
						? sanitize_key( $input['role'] )
						: (string) get_option( 'default_role', 'subscriber' );

					if ( ! get_role( $role ) instanceof \WP_Role ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'That role slug does not exist on this site.', 'shim-mcp' ),
						);
					}

					if ( ! $may_grant_role( $role ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You cannot create an account with a role that outranks your own.', 'shim-mcp' ),
						);
					}

					$userdata = array(
						'user_login' => $username,
						'user_email' => $email,
						'user_pass'  => $password,
						'role'       => $role,
					);

					foreach ( $profile_fields as $field => $sanitizer ) {
						if ( isset( $input[ $field ] ) && is_string( $input[ $field ] ) ) {
							$value = call_user_func( $sanitizer, $input[ $field ] );
							if ( 'url' === $field ) {
								$userdata['user_url'] = $value;
							} else {
								$userdata[ $field ] = $value;
							}
						}
					}

					$user_id = wp_insert_user( $userdata );

					if ( is_wp_error( $user_id ) ) {
						return array(
							'success' => false,
							'message' => esc_html( $user_id->get_error_message() ),
						);
					}

					$created = get_userdata( (int) $user_id );
					if ( ! $created instanceof \WP_User ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The account was created but could not be read back.', 'shim-mcp' ),
						);
					}

					if ( ! empty( $input['notify_new_user'] ) ) {
						wp_new_user_notification( (int) $user_id, null, 'both' );
					}

					return array(
						'success' => true,
						'user'    => $shape_user( $created ),
						'message' => esc_html__( 'The account was created.', 'shim-mcp' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'create_users' );
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
			'shim-mcp/users-update',
			array(
				'label'               => __( 'Modify User', 'shim-mcp' ),
				'description'         => __( 'Changes an existing account. Give it the numeric user ID plus any of the email address, password, profile fields or role you want replaced. Roles you cannot grant yourself are rejected, and you cannot change your own role.', 'shim-mcp' ),
				'category'            => 'user',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id'           => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Numeric ID of the account to change.', 'shim-mcp' ),
						),
						'email'        => array(
							'type'        => 'string',
							'minLength'   => 3,
							'description' => __( 'Replacement email address. It must not belong to another account.', 'shim-mcp' ),
						),
						'password'     => array(
							'type'        => 'string',
							'minLength'   => 6,
							'description' => __( 'New plain text password for this account.', 'shim-mcp' ),
						),
						'role'         => array(
							'type'        => 'string',
							'description' => __( 'Role slug that replaces every role currently held by this account.', 'shim-mcp' ),
						),
						'first_name'   => array(
							'type'        => 'string',
							'description' => __( 'Replacement given name.', 'shim-mcp' ),
						),
						'last_name'    => array(
							'type'        => 'string',
							'description' => __( 'Replacement family name.', 'shim-mcp' ),
						),
						'nickname'     => array(
							'type'        => 'string',
							'description' => __( 'Replacement nickname.', 'shim-mcp' ),
						),
						'display_name' => array(
							'type'        => 'string',
							'description' => __( 'Replacement public display name.', 'shim-mcp' ),
						),
						'description'  => array(
							'type'        => 'string',
							'description' => __( 'Replacement biographical text.', 'shim-mcp' ),
						),
						'url'          => array(
							'type'        => 'string',
							'description' => __( 'Replacement website address.', 'shim-mcp' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'user'    => array( 'type' => 'object' ),
						'updated' => array( 'type' => 'array' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ) use ( $shape_user, $may_grant_role, $profile_fields ): array {
					if ( ! is_array( $input ) || ! isset( $input['id'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'A numeric user ID is required.', 'shim-mcp' ),
						);
					}

					$user_id = absint( $input['id'] );
					if ( $user_id < 1 ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'A numeric user ID is required.', 'shim-mcp' ),
						);
					}

					$user = get_userdata( $user_id );
					if ( ! $user instanceof \WP_User ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'No account exists with that ID.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'edit_user', $user_id ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You are not allowed to change this account.', 'shim-mcp' ),
						);
					}

					$userdata = array( 'ID' => $user_id );
					$updated  = array();

					if ( isset( $input['email'] ) && is_string( $input['email'] ) && '' !== $input['email'] ) {
						$email = sanitize_email( $input['email'] );
						if ( ! is_email( $email ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'That email address is not valid.', 'shim-mcp' ),
							);
						}
						$owner = email_exists( $email );
						if ( false !== $owner && (int) $owner !== $user_id ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'That email address already belongs to another account.', 'shim-mcp' ),
							);
						}
						$userdata['user_email'] = $email;
						$updated[]              = 'email';
					}

					if ( isset( $input['password'] ) && is_string( $input['password'] ) && '' !== $input['password'] ) {
						$userdata['user_pass'] = $input['password'];
						$updated[]             = 'password';
					}

					foreach ( $profile_fields as $field => $sanitizer ) {
						if ( isset( $input[ $field ] ) && is_string( $input[ $field ] ) ) {
							$value = call_user_func( $sanitizer, $input[ $field ] );
							if ( 'url' === $field ) {
								$userdata['user_url'] = $value;
							} else {
								$userdata[ $field ] = $value;
							}
							$updated[] = $field;
						}
					}

					if ( isset( $input['role'] ) && is_string( $input['role'] ) && '' !== $input['role'] ) {
						$role = sanitize_key( $input['role'] );

						if ( ! get_role( $role ) instanceof \WP_Role ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'That role slug does not exist on this site.', 'shim-mcp' ),
							);
						}

						if ( get_current_user_id() === $user_id ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'You cannot change the role on your own account.', 'shim-mcp' ),
							);
						}

						if ( ! $may_grant_role( $role ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'You cannot assign a role that outranks your own.', 'shim-mcp' ),
							);
						}

						foreach ( (array) $user->roles as $held_role ) {
							if ( ! $may_grant_role( (string) $held_role ) ) {
								return array(
									'success' => false,
									'message' => esc_html__( 'This account already holds a role you are not allowed to manage.', 'shim-mcp' ),
								);
							}
						}

						$userdata['role'] = $role;
						$updated[]        = 'role';
					}

					if ( array() === $updated ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Nothing was supplied to change on this account.', 'shim-mcp' ),
						);
					}

					$result = wp_update_user( $userdata );

					if ( is_wp_error( $result ) ) {
						return array(
							'success' => false,
							'message' => esc_html( $result->get_error_message() ),
						);
					}

					$fresh = get_userdata( $user_id );
					if ( ! $fresh instanceof \WP_User ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The account was changed but could not be read back.', 'shim-mcp' ),
						);
					}

					return array(
						'success' => true,
						'user'    => $shape_user( $fresh ),
						'updated' => $updated,
						'message' => esc_html__( 'The account was updated.', 'shim-mcp' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_users' );
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
			'shim-mcp/users-delete',
			array(
				'label'               => __( 'Remove User', 'shim-mcp' ),
				'description'         => __( 'Permanently deletes an account. Posts and links owned by that account are destroyed with it unless you name another account to inherit them. You cannot delete yourself.', 'shim-mcp' ),
				'category'            => 'user',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id'          => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Numeric ID of the account to delete.', 'shim-mcp' ),
						),
						'reassign_to' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Numeric ID of the account that inherits the deleted account content. Leave it out to destroy that content.', 'shim-mcp' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'     => array( 'type' => 'boolean' ),
						'id'          => array( 'type' => 'integer' ),
						'username'    => array( 'type' => 'string' ),
						'reassign_to' => array( 'type' => 'integer' ),
						'message'     => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ) use ( $may_grant_role, $load_admin_user_api ): array {
					if ( ! is_array( $input ) || ! isset( $input['id'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'A numeric user ID is required.', 'shim-mcp' ),
						);
					}

					$user_id = absint( $input['id'] );
					if ( $user_id < 1 ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'A numeric user ID is required.', 'shim-mcp' ),
						);
					}

					$user = get_userdata( $user_id );
					if ( ! $user instanceof \WP_User ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'No account exists with that ID.', 'shim-mcp' ),
						);
					}

					if ( get_current_user_id() === $user_id ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You cannot delete the account you are signed in as.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'delete_user', $user_id ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You are not allowed to delete this account.', 'shim-mcp' ),
						);
					}

					foreach ( (array) $user->roles as $held_role ) {
						if ( ! $may_grant_role( (string) $held_role ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'This account holds a role you are not allowed to manage.', 'shim-mcp' ),
							);
						}
					}

					$reassign = null;
					if ( isset( $input['reassign_to'] ) ) {
						$reassign = absint( $input['reassign_to'] );
						if ( $reassign < 1 || ! get_userdata( $reassign ) instanceof \WP_User ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The account named to inherit the content does not exist.', 'shim-mcp' ),
							);
						}
						if ( $reassign === $user_id ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'Content cannot be reassigned to the account being deleted.', 'shim-mcp' ),
							);
						}
					}

					$username = (string) $user->user_login;

					$load_admin_user_api();
					$deleted = wp_delete_user( $user_id, $reassign );

					if ( true !== $deleted ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'WordPress refused to delete that account.', 'shim-mcp' ),
						);
					}

					return array(
						'success'     => true,
						'id'          => $user_id,
						'username'    => esc_html( $username ),
						'reassign_to' => null === $reassign ? 0 : $reassign,
						'message'     => esc_html__( 'The account was deleted.', 'shim-mcp' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'delete_users' );
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
