<?php
/**
 * Security Class
 * 
 * Handles security measures for the WP Database Search plugin
 * 
 * @package WP_Database_Search
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security Class
 */
class WP_Database_Search_Security {
    
    /**
     * Verify nonce for AJAX requests
     * 
     * @param string $nonce_name Nonce name
     * @return bool True if nonce is valid
     */
    public function verify_nonce($nonce_name) {
        $nonce = $_POST['nonce'] ?? $_GET['nonce'] ?? '';
        
        if (empty($nonce)) {
            return false;
        }
        
        return wp_verify_nonce($nonce, $nonce_name);
    }
    
    /**
     * Verify admin capability
     * 
     * @return bool True if user has required capability
     */
    public function verify_admin_capability() {
        return current_user_can('manage_options');
    }
    
    /**
     * Sanitize text input
     * 
     * @param mixed $input Input to sanitize
     * @return string Sanitized input
     */
    public function sanitize_text($input) {
        if (is_array($input)) {
            return array_map(array($this, 'sanitize_text'), $input);
        }
        
        return sanitize_text_field($input);
    }
    
    /**
     * Sanitize textarea input
     * 
     * @param mixed $input Input to sanitize
     * @return string Sanitized input
     */
    public function sanitize_textarea($input) {
        if (is_array($input)) {
            return array_map(array($this, 'sanitize_textarea'), $input);
        }
        
        return sanitize_textarea_field($input);
    }
    
    /**
     * Sanitize email input
     * 
     * @param string $input Email input
     * @return string Sanitized email
     */
    public function sanitize_email($input) {
        return sanitize_email($input);
    }
    
    /**
     * Sanitize URL input
     * 
     * @param string $input URL input
     * @return string Sanitized URL
     */
    public function sanitize_url($input) {
        return esc_url_raw($input);
    }
    
    /**
     * Sanitize integer input
     * 
     * @param mixed $input Input to sanitize
     * @return int Sanitized integer
     */
    public function sanitize_int($input) {
        return intval($input);
    }
    
    /**
     * Sanitize float input
     * 
     * @param mixed $input Input to sanitize
     * @return float Sanitized float
     */
    public function sanitize_float($input) {
        return floatval($input);
    }
    
    /**
     * Sanitize boolean input
     * 
     * @param mixed $input Input to sanitize
     * @return bool Sanitized boolean
     */
    public function sanitize_bool($input) {
        return (bool) $input;
    }
    
    /**
     * Escape output for HTML
     * 
     * @param string $output Output to escape
     * @return string Escaped output
     */
    public function escape_html($output) {
        return esc_html($output);
    }
    
    /**
     * Escape output for attributes
     * 
     * @param string $output Output to escape
     * @return string Escaped output
     */
    public function escape_attr($output) {
        return esc_attr($output);
    }
    
    /**
     * Escape output for JavaScript
     * 
     * @param string $output Output to escape
     * @return string Escaped output
     */
    public function escape_js($output) {
        return esc_js($output);
    }
    
    /**
     * Validate file upload
     * 
     * @param array $file File array from $_FILES
     * @return array Validation result
     */
    public function validate_file_upload($file) {
        $errors = array();
        
        // Check for upload errors
        if (!isset($file['error']) || is_array($file['error'])) {
            $errors[] = __('Invalid file upload', 'wp-database-search');
            return array('valid' => false, 'errors' => $errors);
        }
        
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                $errors[] = __('No file uploaded', 'wp-database-search');
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = __('File size exceeds limit', 'wp-database-search');
                break;
            default:
                $errors[] = __('Unknown upload error', 'wp-database-search');
        }
        
        // Check file size
        $max_size = wp_max_upload_size();
        if ($file['size'] > $max_size) {
            $errors[] = sprintf(__('File size exceeds maximum allowed size of %s', 'wp-database-search'), size_format($max_size));
        }
        
        // Check file type
        $allowed_types = array('text/csv', 'application/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $allowed_extensions = array('csv', 'xls', 'xlsx');
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file['type'], $allowed_types) && !in_array($file_extension, $allowed_extensions)) {
            $errors[] = __('Invalid file type. Only CSV, XLS, and XLSX files are allowed', 'wp-database-search');
        }
        
        // Check for malicious file names
        if (preg_match('/[^a-zA-Z0-9._-]/', $file['name'])) {
            $errors[] = __('Invalid file name', 'wp-database-search');
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * Validate search input
     * 
     * @param string $search_term Search term
     * @return array Validation result
     */
    public function validate_search_input($search_term) {
        $errors = array();
        
        // Check length
        if (strlen($search_term) > 255) {
            $errors[] = __('Search term is too long', 'wp-database-search');
        }
        
        // Check for SQL injection patterns
        $dangerous_patterns = array(
            '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION|SCRIPT)\b)/i',
            '/(\b(OR|AND)\s+\d+\s*=\s*\d+)/i',
            '/(\b(OR|AND)\s+\'\s*=\s*\')/i',
            '/(\b(OR|AND)\s+\"\s*=\s*\")/i',
            '/(\b(OR|AND)\s+\d+\s*=\s*\d+)/i'
        );
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $search_term)) {
                $errors[] = __('Invalid search term', 'wp-database-search');
                break;
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * Rate limit check
     * 
     * @param string $action Action being performed
     * @param int $limit Limit per time period
     * @param int $time_period Time period in seconds
     * @return bool True if within limits
     */
    public function check_rate_limit($action, $limit = 60, $time_period = 3600) {
        $user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();
        $key = 'wp_database_search_rate_limit_' . $action . '_' . $user_id . '_' . $ip_address;
        
        $current_count = get_transient($key);
        
        if ($current_count === false) {
            set_transient($key, 1, $time_period);
            return true;
        }
        
        if ($current_count >= $limit) {
            return false;
        }
        
        set_transient($key, $current_count + 1, $time_period);
        return true;
    }
    
    /**
     * Get client IP address
     * 
     * @return string Client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Log security event
     * 
     * @param string $event Event type
     * @param string $message Event message
     * @param array $context Additional context
     */
    public function log_security_event($event, $message, $context = array()) {
        if (!WP_DEBUG_LOG) {
            return;
        }
        
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'event' => $event,
            'message' => $message,
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'context' => $context
        );
        
        error_log('WP Database Search Security: ' . json_encode($log_data));
    }
    
    /**
     * Check if request is from admin area
     * 
     * @return bool True if from admin area
     */
    public function is_admin_request() {
        return is_admin() && !wp_doing_ajax();
    }
    
    /**
     * Check if request is AJAX
     * 
     * @return bool True if AJAX request
     */
    public function is_ajax_request() {
        return wp_doing_ajax();
    }
    
    /**
     * Validate CSRF token
     * 
     * @param string $token CSRF token
     * @param string $action Action name
     * @return bool True if token is valid
     */
    public function validate_csrf_token($token, $action) {
        return wp_verify_nonce($token, $action);
    }
    
    /**
     * Generate CSRF token
     * 
     * @param string $action Action name
     * @return string CSRF token
     */
    public function generate_csrf_token($action) {
        return wp_create_nonce($action);
    }
    
    /**
     * Sanitize database input
     * 
     * @param mixed $input Input to sanitize
     * @return mixed Sanitized input
     */
    public function sanitize_database_input($input) {
        if (is_array($input)) {
            return array_map(array($this, 'sanitize_database_input'), $input);
        }
        
        if (is_string($input)) {
            // Remove null bytes
            $input = str_replace(chr(0), '', $input);
            
            // Trim whitespace
            $input = trim($input);
            
            // Limit length
            if (strlen($input) > 65535) {
                $input = substr($input, 0, 65535);
            }
        }
        
        return $input;
    }
    
    /**
     * Check if user can perform action
     * 
     * @param string $capability Required capability
     * @return bool True if user has capability
     */
    public function user_can($capability) {
        return current_user_can($capability);
    }
    
    /**
     * Validate and sanitize array input
     * 
     * @param array $input Input array
     * @param array $schema Validation schema
     * @return array Sanitized array
     */
    public function validate_array_input($input, $schema) {
        if (!is_array($input)) {
            return array();
        }
        
        $sanitized = array();
        
        foreach ($schema as $key => $rules) {
            if (!isset($input[$key])) {
                continue;
            }
            
            $value = $input[$key];
            
            // Apply sanitization rules
            if (isset($rules['sanitize'])) {
                switch ($rules['sanitize']) {
                    case 'text':
                        $value = $this->sanitize_text($value);
                        break;
                    case 'textarea':
                        $value = $this->sanitize_textarea($value);
                        break;
                    case 'email':
                        $value = $this->sanitize_email($value);
                        break;
                    case 'url':
                        $value = $this->sanitize_url($value);
                        break;
                    case 'int':
                        $value = $this->sanitize_int($value);
                        break;
                    case 'float':
                        $value = $this->sanitize_float($value);
                        break;
                    case 'bool':
                        $value = $this->sanitize_bool($value);
                        break;
                }
            }
            
            // Apply validation rules
            if (isset($rules['required']) && $rules['required'] && empty($value)) {
                continue; // Skip invalid required fields
            }
            
            if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
                $value = substr($value, 0, $rules['max_length']);
            }
            
            $sanitized[$key] = $value;
        }
        
        return $sanitized;
    }
}
