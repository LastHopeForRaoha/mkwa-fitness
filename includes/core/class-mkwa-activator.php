// includes/core/class-mkwa-activator.php

class MKWA_Activator {
    public static function activate() {
        // Create database tables
        $database = MKWA_Database::get_instance();
        $database->create_tables();

        // Set default options
        add_option('mkwa_version', MKWA_VERSION);
        add_option('mkwa_installed_at', current_time('mysql'));
        
        // Set default plugin settings
        $default_settings = array(
            'points_per_visit' => 10,
            'points_per_class' => 20,
            'points_per_referral' => 50,
            'streak_bonus_multiplier' => 1.5,
            'minimum_streak_days' => 3,
            'achievement_notification' => true,
            'enable_leaderboard' => true,
            'enable_community_goals' => true,
            'enable_aboriginal_language' => true
        );
        add_option('mkwa_settings', $default_settings);

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}