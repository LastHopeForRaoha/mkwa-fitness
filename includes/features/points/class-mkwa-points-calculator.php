// includes/features/points/class-mkwa-points-calculator.php

class MKWA_Points_Calculator {
    private static $instance = null;
    private $settings;

    private function __construct() {
        $this->settings = get_option('mkwa_settings', array());
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function calculate_points($activity_type, $data = array()) {
        switch ($activity_type) {
            case 'gym_visit':
                return $this->calculate_visit_points($data);
            
            case 'class_attendance':
                return $this->calculate_class_points($data);
            
            case 'referral':
                return $this->calculate_referral_points();
            
            case 'streak_bonus':
                return $this->calculate_streak_bonus($data);
            
            case 'achievement':
                return $this->calculate_achievement_points($data);
            
            case 'community_goal':
                return $this->calculate_community_goal_points($data);
            
            default:
                return 0;
        }
    }

    private function calculate_visit_points($data) {
        $base_points = $this->get_setting('points_per_visit', 10);
        
        // Apply time-of-day multiplier if visiting during off-peak hours
        if (isset($data['visit_time']) && $this->is_off_peak_hour($data['visit_time'])) {
            $base_points *= $this->get_setting('off_peak_multiplier', 1.5);
        }

        return (int) $base_points;
    }

    private function calculate_class_points($data) {
        $base_points = $this->get_setting('points_per_class', 20);
        
        // Additional points for premium classes
        if (isset($data['class_type']) && $data['class_type'] === 'premium') {
            $base_points *= $this->get_setting('premium_class_multiplier', 1.5);
        }

        return (int) $base_points;
    }

    private function calculate_referral_points() {
        return $this->get_setting('points_per_referral', 50);
    }

    private function calculate_streak_bonus($data) {
        $streak_days = isset($data['streak_days']) ? (int) $data['streak_days'] : 0;
        $minimum_streak = $this->get_setting('minimum_streak_days', 3);
        
        if ($streak_days < $minimum_streak) {
            return 0;
        }

        $base_points = $this->get_setting('points_per_visit', 10);
        $multiplier = $this->get_setting('streak_bonus_multiplier', 1.5);
        
        return (int) ($base_points * $multiplier);
    }

    private function calculate_achievement_points($data) {
        if (!isset($data['achievement_id'])) {
            return 0;
        }

        global $wpdb;
        $achievements_table = MKWA_Database::get_instance()->get_table_name('achievements');
        
        $points = $wpdb->get_var($wpdb->prepare("
            SELECT points_value
            FROM {$achievements_table}
            WHERE id = %d
        ", $data['achievement_id']));

        return (int) $points;
    }

    private function calculate_community_goal_points($data) {
        if (!isset($data['goal_id'])) {
            return 0;
        }

        global $wpdb;
        $goals_table = MKWA_Database::get_instance()->get_table_name('community_goals');
        
        $points = $wpdb->get_var($wpdb->prepare("
            SELECT reward_points
            FROM {$goals_table}
            WHERE id = %d
        ", $data['goal_id']));

        return (int) $points;
    }

    private function is_off_peak_hour($timestamp) {
        $hour = date('G', strtotime($timestamp));
        $off_peak_start = $this->get_setting('off_peak_start', 10);
        $off_peak_end = $this->get_setting('off_peak_end', 16);
        
        return $hour >= $off_peak_start && $hour <= $off_peak_end;
    }

    private function get_setting($key, $default = null) {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    public function estimate_points_for_activity($activity_type, $member_id = null) {
        $data = array();
        
        // Add member-specific data if available
        if ($member_id) {
            $streak_data = $this->get_member_streak_data($member_id);
            $data['streak_days'] = $streak_data['current_streak'];
        }
        
        // Get current timestamp for time-based calculations
        $data['visit_time'] = current_time('mysql');
        
        return $this->calculate_points($activity_type, $data);
    }

    private function get_member_streak_data($member_id) {
        global $wpdb;
        $streaks_table = MKWA_Database::get_instance()->get_table_name('workout_streaks');
        
        $streak_data = $wpdb->get_row($wpdb->prepare("
            SELECT current_streak, longest_streak, last_activity_date
            FROM {$streaks_table}
            WHERE member_id = %d
        ", $member_id), ARRAY_A);

        return $streak_data ?: array(
            'current_streak' => 0,
            'longest_streak' => 0,
            'last_activity_date' => null
        );
    }

    public function calculate_bonus_multiplier($member_id) {
        $streak_data = $this->get_member_streak_data($member_id);
        $base_multiplier = 1.0;
        
        // Streak bonus
        if ($streak_data['current_streak'] >= $this->get_setting('minimum_streak_days', 3)) {
            $base_multiplier *= $this->get_setting('streak_bonus_multiplier', 1.5);
        }
        
        // Membership type bonus
        $member = $this->get_member_data($member_id);
        if ($member && $member['membership_type'] === 'premium') {
            $base_multiplier *= $this->get_setting('premium_member_multiplier', 1.2);
        }
        
        return $base_multiplier;
    }

    private function get_member_data($member_id) {
        global $wpdb;
        $members_table = MKWA_Database::get_instance()->get_table_name('members');
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT *
            FROM {$members_table}
            WHERE id = %d
        ", $member_id), ARRAY_A);
    }

    public function get_points_history($member_id, $limit = 10) {
        global $wpdb;
        $points_table = MKWA_Database::get_instance()->get_table_name('points');
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT points, transaction_type, activity_type, description, created_at
            FROM {$points_table}
            WHERE member_id = %d
            ORDER BY created_at DESC
            LIMIT %d
        ", $member_id, $limit), ARRAY_A);
    }

    public function get_total_points($member_id) {
        global $wpdb;
        $points_table = MKWA_Database::get_instance()->get_table_name('points');
        
        return (int) $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(
                CASE 
                    WHEN transaction_type = 'earned' THEN points
                    WHEN transaction_type IN ('redeemed', 'expired') THEN -points
                    ELSE points
                END
            ), 0)
            FROM {$points_table}
            WHERE member_id = %d
        ", $member_id));
    }
}