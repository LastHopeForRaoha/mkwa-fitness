<?php
// includes/features/analytics/class-mkwa-analytics-ajax.php

defined('ABSPATH') || exit;

class MKWA_Analytics_AJAX {
    private static $instance = null;
    private $chart_generator;
    private $analytics_manager;

    private function __construct() {
        $this->chart_generator = MKWA_Chart_Generator::get_instance();
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
        add_action('wp_ajax_mkwa_refresh_chart', array($this, 'refresh_chart'));
        add_action('wp_ajax_mkwa_export_chart', array($this, 'export_chart'));
    }

    public function refresh_chart() {
        check_ajax_referer('mkwa_chart_nonce', 'nonce');

        $chart_id = sanitize_text_field($_POST['chart_id']);
        $user_id = get_current_user_id();

        // Get fresh data based on chart ID
        switch ($chart_id) {
            case 'workout-progress':
                $data = $this->analytics_manager->get_workout_stats($user_id, 'last_30_days');
                $response = $this->chart_generator->generate_line_chart(
                    array(
                        'Workouts Completed' => $data['completed'],
                        'Average Duration' => $data['duration']
                    ),
                    array(
                        'title' => 'Workout Progress',
                        'x_label' => 'Date',
                        'y_label' => 'Count/Minutes',
                        'fill' => true
                    )
                );
                break;

            case 'strength-progress':
                $data = $this->analytics_manager->get_progress_metrics($user_id, 'last_30_days');
                $response = $this->chart_generator->generate_bar_chart(
                    array(
                        'Bench Press' => $data['bench_press'],
                        'Squat' => $data['squat'],
                        'Deadlift' => $data['deadlift']
                    ),
                    array(
                        'title' => 'Strength Progress',
                        'x_label' => 'Week',
                        'y_label' => 'Weight (lbs)',
                        'stacked' => false
                    )
                );
                break;

            // Add more cases for other chart types

            default:
                wp_send_json_error('Invalid chart ID');
                return;
        }

        wp_send_json_success($response);
    }

    public function export_chart() {
        check_ajax_referer('mkwa_chart_nonce', 'nonce');

        $chart_id = sanitize_text_field($_POST['chart_id']);
        $format = sanitize_text_field($_POST['format']);

        try {
            $result = $this->chart_generator->export_chart($chart_id, $format);
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            wp_send_json_success(array('url' => $result));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}

// Initialize AJAX handler
MKWA_Analytics_AJAX::get_instance();