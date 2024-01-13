<?php
/**
 * Plugin Name: Give - MailNiaga v2
 * Plugin URI:  https://mailniaga.com
 * Description: Integrate MailNiaga v2 sign-up with your Give donation forms.
 * Version:     1.0.0
 * Author:      Web Impian Sdn Bhd
 * Author URI:  https://lamanweb.my
 * Text Domain: give-mailniaga
 * Domain Path: /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants.
if ( ! defined( 'GIVE_MAILNIAGA_VERSION' ) ) {
    define( 'GIVE_MAILNIAGA_VERSION', '1.2.5' );
}
if ( ! defined( 'GIVE_MAILNIAGA_MIN_GIVE_VERSION' ) ) {
    define( 'GIVE_MAILNIAGA_MIN_GIVE_VERSION', '2.5.0' );
}
if ( ! defined( 'GIVE_MAILNIAGA_FILE' ) ) {
    define( 'GIVE_MAILNIAGA_FILE', __FILE__ );
}
if ( ! defined( 'GIVE_MAILNIAGA_PATH' ) ) {
    define( 'GIVE_MAILNIAGA_PATH', dirname( GIVE_MAILNIAGA_FILE ) );
}
if ( ! defined( 'GIVE_MAILNIAGA_URL' ) ) {
    define( 'GIVE_MAILNIAGA_URL', plugin_dir_url( GIVE_MAILNIAGA_FILE ) );
}
if ( ! defined( 'GIVE_MAILNIAGA_BASENAME' ) ) {
    define( 'GIVE_MAILNIAGA_BASENAME', plugin_basename( GIVE_MAILNIAGA_FILE ) );
}
if ( ! defined( 'GIVE_MAILNIAGA_DIR' ) ) {
    define( 'GIVE_MAILNIAGA_DIR', plugin_dir_path( GIVE_MAILNIAGA_FILE ) );
}
if ( ! defined( 'GIVE_MAILNIAGA_REQUIRED_PHP_VERSION' ) ) {
    define( 'GIVE_MAILNIAGA_REQUIRED_PHP_VERSION', '5.4.0' );
}


if ( ! class_exists( 'GIVE_MAILNIAGA' ) ) {

    /**
     * Class GIVE_MAILNIAGA
     *
     * @since 1.2.2
     */
    class GIVE_MAILNIAGA {

        /**
         * @since 1.2.2
         *
         * @var Give_Mollie The reference the singleton instance of this class.
         */
        private static $instance;

        /**
         * Notices (array)
         *
         * @since 1.2.2
         *
         * @var array
         */
        public $notices = array();

        /**
         * Returns the singleton instance of this class.
         *
         * @since 1.2.2
         * @return GIVE_MAILNIAGA The singleton instance.
         */
        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
                self::$instance->setup();
            }

            return self::$instance;
        }


        /**
         * Setup Give Mollie.
         *
         * @since  1.2.2
         * @access private
         */
        public function setup() {

            // Give init hook.
            add_action( 'give_init', array( $this, 'init' ), 10 );
            add_action( 'admin_init', array( $this, 'check_environment' ), 999 );
            add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
        }

        /**
         * Init the plugin after plugins_loaded so environment variables are set.
         *
         * @since 1.2.2
         */
        public function init() {

            if ( ! $this->get_environment_warning() ) {
                return;
            }

            $this->activation_banner();

            include GIVE_MAILNIAGA_PATH . '/includes/plugin-listing-page.php';
	        include GIVE_MAILNIAGA_PATH . '/includes/class-give-mailniaga-sendy.php';
            include GIVE_MAILNIAGA_PATH . '/includes/class-give-mailniaga.php';

            // Load scripts.
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ), 100 );

        }


        /**
         * Load Admin Scripts
         *
         * Enqueues the required admin scripts.
         *
         * @since 1.2.2
         * @global       $post
         *
         * @param string $hook Page hook
         *
         * @return void
         */
        function admin_scripts( $hook ) {

            global $post_type;

            // Directories of assets
            $js_dir  = GIVE_MAILNIAGA_URL . 'assets/js/';
            $css_dir = GIVE_MAILNIAGA_URL . 'assets/css/';

            wp_register_script( 'give_mailniaga_admin_ajax_js', $js_dir . 'admin-ajax.js', array( 'jquery' ) );

            // Forms CPT Script
            if ( 'give_forms' === $post_type ) {

                // CSS
                wp_register_style( 'give_mailniaga_admin_css', $css_dir . 'admin-forms.css' );
	            wp_register_style( 'give_mailniaga_sendy_admin_css', $css_dir . 'admin-sendy-forms.css' );
                wp_enqueue_style( 'give_mailniaga_admin_css' );
	            wp_enqueue_style( 'give_mailniaga_sendy_admin_css' );

                // JS
                wp_register_script( 'give-mailniaga-admin-forms-scripts', $js_dir . 'admin-forms.js', array( 'jquery' ), GIVE_MAILNIAGA_VERSION, false );
	            wp_register_script( 'give-mailniaga-sendy-admin-forms-scripts', $js_dir . 'admin-sendy-forms.js', array( 'jquery' ), GIVE_MAILNIAGA_VERSION, false );
                wp_enqueue_script( 'give-mailniaga-admin-forms-scripts' );
	            wp_enqueue_script( 'give-mailniaga-sendy-admin-forms-scripts' );

                wp_enqueue_script( 'give_mailniaga_admin_ajax_js' );

            }

            // Admin settings.
            if ( $hook == 'give_forms_page_give-settings' ) {
                wp_enqueue_script( 'give_mailniaga_admin_ajax_js' );
            }

        }

        /**
         * Check plugin environment.
         *
         * @since  1.2.2
         * @access public
         *
         * @return bool
         */
        public function check_environment() {
            // Flag to check whether plugin file is loaded or not.
            $is_working = true;

            // Load plugin helper functions.
            if ( ! function_exists( 'is_plugin_active' ) ) {
                require_once ABSPATH . '/wp-admin/includes/plugin.php';
            }

            /*
             Check to see if Give is activated, if it isn't deactivate and show a banner. */
            // Check for if give plugin activate or not.
            $is_give_active = defined( 'GIVE_PLUGIN_BASENAME' ) ? is_plugin_active( GIVE_PLUGIN_BASENAME ) : false;

            if ( empty( $is_give_active ) ) {
                // Show admin notice.
                $this->add_admin_notice( 'prompt_give_activate', 'error', sprintf( __( '<strong>Activation Error:</strong> You must have the <a href="%s" target="_blank">Give</a> plugin installed and activated for Give - MailNiaga v2 to activate.', 'give-mailniaga' ), 'https://givewp.com' ) );
                $is_working = false;
            }

            return $is_working;
        }

        /**
         * Check plugin for Give environment.
         *
         * @since  1.2.2
         * @access public
         *
         * @return bool
         */
        public function get_environment_warning() {
            // Flag to check whether plugin file is loaded or not.
            $is_working = true;

            // Verify dependency cases.
            if (
                defined( 'GIVE_VERSION' )
                && version_compare( GIVE_VERSION, GIVE_MAILNIAGA_MIN_GIVE_VERSION, '<' )
            ) {

                /*
                 Min. Give. plugin version. */
                // Show admin notice.
                $this->add_admin_notice( 'prompt_give_incompatible', 'error', sprintf( __( '<strong>Activation Error:</strong> You must have the <a href="%1$s" target="_blank">Give</a> core version %2$s for the Give - MailNiaga v2 add-on to activate.', 'give-mailniaga' ), 'https://givewp.com', GIVE_MAILNIAGA_MIN_GIVE_VERSION ) );

                $is_working = false;
            }

            if ( version_compare( phpversion(), GIVE_MAILNIAGA_REQUIRED_PHP_VERSION, '<' ) ) {
                $this->add_admin_notice( 'prompt_give_incompatible', 'error', sprintf( __( '<strong>Activation Error:</strong> You must have the <a href="%1$s" target="_blank">PHP</a> version %2$s or above for the Give - MailNiaga v2 add-on to activate.', 'give-mailniaga' ), 'https://givewp.com/documentation/core/requirements/', GIVE_MAILNIAGA_REQUIRED_PHP_VERSION ) );

                $is_working = false;
            }

            return $is_working;
        }

        /**
         * Allow this class and other classes to add notices.
         *
         * @since 1.2.2
         *
         * @param $slug
         * @param $class
         * @param $message
         */
        public function add_admin_notice( $slug, $class, $message ) {
            $this->notices[ $slug ] = array(
                'class'   => $class,
                'message' => $message,
            );
        }

        /**
         * Display admin notices.
         *
         * @since 1.2.2
         */
        public function admin_notices() {

            $allowed_tags = array(
                'a'      => array(
                    'href'  => array(),
                    'title' => array(),
                    'class' => array(),
                    'id'    => array(),
                ),
                'br'     => array(),
                'em'     => array(),
                'span'   => array(
                    'class' => array(),
                ),
                'strong' => array(),
            );

            foreach ( (array) $this->notices as $notice_key => $notice ) {
                echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
                echo wp_kses( $notice['message'], $allowed_tags );
                echo '</p></div>';
            }
        }

        /**
         * Show activation banner for this add-on.
         *
         * @since 1.2.2
         *
         * @return bool
         */
        public function activation_banner() {

            // Check for activation banner inclusion.
            if (
                ! class_exists( 'Give_Addon_Activation_Banner' )
                && file_exists( GIVE_PLUGIN_DIR . 'includes/admin/class-addon-activation-banner.php' )
            ) {
                include GIVE_PLUGIN_DIR . 'includes/admin/class-addon-activation-banner.php';
            }

            // Initialize activation welcome banner.
            if ( class_exists( 'Give_Addon_Activation_Banner' ) ) {

                // Only runs on admin
                $args = array(
                    'file'              => __FILE__,
                    'name'              => esc_html__( 'MailNiaga v2', 'give-mailniaga' ),
                    'version'           => GIVE_MAILNIAGA_VERSION,
                    'settings_url'      => admin_url( 'edit.php?post_type=give_forms&page=give-settings&tab=addons&section=mailniaga-settings' ),
                    'support_url'       => 'https://lamanweb.my/hubungi/',
                    'testing'           => false, // Never leave as true
                );

                new Give_Addon_Activation_Banner( $args );
            }

            return true;
        }
    }

    /**
     * Returns class object instance.
     *
     * @since 1.2.2
     *
     * @return GIVE_MAILNIAGA bool|object
     */
    function GIVE_MAILNIAGA() {
        return GIVE_MAILNIAGA::get_instance();
    }

    GIVE_MAILNIAGA();
}
