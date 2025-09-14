<?php
/**
 * Admin Interface Class
 * 
 * Handles the admin interface for the WP Database Search plugin
 * 
 * @package WP_Database_Search
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Interface Class
 */
class WP_Database_Search_Admin_Interface {
    
    /**
     * Database manager instance
     */
    private $database_manager;
    
    /**
     * Current page
     */
    private $current_page;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database_manager = new WP_Database_Search_Database_Manager();
        $this->current_page = $_GET['tab'] ?? 'upload';
    }
    
    /**
     * Render admin page
     */
    public function render() {
        ?>
        <div class="wrap wp-database-search-admin">
            <h1><?php _e('WP Database Search', 'wp-database-search'); ?></h1>
            
            <?php $this->render_tabs(); ?>
            
            <div class="wp-database-search-content">
                <?php
                switch ($this->current_page) {
                    case 'upload':
                        $this->render_upload_page();
                        break;
                    case 'manage':
                        $this->render_manage_page();
                        break;
                    case 'settings':
                        $this->render_settings_page();
                        break;
                    default:
                        $this->render_upload_page();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render navigation tabs
     */
    private function render_tabs() {
        $tabs = array(
            'upload' => __('Upload Data', 'wp-database-search'),
            'manage' => __('Manage Data', 'wp-database-search'),
            'settings' => __('Settings', 'wp-database-search')
        );
        
        echo '<nav class="nav-tab-wrapper">';
        foreach ($tabs as $tab_key => $tab_label) {
            $active_class = ($this->current_page === $tab_key) ? ' nav-tab-active' : '';
            $url = admin_url('admin.php?page=wp-database-search&tab=' . $tab_key);
            echo '<a href="' . esc_url($url) . '" class="nav-tab' . $active_class . '">' . esc_html($tab_label) . '</a>';
        }
        echo '</nav>';
    }
    
    /**
     * Render upload page
     */
    private function render_upload_page() {
        $statistics = $this->database_manager->get_statistics();
        ?>
        <div class="wp-database-search-upload">
            <div class="upload-section">
                <h2><?php _e('Upload Excel/CSV File', 'wp-database-search'); ?></h2>
                <p><?php _e('Upload an Excel (.xls, .xlsx) or CSV file to import data into the search database.', 'wp-database-search'); ?></p>
                
                <form id="wp-database-search-upload-form" enctype="multipart/form-data">
                    <?php wp_nonce_field('wp_database_search_admin_nonce', 'nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="file"><?php _e('Select File', 'wp-database-search'); ?></label>
                            </th>
                            <td>
                                <input type="file" id="file" name="file" accept=".csv,.xls,.xlsx" required />
                                <p class="description">
                                    <?php _e('Maximum file size:', 'wp-database-search'); ?> <?php echo size_format(wp_max_upload_size()); ?>
                                    <br>
                                    <?php _e('Supported formats: CSV, XLS, XLSX', 'wp-database-search'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="clear_existing">
                                    <?php _e('Clear Existing Data', 'wp-database-search'); ?>
                                </label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="clear_existing" name="clear_existing" value="1" />
                                    <?php _e('Clear all existing data before importing new data', 'wp-database-search'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Warning: This will permanently delete all existing records.', 'wp-database-search'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php _e('Upload and Import', 'wp-database-search'); ?>
                        </button>
                    </p>
                </form>
                
                <div id="upload-progress" class="upload-progress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <p class="progress-text"><?php _e('Uploading...', 'wp-database-search'); ?></p>
                </div>
                
                <div id="upload-result" class="upload-result"></div>
            </div>
            
            <div class="statistics-section">
                <h3><?php _e('Current Statistics', 'wp-database-search'); ?></h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo esc_html($statistics['total_records']); ?></span>
                        <span class="stat-label"><?php _e('Total Records', 'wp-database-search'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo esc_html($statistics['total_columns']); ?></span>
                        <span class="stat-label"><?php _e('Data Columns', 'wp-database-search'); ?></span>
                    </div>
                </div>
                
                <?php if (!empty($statistics['columns'])): ?>
                <div class="columns-list">
                    <h4><?php _e('Available Columns', 'wp-database-search'); ?></h4>
                    <ul>
                        <?php foreach ($statistics['columns'] as $column): ?>
                            <li><?php echo esc_html($column); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render manage page
     */
    private function render_manage_page() {
        $page = max(1, intval($_GET['paged'] ?? 1));
        $per_page = 20;
        
        $data = $this->database_manager->get_all_records($page, $per_page);
        $records = $data['records'];
        $total_pages = $data['total_pages'];
        $total_records = $data['total'];
        
        ?>
        <div class="wp-database-search-manage">
            <div class="manage-header">
                <h2><?php _e('Manage Data', 'wp-database-search'); ?></h2>
                <div class="manage-actions">
                    <button type="button" class="button" id="add-new-record">
                        <?php _e('Add New Record', 'wp-database-search'); ?>
                    </button>
                    <button type="button" class="button" id="export-data">
                        <?php _e('Export Data', 'wp-database-search'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="clear-all-data">
                        <?php _e('Clear All Data', 'wp-database-search'); ?>
                    </button>
                </div>
            </div>
            
            <?php if (empty($records)): ?>
                <div class="no-data">
                    <p><?php _e('No data found. Upload a file to get started.', 'wp-database-search'); ?></p>
                </div>
            <?php else: ?>
                <div class="data-table-container">
                    <table class="wp-list-table widefat fixed striped" id="data-table">
                        <thead>
                            <tr>
                                <th class="column-id"><?php _e('ID', 'wp-database-search'); ?></th>
                                <?php
                                $columns = array_keys($records[0]['data']);
                                foreach ($columns as $column):
                                ?>
                                    <th class="column-<?php echo esc_attr(sanitize_title($column)); ?>">
                                        <?php echo esc_html($column); ?>
                                    </th>
                                <?php endforeach; ?>
                                <th class="column-actions"><?php _e('Actions', 'wp-database-search'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr data-record-id="<?php echo esc_attr($record['id']); ?>">
                                    <td class="column-id"><?php echo esc_html($record['id']); ?></td>
                                    <?php foreach ($columns as $column): ?>
                                        <td class="column-<?php echo esc_attr(sanitize_title($column)); ?>" data-column="<?php echo esc_attr($column); ?>">
                                            <span class="cell-content"><?php echo esc_html($record['data'][$column] ?? ''); ?></span>
                                            <input type="text" class="cell-edit" value="<?php echo esc_attr($record['data'][$column] ?? ''); ?>" style="display: none;" />
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="column-actions">
                                        <button type="button" class="button button-small edit-record" data-record-id="<?php echo esc_attr($record['id']); ?>">
                                            <?php _e('Edit', 'wp-database-search'); ?>
                                        </button>
                                        <button type="button" class="button button-small delete-record" data-record-id="<?php echo esc_attr($record['id']); ?>">
                                            <?php _e('Delete', 'wp-database-search'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <?php
                            $pagination_args = array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => __('&laquo;'),
                                'next_text' => __('&raquo;'),
                                'total' => $total_pages,
                                'current' => $page
                            );
                            echo paginate_links($pagination_args);
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Add/Edit Record Modal -->
        <div id="record-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modal-title"><?php _e('Add New Record', 'wp-database-search'); ?></h3>
                    <span class="close">&times;</span>
                </div>
                <div class="modal-body">
                    <form id="record-form">
                        <?php wp_nonce_field('wp_database_search_admin_nonce', 'nonce'); ?>
                        <input type="hidden" id="record-id" name="record_id" value="0" />
                        
                        <div id="record-fields">
                            <!-- Fields will be populated dynamically -->
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="button button-primary">
                                <?php _e('Save Record', 'wp-database-search'); ?>
                            </button>
                            <button type="button" class="button cancel-modal">
                                <?php _e('Cancel', 'wp-database-search'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    private function render_settings_page() {
        $settings = get_option('wp_database_search_settings', array());
        $all_columns = $this->database_manager->get_column_names();
        $filter_columns = get_option('wp_database_search_filter_columns', $all_columns);

        // Handle form submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'wp_database_search_settings')) {
            $new_settings = array(
                'search_delay' => intval($_POST['search_delay'] ?? 300),
                'results_per_page' => intval($_POST['results_per_page'] ?? 10),
                'enable_highlighting' => isset($_POST['enable_highlighting']),
                'custom_css' => sanitize_textarea_field($_POST['custom_css'] ?? ''),
                'detail_page_title' => sanitize_text_field($_POST['detail_page_title'] ?? 'Record Details')
            );
            update_option('wp_database_search_settings', $new_settings);
            $settings = $new_settings;

            // Save filter columns
            $selected_columns = isset($_POST['filter_columns']) && is_array($_POST['filter_columns'])
                ? array_map('sanitize_text_field', $_POST['filter_columns'])
                : $all_columns;
            update_option('wp_database_search_filter_columns', $selected_columns);
            $filter_columns = $selected_columns;

            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'wp-database-search') . '</p></div>';
        }

        ?>
        <div class="wp-database-search-settings">
            <h2><?php _e('Plugin Settings', 'wp-database-search'); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field('wp_database_search_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="search_delay"><?php _e('Search Delay (ms)', 'wp-database-search'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="search_delay" name="search_delay" value="<?php echo esc_attr($settings['search_delay'] ?? 300); ?>" min="100" max="2000" />
                            <p class="description">
                                <?php _e('Delay in milliseconds before performing search after user stops typing.', 'wp-database-search'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="results_per_page"><?php _e('Results Per Page', 'wp-database-search'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="results_per_page" name="results_per_page" value="<?php echo esc_attr($settings['results_per_page'] ?? 10); ?>" min="5" max="50" />
                            <p class="description">
                                <?php _e('Maximum number of search results to display per page.', 'wp-database-search'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="enable_highlighting"><?php _e('Enable Search Highlighting', 'wp-database-search'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="enable_highlighting" name="enable_highlighting" value="1" <?php checked($settings['enable_highlighting'] ?? true); ?> />
                                <?php _e('Highlight search terms in results', 'wp-database-search'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="detail_page_title"><?php _e('Detail Page Title', 'wp-database-search'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="detail_page_title" name="detail_page_title" value="<?php echo esc_attr($settings['detail_page_title'] ?? 'Record Details'); ?>" class="regular-text" />
                            <p class="description">
                                <?php _e('Title displayed on individual record detail pages.', 'wp-database-search'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="custom_css"><?php _e('Custom CSS', 'wp-database-search'); ?></label>
                        </th>
                        <td>
                            <textarea id="custom_css" name="custom_css" rows="10" cols="50" class="large-text code"><?php echo esc_textarea($settings['custom_css'] ?? ''); ?></textarea>
                            <p class="description">
                                <?php _e('Custom CSS to style the search interface. Leave empty to use default styles.', 'wp-database-search'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php if (!empty($all_columns)): ?>
                <h3><?php _e('Frontend Filter Options', 'wp-database-search'); ?></h3>
                <p><?php _e('Select which columns should be available as filter options in the frontend search bar:', 'wp-database-search'); ?></p>
                <div style="margin-bottom: 20px;">
                    <?php foreach ($all_columns as $col): ?>
                        <label style="display:inline-block; margin-right:20px;">
                            <input type="checkbox" name="filter_columns[]" value="<?php echo esc_attr($col); ?>" <?php checked(in_array($col, $filter_columns)); ?> />
                            <?php echo esc_html($col); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" value="<?php _e('Save Settings', 'wp-database-search'); ?>" />
                </p>
            </form>
            
            <div class="shortcode-info">
                <h3><?php _e('Shortcode Usage', 'wp-database-search'); ?></h3>
                <p><?php _e('Use the following shortcode to display the search interface on any page or post:', 'wp-database-search'); ?></p>
                <code>[wp_database_search]</code>
                
                <h4><?php _e('Shortcode Attributes', 'wp-database-search'); ?></h4>
                <ul>
                    <li><code>placeholder</code> - <?php _e('Custom placeholder text for search input', 'wp-database-search'); ?></li>
                    <li><code>show_column_filter</code> - <?php _e('Show column filter dropdown (true/false)', 'wp-database-search'); ?></li>
                    <li><code>results_limit</code> - <?php _e('Maximum number of results to show', 'wp-database-search'); ?></li>
                </ul>
                
                <h4><?php _e('Example', 'wp-database-search'); ?></h4>
                <code>[wp_database_search placeholder="Search our database..." show_column_filter="true" results_limit="20"]</code>
            </div>
        </div>
        <?php
    }
}
