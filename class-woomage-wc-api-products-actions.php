<?php
/**
 * Woomage WooCommerce API Products Actions Class
 *
 * Handles requests to the /categories endpoint
 *
 * @author      Woomage
 * @category    Woomage WooCommerce REST API extension
 * @package     WooMage
 * @since       1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
include_once( dirname( __FILE__ ) . '/class-woomage-wc-api-product-variations.php' );
include_once( dirname( __FILE__ ) . '/class-woomage-wc-api-products.php' );


class Woomage_WC_API_Product_Action_Handler {
    
    private $variations_handler;
    public function __construct(Woomage_Product_Variations_Handler $handler) {
         $this->variations_handler = $handler;
    }
    
    public function initialize_actions(){
        add_action('woocommerce_api_create_product', array( $this, 'woomage_create_product'), 10, 2);
        add_action('woocommerce_api_edit_product', array( $this, 'woomage_edit_product'), 10, 2);
        add_filter('woocommerce_api_product_response', array( $this, 'woomage_get_product'), 10, 4);
    }
    public function woomage_create_product($product_id, $data){
        $this->save_woomage_meta($product_id, $data);
        //$this->variations_handler->save_variations($product_id, $data);
    }
    public function woomage_edit_product($product_id, $data){
        $this->save_woomage_meta($product_id, $data);
        //$this->variations_handler->save_variations($product_id, $data);
    }
    public function woomage_get_product($product_data, $product, $fields, $server){
        $product_data = $this->inject_woomage_meta($product_data, $product, '_woomageid', 'woomageid', true);
        $product_data = $this->inject_woomage_meta($product_data, $product, '_wm_package_size', 'package_size');
        $product_data = $this->inject_woomage_meta($product_data, $product, '_wm_package_unit', 'package_unit');

        $product_data['default_attributes'] = $this->variations_handler->get_woomage_default_attributes($product);
        return $product_data;
    }

    private function save_woomage_meta($product_id, $data){
        if ( isset( $data['woomageid'] ) ) {
                update_post_meta( $product_id, '_woomageid', wc_clean( $data['woomageid'] ) );
        }
        if ( isset( $data['package_size'] ) ) {
                update_post_meta( $product_id, '_wm_package_size', wc_clean( $data['package_size'] ) );
        }
        if ( isset( $data['package_unit'] ) ) {
                update_post_meta( $product_id, '_wm_package_unit', wc_clean( $data['package_unit'] ) );
        }
    }
    
    
    private function inject_woomage_meta($product_data, $product, $meta, $restname, $isint=false){
        $data = get_post_meta($product->id, $meta, true);
        if(!empty($data)) {
            $product_data[$restname] = $isint ? (int)$data : $data;
        }
        return $product_data;
    }
}
