<?php
/**
 * Frontend Search Class
 * 
 * Handles the frontend search interface for the WP Database Search plugin
 * 
 * @package WP_Database_Search
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend Search Class
 */
class WP_Database_Search_Frontend_Search {
    
    /**
     * Database manager instance
     */
    private $database_manager;
    
    /**
     * Settings
     */
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database_manager = new WP_Database_Search_Database_Manager();
        $this->settings = get_option('wp_database_search_settings', array());
    }
    
    /**
     * Render search shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render($atts) {
        $atts = shortcode_atts(array(
            'placeholder' => __('Search...', 'wp-database-search'),
            'show_column_filter' => 'true',
            'results_limit' => $this->settings['results_per_page'] ?? 10,
            'search_delay' => $this->settings['search_delay'] ?? 300
        ), $atts, 'wp_database_search');
        
        // Get available columns for filter dropdown
        $all_columns = $this->database_manager->get_column_names();
        $filter_columns = get_option('wp_database_search_filter_columns', $all_columns);
        $columns = array_values(array_intersect($all_columns, $filter_columns));
        
        ob_start();
        ?>
        <div class="wp-database-search-container" data-results-limit="<?php echo esc_attr($atts['results_limit']); ?>" data-search-delay="<?php echo esc_attr($atts['search_delay']); ?>">
            <div class="wp-database-search-form">
                <div class="search-input-container">
                    <input type="text" 
                           id="wp-database-search-input" 
                           class="wp-database-search-input" 
                           placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                           autocomplete="off" />
                    <div class="search-loading" style="display: none;">
                        <span class="spinner"></span>
                    </div>
                </div>
                
                <?php if ($atts['show_column_filter'] === 'true' && !empty($columns)): ?>
                <div class="column-filter-container">
                    <select id="wp-database-search-column-filter" class="wp-database-search-column-filter">
                        <option value=""><?php _e('All Columns', 'wp-database-search'); ?></option>
                        <?php foreach ($columns as $column): ?>
                            <option value="<?php echo esc_attr($column); ?>"><?php echo esc_html($column); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="wp-database-search-results" id="wp-database-search-results" style="display: none;">
                <div class="results-header">
                    <span class="results-count"></span>
                    <button type="button" class="clear-search" style="display: none;">
                        <?php _e('Clear', 'wp-database-search'); ?>
                    </button>
                </div>
                <div class="results-list"></div>
                <div class="results-footer">
                    <div class="no-results" style="display: none;">
                        <p><?php _e('No results found. Try adjusting your search terms.', 'wp-database-search'); ?></p>
                    </div>
                    <div class="search-error" style="display: none;">
                        <p><?php _e('An error occurred while searching. Please try again.', 'wp-database-search'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render search result item
     * 
     * @param array $result Search result data
     * @param string $search_term Search term for highlighting
     * @return string HTML output
     */
    public function render_result_item($result, $search_term = '') {
        $highlighting_enabled = $this->settings['enable_highlighting'] ?? true;
        
        ob_start();
        ?>
        <div class="search-result-item" data-record-id="<?php echo esc_attr($result['id']); ?>">
            <div class="result-content">
                <h4 class="result-title">
                    <a href="<?php echo esc_url($result['url']); ?>" class="result-link">
                        <?php echo esc_html($this->get_result_title($result['data'])); ?>
                    </a>
                </h4>
                
                <div class="result-summary">
                    <?php if ($highlighting_enabled && !empty($search_term)): ?>
                        <?php echo $this->highlight_search_term($result['summary'], $search_term); ?>
                    <?php else: ?>
                        <?php echo esc_html($result['summary']); ?>
                    <?php endif; ?>
                </div>
                
                <div class="result-meta">
                    <span class="result-id"><?php printf(__('ID: %s', 'wp-database-search'), esc_html($result['id'])); ?></span>
                    <a href="<?php echo esc_url($result['url']); ?>" class="view-details">
                        <?php _e('View Details', 'wp-database-search'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get result title from data
     * 
     * @param array $data Record data
     * @return string Title
     */
    private function get_result_title($data) {
        // Try to find a good title field
        $title_fields = array('name', 'title', 'company', 'organization', 'business');
        
        foreach ($title_fields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                return $data[$field];
            }
        }
        
        // If no title field found, use first non-empty field
        foreach ($data as $key => $value) {
            if (!empty($value)) {
                return $value;
            }
        }
        
        return __('Record', 'wp-database-search');
    }
    
    /**
     * Highlight search term in text
     * 
     * @param string $text Text to highlight
     * @param string $search_term Search term
     * @return string Highlighted text
     */
    private function highlight_search_term($text, $search_term) {
        if (empty($search_term) || empty($text)) {
            return esc_html($text);
        }
        
        $highlighted = preg_replace(
            '/(' . preg_quote($search_term, '/') . ')/i',
            '<mark class="search-highlight">$1</mark>',
            esc_html($text)
        );
        
        return $highlighted;
    }
    
    /**
     * Get search statistics
     * 
     * @return array Statistics
     */
    public function get_search_statistics() {
        return $this->database_manager->get_statistics();
    }
    
    /**
     * Render search widget (for sidebar)
     * 
     * @param array $args Widget arguments
     * @return string HTML output
     */
    public function render_widget($args = array()) {
        $defaults = array(
            'title' => __('Database Search', 'wp-database-search'),
            'placeholder' => __('Search...', 'wp-database-search'),
            'show_column_filter' => 'false',
            'results_limit' => 5
        );
        
        $args = wp_parse_args($args, $defaults);
        
        ob_start();
        ?>
        <div class="wp-database-search-widget">
            <?php if (!empty($args['title'])): ?>
                <h3 class="widget-title"><?php echo esc_html($args['title']); ?></h3>
            <?php endif; ?>
            
            <?php echo $this->render(array(
                'placeholder' => $args['placeholder'],
                'show_column_filter' => $args['show_column_filter'],
                'results_limit' => $args['results_limit']
            )); ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get search suggestions
     * 
     * @param string $search_term Search term
     * @param int $limit Limit number of suggestions
     * @return array Suggestions
     */
    public function get_search_suggestions($search_term, $limit = 5) {
        if (strlen($search_term) < 2) {
            return array();
        }
        
        $results = $this->database_manager->search_data($search_term);
        $suggestions = array();
        
        foreach (array_slice($results, 0, $limit) as $result) {
            $title = $this->get_result_title($result['data']);
            if (!in_array($title, $suggestions)) {
                $suggestions[] = $title;
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Render search suggestions
     * 
     * @param array $suggestions Suggestions array
     * @return string HTML output
     */
    public function render_suggestions($suggestions) {
        if (empty($suggestions)) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="search-suggestions">
            <ul>
                <?php foreach ($suggestions as $suggestion): ?>
                    <li class="suggestion-item" data-suggestion="<?php echo esc_attr($suggestion); ?>">
                        <?php echo esc_html($suggestion); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get popular searches
     * 
     * @param int $limit Limit number of popular searches
     * @return array Popular searches
     */
    public function get_popular_searches($limit = 5) {
        // This would typically be stored in a separate table
        // For now, we'll return empty array
        return array();
    }
    
    /**
     * Log search query
     * 
     * @param string $search_term Search term
     * @param int $results_count Number of results
     */
    public function log_search_query($search_term, $results_count) {
        if (!WP_DEBUG_LOG) {
            return;
        }
        
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'search_term' => $search_term,
            'results_count' => $results_count,
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        
        error_log('WP Database Search Query: ' . json_encode($log_data));
    }
    
    /**
     * Get search form HTML for custom integration
     * 
     * @param array $args Form arguments
     * @return string HTML output
     */
    public function get_search_form($args = array()) {
        $defaults = array(
            'form_class' => 'wp-database-search-form',
            'input_class' => 'wp-database-search-input',
            'button_class' => 'wp-database-search-button',
            'placeholder' => __('Search...', 'wp-database-search'),
            'button_text' => __('Search', 'wp-database-search'),
            'show_column_filter' => true
        );
        
        $args = wp_parse_args($args, $defaults);
        $columns = $this->database_manager->get_column_names();
        
        ob_start();
        ?>
        <form class="<?php echo esc_attr($args['form_class']); ?>" method="get">
            <div class="search-input-group">
                <input type="text" 
                       name="search" 
                       class="<?php echo esc_attr($args['input_class']); ?>" 
                       placeholder="<?php echo esc_attr($args['placeholder']); ?>"
                       value="<?php echo esc_attr(get_query_var('search')); ?>" />
                
                <?php if ($args['show_column_filter'] && !empty($columns)): ?>
                <select name="column" class="column-filter">
                    <option value=""><?php _e('All Columns', 'wp-database-search'); ?></option>
                    <?php foreach ($columns as $column): ?>
                        <option value="<?php echo esc_attr($column); ?>" <?php selected(get_query_var('column'), $column); ?>>
                            <?php echo esc_html($column); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                
                <button type="submit" class="<?php echo esc_attr($args['button_class']); ?>">
                    <?php echo esc_html($args['button_text']); ?>
                </button>
            </div>
        </form>
        <?php
        
        return ob_get_clean();
    }
}
