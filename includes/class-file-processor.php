<?php
/**
 * File Processor Class
 * 
 * Handles Excel and CSV file processing for the WP Database Search plugin
 * 
 * @package WP_Database_Search
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * File Processor Class
 */
class WP_Database_Search_File_Processor {
    
    /**
     * Allowed file types
     */
    private $allowed_types = array(
        'text/csv',
        'application/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
    
    /**
     * Allowed file extensions
     */
    private $allowed_extensions = array('csv', 'xls', 'xlsx');
    
    /**
     * Maximum file size (5MB)
     */
    private $max_file_size = 5242880;
    
    /**
     * Process uploaded file
     * 
     * @return array Result array
     */
    public function process_uploaded_file() {
        // Check if file was uploaded
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            return array(
                'success' => false,
                'message' => __('No file uploaded or upload error occurred', 'wp-database-search')
            );
        }
        
        $file = $_FILES['file'];
        
        // Validate file
        $validation = $this->validate_file($file);
        if (!$validation['valid']) {
            return array(
                'success' => false,
                'message' => $validation['message']
            );
        }
        
        // Process file based on type
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        try {
            if ($file_extension === 'csv') {
                $data = $this->process_csv_file($file['tmp_name']);
            } else {
                $data = $this->process_excel_file($file['tmp_name'], $file_extension);
            }
            
            if (empty($data)) {
                return array(
                    'success' => false,
                    'message' => __('No data found in the file', 'wp-database-search')
                );
            }
            
            // Get column mapping from POST data
            $column_mapping = $this->get_column_mapping($data[0]);
            
            // Process and save data
            $database_manager = new WP_Database_Search_Database_Manager();
            $result = $database_manager->bulk_insert_records($data);
            
            if ($result['success']) {
                $result['column_mapping'] = $column_mapping;
                $result['preview_data'] = array_slice($data, 0, 5); // First 5 rows for preview
            }
            
            return $result;
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => sprintf(__('Error processing file: %s', 'wp-database-search'), $e->getMessage())
            );
        }
    }
    
    /**
     * Validate uploaded file
     * 
     * @param array $file File array from $_FILES
     * @return array Validation result
     */
    private function validate_file($file) {
        // Check file size
        if ($file['size'] > $this->max_file_size) {
            return array(
                'valid' => false,
                'message' => sprintf(__('File size exceeds maximum allowed size of %s', 'wp-database-search'), size_format($this->max_file_size))
            );
        }
        
        // Check file type
        $file_type = $file['type'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_type, $this->allowed_types) && !in_array($file_extension, $this->allowed_extensions)) {
            return array(
                'valid' => false,
                'message' => __('Invalid file type. Only CSV, XLS, and XLSX files are allowed', 'wp-database-search')
            );
        }
        
        // Check if file exists and is readable
        if (!file_exists($file['tmp_name']) || !is_readable($file['tmp_name'])) {
            return array(
                'valid' => false,
                'message' => __('File is not readable', 'wp-database-search')
            );
        }
        
        return array('valid' => true);
    }
    
    /**
     * Process CSV file
     * 
     * @param string $file_path Path to CSV file
     * @return array Parsed data
     */
    private function process_csv_file($file_path) {
        $data = array();
        $handle = fopen($file_path, 'r');
        
        if ($handle === false) {
            throw new Exception(__('Could not open CSV file', 'wp-database-search'));
        }
        
        // Detect delimiter
        $delimiter = $this->detect_csv_delimiter($file_path);
        
        // Read header row
        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false) {
            fclose($handle);
            throw new Exception(__('Could not read CSV header', 'wp-database-search'));
        }
        
        // Clean header
        $header = array_map('trim', $header);
        $header = array_map('sanitize_text_field', $header);
        
        // Read data rows
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($row) === count($header)) {
                $row_data = array();
                for ($i = 0; $i < count($header); $i++) {
                    $row_data[$header[$i]] = trim($row[$i]);
                }
                $data[] = $row_data;
            }
        }
        
        fclose($handle);
        return $data;
    }
    
    /**
     * Process Excel file
     * 
     * @param string $file_path Path to Excel file
     * @param string $extension File extension
     * @return array Parsed data
     */
    private function process_excel_file($file_path, $extension) {
        // Check if PhpSpreadsheet is available
        if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
            // Try to load PhpSpreadsheet
            $this->load_phpspreadsheet();
        }
        
        if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
            throw new Exception(__('PhpSpreadsheet library is not available. Please install it via Composer.', 'wp-database-search'));
        }
        
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file_path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            
            $data = array();
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            
            if ($highestRow < 2) {
                throw new Exception(__('Excel file must have at least a header row and one data row', 'wp-database-search'));
            }
            
            // Get header row
            $header = array();
            for ($col = 'A'; $col <= $highestColumn; $col++) {
                $cellValue = $worksheet->getCell($col . '1')->getCalculatedValue();
                $header[] = sanitize_text_field(trim($cellValue));
            }
            
            // Get data rows
            for ($row = 2; $row <= $highestRow; $row++) {
                $row_data = array();
                $col_index = 0;
                
                for ($col = 'A'; $col <= $highestColumn; $col++) {
                    $cellValue = $worksheet->getCell($col . $row)->getCalculatedValue();
                    $row_data[$header[$col_index]] = trim($cellValue);
                    $col_index++;
                }
                
                $data[] = $row_data;
            }
            
            return $data;
            
        } catch (Exception $e) {
            throw new Exception(sprintf(__('Error reading Excel file: %s', 'wp-database-search'), $e->getMessage()));
        }
    }
    
    /**
     * Detect CSV delimiter
     * 
     * @param string $file_path Path to CSV file
     * @return string Detected delimiter
     */
    private function detect_csv_delimiter($file_path) {
        $delimiters = array(',', ';', '\t', '|');
        $delimiter_counts = array();
        
        $handle = fopen($file_path, 'r');
        $first_line = fgets($handle);
        fclose($handle);
        
        foreach ($delimiters as $delimiter) {
            $delimiter_counts[$delimiter] = substr_count($first_line, $delimiter);
        }
        
        return array_search(max($delimiter_counts), $delimiter_counts);
    }
    
    /**
     * Get column mapping for admin interface
     * 
     * @param array $first_row First row of data
     * @return array Column mapping
     */
    private function get_column_mapping($first_row) {
        $mapping = array();
        
        foreach ($first_row as $key => $value) {
            $mapping[] = array(
                'original' => $key,
                'display' => $key,
                'type' => 'text'
            );
        }
        
        return $mapping;
    }
    
    /**
     * Load PhpSpreadsheet library
     */
    private function load_phpspreadsheet() {
        // Try to load from vendor directory (if installed via Composer)
        $vendor_paths = array(
            WP_DATABASE_SEARCH_PLUGIN_DIR . 'vendor/autoload.php',
            ABSPATH . 'vendor/autoload.php',
            WP_CONTENT_DIR . '/vendor/autoload.php'
        );
        
        foreach ($vendor_paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                return;
            }
        }
        
        // If PhpSpreadsheet is not available, we'll handle it gracefully
        // The error will be caught in the calling method
    }
    
    /**
     * Create sample CSV file for testing
     * 
     * @return string Path to created file
     */
    public function create_sample_csv() {
        $sample_data = array(
            array('Name', 'Email', 'Company', 'Phone', 'Address'),
            array('John Doe', 'john@example.com', 'Acme Corp', '555-1234', '123 Main St'),
            array('Jane Smith', 'jane@example.com', 'Tech Solutions', '555-5678', '456 Oak Ave'),
            array('Bob Johnson', 'bob@example.com', 'Global Inc', '555-9012', '789 Pine Rd')
        );
        
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/sample-data.csv';
        
        $handle = fopen($file_path, 'w');
        if ($handle === false) {
            throw new Exception(__('Could not create sample file', 'wp-database-search'));
        }
        
        foreach ($sample_data as $row) {
            fputcsv($handle, $row);
        }
        
        fclose($handle);
        
        return $file_path;
    }
    
    /**
     * Validate data before import
     * 
     * @param array $data Data to validate
     * @return array Validation result
     */
    public function validate_import_data($data) {
        if (!is_array($data) || empty($data)) {
            return array(
                'valid' => false,
                'message' => __('No data to validate', 'wp-database-search')
            );
        }
        
        $errors = array();
        $warnings = array();
        
        // Check for empty rows
        $empty_rows = 0;
        foreach ($data as $index => $row) {
            if (!is_array($row) || empty(array_filter($row))) {
                $empty_rows++;
            }
        }
        
        if ($empty_rows > 0) {
            $warnings[] = sprintf(__('%d empty rows found and will be skipped', 'wp-database-search'), $empty_rows);
        }
        
        // Check for duplicate headers
        $first_row = $data[0];
        if (is_array($first_row)) {
            $headers = array_keys($first_row);
            $duplicate_headers = array_diff_assoc($headers, array_unique($headers));
            
            if (!empty($duplicate_headers)) {
                $errors[] = sprintf(__('Duplicate column headers found: %s', 'wp-database-search'), implode(', ', $duplicate_headers));
            }
        }
        
        // Check data size
        if (count($data) > 10000) {
            $warnings[] = __('Large dataset detected. Import may take some time.', 'wp-database-search');
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'total_rows' => count($data),
            'valid_rows' => count($data) - $empty_rows
        );
    }
}
