<?php
/**
 * Handles database operations for the plugin.
 *
 * @link       https://github.com/LastHopeForRaoha/mkwa-fitness
 * @since      1.0.0
 *
 * @package    MKWA_Fitness
 * @subpackage MKWA_Fitness/includes
 */

class MKWA_Fitness_DB {

    /**
     * Create the necessary tables for the plugin.
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = [
            "CREATE TABLE {$wpdb->prefix}members (
                id BIGINT PRIMARY KEY AUTO_INCREMENT,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                first_name VARCHAR(50),
                last_name VARCHAR(50),
                join_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                membership_type ENUM('standard', 'premium', 'student', 'family_2adults', 'family_2adults_2children'),
                is_aboriginal BOOLEAN DEFAULT FALSE,
                status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
                privacy_settings JSON,
                last_login TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}member_cards (
                id BIGINT PRIMARY KEY AUTO_INCREMENT,
                member_id BIGINT NOT NULL,
                card_number VARCHAR(50) UNIQUE NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_used TIMESTAMP,
                FOREIGN KEY (member_id) REFERENCES {$wpdb->prefix}members(id)
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}points_transactions (
                id BIGINT PRIMARY KEY AUTO_INCREMENT,
                member_id BIGINT NOT NULL,
                points INT NOT NULL,
                transaction_type ENUM('earned', 'redeemed', 'expired', 'adjusted'),
                activity_type VARCHAR(50),
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (member_id) REFERENCES {$wpdb->prefix}members(id)
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}daily_activities (
                id BIGINT PRIMARY KEY AUTO_INCREMENT,
                member_id BIGINT NOT NULL,
                activity_type VARCHAR(50) NOT NULL,
                location_id VARCHAR(50),
                points_earned INT DEFAULT 0,
                check_in_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_daily_activity (member_id, activity_type, DATE(check_in_time)),
                FOREIGN KEY (member_id) REFERENCES {$wpdb->prefix}members(id)
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}achievements (
                id BIGINT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                points_reward INT DEFAULT 0,
                badge_image_url VARCHAR(255),
                requirements JSON,
                achievement_type ENUM('daily', 'weekly', 'monthly', 'special'),
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}member_achievements (
                id BIGINT PRIMARY KEY AUTO_INCREMENT,
                member_id BIGINT NOT NULL,
                achievement_id BIGINT NOT NULL,
                earned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (member_id) REFERENCES {$wpdb->prefix}members(id),
                FOREIGN KEY (achievement_id) REFERENCES {$wpdb->prefix}achievements(id)
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}workout_streaks (
                id BIGINT PRIMARY KEY AUTO_INCREMENT,
                member_id BIGINT NOT NULL,
                current_streak INT DEFAULT 0,
                longest_streak INT DEFAULT 0,
                last_check_in TIMESTAMP,
                streak_multiplier DECIMAL(3,2) DEFAULT 1.00,
                FOREIGN KEY (member_id) REFERENCES {$wpdb->prefix}members(id)
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}community_goals (
                id BIGINT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                target_value INT NOT NULL,
                current_value INT DEFAULT 0,
                start_date TIMESTAMP,
                end_date TIMESTAMP,
                goal_type VARCHAR(50),
                status ENUM('active', 'completed', 'failed') DEFAULT 'active',
                points_reward INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}community_goal_participants (
                id BIGINT PRIMARY KEY AUTO_INCREMENT,
                goal_id BIGINT NOT NULL,
                member_id BIGINT NOT NULL,
                contribution_value INT DEFAULT 0,
                joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (goal_id) REFERENCES {$wpdb->prefix}community_goals(id),
                FOREIGN KEY (member_id) REFERENCES {$wpdb->prefix}members(id)
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}store_items (
                id BIGINT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                cash_price DECIMAL(10,2),
                points_cost INT NOT NULL,
                stock_quantity INT DEFAULT 0,
                category VARCHAR(50),
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}redemption_history (
                id BIGINT PRIMARY KEY AUTO_INCREMENT,
                member_id BIGINT NOT NULL,
                item_id BIGINT NOT NULL,
                points_spent INT NOT NULL,
                redemption_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
                FOREIGN KEY (member_id) REFERENCES {$wpdb->prefix}members(id),
                FOREIGN KEY (item_id) REFERENCES {$wpdb->prefix}store_items(id)
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}gym_analytics (
                id BIGINT PRIMARY KEY AUTO_INCREMENT,
                date DATE NOT NULL,
                total_visits INT DEFAULT 0,
                unique_visitors INT DEFAULT 0,
                points_awarded INT DEFAULT 0,
                points_redeemed INT DEFAULT 0,
                popular_times JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}language_content (
                id BIGINT PRIMARY KEY AUTO_INCREMENT,
                english_term VARCHAR(100) NOT NULL,
                aboriginal_term VARCHAR(100) NOT NULL,
                category VARCHAR(50),
                description TEXT,
                audio_url VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}system_settings (
                id BIGINT PRIMARY KEY AUTO_INCREMENT,
                setting_key VARCHAR(50) UNIQUE NOT NULL,
                setting_value JSON,
                description TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) $charset_collate;",

            "CREATE INDEX idx_member_activities ON {$wpdb->prefix}daily_activities(member_id, activity_type);",
            "CREATE INDEX idx_points_transactions ON {$wpdb->prefix}points_transactions(member_id, transaction_type);",
            "CREATE INDEX idx_member_achievements ON {$wpdb->prefix}member_achievements(member_id);",
            "CREATE INDEX idx_community_goals ON {$wpdb->prefix}community_goals(status, end_date);"
        ];

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        foreach ($sql as $table) {
            dbDelta($table);
        }
    }
}
?>