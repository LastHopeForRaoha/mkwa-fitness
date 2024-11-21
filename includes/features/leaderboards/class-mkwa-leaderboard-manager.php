// includes/features/leaderboards/class-mkwa-leaderboard-manager.php

class MKWA_Leaderboard_Manager {
    private static $instance = null;
    private $db;
    private $leaderboards_table;
    private $leaderboard_entries_table;

    private function __construct() {
        $this->db = MKWA_Database::get_instance();
        $this->leaderboards_table = $this->db->get_table_name('leaderboards');
        $this->leaderboard_entries_table = $this->db->get_table_name('leaderboard_entries');
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function create_leaderboard($data) {
        global $wpdb;

        $defaults = array(
            'name' => '',
            'description' => '',
            'type' => 'points',
            'period' => 'weekly',
            'start_date' => current_time('mysql'),
            'end_date' => null,
            'status' => 'active',
            'rules' => array(),
            'metadata' => array()
        );

        $leaderboard_data = wp_parse_args($data, $defaults);

        // Validate required fields
        if (empty($leaderboard_data['name']) || empty($leaderboard_data['type'])) {
            return new WP_Error('missing_required', 'Name and type are required.');
        }

        // Convert arrays to JSON
        $leaderboard_data['rules'] = json_encode($leaderboard_data['rules']);
        $leaderboard_data['metadata'] = json_encode($leaderboard_data['metadata']);

        $inserted = $wpdb->insert(
            $this->leaderboards_table,
            $leaderboard_data,
            array(
                '%s', // name
                '%s', // description
                '%s', // type
                '%s', // period
                '%s', // start_date
                '%s', // end_date
                '%s', // status
                '%s', // rules
                '%s'  // metadata
            )
        );

        if ($inserted) {
            $leaderboard_id = $wpdb->insert_id;
            do_action('mkwa_leaderboard_created', $leaderboard_id, $leaderboard_data);
            return $leaderboard_id;
        }

        return new WP_Error('insert_failed', 'Failed to create leaderboard.');
    }

    public function update_entry($leaderboard_id, $member_id, $score, $metadata = array()) {
        global $wpdb;

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Check if entry exists
            $existing_entry = $wpdb->get_row($wpdb->prepare("
                SELECT id, score
                FROM {$this->leaderboard_entries_table}
                WHERE leaderboard_id = %d AND member_id = %d
            ", $leaderboard_id, $member_id));

            if ($existing_entry) {
                // Update existing entry
                $updated = $wpdb->update(
                    $this->leaderboard_entries_table,
                    array(
                        'score' => $score,
                        'metadata' => json_encode($metadata),
                        'updated_at' => current_time('mysql')
                    ),
                    array('id' => $existing_entry->id),
                    array('%d', '%s', '%s'),
                    array('%d')
                );

                if ($updated === false) {
                    throw new Exception('Failed to update leaderboard entry.');
                }
            } else {
                // Create new entry
                $inserted = $wpdb->insert(
                    $this->leaderboard_entries_table,
                    array(
                        'leaderboard_id' => $leaderboard_id,
                        'member_id' => $member_id,
                        'score' => $score,
                        'metadata' => json_encode($metadata),
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ),
                    array('%d', '%d', '%d', '%s', '%s', '%s')
                );

                if (!$inserted) {
                    throw new Exception('Failed to create leaderboard entry.');
                }
            }

            // Update member ranks
            $this->update_ranks($leaderboard_id);

            $wpdb->query('COMMIT');
            
            do_action('mkwa_leaderboard_entry_updated', $leaderboard_id, $member_id, $score);
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('update_failed', $e->getMessage());
        }
    }

    public function get_leaderboard($leaderboard_id, $limit = 10, $offset = 0) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT e.*, m.username, m.first_name, m.last_name,
                   ROW_NUMBER() OVER (ORDER BY e.score DESC) as rank
            FROM {$this->leaderboard_entries_table} e
            JOIN {$this->db->get_table_name('members')} m ON e.member_id = m.id
            WHERE e.leaderboard_id = %d
            ORDER BY e.score DESC
            LIMIT %d OFFSET %d
        ", $leaderboard_id, $limit, $offset), ARRAY_A);
    }

    public function get_member_rank($leaderboard_id, $member_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare("
            SELECT e.*, 
                   (SELECT COUNT(*) + 1 
                    FROM {$this->leaderboard_entries_table} 
                    WHERE leaderboard_id = %d AND score > e.score) as rank
            FROM {$this->leaderboard_entries_table} e
            WHERE e.leaderboard_id = %d AND e.member_id = %d
        ", $leaderboard_id, $leaderboard_id, $member_id), ARRAY_A);
    }

    public function get_active_leaderboards() {
        global $wpdb;

        return $wpdb->get_results("
            SELECT *
            FROM {$this->leaderboards_table}
            WHERE status = 'active'
            AND (end_date IS NULL OR end_date > NOW())
            ORDER BY start_date DESC
        ", ARRAY_A);
    }

    private function update_ranks($leaderboard_id) {
        global $wpdb;

        // Update rank numbers based on scores
        $wpdb->query($wpdb->prepare("
            SET @rank = 0;
            UPDATE {$this->leaderboard_entries_table}
            SET rank = @rank := @rank + 1
            WHERE leaderboard_id = %d
            ORDER BY score DESC
        ", $leaderboard_id));
    }

    public function reset_leaderboard($leaderboard_id) {
        global $wpdb;

        $deleted = $wpdb->delete(
            $this->leaderboard_entries_table,
            array('leaderboard_id' => $leaderboard_id),
            array('%d')
        );

        if ($deleted !== false) {
            do_action('mkwa_leaderboard_reset', $leaderboard_id);
            return true;
        }

        return false;
    }

    public function get_member_leaderboards($member_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT l.*, e.score, e.rank
            FROM {$this->leaderboards_table} l
            JOIN {$this->leaderboard_entries_table} e ON l.id = e.leaderboard_id
            WHERE e.member_id = %d
            AND l.status = 'active'
            ORDER BY l.start_date DESC
        ", $member_id), ARRAY_A);
    }
}