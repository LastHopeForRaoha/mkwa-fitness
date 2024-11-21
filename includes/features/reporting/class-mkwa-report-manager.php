// includes/features/reporting/class-mkwa-report-manager.php

class MKWA_Report_Manager {
    private static $instance = null;
    private $db;
    private $reports_table;
    private $report_data_table;

    private function __construct() {
        $this->db = MKWA_Database::get_instance();
        $this->reports_table = $this->db->get_table_name('reports');
        $this->report_data_table = $this->db->get_table_name('report_data');
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function generate_member_report($member_id, $report_type, $params = array()) {
        $report_data = array();
        $start_date = isset($params['start_date']) ? $params['start_date'] : null;
        $end_date = isset($params['end_date']) ? $params['end_date'] : current_time('mysql');

        switch ($report_type) {
            case 'activity_summary':
                $report_data = $this->generate_activity_summary($member_id, $start_date, $end_date);
                break;
            case 'progress_tracking':
                $report_data = $this->generate_progress_report($member_id, $start_date, $end_date);
                break;
            case 'achievement_stats':
                $report_data = $this->generate_achievement_stats($member_id);
                break;
            case 'workout_analytics':
                $report_data = $this->generate_workout_analytics($member_id, $start_date, $end_date);
                break;
        }

        return $this->save_report($member_id, $report_type, $report_data, $params);
    }

    private function generate_activity_summary($member_id, $start_date, $end_date) {
        global $wpdb;

        $activities_table = $this->db->get_table_name('activities');
        
        $query = $wpdb->prepare("
            SELECT 
                activity_type,
                COUNT(*) as total_activities,
                SUM(duration) as total_duration,
                AVG(intensity_level) as avg_intensity
            FROM $activities_table
            WHERE member_id = %d
            AND created_at BETWEEN %s AND %s
            GROUP BY activity_type
        ", $member_id, $start_date, $end_date);

        $results = $wpdb->get_results($query, ARRAY_A);

        return array(
            'summary' => $results,
            'total_activities' => array_sum(array_column($results, 'total_activities')),
            'total_duration' => array_sum(array_column($results, 'total_duration')),
            'period' => array(
                'start' => $start_date,
                'end' => $end_date
            )
        );
    }

    private function generate_progress_report($member_id, $start_date, $end_date) {
        global $wpdb;

        $progress_table = $this->db->get_table_name('progress_tracking');
        
        $metrics = $wpdb->get_results($wpdb->prepare("
            SELECT 
                metric_type,
                metric_value,
                recorded_date
            FROM $progress_table
            WHERE member_id = %d
            AND recorded_date BETWEEN %s AND %s
            ORDER BY recorded_date ASC
        ", $member_id, $start_date, $end_date), ARRAY_A);

        $progress_data = array();
        foreach ($metrics as $metric) {
            $progress_data[$metric['metric_type']][] = array(
                'value' => $metric['metric_value'],
                'date' => $metric['recorded_date']
            );
        }

        return array(
            'metrics' => $progress_data,
            'period' => array(
                'start' => $start_date,
                'end' => $end_date
            )
        );
    }

    private function generate_achievement_stats($member_id) {
        global $wpdb;

        $achievements_table = $this->db->get_table_name('member_achievements');
        
        $stats = $wpdb->get_results($wpdb->prepare("
            SELECT 
                achievement_type,
                COUNT(*) as total_earned,
                MAX(earned_date) as last_earned
            FROM $achievements_table
            WHERE member_id = %d
            GROUP BY achievement_type
        ", $member_id), ARRAY_A);

        return array(
            'achievements' => $stats,
            'total_achievements' => array_sum(array_column($stats, 'total_earned')),
            'latest_achievement' => max(array_column($stats, 'last_earned'))
        );
    }

    private function generate_workout_analytics($member_id, $start_date, $end_date) {
        global $wpdb;

        $workouts_table = $this->db->get_table_name('workouts');
        
        $analytics = array();

        // Workout frequency
        $analytics['frequency'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT DATE(workout_date))
            FROM $workouts_table
            WHERE member_id = %d
            AND workout_date BETWEEN %s AND %s
        ", $member_id, $start_date, $end_date));

        // Average duration
        $analytics['avg_duration'] = $wpdb->get_var($wpdb->prepare("
            SELECT AVG(duration)
            FROM $workouts_table
            WHERE member_id = %d
            AND workout_date BETWEEN %s AND %s
        ", $member_id, $start_date, $end_date));

        // Most common workout type
        $analytics['popular_type'] = $wpdb->get_var($wpdb->prepare("
            SELECT workout_type
            FROM $workouts_table
            WHERE member_id = %d
            AND workout_date BETWEEN %s AND %s
            GROUP BY workout_type
            ORDER BY COUNT(*) DESC
            LIMIT 1
        ", $member_id, $start_date, $end_date));

        return $analytics;
    }

    private function save_report($member_id, $report_type, $report_data, $params) {
        global $wpdb;

        $inserted = $wpdb->insert(
            $this->reports_table,
            array(
                'member_id' => $member_id,
                'report_type' => $report_type,
                'report_data' => json_encode($report_data),
                'parameters' => json_encode($params),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );

        if ($inserted) {
            return array(
                'id' => $wpdb->insert_id,
                'type' => $report_type,
                'data' => $report_data,
                'created_at' => current_time('mysql')
            );
        }

        return false;
    }

    public function get_report($report_id) {
        global $wpdb;

        $report = $wpdb->get_row($wpdb->prepare("
            SELECT *
            FROM {$this->reports_table}
            WHERE id = %d
        ", $report_id), ARRAY_A);

        if ($report) {
            $report['report_data'] = json_decode($report['report_data'], true);
            $report['parameters'] = json_decode($report['parameters'], true);
        }

        return $report;
    }

    public function get_member_reports($member_id, $report_type = null) {
        global $wpdb;

        $where = array('member_id = %d');
        $params = array($member_id);

        if ($report_type) {
            $where[] = 'report_type = %s';
            $params[] = $report_type;
        }

        $where_clause = implode(' AND ', $where);

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                FROM {$this->reports_table}
                WHERE $where_clause
                ORDER BY created_at DESC",
                $params
            ),
            ARRAY_A
        );
    }
}