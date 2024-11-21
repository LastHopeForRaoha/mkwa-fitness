// includes/features/activities/class-mkwa-activity-types.php

class MKWA_Activity_Types {
    private static $instance = null;
    private $activity_types;

    private function __construct() {
        $this->activity_types = array(
            'gym_visit' => array(
                'name' => 'Gym Visit',
                'description' => 'Regular gym attendance',
                'base_points' => 10,
                'requires_duration' => false,
                'requires_intensity' => false
            ),
            'class_attendance' => array(
                'name' => 'Class Attendance',
                'description' => 'Participation in gym classes',
                'base_points' => 20,
                'requires_duration' => true,
                'requires_intensity' => true
            ),
            'personal_training' => array(
                'name' => 'Personal Training',
                'description' => 'Session with a personal trainer',
                'base_points' => 30,
                'requires_duration' => true,
                'requires_intensity' => true
            ),
            'cardio_session' => array(
                'name' => 'Cardio Session',
                'description' => 'Cardiovascular exercise',
                'base_points' => 15,
                'requires_duration' => true,
                'requires_intensity' => true
            ),
            'strength_training' => array(
                'name' => 'Strength Training',
                'description' => 'Weight and resistance training',
                'base_points' => 15,
                'requires_duration' => true,
                'requires_intensity' => true
            ),
            'achievement_unlock' => array(
                'name' => 'Achievement Unlocked',
                'description' => 'Earning a new achievement',
                'base_points' => 0, // Points determined by achievement
                'requires_duration' => false,
                'requires_intensity' => false
            ),
            'community_goal_contribution' => array(
                'name' => 'Community Goal Contribution',
                'description' => 'Contributing to community goals',
                'base_points' => 0, // Points determined by contribution
                'requires_duration' => false,
                'requires_intensity' => false
            )
        );

        $this->activity_types = apply_filters('mkwa_activity_types', $this->activity_types);
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_activity_types() {
        return $this->activity_types;
    }

    public function get_activity_type($type) {
        return isset($this->activity_types[$type]) ? $this->activity_types[$type] : null;
    }

    public function activity_requires_duration($type) {
        $activity = $this->get_activity_type($type);
        return $activity ? $activity['requires_duration'] : false;
    }

    public function activity_requires_intensity($type) {
        $activity = $this->get_activity_type($type);
        return $activity ? $activity['requires_intensity'] : false;
    }

    public function get_base_points($type) {
        $activity = $this->get_activity_type($type);
        return $activity ? $activity['base_points'] : 0;
    }

    public function validate_activity_data($type, $data) {
        $activity = $this->get_activity_type($type);
        
        if (!$activity) {
            return new WP_Error('invalid_activity_type', 'Invalid activity type.');
        }

        $errors = array();

        if ($activity['requires_duration'] && empty($data['duration'])) {
            $errors[] = 'Duration is required for this activity.';
        }

        if ($activity['requires_intensity'] && empty($data['intensity_level'])) {
            $errors[] = 'Intensity level is required for this activity.';
        }

        return empty($errors) ? true : new WP_Error('invalid_activity_data', implode(' ', $errors));
    }
}