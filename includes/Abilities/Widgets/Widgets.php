<?php
declare(strict_types=1);

namespace ShimMcp\Abilities\Widgets;

class Widgets {

	public static function register(): void {
		wp_register_ability(
			'shim-mcp/widgets-list-sidebars',
			array(
				'label'               => 'List Sidebars',
				'description'         => 'Returns every widget area the current theme has registered, giving the identifier, display name and description for each one. Takes no input.',
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
						'success'   => array( 'type' => 'boolean' ),
						'count'     => array( 'type' => 'integer' ),
						'sidebars'  => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'          => array( 'type' => 'string' ),
									'name'        => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
									'class'       => array( 'type' => 'string' ),
									'widget_count' => array( 'type' => 'integer' ),
								),
							),
						),
						'message'   => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					global $wp_registered_sidebars;

					if ( ! is_array( $wp_registered_sidebars ) ) {
						return array(
							'success'  => true,
							'count'    => 0,
							'sidebars' => array(),
						);
					}

					$placement = get_option( 'sidebars_widgets' );
					if ( ! is_array( $placement ) ) {
						$placement = array();
					}

					$sidebars = array();

					foreach ( $wp_registered_sidebars as $sidebar_id => $sidebar ) {
						if ( ! is_array( $sidebar ) ) {
							continue;
						}

						$id      = (string) $sidebar_id;
						$assigned = isset( $placement[ $id ] ) && is_array( $placement[ $id ] ) ? $placement[ $id ] : array();

						$sidebars[] = array(
							'id'           => esc_html( $id ),
							'name'         => esc_html( isset( $sidebar['name'] ) ? (string) $sidebar['name'] : '' ),
							'description'  => esc_html( isset( $sidebar['description'] ) ? (string) $sidebar['description'] : '' ),
							'class'        => esc_html( isset( $sidebar['class'] ) ? (string) $sidebar['class'] : '' ),
							'widget_count' => count( $assigned ),
						);
					}

					return array(
						'success'  => true,
						'count'    => count( $sidebars ),
						'sidebars' => $sidebars,
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
			'shim-mcp/widgets-list-types',
			array(
				'label'               => 'List Widget Types',
				'description'         => 'Returns the catalogue of widget types this installation offers, so a caller can see what kinds of widgets could be added to a widget area. Takes no input.',
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
						'success'      => array( 'type' => 'boolean' ),
						'count'        => array( 'type' => 'integer' ),
						'widget_types' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id_base'     => array( 'type' => 'string' ),
									'name'        => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
									'classname'   => array( 'type' => 'string' ),
								),
							),
						),
						'message'      => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! function_exists( 'wp_get_widget_defaults' ) && ! class_exists( 'WP_Widget_Factory' ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The widget subsystem is not loaded on this request.', 'shim-mcp' ),
						);
					}

					global $wp_widget_factory, $wp_registered_widget_controls;

					$types = array();

					if ( isset( $wp_widget_factory ) && is_object( $wp_widget_factory ) && ! empty( $wp_widget_factory->widgets ) && is_array( $wp_widget_factory->widgets ) ) {
						foreach ( $wp_widget_factory->widgets as $widget ) {
							if ( ! is_object( $widget ) || ! isset( $widget->id_base ) ) {
								continue;
							}

							$options     = isset( $widget->widget_options ) && is_array( $widget->widget_options ) ? $widget->widget_options : array();
							$id_base     = (string) $widget->id_base;

							$types[ $id_base ] = array(
								'id_base'     => esc_html( $id_base ),
								'name'        => esc_html( isset( $widget->name ) ? (string) $widget->name : $id_base ),
								'description' => esc_html( isset( $options['description'] ) ? (string) $options['description'] : '' ),
								'classname'   => esc_html( isset( $options['classname'] ) ? (string) $options['classname'] : '' ),
							);
						}
					}

					if ( is_array( $wp_registered_widget_controls ) ) {
						foreach ( $wp_registered_widget_controls as $control_id => $control ) {
							if ( ! is_array( $control ) ) {
								continue;
							}

							$id_base = isset( $control['id_base'] ) ? (string) $control['id_base'] : (string) $control_id;

							if ( isset( $types[ $id_base ] ) ) {
								continue;
							}

							$types[ $id_base ] = array(
								'id_base'     => esc_html( $id_base ),
								'name'        => esc_html( isset( $control['name'] ) ? (string) $control['name'] : $id_base ),
								'description' => '',
								'classname'   => '',
							);
						}
					}

					$types = array_values( $types );

					usort(
						$types,
						function ( $left, $right ): int {
							return strcmp( $left['name'], $right['name'] );
						}
					);

					return array(
						'success'      => true,
						'count'        => count( $types ),
						'widget_types' => $types,
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
			'shim-mcp/widgets-list-in-sidebar',
			array(
				'label'               => 'List Widgets In A Sidebar',
				'description'         => 'Reports which widgets are currently sitting in one widget area, in the order they will render. Requires the sidebar identifier, which you can obtain from the sidebar listing ability.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'sidebar_id' ),
					'properties'           => array(
						'sidebar_id' => array(
							'type'        => 'string',
							'description' => 'Identifier of the widget area to inspect, for example sidebar-1 or footer-2.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'      => array( 'type' => 'boolean' ),
						'sidebar_id'   => array( 'type' => 'string' ),
						'sidebar_name' => array( 'type' => 'string' ),
						'count'        => array( 'type' => 'integer' ),
						'widgets'      => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'widget_id' => array( 'type' => 'string' ),
									'id_base'   => array( 'type' => 'string' ),
									'name'      => array( 'type' => 'string' ),
									'position'  => array( 'type' => 'integer' ),
								),
							),
						),
						'message'      => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					if ( ! is_array( $input ) || ! isset( $input['sidebar_id'] ) || ! is_string( $input['sidebar_id'] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'Give me a sidebar identifier as a string.', 'shim-mcp' ),
						);
					}

					$sidebar_id = sanitize_text_field( $input['sidebar_id'] );

					if ( '' === $sidebar_id ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'The sidebar identifier cannot be empty.', 'shim-mcp' ),
						);
					}

					global $wp_registered_sidebars, $wp_registered_widgets;

					if ( ! is_array( $wp_registered_sidebars ) || ! isset( $wp_registered_sidebars[ $sidebar_id ] ) ) {
						return array(
							'success' => false,
							'message' => esc_html__( 'No widget area with that identifier is registered by the active theme.', 'shim-mcp' ),
						);
					}

					$sidebar   = $wp_registered_sidebars[ $sidebar_id ];
					$placement = get_option( 'sidebars_widgets' );

					if ( ! is_array( $placement ) ) {
						$placement = array();
					}

					$assigned = isset( $placement[ $sidebar_id ] ) && is_array( $placement[ $sidebar_id ] ) ? $placement[ $sidebar_id ] : array();

					$widgets  = array();
					$position = 0;

					foreach ( $assigned as $widget_id ) {
						if ( ! is_string( $widget_id ) && ! is_numeric( $widget_id ) ) {
							continue;
						}

						$widget_id = (string) $widget_id;
						$name      = '';
						$id_base   = $widget_id;

						if ( is_array( $wp_registered_widgets ) && isset( $wp_registered_widgets[ $widget_id ] ) && is_array( $wp_registered_widgets[ $widget_id ] ) ) {
							$registered = $wp_registered_widgets[ $widget_id ];
							$name       = isset( $registered['name'] ) ? (string) $registered['name'] : '';

							if ( isset( $registered['callback'] ) && is_array( $registered['callback'] ) && isset( $registered['callback'][0] ) && is_object( $registered['callback'][0] ) && isset( $registered['callback'][0]->id_base ) ) {
								$id_base = (string) $registered['callback'][0]->id_base;
							}
						}

						if ( $id_base === $widget_id ) {
							$separator = strrpos( $widget_id, '-' );
							if ( false !== $separator ) {
								$id_base = substr( $widget_id, 0, $separator );
							}
						}

						$widgets[] = array(
							'widget_id' => esc_html( $widget_id ),
							'id_base'   => esc_html( $id_base ),
							'name'      => esc_html( $name ),
							'position'  => $position,
						);

						++$position;
					}

					return array(
						'success'      => true,
						'sidebar_id'   => esc_html( $sidebar_id ),
						'sidebar_name' => esc_html( isset( $sidebar['name'] ) ? (string) $sidebar['name'] : '' ),
						'count'        => count( $widgets ),
						'widgets'      => $widgets,
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
	}
}
