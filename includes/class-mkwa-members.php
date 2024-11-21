<?php
/**
 * Handles member database operations for the plugin.
 *
 * @link       https://github.com/LastHopeForRaoha/mkwa-fitness
 * @since      1.0.0
 *
 * @package    MKWA_Fitness
 * @subpackage MKWA_Fitness/includes
 */

class MKWA_Members {

    /**
     * Create a new member.
     */
    public static function create_member($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'members';

        $wpdb->insert($table_name, $data);
        return $wpdb->insert_id;
    }

    /**
     * Read member details.
     */
    public static function get_member($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'members';

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    }

    /**
     * Update member details.
     */
    public static function update_member($id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'members';

        return $wpdb->update($table_name, $data, array('id' => $id));
    }

    /**
     * Delete a member.
     */
    public static function delete_member($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'members';

        return $wpdb->delete($table_name, array('id' => $id));
    }
}
?>