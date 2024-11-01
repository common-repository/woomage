<?php
/**
 * Woomage variation handler for WooCommerce API
 *
 * Handles requests to the /woomage/products endpoint for variations
 *
 * @author      WooThemes
 * @category    API
 * @package     WooCommerce/API
 * @since       2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Woomage_Product_Variations_Handler {


    /**
     * Save woomage variations
     *
     * @since 2.2
     * @param int $id
     * @param array $data
     * @return bool
     */
    /*public function save_variations( $id, $data ) {
        global $wpdb;

        $parent_id = $id;
        // Save variations
        $isvariation =  isset( $data['woomagevariations'] ) && is_array( $data['woomagevariations'] );
        if ($isvariation == false) {
            return;
        }
        $variation_id_skus_from_woomage = array();
        $variation_ids_from_woomage = array();
        $variation_id_array = array();

        $variation_id_array = $this->get_all_variation_ids($id);

        $is_just_created = false;
        $variations = $data['woomagevariations'];
        $attributes = (array) maybe_unserialize( get_post_meta( $id, '_product_attributes', true ) );

        foreach ( $variations as $menu_order => $variation ) {
            $variation_id = isset( $variation['id'] ) ? absint( $variation['id'] ) : 0;

            // Generate a useful post title
            $variation_post_title = sprintf( __( 'Variation #%s of %s', 'woocommerce' ), $variation_id, esc_html( get_the_title( $id ) ) );

            // Add new (if no variation ID set or ID not existing yet) or Update post
            if ( ! $variation_id || !in_array($variation_id, $variation_id_array) ) {
                $post_status = ( isset( $variation['enabled'] ) && false === $variation['enabled'] ) ? 'private' : 'publish';

                $new_variation = array(
                    'post_title'   => $variation_post_title,
                    'post_content' => '',
                    'post_status'  => $post_status,
                    'post_author'  => get_current_user_id(),
                    'post_parent'  => $id,
                    'post_type'    => 'product_variation',
                    'menu_order'   => $menu_order
                );

                $variation_id = wp_insert_post( $new_variation );
                                $is_just_created = true;

                do_action( 'woocommerce_create_product_variation', $variation_id );
            } else {
                $update_variation = array( 'post_title' => $variation_post_title, 'menu_order' => $menu_order );
                if ( isset( $variation['enabled'] ) ) {
                    $post_status = ( false === $variation['enabled'] ) ? 'private' : 'publish';
                    $update_variation['post_status'] = $post_status;
                }

                $wpdb->update( $wpdb->posts, $update_variation, array( 'ID' => $variation_id ) );

                do_action( 'woocommerce_update_product_variation', $variation_id );
            }

            // Stop with we don't have a variation ID
            if ( is_wp_error( $variation_id ) ) {
                throw new WC_API_Exception( 'woocommerce_api_cannot_save_product_variation', $variation_id->get_error_message(), 400 );
            }

                        //Save all variation created or updated via Woomage REST API
                        $variation_ids_from_woomage[]=$variation_id;

            // SKU
            if ( isset( $variation['sku'] ) ) {
                $sku     = get_post_meta( $variation_id, '_sku', true );
                $new_sku = wc_clean( $variation['sku'] );
                $set_sku = '';
                if ( $new_sku !== $sku ) {
                    if ( ! empty( $new_sku ) ) {
                          $set_sku = $new_sku;
                          $variation_id_skus_from_woomage[]= array($variation_id => $set_sku);
                    }
                    update_post_meta( $variation_id, '_sku', $set_sku );
                }

            }

            // Thumbnail.
            if ( isset( $variation['image'] ) && is_array( $variation['image'] ) ) {
                $image = current( $variation['image'] );
                if ( $image && is_array( $image ) ) {
                    if ( isset( $image['position'] ) && 0 === $image['position'] ) {
                        $attachment_id = isset( $image['id'] ) ? absint( $image['id'] ) : 0;

                        if ( 0 === $attachment_id && isset( $image['src'] ) ) {
                            $upload = wc_rest_upload_image_from_url( wc_clean( $image['src'] ) );

                            if ( is_wp_error( $upload ) ) {
                                    throw new WC_REST_Exception( 'woocommerce_product_image_upload_error', $upload->get_error_message(), 400 );
                            }

                            $attachment_id = wc_rest_set_uploaded_image_as_attachment( $upload, $product->id );
                        }

                        // Set the image alt if present.
                        if ( ! empty( $image['alt'] ) ) {
                            update_post_meta( $attachment_id, '_wp_attachment_image_alt', wc_clean( $image['alt'] ) );
                        }

                        // Set the image name if present.
                        if ( ! empty( $image['name'] ) ) {
                            wp_update_post( array( 'ID' => $attachment_id, 'post_title' => $image['name'] ) );
                        }

                        update_post_meta( $variation_id, '_thumbnail_id', $attachment_id );
                    }
                } else {
                    delete_post_meta( $variation_id, '_thumbnail_id' );
                }
            }

            // Virtual variation
            if ( isset( $variation['virtual'] ) ) {
                $is_virtual = ( true === $variation['virtual'] ) ? 'yes' : 'no';
                update_post_meta( $variation_id, '_virtual', $is_virtual );
            }

            // Downloadable variation
            if ( isset( $variation['downloadable'] ) ) {
                $is_downloadable = ( true === $variation['downloadable'] ) ? 'yes' : 'no';
                update_post_meta( $variation_id, '_downloadable', $is_downloadable );
            } else {
                $is_downloadable = get_post_meta( $variation_id, '_downloadable', true );
            }

            // Shipping data
            $this->save_product_shipping_data( $variation_id, $variation );

            // Stock handling
            if ( isset( $variation['managing_stock'] ) ) {
                $managing_stock = ( true === $variation['managing_stock'] ) ? 'yes' : 'no';
                update_post_meta( $variation_id, '_manage_stock', $managing_stock );
            } else {
                $managing_stock = get_post_meta( $variation_id, '_manage_stock', true );
            }

            // Only update stock status to user setting if changed by the user, but do so before looking at stock levels at variation level
            
            if ( isset( $variation['in_stock'] ) ) {
                $stock_status = ( true === $variation['in_stock'] ) ? 'instock' : 'outofstock';
            } else {
                $stock_status = get_post_meta( $variation_id, '_stock_status', true );
            }

            wc_update_product_stock_status( $variation_id, '' === $stock_status ? 'instock' : $stock_status );


            if ( 'yes' === $managing_stock ) {
                if ( isset( $variation['backorders'] ) ) {
                    $backorders = $variation['backorders'];
                } else {
                    $backorders = get_post_meta( $variation_id, '_backorders', true );
                }

                update_post_meta( $variation_id, '_backorders', '' === $backorders ? 'no' : $backorders );

                if ( isset( $variation['stock_quantity'] ) ) {
                    wc_update_product_stock( $variation_id, wc_stock_amount( $variation['stock_quantity'] ) );
                } 
                if ( isset( $variation['inventory_delta'] ) ) {
                    $stock_quantity  = wc_stock_amount( get_post_meta( $variation_id, '_stock', true ) );
                    $stock_quantity += wc_stock_amount( $variation['inventory_delta'] );

                    wc_update_product_stock( $variation_id, wc_stock_amount( $stock_quantity ) );
                }
            } else {
                delete_post_meta( $variation_id, '_backorders' );
                delete_post_meta( $variation_id, '_stock' );
            }

            // Regular Price
            if ( isset( $variation['regular_price'] ) ) {
                $regular_price = ( '' === $variation['regular_price'] ) ? '' : wc_format_decimal( $variation['regular_price'] );
                update_post_meta( $variation_id, '_regular_price', $regular_price );
            } else {
                $regular_price = get_post_meta( $variation_id, '_regular_price', true );
            }

            // Sale Price
            if ( isset( $variation['sale_price'] ) ) {
                $sale_price = ( '' === $variation['sale_price'] ) ? '' : wc_format_decimal( $variation['sale_price'] );
                update_post_meta( $variation_id, '_sale_price', $sale_price );
            } else {
                $sale_price = get_post_meta( $variation_id, '_sale_price', true );
            }

            $date_from = isset( $variation['sale_price_dates_from'] ) ? strtotime( $variation['sale_price_dates_from'] ) : get_post_meta( $variation_id, '_sale_price_dates_from', true );
            $date_to   = isset( $variation['sale_price_dates_to'] ) ? strtotime( $variation['sale_price_dates_to'] ) : get_post_meta( $variation_id, '_sale_price_dates_to', true );

            // Save Dates
            if ( $date_from ) {
                update_post_meta( $variation_id, '_sale_price_dates_from', $date_from );
            } else {
                update_post_meta( $variation_id, '_sale_price_dates_from', '' );
            }

            if ( $date_to ) {
                update_post_meta( $variation_id, '_sale_price_dates_to', $date_to );
            } else {
                update_post_meta( $variation_id, '_sale_price_dates_to', '' );
            }

            if ( $date_to && ! $date_from ) {
                update_post_meta( $variation_id, '_sale_price_dates_from', strtotime( 'NOW', current_time( 'timestamp' ) ) );
            }

            // Update price if on sale
            if ( '' != $sale_price && '' == $date_to && '' == $date_from ) {
                update_post_meta( $variation_id, '_price', $sale_price );
            } else {
                update_post_meta( $variation_id, '_price', $regular_price );
            }

            if ( '' != $sale_price && $date_from && $date_from < strtotime( 'NOW', current_time( 'timestamp' ) ) ) {
                update_post_meta( $variation_id, '_price', $sale_price );
            }

            if ( $date_to && $date_to < strtotime( 'NOW', current_time( 'timestamp' ) ) ) {
                update_post_meta( $variation_id, '_price', $regular_price );
                update_post_meta( $variation_id, '_sale_price_dates_from', '' );
                update_post_meta( $variation_id, '_sale_price_dates_to', '' );
            }

            // Tax class
            if ( isset( $variation['tax_class'] ) ) {
                if ( $variation['tax_class'] !== 'parent' ) {
                    update_post_meta( $variation_id, '_tax_class', wc_clean( $variation['tax_class'] ) );
                } else {
                    delete_post_meta( $variation_id, '_tax_class' );
                }
            }

            // Downloads
            if ( 'yes' == $is_downloadable ) {
                // Downloadable files
                if ( isset( $variation['downloads'] ) && is_array( $variation['downloads'] ) ) {
                    $this->save_downloadable_files( $id, $variation['downloads'], $variation_id );
                }

                // Download limit
                if ( isset( $variation['download_limit'] ) ) {
                    $download_limit = absint( $variation['download_limit'] );
                    update_post_meta( $variation_id, '_download_limit', ( ! $download_limit ) ? '' : $download_limit );
                }

                // Download expiry
                if ( isset( $variation['download_expiry'] ) ) {
                    $download_expiry = absint( $variation['download_expiry'] );
                    update_post_meta( $variation_id, '_download_expiry', ( ! $download_expiry ) ? '' : $download_expiry );
                }
            } else {
                update_post_meta( $variation_id, '_download_limit', '' );
                update_post_meta( $variation_id, '_download_expiry', '' );
                update_post_meta( $variation_id, '_downloadable_files', '' );
            }

            // Update taxonomies.
            if ( isset( $variation['attributes'] ) ) {
                $updated_attribute_keys = array();

                foreach ( $variation['attributes'] as $attribute ) {
                    $attribute_id   = 0;
                    $attribute_name = '';

                    // Check ID for global attributes or name for product attributes.
                    if ( ! empty( $attribute['id'] ) ) {
                        $attribute_id   = absint( $attribute['id'] );
                        $attribute_name = $this->wc_attribute_taxonomy_name_by_id( $attribute_id );
                    } elseif ( ! empty( $attribute['name'] ) ) {
                        $attribute_name = sanitize_title( $attribute['name'] );
                    }

                    if ( ! $attribute_id && ! $attribute_name ) {
                        continue;
                    }

                    if ( isset( $attributes[ $attribute_name ] ) ) {
                        $_attribute = $attributes[ $attribute_name ];
                    }

                    if ( isset( $_attribute['is_variation'] ) && $_attribute['is_variation'] ) {
                        $_attribute_key           = 'attribute_' . sanitize_title( $_attribute['name'] );
                        $updated_attribute_keys[] = $_attribute_key;

                        if ( isset( $_attribute['is_taxonomy'] ) && $_attribute['is_taxonomy'] ) {
                                // Don't use wc_clean as it destroys sanitized characters
                                $_attribute_value = isset( $attribute['option'] ) ? sanitize_title( stripslashes( $attribute['option'] ) ) : '';
                        } else {
                                $_attribute_value = isset( $attribute['option'] ) ? wc_clean( stripslashes( $attribute['option'] ) ) : '';
                        }

                        update_post_meta( $variation_id, $_attribute_key, $_attribute_value );
                    }
                }

                // Remove old taxonomies attributes so data is kept up to date - first get attribute key names.
                $delete_attribute_keys = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE 'attribute_%%' AND meta_key NOT IN ( '" . implode( "','", $updated_attribute_keys ) . "' ) AND post_id = %d;", $variation_id ) );

                foreach ( $delete_attribute_keys as $key ) {
                    delete_post_meta( $variation_id, $key );
                }
            }
            
            if ( isset( $variation['package_size'] ) ) {
                update_post_meta( $variation_id, '_wm_package_size', $variation['package_size']);
            } else {
                delete_post_meta( $variation_id, '_wm_package_size' );
            }

            if ( isset( $variation['package_name'] ) ) {
                update_post_meta( $variation_id, '_wm_package_name', $variation['package_name']);
            } else {
                delete_post_meta( $variation_id, '_wm_package_name' );
            }

            do_action( 'woocommerce_api_save_product_variation', $variation_id, $menu_order, $variation );
        }

        // Update parent if variable so price sorting works and stays in sync with the cheapest child
        WC_Product_Variable::sync( $id );

        // Update default attributes options setting
        if ( isset( $data['default_attribute'] ) ) {
            $data['default_attributes'] = $data['default_attribute'];
        }

        if ( isset( $data['default_attributes'] ) && is_array( $data['default_attributes'] ) ) {
            $default_attributes = array();

            foreach ( $data['default_attributes'] as $default_attr_key => $default_attr ) {
                if ( ! isset( $default_attr['name'] ) ) {
                    continue;
                }

                                //Present slug only if you want to get global attribute
                if ( isset( $default_attr['slug'] ) ) {
                    $taxonomy = $this->get_attribute_taxonomy_by_slug( $default_attr['slug'] );
                }
                //woomage -start
                if ( ! $taxonomy ) {
                        $taxonomy = sanitize_title( $default_attr['name'] );
                }
                //woomage -end

                if ( isset( $attributes[ $taxonomy ] ) ) {
                    $_attribute = $attributes[ $taxonomy ];

                    if ( $_attribute['is_variation'] ) {
                        $value = '';

                        if ( isset( $default_attr['option'] ) ) {
                            if ( $_attribute['is_taxonomy'] ) {
                                // Don't use wc_clean as it destroys sanitized characters
                                $value = sanitize_title( trim( stripslashes( $default_attr['option'] ) ) );
                            } else {
                                $value = wc_clean( trim( stripslashes( $default_attr['option'] ) ) );
                            }
                        }

                        if ( $value ) {
                            $default_attributes[ $taxonomy ] = $value;
                        }
                    }
                }
            }
            update_post_meta( $id, '_default_attributes', $default_attributes );
        }

        //Woomage special: delete all variations which did not exist in REST API message
        $to_be_removed_ids = array_diff($variation_id_array, $variation_ids_from_woomage);
        foreach ( $to_be_removed_ids as $child_id ) {
            wp_delete_post( $child_id, true );
            wc_delete_product_transients( $parent_id );
        }

        return true;
    }

        private function get_all_variation_ids($parent_id){
            $args = array(
                    'post_parent' => $parent_id,
                    'post_type'   => 'product_variation',
                    'orderby'     => 'menu_order',
                    'order'       => 'ASC',
                    'fields'      => 'ids',
                    'post_status' => array('publish', 'private'),
                    'numberposts' => -1
            );
            $children_ids = get_posts( $args );
            return $children_ids;
        }


    private function wc_attribute_taxonomy_name_by_id( $attribute_id ) {
	global $wpdb;

	$attribute_name = $wpdb->get_var( $wpdb->prepare( "
		SELECT attribute_name
		FROM {$wpdb->prefix}woocommerce_attribute_taxonomies
		WHERE attribute_id = %d
	", $attribute_id ) );

	if ( $attribute_name && ! is_wp_error( $attribute_name ) ) {
		return wc_attribute_taxonomy_name( $attribute_name );
	}

	return '';
    }*/
        
    /**
     * Save product shipping data
     *
     * @since 2.2
     * @param int $id
     * @param array $data
     */
    /*private function save_product_shipping_data( $product_id, $data ) {

        if ( isset( $data['virtual'] ) && true === $data['virtual'] ) {
            update_post_meta( $product_id, '_weight', '' );
            update_post_meta( $product_id, '_length', '' );
            update_post_meta( $product_id, '_width', '' );
            update_post_meta( $product_id, '_height', '' );
        } else {
            if ( isset( $data['weight'] ) ) {
                    update_post_meta( $product_id, '_weight', '' === $data['weight'] ? '' : wc_format_decimal( $data['weight'] ) );
            }

            // Height.
            if ( isset( $data['dimensions']['height'] ) ) {
                    update_post_meta( $product_id, '_height', '' === $data['dimensions']['height'] ? '' : wc_format_decimal( $data['dimensions']['height'] ) );
            }

            // Width.
            if ( isset( $data['dimensions']['width'] ) ) {
                    update_post_meta( $product_id, '_width', '' === $data['dimensions']['width'] ? '' : wc_format_decimal( $data['dimensions']['width'] ) );
            }

            // Length.
            if ( isset( $data['dimensions']['length'] ) ) {
                    update_post_meta( $product_id, '_length', '' === $data['dimensions']['length'] ? '' : wc_format_decimal( $data['dimensions']['length'] ) );
            }
        }

        // Shipping class
        if ( isset( $data['shipping_class'] ) ) {
            wp_set_object_terms( $product_id, wc_clean( $data['shipping_class'] ), 'product_shipping_class' );
        }
    }*/

    /**
     * Save downloadable files
     *
     * @since 2.2
     * @param int $product_id
     * @param array $downloads
     * @param int $variation_id
     */
    /*private function save_downloadable_files( $product_id, $downloads, $variation_id = 0 ) {
        $files = array();

        // File paths will be stored in an array keyed off md5(file path)
        foreach ( $downloads as $key => $file ) {
            if ( isset( $file['url'] ) ) {
                $file['file'] = $file['url'];
            }

            if ( ! isset( $file['file'] ) ) {
                continue;
            }

            $file_name = isset( $file['name'] ) ? wc_clean( $file['name'] ) : '';

            if ( 0 === strpos( $file['file'], 'http' ) ) {
                $file_url = esc_url_raw( $file['file'] );
            } else {
                $file_url = wc_clean( $file['file'] );
            }

            $files[ md5( $file_url ) ] = array(
                'name' => $file_name,
                'file' => $file_url
            );
        }

        // Grant permission to any newly added files on any existing orders for this product prior to saving
        do_action( 'woocommerce_process_product_file_download_paths', $product_id, $variation_id, $files );

        $id = ( 0 === $variation_id ) ? $product_id : $variation_id;
        update_post_meta( $id, '_downloadable_files', $files );
    }*/

    /**
     * Get attribute taxonomy by slug.
     *
     * @since 2.2
     * @param string $slug
     * @return string|null
     */
    /*private function get_attribute_taxonomy_by_slug( $slug ) {
        $taxonomy = null;
        $attribute_taxonomies = wc_get_attribute_taxonomies();

        foreach ( $attribute_taxonomies as $key => $tax ) {
            if ( $slug == $tax->attribute_name ) {
                $taxonomy = 'pa_' . $tax->attribute_name;

                break;
            }
        }

        return $taxonomy;
    }*/



    /**
     * Upload image from URL
     *
     * @since 2.2
     * @param string $image_url
     * @return int|WP_Error attachment id
     */
    /*public function upload_product_image( $image_url ) {
        $file_name         = basename( current( explode( '?', $image_url ) ) );
        $wp_filetype     = wp_check_filetype( $file_name, null );
        $parsed_url     = @parse_url( $image_url );

        // Check parsed URL
        if ( ! $parsed_url || ! is_array( $parsed_url ) ) {
            throw new WC_API_Exception( 'woocommerce_api_invalid_product_image', sprintf( __( 'Invalid URL %s', 'woocommerce' ), $image_url ), 400 );
        }

        // Ensure url is valid
        $image_url = str_replace( ' ', '%20', $image_url );

        // Get the file
        $response = wp_safe_remote_get( $image_url, array(
            'timeout' => 10
        ) );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            throw new WC_API_Exception( 'woocommerce_api_invalid_remote_product_image', sprintf( __( 'Error getting remote image %s', 'woocommerce' ), $image_url ), 400 );
        }

        // Ensure we have a file name and type
        if ( ! $wp_filetype['type'] ) {
            $headers = wp_remote_retrieve_headers( $response );
            if ( isset( $headers['content-disposition'] ) && strstr( $headers['content-disposition'], 'filename=' ) ) {
                $disposition = end( explode( 'filename=', $headers['content-disposition'] ) );
                $disposition = sanitize_file_name( $disposition );
                $file_name   = $disposition;
            } elseif ( isset( $headers['content-type'] ) && strstr( $headers['content-type'], 'image/' ) ) {
                $file_name = 'image.' . str_replace( 'image/', '', $headers['content-type'] );
            }
            unset( $headers );
        }

        // Upload the file
        $upload = wp_upload_bits( $file_name, '', wp_remote_retrieve_body( $response ) );

        if ( $upload['error'] ) {
            throw new WC_API_Exception( 'woocommerce_api_product_image_upload_error', $upload['error'], 400 );
        }

        // Get filesize
        $filesize = filesize( $upload['file'] );

        if ( 0 == $filesize ) {
            @unlink( $upload['file'] );
            unset( $upload );
            throw new WC_API_Exception( 'woocommerce_api_product_image_upload_file_error', __( 'Zero size file downloaded', 'woocommerce' ), 400 );
        }

        unset( $response );

        return $upload;
    }*/

    /**
     * Get product image as attachment
     *
     * @since 2.2
     * @param integer $upload
     * @param int $id
     * @return int
     */
    /*protected function set_product_image_as_attachment( $upload, $id ) {
        $info    = wp_check_filetype( $upload['file'] );
        $title   = '';
        $content = '';

        if ( $image_meta = @wp_read_image_metadata( $upload['file'] ) ) {
            if ( trim( $image_meta['title'] ) && ! is_numeric( sanitize_title( $image_meta['title'] ) ) ) {
                $title = $image_meta['title'];
            }
            if ( trim( $image_meta['caption'] ) ) {
                $content = $image_meta['caption'];
            }
        }

        $attachment = array(
            'post_mime_type' => $info['type'],
            'guid'           => $upload['url'],
            'post_parent'    => $id,
            'post_title'     => $title,
            'post_content'   => $content
        );

        $attachment_id = wp_insert_attachment( $attachment, $upload['file'], $id );
        if ( ! is_wp_error( $attachment_id ) ) {
            wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
        }

        return $attachment_id;
    }*/

    public function get_woomage_default_attributes($parent_product){
        $attributes = array();
        $default_attributes = maybe_unserialize(get_post_meta($parent_product->id, '_default_attributes', true));
        if(is_array($default_attributes)){
            // variation attributes
            foreach ( $default_attributes as $attribute_name => $attribute ) {
                    // taxonomy-based attributes are prefixed with `pa_`, otherwise simply `attribute_`
                    $attributes[] = array(
                            'name'   => wc_attribute_label( str_replace( 'attribute_', '', $attribute_name ) ),
                            'slug'   => str_replace( 'attribute_', '', str_replace( 'pa_', '', $attribute_name ) ),
                            'option' => wc_attribute_label($attribute),
                    );
            }
        }
        return $attributes;
    }

}
