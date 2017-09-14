<?php
/**
 * Plugin Name: WooCommerce  Tealium Integration
 * Author: Vitali Korezki
 * Author URI: http://true-metrics.com
 * Version: 1.0.0
 * License: GPLv2 or later
 * Text Domain: woocommerce-tealium-integration
 * Domain Path: languages/
 */

if (!defined('ABSPATH')) {
    exit;
}


if (!class_exists('WC_Tealium_Integration')) :



    class WC_Google_Analytics_Integration_Via_Tealium
    {


        /**
         * Plugin version.
         *
         * @var string
         */
        const VERSION = '1.0.0';

        /**
         * Instance of this class.
         *
         * @var object
         */
        protected static $instance = null;

        /**
         * Initialize the plugin.
         */
        private function __construct()
        {
            // Load plugin text domain
            add_action('init', array($this, 'load_plugin_textdomain'));

            // Checks with WooCommerce is installed.
            if (class_exists('WC_Integration') && defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.1-beta-1', '>=')) {
                include_once 'includes/class-wc-google-analytics.php';
                include_once 'includes/class-wc-tealium-integration.php';
                include_once 'includes/class-wc-tealium-integration-datalayer.php';
                include_once 'includes/tealium-integration-custom-post-type.php';


                // Register the integration.
                add_filter('woocommerce_integrations', array($this, 'add_integration'));
            } else {
                add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            }
        }


        public static function get_instance()
        {
            // If the single instance hasn't been set, set it now.
            if (null == self::$instance) {
                self::$instance = new self;
            }

            return self::$instance;
        }


        public function woocommerce_missing_notice()
        {
            echo '<div class="error"><p>' . sprintf(__('WooCommerce Google Analytics depends on the last version of %s to work!', 'woocommerce-google-analytics-integration-via-tealium'), '<a href="http://www.woothemes.com/woocommerce/" target="_blank">' . __('WooCommerce', 'woocommerce-google-analytics-integration-via-tealium') . '</a>') . '</p></div>';
        }


        public function add_integration($integrations)
        {
            $integrations[] = 'WC_Tealium_Integration';
            $integrations[] = 'WC_Tealium_Integration_Datalayer';

            return $integrations;
        }
    }

    add_action('plugins_loaded', array('WC_Google_Analytics_Integration_Via_Tealium', 'get_instance'), 10);




endif;
