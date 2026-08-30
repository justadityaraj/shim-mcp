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
						'search'      => array(
							'type'        => 'string',
							'description' => 'Case-insensitive substring matched against the plugin name and its file path.',
						),
						'status'      => array(
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
			'shim-mcp/plugins-install-from-zip',
			array(
				'label'               => 'Install Plugin From Uploaded Zip',
				'description'         => 'Takes the raw bytes of a plugin zip archive encoded as base64, writes them to a temporary file and runs the WordPress plugin installer against it. The plugin is left inactive unless activation is requested.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'zip_base64' ),
					'properties'           => array(
						'zip_base64'       => array(
							'type'        => 'string',
							'description' => 'The complete zip archive encoded with standard base64.',
						),
						'filename'         => array(
							'type'        => 'string',
							'description' => 'A name to give the temporary archive, useful for readable error output. Defaults to a generated name.',
						),
						'activate'         => array(
							'type'        => 'boolean',
							'description' => 'Set to true to activate the plugin once the files are in place.',
						),
						'overwrite'        => array(
							'type'        => 'boolean',
							'description' => 'Allow the installer to replace a plugin that already occupies the same folder.',
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
						'version'     => array( 'type' => 'string' ),
						'activated'   => array( 'type' => 'boolean' ),
						'message'     => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['zip_base64'] ) || ! is_string( $input['zip_base64'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Provide the plugin archive as a base64 string.', 'shim-mcp' ),
						);
					}

					$encoded = preg_replace( '/\s+/', '', $input['zip_base64'] );
					if ( ! is_string( $encoded ) || '' === $encoded ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The base64 payload was empty.', 'shim-mcp' ),
						);
					}

					$bytes = base64_decode( $encoded, true );
					if ( false === $bytes || '' === $bytes ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The base64 payload could not be decoded.', 'shim-mcp' ),
						);
					}

					if ( 'PK' !== substr( $bytes, 0, 2 ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The decoded bytes do not look like a zip archive.', 'shim-mcp' ),
						);
					}

					require_once ABSPATH . 'wp-admin/includes/file.php';
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
					require_once ABSPATH . 'wp-admin/includes/misc.php';
					require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
					require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';

					$filename = isset( $input['filename'] ) && is_string( $input['filename'] )
						? sanitize_file_name( $input['filename'] )
						: '';

					if ( '' === $filename || '.zip' !== strtolower( substr( $filename, -4 ) ) ) {
						$filename = 'shim-mcp-plugin-' . wp_generate_password( 8, false, false ) . '.zip';
					}

					$tmp_file = wp_tempnam( $filename );
					if ( ! $tmp_file ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'A temporary file could not be created for the upload.', 'shim-mcp' ),
						);
					}

					$written = file_put_contents( $tmp_file, $bytes );
					if ( false === $written ) {
						wp_delete_file( $tmp_file );
						return array(
							'success' => false,
							'message' => esc_html__( 'The archive could not be written to disk.', 'shim-mcp' ),
						);
					}

					$before = array_keys( get_plugins() );

					$skin     = new \WP_Ajax_Upgrader_Skin();
					$upgrader = new \Plugin_Upgrader( $skin );

					$args = array();
					if ( ! empty( $input['overwrite'] ) ) {
						$args['overwrite_package'] = true;
						$args['clear_destination']  = true;
					}

					$result = $upgrader->install( $tmp_file, $args );

					wp_delete_file( $tmp_file );

					if ( is_wp_error( $result ) ) {
						return array(
							'success' => false,
							'message' => esc_html( $result->get_error_message() ),
						);
					}

					if ( is_wp_error( $skin->result ) ) {
						return array(
							'success' => false,
							'message' => esc_html( $skin->result->get_error_message() ),
						);
					}

					if ( false === $result ) {
						$errors = $skin->get_errors();
						$reason = ( is_wp_error( $errors ) && $errors->has_errors() )
							? $errors->get_error_message()
							: __( 'The installer did not report a reason.', 'shim-mcp' );

						return array(
							'success' => false,
							'message' => esc_html( $reason ),
						);
					}

					$plugin_file = $upgrader->plugin_info();
					if ( ! is_string( $plugin_file ) || '' === $plugin_file ) {
						$after = array_keys( get_plugins() );
						$added = array_values( array_diff( $after, $before ) );
						$plugin_file = isset( $added[0] ) ? (string) $added[0] : '';
					}

					if ( '' === $plugin_file ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The files were unpacked but the main plugin file could not be identified.', 'shim-mcp' ),
						);
					}

					$installed = get_plugins();
					$header    = isset( $installed[ $plugin_file ] ) ? $installed[ $plugin_file ] : array();

					$activated = false;
					if ( ! empty( $input['activate'] ) ) {
						$activation = activate_plugin( $plugin_file );
						if ( is_wp_error( $activation ) ) {
							return array(
								'success'     => true,
								'plugin_file' => $plugin_file,
								'name'        => isset( $header['Name'] ) ? esc_html( (string) $header['Name'] ) : '',
								'version'     => isset( $header['Version'] ) ? (string) $header['Version'] : '',
								'activated'   => false,
								'message'     => esc_html( $activation->get_error_message() ),
							);
						}
						$activated = true;
					}

					return array(
						'success'     => true,
						'plugin_file' => $plugin_file,
						'name'        => isset( $header['Name'] ) ? esc_html( (string) $header['Name'] ) : '',
						'version'     => isset( $header['Version'] ) ? (string) $header['Version'] : '',
						'activated'   => $activated,
						'message'     => esc_html__( 'The plugin was installed.', 'shim-mcp' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'install_plugins' );
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
			'shim-mcp/plugins-install-from-url',
			array(
				'label'               => 'Install Plugin From Remote Zip',
				'description'         => 'Downloads a plugin zip from an http or https address and installs it. The archive is fetched to a temporary file which is removed once the installer finishes. Activation after install is optional.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'url' ),
					'properties'           => array(
						'url'       => array(
							'type'        => 'string',
							'description' => 'A direct http or https link to the plugin zip archive.',
						),
						'activate'  => array(
							'type'        => 'boolean',
							'description' => 'Set to true to activate the plugin once the files are in place.',
						),
						'overwrite' => array(
							'type'        => 'boolean',
							'description' => 'Allow the installer to replace a plugin that already occupies the same folder.',
						),
						'timeout'   => array(
							'type'        => 'integer',
							'description' => 'Seconds to wait for the download before giving up. Between 5 and 300, defaulting to 300.',
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
						'version'     => array( 'type' => 'string' ),
						'activated'   => array( 'type' => 'boolean' ),
						'message'     => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['url'] ) || ! is_string( $input['url'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Provide the address of the plugin archive.', 'shim-mcp' ),
						);
					}

					$url = esc_url_raw( trim( $input['url'] ) );
					if ( '' === $url || ! wp_http_validate_url( $url ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'That address is not a usable download link.', 'shim-mcp' ),
						);
					}

					$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
					if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Only http and https downloads are accepted.', 'shim-mcp' ),
						);
					}

					require_once ABSPATH . 'wp-admin/includes/file.php';
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
					require_once ABSPATH . 'wp-admin/includes/misc.php';
					require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
					require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';

					$timeout = isset( $input['timeout'] ) ? absint( $input['timeout'] ) : 300;
					if ( $timeout < 5 || $timeout > 300 ) {
						$timeout = 300;
					}

					$downloaded = download_url( $url, $timeout );
					if ( is_wp_error( $downloaded ) ) {
						return array(
							'success' => false,
							'message' => esc_html( $downloaded->get_error_message() ),
						);
					}

					$handle = fopen( $downloaded, 'rb' );
					$magic  = $handle ? fread( $handle, 2 ) : '';
					if ( $handle ) {
						fclose( $handle );
					}

					if ( 'PK' !== $magic ) {
						wp_delete_file( $downloaded );
						return array(
							'success' => false,
							'message' => esc_html__( 'The downloaded file is not a zip archive.', 'shim-mcp' ),
						);
					}

					$before = array_keys( get_plugins() );

					$skin     = new \WP_Ajax_Upgrader_Skin();
					$upgrader = new \Plugin_Upgrader( $skin );

					$args = array();
					if ( ! empty( $input['overwrite'] ) ) {
						$args['overwrite_package'] = true;
						$args['clear_destination']  = true;
					}

					$result = $upgrader->install( $downloaded, $args );

					wp_delete_file( $downloaded );

					if ( is_wp_error( $result ) ) {
						return array(
							'success' => false,
							'message' => esc_html( $result->get_error_message() ),
						);
					}

					if ( is_wp_error( $skin->result ) ) {
						return array(
							'success' => false,
							'message' => esc_html( $skin->result->get_error_message() ),
						);
					}

					if ( false === $result ) {
						$errors = $skin->get_errors();
						$reason = ( is_wp_error( $errors ) && $errors->has_errors() )
							? $errors->get_error_message()
							: __( 'The installer did not report a reason.', 'shim-mcp' );

						return array(
							'success' => false,
							'message' => esc_html( $reason ),
						);
					}

					$plugin_file = $upgrader->plugin_info();
					if ( ! is_string( $plugin_file ) || '' === $plugin_file ) {
						$after       = array_keys( get_plugins() );
						$added       = array_values( array_diff( $after, $before ) );
						$plugin_file = isset( $added[0] ) ? (string) $added[0] : '';
					}

					if ( '' === $plugin_file ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The files were unpacked but the main plugin file could not be identified.', 'shim-mcp' ),
						);
					}

					$installed = get_plugins();
					$header    = isset( $installed[ $plugin_file ] ) ? $installed[ $plugin_file ] : array();

					$activated = false;
					if ( ! empty( $input['activate'] ) ) {
						$activation = activate_plugin( $plugin_file );
						if ( is_wp_error( $activation ) ) {
							return array(
								'success'     => true,
								'plugin_file' => $plugin_file,
								'name'        => isset( $header['Name'] ) ? esc_html( (string) $header['Name'] ) : '',
								'version'     => isset( $header['Version'] ) ? (string) $header['Version'] : '',
								'activated'   => false,
								'message'     => esc_html( $activation->get_error_message() ),
							);
						}
						$activated = true;
					}

					return array(
						'success'     => true,
						'plugin_file' => $plugin_file,
						'name'        => isset( $header['Name'] ) ? esc_html( (string) $header['Name'] ) : '',
						'version'     => isset( $header['Version'] ) ? (string) $header['Version'] : '',
						'activated'   => $activated,
						'message'     => esc_html__( 'The plugin was downloaded and installed.', 'shim-mcp' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'install_plugins' );
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
						'plugin_file'       => array(
							'type'        => 'string',
							'description' => 'Path of the main plugin file relative to wp-content/plugins.',
						),
						'confirm'           => array(
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
