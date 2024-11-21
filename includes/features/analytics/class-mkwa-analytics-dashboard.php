<?php
// includes/features/analytics/class-mkwa-analytics-dashboard.php

defined('ABSPATH') || exit;

/**
 * Class MKWA_Analytics_Dashboard
 * 
 * Handles the analytics dashboard initialization, scripts, and shortcode
 */
class MKWA_Analytics_Dashboard {
    private static $instance = null;

    private function __construct() {
        $this->init();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init() {
        // Register scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Register shortcode
        add_shortcode('mkwa_analytics_dashboard', array($this, 'render_dashboard'));
    }

    public function enqueue_assets() {
        wp_enqueue_style(
            'mkwa-analytics-dashboard',
            MKWA_PLUGIN_URL . 'assets/css/analytics-dashboard.css',
            array('mkwa-charts'),
            MKWA_VERSION
        );
    }

    public function render_dashboard() {
        ob_start();
        include MKWA_PLUGIN_DIR . 'templates/analytics/dashboard.php';
        return ob_get_clean();
    }
}