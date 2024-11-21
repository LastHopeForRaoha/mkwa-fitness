// includes/features/users/class-mkwa-user-manager.php

class MKWA_User_Manager {
    private static $instance = null;
    private $db;
    private $table_name;

    private function __construct() {
        $this->db = MKWA_Database::get_instance();
        $this->table_name = $this->db->get_table_name('members');
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function create_member($data) {
        global $wpdb;

        $defaults = array(
            'username'        => '',
            'email'          => '',
            'password_hash'  => '',
            'first_name'     => '',
            'last_name'      => '',
            'membership_type'=> 'standard',
            'is_aboriginal'  => false,
            'status'         => 'active',
            'privacy_settings' => json_encode(array(
                'show_profile' => true,
                'show_achievements' => true,
                'show_points' => true,
                'show_activities' => true
            )),
            'card_id'        => null
        );

        $member_data = wp_parse_args($data, $defaults);
        
        // Sanitize data
        $member_data = $this->sanitize_member_data($member_data);

        // Validate required fields
        if (empty($member_data['username']) || empty($member_data['email'])) {
            return new WP_Error('missing_required', 'Username and email are required fields.');
        }

        // Check if username or email already exists
        if ($this->user_exists($member_data['username'], $member_data['email'])) {
            return new WP_Error('user_exists', 'Username or email already exists.');
        }

        $inserted = $wpdb->insert(
            $this->table_name,
            $member_data,
            array(
                '%s', // username
                '%s', // email
                '%s', // password_hash
                '%s', // first_name
                '%s', // last_name
                '%s', // membership_type
                '%d', // is_aboriginal
                '%s', // status
                '%s', // privacy_settings
                '%s'  // card_id
            )
        );

        if ($inserted) {
            $member_id = $wpdb->insert_id;
            
            // Initialize related records
            $this->initialize_member_records($member_id);
            
            return $member_id;
        }

        return new WP_Error('insert_failed', 'Failed to create member.');
    }

    private function initialize_member_records($member_id) {
        global $wpdb;
        
        // Initialize workout streaks
        $wpdb->insert(
            $this->db->get_table_name('workout_streaks'),
            array('member_id' => $member_id),
            array('%d')
        );

        // Fire action for other modules to initialize their data
        do_action('mkwa_member_initialized', $member_id);
    }

    public function update_member($member_id, $data) {
        global $wpdb;

        $data = $this->sanitize_member_data($data);
        
        // Remove fields that shouldn't be updated
        unset($data['username']);
        unset($data['email']);
        unset($data['created_at']);

        $updated = $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $member_id),
            array_fill(0, count($data), '%s'),
            array('%d')
        );

        return $updated !== false;
    }

    public function get_member($member_id) {
        global $wpdb;

        $member = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $member_id
            ),
            ARRAY_A
        );

        if ($member) {
            $member['privacy_settings'] = json_decode($member['privacy_settings'], true);
        }

        return $member;
    }

    public function get_member_by_card($card_id) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE card_id = %s",
                $card_id
            ),
            ARRAY_A
        );
    }

    public function delete_member($member_id) {
        global $wpdb;

        // Begin transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Delete related records first
            $tables = array(
                'points',
                'activities',
                'member_achievements',
                'workout_streaks',
                'goal_participants'
            );

            foreach ($tables as $table) {
                $wpdb->delete(
                    $this->db->get_table_name($table),
                    array('member_id' => $member_id),
                    array('%d')
                );
            }

            // Delete member record
            $deleted = $wpdb->delete(
                $this->table_name,
                array('id' => $member_id),
                array('%d')
            );

            if ($deleted) {
                $wpdb->query('COMMIT');
                return true;
            } else {
                throw new Exception('Failed to delete member.');
            }
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return false;
        }
    }

    private function user_exists($username, $email) {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} 
                WHERE username = %s OR email = %s",
                $username,
                $email
            )
        );

        return $count > 0;
    }

    private function sanitize_member_data($data) {
        $sanitized = array();

        if (isset($data['username'])) {
            $sanitized['username'] = sanitize_user($data['username']);
        }

        if (isset($data['email'])) {
            $sanitized['email'] = sanitize_email($data['email']);
        }

        if (isset($data['first_name'])) {
            $sanitized['first_name'] = sanitize_text_field($data['first_name']);
        }

        if (isset($data['last_name'])) {
            $sanitized['last_name'] = sanitize_text_field($data['last_name']);
        }

        if (isset($data['membership_type'])) {
            $sanitized['membership_type'] = in_array($data['membership_type'], 
                array('standard', 'premium', 'student', 'family_2adults', 'family_2adults_2children'))
                ? $data['membership_type']
                : 'standard';
        }

        if (isset($data['is_aboriginal'])) {
            $sanitized['is_aboriginal'] = (bool) $data['is_aboriginal'];
        }

        if (isset($data['status'])) {
            $sanitized['status'] = in_array($data['status'], array('active', 'inactive', 'suspended'))
                ? $data['status']
                : 'active';
        }

        if (isset($data['privacy_settings']) && is_array($data['privacy_settings'])) {
            $sanitized['privacy_settings'] = json_encode($data['privacy_settings']);
        }

        if (isset($data['card_id'])) {
            $sanitized['card_id'] = sanitize_text_field($data['card_id']);
        }

        return $sanitized;
    }
}