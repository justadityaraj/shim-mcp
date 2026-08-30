<?php
declare(strict_types=1);

namespace ShimMcp\Abilities\System;

class System {

	private static function config_writes_allowed(): bool {
		return defined( 'SHIM_MCP_ALLOW_CONFIG_WRITES' ) && SHIM_MCP_ALLOW_CONFIG_WRITES;
	}

	public static function register(): void {
		wp_register_ability(
			'shim-mcp/system-environment',
			array(
				'label'               => 'Site Environment Report',
				'description'         => 'Reports the WordPress and PHP versions, the active theme, the site and home addresses, whether multisite is enabled, the configured PHP memory limit, and how many plugins are currently active. It takes no arguments.',
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
						'success'        => array( 'type' => 'boolean' ),
						'wp_version'     => array( 'type' => 'string' ),
						'php_version'    => array( 'type' => 'string' ),
						'theme_name'     => array( 'type' => 'string' ),
						'theme_version'  => array( 'type' => 'string' ),
						'theme_slug'     => array( 'type' => 'string' ),
						'site_url'       => array( 'type' => 'string' ),
						'home_url'       => array( 'type' => 'string' ),
						'is_multisite'   => array( 'type' => 'boolean' ),
						'memory_limit'   => array( 'type' => 'string' ),
						'active_plugins' => array( 'type' => 'integer' ),
						'debug_enabled'  => array( 'type' => 'boolean' ),
						'message'        => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					unset( $input );

					$theme = wp_get_theme();

					if ( ! function_exists( 'get_plugins' ) ) {
						require_once ABSPATH . 'wp-admin/includes/plugin.php';
					}

					$active = get_option( 'active_plugins' );
					$count  = is_array( $active ) ? count( $active ) : 0;

					if ( is_multisite() ) {
						$network = get_site_option( 'active_sitewide_plugins' );
						if ( is_array( $network ) ) {
							$count += count( $network );
						}
					}

					return array(
						'success'        => true,
						'wp_version'     => esc_html( get_bloginfo( 'version' ) ),
						'php_version'    => esc_html( PHP_VERSION ),
						'theme_name'     => esc_html( (string) $theme->get( 'Name' ) ),
						'theme_version'  => esc_html( (string) $theme->get( 'Version' ) ),
						'theme_slug'     => esc_html( (string) $theme->get_stylesheet() ),
						'site_url'       => esc_url_raw( site_url() ),
						'home_url'       => esc_url_raw( home_url() ),
						'is_multisite'   => is_multisite(),
						'memory_limit'   => esc_html( (string) ini_get( 'memory_limit' ) ),
						'active_plugins' => $count,
						'debug_enabled'  => defined( 'WP_DEBUG' ) && WP_DEBUG,
						'message'        => esc_html__( 'Environment details collected.', 'shim-mcp' ),
					);
				},
				'permission_callback' => function (): bool {
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
			'shim-mcp/system-read-debug-log',
			array(
				'label'               => 'Read Debug Log Tail',
				'description'         => 'Returns the most recent lines of the WordPress debug log. You may choose how many lines to return and supply a plain substring that a line must contain to be included. It reports a clear failure when debug logging is switched off or the log file does not exist.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array(),
					'properties'           => array(
						'lines'    => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 1000,
							'default'     => 100,
							'description' => 'How many trailing lines to return, up to one thousand.',
						),
						'contains' => array(
							'type'        => 'string',
							'description' => 'Keep only lines containing this text. Matching ignores letter case.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'        => array( 'type' => 'boolean' ),
						'path'           => array( 'type' => 'string' ),
						'size_bytes'     => array( 'type' => 'integer' ),
						'returned_lines' => array( 'type' => 'integer' ),
						'entries'        => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'message'        => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) ) {
						$input = array();
					}

					$limit = isset( $input['lines'] ) ? absint( $input['lines'] ) : 100;
					if ( $limit < 1 ) {
						$limit = 100;
					}
					if ( $limit > 1000 ) {
						$limit = 1000;
					}

					$needle = '';
					if ( isset( $input['contains'] ) ) {
						if ( ! is_string( $input['contains'] ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The filter text must be a string.', 'shim-mcp' ),
							);
						}
						$needle = sanitize_text_field( $input['contains'] );
					}

					if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Debug logging is turned off, so there is no log to read.', 'shim-mcp' ),
						);
					}

					$path = is_string( WP_DEBUG_LOG ) ? WP_DEBUG_LOG : WP_CONTENT_DIR . '/debug.log';

					if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'No readable debug log file was found at the configured location.', 'shim-mcp' ),
						);
					}

					$size = filesize( $path );
					if ( false === $size ) {
						$size = 0;
					}

					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- the log is read as a stream so a large debug.log is never loaded into memory; WP_Filesystem has no streaming read.
					$handle = fopen( $path, 'rb' );
					if ( false === $handle ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The debug log could not be opened for reading.', 'shim-mcp' ),
						);
					}

					$chunk  = 65536;
					$offset = $size > $chunk ? $size - $chunk : 0;
					fseek( $handle, $offset );
					$blob = stream_get_contents( $handle );
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closes the streamed log handle opened above.
					fclose( $handle );

					if ( ! is_string( $blob ) ) {
						$blob = '';
					}

					$rows = preg_split( "/\r\n|\n|\r/", $blob );
					if ( ! is_array( $rows ) ) {
						$rows = array();
					}

					if ( $offset > 0 && count( $rows ) > 0 ) {
						array_shift( $rows );
					}

					$kept = array();
					foreach ( $rows as $row ) {
						if ( '' === trim( $row ) ) {
							continue;
						}
						if ( '' !== $needle && false === stripos( $row, $needle ) ) {
							continue;
						}
						$kept[] = esc_html( $row );
					}

					$tail = array_slice( $kept, -$limit );

					return array(
						'success'        => true,
						'path'           => esc_html( $path ),
						'size_bytes'     => (int) $size,
						'returned_lines' => count( $tail ),
						'entries'        => array_values( $tail ),
						'message'        => esc_html__( 'Debug log tail retrieved.', 'shim-mcp' ),
					);
				},
				'permission_callback' => function (): bool {
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
			'shim-mcp/system-set-debug-constants',
			array(
				'label'               => 'Change Debug Constants',
				'description'         => 'Rewrites the WP_DEBUG, WP_DEBUG_LOG and WP_DEBUG_DISPLAY constants inside wp-config.php. Switched off unless SHIM_MCP_ALLOW_CONFIG_WRITES is defined as true in wp-config.php. Send only the constants you want changed; anything left out keeps its current value. The write is also refused when file editing has been locked down, when the file is not writable, or when the rewritten contents fail a safety check.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array(),
					'properties'           => array(
						'debug'         => array(
							'type'        => 'boolean',
							'description' => 'Desired value for WP_DEBUG.',
						),
						'debug_log'     => array(
							'type'        => 'boolean',
							'description' => 'Desired value for WP_DEBUG_LOG.',
						),
						'debug_display' => array(
							'type'        => 'boolean',
							'description' => 'Desired value for WP_DEBUG_DISPLAY.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'path'    => array( 'type' => 'string' ),
						'applied' => array(
							'type'       => 'object',
							'properties' => array(
								'WP_DEBUG'         => array( 'type' => 'boolean' ),
								'WP_DEBUG_LOG'     => array( 'type' => 'boolean' ),
								'WP_DEBUG_DISPLAY' => array( 'type' => 'boolean' ),
							),
						),
						'changed' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) ) {
						$input = array();
					}

					if ( ! self::config_writes_allowed() ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Writing to wp-config.php is switched off. Define SHIM_MCP_ALLOW_CONFIG_WRITES as true in wp-config.php to enable it.', 'shim-mcp' ),
						);
					}

					if ( ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) || ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'This site forbids editing files from the dashboard, so wp-config.php will not be touched.', 'shim-mcp' ),
						);
					}

					$wanted = array();
					$map    = array(
						'debug'         => 'WP_DEBUG',
						'debug_log'     => 'WP_DEBUG_LOG',
						'debug_display' => 'WP_DEBUG_DISPLAY',
					);

					foreach ( $map as $key => $constant ) {
						if ( ! array_key_exists( $key, $input ) ) {
							continue;
						}
						if ( ! is_bool( $input[ $key ] ) ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'Each debug setting must be given as true or false.', 'shim-mcp' ),
							);
						}
						$wanted[ $constant ] = $input[ $key ];
					}

					if ( empty( $wanted ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Name at least one debug constant to change.', 'shim-mcp' ),
						);
					}

					$path = ABSPATH . 'wp-config.php';
					if ( ! file_exists( $path ) ) {
						$parent = dirname( ABSPATH ) . '/wp-config.php';
						if ( file_exists( $parent ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
							$path = $parent;
						}
					}

					if ( ! file_exists( $path ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The wp-config.php file could not be located.', 'shim-mcp' ),
						);
					}

					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- pre-flight check before the guarded wp-config.php write below.
					if ( ! is_writable( $path ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The wp-config.php file is not writable by the web server.', 'shim-mcp' ),
						);
					}

					if ( ! function_exists( 'WP_Filesystem' ) ) {
						require_once ABSPATH . 'wp-admin/includes/file.php';
					}

					$ready = WP_Filesystem();
					global $wp_filesystem;

					if ( ! $ready || ! ( $wp_filesystem instanceof \WP_Filesystem_Base ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The WordPress filesystem layer is unavailable, so no write was attempted.', 'shim-mcp' ),
						);
					}

					$original = $wp_filesystem->get_contents( $path );
					if ( ! is_string( $original ) || '' === $original ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The current contents of wp-config.php could not be read.', 'shim-mcp' ),
						);
					}

					$updated = $original;
					$changed = array();
					$applied = array();

					foreach ( $wanted as $constant => $value ) {
						$literal = $value ? 'true' : 'false';
						$line    = "define( '" . $constant . "', " . $literal . ' );';
						$pattern = "/^[ \t]*define\s*\(\s*(['\"])" . preg_quote( $constant, '/' ) . "\\1\s*,.*?\)\s*;[ \t]*$/mi";

						$result = preg_replace( $pattern, $line, $updated, 1, $hits );

						if ( null === $result ) {
							return array(
								'success' => false,
								'message' => esc_html__( 'The configuration file could not be parsed safely, so nothing was written.', 'shim-mcp' ),
							);
						}

						if ( 0 === $hits ) {
							$anchor = "/^([ \t]*)(\/\* That's all, stop editing.*)$/mi";
							$result = preg_replace( $anchor, $line . "\n\n" . '${1}${2}', $updated, 1, $anchored );

							if ( null === $result || 0 === $anchored ) {
								return array(
									'success' => false,
									'message' => esc_html__( 'No safe place was found in wp-config.php to add the missing constant.', 'shim-mcp' ),
								);
							}
						}

						$updated              = $result;
						$changed[]            = $constant;
						$applied[ $constant ] = $value;
					}

					if ( 0 !== strpos( ltrim( $updated ), '<?php' ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The rewritten configuration no longer starts with a PHP opening tag, so the write was cancelled.', 'shim-mcp' ),
						);
					}

					if ( strlen( $updated ) < (int) ( strlen( $original ) * 0.8 ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The rewritten configuration shrank unexpectedly, so the write was cancelled.', 'shim-mcp' ),
						);
					}

					if ( $updated === $original ) {
						return array(
							'success' => true,
							'path'    => esc_html( basename( $path ) ),
							'applied' => $applied,
							'changed' => array(),
							'message' => esc_html__( 'The requested values already matched the file, so nothing was written.', 'shim-mcp' ),
						);
					}

					$written = $wp_filesystem->put_contents( $path, $updated, FS_CHMOD_FILE );

					if ( ! $written ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Writing the updated wp-config.php failed and the original file is untouched.', 'shim-mcp' ),
						);
					}

					return array(
						'success' => true,
						'path'    => esc_html( basename( $path ) ),
						'applied' => $applied,
						'changed' => $changed,
						'message' => esc_html__( 'The debug constants were updated in wp-config.php.', 'shim-mcp' ),
					);
				},
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' ) && self::config_writes_allowed();
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
