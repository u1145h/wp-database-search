<?php
/**
 * Uninstall script for WP Database Search Plugin
 * 
 * This file is executed when the plugin is uninstalled (deleted).
 * It removes all plugin data from the database.
 * 
 * @package WP_Database_Search
 * @since 1.0.0
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check user permissions
if (!current_user_can('activate_plugins')) {
    exit;
}

// Verify that we are uninstalling
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin data
global $wpdb;

// Drop the custom table
$table_name = $wpdb->prefix . 'wp_database_search_data';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Remove plugin options
delete_option('wp_database_search_version');
delete_option('wp_database_search_settings');

// Remove any transients
delete_transient('wp_database_search_rate_limit_*');

// Clear any cached data
wp_cache_flush();

// Remove any scheduled events
wp_clear_scheduled_hook('wp_database_search_cleanup');

// Remove user meta (if any)
$wpdb->delete(
    $wpdb->usermeta,
    array('meta_key' => 'wp_database_search_user_preferences')
);

// Remove any custom post meta (if any)
$wpdb->delete(
    $wpdb->postmeta,
    array('meta_key' => 'wp_database_search_meta')
);

// Remove any custom comment meta (if any)
$wpdb->delete(
    $wpdb->commentmeta,
    array('meta_key' => 'wp_database_search_meta')
);

// Log the uninstall action
if (WP_DEBUG_LOG) {
    error_log('WP Database Search Plugin: Uninstalled and all data removed');
}
