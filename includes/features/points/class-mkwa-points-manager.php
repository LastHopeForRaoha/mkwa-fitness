// includes/features/points/class-mkwa-points-manager.php

class MKWA_Points_Manager {
    private static $instance = null;
    private $db;
    private $points_table;
    private $calculator;

    private function __construct() {
        $this->db = MKWA_Database::get_instance();
        $this->points_table = $this->db->get_table_name('points');
        $this->calculator = MKWA_Points_Calculator::get_instance();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function award_points($member_id, $activity_type, $data = array()) {
        global $wpdb;

        // Calculate points for the activity
        $points = $this->calculator->calculate_points($activity_type, $data);
        
        if ($points <= 0) {
            return new WP_Error('invalid_points', 'No points to award for this activity.');
        }

        // Prepare transaction data
        $transaction = array(
            'member_id' => $member_id,
            'points' => $points,
            'transaction_type' => 'earned',
            'activity_type' => $activity_type,
            'description' => $this->get_activity_description($activity_type, $data),
            'created_at' => current_time('mysql')
        );

        // Insert transaction
        $inserted = $wpdb->insert(
            $this->points_table,
            $transaction,
            array(
                '%d', // member_id
                '%d', // points
                '%s', // transaction_type
                '%s', // activity_type
                '%s', // description
                '%s'  // created_at
            )
        );

        if ($inserted) {
            // Trigger points awarded action
            do_action('mkwa_points_awarded', array(
                'member_id' => $member_id,
                'points' => $points,
                'activity_type' => $activity_type,
                'transaction_id' => $wpdb->insert_id
            ));

            return $wpdb->insert_id;
        }

        return new WP_Error('insert_failed', 'Failed to record points transaction.');
    }

    public function redeem_points($member_id, $points, $reason) {
        global $wpdb;

        // Check if member has enough points
        $available_points = $this->calculator->get_total_points($member_id);
        
        if ($available_points < $points) {
            return new WP_Error(
                'insufficient_points', 
                sprintf('Insufficient points. Available: %d, Required: %d', $available_points, $points)
            );
        }

        // Record redemption transaction
        $transaction = array(
            'member_id' => $member_id,
            'points' => $points,
            'transaction_type' => 'redeemed',
            'activity_type' => 'redemption',
            'description' => sanitize_text_field($reason),
            'created_at' => current_time('mysql')
        );

        $inserted = $wpdb->insert(
            $this->points_table,
            $transaction,
            array(
                '%d', // member_id
                '%d', // points
                '%s', // transaction_type
                '%s', // activity_type
                '%s', // description
                '%s'  // created_at
            )
        );

        if ($inserted) {
            // Trigger points redeemed action
            do_action('mkwa_points_redeemed', array(
                'member_id' => $member_id,
                'points' => $points,
                'reason' => $reason,
                'transaction_id' => $wpdb->insert_id
            ));

            return $wpdb->insert_id;
        }

        return new WP_Error('redemption_failed', 'Failed to record redemption transaction.');
    }

    public function adjust_points($member_id, $points, $reason, $admin_id = null) {
        global $wpdb;

        // Prepare adjustment transaction
        $transaction = array(
            'member_id' => $member_id,
            'points' => abs($points), // Store absolute value
            'transaction_type' => 'adjusted',
            'activity_type' => 'manual_adjustment',
            'description' => sprintf(
                'Manual adjustment by admin (%s): %s',
                $admin_id ? get_userdata($admin_id)->user_login : 'system',
                sanitize_text_field($reason)
            ),
            'created_at' => current_time('mysql')
        );

        $inserted = $wpdb->insert(
            $this->points_table,
            $transaction,
            array(
                '%d', // member_id
                '%d', // points
                '%s', // transaction_type
                '%s', // activity_type
                '%s', // description
                '%s'  // created_at
            )
        );

        if ($inserted) {
            // Trigger points adjusted action
            do_action('mkwa_points_adjusted', array(
                'member_id' => $member_id,
                'points' => $points,
                'reason' => $reason,
                'admin_id' => $admin_id,
                'transaction_id' => $wpdb->insert_id
            ));

            return $wpdb->insert_id;
        }

        return new WP_Error('adjustment_failed', 'Failed to record points adjustment.');
    }

    public function expire_points($member_id, $points_to_expire, $reason = 'Points expiration') {
        global $wpdb;

        $transaction = array(
            'member_id' => $member_id,
            'points' => $points_to_expire,
            'transaction_type' => 'expired',
            'activity_type' => 'expiration',
            'description' => sanitize_text_field($reason),
            'created_at' => current_time('mysql')
        );

        $inserted = $wpdb->insert(
            $this->points_table,
            $transaction,
            array(
                '%d', // member_id
                '%d', // points
                '%s', // transaction_type
                '%s', // activity_type
                '%s', // description
                '%s'  // created_at
            )
        );

        if ($inserted) {
            // Trigger points expired action
            do_action('mkwa_points_expired', array(
                'member_id' => $member_id,
                'points' => $points_to_expire,
                'reason' => $reason,
                'transaction_id' => $wpdb->insert_id
            ));

            return $wpdb->insert_id;
        }

        return new WP_Error('expiration_failed', 'Failed to record points expiration.');
    }

    public function get_points_balance($member_id) {
        return $this->calculator->get_total_points($member_id);
    }

    public function get_transaction_history($member_id, $args = array()) {
        global $wpdb;

        $defaults = array(
            'limit' => 10,
            'offset' => 0,
            'transaction_type' => '', // empty for all types
            'start_date' => '',
            'end_date' => '',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);
        $where = array("member_id = %d");
        $where_values = array($member_id);

        if (!empty($args['transaction_type'])) {
            $where[] = "transaction_type = %s";
            $where_values[] = $args['transaction_type'];
        }

        if (!empty($args['start_date'])) {
            $where[] = "created_at >= %s";
            $where_values[] = $args['start_date'];
        }

        if (!empty($args['end_date'])) {
            $where[] = "created_at <= %s";
            $where_values[] = $args['end_date'];
        }

        $where_clause = implode(' AND ', $where);
        $order_clause = $args['order'] === 'ASC' ? 'ASC' : 'DESC';

        $query = $wpdb->prepare(
            "SELECT *
            FROM {$this->points_table}
            WHERE {$where_clause}
            ORDER BY created_at {$order_clause}
            LIMIT %d OFFSET %d",
            array_merge($where_values, array($args['limit'], $args['offset']))
        );

        return $wpdb->get_results($query, ARRAY_A);
    }

    private function get_activity_description($activity_type, $data) {
        $descriptions = array(
            'gym_visit' => 'Gym visit',
            'class_attendance' => isset($data['class_name']) ? 
                sprintf('Attended %s class', $data['class_name']) : 'Class attendance',
            'referral' => 'New member referral',
            'streak_bonus' => sprintf('Streak bonus for %d days', 
                isset($data['streak_days']) ? $data['streak_days'] : 0),
            'achievement' => isset($data['achievement_name']) ? 
                sprintf('Earned achievement: %s', $data['achievement_name']) : 'Achievement earned',
            'community_goal' => isset($data['goal_name']) ? 
                sprintf('Completed community goal: %s', $data['goal_name']) : 'Community goal completed'
        );

        return isset($descriptions[$activity_type]) ? 
            $descriptions[$activity_type] : 
            'Points transaction';
    }

    public function get_leaderboard($period = 'all_time', $limit = 10) {
        global $wpdb;
        $members_table = $this->db->get_table_name('members');

        $where_clause = '';
        switch ($period) {
            case 'daily':
                $where_clause = "AND DATE(p.created_at) = CURDATE()";
                break;
            case 'weekly':
                $where_clause = "AND YEARWEEK(p.created_at) = YEARWEEK(CURDATE())";
                break;
            case 'monthly':
                $where_clause = "AND YEAR(p.created_at) = YEAR(CURDATE()) 
                                AND MONTH(p.created_at) = MONTH(CURDATE())";
                break;
        }

        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                m.id,
                m.username,
                m.first_name,
                m.last_name,
                COALESCE(SUM(
                    CASE 
                        WHEN p.transaction_type = 'earned' THEN p.points
                        WHEN p.transaction_type IN ('redeemed', 'expired') THEN -p.points
                        ELSE p.points
                    END
                ), 0) as total_points
            FROM {$members_table} m
            LEFT JOIN {$this->points_table} p ON m.id = p.member_id
            WHERE m.status = 'active' {$where_clause}
            GROUP BY m.id
            HAVING total_points > 0
            ORDER BY total_points DESC
            LIMIT %d
        ", $limit), ARRAY_A);
    }
}