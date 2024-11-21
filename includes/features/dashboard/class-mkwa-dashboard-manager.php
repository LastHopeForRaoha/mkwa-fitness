// includes/features/dashboard/class-mkwa-dashboard-manager.php

class MKWA_Dashboard_Manager {
    private static $instance = null;
    private $db;
    private $report_manager;

    private function __construct() {
        $this->db = MKWA_Database::get_instance();
        $this->report_manager = MKWA_Report_Manager::get_instance();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_member_dashboard($member_id) {
        $dashboard_data = array(
            'summary' => $this->get_summary_stats($member_id),
            'recent_activity' => $this->get_recent_activity($member_id),
            'progress_charts' => $this->get_progress_charts($member_id),
            'achievement_overview' => $this->get_achievement_overview($member_id),
            'workout_trends' => $this->get_workout_trends($member_id),
            'streak_data' => $this->get_streak_data($member_id)
        );

        return apply_filters('mkwa_dashboard_data', $dashboard_data, $member_id);
    }

    private function get_summary_stats($member_id) {
        global $wpdb;

        $activities_table = $this->db->get_table_name('activities');
        $workouts_table = $this->db->get_table_name('workouts');
        $achievements_table = $this->db->get_table_name('member_achievements');

        // Last 30 days stats
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));

        $stats = array(
            'total_workouts' => $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*)
                FROM $workouts_table
                WHERE member_id = %d
                AND workout_date >= %s
            ", $member_id, $thirty_days_ago)),

            'total_duration' => $wpdb->get_var($wpdb->prepare("
                SELECT SUM(duration)
                FROM $activities_table
                WHERE member_id = %d
                AND created_at >= %s
            ", $member_id, $thirty_days_ago)),

            'achievement_count' => $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*)
                FROM $achievements_table
                WHERE member_id = %d
            ", $member_id)),

            'current_streak' => $this->calculate_current_streak($member_id)
        );

        return $stats;
    }

    private function get_recent_activity($member_id) {
        global $wpdb;

        $activities_table = $this->db->get_table_name('activities');
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM $activities_table
            WHERE member_id = %d
            ORDER BY created_at DESC
            LIMIT 5
        ", $member_id), ARRAY_A);
    }

    private function get_progress_charts($member_id) {
        global $wpdb;

        $progress_table = $this->db->get_table_name('progress_tracking');
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));

        $metrics = $wpdb->get_results($wpdb->prepare("
            SELECT metric_type, metric_value, recorded_date
            FROM $progress_table
            WHERE member_id = %d
            AND recorded_date >= %s
            ORDER BY recorded_date ASC
        ", $member_id, $thirty_days_ago), ARRAY_A);

        $chart_data = array();
        foreach ($metrics as $metric) {
            if (!isset($chart_data[$metric['metric_type']])) {
                $chart_data[$metric['metric_type']] = array();
            }
            $chart_data[$metric['metric_type']][] = array(
                'date' => $metric['recorded_date'],
                'value' => $metric['metric_value']
            );
        }

        return $chart_data;
    }

    private function get_achievement_overview($member_id) {
        global $wpdb;

        $achievements_table = $this->db->get_table_name('member_achievements');
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                achievement_type,
                COUNT(*) as count,
                MAX(earned_date) as latest_earned
            FROM $achievements_table
            WHERE member_id = %d
            GROUP BY achievement_type
        ", $member_id), ARRAY_A);
    }

    private function get_workout_trends($member_id) {
        global $wpdb;

        $workouts_table = $this->db->get_table_name('workouts');
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));

        $trends = array(
            'by_type' => $wpdb->get_results($wpdb->prepare("
                SELECT 
                    workout_type,
                    COUNT(*) as count,
                    AVG(duration) as avg_duration
                FROM $workouts_table
                WHERE member_id = %d
                AND workout_date >= %s
                GROUP BY workout_type
            ", $member_id, $thirty_days_ago), ARRAY_A),

            'by_day' => $wpdb->get_results($wpdb->prepare("
                SELECT 
                    DAYNAME(workout_date) as day_name,
                    COUNT(*) as count
                FROM $workouts_table
                WHERE member_id = %d
                AND workout_date >= %s
                GROUP BY day_name
                ORDER BY DAYOFWEEK(workout_date)
            ", $member_id, $thirty_days_ago), ARRAY_A)
        );

        return $trends;
    }

    private function get_streak_data($member_id) {
        global $wpdb;

        $workouts_table = $this->db->get_table_name('workouts');
        
        $streak_data = array(
            'current_streak' => $this->calculate_current_streak($member_id),
            'longest_streak' => $this->get_longest_streak($member_id),
            'weekly_completion' => $this->get_weekly_completion_rate($member_id)
        );

        return $streak_data;
    }

    private function calculate_current_streak($member_id) {
        global $wpdb;

        $workouts_table = $this->db->get_table_name('workouts');
        $current_date = current_time('Y-m-d');

        $consecutive_days = 0;
        $check_date = $current_date;

        while (true) {
            $has_workout = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*)
                FROM $workouts_table
                WHERE member_id = %d
                AND DATE(workout_date) = %s
            ", $member_id, $check_date));

            if ($has_workout) {
                $consecutive_days++;
                $check_date = date('Y-m-d', strtotime("-1 day", strtotime($check_date)));
            } else {
                break;
            }
        }

        return $consecutive_days;
    }

    private function get_longest_streak($member_id) {
        global $wpdb;

        $workouts_table = $this->db->get_table_name('workouts');
        
        $workout_dates = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT DATE(workout_date) as workout_date
            FROM $workouts_table
            WHERE member_id = %d
            ORDER BY workout_date
        ", $member_id));

        $longest_streak = 0;
        $current_streak = 0;
        $prev_date = null;

        foreach ($workout_dates as $date) {
            if ($prev_date === null) {
                $current_streak = 1;
            } else {
                $days_diff = (strtotime($date) - strtotime($prev_date)) / (60 * 60 * 24);
                if ($days_diff == 1) {
                    $current_streak++;
                } else {
                    $longest_streak = max($longest_streak, $current_streak);
                    $current_streak = 1;
                }
            }
            $prev_date = $date;
        }

        return max($longest_streak, $current_streak);
    }

    private function get_weekly_completion_rate($member_id) {
        global $wpdb;

        $workouts_table = $this->db->get_table_name('workouts');
        $four_weeks_ago = date('Y-m-d', strtotime('-4 weeks'));

        $weekly_workouts = $wpdb->get_results($wpdb->prepare("
            SELECT 
                YEARWEEK(workout_date) as week,
                COUNT(DISTINCT DATE(workout_date)) as days_worked_out
            FROM $workouts_table
            WHERE member_id = %d
            AND workout_date >= %s
            GROUP BY week
        ", $member_id, $four_weeks_ago), ARRAY_A);

        $completion_rates = array();
        foreach ($weekly_workouts as $week) {
            $completion_rates[] = array(
                'week' => $week['week'],
                'rate' => ($week['days_worked_out'] / 7) * 100
            );
        }

        return $completion_rates;
    }

    public function get_dashboard_widgets($member_id) {
        $widgets = array(
            'summary' => array(
                'title' => 'Summary',
                'data' => $this->get_summary_stats($member_id),
                'type' => 'stats'
            ),
            'streak' => array(
                'title' => 'Current Streak',
                'data' => $this->get_streak_data($member_id),
                'type' => 'streak'
            ),
            'progress' => array(
                'title' => 'Progress Charts',
                'data' => $this->get_progress_charts($member_id),
                'type' => 'chart'
            ),
            'activity' => array(
                'title' => 'Recent Activity',
                'data' => $this->get_recent_activity($member_id),
                'type' => 'list'
            )
        );

        return apply_filters('mkwa_dashboard_widgets', $widgets, $member_id);
    }
}