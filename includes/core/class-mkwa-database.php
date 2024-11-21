// includes/core/class-mkwa-database.php

class MKWA_Database {
    private static $instance = null;
    private $wpdb;
    private $charset_collate;
    private $tables;

    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
        
        // Define table names
        $this->tables = array(
            'members'                => $wpdb->prefix . 'mkwa_members',
            'points'                 => $wpdb->prefix . 'mkwa_points_transactions',
            'activities'            => $wpdb->prefix . 'mkwa_daily_activities',
            'achievements'          => $wpdb->prefix . 'mkwa_achievements',
            'member_achievements'   => $wpdb->prefix . 'mkwa_member_achievements',
            'workout_streaks'      => $wpdb->prefix . 'mkwa_workout_streaks',
            'community_goals'      => $wpdb->prefix . 'mkwa_community_goals',
            'goal_participants'    => $wpdb->prefix . 'mkwa_community_goal_participants',
            'gym_analytics'        => $wpdb->prefix . 'mkwa_gym_analytics',
            'language_content'     => $wpdb->prefix . 'mkwa_language_content'
        );
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Members Table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['members']} (
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
            card_id VARCHAR(50) UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) {$this->charset_collate};";
        dbDelta($sql);

        // Points Transactions Table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['points']} (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            member_id BIGINT NOT NULL,
            points INT NOT NULL,
            transaction_type ENUM('earned', 'redeemed', 'expired', 'adjusted'),
            activity_type VARCHAR(50),
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (member_id) REFERENCES {$this->tables['members']}(id)
        ) {$this->charset_collate};";
        dbDelta($sql);

        // Daily Activities Table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['activities']} (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            member_id BIGINT NOT NULL,
            activity_type VARCHAR(50) NOT NULL,
            duration INT,
            intensity_level ENUM('low', 'medium', 'high'),
            points_earned INT,
            comments TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (member_id) REFERENCES {$this->tables['members']}(id)
        ) {$this->charset_collate};";
        dbDelta($sql);

        // Achievements Table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['achievements']} (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            badge_image_url VARCHAR(255),
            points_value INT,
            requirements JSON,
            achievement_type VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) {$this->charset_collate};";
        dbDelta($sql);

        // Member Achievements Table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['member_achievements']} (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            member_id BIGINT NOT NULL,
            achievement_id BIGINT NOT NULL,
            earned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (member_id) REFERENCES {$this->tables['members']}(id),
            FOREIGN KEY (achievement_id) REFERENCES {$this->tables['achievements']}(id)
        ) {$this->charset_collate};";
        dbDelta($sql);

        // Workout Streaks Table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['workout_streaks']} (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            member_id BIGINT NOT NULL,
            current_streak INT DEFAULT 0,
            longest_streak INT DEFAULT 0,
            last_activity_date DATE,
            streak_start_date DATE,
            FOREIGN KEY (member_id) REFERENCES {$this->tables['members']}(id)
        ) {$this->charset_collate};";
        dbDelta($sql);

        // Community Goals Table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['community_goals']} (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(100) NOT NULL,
            description TEXT,
            target_value INT NOT NULL,
            current_value INT DEFAULT 0,
            start_date DATE,
            end_date DATE,
            status ENUM('active', 'completed', 'failed') DEFAULT 'active',
            reward_points INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) {$this->charset_collate};";
        dbDelta($sql);

        // Goal Participants Table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['goal_participants']} (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            goal_id BIGINT NOT NULL,
            member_id BIGINT NOT NULL,
            contribution_value INT DEFAULT 0,
            joined_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (goal_id) REFERENCES {$this->tables['community_goals']}(id),
            FOREIGN KEY (member_id) REFERENCES {$this->tables['members']}(id)
        ) {$this->charset_collate};";
        dbDelta($sql);

        // Gym Analytics Table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['gym_analytics']} (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            date DATE NOT NULL,
            hour INT NOT NULL,
            member_count INT DEFAULT 0,
            peak_time BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY date_hour (date, hour)
        ) {$this->charset_collate};";
        dbDelta($sql);

        // Language Content Table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['language_content']} (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            content_key VARCHAR(100) NOT NULL,
            english_text TEXT NOT NULL,
            aboriginal_text TEXT,
            content_type VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY content_key (content_key)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }

    public function get_table_name($table) {
        return isset($this->tables[$table]) ? $this->tables[$table] : false;
    }

    public function drop_tables() {
        foreach ($this->tables as $table) {
            $this->wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
}