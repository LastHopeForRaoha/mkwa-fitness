// includes/features/notifications/class-mkwa-notification-manager.php

class MKWA_Notification_Manager {
    private static $instance = null;
    private $db;
    private $notifications_table;
    private $notification_types;

    private function __construct() {
        $this->db = MKWA_Database::get_instance();
        $this->notifications_table = $this->db->get_table_name('notifications');
        $this->notification_types = MKWA_Notification_Types::get_instance();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function create_notification($data) {
        global $wpdb;

        $defaults = array(
            'member_id' => 0,
            'type' => '',
            'title' => '',
            'message' => '',
            'link' => '',
            'status' => 'unread',
            'priority' => 'normal',
            'expiry_date' => null,
            'metadata' => array()
        );

        $notification_data = wp_parse_args($data, $defaults);

        // Validate required fields
        if (empty($notification_data['member_id']) || empty($notification_data['type'])) {
            return new WP_Error('missing_required', 'Member ID and notification type are required.');
        }

        // Validate notification type
        if (!$this->notification_types->type_exists($notification_data['type'])) {
            return new WP_Error('invalid_type', 'Invalid notification type.');
        }

        // Convert metadata to JSON
        $notification_data['metadata'] = json_encode($notification_data['metadata']);

        $inserted = $wpdb->insert(
            $this->notifications_table,
            array(
                'member_id' => $notification_data['member_id'],
                'type' => $notification_data['type'],
                'title' => $notification_data['title'],
                'message' => $notification_data['message'],
                'link' => $notification_data['link'],
                'status' => $notification_data['status'],
                'priority' => $notification_data['priority'],
                'created_at' => current_time('mysql'),
                'expiry_date' => $notification_data['expiry_date'],
                'metadata' => $notification_data['metadata']
            ),
            array(
                '%d', // member_id
                '%s', // type
                '%s', // title
                '%s', // message
                '%s', // link
                '%s', // status
                '%s', // priority
                '%s', // created_at
                '%s', // expiry_date
                '%s'  // metadata
            )
        );

        if ($inserted) {
            $notification_id = $wpdb->insert_id;
            do_action('mkwa_notification_created', $notification_id, $notification_data);
            
            // Send real-time notification if enabled
            $this->send_realtime_notification($notification_id);
            
            return $notification_id;
        }

        return new WP_Error('insert_failed', 'Failed to create notification.');
    }

    public function get_member_notifications($member_id, $status = null, $limit = 10, $offset = 0) {
        global $wpdb;

        $where = array('member_id = %d');
        $params = array($member_id);

        if ($status) {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        // Exclude expired notifications
        $where[] = '(expiry_date IS NULL OR expiry_date > %s)';
        $params[] = current_time('mysql');

        $where_clause = implode(' AND ', $where);

        $notifications = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                FROM {$this->notifications_table}
                WHERE {$where_clause}
                ORDER BY created_at DESC
                LIMIT %d OFFSET %d",
                array_merge($params, array($limit, $offset))
            ),
            ARRAY_A
        );

        foreach ($notifications as &$notification) {
            $notification['metadata'] = json_decode($notification['metadata'], true);
        }

        return $notifications;
    }

    public function mark_as_read($notification_id, $member_id) {
        global $wpdb;

        $updated = $wpdb->update(
            $this->notifications_table,
            array('status' => 'read', 'read_at' => current_time('mysql')),
            array('id' => $notification_id, 'member_id' => $member_id),
            array('%s', '%s'),
            array('%d', '%d')
        );

        if ($updated) {
            do_action('mkwa_notification_read', $notification_id, $member_id);
            return true;
        }

        return false;
    }

    public function mark_all_as_read($member_id) {
        global $wpdb;

        $updated = $wpdb->update(
            $this->notifications_table,
            array('status' => 'read', 'read_at' => current_time('mysql')),
            array('member_id' => $member_id, 'status' => 'unread'),
            array('%s', '%s'),
            array('%d', '%s')
        );

        if ($updated !== false) {
            do_action('mkwa_all_notifications_read', $member_id);
            return true;
        }

        return false;
    }

    public function delete_notification($notification_id, $member_id) {
        global $wpdb;

        $deleted = $wpdb->delete(
            $this->notifications_table,
            array('id' => $notification_id, 'member_id' => $member_id),
            array('%d', '%d')
        );

        if ($deleted) {
            do_action('mkwa_notification_deleted', $notification_id, $member_id);
            return true;
        }

        return false;
    }

    public function get_unread_count($member_id) {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$this->notifications_table}
            WHERE member_id = %d
            AND status = 'unread'
            AND (expiry_date IS NULL OR expiry_date > %s)
        ", $member_id, current_time('mysql')));
    }

    private function send_realtime_notification($notification_id) {
        // Implementation depends on the real-time notification system used
        // (e.g., WebSockets, Server-Sent Events, or Push Notifications)
        do_action('mkwa_send_realtime_notification', $notification_id);
    }

    public function cleanup_expired_notifications() {
        global $wpdb;

        $deleted = $wpdb->query($wpdb->prepare("
            DELETE FROM {$this->notifications_table}
            WHERE expiry_date IS NOT NULL
            AND expiry_date <= %s
        ", current_time('mysql')));

        if ($deleted !== false) {
            do_action('mkwa_expired_notifications_cleaned', $deleted);
            return $deleted;
        }

        return false;
    }

    public function get_notification_preferences($member_id) {
        return get_user_meta($member_id, 'mkwa_notification_preferences', true) ?: array();
    }

    public function update_notification_preferences($member_id, $preferences) {
        return update_user_meta($member_id, 'mkwa_notification_preferences', $preferences);
    }
}