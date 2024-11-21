// includes/class-mkwa-member-manager.php
<?php
/**
 * Member management system for MKWA Fitness plugin
 *
 * @package    MKWA_Fitness
 * @subpackage MKWA_Fitness/includes
 * @since      1.0.0
 */

class MKWA_Member_Manager {
    private static $instance = null;
    private $db;
    private $table_name;
    private $logger;

    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table_name = $wpdb->prefix . 'mkwa_members';
        $this->logger = MKWA_Logger::get_instance();
    }

    /**
     * Get instance of the member manager
     *
     * @return MKWA_Member_Manager
     */
    public static function get_instance(): MKWA_Member_Manager {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Create a new member
     *
     * @param array $data Member data
     * @return int|WP_Error Member ID on success, WP_Error on failure
     */
    public function create_member(array $data) {
        try {
            // Validate required fields
            $required_fields = ['username', 'email', 'first_name', 'last_name'];
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    throw new Exception(
                        sprintf(__('Missing required field: %s', 'mkwa'), $field)
                    );
                }
            }

            // Validate email
            if (!is_email($data['email'])) {
                throw new Exception(__('Invalid email address', 'mkwa'));
            }

            // Check if username or email exists
            if ($this->user_exists($data['username'], $data['email'])) {
                throw new Exception(__('Username or email already exists', 'mkwa'));
            }

            // Sanitize and prepare data
            $member_data = $this->sanitize_member_data($data);

            // Begin transaction
            $this->db->query('START TRANSACTION');

            // Insert member
            $inserted = $this->db->insert(
                $this->table_name,
                $member_data,
                $this->get_data_formats($member_data)
            );

            if (!$inserted) {
                throw new Exception(__('Failed to create member', 'mkwa'));
            }

            $member_id = $this->db->insert_id;

            // Initialize related records
            $this->initialize_member_records($member_id);

            // Commit transaction
            $this->db->query('COMMIT');

            // Log success
            $this->logger->info(
                sprintf('Created new member with ID: %d', $member_id),
                ['data' => $member_data]
            );

            return $member_id;

        } catch (Exception $e) {
            // Rollback transaction
            $this->db->query('ROLLBACK');

            // Log error
            $this->logger->error(
                'Failed to create member: ' . $e->getMessage(),
                ['data' => $data]
            );

            return new WP_Error('create_member_failed', $e->getMessage());
        }
    }

    /**
     * Get member by ID
     *
     * @param int $member_id Member ID
     * @return array|null|WP_Error Member data or null if not found
     */
    public function get_member(int $member_id) {
        try {
            $member = $this->db->get_row(
                $this->db->prepare(
                    "SELECT * FROM {$this->table_name} WHERE id = %d",
                    $member_id
                ),
                ARRAY_A
            );

            if ($member) {
                $member['privacy_settings'] = json_decode($member['privacy_settings'], true);
                return $member;
            }

            return null;

        } catch (Exception $e) {
            $this->logger->error(
                sprintf('Failed to get member %d: %s', $member_id, $e->getMessage())
            );
            return new WP_Error('get_member_failed', $e->getMessage());
        }
    }

    /**
     * Update member
     *
     * @param int   $member_id Member ID
     * @param array $data      Updated member data
     * @return bool|WP_Error
     */
    public function update_member(int $member_id, array $data) {
        try {
            // Validate member exists
            if (!$this->get_member($member_id)) {
                throw new Exception(__('Member not found', 'mkwa'));
            }

            // Sanitize and prepare data
            $update_data = $this->sanitize_member_data($data);

            // Remove protected fields
            unset($update_data['username']);
            unset($update_data['email']);
            unset($update_data['created_at']);

            // Update member
            $updated = $this->db->update(
                $this->table_name,
                $update_data,
                ['id' => $member_id],
                $this->get_data_formats($update_data),
                ['%d']
            );

            if ($updated === false) {
                throw new Exception(__('Failed to update member', 'mkwa'));
            }

            $this->logger->info(
                sprintf('Updated member %d', $member_id),
                ['data' => $update_data]
            );

            return true;

        } catch (Exception $e) {
            $this->logger->error(
                sprintf('Failed to update member %d: %s', $member_id, $e->getMessage()),
                ['data' => $data]
            );
            return new WP_Error('update_member_failed', $e->getMessage());
        }
    }

    /**
     * Delete member
     *
     * @param int $member_id Member ID
     * @return bool|WP_Error
     */
    public function delete_member(int $member_id) {
        try {
            // Begin transaction
            $this->db->query('START TRANSACTION');

            // Delete related records
            $this->delete_member_records($member_id);

            // Delete member
            $deleted = $this->db->delete(
                $this->table_name,
                ['id' => $member_id],
                ['%d']
            );

            if (!$deleted) {
                throw new Exception(__('Failed to delete member', 'mkwa'));
            }

            // Commit transaction
            $this->db->query('COMMIT');

            $this->logger->info(sprintf('Deleted member %d', $member_id));

            return true;

        } catch (Exception $e) {
            // Rollback transaction
            $this->db->query('ROLLBACK');

            $this->logger->error(
                sprintf('Failed to delete member %d: %s', $member_id, $e->getMessage())
            );
            return new WP_Error('delete_member_failed', $e->getMessage());
        }
    }

    /**