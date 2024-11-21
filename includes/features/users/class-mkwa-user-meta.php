// includes/features/users/class-mkwa-user-meta.php

class MKWA_User_Meta {
    private static $instance = null;
    private $user_manager;

    private function __construct() {
        $this->user_manager = MKWA_User_Manager::get_instance();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_total_points($member_id) {
        global $wpdb;
        $points_table = MKWA_Database::get_instance()->get_table_name('points');

        return (int) $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(
                CASE 
                    WHEN transaction_type = 'earned' THEN points
                    WHEN transaction_type = 'redeemed' THEN -points
                    WHEN transaction_type = 'expired' THEN -points
                    WHEN transaction_type = 'adjusted' THEN points
                    ELSE 0
                END
            ), 0)
            FROM {$points_table}
            WHERE member_id = %d
        ", $member_id));
    }

    public function get_achievement_count($member_id) {
        global $wpdb;
        $achievements_table = MKWA_Database::get_instance()->get_table_name('member_achievements');

        return (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$achievements_table}
            WHERE member_id = %d
        ", $member_id));
    }

    public function get_current_streak($member_id) {
        global $wpdb;
        $streaks_table = MKWA_Database::get_instance()->get_table_name('workout_streaks');

        return (int) $wpdb->get_var($wpdb->prepare("
            SELECT current_streak
            FROM {$streaks_table}
            WHERE member_id = %d
        ", $member_id));
    }

    public function get_member_stats($member_id) {
        $member = $this->user_manager->get_member($member_id);
        
        if (!$member) {
            return false;
        }

        return array(
            'total_points' => $this->get_total_points($member_id),
            'achievements' => $this->get_achievement_count($member_id),
            'current_streak' => $this->get_current_streak($member_id),
            'member_since' => $member['join_date'],
            'last_activity' => $this->get_last_activity_date($member_id),
            'membership_type' => $member['membership_type']
        );
    }

    private function get_last_activity_date($member_id) {
        global $wpdb;
        $activities_table = MKWA_Database::get_instance()->get_table_name('activities');

        return $wpdb->get_var($wpdb->prepare("
            SELECT created_at
            FROM {$activities_table}
            WHERE member_id = %d
            ORDER BY created_at DESC
            LIMIT 1
        ", $member_id));
    }

    public function update_privacy_settings($member_id, $settings) {
        $current_member = $this->user_manager->get_member($member_id);
        
        if (!$current_member) {
            return false;
        }

        $current_settings = is_array($current_member['privacy_settings']) 
            ? $current_member['privacy_settings']
            : array();

        $updated_settings = wp_parse_args($settings, $current_settings);

        return $this->user_manager->update_member($member_id, array(
            'privacy_settings' => $updated_settings
        ));
    }
}