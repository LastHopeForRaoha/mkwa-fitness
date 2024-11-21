// includes/features/leaderboards/class-mkwa-leaderboard-types.php

class MKWA_Leaderboard_Types {
    private static $instance = null;
    private $leaderboard_types;

    private function __construct() {
        $this->leaderboard_types = array(
            'points' => array(
                'name' => 'Points Leaderboard',
                'description' => 'Ranking based on total points earned',
                'icon' => 'star',
                'period_options' => array('daily', 'weekly', 'monthly', 'all-time')
            ),
            'streak' => array(
                'name' => 'Streak Leaderboard',
                'description' => 'Ranking based on workout streaks',
                'icon' => 'fire',
                'period_options' => array('current', 'all-time')
            ),
            'activity' => array(
                'name' => 'Activity Leaderboard',
                'description' => 'Ranking based on specific activities',
                'icon' => 'dumbbell',
                'period_options' => array('daily', 'weekly', 'monthly')
            ),
            'achievement' => array(
                'name' => 'Achievement Leaderboard',
                'description' => 'Ranking based on achievements earned',
                'icon' => 'trophy',
                'period_options' => array('monthly', 'all-time')
            ),
            'challenge' => array(
                'name' => 'Challenge Leaderboard',
                'description' => 'Ranking for specific challenges',
                'icon' => 'flag',
                'period_options' => array('custom')
            )
        );

        $this->leaderboard_types = apply_filters('mkwa_leaderboard_types', $this->leaderboard_types);
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_leaderboard_types() {
        return $this->leaderboard_types;
    }

    public function get_leaderboard_type($type) {
        return isset($this->leaderboard_types[$type]) ? $this->leaderboard_types[$type] : null;
    }

    public function register_leaderboard_type($type, $data) {
        if (!isset($this->leaderboard_types[$type])) {
            $this->leaderboard_types[$type] = wp_parse_args($data, array(
                'name' => '',
                'description' => '',
                'icon' => 'trophy',
                'period_options' => array('weekly')
            ));
            return true;
        }
        return false;
    }

    public function get_period_options() {
        return array(
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'all-time' => 'All Time',
            'current' => 'Current',
            'custom' => 'Custom Period'
        );
    }
}