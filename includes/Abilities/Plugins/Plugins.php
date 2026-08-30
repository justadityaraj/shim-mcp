<?php
declare(strict_types=1);

namespace ShimMcp\Abilities\Plugins;

class Plugins {

	public static function register(): void {

		wp_register_ability(
			'shim-mcp/plugins-list',
			array(
				'label'               => 'List Installed Plugins',
				'description'         => 'Returns every plugin present in the plugins directory together with its name, version, author, whether it is currently active and whether a newer version is waiting to be installed. Optionally narrows the result to a text match on the plugin name or folder.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array(),
					'properties'           => array(
						'search'       => array(
							'type'        => 'string',
							'description' => 'Case-insensitive substring matched against the plugin name and its file path.',
						),
						'status'       => array(
							'type'        => 'string',
							'enum'        => array( 'all', 'active', 'inactive', 'update-available' ),
							'description' => 'Restricts the listing to plugins in the given state. Defaults to all.',
						),
						'network_wide' => array(
							'type'        => 'boolean',
							'description' => 'On multisite, treat network-activated plugins as active. Ignored on a single site.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'count'   => array( 'type' => 'integer' ),
						'plugins' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'plugin_file'      => array( 'type' => 'string' ),
									'name'             => array( 'type' => 'string' ),
									'version'          => array( 'type' => 'string' ),
									'author'           => array( 'type' => 'string' ),
									'description'      => array( 'type' => 'string' ),
									'plugin_uri'       => array( 'type' => 'string' ),
									'requires_wp'      => array( 'type' => 'string' ),
									'requires_php'     => array( 'type' => 'string' ),
									'active'           => array( 'type' => 'boolean' ),
									'network_active'   => array( 'type' => 'boolean' ),
									'update_available' => array( 'type' => 'boolean' ),
									'new_version'      => array( 'type' => 'string' ),
								),
							),
						),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) ) {
						$input = array();
					}

					if ( ! function_exists( 'get_plugins' ) ) {
						require_once ABSPATH . 'wp-admin/includes/plugin.php';
					}

					$search = isset( $input['search'] ) && is_string( $input['search'] )
						? strtolower( sanitize_text_field( $input['search'] ) )
						: '';

					$status = isset( $input['status'] ) && is_string( $input['status'] )
						? sanitize_text_field( $input['status'] )
						: 'all';

					if ( ! in_array( $status, array( 'all', 'active', 'inactive', 'update-available' ), true ) ) {
						$status = 'all';
					}

					$treat_network = ! empty( $input['network_wide'] ) && is_multisite();

					$installed = get_plugins();
					$updates   = get_site_transient( 'update_plugins' );
					$pending   = ( is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response ) )
						? $updates->response
						: array();

					$rows = array();

					foreach ( $installed as $plugin_file => $data ) {
						$name = isset( $data['Name'] ) ? (string) $data['Name'] : '';

						if ( '' !== $search ) {
							$haystack = strtolower( $name . ' ' . $plugin_file );
							if ( false === strpos( $haystack, $search ) ) {
								continue;
							}
						}

						$is_network_active = is_multisite() && is_plugin_active_for_network( $plugin_file );
						$is_active         = is_plugin_active( $plugin_file ) || ( $treat_network && $is_network_active );
						$has_update        = isset( $pending[ $plugin_file ] );

						if ( 'active' === $status && ! $is_active ) {
							continue;
						}
						if ( 'inactive' === $status && $is_active ) {
							continue;
						}
						if ( 'update-available' === $status && ! $has_update ) {
							continue;
						}

						$new_version = '';
						if ( $has_update && isset( $pending[ $plugin_file ]->new_version ) ) {
							$new_version = (string) $pending[ $plugin_file ]->new_version;
						}

						$rows[] = array(
							'plugin_file'      => $plugin_file,
							'name'             => esc_html( $name ),
							'version'          => isset( $data['Version'] ) ? (string) $data['Version'] : '',
							'author'           => isset( $data['Author'] ) ? esc_html( wp_strip_all_tags( (string) $data['Author'] ) ) : '',
							'description'      => isset( $data['Description'] ) ? esc_html( wp_strip_all_tags( (string) $data['Description'] ) ) : '',
							'plugin_uri'       => isset( $data['PluginURI'] ) ? esc_url_raw( (string) $data['PluginURI'] ) : '',
							'requires_wp'      => isset( $data['RequiresWP'] ) ? (string) $data['RequiresWP'] : '',
							'requires_php'     => isset( $data['RequiresPHP'] ) ? (string) $data['RequiresPHP'] : '',
							'active'           => $is_active,
							'network_active'   => $is_network_active,
							'update_available' => $has_update,
							'new_version'      => $new_version,
						);
					}

					return array(
						'success' => true,
						'count'   => count( $rows ),
						'plugins' => $rows,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'activate_plugins' );
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
			'shim-mcp/plugins-activate',
			array(
				'label'               => 'Activate Plugin',
				'description'         => 'Switches on an installed plugin identified by its file path relative to the plugins directory, for example akismet/akismet.php. On multisite the plugin can be turned on for the whole network.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'plugin_file' ),
					'properties'           => array(
						'plugin_file'  => array(
							'type'        => 'string',
							'description' => 'Path of the main plugin file relative to wp-content/plugins, such as hello-dolly/hello.php.',
						),
						'network_wide' => array(
							'type'        => 'boolean',
							'description' => 'Activate across every site of the network. Only meaningful on multisite.',
						),
						'silent'       => array(
							'type'        => 'boolean',
							'description' => 'Skip the plugin activation hooks. Leave this off unless you know the plugin tolerates it.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'     => array( 'type' => 'boolean' ),
						'plugin_file' => array( 'type' => 'string' ),
						'name'        => array( 'type' => 'string' ),
						'active'      => array( 'type' => 'boolean' ),
						'message'     => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['plugin_file'] ) || ! is_string( $input['plugin_file'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Name the plugin file you want to activate.', 'shim-mcp' ),
						);
					}

					require_once ABSPATH . 'wp-admin/includes/plugin.php';
					require_once ABSPATH . 'wp-admin/includes/file.php';

					$plugin_file = plugin_basename( trim( $input['plugin_file'] ) );

					if ( 0 !== validate_file( $plugin_file ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'That plugin path is not allowed.', 'shim-mcp' ),
						);
					}

					$installed = get_plugins();
					if ( ! isset( $installed[ $plugin_file ] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'No installed plugin matches that path.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'activate_plugin', $plugin_file ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You are not allowed to activate this plugin.', 'shim-mcp' ),
						);
					}

					$network_wide = ! empty( $input['network_wide'] ) && is_multisite();
					$silent       = ! empty( $input['silent'] );

					if ( $network_wide && ! current_user_can( 'manage_network_plugins' ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You are not allowed to activate plugins across the network.', 'shim-mcp' ),
						);
					}

					if ( is_plugin_active( $plugin_file ) || ( $network_wide && is_plugin_active_for_network( $plugin_file ) ) ) {
						return array(
							'success'     => true,
							'plugin_file' => $plugin_file,
							'name'        => esc_html( (string) $installed[ $plugin_file ]['Name'] ),
							'active'      => true,
							'message'     => esc_html__( 'That plugin was already running.', 'shim-mcp' ),
						);
					}

					$result = activate_plugin( $plugin_file, '', $network_wide, $silent );

					if ( is_wp_error( $result ) ) {
						return array(
							'success' => false,
							'message' => esc_html( $result->get_error_message() ),
						);
					}

					return array(
						'success'     => true,
						'plugin_file' => $plugin_file,
						'name'        => esc_html( (string) $installed[ $plugin_file ]['Name'] ),
						'active'      => true,
						'message'     => esc_html__( 'The plugin is now active.', 'shim-mcp' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'activate_plugins' );
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
			'shim-mcp/plugins-deactivate',
			array(
				'label'               => 'Deactivate Plugin',
				'description'         => 'Turns off a running plugin identified by its file path relative to the plugins directory. The plugin files stay on disk and its settings are untouched.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'plugin_file' ),
					'properties'           => array(
						'plugin_file'  => array(
							'type'        => 'string',
							'description' => 'Path of the main plugin file relative to wp-content/plugins.',
						),
						'network_wide' => array(
							'type'        => 'boolean',
							'description' => 'Remove the network-wide activation as well. Only meaningful on multisite.',
						),
						'silent'       => array(
							'type'        => 'boolean',
							'description' => 'Skip the plugin deactivation hooks.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'     => array( 'type' => 'boolean' ),
						'plugin_file' => array( 'type' => 'string' ),
						'name'        => array( 'type' => 'string' ),
						'active'      => array( 'type' => 'boolean' ),
						'message'     => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['plugin_file'] ) || ! is_string( $input['plugin_file'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Name the plugin file you want to switch off.', 'shim-mcp' ),
						);
					}

					require_once ABSPATH . 'wp-admin/includes/plugin.php';

					$plugin_file = plugin_basename( trim( $input['plugin_file'] ) );

					if ( 0 !== validate_file( $plugin_file ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'That plugin path is not allowed.', 'shim-mcp' ),
						);
					}

					$installed = get_plugins();
					if ( ! isset( $installed[ $plugin_file ] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'No installed plugin matches that path.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'deactivate_plugin', $plugin_file ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You are not allowed to deactivate this plugin.', 'shim-mcp' ),
						);
					}

					$network_wide = ! empty( $input['network_wide'] ) && is_multisite();

					if ( $network_wide && ! current_user_can( 'manage_network_plugins' ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You are not allowed to change network plugin activation.', 'shim-mcp' ),
						);
					}

					$was_active = is_plugin_active( $plugin_file ) || is_plugin_active_for_network( $plugin_file );

					if ( ! $was_active ) {
						return array(
							'success'     => true,
							'plugin_file' => $plugin_file,
							'name'        => esc_html( (string) $installed[ $plugin_file ]['Name'] ),
							'active'      => false,
							'message'     => esc_html__( 'That plugin was already switched off.', 'shim-mcp' ),
						);
					}

					deactivate_plugins( array( $plugin_file ), ! empty( $input['silent'] ), $network_wide ? true : null );

					$still_active = is_plugin_active( $plugin_file ) || is_plugin_active_for_network( $plugin_file );

					return array(
						'success'     => ! $still_active,
						'plugin_file' => $plugin_file,
						'name'        => esc_html( (string) $installed[ $plugin_file ]['Name'] ),
						'active'      => $still_active,
						'message'     => $still_active
							? esc_html__( 'WordPress reports the plugin is still active.', 'shim-mcp' )
							: esc_html__( 'The plugin is now switched off.', 'shim-mcp' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'activate_plugins' );
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
			'shim-mcp/plugins-delete',
			array(
				'label'               => 'Delete Plugin',
				'description'         => 'Removes an installed plugin and its folder from the server permanently. An active plugin is refused unless you ask for it to be deactivated first, and the caller must confirm the removal.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'plugin_file', 'confirm' ),
					'properties'           => array(
						'plugin_file'      => array(
							'type'        => 'string',
							'description' => 'Path of the main plugin file relative to wp-content/plugins.',
						),
						'confirm'          => array(
							'type'        => 'boolean',
							'description' => 'Must be true. Files are erased from disk and cannot be recovered from WordPress.',
						),
						'deactivate_first' => array(
							'type'        => 'boolean',
							'description' => 'Switch the plugin off before deleting it instead of refusing an active plugin.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'     => array( 'type' => 'boolean' ),
						'plugin_file' => array( 'type' => 'string' ),
						'name'        => array( 'type' => 'string' ),
						'deleted'     => array( 'type' => 'boolean' ),
						'message'     => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['plugin_file'] ) || ! is_string( $input['plugin_file'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Name the plugin file you want to remove.', 'shim-mcp' ),
						);
					}

					if ( empty( $input['confirm'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Set confirm to true to erase the plugin files.', 'shim-mcp' ),
						);
					}

					require_once ABSPATH . 'wp-admin/includes/file.php';
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
					require_once ABSPATH . 'wp-admin/includes/misc.php';
					require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

					$plugin_file = plugin_basename( trim( $input['plugin_file'] ) );

					if ( 0 !== validate_file( $plugin_file ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'That plugin path is not allowed.', 'shim-mcp' ),
						);
					}

					$installed = get_plugins();
					if ( ! isset( $installed[ $plugin_file ] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'No installed plugin matches that path.', 'shim-mcp' ),
						);
					}

					if ( ! current_user_can( 'delete_plugins' ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'You are not allowed to delete plugins on this site.', 'shim-mcp' ),
						);
					}

					$name       = esc_html( (string) $installed[ $plugin_file ]['Name'] );
					$was_active = is_plugin_active( $plugin_file ) || is_plugin_active_for_network( $plugin_file );

					if ( $was_active ) {
						if ( empty( $input['deactivate_first'] ) ) {
							return array(
								'success'     => false,
								'plugin_file' => $plugin_file,
								'name'        => $name,
								'deleted'     => false,
								'message'     => esc_html__( 'This plugin is running. Deactivate it first or pass deactivate_first.', 'shim-mcp' ),
							);
						}

						if ( ! current_user_can( 'deactivate_plugin', $plugin_file ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'You are not allowed to deactivate this plugin.', 'shim-mcp' ),
							);
						}

						deactivate_plugins( array( $plugin_file ), false, is_plugin_active_for_network( $plugin_file ) ? true : null );
					}

					$result = delete_plugins( array( $plugin_file ) );

					if ( is_wp_error( $result ) ) {
						return array(
							'success' => false,
							'message' => esc_html( $result->get_error_message() ),
						);
					}

					if ( null === $result ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'WordPress needs filesystem credentials before it can remove plugin files.', 'shim-mcp' ),
						);
					}

					if ( false === $result ) {
						return array(
							'success'     => false,
							'plugin_file' => $plugin_file,
							'name'        => $name,
							'deleted'     => false,
							'message'     => esc_html__( 'The plugin folder could not be removed.', 'shim-mcp' ),
						);
					}

					return array(
						'success'     => true,
						'plugin_file' => $plugin_file,
						'name'        => $name,
						'deleted'     => true,
						'message'     => esc_html__( 'The plugin was removed from the server.', 'shim-mcp' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'delete_plugins' );
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
