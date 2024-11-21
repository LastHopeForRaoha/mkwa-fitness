// includes/features/dashboard/class-mkwa-dashboard-widget-templates.php

class MKWA_Dashboard_Widget_Templates {
    public static function get_workout_stats($settings) {
        // Example data callback for workout statistics
        return array(
            array(
                'label' => 'Total Workouts',
                'value' => '125',
                'change' => array(
                    'value' => '+12%',
                    'direction' => 'up'
                )
            ),
            array(
                'label' => 'This Week',
                'value' => '5',
                'change' => array(
                    'value' => '+2',
                    'direction' => 'up'
                )
            ),
            array(
                'label' => 'Avg. Duration',
                'value' => '45 min'
            )
        );
    }

    public static function get_achievement_progress($settings) {
        // Example data callback for achievement progress
        return array(
            'current' => 15,
            'total' => 25,
            'label' => 'Achievements Completed'
        );
    }

    public static function get_activity_chart($settings) {
        // Example data callback for activity chart
        return array(
            'labels' => array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'),
            'datasets' => array(
                array(
                    'label' => 'Workout Minutes',
                    'data' => array(30, 45, 60, 45, 30, 75, 60)
                )
            )
        );
    }

    public static function get_recent_activities($settings) {
        // Example data callback for recent activities list
        return array(
            array(
                'icon' => 'dashicons-heart',
                'label' => 'Cardio Workout',
                'value' => '30 min'
            ),
            array(
                'icon' => 'dashicons-performance',
                'label' => 'Strength Training',
                'value' => '45 min'
            ),
            array(
                'icon' => 'dashicons-awards',
                'label' => 'New Achievement',
                'value' => 'Level 5'
            )
        );
    }
}