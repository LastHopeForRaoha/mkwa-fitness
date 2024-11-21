<?php
// includes/core/class-mkwa-core.php

class MKWA_Core {
    protected $loader;
    protected $plugin_name;
    protected $version;
    protected $chart_generator;

    public function __construct() {
        $this->version = MKWA_VERSION;
        $this->plugin_name = 'mkwa-fitness';
        $this->load_dependencies();
        $this->setup_features();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies() {
        // Load any required dependencies
    }

    private function setup_features() {
        // Initialize Chart Generator
        $this->chart_generator = new MKWA_Chart_Generator();
        
        // Initialize Analytics Dashboard
        new MKWA_Analytics_Dashboard($this->chart_generator);
    }

    private function define_admin_hooks() {
        // Register admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    private function define_public_hooks() {
        // Register public scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
    }

    public function enqueue_admin_assets() {
        // Enqueue Chart.js library
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js',
            array(),
            '4.4.0',
            true
        );

        // Enqueue our custom charts JS
        wp_enqueue_script(
            'mkwa-charts',
            MKWA_PLUGIN_URL . 'assets/js/charts.js',
            array('jquery', 'chartjs'),
            $this->version,
            true
        );

        // Enqueue our custom charts CSS
        wp_enqueue_style(
            'mkwa-charts',
            MKWA_PLUGIN_URL . 'assets/css/charts.css',
            array(),
            $this->version
        );

        wp_enqueue_style(
            'mkwa-analytics-dashboard',
            MKWA_PLUGIN_URL . 'assets/css/analytics-dashboard.css',
            array('mkwa-charts'),
            $this->version
        );

        // Localize script for AJAX
        wp_localize_script('mkwa-charts', 'mkwaChartData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mkwa_chart_nonce'),
            'defaults' => array(
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => array(
                    'legend' => array(
                        'position' => 'top',
                    ),
                    'tooltip' => array(
                        'mode' => 'index',
                        'intersect' => false,
                    ),
                ),
            ),
        ));
    }

    public function enqueue_public_assets() {
        // Similar to admin_enqueue_scripts but only if needed on public pages
        if (has_shortcode(get_post()->post_content, 'mkwa_analytics_dashboard')) {
            $this->enqueue_admin_assets();
        }
    }

    public function run() {
        // Run the plugin
    }
}