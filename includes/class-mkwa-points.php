<?php
/**
 * Handles points transactions database operations for the plugin.
 *
 * @link       https://github.com/LastHopeForRaoha/mkwa-fitness
 * @since      1.0.0
 *
 * @package    MKWA_Fitness
 * @subpackage MKWA_Fitness/includes
 */

class MKWA_Points {

    /**
     * Create a new points transaction.
     */
    public static function create_transaction($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'points_transactions';

        $wpdb->insert($table_name, $data);
        return $wpdb->insert_id;
    }

    /**
     * Read points transaction details.
     */
    public static function get_transaction($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'points_transactions';

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    }

    /**
     * Update points transaction details.
     */
    public static function update_transaction($id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'points_transactions';

        return $wpdb->update($table_name, $data, array('id' => $id));
    }

    /**
     * Delete a points transaction.
     */
    public static function delete_transaction($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'points_transactions';

        return $wpdb->delete($table_name, array('id' => $id));
    }
}
?>