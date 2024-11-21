// includes/features/community/class-mkwa-community-goal-types.php

class MKWA_Community_Goal_Types {
    private static $instance = null;
    private $goal_types;

    private function __construct() {
        $this->goal_types = array(
            'collective' => array(
                'name' => 'Collective Goal',
                'description' => 'Combined effort from all participants',
                'icon' => 'users',
                'progress_type' => 'cumulative'
            ),
            'competitive' => array(
                'name' => 'Competitive Goal',
                'description' => 'Members compete to achieve the highest contribution',
                'icon' => 'trophy',
                'progress_type' => 'individual'
            ),
            'team' => array(
                'name' => 'Team Goal',
                'description' => 'Teams work together to achieve the goal',
                'icon' => 'people-group',
                'progress_type' => 'team'
            ),
            'challenge' => array(
                'name' => 'Time Challenge',
                'description' => 'Complete the goal within a time limit',
                'icon' => 'clock',
                'progress_type' => 'timed'
            ),
            'milestone' => array(
                'name' => 'Community Milestone',
                'description' => 'Long-term community achievement',
                'icon' => 'flag',
                'progress_type' => 'milestone'
            )
        );

        $this->goal_types = apply_filters('mkwa_community_goal_types', $this->goal_types);
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_goal_types() {
        return $this->goal_types;
    }

    public function get_goal_type($type) {
        return isset($this->goal_types[$type]) ? $this->goal_types[$type] : null;
    }

    public function register_goal_type($type, $data) {
        if (!isset($this->goal_types[$type])) {
            $this->goal_types[$type] = wp_parse_args($data, array(
                'name' => '',
                'description' => '',
                'icon' => 'flag',
                'progress_type' => 'cumulative'
            ));
            return true;
        }
        return false;
    }

    public function get_progress_types() {
        return array(
            'cumulative' => 'Total of all contributions',
            'individual' => 'Highest individual contribution',
            'team' => 'Team-based progress',
            'timed' => 'Time-based completion',
            'milestone' => 'Progressive milestones'
        );
    }
}