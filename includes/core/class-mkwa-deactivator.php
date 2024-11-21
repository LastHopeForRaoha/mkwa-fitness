// includes/core/class-mkwa-deactivator.php

class MKWA_Deactivator {
    public static function deactivate() {
        // Clear scheduled hooks
        wp_clear_scheduled_hook('mkwa_daily_maintenance');
        wp_clear_scheduled_hook('mkwa_weekly_report');
        wp_clear_scheduled_hook('mkwa_monthly_points_reset');

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}