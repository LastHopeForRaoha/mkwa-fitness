// includes/class-mkwa-image-manager.php

class MKWA_Image_Manager {
    private static $instance = null;
    private $allowed_mime_types = array(
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png'          => 'image/png',
        'svg'          => 'image/svg+xml'
    );

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_badge_url($badge_type, $level = 'default') {
        $badge_path = MKWA_PLUGIN_URL . 'assets/badges/' . $level . '/' . $badge_type . '.svg';
        return file_exists(MKWA_PLUGIN_DIR . 'assets/badges/' . $level . '/' . $badge_type . '.svg') 
            ? $badge_path 
            : MKWA_PLUGIN_URL . 'assets/badges/default/default.svg';
    }

    public function get_level_badge($level) {
        $level_path = MKWA_PLUGIN_URL . 'assets/levels/' . $level . '/badge.svg';
        return file_exists(MKWA_PLUGIN_DIR . 'assets/levels/' . $level . '/badge.svg')
            ? $level_path
            : MKWA_PLUGIN_URL . 'assets/levels/beginner/badge.svg';
    }

    public function get_activity_icon($activity_type) {
        $icon_path = MKWA_PLUGIN_URL . 'assets/icons/activities/' . $activity_type . '.svg';
        return file_exists(MKWA_PLUGIN_DIR . 'assets/icons/activities/' . $activity_type . '.svg')
            ? $icon_path
            : MKWA_PLUGIN_URL . 'assets/icons/activities/default.svg';
    }

    public function register_default_images() {
        // Ensure all default images exist
        $this->create_default_badge();
        $this->create_default_level_badge();
        $this->create_default_activity_icon();
    }

    private function create_default_badge() {
        $default_badge_path = MKWA_PLUGIN_DIR . 'assets/badges/default/default.svg';
        if (!file_exists($default_badge_path)) {
            // Create a simple default badge SVG
            $svg = $this->generate_default_svg('Badge');
            file_put_contents($default_badge_path, $svg);
        }
    }

    private function create_default_level_badge() {
        $default_level_path = MKWA_PLUGIN_DIR . 'assets/levels/beginner/badge.svg';
        if (!file_exists($default_level_path)) {
            $svg = $this->generate_default_svg('Level');
            file_put_contents($default_level_path, $svg);
        }
    }

    private function create_default_activity_icon() {
        $default_icon_path = MKWA_PLUGIN_DIR . 'assets/icons/activities/default.svg';
        if (!file_exists($default_icon_path)) {
            $svg = $this->generate_default_svg('Activity');
            file_put_contents($default_icon_path, $svg);
        }
    }

    private function generate_default_svg($text) {
        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100">
            <circle cx="50" cy="50" r="45" fill="#f0f0f0" stroke="#ccc" stroke-width="2"/>
            <text x="50" y="55" font-family="Arial" font-size="12" text-anchor="middle" fill="#666">
                Default {$text}
            </text>
        </svg>
        SVG;
    }

    public function ensure_directories() {
        $directories = array(
            'badges/default',
            'badges/bronze',
            'badges/silver',
            'badges/gold',
            'levels/beginner',
            'levels/intermediate',
            'levels/advanced',
            'icons/activities',
            'icons/points',
            'icons/navigation',
            'backgrounds'
        );

        foreach ($directories as $dir) {
            $full_path = MKWA_PLUGIN_DIR . 'assets/' . $dir;
            if (!file_exists($full_path)) {
                wp_mkdir_p($full_path);
            }
        }
    }
}

// Add to plugin initialization
add_action('plugins_loaded', array(MKWA_Image_Manager::get_instance(), 'ensure_directories'));