// includes/features/analytics/class-mkwa-analytics-manager.php

class MKWA_Analytics_Manager {
    private static $instance = null;
    private $db;
    private $events_table;
    private $metrics_table;

    private function __construct() {
        $this->db = MKWA_Database::get_instance();
        $this->events_table = $this->db->get_table_name('analytics_events');
        $this->metrics_table = $this->db->get_table_name('analytics_metrics');
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function track_event($event_type, $member_id, $data = array()) {
        global $wpdb;

        $event_data = wp_parse_args($data, array(
            'event_type' => $event_type,
            'member_id' => $member_id,
            'session_id' => $this->get_session_id(),
            'timestamp' => current_time('mysql'),
            'data' => json_encode($data),
            'ip_address' => $this->get_ip_address(),
            'user_agent' => $this->get_user_agent()
        ));

        $inserted = $wpdb->insert(
            $this->events_table,
            $event_data,
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s')
        );

        if ($inserted) {
            do_action('mkwa_analytics_event_tracked', $wpdb->insert_id, $event_data);
            return $wpdb->insert_id;
        }

        return false;
    }

    public function update_metric($metric_name, $member_id, $value, $context = array()) {
        global $wpdb;

        $metric_data = array(
            'metric_name' => $metric_name,
            'member_id' => $member_id,
            'value' => $value,
            'context' => json_encode($context),
            'timestamp' => current_time('mysql')
        );

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->metrics_table} 
            WHERE metric_name = %s AND member_id = %d",
            $metric_name,
            $member_id
        ));

        if ($existing) {
            $updated = $wpdb->update(
                $this->metrics_table,
                $metric_data,
                array('id' => $existing->id),
                array('%s', '%d', '%f', '%s', '%s'),
                array('%d')
            );
            return $updated !== false;
        }

        $inserted = $wpdb->insert(
            $this->metrics_table,
            $metric_data,
            array('%s', '%d', '%f', '%s', '%s')
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    public function get_member_metrics($member_id, $metric_names = array(), $start_date = null, $end_date = null) {
        global $wpdb;

        $query = "SELECT * FROM {$this->metrics_table} WHERE member_id = %d";
        $params = array($member_id);

        if (!empty($metric_names)) {
            $placeholders = array_fill(0, count($metric_names), '%s');
            $query .= " AND metric_name IN (" . implode(', ', $placeholders) . ")";
            $params = array_merge($params, $metric_names);
        }

        if ($start_date) {
            $query .= " AND timestamp >= %s";
            $params[] = $start_date;
        }

        if ($end_date) {
            $query .= " AND timestamp <= %s";
            $params[] = $end_date;
        }

        $query .= " ORDER BY timestamp DESC";

        return $wpdb->get_results(
            $wpdb->prepare($query, $params),
            ARRAY_A
        );
    }

    public function get_analytics_summary($member_id, $period = 'last_30_days') {
        $end_date = current_time('mysql');
        $start_date = $this->convert_period_to_date($period);

        return array(
            'page_views' => $this->get_page_views($member_id, $start_date, $end_date),
            'events' => $this->get_event_summary($member_id, $start_date, $end_date),
            'metrics' => $this->get_member_metrics($member_id, array(), $start_date, $end_date)
        );
    }

    private function get_page_views($member_id, $start_date, $end_date) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
            FROM {$this->events_table} 
            WHERE member_id = %d 
            AND event_type = 'page_view'
            AND timestamp BETWEEN %s AND %s",
            $member_id,
            $start_date,
            $end_date
        ));
    }

    private function get_event_summary($member_id, $start_date, $end_date) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, COUNT(*) as count 
            FROM {$this->events_table} 
            WHERE member_id = %d 
            AND timestamp BETWEEN %s AND %s 
            GROUP BY event_type",
            $member_id,
            $start_date,
            $end_date
        ), ARRAY_A);
    }

    private function convert_period_to_date($period) {
        switch ($period) {
            case 'last_7_days':
                return date('Y-m-d H:i:s', strtotime('-7 days'));
            case 'last_30_days':
                return date('Y-m-d H:i:s', strtotime('-30 days'));
            case 'last_90_days':
                return date('Y-m-d H:i:s', strtotime('-90 days'));
            default:
                return date('Y-m-d H:i:s', strtotime('-30 days'));
        }
    }

    private function get_session_id() {
        if (!session_id()) {
            session_start();
        }
        return session_id();
    }

    private function get_ip_address() {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }

    private function get_user_agent() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    }
}