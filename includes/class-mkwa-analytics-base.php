<?php
// includes/analytics/class-mkwa-analytics-base.php

defined('ABSPATH') || exit;

/**
 * Base class for MKWA Analytics functionality
 */
abstract class MKWA_Analytics_Base {
    /**
     * @var MKWA_Chart_Generator
     */
    protected $chart_generator;

    /**
     * @var string The current user's role (member or admin)
     */
    protected $user_role;

    /**
     * Constructor
     */
    public function __construct() {
        $this->chart_generator = new MKWA_Chart_Generator();
        $this->user_role = $this->get_user_role();
        $this->init();
    }

    /**
     * Initialize the analytics component
     */
    abstract protected function init();

    /**
     * Get the current user's role
     * 
     * @return string
     */
    protected function get_user_role() {
        if (current_user_can('manage_options')) {
            return 'admin';
        }
        return 'member';
    }

    /**
     * Check if user has access to analytics
     * 
     * @return boolean
     */
    protected function has_analytics_access() {
        return is_user_logged_in();
    }

    /**
     * Get date range for analytics
     * 
     * @param string $range daily|weekly|monthly|yearly
     * @return array
     */
    protected function get_date_range($range = 'daily') {
        $end = current_time('mysql');
        
        switch ($range) {
            case 'weekly':
                $start = date('Y-m-d H:i:s', strtotime('-7 days'));
                break;
            case 'monthly':
                $start = date('Y-m-d H:i:s', strtotime('-30 days'));
                break;
            case 'yearly':
                $start = date('Y-m-d H:i:s', strtotime('-365 days'));
                break;
            default:
                $start = date('Y-m-d H:i:s', strtotime('-24 hours'));
        }

        return array(
            'start' => $start,
            'end' => $end
        );
    }

    /**
     * Format data for charts
     * 
     * @param array $data Raw data
     * @return array Formatted data for Chart.js
     */
    protected function format_chart_data($data) {
        return array(
            'labels' => array_keys($data),
            'datasets' => array(
                array(
                    'data' => array_values($data),
                    'backgroundColor' => $this->get_chart_colors(),
                    'borderColor' => $this->get_chart_colors(true)
                )
            )
        );
    }

    /**
     * Get chart colors
     * 
     * @param boolean $border Whether to return border colors
     * @return array
     */
    protected function get_chart_colors($border = false) {
        $colors = array(
            '#FF6384',
            '#36A2EB',
            '#FFCE56',
            '#4BC0C0',
            '#9966FF'
        );

        if ($border) {
            return array_map(function($color) {
                return $this->adjust_color_brightness($color, -20);
            }, $colors);
        }

        return $colors;
    }

    /**
     * Adjust color brightness
     * 
     * @param string $hex_color
     * @param integer $percent_change
     * @return string
     */
    private function adjust_color_brightness($hex_color, $percent_change) {
        $hex_color = ltrim($hex_color, '#');
        
        $red = hexdec(substr($hex_color, 0, 2));
        $green = hexdec(substr($hex_color, 2, 2));
        $blue = hexdec(substr($hex_color, 4, 2));
        
        $red = max(0, min(255, $red + $percent_change));
        $green = max(0, min(255, $green + $percent_change));
        $blue = max(0, min(255, $blue + $percent_change));
        
        return sprintf('#%02x%02x%02x', $red, $green, $blue);
    }
}