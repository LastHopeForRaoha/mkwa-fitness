// includes/features/dashboard/class-mkwa-dashboard-assets.php

class MKWA_Dashboard_Assets {
    public static function register_assets() {
        // Register and enqueue CSS
        wp_register_style(
            'mkwa-dashboard-widgets',
            MKWA_PLUGIN_URL . 'assets/css/dashboard-widgets.css',
            array(),
            MKWA_VERSION
        );

        // Register and enqueue JavaScript
        wp_register_script(
            'mkwa-dashboard-widgets',
            MKWA_PLUGIN_URL . 'assets/js/dashboard-widgets.js',
            array('jquery', 'chart-js'),
            MKWA_VERSION,
            true
        );

        // Localize script with necessary data
        wp_localize_script('mkwa-dashboard-widgets', 'mkwaDashboard', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mkwa_dashboard_nonce'),
            'i18n' => array(
                'refreshing' => __('Refreshing...', 'my-workout-app'),
                'updated' => __('Updated', 'my-workout-app'),
                'error' => __('Error updating widget', 'my-workout-app')
            )
        ));

        // Enqueue assets
        wp_enqueue_style('mkwa-dashboard-widgets');
        wp_enqueue_script('mkwa-dashboard-widgets');
    }
}