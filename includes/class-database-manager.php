<?php
/**
 * Database Manager Class
 * 
 * Handles all database operations for the WP Database Search plugin
 * 
 * @package WP_Database_Search
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database Manager Class
 */
class WP_Database_Search_Database_Manager {
    
    /**
     * Database table name
     */
    private $table_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wp_database_search_data';
    }
    
    /**
     * Search data based on search term and optional column filter
     * 
     * @param string $search_term The search term
     * @param string $column_filter Optional column to filter by
     * @return array Search results
     */
    public function search_data($search_term, $column_filter = '') {
        global $wpdb;
        
        if (empty($search_term) || strlen($search_term) < 2) {
            return array();
        }
        
        // Escape the search term for LIKE queries
        $escaped_term = $wpdb->esc_like($search_term);
        $like_term = '%' . $escaped_term . '%';
        
        // Try FULLTEXT search first (faster), fallback to LIKE if no results
        $sql = '';
        if (!empty($column_filter)) {
            // Search in specific column using JSON_EXTRACT and LIKE
            $sql = $wpdb->prepare(
                "SELECT id, data FROM {$this->table_name} WHERE JSON_EXTRACT(data, '$." . $wpdb->esc_like($column_filter) . "') LIKE %s ORDER BY id DESC LIMIT 50",
                $like_term
            );
        } else {
            // First try FULLTEXT search for better performance
            $fulltext_sql = $wpdb->prepare(
                "SELECT id, data FROM {$this->table_name} WHERE MATCH(searchable_text) AGAINST (%s IN NATURAL LANGUAGE MODE) ORDER BY id DESC LIMIT 50",
                $search_term
            );
            
            $results = $wpdb->get_results($fulltext_sql);
            
            // If FULLTEXT search returns no results or fails, fallback to LIKE
            if (empty($results) || $wpdb->last_error) {
                // Try LIKE on searchable_text first
                $sql = $wpdb->prepare(
                    "SELECT id, data FROM {$this->table_name} WHERE searchable_text LIKE %s ORDER BY id DESC LIMIT 50",
                    $like_term
                );
                
                $results = $wpdb->get_results($sql);
                
                // If still no results, try LIKE on JSON data
                if (empty($results)) {
                    $sql = $wpdb->prepare(
                        "SELECT id, data FROM {$this->table_name} WHERE data LIKE %s ORDER BY id DESC LIMIT 50",
                        $like_term
                    );
                }
            } else {
                // Process FULLTEXT results
                return $this->process_search_results($results, $search_term, $column_filter);
            }
        }
        
        if (!empty($sql)) {
            $results = $wpdb->get_results($sql);
            return $this->process_search_results($results, $search_term, $column_filter);
        }
        
        return array();
    }
    
    /**
     * Process search results
     * 
     * @param array $results Raw database results
     * @param string $search_term Search term
     * @param string $column_filter Column filter
     * @return array Formatted results
     */
    private function process_search_results($results, $search_term, $column_filter = '') {
        $formatted_results = array();
        foreach ($results as $result) {
            $data = json_decode($result->data, true);
            if ($data) {
                // For FULLTEXT search, we trust the database results
                // For LIKE search, do additional filtering
                $should_include = true;
                
                // Only do additional filtering for LIKE searches or specific column searches
                if (!empty($column_filter) || strpos($search_term, '%') !== false) {
                    $should_include = $this->matches_search_term($data, $search_term, $column_filter);
                }
                
                if ($should_include) {
                    $formatted_results[] = array(
                        'id' => $result->id,
                        'data' => $data,
                        'summary' => $this->create_summary($data),
                        'url' => home_url('/database-record/' . $result->id . '/')
                    );
                }
            }
        }
        
        return $formatted_results;
    }
    
    /**
     * Check if data matches search term (case-insensitive)
     * 
     * @param array $data Record data
     * @param string $search_term Search term
     * @param string $column_filter Column filter
     * @return bool True if matches
     */
    private function matches_search_term($data, $search_term, $column_filter = '') {
        $search_term = strtolower($search_term);
        
        if (!empty($column_filter)) {
            // Search in specific column
            if (isset($data[$column_filter])) {
                return strpos(strtolower($data[$column_filter]), $search_term) !== false;
            }
            return false;
        } else {
            // Search in all columns
            foreach ($data as $value) {
                if (is_string($value) && strpos(strtolower($value), $search_term) !== false) {
                    return true;
                }
            }
            return false;
        }
    }
    
    /**
     * Get a single record by ID
     * 
     * @param int $record_id Record ID
     * @return object|null Record data or null if not found
     */
    public function get_record($record_id) {
        global $wpdb;
        
        $record_id = intval($record_id);
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $record_id
        );
        
        return $wpdb->get_row($sql);
    }
    
    /**
     * Get all records with pagination
     * 
     * @param int $page Current page
     * @param int $per_page Records per page
     * @return array Records and pagination info
     */
    public function get_all_records($page = 1, $per_page = 20) {
        global $wpdb;
        
        $page = max(1, intval($page));
        $per_page = max(1, min(100, intval($per_page)));
        $offset = ($page - 1) * $per_page;
        
        // Get total count
        $total_sql = "SELECT COUNT(*) FROM {$this->table_name}";
        $total = $wpdb->get_var($total_sql);
        
        // Get records
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} ORDER BY id DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );
        
        $records = $wpdb->get_results($sql);
        
        // Format records
        $formatted_records = array();
        foreach ($records as $record) {
            $data = json_decode($record->data, true);
            if ($data) {
                $formatted_records[] = array(
                    'id' => $record->id,
                    'data' => $data,
                    'created_at' => $record->created_at,
                    'updated_at' => $record->updated_at
                );
            }
        }
        
        return array(
            'records' => $formatted_records,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        );
    }
    
    /**
     * Get all unique column names from the data
     * 
     * @return array Array of column names
     */
    public function get_column_names() {
        global $wpdb;
        
        $sql = "SELECT data FROM {$this->table_name} LIMIT 100";
        $results = $wpdb->get_results($sql);
        
        $columns = array();
        foreach ($results as $result) {
            $data = json_decode($result->data, true);
            if ($data && is_array($data)) {
                $columns = array_merge($columns, array_keys($data));
            }
        }
        
        return array_unique($columns);
    }
    
    /**
     * Insert a new record
     * 
     * @param array $data Record data
     * @return int|false Record ID or false on failure
     */
    public function insert_record($data) {
        global $wpdb;
        
        if (!is_array($data) || empty($data)) {
            return false;
        }
        
        $json_data = json_encode($data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        $searchable_text = $this->create_searchable_text($data);
        
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'data' => $json_data,
                'searchable_text' => $searchable_text
            ),
            array('%s', '%s')
        );
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update an existing record
     * 
     * @param int $record_id Record ID
     * @param array $data New record data
     * @return bool Success status
     */
    public function update_record($record_id, $data) {
        global $wpdb;
        
        $record_id = intval($record_id);
        
        if (!is_array($data) || empty($data)) {
            return false;
        }
        
        $json_data = json_encode($data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        $searchable_text = $this->create_searchable_text($data);
        
        $result = $wpdb->update(
            $this->table_name,
            array(
                'data' => $json_data,
                'searchable_text' => $searchable_text
            ),
            array('id' => $record_id),
            array('%s', '%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete a record
     * 
     * @param int $record_id Record ID
     * @return bool Success status
     */
    public function delete_record($record_id = null) {
        global $wpdb;
        
        // If called via AJAX, get record_id from POST
        if ($record_id === null) {
            $record_id = intval($_POST['record_id'] ?? 0);
        } else {
            $record_id = intval($record_id);
        }
        
        if ($record_id <= 0) {
            return array('success' => false, 'message' => __('Invalid record ID', 'wp-database-search'));
        }
        
        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $record_id),
            array('%d')
        );
        
        if ($result === false) {
            return array('success' => false, 'message' => __('Failed to delete record', 'wp-database-search'));
        }
        
        return array('success' => true, 'message' => __('Record deleted successfully', 'wp-database-search'));
    }
    
    /**
     * Save record (insert or update)
     * 
     * @return array Result array
     */
    public function save_record() {
        $record_id = intval($_POST['record_id'] ?? 0);
        $data = $_POST['data'] ?? array();
        
        // Sanitize data
        $sanitized_data = array();
        foreach ($data as $key => $value) {
            $sanitized_key = sanitize_text_field($key);
            $sanitized_value = sanitize_textarea_field($value);
            if (!empty($sanitized_key) && !empty($sanitized_value)) {
                $sanitized_data[$sanitized_key] = $sanitized_value;
            }
        }
        
        if (empty($sanitized_data)) {
            return array('success' => false, 'message' => __('No valid data provided', 'wp-database-search'));
        }
        
        if ($record_id > 0) {
            // Update existing record
            $success = $this->update_record($record_id, $sanitized_data);
            $message = $success ? __('Record updated successfully', 'wp-database-search') : __('Failed to update record', 'wp-database-search');
        } else {
            // Insert new record
            $new_id = $this->insert_record($sanitized_data);
            $success = $new_id !== false;
            $message = $success ? __('Record created successfully', 'wp-database-search') : __('Failed to create record', 'wp-database-search');
        }
        
        return array(
            'success' => $success,
            'message' => $message,
            'record_id' => $record_id > 0 ? $record_id : ($new_id ?? 0)
        );
    }
    
    /**
     * Bulk insert records
     * 
     * @param array $records Array of record data
     * @return array Result array
     */
    public function bulk_insert_records($records) {
        if (!is_array($records) || empty($records)) {
            return array('success' => false, 'message' => __('No records to insert', 'wp-database-search'));
        }
        
        $inserted_count = 0;
        $errors = array();
        
        foreach ($records as $index => $record) {
            if (!is_array($record) || empty($record)) {
                $errors[] = sprintf(__('Record %d: Invalid data format', 'wp-database-search'), $index + 1);
                continue;
            }
            
            $record_id = $this->insert_record($record);
            if ($record_id !== false) {
                $inserted_count++;
            } else {
                $errors[] = sprintf(__('Record %d: Failed to insert', 'wp-database-search'), $index + 1);
            }
        }
        
        $message = sprintf(__('%d records inserted successfully', 'wp-database-search'), $inserted_count);
        if (!empty($errors)) {
            $message .= '. ' . sprintf(__('%d errors occurred', 'wp-database-search'), count($errors));
        }
        
        return array(
            'success' => $inserted_count > 0,
            'message' => $message,
            'inserted_count' => $inserted_count,
            'errors' => $errors
        );
    }
    
    /**
     * Export data to CSV
     */
    public function export_data() {
        $records = $this->get_all_records(1, 10000); // Get all records
        
        if (empty($records['records'])) {
            wp_die(__('No data to export', 'wp-database-search'));
        }
        
        // Get all unique column names
        $all_columns = array();
        foreach ($records['records'] as $record) {
            $all_columns = array_merge($all_columns, array_keys($record['data']));
        }
        $all_columns = array_unique($all_columns);
        
        // Set headers for CSV download
        $filename = 'wp-database-search-export-' . date('Y-m-d-H-i-s') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Write CSV header
        fputcsv($output, array_merge(array('ID'), $all_columns));
        
        // Write data rows
        foreach ($records['records'] as $record) {
            $row = array($record['id']);
            foreach ($all_columns as $column) {
                $row[] = $record['data'][$column] ?? '';
            }
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Create a summary from record data
     * 
     * @param array $data Record data
     * @return string Summary text
     */
    private function create_summary($data) {
        if (!is_array($data) || empty($data)) {
            return '';
        }
        
        // Take first few non-empty values as summary
        $summary_parts = array();
        foreach ($data as $key => $value) {
            if (!empty($value) && count($summary_parts) < 3) {
                $summary_parts[] = $key . ': ' . $value;
            }
        }
        
        return implode(' | ', $summary_parts);
    }
    
    /**
     * Create searchable text from record data
     * 
     * @param array $data Record data
     * @return string Searchable text
     */
    private function create_searchable_text($data) {
        if (!is_array($data) || empty($data)) {
            return '';
        }
        
        // Combine all text values for full-text search
        $searchable_parts = array();
        foreach ($data as $key => $value) {
            if (is_string($value) && !empty(trim($value))) {
                $searchable_parts[] = trim($value);
            }
        }
        
        return implode(' ', $searchable_parts);
    }
    
    /**
     * Clear all data
     * 
     * @return bool Success status
     */
    public function clear_all_data() {
        global $wpdb;
        
        $result = $wpdb->query("TRUNCATE TABLE {$this->table_name}");
        
        return $result !== false;
    }
    
    /**
     * Get database statistics
     * 
     * @return array Statistics
     */
    public function get_statistics() {
        global $wpdb;
        
        $total_records = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        $columns = $this->get_column_names();
        
        return array(
            'total_records' => intval($total_records),
            'total_columns' => count($columns),
            'columns' => $columns
        );
    }
    
    /**
     * Update searchable_text for all existing records
     * This is useful when upgrading the plugin
     * 
     * @return bool Success status
     */
    public function update_all_searchable_text() {
        global $wpdb;
        
        // Get all records that don't have searchable_text
        $records = $wpdb->get_results("SELECT id, data FROM {$this->table_name} WHERE searchable_text IS NULL OR searchable_text = ''");
        
        foreach ($records as $record) {
            $data = json_decode($record->data, true);
            if ($data) {
                $searchable_text = $this->create_searchable_text($data);
                
                $wpdb->update(
                    $this->table_name,
                    array('searchable_text' => $searchable_text),
                    array('id' => $record->id),
                    array('%s'),
                    array('%d')
                );
            }
        }
        
        return true;
    }
}

