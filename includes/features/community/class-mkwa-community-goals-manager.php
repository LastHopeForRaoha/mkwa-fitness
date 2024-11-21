// includes/features/community/class-mkwa-community-goals-manager.php

class MKWA_Community_Goals_Manager {
    private static $instance = null;
    private $db;
    private $goals_table;
    private $participants_table;
    private $points_manager;

    private function __construct() {
        $this->db = MKWA_Database::get_instance();
        $this->goals_table = $this->db->get_table_name('community_goals');
        $this->participants_table = $this->db->get_table_name('goal_participants');
        $this->points_manager = MKWA_Points_Manager::get_instance();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function create_goal($data) {
        global $wpdb;

        $defaults = array(
            'title' => '',
            'description' => '',
            'goal_type' => 'collective',
            'target_value' => 0,
            'current_value' => 0,
            'start_date' => current_time('mysql'),
            'end_date' => null,
            'reward_points' => 0,
            'status' => 'active',
            'requirements' => array(),
            'metadata' => array()
        );

        $goal_data = wp_parse_args($data, $defaults);

        // Validate required fields
        if (empty($goal_data['title']) || empty($goal_data['target_value'])) {
            return new WP_Error('missing_required', 'Title and target value are required.');
        }

        // Convert arrays to JSON
        $goal_data['requirements'] = json_encode($goal_data['requirements']);
        $goal_data['metadata'] = json_encode($goal_data['metadata']);

        $inserted = $wpdb->insert(
            $this->goals_table,
            $goal_data,
            array(
                '%s', // title
                '%s', // description
                '%s', // goal_type
                '%d', // target_value
                '%d', // current_value
                '%s', // start_date
                '%s', // end_date
                '%d', // reward_points
                '%s', // status
                '%s', // requirements
                '%s'  // metadata
            )
        );

        if ($inserted) {
            do_action('mkwa_community_goal_created', $wpdb->insert_id, $goal_data);
            return $wpdb->insert_id;
        }

        return new WP_Error('insert_failed', 'Failed to create community goal.');
    }

    public function join_goal($member_id, $goal_id) {
        global $wpdb;

        // Check if goal exists and is active
        $goal = $this->get_goal($goal_id);
        if (!$goal || $goal['status'] !== 'active') {
            return new WP_Error('invalid_goal', 'Goal is not available for joining.');
        }

        // Check if already participating
        if ($this->is_participant($member_id, $goal_id)) {
            return new WP_Error('already_participating', 'Member is already participating in this goal.');
        }

        $inserted = $wpdb->insert(
            $this->participants_table,
            array(
                'member_id' => $member_id,
                'goal_id' => $goal_id,
                'join_date' => current_time('mysql'),
                'contribution' => 0
            ),
            array('%d', '%d', '%s', '%d')
        );

        if ($inserted) {
            do_action('mkwa_goal_joined', $member_id, $goal_id);
            return true;
        }

        return new WP_Error('join_failed', 'Failed to join community goal.');
    }

    public function update_progress($goal_id, $member_id, $contribution) {
        global $wpdb;

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Update participant contribution
            $updated = $wpdb->query($wpdb->prepare("
                UPDATE {$this->participants_table}
                SET contribution = contribution + %d
                WHERE goal_id = %d AND member_id = %d
            ", $contribution, $goal_id, $member_id));

            if ($updated === false) {
                throw new Exception('Failed to update participant contribution.');
            }

            // Update goal progress
            $updated = $wpdb->query($wpdb->prepare("
                UPDATE {$this->goals_table}
                SET current_value = current_value + %d
                WHERE id = %d
            ", $contribution, $goal_id));

            if ($updated === false) {
                throw new Exception('Failed to update goal progress.');
            }

            // Check if goal is completed
            $goal = $this->get_goal($goal_id);
            if ($goal['current_value'] >= $goal['target_value'] && $goal['status'] === 'active') {
                $this->complete_goal($goal_id);
            }

            $wpdb->query('COMMIT');
            
            do_action('mkwa_goal_progress_updated', $goal_id, $member_id, $contribution);
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('update_failed', $e->getMessage());
        }
    }

    public function complete_goal($goal_id) {
        global $wpdb;

        $goal = $this->get_goal($goal_id);
        if (!$goal || $goal['status'] !== 'active') {
            return new WP_Error('invalid_goal', 'Invalid or inactive goal.');
        }

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Update goal status
            $updated = $wpdb->update(
                $this->goals_table,
                array(
                    'status' => 'completed',
                    'completion_date' => current_time('mysql')
                ),
                array('id' => $goal_id),
                array('%s', '%s'),
                array('%d')
            );

            if (!$updated) {
                throw new Exception('Failed to update goal status.');
            }

            // Award points to participants
            $participants = $this->get_goal_participants($goal_id);
            foreach ($participants as $participant) {
                $points_result = $this->points_manager->award_points(
                    $participant['member_id'],
                    'community_goal',
                    array(
                        'goal_id' => $goal_id,
                        'contribution' => $participant['contribution']
                    )
                );

                if (is_wp_error($points_result)) {
                    throw new Exception($points_result->get_error_message());
                }
            }

            $wpdb->query('COMMIT');
            
            do_action('mkwa_goal_completed', $goal_id);
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('completion_failed', $e->getMessage());
        }
    }

    public function get_goal($goal_id) {
        global $wpdb;

        $goal = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->goals_table} WHERE id = %d",
                $goal_id
            ),
            ARRAY_A
        );

        if ($goal) {
            $goal['requirements'] = json_decode($goal['requirements'], true);
            $goal['metadata'] = json_decode($goal['metadata'], true);
        }

        return $goal;
    }

    public function get_active_goals() {
        global $wpdb;

        return $wpdb->get_results("
            SELECT * FROM {$this->goals_table}
            WHERE status = 'active'
            ORDER BY start_date DESC
        ", ARRAY_A);
    }

    public function get_goal_participants($goal_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT p.*, m.username, m.first_name, m.last_name
            FROM {$this->participants_table} p
            JOIN {$this->db->get_table_name('members')} m ON p.member_id = m.id
            WHERE p.goal_id = %d
            ORDER BY p.contribution DESC
        ", $goal_id), ARRAY_A);
    }

    public function get_member_goals($member_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT g.*, p.contribution, p.join_date
            FROM {$this->goals_table} g
            JOIN {$this->participants_table} p ON g.id = p.goal_id
            WHERE p.member_id = %d
            ORDER BY g.start_date DESC
        ", $member_id), ARRAY_A);
    }

    private function is_participant($member_id, $goal_id) {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$this->participants_table}
            WHERE member_id = %d AND goal_id = %d
        ", $member_id, $goal_id));
    }
}