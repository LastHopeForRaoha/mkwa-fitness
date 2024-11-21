<?php
/**
 * Plugin Name: MKWA Fitness
 * Plugin URI: https://yourwebsite.com/mkwa-fitness
 * Description: A comprehensive fitness tracking and analytics plugin
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: mkwa
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MKWA_VERSION', '1.0.0');
define('MKWA_PLUGIN_FILE', __FILE__);
define('MKWA_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MKWA_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    // Plugin namespace prefix
    $prefix = 'MKWA_';
    $base_dir = MKWA_PLUGIN_PATH . 'includes/';

    // Check if the class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Replace underscores with directory separators
    $file = $base_dir . str_replace('_', '/', $relative_class) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Plugin Activation
register_activation_hook(__FILE__, 'mkwa_activate_plugin');
function mkwa_activate_plugin() {
    // Initialize database tables
    $database = MKWA_Database::get_instance();
    $database->create_tables();

    // Set default options
    add_option('mkwa_version', MKWA_VERSION);

    // Clear permalinks
    flush_rewrite_rules();
}

// Plugin Deactivation
register_deactivation_hook(__FILE__, 'mkwa_deactivate_plugin');
function mkwa_deactivate_plugin() {
    // Clear any plugin-specific options if needed
    flush_rewrite_rules();
}

// Initialize Plugin
add_action('plugins_loaded', 'mkwa_init_plugin');
function mkwa_init_plugin() {
    // Load text domain for translations
    load_plugin_textdomain('mkwa', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Initialize core classes
    MKWA_Core::get_instance();
    MKWA_Analytics_Manager::get_instance();
    MKWA_Points_Calculator::get_instance();
    MKWA_Points_Manager::get_instance();
    MKWA_Achievement_Manager::get_instance();
}

// Admin Menu
add_action('admin_menu', 'mkwa_add_admin_menu');
function mkwa_add_admin_menu() {
    add_menu_page(
        __('MKWA Fitness', 'mkwa'),
        __('MKWA Fitness', 'mkwa'),
        'manage_options',
        'mkwa-dashboard',
        'mkwa_render_dashboard_page',
        'dashicons-chart-bar',
        30
    );

    add_submenu_page(
        'mkwa-dashboard',
        __('Analytics', 'mkwa'),
        __('Analytics', 'mkwa'),
        'manage_options',
        'mkwa-analytics',
        'mkwa_render_analytics_page'
    );
}

// Render Functions
function mkwa_render_dashboard_page() {
    include MKWA_PLUGIN_PATH . 'templates/dashboard.php';
}

function mkwa_render_analytics_page() {
    include MKWA_PLUGIN_PATH . 'templates/analytics/dashboard.php';
}

// Enqueue Admin Scripts and Styles
add_action('admin_enqueue_scripts', 'mkwa_enqueue_admin_assets');
function mkwa_enqueue_admin_assets($hook) {
    // Only load on plugin pages
    if (strpos($hook, 'mkwa') === false) {
        return;
    }

    // Enqueue CSS
    wp_enqueue_style(
        'mkwa-admin-styles',
        MKWA_PLUGIN_URL . 'assets/css/admin.css',
        array(),
        MKWA_VERSION
    );

    // Analytics dashboard specific assets
    if ($hook === 'mkwa-fitness_page_mkwa-analytics') {
        // Analytics Dashboard CSS
        wp_enqueue_style(
            'mkwa-analytics-dashboard',
            MKWA_PLUGIN_URL . 'assets/css/analytics-dashboard.css',
            array(),
            MKWA_VERSION
        );

        // Chart.js Library
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js',
            array(),
            '3.7.0',
            true
        );

        // Analytics Dashboard JS
        wp_enqueue_script(
            'mkwa-analytics-dashboard',
            MKWA_PLUGIN_URL . 'assets/js/analytics-dashboard.js',
            array('jquery', 'chartjs'),
            MKWA_VERSION,
            true
        );

        // Localize script with necessary data
        wp_localize_script('mkwa-analytics-dashboard', 'mkwaAnalytics', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mkwa_analytics_nonce')
        ));
    }
}

// AJAX Handlers
add_action('wp_ajax_mkwa_get_analytics_data', 'mkwa_ajax_get_analytics_data');
function mkwa_ajax_get_analytics_data() {
    check_ajax_referer('mkwa_analytics_nonce', 'nonce');

    $analytics_manager = MKWA_Analytics_Manager::get_instance();
    $user_id = get_current_user_id();
    $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'last_30_days';

    $data = $analytics_manager->get_analytics_summary($user_id, $period);
    wp_send_json_success($data);
}

// Plugin Updates
add_action('plugins_loaded', 'mkwa_check_version');
function mkwa_check_version() {
    if (get_option('mkwa_version') !== MKWA_VERSION) {
        // Run update routine if needed
        mkwa_update_plugin();
        update_option('mkwa_version', MKWA_VERSION);
    }
}

function mkwa_update_plugin() {
    // Handle any version-specific updates here
    $database = MKWA_Database::get_instance();
    $database->update_tables();
}

// Security headers
add_action('send_headers', 'mkwa_add_security_headers');
function mkwa_add_security_headers() {
    if (!is_admin()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}

// Add REST API endpoints if needed
add_action('rest_api_init', 'mkwa_register_rest_routes');
function mkwa_register_rest_routes() {
    register_rest_route('mkwa/v1', '/analytics', array(
        'methods' => 'GET',
        'callback' => 'mkwa_rest_get_analytics',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
}

function mkwa_rest_get_analytics(WP_REST_Request $request) {
    $analytics_manager = MKWA_Analytics_Manager::get_instance();
    $user_id = get_current_user_id();
    $period = $request->get_param('period') ?: 'last_30_days';

    return rest_ensure_response(
        $analytics_manager->get_analytics_summary($user_id, $period)
    );
}

// Add any additional hooks and filters as needed
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'mkwa_add_plugin_action_links');
function mkwa_add_plugin_action_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=mkwa-dashboard') . '">' . __('Dashboard', 'mkwa') . '</a>',
        '<a href="' . admin_url('admin.php?page=mkwa-analytics') . '">' . __('Analytics', 'mkwa') . '</a>'
    );
    return array_merge($plugin_links, $links);
}

// Clean up on uninstall
register_uninstall_hook(__FILE__, 'mkwa_uninstall_plugin');
function mkwa_uninstall_plugin() {
    // Remove plugin options
    delete_option('mkwa_version');
    
    // Remove plugin tables if needed
    // Note: Uncomment the following lines if you want to remove tables on uninstall
    /*
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mkwa_analytics_events");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mkwa_analytics_metrics");
    */
}