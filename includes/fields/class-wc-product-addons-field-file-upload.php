<?php
/**
 * File Upload field
 */
class WC_Product_Addons_Field_File_Upload extends WC_Product_Addons_Field {
	public $addon;
	public $value;
	public $test;

	/**
	 * Constructor
	 */
	public function __construct( $addon, $value = '', $test = false ) {
		$this->addon = $addon;
		$this->value = $value;
		$this->test  = $test;
	}

	/**
	 * Validate an addon
	 * @return bool pass, or WP_Error
	 */
	public function validate() {
		$field_name = $this->get_field_name();

		if ( ! empty( $this->addon['required'] ) ) {
			if ( empty( $_FILES[ $field_name ] ) || empty( $_FILES[ $field_name ]['name'] ) ) {
				/* translators: %s Addon name */
				return new WP_Error( 'error', sprintf( __( '"%s" is a required field.', 'woocommerce-product-addons-extra-digital' ), $this->addon['name'] ) );
			}
		}

		if ( ! empty( $_FILES[ $field_name ] ) && WC_Product_Addons_Helper::is_filesize_over_limit( $_FILES[ $field_name ] ) ) {
			return new WP_Error( 'error', __( 'Filesize exceeds the limit.', 'woocommerce-product-addons-extra-digital' ) );
		}

		return true;
	}

	/**
	 * Process this field after being posted
	 * @return array on success, WP_ERROR on failure
	 */
	public function get_cart_item_data() {
		$cart_item_data = array();
		$adjust_price   = $this->addon['adjust_price'];
		$field_name     = $this->get_field_name();
		$this_data      = array(
			'name'    => sanitize_text_field( $this->addon['name'] ),
			'price'   => '1' != $adjust_price ? 0 : floatval( sanitize_text_field( $this->addon['price'] ) ),
			'value'   => '',
			'display' => '',
			'field_name' => $this->addon['field_name'],
			'field_type' => $this->addon['type'],
			'price_type' => $this->addon['price_type'],
		);

		if ( ! empty( $_FILES[ $field_name ] ) && ! empty( $_FILES[ $field_name ]['name'] ) && ! $this->test ) {
			$upload = $this->handle_upload( $_FILES[ $field_name ] );

			if ( empty( $upload['error'] ) && ! empty( $upload['file'] ) ) {
				$value                = wc_clean( $upload['url'] );
				$this_data['value']   = wc_clean( $upload['url'] );
				$this_data['display'] = basename( wc_clean( $upload['url'] ) );
				$cart_item_data[]     = $this_data;
			} else {
				return new WP_Error( 'error', $upload['error'] );
			}
		} elseif ( ! empty( $this->value ) ) {
			$this_data['value']   = wc_clean( $this->value );
			$this_data['display'] = basename( wc_clean( $this->value ) );
			$cart_item_data[]     = $this_data;
		}

		return $cart_item_data;
	}

	/**
	 * Handle file upload
	 * @param  string $file
	 * @return array
	 */
	public function handle_upload( $file ) {
		include_once( ABSPATH . 'wp-admin/includes/file.php' );
		include_once( ABSPATH . 'wp-admin/includes/media.php' );

		add_filter( 'upload_dir',  array( $this, 'upload_dir' ) );

		$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

		remove_filter( 'upload_dir',  array( $this, 'upload_dir' ) );

		return $upload;
	}

	/**
	 * upload_dir function.
	 *
	 * @access public
	 * @param mixed $pathdata
	 * @return void
	 */
	public function upload_dir( $pathdata ) {
		if ( empty( $pathdata['subdir'] ) ) {
			$pathdata['path']   = $pathdata['path'] . '/product_addons_uploads/' . md5( WC()->session->get_customer_id() );
			$pathdata['url']    = $pathdata['url'] . '/product_addons_uploads/' . md5( WC()->session->get_customer_id() );
			$pathdata['subdir'] = '/product_addons_uploads/' . md5( WC()->session->get_customer_id() );
		} else {
			$subdir             = '/product_addons_uploads/' . md5( WC()->session->get_customer_id() );
			$pathdata['path']   = str_replace( $pathdata['subdir'], $subdir, $pathdata['path'] );
			$pathdata['url']    = str_replace( $pathdata['subdir'], $subdir, $pathdata['url'] );
			$pathdata['subdir'] = str_replace( $pathdata['subdir'], $subdir, $pathdata['subdir'] );
		}

		return apply_filters( 'woocommerce_product_addons_upload_dir', $pathdata );
	}
}
