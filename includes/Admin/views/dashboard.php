<?php
declare(strict_types=1);

defined( 'ABSPATH' ) || exit();

$shim_mcp_user_id   = get_current_user_id();
$shim_mcp_has_key   = (bool) get_user_meta( $shim_mcp_user_id, 'shim_mcp_app_password', true );
$shim_mcp_site_url  = site_url();
$shim_mcp_rest_base = rest_url( 'mcp/shim-mcp' );

// Health checks.
global $wp_version;
$shim_mcp_checks = [
	[
		'label'  => __( 'PHP Version', 'shim-mcp' ),
		'ok'     => PHP_VERSION_ID >= 80000,
		/* translators: %s: PHP version number. */
		'detail' => sprintf( __( 'PHP %s', 'shim-mcp' ), PHP_VERSION ) . ( PHP_VERSION_ID >= 80000 ? '' : ' ' . __( '(requires 8.0 or later)', 'shim-mcp' ) ),
	],
	[
		'label'  => __( 'WordPress Version', 'shim-mcp' ),
		'ok'     => version_compare( $wp_version, '6.9', '>=' ),
		/* translators: %s: WordPress version number. */
		'detail' => sprintf( __( 'WordPress %s', 'shim-mcp' ), $wp_version ) . ( version_compare( $wp_version, '6.9', '>=' ) ? '' : ' ' . __( '(requires 6.9 or later)', 'shim-mcp' ) ),
	],
	[
		'label'  => __( 'REST API', 'shim-mcp' ),
		'ok'     => ! empty( rest_url() ),
		'detail' => ! empty( rest_url() ) ? __( 'Enabled', 'shim-mcp' ) : __( 'Disabled', 'shim-mcp' ),
	],
	[
		'label'  => __( 'HTTPS', 'shim-mcp' ),
		'ok'     => is_ssl(),
		'detail' => is_ssl() ? __( 'Active', 'shim-mcp' ) : __( 'Not active (recommended for production)', 'shim-mcp' ),
	],
];

// Ability count.
$shim_mcp_ability_count = function_exists( 'wp_get_abilities' ) ? count( wp_get_abilities() ) : null;

// Config placeholders.
$shim_mcp_auth_placeholder   = '[username]:[password]';
$shim_mcp_base64_placeholder = 'BASE64_ENCODED_CREDENTIALS';
?>

<div class="wrap">
	<h1>Shim MCP</h1>
	<p class="mcp-subtitle"><?php esc_html_e( 'Connect WordPress to AI assistants via the Model Context Protocol.', 'shim-mcp' ); ?></p>

	<!-- Health Check Section -->
	<div class="mcp-card">
		<h2><?php esc_html_e( 'Health Check', 'shim-mcp' ); ?></h2>
		<div class="mcp-health-grid">
			<?php foreach ( $shim_mcp_checks as $shim_mcp_check ) : ?>
				<div class="mcp-health-item">
					<span class="mcp-status-icon <?php echo $shim_mcp_check['ok'] ? 'mcp-status-ok' : 'mcp-status-error'; ?>">
						<?php echo $shim_mcp_check['ok'] ? '&#10003;' : '&#10007;'; ?>
					</span>
					<div>
						<strong><?php echo esc_html( $shim_mcp_check['label'] ); ?></strong><br>
						<span class="mcp-health-detail"><?php echo esc_html( $shim_mcp_check['detail'] ); ?></span>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- API Key Section -->
	<div class="mcp-card">
		<h2><?php esc_html_e( 'API Key', 'shim-mcp' ); ?></h2>
		<p><?php esc_html_e( 'Application passwords authenticate MCP clients with your WordPress site.', 'shim-mcp' ); ?></p>

		<div id="mcp-key-status">
			<?php if ( $shim_mcp_has_key ) : ?>
				<span class="mcp-badge mcp-badge-active"><?php esc_html_e( 'API Key Active', 'shim-mcp' ); ?></span>
				<button type="button" class="button button-secondary" id="mcp-generate-key"><?php esc_html_e( 'Generate New Key', 'shim-mcp' ); ?></button>
				<button type="button" class="button button-secondary" id="mcp-revoke-key"><?php esc_html_e( 'Revoke Key', 'shim-mcp' ); ?></button>
			<?php else : ?>
				<button type="button" class="button button-primary" id="mcp-generate-key"><?php esc_html_e( 'Generate API Key', 'shim-mcp' ); ?></button>
			<?php endif; ?>
		</div>

		<div id="mcp-key-output" style="display:none;">
			<div class="notice notice-warning inline">
				<p><strong><?php esc_html_e( 'Copy this password now.', 'shim-mcp' ); ?></strong> <?php esc_html_e( 'It will not be shown again.', 'shim-mcp' ); ?></p>
			</div>
			<div class="mcp-key-display">
				<code id="mcp-key-value"></code>
				<button type="button" class="button button-small mcp-copy-btn" data-target="mcp-key-value"><?php esc_html_e( 'Copy', 'shim-mcp' ); ?></button>
			</div>
		</div>
	</div>

	<!-- Config Section -->
	<div class="mcp-card">
		<h2><?php esc_html_e( 'Configuration', 'shim-mcp' ); ?></h2>
		<p><?php esc_html_e( 'Copy the configuration below into your AI client settings.', 'shim-mcp' ); ?></p>
		<p id="mcp-config-note" class="description"><?php esc_html_e( 'WordPress cannot show an application password again after it is created. Generate a new key to fill in the credentials and export a complete configuration.', 'shim-mcp' ); ?></p>

		<!-- Claude Code -->
		<div class="mcp-config-block">
			<h3>Claude Code <code>~/.claude.json</code></h3>
			<div class="mcp-code-wrapper">
				<pre><code id="mcp-config-claude-code">{
	"mcpServers": {
	"shim-mcp": {
		"type": "http",
		"url": "<?php echo esc_url( $shim_mcp_rest_base ); ?>",
		"headers": {
		"Authorization": "Basic <span class="mcp-credential" data-placeholder="<?php echo esc_attr( $shim_mcp_base64_placeholder ); ?>"><?php echo esc_html( $shim_mcp_base64_placeholder ); ?></span>"
		}
	}
	}
}</code></pre>
				<button type="button" class="button button-small mcp-copy-btn mcp-config-copy" data-target="mcp-config-claude-code" disabled><?php esc_html_e( 'Copy', 'shim-mcp' ); ?></button>
			</div>
		</div>

		<!-- Claude Desktop -->
		<div class="mcp-config-block">
			<h3>Claude Desktop</h3>
			<div class="mcp-code-wrapper">
				<pre><code id="mcp-config-claude-desktop">{
	"mcpServers": {
	"shim-mcp": {
		"command": "npx",
		"args": [
		"-y",
		"mcp-remote",
		"<?php echo esc_url( $shim_mcp_rest_base ); ?>",
		"--header",
		"Authorization: Basic <span class="mcp-credential" data-placeholder="<?php echo esc_attr( $shim_mcp_base64_placeholder ); ?>"><?php echo esc_html( $shim_mcp_base64_placeholder ); ?></span>"
		]
	}
	}
}</code></pre>
				<button type="button" class="button button-small mcp-copy-btn mcp-config-copy" data-target="mcp-config-claude-desktop" disabled><?php esc_html_e( 'Copy', 'shim-mcp' ); ?></button>
			</div>
		</div>

		<!-- Cursor -->
		<div class="mcp-config-block">
			<h3>Cursor</h3>
			<div class="mcp-code-wrapper">
				<pre><code id="mcp-config-cursor">{
	"mcpServers": {
	"shim-mcp": {
		"command": "npx",
		"args": [
		"-y",
		"mcp-remote",
		"<?php echo esc_url( $shim_mcp_rest_base ); ?>",
		"--header",
		"Authorization: Basic <span class="mcp-credential" data-placeholder="<?php echo esc_attr( $shim_mcp_base64_placeholder ); ?>"><?php echo esc_html( $shim_mcp_base64_placeholder ); ?></span>"
		]
	}
	}
}</code></pre>
				<button type="button" class="button button-small mcp-copy-btn mcp-config-copy" data-target="mcp-config-cursor" disabled><?php esc_html_e( 'Copy', 'shim-mcp' ); ?></button>
			</div>
		</div>
	</div>

	<!-- Status Section -->
	<div class="mcp-card">
		<h2><?php esc_html_e( 'Status', 'shim-mcp' ); ?></h2>
		<div class="mcp-status-row">
			<span id="mcp-connection-dot" class="mcp-dot <?php echo $shim_mcp_has_key ? 'mcp-dot-unknown' : 'mcp-dot-inactive'; ?>"></span>
			<span id="mcp-connection-text"><?php echo esc_html( $shim_mcp_has_key ? __( 'Key configured — run the endpoint check', 'shim-mcp' ) : __( 'No API key configured', 'shim-mcp' ) ); ?></span>
		</div>

		<?php if ( null !== $shim_mcp_ability_count ) : ?>
			<p class="mcp-ability-count">
				<?php
				/* translators: %s: number of registered abilities. */
				echo esc_html( sprintf( _n( '%s ability registered.', '%s abilities registered.', $shim_mcp_ability_count, 'shim-mcp' ), number_format_i18n( $shim_mcp_ability_count ) ) );
				?>
			</p>
		<?php endif; ?>

		<button type="button" class="button button-secondary" id="mcp-test-connection" <?php echo $shim_mcp_has_key ? '' : 'disabled'; ?>>
			<?php esc_html_e( 'Check Endpoint', 'shim-mcp' ); ?>
		</button>
	</div>
</div>
