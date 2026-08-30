<?php
// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('shim_mcp_settings');

// Clean up Application Passwords created by this plugin (tracked in user meta)
$users = get_users(['meta_key' => 'shim_mcp_app_password']);
foreach ($users as $user) {
    $uuid = get_user_meta($user->ID, 'shim_mcp_app_password', true);
    if ($uuid && class_exists('WP_Application_Passwords')) {
        WP_Application_Passwords::delete_application_password($user->ID, $uuid);
    }
    delete_user_meta($user->ID, 'shim_mcp_app_password');
}

// Clean up transients
delete_transient('shim_mcp_activated');
