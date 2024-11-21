<?php
// includes/features/analytics/class-mkwa-chart-generator.php

defined('ABSPATH') || exit;

/**
 * Class MKWA_Chart_Generator
 * 
 * Handles generation of charts and data visualizations for the analytics system.
 * Uses Chart.js for rendering and supports multiple chart types.
 * 
 * @since 1.0.0
 */
class MKWA_Chart_Generator {
    private static $instance = null;
    private $analytics_manager;
    private $default_colors = array(
        '#4e73df', // Primary blue
        '#1cc88a', // Success green
        '#36b9cc', // Info cyan
        '#f6c23e', // Warning yellow
        '#e74a3b', // Danger red
        '#858796', // Secondary gray
        '#5a5c69', // Dark gray
        '#2e59d9', // Royal blue
        '#17a673', // Forest green
        '#2c9faf'  // Ocean blue
    );

    private $chart_defaults = array(
        'responsive' => true,
        'maintainAspectRatio' => false,
        'plugins' => array(
            'legend' => array(
                'position' => 'bottom',
                'labels' => array(
                    'usePointStyle' => true,
                    'padding' => 20
                )
            ),
            'tooltip' => array(
                'mode' => 'index',
                'intersect' => false,
                'padding' => 10
            )
        )
    );

    private function __construct() {
        $this->analytics_manager = MKWA_Analytics_Manager::get_instance();
        $this->init();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Enqueue required JavaScript and CSS assets
     */
    public function enqueue_assets() {
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            array(),
            '4.4.0',
            true
        );

        wp_enqueue_style(
            'mkwa-charts',
            MKWA_PLUGIN_URL . 'assets/css/charts.css',
            array(),
            MKWA_VERSION
        );

        wp_enqueue_script(
            'mkwa-charts',
            MKWA_PLUGIN_URL . 'assets/js/charts.js',
            array('jquery', 'chartjs'),
            MKWA_VERSION,
            true
        );

        wp_localize_script('mkwa-charts', 'mkwaChartData', array(
            'nonce' => wp_create_nonce('mkwa_chart_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'defaults' => $this->chart_defaults,
            'colors' => $this->default_colors
        ));
    }

    /**
     * Generate a line chart configuration
     */
    public function generate_line_chart($data, $options = array()) {
        $defaults = array(
            'title' => '',
            'x_label' => '',
            'y_label' => '',
            'fill' => false,
            'tension' => 0.4
        );

        $config = array_merge($defaults, $options);

        return array(
            'type' => 'line',
            'data' => $this->format_chart_data($data),
            'options' => $this->get_chart_options($config)
        );
    }

    /**
     * Generate a bar chart configuration
     */
    public function generate_bar_chart($data, $options = array()) {
        $defaults = array(
            'title' => '',
            'x_label' => '',
            'y_label' => '',
            'stacked' => false
        );

        $config = array_merge($defaults, $options);

        return array(
            'type' => 'bar',
            'data' => $this->format_chart_data($data),
            'options' => $this->get_chart_options($config)
        );
    }

    /**
     * Generate a pie chart configuration
     */
    public function generate_pie_chart($data, $options = array()) {
        $defaults = array(
            'title' => '',
            'doughnut' => false
        );

        $config = array_merge($defaults, $options);

        return array(
            'type' => $config['doughnut'] ? 'doughnut' : 'pie',
            'data' => $this->format_pie_data($data),
            'options' => $this->get_pie_options($config)
        );
    }

    /**
     * Format data for standard charts (line, bar)
     */
    private function format_chart_data($data) {
        $datasets = array();
        $labels = array_keys(reset($data));

        foreach ($data as $label => $values) {
            $color = $this->default_colors[count($datasets) % count($this->default_colors)];
            $datasets[] = array(
                'label' => $label,
                'data' => array_values($values),
                'backgroundColor' => $color,
                'borderColor' => $color,
                'borderWidth' => 2
            );
        }

        return array(
            'labels' => $labels,
            'datasets' => $datasets
        );
    }

    /**
     * Format data for pie/doughnut charts
     */
    private function format_pie_data($data) {
        $backgrounds = array_slice($this->default_colors, 0, count($data));
        
        return array(
            'labels' => array_keys($data),
            'datasets' => array(
                array(
                    'data' => array_values($data),
                    'backgroundColor' => $backgrounds,
                    'borderWidth' => 1
                )
            )
        );
    }

    /**
     * Get standard chart options
     */
    private function get_chart_options($config) {
        return array_merge_recursive($this->chart_defaults, array(
            'plugins' => array(
                'title' => array(
                    'display' => !empty($config['title']),
                    'text' => $config['title']
                )
            ),
            'scales' => array(
                'x' => array(
                    'title' => array(
                        'display' => !empty($config['x_label']),
                        'text' => $config['x_label']
                    )
                ),
                'y' => array(
                    'title' => array(
                        'display' => !empty($config['y_label']),
                        'text' => $config['y_label']
                    ),
                    'beginAtZero' => true
                )
            )
        ));
    }

    /**
     * Get pie chart specific options
     */
    private function get_pie_options($config) {
        return array_merge_recursive($this->chart_defaults, array(
            'plugins' => array(
                'title' => array(
                    'display' => !empty($config['title']),
                    'text' => $config['title']
                )
            )
        ));
    }

    /**
     * Render a chart container
     */
    public function render_chart($id, $chart_config) {
        $container_id = esc_attr('mkwa-chart-' . $id);
        $config = esc_attr(json_encode($chart_config));
        
        return sprintf(
            '<div class="mkwa-chart-container"><canvas id="%s" data-chart-config="%s"></canvas></div>',
            $container_id,
            $config
        );
    }

    /**
     * Export chart as image
     */
    public function export_chart($chart_id, $format = 'png') {
        // Validate format
        if (!in_array($format, array('png', 'jpg', 'pdf'))) {
            return new WP_Error('invalid_format', 'Invalid export format');
        }

        // Get chart configuration
        $chart_config = get_transient('mkwa_chart_' . $chart_id);
        if (!$chart_config) {
            return new WP_Error('chart_not_found', 'Chart configuration not found');
        }

        // Implementation for chart export will depend on server-side rendering capabilities
        // This could be handled by a service like node-canvas or a third-party service
        
        return new WP_Error('not_implemented', 'Chart export not implemented');
    }
}