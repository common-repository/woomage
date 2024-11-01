<?php

/**
 * Singleton class
 *
 */
final class WoomageUtil {
    /**
     * Call this method to get singleton
     *
     * @return UserFactory
     */
    public static function Instance() {
        static $inst = null;
        if ($inst === null) {
            $inst = new WoomageUtil();
        }
        return $inst;
    }
    

    /**
     * Private ctor so nobody else can instance it
     *
     */
    private function __construct() {

    }
    
    public function getWcVersion() {
        return WC()->version;
    }

    public function calculateCategoriesHash(){
        $terms = get_terms( 'product_cat', array( 'hide_empty' => false, 'orderby' => 'id' ) );
        $hash_input = '';
        foreach ( $terms as $category ) {
            $display_type = get_woocommerce_term_meta( $category->term_id, 'display_type' );
            $image_src = '';
            $thumbnail_id = absint( get_woocommerce_term_meta( $category->term_id, 'thumbnail_id', true ) );
            if ( $thumbnail_id ) {
                $image_src = wp_get_attachment_thumb_url( $thumbnail_id );
            }
            // Get woomage SKU Letters
        $woomage_skuchr = get_woocommerce_term_meta( $category->term_id, 'woomage_skuchr' );

            $hash_input .= strval($category->term_id) .
                    strval($category->parent) .
                    $category->name .
                    $category->slug .
                    $category->description.
                    ($display_type ? $display_type : 'default') .
                    ($thumbnail_id ? strval($thumbnail_id) : '0') .
                    ($image_src ? esc_url( $image_src ) : '') .
                    (isset($woomage_skuchr) ? $woomage_skuchr : '');

        }
        $hash_input = str_replace(array("\r\n", "\r"), "\n", $hash_input);
        $hash_input = htmlspecialchars_decode($hash_input);
        $hash_value = ltrim(md5($hash_input), '0'); //Remove leading zeros
        return $hash_value;
    }

    //
    // Assume server has changed categories if
    //   - local has no last sync hash (it's empty) OR
    //   - categories_lastsync differs from cat_server_hash
    //
    //   If no change at all in server nor locally, then return false
    //

    public function is_any_change($localdata, $cat_server_hash ) {
        $is_any_change = false;

        if(WoomageUtil::is_server_change($localdata, $cat_server_hash) ||
                WoomageUtil::is_woomage_change($localdata, $cat_server_hash)){
            $is_any_change = true;
        }

        return $is_any_change;
    }

    public function is_woomage_change($localdata, $cat_server_hash ) {
        $is_woomage_change = false;

        if( isset($localdata['categories_hash']) && $localdata['categories_hash'] !== $cat_server_hash ){
            $is_woomage_change = true;
        }

        return $is_woomage_change;
    }

    public function is_server_change($localdata, $cat_server_hash ) {
        $is_server_change_only = false;

        if( isset($localdata['categories_lastsync']) && $localdata['categories_lastsync'] !== $cat_server_hash ){
            $is_server_change_only = true;
        }

        return $is_server_change_only;
    }
    
    public function format_unixtime( $timestamp, $convert_to_utc = false ) {

            if ( $convert_to_utc ) {
                    $timezone = new DateTimeZone( wc_timezone_string() );
            } else {
                    $timezone = new DateTimeZone( 'UTC' );
            }

            try {

                    if ( is_numeric( $timestamp ) ) {
                            $date = new DateTime( "@{$timestamp}" );
                    } else {
                            $date = new DateTime( $timestamp, $timezone );
                    }

                    // convert to UTC by adjusting the time based on the offset of the site's timezone
                    if ( $convert_to_utc ) {
                            $date->modify( -1 * $date->getOffset() . ' seconds' );
                    }

            } catch ( Exception $e ) {

                    $date = new DateTime( '@0' );
            }

            return $date->getTimestamp();
    }
}
