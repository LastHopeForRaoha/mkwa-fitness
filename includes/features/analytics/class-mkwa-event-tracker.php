// includes/features/analytics/class-mkwa-event-tracker.php

class MKWA_Event_Tracker {
    private static $instance = null;
    private $analytics_manager;
    private $event_types;

    private function __construct() {
        $this->analytics_manager = MKWA_Analytics_Manager::get_instance();
        $this->init_event_types();
        $this->setup_hooks();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init_event_types() {
        $this->event_types = array(
            'workout' => array(
                'start_workout',
                'complete_workout',
                'pause_workout',
                'resume_workout',
                'cancel_workout'
            ),
            'achievement' => array(
                'unlock_achievement',
                'progress_update',
                'milestone_reached'
            ),
            'program' => array(
                'join_program',
                'complete_program',
                'switch_program',
                'update_progress'
            ),
            'nutrition' => array(
                'log_meal',
                'track_water',
                'update_macro_goals'
            ),
            'social' => array(
                'share_achievement',
                'join_challenge',
                'invite_friend',
                'give_kudos'
            ),
            'system' => array(
                'app_open',
                'app_close',
                'feature_use',
                'error_occurred'
            )
        );

        $this->event_types = apply_filters('mkwa_event_types', $this->event_types);
    }

    private function setup_hooks() {
        // Workout Events
        add_action('mkwa_workout_started', array($this, 'track_workout_start'), 10, 2);
        add_action('mkwa_workout_completed', array($this, 'track_workout_completion'), 10, 2);
        add_action('mkwa_workout_paused', array($this, 'track_workout_pause'), 10, 2);
        add_action('mkwa_workout_resumed', array($this, 'track_workout_resume'), 10, 2);
        
        // Achievement Events
        add_action('mkwa_achievement_unlocked', array($this, 'track_achievement'), 10, 3);
        add_action('mkwa_milestone_reached', array($this, 'track_milestone'), 10, 3);
        
        // Program Events
        add_action('mkwa_program_joined', array($this, 'track_program_join'), 10, 3);
        add_action('mkwa_program_completed', array($this, 'track_program_completion'), 10, 3);
        
        // Nutrition Events
        add_action('mkwa_meal_logged', array($this, 'track_meal_log'), 10, 3);
        add_action('mkwa_water_tracked', array($this, 'track_water_intake'), 10, 2);
        
        // Social Events
        add_action('mkwa_achievement_shared', array($this, 'track_achievement_share'), 10, 3);
        add_action('mkwa_challenge_joined', array($this, 'track_challenge_join'), 10, 3);
    }

    // Workout Event Tracking Methods
    public function track_workout_start($member_id, $workout_data) {
        $event_data = array(
            'workout_id' => $workout_data['id'],
            'workout_type' => $workout_data['type'],
            'planned_duration' => $workout_data['planned_duration'],
            'program_id' => $workout_data['program_id'] ?? null
        );

        $this->analytics_manager->track_event('start_workout', $member_id, $event_data);
        $this->update_workout_metrics($member_id, 'workout_started', $workout_data);
    }

    public function track_workout_completion($member_id, $workout_data) {
        $event_data = array(
            'workout_id' => $workout_data['id'],
            'actual_duration' => $workout_data['actual_duration'],
            'calories_burned' => $workout_data['calories_burned'],
            'exercises_completed' => $workout_data['exercises_completed'],
            'performance_score' => $this->calculate_performance_score($workout_data)
        );

        $this->analytics_manager->track_event('complete_workout', $member_id, $event_data);
        $this->update_workout_metrics($member_id, 'workout_completed', $workout_data);
    }

    // Achievement Event Tracking Methods
    public function track_achievement($member_id, $achievement_id, $achievement_data) {
        $event_data = array(
            'achievement_id' => $achievement_id,
            'achievement_type' => $achievement_data['type'],
            'points_earned' => $achievement_data['points'],
            'requirements_met' => $achievement_data['requirements']
        );

        $this->analytics_manager->track_event('unlock_achievement', $member_id, $event_data);
        $this->update_achievement_metrics($member_id, $achievement_data);
    }

    // Program Event Tracking Methods
    public function track_program_join($member_id, $program_id, $program_data) {
        $event_data = array(
            'program_id' => $program_id,
            'program_type' => $program_data['type'],
            'difficulty_level' => $program_data['difficulty'],
            'estimated_duration' => $program_data['duration']
        );

        $this->analytics_manager->track_event('join_program', $member_id, $event_data);
        $this->update_program_metrics($member_id, 'program_joined', $program_data);
    }

    // Nutrition Event Tracking Methods
    public function track_meal_log($member_id, $meal_id, $meal_data) {
        $event_data = array(
            'meal_id' => $meal_id,
            'meal_type' => $meal_data['type'],
            'calories' => $meal_data['calories'],
            'macros' => $meal_data['macros'],
            'time_of_day' => $meal_data['time']
        );

        $this->analytics_manager->track_event('log_meal', $member_id, $event_data);
        $this->update_nutrition_metrics($member_id, $meal_data);
    }

    // Social Event Tracking Methods
    public function track_achievement_share($member_id, $achievement_id, $share_data) {
        $event_data = array(
            'achievement_id' => $achievement_id,
            'platform' => $share_data['platform'],
            'share_type' => $share_data['type'],
            'audience' => $share_data['audience']
        );

        $this->analytics_manager->track_event('share_achievement', $member_id, $event_data);
        $this->update_social_metrics($member_id, 'achievement_shared', $share_data);
    }

    // Metric Update Methods
    private function update_workout_metrics($member_id, $event_type, $data) {
        switch ($event_type) {
            case 'workout_completed':
                $this->analytics_manager->update_metric('total_workouts', $member_id, 1, array('increment' => true));
                $this->analytics_manager->update_metric('total_workout_duration', $member_id, $data['actual_duration'], array('increment' => true));
                $this->analytics_manager->update_metric('total_calories_burned', $member_id, $data['calories_burned'], array('increment' => true));
                break;
            
            case 'workout_started':
                $this->analytics_manager->update_metric('workout_streak', $member_id, $this->calculate_workout_streak($member_id));
                break;
        }
    }

    private function update_achievement_metrics($member_id, $achievement_data) {
        $this->analytics_manager->update_metric('total_achievements', $member_id, 1, array('increment' => true));
        $this->analytics_manager->update_metric('achievement_points', $member_id, $achievement_data['points'], array('increment' => true));
    }

    private function update_program_metrics($member_id, $event_type, $data) {
        if ($event_type === 'program_joined') {
            $this->analytics_manager->update_metric('programs_joined', $member_id, 1, array('increment' => true));
        }
    }

    private function update_nutrition_metrics($member_id, $meal_data) {
        $this->analytics_manager->update_metric('total_calories_consumed', $member_id, $meal_data['calories'], array('increment' => true));
        foreach ($meal_data['macros'] as $macro => $value) {
            $this->analytics_manager->update_metric("total_{$macro}", $member_id, $value, array('increment' => true));
        }
    }

    // Helper Methods
    private function calculate_performance_score($workout_data) {
        // Implementation of performance score calculation
        $score_factors = array(
            'completion_rate' => $this->get_completion_rate($workout_data),
            'intensity_score' => $this->get_intensity_score($workout_data),
            'form_accuracy' => $workout_data['form_accuracy'] ?? 1.0
        );

        return array_sum($score_factors) / count($score_factors) * 100;
    }

    private function get_completion_rate($workout_data) {
        if (empty($workout_data['planned_exercises'])) {
            return 1.0;
        }
        return count($workout_data['exercises_completed']) / count($workout_data['planned_exercises']);
    }

    private function get_intensity_score($workout_data) {
        $target_heart_rate = $workout_data['target_heart_rate'] ?? 0;
        $actual_heart_rate = $workout_data['average_heart_rate'] ?? 0;

        if ($target_heart_rate === 0) {
            return 1.0;
        }

        return min(1.0, $actual_heart_rate / $target_heart_rate);
    }

    private function calculate_workout_streak($member_id) {
        // Implementation of workout streak calculation
        global $wpdb;
        $workouts_table = MKWA_Database::get_instance()->get_table_name('workouts');
        
        $streak = $wpdb->get_var($wpdb->prepare(
            "WITH consecutive_days AS (
                SELECT 
                    workout_date,
                    DATE_SUB(workout_date, INTERVAL ROW_NUMBER() OVER (ORDER BY workout_date) DAY) AS group_date
                FROM (
                    SELECT DISTINCT DATE(workout_date) as workout_date
                    FROM {$workouts_table}
                    WHERE member_id = %d
                    AND workout_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ) distinct_dates
            )
            SELECT COUNT(*)
            FROM consecutive_days
            WHERE group_date = (
                SELECT group_date
                FROM consecutive_days
                WHERE workout_date = DATE(NOW())
            )",
            $member_id
        ));

        return (int) $streak;
    }
}