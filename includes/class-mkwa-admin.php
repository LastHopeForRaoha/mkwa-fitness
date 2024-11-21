<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://github.com/LastHopeForRaoha/mkwa-fitness
 * @since      1.0.0
 *
 * @package    MKWA_Fitness
 * @subpackage MKWA_Fitness/admin
 */

/**
 * The admin-specific functionality of the plugin.
 */
class MKWA_Fitness_Admin {

    /**
     * The ID of this plugin.
     *
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @param      string    $plugin_name       The name of the plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {

        $this->plugin_name = $plugin_name;
        $this->version = $version;

    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {

        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/mkwa-fitness-admin.css', array(), $this->version, 'all' );

    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {

        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/mkwa-fitness-admin.js', array( 'jquery' ), $this->version, false );

    }

}
?>