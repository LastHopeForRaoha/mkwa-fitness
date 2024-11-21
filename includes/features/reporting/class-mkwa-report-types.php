// includes/features/reporting/class-mkwa-report-types.php

class MKWA_Report_Types {
    private static $instance = null;
    private $report_types;

    private function __construct() {
        $this->report_types = array(
            'activity_summary' => array(
                'name' => 'Activity Summary',
                'description' => 'Overview of all workout activities',
                'icon' => 'chart-bar',
                'parameters' => array('start_date', 'end_date')
            ),
            'progress_tracking' => array(
                'name' => 'Progress Tracking',
                'description' => 'Track changes in fitness metrics over time',
                'icon' => 'chart-line',
                'parameters' => array('start_date', 'end_date', 'metrics')
            ),
            'achievement_stats' => array(
                'name' => 'Achievement Statistics',
                'description' => 'Summary of earned achievements and badges',
                'icon' => 'trophy',
                'parameters' => array()
            ),
            'workout_analytics' => array(
                'name' => 'Workout Analytics',
                'description' => 'Detailed analysis of workout patterns',
                'icon' => 'chart-pie',
                'parameters' => array('start_date', 'end_date', 'workout_type')
            )
        );

        $this->report_types = apply_filters('mkwa_report_types', $this->report_types);
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_report_types() {
        return $this->report_types;
    }

    public function get_report_type($type) {
        return isset($this->report_types[$type]) ? $this->report_types[$type] : null;
    }

    public function register_report_type($type, $data) {
        if (!isset($this->report_types[$type])) {
            $this->report_types[$type] = wp_parse_args($data, array(
                'name' => '',
                'description' => '',
                'icon' => 'chart-bar',
                'parameters' => array()
            ));
            return true;
        }
        return false;
    }

    public function get_required_parameters($type) {
        $report_type = $this->get_report_type($type);
        return $report_type ? $report_type['parameters'] : array();
    }
}