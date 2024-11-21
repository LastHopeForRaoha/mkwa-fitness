// includes/features/achievements/class-mkwa-achievement-types.php

class MKWA_Achievement_Types {
    private static $instance = null;
    private $achievement_types;

    private function __construct() {
        $this->achievement_types = array(
            'milestone' => array(
                'name' => 'Milestone Achievement',
                'description' => 'Awarded for reaching specific milestones',
                'icon' => 'trophy'
            ),
            'streak' => array(
                'name' => 'Streak Achievement',
                'description' => 'Awarded for maintaining workout streaks',
                'icon' => 'fire'
            ),
            'activity' => array(
                'name' => 'Activity Achievement',
                'description' => 'Awarded for completing specific activities',
                'icon' => 'dumbbell'
            ),
            'points' => array(
                'name' => 'Points Achievement',
                'description' => 'Awarded for accumulating points',
                'icon' => 'star'
            ),
            'community' => array(
                'name' => 'Community Achievement',
                'description' => 'Awarded for community participation',
                'icon' => 'users'
            ),
            'special' => array(
                'name' => 'Special Achievement',
                'description' => 'Special or seasonal achievements',
                'icon' => 'award'
            )
        );

        $this->achievement_types = apply_filters('mkwa_achievement_types', $this->achievement_types);
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_achievement_types() {
        return $this->achievement_types;
    }

    public function get_achievement_type($type) {
        return isset($this->achievement_types[$type]) ? $this->achievement_types[$type] : null;
    }

    public function get_type_icon($type) {
        $achievement_type = $this->get_achievement_type($type);
        return $achievement_type ? $achievement_type['icon'] : 'trophy';
    }

    public function register_achievement_type($type, $data) {
        if (!isset($this->achievement_types[$type])) {
            $this->achievement_types[$type] = wp_parse_args($data, array(
                'name' => '',
                'description' => '',
                'icon' => 'trophy'
            ));
            return true;
        }
        return false;
    }
}