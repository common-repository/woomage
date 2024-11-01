<?php
/*
Plugin Name: Woomage Store Manager WooCommerce API
Plugin URI: http://www.woomage.com
Description: Woomage WooCommerce plugin enabling Woomage app to sync products with WooCommerce web store
Author: Woomage
Author URI: http://www.woomage.com
Version: 0.10.7

    Copyright: © 2017 Woomage (email : info@woomage.com)
    License: GNU General Public License v2.0
    License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/


/**
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    if ( ! class_exists( 'WC_Woomage' ) ) {



        /**
         * Localisation
         **/
        load_plugin_textdomain( 'wc_woomage', false, dirname( plugin_basename( __FILE__ ) ) . '/' );

        class WC_Woomage {


            public function __construct() {
                            register_activation_hook( __FILE__, array($this, 'install' ));
                            // called only after woocommerce has finished loading
                            add_action( 'woocommerce_api_loaded', array( $this, 'woocommerce_api_loaded' ) );
                            add_filter('woocommerce_api_index', array( $this, 'woomage_add_index'), 10, 1);

                            // indicates we are running the admin
                            if ( is_admin() ) {
                                    // ...
                            }

                            // indicates we are being served over ssl
                            if ( is_ssl() ) {
                                    // ...
                            }

                            // take care of anything else that needs to be done immediately upon plugin instantiation, here in the constructor
            }
            public function install(){
                $upload_dir = wp_upload_dir();
                $dir = $upload_dir['basedir'] .'/woomage_ftp/';
                @mkdir($dir);
            }
            public function woomage_add_index($available){
                $woomage_plugin_version = '0.10.7';
                $upload_dir = wp_upload_dir();
                $woomagedir = $upload_dir['basedir'] .'/woomage_ftp/';
                $available['store']['meta']['woomage_enabled'] = true;
                $available['store']['meta']['woomage_plugin_ver'] = $woomage_plugin_version;
                $available['store']['meta']['woomage_ftp_path'] = $woomagedir;
                $available['store']['meta']['product_types'] = array_keys( wc_get_product_types() );
                $available['store']['meta']['product_statuses'] = array_keys( get_post_statuses() );
                //Overwrite these until correction ok
                $available['store']['meta']['woomage_thousand_separator'] = get_option( 'woocommerce_price_thousand_sep' );
                $available['store']['meta']['woomage_decimal_separator'] = get_option( 'woocommerce_price_decimal_sep' );
                return $available;
            }
            public function add_woomage_api_classes($array){
                include_once( dirname( __FILE__ ) . '/class-woomage-wc-api-categories.php' );
                include_once( dirname( __FILE__ ) . '/class-woomage-wc-api-products.php' );
                include_once( dirname( __FILE__ ) . '/class-woomage-wc-v3-api-products.php' );
                include_once( dirname( __FILE__ ) . '/class-woomage-wc-api-media.php' );
                $array[]='Woomage_WC_API_Categories';
                $version = WoomageUtil::getWcVersion();
                $majorStr = $version[0];
                $major= (int)$majorStr;
                if($major < 3) {
                    $array[]='Woomage_WC_API_Products';
                } else {
                    $array[]='Woomage_WC_V3_API_Products';
                }

                $array[]='Woomage_WC_API_Media';
                return $array;
            }
            /**
             * Take care of anything that needs woocommerce to be loaded.
             * For instance, if you need access to the $woocommerce global
             */
            public function woocommerce_api_loaded() {
                            add_filter('woocommerce_api_classes', array( $this, 'add_woomage_api_classes'));
                            include_once( dirname( __FILE__ ) . '/class-woomage-wc-api-products-actions.php' );
                            $variations_handler = new Woomage_Product_Variations_Handler();
                            $products_handler = new Woomage_WC_API_Product_Action_Handler($variations_handler);
                            $products_handler->initialize_actions();
            }

            /**
             * Take care of anything that needs all plugins to be loaded
             */
            public function plugins_loaded() {

            }

            /**
             * Override any of the template functions from woocommerce/woocommerce-template.php
             * with our own template functions file
             */
            public function include_template_functions() {
                //include( 'woocommerce-template.php' );
            }
        }

        // finally instantiate our plugin class and add it to the set of globals
        $GLOBALS['wc_woomage'] = new WC_Woomage();
    }
}
