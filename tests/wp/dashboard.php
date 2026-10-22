<?php

require_once __DIR__ . '/../lib/harness.php';

$admin = get_user_by( 'login', 'admin' );
wp_set_current_user( $admin->ID );
$original = get_user_meta( $admin->ID, 'shim_mcp_app_password', true );

$render = static function (): string {
	ob_start();
	include SHIM_MCP_DIR . 'includes/Admin/views/dashboard.php';
	return (string) ob_get_clean();
};

$config_copy_buttons = static function ( string $html ): array {
	preg_match_all( '#<button[^>]*\bmcp-config-copy\b[^>]*>#', $html, $matches );
	return $matches[0];
};

foreach ( array( 'with no key' => '', 'with a key whose password can no longer be shown' => 'existing-uuid' ) as $label => $uuid ) {
	update_user_meta( $admin->ID, 'shim_mcp_app_password', $uuid );
	$html    = $render();
	$buttons = $config_copy_buttons( $html );

	same( 3, count( $buttons ), "{$label}: every exported configuration has a Copy button" );
	same( array(), array_values( array_filter( $buttons, static fn( $button ) => false === strpos( $button, ' disabled' ) ) ), "{$label}: no configuration can be copied while it holds a placeholder" );
	same( 3, substr_count( $html, 'class="mcp-credential" data-placeholder="BASE64_ENCODED_CREDENTIALS"' ), "{$label}: every credential slot has a stable selector and its placeholder" );
	check( false !== strpos( $html, 'id="mcp-config-note"' ), "{$label}: the page explains that a new key is needed to export a configuration" );
}

check( false !== strpos( $html, 'id="mcp-generate-key">Generate New Key' ), 'with a key, the page offers to generate a new one' );
check( false !== strpos( $html, '"type": "http"' ), 'the Claude Code configuration names its HTTP transport' );

update_user_meta( $admin->ID, 'shim_mcp_app_password', $original );
if ( '' === $original ) {
	delete_user_meta( $admin->ID, 'shim_mcp_app_password' );
}
