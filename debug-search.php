<?php
/**
 * Debug Search Script for WP Database Search Plugin
 * 
 * This script helps debug search functionality by testing the database queries directly
 * 
 * @package WP_Database_Search
 * @since 1.0.0
 */

// Include WordPress
require_once('../../../wp-config.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied. Admin privileges required.');
}

echo "<h1>WP Database Search Debug</h1>";

// Test database connection
global $wpdb;
$table_name = $wpdb->prefix . 'wp_database_search_data';

echo "<h2>Database Information</h2>";
echo "<p><strong>Table Name:</strong> {$table_name}</p>";

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
if ($table_exists) {
    echo "<p><strong>Table Status:</strong> ✅ Exists</p>";
    
    // Get table structure
    $columns = $wpdb->get_results("DESCRIBE {$table_name}");
    echo "<h3>Table Structure:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column->Field}</td>";
        echo "<td>{$column->Type}</td>";
        echo "<td>{$column->Null}</td>";
        echo "<td>{$column->Key}</td>";
        echo "<td>{$column->Default}</td>";
        echo "<td>{$column->Extra}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Get record count
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    echo "<p><strong>Total Records:</strong> {$count}</p>";
    
    // Test search functionality
    echo "<h2>Search Tests</h2>";
    
    if ($count > 0) {
        // Get sample data
        $sample = $wpdb->get_row("SELECT * FROM {$table_name} LIMIT 1");
        if ($sample) {
            echo "<h3>Sample Record:</h3>";
            echo "<p><strong>ID:</strong> {$sample->id}</p>";
            echo "<p><strong>Data:</strong> " . esc_html($sample->data) . "</p>";
            echo "<p><strong>Searchable Text:</strong> " . esc_html($sample->searchable_text ?? 'NULL') . "</p>";
            
            // Test different search queries
            $test_terms = array('test', 'sample', 'data', 'record');
            
            foreach ($test_terms as $term) {
                echo "<h4>Testing search term: '{$term}'</h4>";
                
                // Test FULLTEXT search
                $fulltext_sql = $wpdb->prepare(
                    "SELECT id, data FROM {$table_name} WHERE MATCH(searchable_text) AGAINST (%s IN NATURAL LANGUAGE MODE) LIMIT 5",
                    $term
                );
                $fulltext_results = $wpdb->get_results($fulltext_sql);
                echo "<p><strong>FULLTEXT Results:</strong> " . count($fulltext_results) . " records</p>";
                
                // Test LIKE search
                $like_sql = $wpdb->prepare(
                    "SELECT id, data FROM {$table_name} WHERE searchable_text LIKE %s LIMIT 5",
                    '%' . $wpdb->esc_like($term) . '%'
                );
                $like_results = $wpdb->get_results($like_sql);
                echo "<p><strong>LIKE Results:</strong> " . count($like_results) . " records</p>";
                
                // Test JSON search
                $json_sql = $wpdb->prepare(
                    "SELECT id, data FROM {$table_name} WHERE data LIKE %s LIMIT 5",
                    '%' . $wpdb->esc_like($term) . '%'
                );
                $json_results = $wpdb->get_results($json_sql);
                echo "<p><strong>JSON Results:</strong> " . count($json_results) . " records</p>";
            }
        }
    } else {
        echo "<p><strong>No records found.</strong> Please upload some data first.</p>";
    }
    
} else {
    echo "<p><strong>Table Status:</strong> ❌ Does not exist</p>";
    echo "<p>Please activate the plugin first to create the database table.</p>";
}

// Test AJAX endpoint
echo "<h2>AJAX Endpoint Test</h2>";
$ajax_url = admin_url('admin-ajax.php');
echo "<p><strong>AJAX URL:</strong> {$ajax_url}</p>";

// Test nonce generation
$nonce = wp_create_nonce('wp_database_search_nonce');
echo "<p><strong>Test Nonce:</strong> {$nonce}</p>";

echo "<h2>Plugin Status</h2>";
if (class_exists('WP_Database_Search')) {
    echo "<p><strong>Plugin Class:</strong> ✅ Loaded</p>";
} else {
    echo "<p><strong>Plugin Class:</strong> ❌ Not loaded</p>";
}

if (class_exists('WP_Database_Search_Database_Manager')) {
    echo "<p><strong>Database Manager:</strong> ✅ Loaded</p>";
} else {
    echo "<p><strong>Database Manager:</strong> ❌ Not loaded</p>";
}

echo "<h2>Instructions</h2>";
echo "<ol>";
echo "<li>Make sure the plugin is activated</li>";
echo "<li>Upload some test data via the admin interface</li>";
echo "<li>Check the browser console for JavaScript errors</li>";
echo "<li>Check WordPress debug.log for PHP errors</li>";
echo "<li>Test the search functionality on the frontend</li>";
echo "</ol>";

echo "<p><a href='" . admin_url('admin.php?page=wp-database-search') . "'>Go to Plugin Admin</a></p>";
?>
