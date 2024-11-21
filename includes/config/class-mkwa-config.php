// config/class-mkwa-config.php
<?php
/**
 * Configuration management for MKWA Fitness plugin
 *
 * @package    MKWA_Fitness
 * @subpackage MKWA_Fitness/config
 * @since      1.0.0
 */

// ADD THESE TWO LINES AT THE TOP AFTER THE OPENING PHP TAG
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class MKWA_Config {
    // ADD THIS CONSTANT AT THE TOP OF THE CLASS
    private const OPTION_NAME = 'mkwa_settings';

    private static $instance = null;
    private $settings = [];
    
    /**
     * Default plugin settings
     *
     * @var array
     */
    private $defaults = [
        'points' => [
            'visit' => 10,
            'class' => 20,
            'referral' => 50,
            'streak_bonus_multiplier' => 1.5,
            'minimum_streak_days' => 3,
        ],
        'features' => [
            'achievement_notification' => true,
            'leaderboard' => true,
            'community_goals' => true,
            'aboriginal_language' => true,
        ],
        'time' => [
            'off_peak_start' => 10,
            'off_peak_end' => 16,
        ],
        'security' => [
            'max_login_attempts' => 5,
            'lockout_duration' => 900, // 15 minutes
        ],
    ];

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_settings();
    }

    /**
     * Get instance of the config class
     *
     * @return MKWA_Config
     */
    public static function get_instance(): MKWA_Config {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load settings from WordPress options
     */
    private function load_settings(): void {
        // UPDATE THIS LINE TO USE THE CONSTANT
        $saved_settings = get_option(self::OPTION_NAME, []);
        $this->settings = wp_parse_args($saved_settings, $this->defaults);
    }

    /**
     * Get a setting value
     *
     * @param string $key     Setting key using dot notation (e.g., 'points.visit')
     * @param mixed  $default Default value if setting doesn't exist
     * @return mixed
     */
    public function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->settings;

        foreach ($keys as $key_part) {
            if (!isset($value[$key_part])) {
                return $default;
            }
            $value = $value[$key_part];
        }

        return $value;
    }

    /**
     * Update a setting value
     *
     * @param string $key   Setting key using dot notation
     * @param mixed  $value New value
     * @return bool
     */
    public function update($key, $value): bool {
        $keys = explode('.', $key);
        $settings = &$this->settings;
        
        foreach ($keys as $i => $key_part) {
            if ($i === count($keys) - 1) {
                $settings[$key_part] = $value;
            } else {
                if (!isset($settings[$key_part]) || !is_array($settings[$key_part])) {
                    $settings[$key_part] = [];
                }
                $settings = &$settings[$key_part];
            }
        }

        // UPDATE THIS LINE TO USE THE CONSTANT
        return update_option(self::OPTION_NAME, $this->settings);
    }

    /**
     * Validate a setting value
     *
     * @param string $key   Setting key
     * @param mixed  $value Value to validate
     * @return bool|WP_Error
     */
    public function validate($key, $value) {
        $validators = [
            'points.visit' => fn($v) => is_int($v) && $v >= 0,
            'points.class' => fn($v) => is_int($v) && $v >= 0,
            'points.referral' => fn($v) => is_int($v) && $v >= 0,
            'points.streak_bonus_multiplier' => fn($v) => is_float($v) && $v >= 1.0,
            'points.minimum_streak_days' => fn($v) => is_int($v) && $v >= 1,
            'time.off_peak_start' => fn($v) => is_int($v) && $v >= 0 && $v <= 23,
            'time.off_peak_end' => fn($v) => is_int($v) && $v >= 0 && $v <= 23,
        ];

        if (!isset($validators[$key])) {
            return true;
        }

        return $validators[$key]($value) ? true : new WP_Error(
            'invalid_setting',
            sprintf(__('Invalid value for setting: %s', 'mkwa'), $key)
        );
    }

    // ADD THESE NEW METHODS AT THE END OF THE CLASS, BEFORE THE CLOSING BRACE

    /**
     * Reset settings to defaults
     *
     * @return bool
     */
    public function reset(): bool {
        $this->settings = $this->defaults;
        return update_option(self::OPTION_NAME, $this->defaults);
    }
    
    /**
     * Get all settings
     *
     * @return array
     */
    public function get_all(): array {
        return $this->settings;
    }
    
    /**
     * Check if a setting exists
     *
     * @param string $key Setting key using dot notation
     * @return bool
     */
    public function has(string $key): bool {
        $keys = explode('.', $key);
        $value = $this->settings;

        foreach ($keys as $key_part) {
            if (!isset($value[$key_part])) {
                return false;
            }
            $value = $value[$key_part];
        }

        return true;
    }
    
    /**
     * Validate a feature
     *
     * @param string $feature Feature key to validate
     * @return bool
     */
    private function validate_feature(string $feature): bool {
        return in_array($feature, array_keys($this->defaults['features']), true);
    }
}