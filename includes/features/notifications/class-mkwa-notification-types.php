// includes/features/notifications/class-mkwa-notification-types.php

class MKWA_Notification_Types {
    private static $instance = null;
    private $notification_types;

    private function __construct() {
        $this->notification_types = array(
            'achievement' => array(
                'name' => 'Achievement',
                'description' => 'Achievement unlocked notifications',
                'icon' => 'trophy',
                'priority' => 'normal',
                'lifetime' => '30 days'
            ),
            'points' => array(
                'name' => 'Points',
                'description' => 'Points earned or updated',
                'icon' => 'star',
                'priority' => 'low',
                'lifetime' => '7 days'
            ),
            'streak' => array(
                'name' => 'Streak',
                'description' => 'Workout streak updates',
                'icon' => 'fire',
                'priority' => 'normal',
                'lifetime' => '2 days'
            ),
            'community_goal' => array(
                'name' => 'Community Goal',
                'description' => 'Community goal updates',
                'icon' => 'users',
                'priority' => 'normal',
                'lifetime' => '14 days'
            ),
            'reminder' => array(
                'name' => 'Reminder',
                'description' => 'Workout and activity reminders',
                'icon' => 'bell',
                'priority' => 'high',
                'lifetime' => '1 day'
            ),
            'system' => array(
                'name' => 'System',
                'description' => 'System notifications',
                'icon' => 'info-circle',
                'priority' => 'high',
                'lifetime' => '30 days'
            )
        );

        $this->notification_types = apply_filters('mkwa_notification_types', $this->notification_types);
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_notification_types() {
        return $this->notification_types;
    }

    public function get_notification_type($type) {
        return isset($this->notification_types[$type]) ? $this->notification_types[$type] : null;
    }

    public function type_exists($type) {
        return isset($this->notification_types[$type]);
    }

    public function register_notification_type($type, $data) {
        if (!isset($this->notification_types[$type])) {
            $this->notification_types[$type] = wp_parse_args($data, array(
                'name' => '',
                'description' => '',
                'icon' => 'bell',
                'priority' => 'normal',
                'lifetime' => '7 days'
            ));
            return true;
        }
        return false;
    }

    public function get_priority_levels() {
        return array(
            'low' => 'Low Priority',
            'normal' => 'Normal Priority',
            'high' => 'High Priority'
        );
    }

    public function get_notification_lifetime($type) {
        $notification_type = $this->get_notification_type($type);
        return $notification_type ? $notification_type['lifetime'] : '7 days';
    }
}