// includes/features/activities/class-mkwa-activity-logger.php

class MKWA_Activity_Logger {
    private static $instance = null;
    private $db;
    private $activities_table;
    private $points_manager;

    private function __construct() {
        $this->db = MKWA_Database::get_instance();
        $this->activities_table = $this->db->get_table_name('activities');
        $this->points_manager = MKWA_Points_Manager::get_instance();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function log_activity($member_id, $activity_type, $data = array()) {
        global $wpdb;

        if (!$this->validate_activity_type($activity_type)) {
            return new WP_Error('invalid_activity', 'Invalid activity type.');
        }

        // Prepare activity data
        $activity = array(
            'member_id' => $member_id,
            'activity_type' => $activity_type,
            'duration' => isset($data['duration']) ? $data['duration'] : null,
            'intensity_level' => isset($data['intensity_level']) ? $data['intensity_level'] : null,
            'points_earned' => 0,
            'comments' => isset($data['comments']) ? $data['comments'] : '',
            'created_at' => current_time('mysql')
        );

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Insert activity record
            $inserted = $wpdb->insert(
                $this->activities_table,
                $activity,
                array(
                    '%d', // member_id
                    '%s', // activity_type
                    '%d', // duration
                    '%s', // intensity_level
                    '%d', // points_earned
                    '%s', // comments
                    '%s'  // created_at
                )
            );

            if (!$inserted) {
                throw new Exception('Failed to log activity.');
            }

            $activity_id = $wpdb->insert_id;

            // Award points for the activity
            $points_result = $this->points_manager->award_points($member_id, $activity_type, $data);
            
            if (is_wp_error($points_result)) {
                throw new Exception($points_result->get_error_message());
            }

            // Update points earned in activity record
            $wpdb->update(
                $this->activities_table,
                array('points_earned' => $points_result),
                array('id' => $activity_id),
                array('%d'),
                array('%d')
            );

            // Update streak if applicable
            $this->update_streak($member_id);

            $wpdb->query('COMMIT');

            do_action('mkwa_activity_logged', $activity_id, $member_id, $activity_type, $data);
            
            return $activity_id;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('activity_log_failed', $e->getMessage());
        }
    }

    private function update_streak($member_id) {
        global $wpdb;
        $streaks_table = $this->db->get_table_name('workout_streaks');
        $current_date = current_time('Y-m-d');

        // Get current streak data
        $streak_data = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$streaks_table}
            WHERE member_id = %d
        ", $member_id), ARRAY_A);

        if (!$streak_data) {
            // Initialize streak record if it doesn't exist
            $wpdb->insert(
                $streaks_table,
                array(
                    'member_id' => $member_id,
                    'current_streak' => 1,
                    'longest_streak' => 1,
                    'last_activity_date' => $current_date,
                    'streak_start_date' => $current_date
                ),
                array('%d', '%d', '%d', '%s', '%s')
            );
            return;
        }

        $last_activity = new DateTime($streak_data['last_activity_date']);
        $today = new DateTime($current_date);
        $diff = $today->diff($last_activity);

        // Only update if this is the first activity today
        if ($streak_data['last_activity_date'] !== $current_date) {
            if ($diff->days == 1) {
                // Consecutive day
                $new_streak = $streak_data['current_streak'] + 1;
                $longest_streak = max($new_streak, $streak_data['longest_streak']);
                
                $wpdb->update(
                    $streaks_table,
                    array(
                        'current_streak' => $new_streak,
                        'longest_streak' => $longest_streak,
                        'last_activity_date' => $current_date
                    ),
                    array('member_id' => $member_id),
                    array('%d', '%d', '%s'),
                    array('%d')
                );
            } elseif ($diff->days > 1) {
                // Streak broken
                $wpdb->update(
                    $streaks_table,
                    array(
                        'current_streak' => 1,
                        'last_activity_date' => $current_date,
                        'streak_start_date' => $current_date
                    ),
                    array('member_id' => $member_id),
                    array('%d', '%s', '%s'),
                    array('%d')
                );
            }
        }
    }

    public function get_member_activities($member_id, $limit = 10, $offset = 0) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$this->activities_table}
            WHERE member_id = %d
            ORDER BY created_at DESC
            LIMIT %d OFFSET %d
        ", $member_id, $limit, $offset), ARRAY_A);
    }

    public function get_activity_stats($member_id) {
        global $wpdb;

        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_activities,
                SUM(duration) as total_duration,
                SUM(points_earned) as total_points,
                COUNT(DISTINCT DATE(created_at)) as active_days
            FROM {$this->activities_table}
            WHERE member_id = %d
        ", $member_id), ARRAY_A);

        $streak_data = $wpdb->get_row($wpdb->prepare("
            SELECT current_streak, longest_streak
            FROM {$this->db->get_table_name('workout_streaks')}
            WHERE member_id = %d
        ", $member_id), ARRAY_A);

        return array_merge($stats, $streak_data ?: array(
            'current_streak' => 0,
            'longest_streak' => 0
        ));
    }

    private function validate_activity_type($activity_type) {
        $valid_types = MKWA_Activity_Types::get_instance()->get_activity_types();
        return in_array($activity_type, array_keys($valid_types));
    }
}