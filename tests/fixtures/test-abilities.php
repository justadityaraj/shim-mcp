<?php

add_action(
	'wp_abilities_api_init',
	static function (): void {
		wp_register_ability(
			'shim-mcp-tests/echo-to-stdout',
			array(
				'label'               => 'Echo to stdout',
				'description'         => 'Test fixture that writes to standard output before it returns.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'echoed' => array( 'type' => 'boolean' ),
					),
				),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'execute_callback'    => static function (): array {
					echo "stray fixture output\n";
					return array( 'echoed' => true );
				},
			)
		);
	}
);
