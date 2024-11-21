// includes/features/analytics/class-mkwa-analytics-processor.php

class MKWA_Analytics_Processor {
    private static $instance = null;
    private $analytics_manager;
    private $db;

    private function __construct() {
        $this->analytics_manager = MKWA_Analytics_Manager::get_instance();
        $this->db = MKWA_Database::get_instance();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function process_member_analytics($member_id, $time_range = '30d') {
        $analytics = array(
            'summary' => $this->get_member_summary($member_id, $time_range),
            'trends' => $this->analyze_trends($member_id, $time_range),
            'performance' => $this->analyze_performance($member_id, $time_range),
            'goals' => $this->analyze_goals_progress($member_id),
            'recommendations' => $this->generate_recommendations($member_id)
        );

        return apply_filters('mkwa_processed_analytics', $analytics, $member_id, $time_range);
    }

    public function get_member_summary($member_id, $time_range) {
        $end_date = current_time('mysql');
        $start_date = $this->calculate_start_date($end_date, $time_range);

        return array(
            'total_workouts' => $this->get_total_workouts($member_id, $start_date, $end_date),
            'total_duration' => $this->get_total_duration($member_id, $start_date, $end_date),
            'total_calories' => $this->get_total_calories($member_id, $start_date, $end_date),
            'achievements' => $this->get_achievement_summary($member_id, $start_date, $end_date),
            'current_streak' => $this->get_current_streak($member_id),
            'completion_rate' => $this->calculate_completion_rate($member_id, $start_date, $end_date)
        );
    }

    public function analyze_trends($member_id, $time_range) {
        $end_date = current_time('mysql');
        $start_date = $this->calculate_start_date($end_date, $time_range);

        return array(
            'workout_frequency' => $this->analyze_workout_frequency($member_id, $start_date, $end_date),
            'duration_trend' => $this->analyze_duration_trend($member_id, $start_date, $end_date),
            'intensity_trend' => $this->analyze_intensity_trend($member_id, $start_date, $end_date),
            'preferred_times' => $this->analyze_preferred_times($member_id, $start_date, $end_date),
            'workout_types' => $this->analyze_workout_types($member_id, $start_date, $end_date),
            'performance_trend' => $this->analyze_performance_trend($member_id, $start_date, $end_date)
        );
    }

    public function analyze_performance($member_id, $time_range) {
        $end_date = current_time('mysql');
        $start_date = $this->calculate_start_date($end_date, $time_range);

        return array(
            'overall_score' => $this->calculate_overall_performance($member_id, $start_date, $end_date),
            'strength_progress' => $this->analyze_strength_progress($member_id, $start_date, $end_date),
            'cardio_progress' => $this->analyze_cardio_progress($member_id, $start_date, $end_date),
            'form_accuracy' => $this->analyze_form_accuracy($member_id, $start_date, $end_date),
            'personal_records' => $this->get_personal_records($member_id, $start_date, $end_date),
            'improvement_areas' => $this->identify_improvement_areas($member_id)
        );
    }

    public function analyze_goals_progress($member_id) {
        return array(
            'active_goals' => $this->get_active_goals($member_id),
            'completed_goals' => $this->get_completed_goals($member_id),
            'progress_metrics' => $this->calculate_goals_progress($member_id),
            'projected_completion' => $this->project_goal_completion($member_id),
            'success_rate' => $this->calculate_goals_success_rate($member_id)
        );
    }

    public function generate_recommendations($member_id) {
        return array(
            'workout_suggestions' => $this->suggest_workouts($member_id),
            'intensity_adjustments' => $this->suggest_intensity_adjustments($member_id),
            'rest_days' => $this->suggest_rest_days($member_id),
            'progression_path' => $this->suggest_progression_path($member_id),
            'habit_improvements' => $this->suggest_habit_improvements($member_id)
        );
    }

    private function calculate_start_date($end_date, $time_range) {
        $interval = preg_replace('/[^0-9]/', '', $time_range);
        $unit = preg_replace('/[^a-zA-Z]/', '', $time_range);
        
        switch ($unit) {
            case 'w':
                $interval *= 7;
                $unit = 'd';
                break;
            case 'm':
                $interval *= 30;
                $unit = 'd';
                break;
            case 'y':
                $interval *= 365;
                $unit = 'd';
                break;
        }

        return date('Y-m-d H:i:s', strtotime("-{$interval}{$unit}", strtotime($end_date)));
    }

    private function get_total_workouts($member_id, $start_date, $end_date) {
        global $wpdb;
        $workouts_table = $this->db->get_table_name('workouts');

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
            FROM {$workouts_table} 
            WHERE member_id = %d 
            AND workout_date BETWEEN %s AND %s",
            $member_id,
            $start_date,
            $end_date
        ));
    }

    private function analyze_workout_frequency($member_id, $start_date, $end_date) {
        global $wpdb;
        $workouts_table = $this->db->get_table_name('workouts');

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(workout_date) as workout_date,
                COUNT(*) as daily_count,
                DAYNAME(workout_date) as day_of_week
            FROM {$workouts_table}
            WHERE member_id = %d
            AND workout_date BETWEEN %s AND %s
            GROUP BY DATE(workout_date)
            ORDER BY workout_date",
            $member_id,
            $start_date,
            $end_date
        ), ARRAY_A);

        return array(
            'daily_distribution' => $this->process_daily_distribution($results),
            'weekly_average' => $this->calculate_weekly_average($results),
            'consistency_score' => $this->calculate_consistency_score($results)
        );
    }

    private function analyze_intensity_trend($member_id, $start_date, $end_date) {
        global $wpdb;
        $workouts_table = $this->db->get_table_name('workouts');

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(workout_date) as date,
                AVG(intensity_score) as avg_intensity,
                MAX(intensity_score) as max_intensity,
                workout_type
            FROM {$workouts_table}
            WHERE member_id = %d
            AND workout_date BETWEEN %s AND %s
            GROUP BY DATE(workout_date), workout_type
            ORDER BY date",
            $member_id,
            $start_date,
            $end_date
        ), ARRAY_A);

        return array(
            'trend_line' => $this->calculate_trend_line($results, 'avg_intensity'),
            'peak_intensity_days' => $this->identify_peak_days($results),
            'intensity_by_type' => $this->group_by_workout_type($results)
        );
    }

    private function calculate_trend_line($data, $metric_key) {
        if (empty($data)) {
            return array();
        }

        $x_values = array_keys($data);
        $y_values = array_column($data, $metric_key);
        
        $n = count($data);
        $sum_x = array_sum($x_values);
        $sum_y = array_sum($y_values);
        $sum_xy = 0;
        $sum_xx = 0;

        for ($i = 0; $i < $n; $i++) {
            $sum_xy += $x_values[$i] * $y_values[$i];
            $sum_xx += $x_values[$i] * $x_values[$i];
        }

        $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_xx - $sum_x * $sum_x);
        $intercept = ($sum_y - $slope * $sum_x) / $n;

        return array(
            'slope' => $slope,
            'intercept' => $intercept,
            'trend_direction' => $slope > 0 ? 'increasing' : 'decreasing'
        );
    }

    private function suggest_workouts($member_id) {
        $recent_performance = $this->analyze_recent_performance($member_id);
        $user_preferences = $this->get_user_preferences($member_id);
        $current_goals = $this->get_active_goals($member_id);

        $suggestions = array();

        // Base suggestions on performance gaps
        foreach ($recent_performance['improvement_areas'] as $area) {
            $suggestions[] = $this->get_workout_for_improvement($area);
        }

        // Consider user preferences
        $suggestions = array_merge(
            $suggestions,
            $this->get_preferred_workout_types($user_preferences)
        );

        // Align with goals
        $suggestions = $this->prioritize_suggestions_by_goals($suggestions, $current_goals);

        return array_slice($suggestions, 0, 5); // Return top 5 suggestions
    }

    private function suggest_intensity_adjustments($member_id) {
        $recent_workouts = $this->get_recent_workouts($member_id, 5);
        $recovery_status = $this->assess_recovery_status($member_id);
        $performance_trend = $this->analyze_performance_trend($member_id, '-14 days');

        return array(
            'recommended_intensity' => $this->calculate_recommended_intensity(
                $recent_workouts,
                $recovery_status,
                $performance_trend
            ),
            'reasoning' => $this->generate_intensity_reasoning(
                $recovery_status,
                $performance_trend
            ),
            'adjustment_period' => $this->determine_adjustment_period($member_id)
        );
    }

    private function suggest_habit_improvements($member_id) {
        $workout_patterns = $this->analyze_workout_patterns($member_id);
        $success_factors = $this->analyze_success_factors($member_id);
        $common_obstacles = $this->identify_common_obstacles($member_id);

        return array(
            'schedule_optimizations' => $this->suggest_schedule_improvements($workout_patterns),
            'consistency_tips' => $this->generate_consistency_tips($success_factors),
            'obstacle_solutions' => $this->suggest_obstacle_solutions($common_obstacles)
        );
    }
}