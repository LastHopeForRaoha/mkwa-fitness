// admin/class-mkwa-badge-manager.php

class MKWA_Badge_Manager {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_badge_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function add_badge_menu() {
        add_menu_page(
            'MKWA Badges',
            'MKWA Badges',
            'manage_options',
            'mkwa-badges',
            array($this, 'render_badge_page'),
            'dashicons-awards',
            30
        );
    }

    public function render_badge_page() {
        // Check if user has required permissions
        if (!current_user_can('manage_options')) {
            return;
        }
        
        include MKWA_PLUGIN_DIR . 'admin/templates/badge-manager.php';
    }

    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_mkwa-badges' !== $hook) {
            return;
        }

        wp_enqueue_style('mkwa-admin-css', 
            MKWA_PLUGIN_URL . 'admin/css/badge-manager.css', 
            array(), 
            MKWA_VERSION
        );

        wp_enqueue_script('mkwa-admin-js',
            MKWA_PLUGIN_URL . 'admin/js/badge-manager.js',
            array('jquery'),
            MKWA_VERSION,
            true
        );
    }
}