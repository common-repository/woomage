<?php
/**
 * Woomage WooCommerce API Media Class
 *
 * Handles requests to the /woomage/media endpoint
 *
 * @author      Woomage
 * @category    WooCommerce REST API extension
 * @package     WooMage
 * @since       1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Woomage_WC_API_Media extends WC_API_Resource {

    /** @var string $base the route base */
    protected $base = '/woomage/media';


    /**
     * Register the routes for this class
     *
     * @since 2.1
     * @param array $routes
     * @return array
     */
    public function register_routes( $routes ) {

                # POST
        $routes[ $this->base . '/upload' ] = array(
            array( array( $this, 'upload_media' ),  WC_API_Server::CREATABLE ),
        );

        return $routes;
    }

        /**
     * Upload a new media (e.g. image)
     *
     * Creating a new attachment is done in two steps: uploading the data, then
     * setting the post. This is achieved by first creating an attachment, then
     * editing the post data for it.
     *
     * @param array $_files Data from $_FILES
     * @param array $_headers HTTP headers from the request
     * @return array|WP_Error Attachment data or error
     */
    public function upload_media( $_headers /*, $product_id */ ) {
        // Get the file via raw data

        $file = $this->upload_from_data($this->server->get_raw_data(), $_headers );

        if ( is_wp_error( $file ) ) {
            return $file;
        }

        $name       = basename( $file['file'] );
        $name_parts = pathinfo( $name );
        $name       = trim( substr( $name, 0, -(1 + strlen($name_parts['extension'])) ) );

        $url     = $file['url'];
        $type    = $file['type'];
        $file    = $file['file'];
        $title   = $name;
        $content = '';

        // use image exif/iptc data for title and caption defaults if possible
        if ( $image_meta = @wp_read_image_metadata($file) ) {
            if ( trim( $image_meta['title'] ) && ! is_numeric( sanitize_title( $image_meta['title'] ) ) ) {
                $title = $image_meta['title'];
            }

            if ( trim( $image_meta['caption'] ) ) {
                $content = $image_meta['caption'];
            }
        }

        // Construct the attachment array
        $post_data  = array();
        $attachment = array(
            'post_mime_type' => $type,
            'guid'           => $url,
            'post_title'     => $title,
            'post_content'   => $content,
        );

        // This should never be set as it would then overwrite an existing attachment.
        if ( isset( $attachment['ID'] ) ) {
            unset( $attachment['ID'] );
        }
                
        // Save the data
        $id = wp_insert_attachment($attachment, $file /*, $post_id*/ );

        if ( !is_wp_error($id) ) {
            wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
        }

        return array( 'media' => array( 'id' => $id, 'url' => $url ) );
    }

    /**
     * Handle an upload via raw POST data
     *
     * @param array $_files Data from $_FILES. Unused.
     * @param array $_headers HTTP headers from the request
     * @return array|WP_Error Data from {@see wp_handle_sideload()}
     */
    protected function upload_from_data( $data, $_headers ) {
        

        if ( empty( $data ) ) {
            return new WP_Error( 'json_upload_no_data', __( 'No data supplied' ), array( 'status' => 400 ) );
        }

        if ( empty( $_headers['CONTENT_TYPE'] ) ) {
            return new WP_Error( 'json_upload_no_type', __( 'No Content-Type supplied' ), array( 'status' => 400 ) );
        }

        if ( empty( $_headers['CONTENT_DISPOSITION'] ) ) {
            return new WP_Error( 'json_upload_no_disposition', __( 'No Content-Disposition supplied' ), array( 'status' => 400 ) );
        }

        // Get the filename
        $disposition_parts = explode( ';', $_headers['CONTENT_DISPOSITION'] );
        $filename = null;

        foreach ( $disposition_parts as $part ) {
            $part = trim( $part );

            if ( strpos( $part, 'filename' ) !== 0 ) {
                continue;
            }

            $filenameparts = explode( '=', $part );
            $filename      = trim( $filenameparts[1] );
        }

        if ( empty( $filename ) ) {
            return new WP_Error( 'json_upload_invalid_disposition', __( 'Invalid Content-Disposition supplied' ), array( 'status' => 400 ) );
        }

        if ( ! empty( $_headers['CONTENT_MD5'] ) ) {
            $expected = trim( $_headers['CONTENT_MD5'] );
            $actual   = md5( $data );

            if ( $expected !== $actual ) {
                return new WP_Error( 'json_upload_hash_mismatch', __( 'Content hash did not match expected' ), array( 'status' => 412 ) );
            }
        }

        // Get the content-type
        $type = $_headers['CONTENT_TYPE'];

        // Save the file
        $tmpfname = wp_tempnam( $filename );

        $fp = fopen( $tmpfname, 'w+' );

        if ( ! $fp ) {
            return new WP_Error( 'json_upload_file_error', __( 'Could not open file handle' ), array( 'status' => 500 ) );
        }

        fwrite( $fp, $data );
        fclose( $fp );

        // Now, sideload it in
        $file_data = array(
            'error' => null,
            'tmp_name' => $tmpfname,
            'name' => $filename,
            'type' => $type,
        );
        $overrides = array(
            'test_form' => false,
        );
        $sideloaded = wp_handle_sideload( $file_data, $overrides );

        if ( isset( $sideloaded['error'] ) ) {
            @unlink( $tmpfname );
            return new WP_Error( 'json_upload_sideload_error', $sideloaded['error'], array( 'status' => 500 ) );
        }

        return $sideloaded;
    }

}
