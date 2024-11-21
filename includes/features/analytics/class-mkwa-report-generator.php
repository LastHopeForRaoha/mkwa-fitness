// includes/features/analytics/class-mkwa-report-generator.php

class MKWA_Report_Generator {
    private static $instance = null;
    private $analytics_manager;
    private $event_tracker;
    private $report_cache;

    private function __construct() {
        $this->analytics_manager = MKWA_Analytics_Manager::get_instance();
        $this->event_tracker = MKWA_Event_Tracker::get_instance();
        $this->report_cache = new MKWA_Cache('analytics_reports');
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function generate_member_report($member_id, $report_type, $date_range = 'last_30_days', $options = array()) {
        $cache_key = "member_{$member_id}_{$report_type}_{$date_range}";
        $cached_report = $this->report_cache->get($cache_key);

        if ($cached_report !== false && !isset($options['bypass_cache'])) {
            return $cached_report;
        }

        $report_data = $this->build_report($member_id, $report_type, $date_range, $options);
        
        if (!empty($report_data)) {
            $this->report_cache->set($cache_key, $report_data, HOUR_IN_SECONDS);
        }

        return $report_data;
    }

    private function build_report($member_id, $report_type, $date_range, $options) {
        $date_limits = $this->get_date_range_limits($date_range);
        
        switch ($report_type) {
            case 'workout_summary':
                return $this->generate_workout_summary($member_id, $date_limits, $options);
            
            case 'progress_tracking':
                return $this->generate_progress_report($member_id, $date_limits, $options);
            
            case 'achievement_overview':
                return $this->generate_achievement_report($member_id, $date_limits, $options);
            
            case 'nutrition_analysis':
                return $this->generate_nutrition_report($member_id, $date_limits, $options);
            
            case 'program_progress':
                return $this->generate_program_progress_report($member_id, $date_limits, $options);
            
            case 'comprehensive':
                return $this->generate_comprehensive_report($member_id, $date_limits, $options);
            
            default:
                return $this->generate_custom_report($member_id, $report_type, $date_limits, $options);
        }
    }

    private function generate_workout_summary($member_id, $date_limits, $options) {
        $workouts = $this->analytics_manager->get_member_metrics(
            $member_id,
            array('workout_completion', 'workout_duration', 'workout_intensity'),
            $date_limits['start'],
            $date_limits['end']
        );

        $summary = array(
            'total_workouts' => count($workouts),
            'total_duration' => array_sum(array_column($workouts, 'duration')),
            'avg_intensity' => $this->calculate_average($workouts, 'intensity'),
            'completion_rate' => $this->calculate_completion_rate($workouts),
            'workout_types' => $this->aggregate_workout_types($workouts),
            'progress_trends' => $this->calculate_progress_trends($workouts),
            'peak_performance' => $this->identify_peak_performance($workouts),
            'recommendations' => $this->generate_workout_recommendations($workouts)
        );

        return array(
            'report_type' => 'workout_summary',
            'member_id' => $member_id,
            'date_range' => $date_limits,
            'generated_at' => current_time('mysql'),
            'data' => $summary
        );
    }

    private function generate_progress_report($member_id, $date_limits, $options) {
        $metrics = $this->analytics_manager->get_member_metrics(
            $member_id,
            array('weight', 'body_measurements', 'strength_scores', 'endurance_metrics'),
            $date_limits['start'],
            $date_limits['end']
        );

        $progress = array(
            'metrics_summary' => $this->summarize_progress_metrics($metrics),
            'goal_tracking' => $this->track_progress_goals($member_id, $metrics),
            'milestone_achievements' => $this->list_milestone_achievements($member_id, $date_limits),
            'trend_analysis' => $this->analyze_progress_trends($metrics),
            'comparison_data' => $this->generate_comparison_data($member_id, $metrics),
            'recommendations' => $this->generate_progress_recommendations($metrics)
        );

        return array(
            'report_type' => 'progress_tracking',
            'member_id' => $member_id,
            'date_range' => $date_limits,
            'generated_at' => current_time('mysql'),
            'data' => $progress
        );
    }

    private function generate_achievement_report($member_id, $date_limits, $options) {
        $achievements = $this->event_tracker->get_member_achievements(
            $member_id,
            $date_limits['start'],
            $date_limits['end']
        );

        $report = array(
            'total_achievements' => count($achievements),
            'achievement_categories' => $this->categorize_achievements($achievements),
            'points_earned' => $this->calculate_achievement_points($achievements),
            'completion_timeline' => $this->create_achievement_timeline($achievements),
            'rarest_achievements' => $this->identify_rare_achievements($achievements),
            'next_achievements' => $this->predict_next_achievements($member_id),
            'leaderboard_position' => $this->get_leaderboard_position($member_id),
            'achievement_recommendations' => $this->recommend_achievements($member_id)
        );

        return array(
            'report_type' => 'achievement_overview',
            'member_id' => $member_id,
            'date_range' => $date_limits,
            'generated_at' => current_time('mysql'),
            'data' => $report
        );
    }

    private function generate_nutrition_report($member_id, $date_limits, $options) {
        $nutrition_data = $this->analytics_manager->get_member_metrics(
            $member_id,
            array('meal_logs', 'macro_tracking', 'water_intake', 'supplement_logs'),
            $date_limits['start'],
            $date_limits['end']
        );

        $report = array(
            'calorie_summary' => $this->analyze_calorie_intake($nutrition_data),
            'macro_breakdown' => $this->analyze_macro_distribution($nutrition_data),
            'meal_patterns' => $this->identify_meal_patterns($nutrition_data),
            'hydration_tracking' => $this->analyze_water_intake($nutrition_data),
            'nutrition_goals' => $this->track_nutrition_goals($member_id, $nutrition_data),
            'dietary_adherence' => $this->calculate_dietary_adherence($nutrition_data),
            'recommendations' => $this->generate_nutrition_recommendations($nutrition_data)
        );

        return array(
            'report_type' => 'nutrition_analysis',
            'member_id' => $member_id,
            'date_range' => $date_limits,
            'generated_at' => current_time('mysql'),
            'data' => $report
        );
    }

    // Helper methods for calculations and analysis
    private function get_date_range_limits($range) {
        $end = current_time('mysql');
        
        switch ($range) {
            case 'last_7_days':
                $start = date('Y-m-d H:i:s', strtotime('-7 days'));
                break;
            case 'last_30_days':
                $start = date('Y-m-d H:i:s', strtotime('-30 days'));
                break;
            case 'last_90_days':
                $start = date('Y-m-d H:i:s', strtotime('-90 days'));
                break;
            case 'year_to_date':
                $start = date('Y-01-01 00:00:00');
                break;
            default:
                if (strpos($range, '|') !== false) {
                    list($start, $end) = explode('|', $range);
                } else {
                    $start = date('Y-m-d H:i:s', strtotime('-30 days'));
                }
        }

        return array(
            'start' => $start,
            'end' => $end
        );
    }

    private function calculate_average($data, $metric) {
        if (empty($data)) {
            return 0;
        }
        return array_sum(array_column($data, $metric)) / count($data);
    }

    private function format_report($data, $format = 'array') {
        switch ($format) {
            case 'json':
                return json_encode($data);
            case 'csv':
                return $this->convert_to_csv($data);
            case 'pdf':
                return $this->generate_pdf_report($data);
            default:
                return $data;
        }
    }
}