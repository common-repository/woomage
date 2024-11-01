<?php
/**
 * Woomage WooCommerce API Categories Class
 *
 * Handles requests to the /categories endpoint
 *
 * @author      Woomage
 * @category    WooCommerce REST API extension
 * @package     WooMage
 * @since       2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

include_once( dirname( __FILE__ ) . '/class-woomage-util.php' );

class Woomage_WC_API_Categories extends WC_API_Resource {


    /** @var string $base the route base */
    protected $base = '/woomage/products';


    /**
     * Register the routes for this class
     *
     * @since 2.1
     * @param array $routes
     * @return array
     */
    public function register_routes( $routes ) {


                # GET /products/categories
        $routes[ $this->base . '/categories' ] = array(
            array( array( $this, 'get_product_categories' ), WC_API_Server::READABLE ),
                        array( array( $this, 'create_product_category' ), WC_API_SERVER::CREATABLE | WC_API_Server::ACCEPT_DATA ),
                        array( array( $this, 'delete_product_categories' ), WC_API_Server::DELETABLE ),
        );

                # DELETE /categories
        //$routes[ $this->base . '/categories' ] = array(
        //);
                # POST /categories
        //$routes[ $this->base . '/categories' ] = array(
        //    array( array( $this, 'create_product_category' ), WC_API_SERVER::CREATABLE | WC_API_Server::ACCEPT_DATA ),
        //);

                # GET|PUT|DELETE /categories/<id>
        $routes[ $this->base . '/categories/(?P<id>\d+)' ] = array(
                        array( array( $this, 'get_product_category' ), WC_API_Server::READABLE ),
            array( array( $this, 'edit_product_category' ), WC_API_Server::EDITABLE | WC_API_Server::ACCEPT_DATA ),
            array( array( $this, 'delete_product_category' ), WC_API_Server::DELETABLE ),
        );

                # POST /categories/bulk
        $routes[ $this->base . '/categories/bulk' ] = array(
            array( array( $this, 'create_delete_or_update_categories' ), WC_API_SERVER::CREATABLE | WC_API_Server::EDITABLE | WC_API_Server::ACCEPT_DATA ),
        );


        return $routes;
    }


        /**
     * Create or update product categories when Woomage only changed them
     *
     * @since 2.2
     * @param array $categories
         *    posted data = categories in format of categories tree or flat list with parent ids
         * @param $parent_id - parent ID in the tree
         * @param $index - for reordering categories
     * @return current index
     */

        public function loop_category_update_when_woomageapp_change_only($categories, $parent_id, $index = 0){
            $categories_to_be_deleted = array();

            foreach ($categories as $category) {
                $id = $category['id'];
                $action_create = false;

                if(isset($id)){
                    //Check if ID is still the exist, if not, must create new
                    $id_term = get_term( $id, 'product_cat' );
                    if ( is_wp_error( $id_term )) {
                        throw new WC_API_Exception( 'woocommerce_api_invalid_product_category_id', __( 'A product category ID is invalid', 'woocommerce' ), 404 );
                    }
                    if(is_null( $id_term )){
                        $action_create = true;
                    }
                    //else  update per id and data

                } else {
                    //If no ID, we must create new
                    $action_create = true;

                    //Just in case, something went badly wrong (bug etc.), we check if this can be created at all
                    //this branch checks existance based on name&slug and parent info
                        $name_term = term_exists( $category['name'], 'product_cat', $parent_id);
                        if ($name_term === 0 || $name_term === null) {
                            //category does not exist, must create new
                            $action_create = true;
                        }
                        else {
                            //name exist, check if  both slug & name found under same parent
                            $term = get_term_by('name', $category['name'], 'product_cat');
                            if($term && $term->slug === $category['slug'] && $term->parent === $parent_id){
                               $id = intval($term->term_id);
                            } else {
                                //only name exist, update category by ID determined by name
                               $id = intval($name_term['term_id']);
                            }
                        }
                }

                $term = null;
                if( $action_create == true ){ //If Id given, but it does not exist, create category
                    $category['parent'] = $parent_id;
                    $created_cat = $this->create_product_category_to_db( $category );
                    if ( is_wp_error( $created_cat ) || $created_cat instanceof WC_API_Exception ) {
                            throw new WC_API_Exception( 'woocommerce_api_cannot_create_product_category', $created_cat->get_error_message(), 400 );
                    }
                    $id = intval($created_cat['term_id']);
                    if ( $id == 0 ) {
                            throw new WC_API_Exception( 'woocommerce_api_cannot_create_product_category', 'Category could not be created properly due to database might be corrupted', 400 );
                    }

                }
                else { //update category
                    $category['parent'] = $parent_id;
                    $term = $this->edit_product_category_to_db($id, $category);

                }

                //Set order for category - taxonomy is 'product_cat'
                update_woocommerce_term_meta( $id, 'order', $index );
                $index++;

                if(isset($category['children'])){
                    $index = $this->loop_category_update_when_woomageapp_change_only($category['children'], $id, $index);
                }
                clean_term_cache( $id, 'product_cat' );

            }
            return $index;
        }

        /**
     * Create or update product categories
     *
     * @since 2.2
     * @param array $categories
         *    posted data = categories in format of categories tree or flat list with parent ids
         * @param $parent_id - parent ID in the tree
         * @param $index - for reordering categories
     * @return array $categories_to_be_deleted
     */

        public function loop_category_update($categories, $parent_id, $index = 0){
            $categories_to_be_deleted = array();

            foreach ($categories as $category) {
                $id = $category['id'];
                $action_create = false;
                if(isset($id) && isset($category['parent'])){ //This is flat update - not typically used by woomage
                    $id_term = get_term( $id, 'product_cat' );
                    if ( is_wp_error( $id_term )) {
                        throw new WC_API_Exception( 'woocommerce_api_invalid_product_category_id', __( 'A product category ID is invalid', 'woocommerce' ), 404 );
                    }
                    if(is_null( $id_term )){ //If Id given, but it does not exist, create category
                        $created_cat = $this->create_product_category_to_db( $category );
                        if ( is_wp_error( $created_cat ) || $created_cat instanceof WC_API_Exception ) {
                                throw new WC_API_Exception( 'woocommerce_api_cannot_create_product_category', $created_cat->get_error_message(), 400 );
                        }
                    } else {
                        $this->edit_product_category_to_db($id, $category);
                    }
                }
                else {
                    if(isset($id)){
                        //Check if ID is still the exist, if not, must create new
                        $id_term = get_term( $id, 'product_cat' );
                        if ( is_wp_error( $id_term )) {
                            throw new WC_API_Exception( 'woocommerce_api_invalid_product_category_id', __( 'A product category ID is invalid', 'woocommerce' ), 404 );
                        }
                        if(is_null( $id_term )){
                            //If Id given, but it does not exist, create category
                            //But only in case of both slug & name are NOT found
                            //If name & slug exist, we reuse it since we assume someone has deleted original with ID
                            $term = get_term_by('name', $category['name'], 'product_cat');
                            if($term && $term->slug === $category['slug']){
                               $id = intval($term->term_id);
                            }
                            $action_create = true;
                        }
                        //else  update per id and data

                    } else {

                        //this branch checks existance based on name&slug and parent info
                        $name_term = term_exists( $category['name'], 'product_cat', $parent_id);
                        if ($name_term === 0 || $name_term === null) {
                            //category does not exist, must create new
                            $action_create = true;
                        }
                        else {
                            //name exist, check if  both slug & name found
                            $term = get_term_by('name', $category['name'], 'product_cat');
                            if($term && $term->slug === $category['slug']){
                               $id = intval($term->term_id);
                            } else {
                                //only name exist, update category by ID determined by name
                               $id = intval($name_term['term_id']);
                            }

                        }

                    }

                    $term = null;
                    if(isset($category['delete']) && $category['delete'] === true){ //If delete true, delete category
                        $categories_to_be_deleted[]= $id;
                    } elseif( $action_create == true ){ //If Id given, but it does not exist, create category
                        $category['parent'] = $parent_id;
                        $created_cat = $this->create_product_category_to_db( $category );
                        if ( is_wp_error( $created_cat ) || $created_cat instanceof WC_API_Exception ) {
                                throw new WC_API_Exception( 'woocommerce_api_cannot_create_product_category', $created_cat->get_error_message(), 400 );
                        }
                        $id = intval($created_cat['term_id']);
                        if ( $id == 0 ) {
                                throw new WC_API_Exception( 'woocommerce_api_cannot_create_product_category', 'Category could not be created properly due to database might be corrupted', 400 );
                        }

                    }
                    else { //update category
                        $category['parent'] = $parent_id;
                        $term = $this->edit_product_category_to_db($id, $category);

                    }
                    //Set order for category - taxonomy is 'product_cat'
                    update_woocommerce_term_meta( $id, 'order', $index );
                    $index++;

                    if(isset($category['children'])){
                        $deleted_children = $this->loop_category_update($category['children'], $id, $index);
                        $categories_to_be_deleted = array_merge($deleted_children, $categories_to_be_deleted);
                    }
                    clean_term_cache( $id, 'product_cat' );

                }
            }
            return $categories_to_be_deleted;
        }

    public function create_delete_or_update_categories($data){
        try{
                    $catdata = isset( $data['product_categories'] ) ? $data['product_categories'] : array();

                    // Permissions check
                    if ( ! current_user_can( 'manage_product_terms' ) ) {
                            throw new WC_API_Exception( 'woocommerce_api_user_cannot_read_product_categories', __( 'You do not have permission to read product categories', 'woocommerce' ), 401 );
                    }

                    //check out +http://wordpress.stackexchange.com/questions/24498/wp-insert-term-parent-child-problem+
                    delete_option("product_cat_children");

                    $all_product_categories = array();
                    $server_change = array();
                    $server_hash = WoomageUtil::calculateCategoriesHash();
                    $is_any_change = WoomageUtil::is_any_change($data, $server_hash);
                    $force_update = $data['force'];

                    if($is_any_change === true || (isset($force_update) && $force_update === true)){
                        $has_server_side_changed = WoomageUtil::is_server_change($data, $server_hash);
                        //If no server change, it's only woomage side changed
                        if(! $has_server_side_changed || (isset($force_update) && $force_update === true)){
                            if(isset($data['delete_categories']) && ! empty($data['delete_categories']) ){
                                $this->delete_product_categories($data['delete_categories']);
                            }
                            $this->loop_category_update_when_woomageapp_change_only($catdata, 0);
                            $server_hash = WoomageUtil::calculateCategoriesHash();
                        }
                        else {
                            $server_change = array('server_change' => true);
                        }
                        $all_product_categories = $this->get_product_categories();
                    } else {
                        $server_change = array('server_change' => false);
                    }

                    return array_merge(array('hash' => $server_hash),
                            $server_change,
                            $all_product_categories);

                } catch ( WC_API_Exception $e ) {
            return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
        }
    }


        /**
     * Create a new product category
     *
     * @since 2.2
     * @param array $data posted data
     * @return array
     */
    public function create_product_category( $data ) {
        $id = 0;

        try {
            $data = isset( $data['product_category'] ) ? $data['product_category'] : array();

            // Permissions check
            if ( ! current_user_can( 'manage_product_terms' ) ) {
                throw new WC_API_Exception( 'woocommerce_api_user_cannot_read_product_categories', __( 'You do not have permission to read product categories', 'woocommerce' ), 401 );
            }

            $new_cat_term = $this->create_product_category_to_db($data);

            $this->server->send_status( 201 );

            return $this->get_product_category( $new_cat_term['term_id'] );
        } catch ( WC_API_Exception $e ) {

            return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
        }
    }

        /**
     * Create a new product category object
     *
     */
    public function create_product_category_to_db( $data ) {
        $id = 0;
        try {
            //Clear cache
            delete_option("product_cat_children");

            $term_name = wc_clean( $data['name'] );
            $slug = $data['slug'];
            $new_product_category = array(
                'slug'  => $slug,
                'parent'    => $data['parent'],
                'description' => ( isset( $data['description'] ) ? $data['description'] : '' )
            );

            $new_cat_term = wp_insert_term( $term_name, 'product_cat', $new_product_category );

            if ( is_wp_error( $new_cat_term ) ) {
                throw new WC_API_Exception( 'woocommerce_api_cannot_create_product_category', $new_cat_term->get_error_message(), 400 );
            }
            $id = absint($new_cat_term['term_id']);
            $display_type = isset($data['display']) ? $data['display'] : 'default';
                        // Update category display type
            add_woocommerce_term_meta( $id, 'display_type', $display_type);
            add_woocommerce_term_meta( $id, 'woomageid', isset($data['woomageid'])?$data['woomageid']:0);
            add_woocommerce_term_meta( $id, 'woomage_skuchr',  isset($data['woomage_skuch'])?$data['woomage_skuch']:'');

            if ( isset( $data['image'] ) ) {
                $this->set_product_category_image($data['image']);
            }
            return $new_cat_term;

        } catch ( WC_API_Exception $e ) {
            return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
        }
    }
    /**
     * Edit an category
     *
         *
     * @since 2.2
     * @param int $id the order ID
     * @param array $data
     * @return array
     */
    public function edit_product_category( $id, $data ) {

        $data = isset( $data['product_category'] ) ? $data['product_category'] : array();

        try {
            $id = absint( $id );

            // Validate ID
            if ( empty( $id ) ) {
                throw new WC_API_Exception( 'woocommerce_api_invalid_product_category_id', __( 'Invalid product category ID', 'woocommerce' ), 400 );
            }

            // Permissions check
            if ( ! current_user_can( 'manage_product_terms' ) ) {
                throw new WC_API_Exception( 'woocommerce_api_user_cannot_edit_product_categories', __( 'You do not have permission to edit product categories', 'woocommerce' ), 401 );
            }

            $this->edit_product_category_to_db($id, $data);
            return $this->get_product_category($id);

        } catch ( WC_API_Exception $e ) {

            return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
        }
    }

        /**
     * Edit an category
     *
         *
     * @since 2.2
     * @param int $id the category term ID
     * @param array $data
     * @return array
     */
    public function edit_product_category_to_db( $id, $data ) {

        try {
            $id = absint( $id );

            $term = get_term( $id, 'product_cat' );

            if ( is_wp_error( $term ) || is_null( $term ) ) {
                throw new WC_API_Exception( 'woocommerce_api_invalid_product_category_id', __( 'A product category with the provided ID could not be found', 'woocommerce' ), 404 );
            }

            $new_terms = array(
                'name'        => $data['name'],
                'slug'        => $data['slug'],
                'parent'      => $data['parent'],
                'description' => $data['description'],
            );

            $display_type = isset($data['display']) ? $data['display'] : 'default';
                        // Update category display type
            update_woocommerce_term_meta( $id, 'display_type',  $display_type);

            update_woocommerce_term_meta( $id, 'woomageid',  isset($data['woomageid'])?$data['woomageid']:0);

            update_woocommerce_term_meta( $id, 'woomage_skuchr',  isset($data['woomage_skuch'])?$data['woomage_skuch']:'');

            if ( isset( $data['image'] ) ) {
                $this->set_product_category_image($data['image']);
            }

            wp_update_term($id, 'product_cat', $new_terms);

            return $term;

        } catch ( WC_API_Exception $e ) {

            return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
        }
    }

  	public function set_product_category_image($image){

          $thumbnail_id = isset( $image['id'] ) ? absint( $image['id'] ) : 0;

          if ( $thumbnail_id == 0 && isset($image['src']) && strcmp($image['src'], '') !== 0 ) {
              $upload = $this->upload_image_from_url( wc_clean( $image['src'] ) );
              if ( is_wp_error( $upload ) ) {
                      throw new WC_API_Exception( 'woocommerce_api_cannot_upload_product_category_image', $upload->get_error_message(), 400 );
              }
              $thumbnail_id = $this->set_image_as_attachment($upload);

          } elseif($thumbnail_id > 0){

              //Just check image referred by ID exists
              $image = wp_get_attachment_thumb_url( $thumbnail_id );
              if ( is_wp_error( $image ) ) {
                       throw new WC_API_Exception( 'woocommerce_api_product_category_image_id_does_no_exist', $image->get_error_message(), 400 );
              }
          }
          // In case client supplies invalid image or wants to unset category image.
          if ( ! wp_attachment_is_image( $thumbnail_id ) ) {
                  $thumbnail_id = '';
          }
          update_woocommerce_term_meta( $term->term_id, 'thumbnail_id', absint( $thumbnail_id ) );

    }

        /**
     * Get a listing of product categories
     *
     * @since 2.2
     * @param string|null $fields fields to limit response to
     * @return array
     */
    public function get_product_categories( $fields = null) {
        try {
            // Permissions check
            if ( ! current_user_can( 'manage_product_terms' ) ) {
                throw new WC_API_Exception( 'woocommerce_api_user_cannot_read_product_categories', __( 'You do not have permission to read product categories', 'woocommerce' ), 401 );
            }

            $product_categories = array();

            $terms = get_terms( 'product_cat', array( 'hide_empty' => false, 'fields' => 'ids' ) );//get_categories(array( 'taxonomy'=> 'product_cat', 'hide_empty' => false, 'fields' => 'ids' ));//

            foreach ( $terms as $term_id ) {
                $product_categories[] = current( $this->get_product_category( $term_id, $fields ) );
            }

            return array( 'product_categories' => apply_filters( 'woocommerce_api_product_categories_response', $product_categories, $terms, $fields, $this ) );
        } catch ( WC_API_Exception $e ) {
            return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
        }
    }

    /**
     * Get the product category for the given ID
     *
     * @since 2.2
     * @param string $id product category term ID
     * @param string|null $fields fields to limit response to
     * @return array
     */
    public function get_product_category( $id, $fields = null ) {
        try {
            $id = absint( $id );

            // Validate ID
            if ( empty( $id ) ) {
                throw new WC_API_Exception( 'woocommerce_api_invalid_product_category_id', __( 'Invalid product category ID', 'woocommerce' ), 400 );
            }

            // Permissions check
            if ( ! current_user_can( 'manage_product_terms' ) ) {
                throw new WC_API_Exception( 'woocommerce_api_user_cannot_read_product_categories', __( 'You do not have permission to read product categories', 'woocommerce' ), 401 );
            }

            $term = get_term( $id, 'product_cat' );

            if ( is_wp_error( $term ) || is_null( $term ) ) {
                throw new WC_API_Exception( 'woocommerce_api_invalid_product_category_id', __( 'A product category with the provided ID could not be found', 'woocommerce' ), 404 );
            }

                        // Get category display type
            $display_type = get_woocommerce_term_meta( $id, 'display_type' );

                        // Get woomage ID
            $woomage_id = get_woocommerce_term_meta( $id, 'woomageid' );

                        // Get woomage SKU Letters
            $woomage_skuchr = get_woocommerce_term_meta( $id, 'woomage_skuchr' );

            $image = '';
            $thumbnail_id = absint( get_woocommerce_term_meta( $id, 'thumbnail_id', true ) );
            if ( $thumbnail_id  ) {
                    $image = wp_get_attachment_thumb_url( $thumbnail_id );
            }

            $product_category = array(
                'id'          => intval( $term->term_id ),
                'woomageid'   => (isset($woomage_id) && is_numeric($woomage_id)) ?absint( $woomage_id ):0,
                'name'        => $term->name,
                'slug'        => $term->slug,
                'parent'      => $term->parent,
                'description' => $term->description,
                'display'     => $display_type ? $display_type : 'default',
                'image'       => array( 'id' => $thumbnail_id, 'src' => $image ? esc_url( $image ) : '' ),
                'count'       => intval( $term->count ),
                'woomage_skuch' => isset($woomage_skuchr) ? $woomage_skuchr : ''
            );

            return array( 'product_category' => apply_filters( 'woocommerce_api_product_category_response', $product_category, $id, $fields, $term, $this ) );
        } catch ( WC_API_Exception $e ) {
            return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
        }
    }
        /**
     * Delete multiple product categories
     *
     * @since 2.2
     * @param int $id the product ID
     * @param bool $force true to permanently delete order, false to move to trash
     * @return array
     */
    public function delete_product_categories( $cat_array ) {

            try {
                //{"id":1162,"parent":1160,"name":"My new cat under New1"}
                foreach ($cat_array as $categ) {
                    $this->delete_product_category($categ['id']);
                }
                return array( 'message' => sprintf( __( 'Permanently deleted product categories', 'woocommerce' ) ) );
            } catch ( WC_API_Exception $e ) {

                return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
            }
        }
        /**
     * Delete a product category
     *
     * @since 2.2
     * @param int $id the product ID
     * @param bool $force true to permanently delete order, false to move to trash
     * @return array
     */
    public function delete_product_category( $id ) {
    try {
            $id = absint( $id );

            // Validate ID
            if ( empty( $id ) ) {
                throw new WC_API_Exception( 'woocommerce_api_invalid_product_category_id', __( 'Invalid product category ID', 'woocommerce' ), 400 );
            }

            // Permissions check
            if ( ! current_user_can( 'manage_product_terms' ) ) {
                throw new WC_API_Exception( 'woocommerce_api_user_cannot_edit_product_categories', __( 'You do not have permission to edit product categories', 'woocommerce' ), 401 );
            }

            $term = get_term( $id, 'product_cat' );

            if ( is_wp_error( $term ) || is_null( $term ) ) {
                throw new WC_API_Exception( 'woocommerce_api_invalid_product_category_id', __( 'A product category with the provided ID could not be found', 'woocommerce' ), 404 );
            }
            //delete category image id
            $thumbnail_id = absint( get_woocommerce_term_meta( $term->term_id, 'thumbnail_id', true ) );
            if ($thumbnail_id){
                delete_woocommerce_term_meta($term->term_id, 'thumbnail_id');
            }

            do_action( 'woocommerce_api_delete_product_category', $term->term_id, $this );

            $result = wp_delete_term( $term->term_id, 'product_cat');

            if ( ! $result )
                return new WP_Error( "woocommerce_api_cannot_delete_category", sprintf( __( 'This category cannot be deleted', 'woocommerce' ) ), array( 'status' => 500 ) );

            delete_woocommerce_term_meta($id, 'display_type');
            delete_woocommerce_term_meta($id, 'woomageid');

            return array( 'message' => sprintf( __( 'Permanently deleted product category', 'woocommerce' ) ) );

        } catch ( WC_API_Exception $e ) {

            return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
        }


    }


    /**
     * Upload image from URL
     *
     * @since 2.2
     * @param string $image_url
     * @return int|WP_Error attachment id
     */
    public function upload_image_from_url( $image_url ) {
        $file_name         = basename( current( explode( '?', $image_url ) ) );
        $wp_filetype     = wp_check_filetype( $file_name, null );
        $parsed_url     = @parse_url( $image_url );

        // Check parsed URL
        if ( ! $parsed_url || ! is_array( $parsed_url ) ) {
            throw new WC_API_Exception( 'woocommerce_api_invalid_image', sprintf( __( 'Invalid URL %s', 'woocommerce' ), $image_url ), 400 );
        }

        // Ensure url is valid
        $image_url = str_replace( ' ', '%20', $image_url );

        // Get the file
        $response = wp_remote_get( $image_url, array(
            'timeout' => 10
        ) );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            throw new WC_API_Exception( 'woocommerce_api_invalid_remote_image', sprintf( __( 'Error getting remote image %s', 'woocommerce' ), $image_url ), 400 );
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
            throw new WC_API_Exception( 'woocommerce_api_image_upload_error', $upload['error'], 400 );
        }

        // Get filesize
        $filesize = filesize( $upload['file'] );

        if ( 0 == $filesize ) {
            @unlink( $upload['file'] );
            unset( $upload );
            throw new WC_API_Exception( 'woocommerce_api_image_upload_file_error', __( 'Zero size file downloaded', 'woocommerce' ), 400 );
        }

        unset( $response );

        return $upload;
    }

        /**
     * Get product category image as attachment
     *
     * @since 2.2
     * @param integer $upload
     * @param int $id
     * @return int
     */
    protected function set_image_as_attachment( $upload, $id = 0 ) {
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
  }



}
