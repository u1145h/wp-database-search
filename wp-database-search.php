<?php
/**
 * Plugin Name: WP Database Search
 * Plugin URI: https://ullashroy.site/wp-database-search
 * Description: A powerful WordPress plugin offering a single dynamic search bar shortcode that allows users to search live across all imported Excel/CSV data fields. Users can enter any search term and optionally select a specific data column (field) to precisely filter search results, with column titles defined dynamically by the admin. The plugin displays live AJAX search results updating as the user types and links to detailed individual record pages. The admin backend allows uploading Excel/CSV files with flexible column mapping and inline editing of all imported data fields.
 * Version: 1.0.0
 * Author: u1145h
 * Author URI: https://ullashroy.site
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-database-search
 * Domain Path: /languages
 * GitHub Plugin URI: https://github.com/u1145h/wp-database-search
 */

/*
 WP Database Search is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 2 of the License, or (at your option) any later version.

 WP Database Search is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along with WP Database Search. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_DATABASE_SEARCH_VERSION', '1.0.0');
define('WP_DATABASE_SEARCH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_DATABASE_SEARCH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_DATABASE_SEARCH_PLUGIN_FILE', __FILE__);

/**
 * Main plugin class
 */
class WP_Database_Search {
    
    /**
     * Single instance of the plugin
     */
    private static $instance = null;
    
    /**
     * Database table name
     */
    private $table_name;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wp_database_search_data';
        
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX hooks
        add_action('wp_ajax_wp_database_search', array($this, 'handle_ajax_search'));
        add_action('wp_ajax_nopriv_wp_database_search', array($this, 'handle_ajax_search'));
        add_action('wp_ajax_wp_database_admin_upload', array($this, 'handle_admin_upload'));
        add_action('wp_ajax_wp_database_admin_save', array($this, 'handle_admin_save'));
        add_action('wp_ajax_wp_database_admin_delete', array($this, 'handle_admin_delete'));
        add_action('wp_ajax_wp_database_admin_export', array($this, 'handle_admin_export'));
        
        // Shortcode
        add_shortcode('wp_database_search', array($this, 'render_search_shortcode'));
        
        // Rewrite rules for detail pages
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_detail_page'));
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once WP_DATABASE_SEARCH_PLUGIN_DIR . 'includes/class-database-manager.php';
        require_once WP_DATABASE_SEARCH_PLUGIN_DIR . 'includes/class-file-processor.php';
        require_once WP_DATABASE_SEARCH_PLUGIN_DIR . 'includes/class-admin-interface.php';
        require_once WP_DATABASE_SEARCH_PLUGIN_DIR . 'includes/class-frontend-search.php';
        require_once WP_DATABASE_SEARCH_PLUGIN_DIR . 'includes/class-security.php';
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        $this->create_database_tables();
        $this->add_rewrite_rules();
        flush_rewrite_rules();
        
        // Update searchable_text for existing records
        $database_manager = new WP_Database_Search_Database_Manager();
        $database_manager->update_all_searchable_text();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        load_plugin_textdomain('wp-database-search', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Create database tables
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            data longtext NOT NULL,
            searchable_text longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            FULLTEXT KEY searchable_text (searchable_text)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Store plugin version
        update_option('wp_database_search_version', WP_DATABASE_SEARCH_VERSION);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Database Search', 'wp-database-search'),
            __('Database Search', 'wp-database-search'),
            'manage_options',
            'wp-database-search',
            array($this, 'admin_page'),
            'dashicons-search',
            30
        );
    }
    
    /**
     * Admin page callback
     */
    public function admin_page() {
        $admin_interface = new WP_Database_Search_Admin_Interface();
        $admin_interface->render();
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'wp-database-search-frontend',
            WP_DATABASE_SEARCH_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            WP_DATABASE_SEARCH_VERSION,
            true
        );
        
        wp_enqueue_style(
            'wp-database-search-frontend',
            WP_DATABASE_SEARCH_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            WP_DATABASE_SEARCH_VERSION
        );
        
        // Localize script
        wp_localize_script('wp-database-search-frontend', 'wpDatabaseSearch', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_database_search_nonce'),
            'loadingText' => __('Searching...', 'wp-database-search'),
            'noResultsText' => __('No results found', 'wp-database-search'),
            'errorText' => __('An error occurred. Please try again.', 'wp-database-search')
        ));
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_wp-database-search' !== $hook) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script(
            'wp-database-search-admin',
            WP_DATABASE_SEARCH_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-sortable'),
            WP_DATABASE_SEARCH_VERSION,
            true
        );
        
        wp_enqueue_style(
            'wp-database-search-admin',
            WP_DATABASE_SEARCH_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WP_DATABASE_SEARCH_VERSION
        );
        
        // Localize script
        wp_localize_script('wp-database-search-admin', 'wpDatabaseSearchAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_database_search_admin_nonce'),
            'confirmDelete' => __('Are you sure you want to delete this record?', 'wp-database-search'),
            'savingText' => __('Saving...', 'wp-database-search'),
            'savedText' => __('Saved!', 'wp-database-search'),
            'errorText' => __('An error occurred. Please try again.', 'wp-database-search')
        ));
    }
    
    /**
     * Render search shortcode
     */
    public function render_search_shortcode($atts) {
        $frontend_search = new WP_Database_Search_Frontend_Search();
        return $frontend_search->render($atts);
    }
    
    /**
     * Handle AJAX search
     */
    public function handle_ajax_search() {
        $security = new WP_Database_Search_Security();
        if (!$security->verify_nonce('wp_database_search_nonce')) {
            wp_die(__('Security check failed', 'wp-database-search'));
        }
        
        $search_term = sanitize_text_field($_POST['search_term'] ?? '');
        $column_filter = sanitize_text_field($_POST['column_filter'] ?? '');
        
        // Debug logging
        if (WP_DEBUG_LOG) {
            error_log('WP Database Search: Searching for "' . $search_term . '" in column "' . $column_filter . '"');
        }
        
        $database_manager = new WP_Database_Search_Database_Manager();
        $results = $database_manager->search_data($search_term, $column_filter);
        
        // Debug logging
        if (WP_DEBUG_LOG) {
            error_log('WP Database Search: Found ' . count($results) . ' results');
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * Handle admin upload
     */
    public function handle_admin_upload() {
        $security = new WP_Database_Search_Security();
        if (!$security->verify_admin_capability() || !$security->verify_nonce('wp_database_search_admin_nonce')) {
            wp_die(__('Security check failed', 'wp-database-search'));
        }
        
        $file_processor = new WP_Database_Search_File_Processor();
        $result = $file_processor->process_uploaded_file();
        
        wp_send_json($result);
    }
    
    /**
     * Handle admin save
     */
    public function handle_admin_save() {
        $security = new WP_Database_Search_Security();
        if (!$security->verify_admin_capability() || !$security->verify_nonce('wp_database_search_admin_nonce')) {
            wp_die(__('Security check failed', 'wp-database-search'));
        }
        
        $database_manager = new WP_Database_Search_Database_Manager();
        $result = $database_manager->save_record();
        
        wp_send_json($result);
    }
    
    /**
     * Handle admin delete
     */
    public function handle_admin_delete() {
        $security = new WP_Database_Search_Security();
        if (!$security->verify_admin_capability() || !$security->verify_nonce('wp_database_search_admin_nonce')) {
            wp_die(__('Security check failed', 'wp-database-search'));
        }
        
        $database_manager = new WP_Database_Search_Database_Manager();
        $result = $database_manager->delete_record();
        
        wp_send_json($result);
    }
    
    /**
     * Handle admin export
     */
    public function handle_admin_export() {
        $security = new WP_Database_Search_Security();
        if (!$security->verify_admin_capability() || !$security->verify_nonce('wp_database_search_admin_nonce')) {
            wp_die(__('Security check failed', 'wp-database-search'));
        }
        
        $database_manager = new WP_Database_Search_Database_Manager();
        $database_manager->export_data();
    }
    
    /**
     * Add rewrite rules for detail pages
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^database-record/([0-9]+)/?$',
            'index.php?wp_database_search_record=$matches[1]',
            'top'
        );
    }
    
    /**
     * Add query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'wp_database_search_record';
        return $vars;
    }
    
    /**
     * Handle detail page
     */
    public function handle_detail_page() {
        $record_id = get_query_var('wp_database_search_record');
        
        if ($record_id) {
            $database_manager = new WP_Database_Search_Database_Manager();
            $record = $database_manager->get_record($record_id);
            
            if ($record) {
                $this->render_detail_page($record);
                exit;
            } else {
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
            }
        }
    }
    
    /**
     * Render detail page
     */
    private function render_detail_page($record) {
        $data = json_decode($record->data, true);
        
        get_header();
        ?>
        <div class="wp-database-search-detail-page">
            <div class="container">
                <h1><?php _e('Record Details', 'wp-database-search'); ?></h1>
                <div class="record-details">
                    <?php foreach ($data as $key => $value): ?>
                        <div class="detail-row">
                            <strong><?php echo esc_html($key); ?>:</strong>
                            <span><?php echo esc_html($value); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="back-link">
                    <a href="javascript:history.back()"><?php _e('← Back', 'wp-database-search'); ?></a>
                </div>
            </div>
        </div>
        <?php
        get_footer();
    }
    
    /**
     * Get table name
     */
    public function get_table_name() {
        return $this->table_name;
    }
}

// Initialize the plugin
WP_Database_Search::get_instance();
