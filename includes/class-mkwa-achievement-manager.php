// includes/features/achievements/class-mkwa-achievement-manager.php

class MKWA_Achievement_Manager {
    private static $instance = null;
    private $db;
    private $achievements_table;
    private $member_achievements_table;
    private $points_manager;

    private function __construct() {
        $this->db = MKWA_Database::get_instance();
        $this->achievements_table = $this->db->get_table_name('achievements');
        $this->member_achievements_table = $this->db->get_table_name('member_achievements');
        $this->points_manager = MKWA_Points_Manager::get_instance();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function create_achievement($data) {
        global $wpdb;

        $defaults = array(
            'name' => '',
            'description' => '',
            'badge_image_url' => '',
            'points_value' => 0,
            'requirements' => array(),
            'achievement_type' => 'standard'
        );

        $achievement_data = wp_parse_args($data, $defaults);
        
        // Validate required fields
        if (empty($achievement_data['name'])) {
            return new WP_Error('missing_name', 'Achievement name is required.');
        }

        // Convert requirements to JSON
        $achievement_data['requirements'] = json_encode($achievement_data['requirements']);

        $inserted = $wpdb->insert(
            $this->achievements_table,
            $achievement_data,
            array(
                '%s', // name
                '%s', // description
                '%s', // badge_image_url
                '%d', // points_value
                '%s', // requirements (JSON)
                '%s'  // achievement_type
            )
        );

        if ($inserted) {
            do_action('mkwa_achievement_created', $wpdb->insert_id, $achievement_data);
            return $wpdb->insert_id;
        }

        return new WP_Error('insert_failed', 'Failed to create achievement.');
    }

    public function award_achievement($member_id, $achievement_id) {
        global $wpdb;

        // Check if already awarded
        if ($this->has_achievement($member_id, $achievement_id)) {
            return new WP_Error('already_awarded', 'Member already has this achievement.');
        }

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Record achievement
            $awarded = $wpdb->insert(
                $this->member_achievements_table,
                array(
                    'member_id' => $member_id,
                    'achievement_id' => $achievement_id,
                    'earned_date' => current_time('mysql')
                ),
                array('%d', '%d', '%s')
            );

            if (!$awarded) {
                throw new Exception('Failed to record achievement.');
            }

            // Get achievement details
            $achievement = $this->get_achievement($achievement_id);
            
            // Award points
            if ($achievement['points_value'] > 0) {
                $points_result = $this->points_manager->award_points(
                    $member_id,
                    'achievement',
                    array('achievement_id' => $achievement_id)
                );

                if (is_wp_error($points_result)) {
                    throw new Exception($points_result->get_error_message());
                }
            }

            $wpdb->query('COMMIT');

            do_action('mkwa_achievement_awarded', $member_id, $achievement_id);
            
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('award_failed', $e->getMessage());
        }
    }

    public function get_achievement($achievement_id) {
        global $wpdb;

        $achievement = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->achievements_table} WHERE id = %d",
                $achievement_id
            ),
            ARRAY_A
        );

        if ($achievement) {
            $achievement['requirements'] = json_decode($achievement['requirements'], true);
        }

        return $achievement;
    }

    public function get_member_achievements($member_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT a.*, ma.earned_date
            FROM {$this->achievements_table} a
            JOIN {$this->member_achievements_table} ma ON a.id = ma.achievement_id
            WHERE ma.member_id = %d
            ORDER BY ma.earned_date DESC
        ", $member_id), ARRAY_A);
    }

    public function has_achievement($member_id, $achievement_id) {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$this->member_achievements_table}
            WHERE member_id = %d AND achievement_id = %d
        ", $member_id, $achievement_id));
    }

    public function check_achievements($member_id) {
        global $wpdb;

        // Get all achievements member doesn't have yet
        $available_achievements = $wpdb->get_results($wpdb->prepare("
            SELECT a.*
            FROM {$this->achievements_table} a
            LEFT JOIN {$this->member_achievements_table} ma 
                ON a.id = ma.achievement_id AND ma.member_id = %d
            WHERE ma.id IS NULL
        ", $member_id), ARRAY_A);

        foreach ($available_achievements as $achievement) {
            $achievement['requirements'] = json_decode($achievement['requirements'], true);
            
            if ($this->check_achievement_requirements($member_id, $achievement)) {
                $this->award_achievement($member_id, $achievement['id']);
            }
        }
    }

    private function check_achievement_requirements($member_id, $achievement) {
        if (empty($achievement['requirements'])) {
            return false;
        }

        foreach ($achievement['requirements'] as $requirement_type => $requirement_value) {
            switch ($requirement_type) {
                case 'total_visits':
                    if (!$this->check_total_visits($member_id, $requirement_value)) {
                        return false;
                    }
                    break;

                case 'streak_days':
                    if (!$this->check_streak_days($member_id, $requirement_value)) {
                        return false;
                    }
                    break;

                case 'total_points':
                    if (!$this->check_total_points($member_id, $requirement_value)) {
                        return false;
                    }
                    break;

                case 'activity_count':
                    if (!$this->check_activity_count($member_id, $requirement_value)) {
                        return false;
                    }
                    break;

                // Add more requirement types as needed
            }
        }

        return true;
    }

    private function check_total_visits($member_id, $required_visits) {
        global $wpdb;
        $activities_table = $this->db->get_table_name('activities');

        $visits = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$activities_table}
            WHERE member_id = %d AND activity_type = 'gym_visit'
        ", $member_id));

        return $visits >= $required_visits;
    }

    private function check_streak_days($member_id, $required_streak) {
        global $wpdb;
        $streaks_table = $this->db->get_table_name('workout_streaks');

        $current_streak = $wpdb->get_var($wpdb->prepare("
            SELECT current_streak
            FROM {$streaks_table}
            WHERE member_id = %d
        ", $member_id));

        return $current_streak >= $required_streak;
    }

    private function check_total_points($member_id, $required_points) {
        return $this->points_manager->get_total_points($member_id) >= $required_points;
    }

    private function check_activity_count($member_id, $requirements) {
        global $wpdb;
        $activities_table = $this->db->get_table_name('activities');

        foreach ($requirements as $activity_type => $count) {
            $actual_count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*)
                FROM {$activities_table}
                WHERE member_id = %d AND activity_type = %s
            ", $member_id, $activity_type));

            if ($actual_count < $count) {
                return false;
            }
        }

        return true;
    }
}