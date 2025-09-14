<?php
/**
 * Test Search Functionality
 * 
 * This file helps test the search functionality directly
 */

// Include WordPress
require_once('../../../wp-config.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied. Admin privileges required.');
}

echo "<h1>WP Database Search - Test Search</h1>";

// Include the plugin classes
require_once('includes/class-database-manager.php');

$database_manager = new WP_Database_Search_Database_Manager();

// Test search functionality
if (isset($_POST['test_search'])) {
    $search_term = sanitize_text_field($_POST['search_term']);
    $column_filter = sanitize_text_field($_POST['column_filter']);
    
    echo "<h2>Search Results for: '{$search_term}'</h2>";
    if (!empty($column_filter)) {
        echo "<p>Column Filter: {$column_filter}</p>";
    }
    
    $results = $database_manager->search_data($search_term, $column_filter);
    
    echo "<p><strong>Found " . count($results) . " results</strong></p>";
    
    if (!empty($results)) {
        echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
        foreach ($results as $result) {
            echo "<h3>Record ID: " . $result['id'] . "</h3>";
            echo "<div style='background: #f9f9f9; padding: 10px; margin: 5px 0;'>";
            foreach ($result['data'] as $key => $value) {
                echo "<strong>" . esc_html($key) . ":</strong> " . esc_html($value) . "<br>";
            }
            echo "</div>";
            echo "<p><a href='" . $result['url'] . "' target='_blank'>View Details</a></p>";
            echo "<hr>";
        }
        echo "</div>";
    } else {
        echo "<p style='color: red;'>No results found!</p>";
    }
}

// Get available columns
$columns = $database_manager->get_column_names();
$stats = $database_manager->get_statistics();

echo "<h2>Database Statistics</h2>";
echo "<p><strong>Total Records:</strong> " . $stats['total_records'] . "</p>";
echo "<p><strong>Available Columns:</strong> " . implode(', ', $columns) . "</p>";

?>

<h2>Test Search</h2>
<form method="post">
    <p>
        <label for="search_term">Search Term:</label><br>
        <input type="text" id="search_term" name="search_term" value="<?php echo esc_attr($_POST['search_term'] ?? ''); ?>" style="width: 300px; padding: 5px;">
    </p>
    
    <p>
        <label for="column_filter">Column Filter (optional):</label><br>
        <select id="column_filter" name="column_filter" style="width: 300px; padding: 5px;">
            <option value="">All Columns</option>
            <?php foreach ($columns as $column): ?>
                <option value="<?php echo esc_attr($column); ?>" <?php selected($_POST['column_filter'] ?? '', $column); ?>>
                    <?php echo esc_html($column); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    
    <p>
        <input type="submit" name="test_search" value="Test Search" style="padding: 10px 20px; background: #0073aa; color: white; border: none; cursor: pointer;">
    </p>
</form>

<h2>Sample Test Searches</h2>
<ul>
    <li><a href="?test_search=1&search_term=test">Test with "test"</a></li>
    <li><a href="?test_search=1&search_term=sample">Test with "sample"</a></li>
    <li><a href="?test_search=1&search_term=data">Test with "data"</a></li>
</ul>

<?php
// Auto-test if no data
if ($stats['total_records'] == 0) {
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0;'>";
    echo "<h3>⚠️ No Data Found</h3>";
    echo "<p>Please upload some test data first:</p>";
    echo "<ol>";
    echo "<li>Go to <a href='" . admin_url('admin.php?page=wp-database-search') . "'>Database Search Admin</a></li>";
    echo "<li>Upload a CSV or Excel file</li>";
    echo "<li>Come back here to test the search</li>";
    echo "</ol>";
    echo "</div>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1, h2, h3 { color: #333; }
input, select { margin: 5px 0; }
a { color: #0073aa; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
