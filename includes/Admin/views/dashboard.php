<?php
declare(strict_types=1);

defined( 'ABSPATH' ) || exit();

$shim_user_id   = get_current_user_id();
$shim_has_key   = (bool) get_user_meta( $shim_user_id, 'shim_mcp_app_password', true );
$shim_site_url  = site_url();
$shim_rest_base = rest_url( 'mcp/shim-mcp' );

// Health checks.
global $wp_version;
$shim_checks = [
	[
		'label'  => 'PHP Version',
		'ok'     => PHP_VERSION_ID >= 80000,
		'detail' => 'PHP ' . PHP_VERSION . ( PHP_VERSION_ID >= 80000 ? '' : ' (requires 8.0+)' ),
	],
	[
		'label'  => 'WordPress Version',
		'ok'     => version_compare( $wp_version, '6.7', '>=' ),
		'detail' => 'WordPress ' . $wp_version . ( version_compare( $wp_version, '6.7', '>=' ) ? '' : ' (requires 6.7+)' ),
	],
	[
		'label'  => 'REST API',
		'ok'     => ! empty( rest_url() ),
		'detail' => ! empty( rest_url() ) ? 'Enabled' : 'Disabled',
	],
	[
		'label'  => 'HTTPS',
		'ok'     => is_ssl(),
		'detail' => is_ssl() ? 'Active' : 'Not active (recommended for production)',
	],
];

// Ability count.
$shim_ability_count = function_exists( 'wp_get_abilities' ) ? count( wp_get_abilities() ) : null;

// Config placeholders.
$shim_auth_placeholder   = '[username]:[password]';
$shim_base64_placeholder = 'BASE64_ENCODED_CREDENTIALS';
?>

<div class="wrap">
	<h1>Shim MCP</h1>
	<p class="mcp-subtitle">Connect WordPress to AI assistants via the Model Context Protocol.</p>

	<!-- Health Check Section -->
	<div class="mcp-card">
		<h2>Health Check</h2>
		<div class="mcp-health-grid">
			<?php foreach ( $shim_checks as $shim_check ) : ?>
				<div class="mcp-health-item">
					<span class="mcp-status-icon <?php echo $shim_check['ok'] ? 'mcp-status-ok' : 'mcp-status-error'; ?>">
						<?php echo $shim_check['ok'] ? '&#10003;' : '&#10007;'; ?>
					</span>
					<div>
						<strong><?php echo esc_html( $shim_check['label'] ); ?></strong><br>
						<span class="mcp-health-detail"><?php echo esc_html( $shim_check['detail'] ); ?></span>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- API Key Section -->
	<div class="mcp-card">
		<h2>API Key</h2>
		<p>Application passwords authenticate MCP clients with your WordPress site.</p>

		<div id="mcp-key-status">
			<?php if ( $shim_has_key ) : ?>
				<span class="mcp-badge mcp-badge-active">API Key Active</span>
				<button type="button" class="button button-secondary" id="mcp-revoke-key">Revoke Key</button>
			<?php else : ?>
				<button type="button" class="button button-primary" id="mcp-generate-key">Generate API Key</button>
			<?php endif; ?>
		</div>

		<div id="mcp-key-output" style="display:none;">
			<div class="notice notice-warning inline">
				<p><strong>Copy this password now.</strong> It will not be shown again.</p>
			</div>
			<div class="mcp-key-display">
				<code id="mcp-key-value"></code>
				<button type="button" class="button button-small mcp-copy-btn" data-target="mcp-key-value">Copy</button>
			</div>
		</div>
	</div>

	<!-- Config Section -->
	<div class="mcp-card">
		<h2>Configuration</h2>
		<p>Copy the configuration below into your AI client settings.</p>

		<!-- Claude Code -->
		<div class="mcp-config-block">
			<h3>Claude Code <code>~/.claude.json</code></h3>
			<div class="mcp-code-wrapper">
				<pre><code id="mcp-config-claude-code">{
	"mcpServers": {
	"shim-mcp": {
		"url": "<?php echo esc_url( $shim_rest_base ); ?>",
		"headers": {
		"Authorization": "Basic <span class="mcp-placeholder"><?php echo esc_html( $shim_base64_placeholder ); ?></span>"
		}
	}
	}
}</code></pre>
				<button type="button" class="button button-small mcp-copy-btn" data-target="mcp-config-claude-code">Copy</button>
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
		"<?php echo esc_url( $shim_rest_base ); ?>",
		"--header",
		"Authorization: Basic <span class="mcp-placeholder"><?php echo esc_html( $shim_base64_placeholder ); ?></span>"
		]
	}
	}
}</code></pre>
				<button type="button" class="button button-small mcp-copy-btn" data-target="mcp-config-claude-desktop">Copy</button>
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
		"<?php echo esc_url( $shim_rest_base ); ?>",
		"--header",
		"Authorization: Basic <span class="mcp-placeholder"><?php echo esc_html( $shim_base64_placeholder ); ?></span>"
		]
	}
	}
}</code></pre>
				<button type="button" class="button button-small mcp-copy-btn" data-target="mcp-config-cursor">Copy</button>
			</div>
		</div>
	</div>

	<!-- Status Section -->
	<div class="mcp-card">
		<h2>Status</h2>
		<div class="mcp-status-row">
			<span id="mcp-connection-dot" class="mcp-dot <?php echo $shim_has_key ? 'mcp-dot-unknown' : 'mcp-dot-inactive'; ?>"></span>
			<span id="mcp-connection-text"><?php echo $shim_has_key ? 'Key configured &mdash; test to verify' : 'No API key configured'; ?></span>
		</div>

		<?php if ( null !== $shim_ability_count ) : ?>
			<p class="mcp-ability-count"><?php echo (int) $shim_ability_count; ?> abilities registered.</p>
		<?php endif; ?>

		<button type="button" class="button button-secondary" id="mcp-test-connection" <?php echo $shim_has_key ? '' : 'disabled'; ?>>
			Test Connection
		</button>
	</div>
</div>
