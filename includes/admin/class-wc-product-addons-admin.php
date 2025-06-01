<?php
/**
 * Product Add-ons admin
 *
 * @package WC_Product_Addons/Classes/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Admin\Features\Navigation\Menu;
use Automattic\WooCommerce\Admin\Features\Navigation\Screen;
use Automattic\WooCommerce\Admin\Features\Features;

/**
 * Product_Addon_Admin class.
 */
class WC_Product_Addons_Admin {

	/**
	 * Initialize administrative actions.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'script_styles' ), 100 );
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 9 );
		add_filter( 'woocommerce_screen_ids', array( $this, 'add_screen_id' ) );
		add_action( 'woocommerce_product_write_panel_tabs', array( $this, 'tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'process_meta_box' ), 1 );

		// Addon order display.
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'filter_hidden_order_itemmeta' ) );
		add_action( 'woocommerce_before_order_item_line_item_html', array( $this, 'filter_order_line_item_html' ), 10, 3 );
		add_action( 'woocommerce_order_item_line_item_html', array( $this, 'filter_order_line_item_after_html' ), 10, 3 );

		add_action( 'wp_ajax_wc_pao_get_addon_options', array( $this, 'ajax_get_addon_options' ) );
		add_action( 'wp_ajax_wc_pao_get_addon_field', array( $this, 'ajax_get_addon_field' ) );
		
		// Conditional logic AJAX handlers
		add_action( 'wp_ajax_wc_product_addons_save_rule', array( $this, 'ajax_save_rule' ) );
		add_action( 'wp_ajax_wc_product_addons_get_rules', array( $this, 'ajax_get_rules' ) );
		add_action( 'wp_ajax_wc_product_addons_get_rule', array( $this, 'ajax_get_rule' ) );
		add_action( 'wp_ajax_wc_product_addons_delete_rule', array( $this, 'ajax_delete_rule' ) );
		add_action( 'wp_ajax_wc_product_addons_toggle_rule', array( $this, 'ajax_toggle_rule' ) );
		add_action( 'wp_ajax_wc_product_addons_duplicate_rule', array( $this, 'ajax_duplicate_rule' ) );
		add_action( 'wp_ajax_wc_product_addons_get_all_addons', array( $this, 'ajax_get_all_addons' ) );
		add_action( 'wp_ajax_wc_product_addons_search_categories', array( $this, 'ajax_search_categories' ) );
		add_action( 'wp_ajax_wc_product_addons_update_rule_priorities', array( $this, 'ajax_update_rule_priorities' ) );
	}

	/**
	 * Add menus
	 */
	public function admin_menu() {
		$page = add_submenu_page( 'edit.php?post_type=product', __( 'Add-ons', 'woocommerce-product-addons-extra-digital' ), __( 'Add-ons', 'woocommerce-product-addons-extra-digital' ), 'manage_woocommerce', 'addons', array( $this, 'global_addons_admin' ) );
		
		// Add conditional logic submenu
		$conditional_page = add_submenu_page( 'edit.php?post_type=product', __( 'Conditional Logic', 'woocommerce-product-addons-extra-digital' ), __( 'Conditional Logic', 'woocommerce-product-addons-extra-digital' ), 'manage_woocommerce', 'addon-conditional-logic', array( $this, 'conditional_logic_admin' ) );

		if (
			! class_exists( 'Features' ) ||
			! method_exists( Screen::class, 'register_post_type' ) ||
			! method_exists( Menu::class, 'add_plugin_item' ) ||
			! method_exists( Menu::class, 'add_plugin_category' ) ||
			! Features::is_enabled( 'navigation' )
		) {
			return;
		}

		Menu::add_plugin_item(
			array(
				'id'         => 'woocommerce-product-addons-extra-digital',
				'title'      => __( 'Product Add-ons', 'woocommerce-product-addons-extra-digital' ),
				'url'        => 'edit.php?post_type=product&page=addons',
				'capability' => 'manage_woocommerce',
			)
		);
	}

	/**
	 * Get add-on options.
	 *
	 * @since 3.0.0.
	 */
	public function ajax_get_addon_options() {
		check_ajax_referer( 'wc-pao-get-addon-options', 'security' );

		global $product_addons, $post, $options;

		$option = WC_Product_Addons_Admin::get_new_addon_option();
		$loop   = "{loop}";

		ob_start();
		include( WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/admin/views/html-addon-option.php' );
		$html = ob_get_clean();

		$html = str_replace( array( "\n", "\r" ), '', str_replace( "'", '"', $html ) );

		wp_send_json( array( 'html' => $html ) );
	}

	/**
	 * Get add-on field.
	 *
	 * @since 3.0.0.
	 */
	public function ajax_get_addon_field() {
		check_ajax_referer( 'wc-pao-get-addon-field', 'security' );

		global $product_addons, $post, $options;

		ob_start();
		$addon                       = array();
		$addon['name']               = '';
		$addon['title_format']       = 'label';
		$addon['description_enable'] = '';
		$addon['description']        = '';
		$addon['required']           = '';
		$addon['type']               = 'multiple_choice';
		$addon['display']            = 'select';
		$addon['restrictions']       = '';
		$addon['restrictions_type']  = 'any_text';
		$addon['min']                = '';
		$addon['max']                = '';
		$addon['adjust_price']       = '';
		$addon['price_type']         = '';
		$addon['price']              = '';

		$addon['options']            = array(
			WC_Product_Addons_Admin::get_new_addon_option(),
		);

		$loop = "{loop}";

		include( WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/admin/views/html-addon.php' );

		$html = ob_get_clean();

		$html = str_replace( array( "\n", "\r" ), '', str_replace( "'", '"', $html ) );

		wp_send_json( array( 'html' => $html ) );
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @since 3.0.0
	 */
	public function script_styles() {
		$screen_id = get_current_screen()->id;
		$is_conditional_logic_page = isset( $_GET['page'] ) && $_GET['page'] === 'addon-conditional-logic';
		
		if (
			'product_page_addons' !== $screen_id &&
			'product'             !== $screen_id &&
			'shop_order'          !== $screen_id &&
			'shop_subscription'   !== $screen_id &&
			! $is_conditional_logic_page
		) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'woocommerce-product-addons-extra-digital-admin-css', WC_PRODUCT_ADDONS_PLUGIN_URL . '/assets/css/admin.css', array(), WC_PRODUCT_ADDONS_VERSION );

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_script( 'woocommerce-product-addons-extra-digital-admin', plugins_url( 'assets/js/admin' . $suffix . '.js', WC_PRODUCT_ADDONS_MAIN_FILE ), array( 'jquery' ), WC_PRODUCT_ADDONS_VERSION, true );

		$params = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => array(
				'get_addon_options' => wp_create_nonce( 'wc-pao-get-addon-options' ),
				'get_addon_field'   => wp_create_nonce( 'wc-pao-get-addon-field' ),
			),
			'i18n'     => array(
				'required_fields'       => __( 'All fields must have a title and/or option name. Please review the settings highlighted in red border.', 'woocommerce-product-addons-extra-digital' ),
				'limit_price_range'         => __( 'Limit price range', 'woocommerce-product-addons-extra-digital' ),
				'limit_quantity_range'      => __( 'Limit quantity range', 'woocommerce-product-addons-extra-digital' ),
				'limit_character_length'    => __( 'Limit character length', 'woocommerce-product-addons-extra-digital' ),
				'restrictions'              => __( 'Restrictions', 'woocommerce-product-addons-extra-digital' ),
				'confirm_remove_addon'      => __( 'Are you sure you want remove this add-on field?', 'woocommerce-product-addons-extra-digital' ),
				'confirm_remove_option'     => __( 'Are you sure you want delete this option?', 'woocommerce-product-addons-extra-digital' ),
				'add_image_swatch'          => __( 'Add Image Swatch', 'woocommerce-product-addons-extra-digital' ),
				'add_image'                 => __( 'Add Image', 'woocommerce-product-addons-extra-digital' ),
			),
		);

		wp_localize_script( 'woocommerce-product-addons-extra-digital-admin', 'wc_pao_params', apply_filters( 'wc_pao_params', $params ) );

		wp_enqueue_script( 'woocommerce-product-addons-extra-digital-admin' );
		
		// Load conditional logic scripts on the conditional logic admin page
		if ( $is_conditional_logic_page ) {
			wp_enqueue_script( 'wc-pao-conditional-logic-admin', WC_PRODUCT_ADDONS_PLUGIN_URL . '/assets/js/conditional-logic-admin.js', array( 'jquery', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable', 'wc-enhanced-select', 'select2' ), WC_PRODUCT_ADDONS_VERSION, true );
			wp_enqueue_style( 'wc-pao-conditional-logic-admin-css', WC_PRODUCT_ADDONS_PLUGIN_URL . '/assets/css/conditional-logic-admin.css', array(), WC_PRODUCT_ADDONS_VERSION );
			
			wp_localize_script(
				'wc-pao-conditional-logic-admin',
				'wc_product_addons_params',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'save_rule_nonce' => wp_create_nonce( 'wc_pao_conditional_logic' ),
					'get_rules_nonce' => wp_create_nonce( 'wc_pao_conditional_logic' ),
					'get_rule_nonce' => wp_create_nonce( 'wc_pao_conditional_logic' ),
					'delete_rule_nonce' => wp_create_nonce( 'wc_pao_conditional_logic' ),
					'toggle_rule_nonce' => wp_create_nonce( 'wc_pao_conditional_logic' ),
					'duplicate_rule_nonce' => wp_create_nonce( 'wc_pao_conditional_logic' ),
					'get_addons_nonce' => wp_create_nonce( 'wc_pao_conditional_logic' ),
					'search_categories_nonce' => wp_create_nonce( 'wc_pao_conditional_logic' ),
					'search_products_nonce' => wp_create_nonce( 'woocommerce_json_search_products' ),
					'update_priorities_nonce' => wp_create_nonce( 'wc_pao_conditional_logic' ),
					'i18n_confirm_delete_rule' => __( 'Are you sure you want to delete this rule?', 'woocommerce-product-addons' ),
					'i18n_rule_saved' => __( 'Rule saved successfully', 'woocommerce-product-addons' ),
					'i18n_error_saving' => __( 'Error saving rule', 'woocommerce-product-addons' ),
					'i18n_rule_deleted' => __( 'Rule deleted successfully', 'woocommerce-product-addons' ),
					'i18n_rule_duplicated' => __( 'Rule duplicated successfully', 'woocommerce-product-addons' ),
					'i18n_rule_name_required' => __( 'Please enter a rule name', 'woocommerce-product-addons' ),
					'i18n_at_least_one_condition' => __( 'Please add at least one condition', 'woocommerce-product-addons' ),
					'i18n_at_least_one_action' => __( 'Please add at least one action', 'woocommerce-product-addons' ),
					'i18n_select_scope_target' => __( 'Please select at least one category or product', 'woocommerce-product-addons' ),
					'i18n_no_rules' => __( 'No rules found. Create your first rule above!', 'woocommerce-product-addons' ),
					'i18n_active' => __( 'Active', 'woocommerce-product-addons' ),
					'i18n_inactive' => __( 'Inactive', 'woocommerce-product-addons' ),
					'i18n_select_addon' => __( 'Select add-on...', 'woocommerce-product-addons' ),
					'i18n_select_option' => __( 'Select option...', 'woocommerce-product-addons' ),
					'i18n_entire_addon' => __( 'Entire Add-on', 'woocommerce-product-addons' ),
					'i18n_specific_option' => __( 'Specific Option', 'woocommerce-product-addons' ),
					'i18n_new_price' => __( 'New price...', 'woocommerce-product-addons' ),
					'i18n_increase_by' => __( 'Increase by fixed amount', 'woocommerce-product-addons' ),
					'i18n_decrease_by' => __( 'Decrease by fixed amount', 'woocommerce-product-addons' ),
					'i18n_increase_percent' => __( 'Increase by percentage', 'woocommerce-product-addons' ),
					'i18n_decrease_percent' => __( 'Decrease by percentage', 'woocommerce-product-addons' ),
					'i18n_amount' => __( 'Amount...', 'woocommerce-product-addons' ),
					'i18n_new_label' => __( 'New label...', 'woocommerce-product-addons' ),
					'i18n_new_description' => __( 'New description...', 'woocommerce-product-addons' ),
					'i18n_equals' => __( 'equals', 'woocommerce-product-addons' ),
					'i18n_not_equals' => __( 'not equals', 'woocommerce-product-addons' ),
					'i18n_contains' => __( 'contains', 'woocommerce-product-addons' ),
					'i18n_not_contains' => __( 'does not contain', 'woocommerce-product-addons' ),
					'i18n_is_empty' => __( 'is empty', 'woocommerce-product-addons' ),
					'i18n_is_not_empty' => __( 'is not empty', 'woocommerce-product-addons' ),
					'i18n_is_selected' => __( 'is selected', 'woocommerce-product-addons' ),
					'i18n_is_not_selected' => __( 'is not selected', 'woocommerce-product-addons' ),
					'i18n_greater_than' => __( 'greater than', 'woocommerce-product-addons' ),
					'i18n_less_than' => __( 'less than', 'woocommerce-product-addons' ),
					'i18n_greater_equals' => __( 'greater than or equal', 'woocommerce-product-addons' ),
					'i18n_less_equals' => __( 'less than or equal', 'woocommerce-product-addons' ),
					'i18n_value' => __( 'Value', 'woocommerce-product-addons' ),
					'i18n_price' => __( 'Price', 'woocommerce-product-addons' ),
					'i18n_new_price' => __( 'New price', 'woocommerce-product-addons' ),
					'i18n_increase_by' => __( 'Increase by', 'woocommerce-product-addons' ),
					'i18n_decrease_by' => __( 'Decrease by', 'woocommerce-product-addons' ),
					'i18n_increase_percent' => __( 'Increase by %', 'woocommerce-product-addons' ),
					'i18n_decrease_percent' => __( 'Decrease by %', 'woocommerce-product-addons' ),
					'i18n_amount' => __( 'Amount', 'woocommerce-product-addons' ),
				)
			);
		}
	}

	/**
	 * Add screen id to WooCommerce.
	 *
	 * @param array $screen_ids List of screen IDs.
	 * @return array
	 */
	public function add_screen_id( $screen_ids ) {
		$screen_ids[] = 'product_page_addons';

		return $screen_ids;
	}

	/**
	 * Controls the global addons admin page.
	 */
	public function global_addons_admin() {
		if ( ! empty( $_GET['add'] ) || ! empty( $_GET['edit'] ) ) {

			if ( $_POST ) {
				$edit_id = $this->save_global_addons();

				if ( $edit_id ) {
					echo '<div class="updated"><p>' . esc_html__( 'Add-on saved successfully', 'woocommerce-product-addons-extra-digital' ) . '</p></div>';
				}

				$reference      = wc_clean( $_POST['addon-reference'] );
				$priority       = absint( $_POST['addon-priority'] );
				$objects        = ! empty( $_POST['addon-objects'] ) ? array_map( 'absint', $_POST['addon-objects'] ) : array();
				$product_addons = array_filter( (array) $this->get_posted_product_addons() );
			}

			if ( ! empty( $_GET['edit'] ) ) {

				$edit_id      = absint( $_GET['edit'] );
				$global_addon = get_post( $edit_id );

				if ( ! $global_addon ) {
					echo '<div class="error">' . esc_html__( 'Error: Add-on not found', 'woocommerce-product-addons-extra-digital' ) . '</div>';
					return;
				}

				$reference      = $global_addon->post_title;
				$priority       = get_post_meta( $global_addon->ID, '_priority', true );
				$objects        = (array) wp_get_post_terms( $global_addon->ID, apply_filters( 'woocommerce_product_addons_global_post_terms', array( 'product_cat' ) ), array( 'fields' => 'ids' ) );
				$product_addons = array_filter( (array) get_post_meta( $global_addon->ID, '_product_addons', true ) );

				if ( get_post_meta( $global_addon->ID, '_all_products', true ) == 1 ) {
					$objects[] = 0;
				}
			} elseif ( ! empty( $edit_id ) ) {

				$global_addon   = get_post( $edit_id );
				$reference      = $global_addon->post_title;
				$priority       = get_post_meta( $global_addon->ID, '_priority', true );
				$objects        = (array) wp_get_post_terms( $global_addon->ID, apply_filters( 'woocommerce_product_addons_global_post_terms', array( 'product_cat' ) ), array( 'fields' => 'ids' ) );
				$product_addons = array_filter( (array) get_post_meta( $global_addon->ID, '_product_addons', true ) );

				if ( get_post_meta( $global_addon->ID, '_all_products', true ) == 1 ) {
					$objects[] = 0;
				}
			} else {

				$global_addons_count = wp_count_posts( 'global_product_addon' );
				$reference           = __( 'Add-ons Group', 'woocommerce-product-addons-extra-digital' ) . ' #' . ( $global_addons_count->publish + 1 );
				$priority            = 10;
				$objects             = array( 0 );
				$product_addons      = array();

			}

			include( dirname( __FILE__ ) . '/views/html-global-admin-add.php' );
		} else {

			if ( ! empty( $_GET['delete'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'delete_addon' ) ) {
				wp_delete_post( absint( $_GET['delete'] ), true );
				echo '<div class="updated"><p>' . esc_html__( 'Add-on deleted successfully', 'woocommerce-product-addons-extra-digital' ) . '</p></div>';
			}

			include( dirname( __FILE__ ) . '/views/html-global-admin.php' );
		}
	}

	/**
	 * Converts the field type key to display name.
	 *
	 * @since 3.0.0
	 * @param string $type
	 * @return string $name
	 */
	public function convert_type_name( $type = '' ) {
		switch ( $type ) {
			case 'checkboxes':
				$name = __( 'Checkbox', 'woocommerce-product-addons-extra-digital' );
				break;
			case 'custom_price':
				$name = __( 'Price', 'woocommerce-product-addons-extra-digital' );
				break;
			case 'input_multiplier':
				$name = __( 'Quantity', 'woocommerce-product-addons-extra-digital' );
				break;
			case 'custom_text':
				$name = __( 'Short Text', 'woocommerce-product-addons-extra-digital' );
				break;
			case 'custom_textarea':
				$name = __( 'Long Text', 'woocommerce-product-addons-extra-digital' );
				break;
			case 'file_upload':
				$name = __( 'File Upload', 'woocommerce-product-addons-extra-digital' );
				break;
			case 'select':
				$name = __( 'Dropdown', 'woocommerce-product-addons-extra-digital' );
				break;
			case 'multiple_choice':
			default:
				$name = __( 'Multiple Choice', 'woocommerce-product-addons-extra-digital' );
				break;
		}

		return $name;
	}

	/**
	 * Save global addons
	 *
	 * @return bool success or failure
	 */
	public function save_global_addons() {
		$edit_id        = ! empty( $_POST['edit_id'] ) ? absint( $_POST['edit_id'] ) : '';
		$reference      = wc_clean( $_POST['addon-reference'] );
		$priority       = absint( $_POST['addon-priority'] );
		$objects        = ! empty( $_POST['addon-objects'] ) ? array_map( 'absint', $_POST['addon-objects'] ) : array();
		$product_addons = $this->get_posted_product_addons();

		if ( ! $reference ) {
			$global_addons_count = wp_count_posts( 'global_product_addon' );
			$reference           = __( 'Add-ons Group', 'woocommerce-product-addons-extra-digital' ) . ' #' . ( $global_addons_count->publish + 1 );
		}

		if ( ! $priority && 0 !== $priority ) {
			$priority = 10;
		}

		if ( $edit_id ) {

			$edit_post               = array();
			$edit_post['ID']         = $edit_id;
			$edit_post['post_title'] = $reference;

			wp_update_post( $edit_post );
			wp_set_post_terms( $edit_id, $objects, 'product_cat', false );
			do_action( 'woocommerce_product_addons_global_edit_addons', $edit_post, $objects );

		} else {

			$edit_id = wp_insert_post( apply_filters( 'woocommerce_product_addons_global_insert_post_args', array(
				'post_title'    => $reference,
				'post_status'   => 'publish',
				'post_type'     => 'global_product_addon',
				'tax_input'     => array(
					'product_cat' => $objects,
				),
			), $reference, $objects ) );
		}

		if ( in_array( 0, $objects ) ) {
			update_post_meta( $edit_id, '_all_products', 1 );
		} else {
			update_post_meta( $edit_id, '_all_products', 0 );
		}

		update_post_meta( $edit_id, '_priority', $priority );
		update_post_meta( $edit_id, '_product_addons', $product_addons );

		return $edit_id;
	}

	/**
	 * Add product tab.
	 */
	public function tab() {
	?>
		<li class="addons_tab product_addons hide_if_grouped hide_if_external"><a href="#product_addons_data"><span><?php esc_html_e( 'Add-ons', 'woocommerce-product-addons-extra-digital' ); ?></span></a></li>
	<?php
	}

	/**
	 * Add product panel.
	 */
	public function panel() {
		global $post;

		$product        = wc_get_product( $post );
		$exists         = (bool) $product->get_id();
		$product_addons = array_filter( (array) $product->get_meta( '_product_addons' ) );
		$exclude_global = $product->get_meta( '_product_addons_exclude_global' );

		include( dirname( __FILE__ ) . '/views/html-addon-panel.php' );
	}

	/**
	 * Process meta box.
	 *
	 * @param int $post_id Post ID.
	 */
	public function process_meta_box( $post_id ) {
		// Save addons as serialised array.
		$product_addons                = $this->get_posted_product_addons();
		$product_addons_exclude_global = isset( $_POST['_product_addons_exclude_global'] ) ? 1 : 0;
		$product = wc_get_product( $post_id );
		$product->update_meta_data( '_product_addons', $product_addons );
		$product->update_meta_data( '_product_addons_exclude_global', $product_addons_exclude_global );
		$product->save();
	}

	/**
	 * Generate a filterable default new addon option.
	 *
	 * @return array
	 */
	public static function get_new_addon_option() {
		$new_addon_option = array(
			'label'      => '',
			'label_2'      => '',
			'image'      => '',
			'price'      => '',
			'price_type' => 'flat_fee',
		);

		return apply_filters( 'woocommerce_product_addons_new_addon_option', $new_addon_option );
	}

	/**
	 * Put posted addon data into an array.
	 *
	 * @return array
	 */
	protected function get_posted_product_addons() {
		$product_addons = array();

		if ( isset( $_POST['product_addon_name'] ) ) {
			$addon_name               = $_POST['product_addon_name'];
			$addon_title_format       = $_POST['product_addon_title_format'];
			$addon_description_enable = isset( $_POST['product_addon_description_enable'] ) ? $_POST['product_addon_description_enable'] : array();
			$addon_description        = $_POST['product_addon_description'];
			$addon_type               = $_POST['product_addon_type'];
			$addon_display            = $_POST['product_addon_display'];
			$addon_position           = $_POST['product_addon_position'];
			$addon_required           = isset( $_POST['product_addon_required'] ) ? $_POST['product_addon_required'] : array();
			$addon_option_label       = $_POST['product_addon_option_label'];
			$addon_option_label_2       = $_POST['product_addon_option_label_2'];
			$addon_option_price       = $_POST['product_addon_option_price'];
			$addon_option_price_type  = $_POST['product_addon_option_price_type'];
			$addon_option_image       = $_POST['product_addon_option_image'];
			$addon_restrictions       = isset( $_POST['product_addon_restrictions'] ) ? $_POST['product_addon_restrictions'] : array();
			$addon_restrictions_type  = $_POST['product_addon_restrictions_type'];
			$addon_adjust_price       = isset( $_POST['product_addon_adjust_price'] ) ? $_POST['product_addon_adjust_price'] : array();
			$addon_price_type         = $_POST['product_addon_price_type'];
			$addon_price              = $_POST['product_addon_price'];
			$addon_min                = $_POST['product_addon_min'];
			$addon_max                = $_POST['product_addon_max'];

			for ( $i = 0; $i < count( $addon_name ); $i++ ) {
				if ( ! isset( $addon_name[ $i ] ) || ( '' == $addon_name[ $i ] ) ) {
					continue;
				}

				$addon_options = array();

				if ( isset( $addon_option_label[ $i ] ) ) {
					$option_label      = $addon_option_label[ $i ];
					$option_label_2      = $addon_option_label_2[ $i ];
					$option_price      = $addon_option_price[ $i ];
					$option_price_type = $addon_option_price_type[ $i ];
					$option_image      = $addon_option_image[ $i ];

					for ( $ii = 0; $ii < count( $option_label ); $ii++ ) {
						$label      = $option_label[ $ii ];
						$label_2      = $option_label_2[ $ii ];
						$price      = wc_format_decimal( sanitize_text_field( wp_unslash( $option_price[ $ii ] ) ) );
						$image      = sanitize_text_field( wp_unslash( $option_image[ $ii ] ) );
						$price_type = sanitize_text_field( wp_unslash( $option_price_type[ $ii ] ) );

						$addon_options[] = array(
							'label'      => $label,
							'label_2'      => $label_2,
							'price'      => $price,
							'image'      => $image,
							'price_type' => $price_type,
						);
					}
				}

				$data                       = array();
				$data['name']               = sanitize_text_field( wp_unslash( $addon_name[ $i ] ) );
				$data['title_format']       = sanitize_text_field( wp_unslash( $addon_title_format[ $i ] ) );
				$data['description_enable'] = isset( $addon_description_enable[ $i ] ) ? 1 : 0;
				$data['description']        = wp_kses_post( wp_unslash( $addon_description[ $i ] ) );
				$data['type']               = sanitize_text_field( wp_unslash( $addon_type[ $i ] ) );
				$data['display']            = sanitize_text_field( wp_unslash( $addon_display[ $i ] ) );
				$data['position']           = absint( $addon_position[ $i ] );
				$data['required']           = isset( $addon_required[ $i ] ) ? 1 : 0;
				$data['restrictions']       = isset( $addon_restrictions[ $i ] ) ? 1 : 0;
				$data['restrictions_type']  = sanitize_text_field( wp_unslash( $addon_restrictions_type[ $i ] ) );
				$data['adjust_price']       = isset( $addon_adjust_price[ $i ] ) ? 1 : 0;
				$data['price_type']         = sanitize_text_field( wp_unslash( $addon_price_type[ $i ] ) );
				$data['price']              = wc_format_decimal( sanitize_text_field( wp_unslash( $addon_price[ $i ] ) ) );
				$data['min']                = (float) sanitize_text_field( wp_unslash( $addon_min[ $i ] ) );
				$data['max']                = (float) sanitize_text_field( wp_unslash( $addon_max[ $i ] ) );

				if ( ! empty( $addon_options ) ) {
					$data['options'] = $addon_options;
				}

				// Always use quantity based price type for custom price.
				if ( 'custom_price' === $data['type'] ) {
					$data['price_type'] = 'quantity_based';
				}

				// Add to array.
				$product_addons[] = apply_filters( 'woocommerce_product_addons_save_data', $data, $i );
			}
		}

		if ( ! empty( $_POST['import_product_addon'] ) ) {
			$import_addons = maybe_unserialize( maybe_unserialize( wp_unslash( trim( $_POST['import_product_addon'] ) ) ) );

			if ( is_array( $import_addons ) && sizeof( $import_addons ) > 0 ) {
				$valid = true;

				foreach ( $import_addons as $key => $addon ) {
					if ( ! isset( $addon['name'] ) || ! $addon['name'] ) {
						$valid = false;
					}
					if ( ! isset( $addon['description'] ) ) {
						$valid = false;
					}
					if ( ! isset( $addon['type'] ) ) {
						$valid = false;
					}
					if ( ! isset( $addon['position'] ) ) {
						$valid = false;
					}
					if ( ! isset( $addon['required'] ) ) {
						$valid = false;
					}

					// Sanitize the addon before importing.
					if ( $valid ) {
						$import_addons[ $key ] = apply_filters( 'woocommerce_product_addons_import_data', $this->sanitize_addon( $addon ), $addon, $key );
					}
				}

				if ( $valid ) {
					$product_addons = array_merge( $product_addons, $import_addons );
				}
			}
		}

		uasort( $product_addons, array( $this, 'addons_cmp' ) );

		return $product_addons;
	}

	/**
	 * Sanitize the addon.
	 *
	 * @since 3.0.36
	 * @param array $addon Array containing the addon data.
	 * @return array
	 */
	public function sanitize_addon( $addon ) {
		$sanitized = array(
			'name'               => sanitize_text_field( $addon['name'] ),
			'title_format'       => sanitize_text_field( $addon['title_format'] ),
			'description_enable' => ! empty( $addon['description_enable'] ) ? 1 : 0,
			'description'        => wp_kses_post( $addon['description'] ),
			'type'               => sanitize_text_field( $addon['type'] ),
			'display'            => sanitize_text_field( $addon['display'] ),
			'position'           => absint( $addon['position'] ),
			'required'           => ! empty( $addon['required'] ) ? 1 : 0,
			'restrictions'       => ! empty( $addon['restrictions'] ) ? 1 : 0,
			'restrictions_type'  => sanitize_text_field( $addon['restrictions_type'] ),
			'adjust_price'       => ! empty( $addon['adjust_price'] ) ? 1 : 0,
			'price_type'         => sanitize_text_field( $addon['price_type'] ),
			'price'              => wc_format_decimal( sanitize_text_field( $addon['price'] ) ),
			'min'                => (float) sanitize_text_field( $addon['min'] ),
			'max'                => (float) sanitize_text_field( $addon['max'] ),
		);

		if ( is_array( $addon['options'] ) ) {
			$sanitized['options'] = array();

			foreach ( $addon['options'] as $key => $option ) {
				$sanitized['options'][ $key ] = array(
					'label'      => sanitize_text_field( $option['label'] ),
					'label_2'      => sanitize_text_field( $option['label_2'] ),
					'price'      => wc_format_decimal( sanitize_text_field( $option['price'] ) ),
					'image'      => sanitize_text_field( $option['image'] ),
					'price_type' => sanitize_text_field( $option['price_type'] ),
				);
			}
		}

		return $sanitized;
	}

	/**
	 * Filters the admin order hidden metas to hide addons.
	 *
	 * @since 3.0.0
	 * @param array $hidden_metas
	 */
	public function filter_hidden_order_itemmeta( $hidden_metas ) {
		$hidden_metas[] = '_wc_pao_addon_name';
		$hidden_metas[] = '_wc_pao_addon_value';
		$hidden_metas[] = '_wc_pao_addon_field_type';
		$hidden_metas[] = '_reduced_stock';

		return $hidden_metas;
	}

	/**
	 * Filters the admin order line item to show only addons.
	 *
	 * @since 3.0.0
	 * @param int $item_id
	 * @param object $item
	 * @param object $order
	 */
	public function filter_order_line_item_html( $item_id, $item, $order ) {
		$is_addon = ! empty( $item->get_meta( '_wc_pao_addon_value', true ) );

		if ( $is_addon ) {
			ob_start();
		}
	}

	/**
	 * Filters the admin order line item to show only addons.
	 *
	 * @since 3.0.0
	 * @param int $item_id
	 * @param object $item
	 * @param object $order
	 */
	public function filter_order_line_item_after_html( $item_id, $item, $order ) {
		$addon_name  = $item->get_meta( '_wc_pao_addon_name', true );
		$addon_value = $item->get_meta( '_wc_pao_addon_value', true );

		$is_addon = ! empty( $addon_value );

		if ( $is_addon ) {
			$product      = $item->get_product();
			$product_link = $product ? admin_url( 'post.php?post=' . $item->get_product_id() . '&action=edit' ) : '';
			$thumbnail    = $product ? apply_filters( 'woocommerce_admin_order_item_thumbnail', $product->get_image( 'thumbnail', array( 'title' => '' ), false ), $item_id, $item ) : '';

			$addon_html = ob_get_clean();

			$addon_html = str_replace( '<div class="wc-order-item-thumbnail">' . wp_kses_post( $thumbnail ) . '</div>', '', $addon_html );

			$addon_html = str_replace( $product_link ? '<a href="' . esc_url( $product_link ) . '" class="wc-order-item-name">' . esc_html( $item->get_name() ) . '</a>' : '<div class="wc-order-item-name">' . esc_html( $item->get_name() ) . '</div>', '<div class="wc-order-item-name"><div class="wc-pao-order-item-name"><strong>' . esc_html( $addon_name ) . '</strong></div><div class="wc-pao-order-item-value">' . esc_html( $addon_value ) . '</div></div>', $addon_html );

			$addon_html = str_replace( '"display_meta"', '"display_meta" style="display:none;"', $addon_html );

			if ( $product && $product->get_sku() ) {
				$addon_html = str_replace( '<div class="wc-order-item-sku"><strong>' . esc_html__( 'SKU:', 'woocommerce-product-addons-extra-digital' ) . '</strong> ' . esc_html( $product->get_sku() ) . '</div>', '', $addon_html );
			}

			// Variations.
			if ( $item->get_variation_id() ) {
				if ( 'product_variation' === get_post_type( $item->get_variation_id() ) ) {
					$var_id = esc_html( $item->get_variation_id() );
				} else {
					/* translators: %s ID of variation */
					$var_id = sprintf( esc_html__( '%s (No longer exists)', 'woocommerce-product-addons-extra-digital' ), $item->get_variation_id() );
				}

				$addon_html = str_replace( '<div class="wc-order-item-variation"><strong>' . esc_html__( 'Variation ID:', 'woocommerce-product-addons-extra-digital' ) . '</strong> ' . $var_id . '</div>', '', $addon_html );
			}

			echo $addon_html;
		}
	}

	/**
	 * Conditional Logic admin page
	 */
	public function conditional_logic_admin() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'woocommerce-product-addons-extra-digital' ) );
		}

		include_once WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/admin/views/html-conditional-logic-admin.php';
	}

	/**
	 * Sort addons.
	 *
	 * @param  array $a First item to compare.
	 * @param  array $b Second item to compare.
	 * @return bool
	 */
	protected function addons_cmp( $a, $b ) {
		if ( $a['position'] == $b['position'] ) {
			return 0;
		}

		return ( $a['position'] < $b['position'] ) ? -1 : 1;
	}
	
	/**
	 * AJAX: Save conditional logic rules
	 */
	public function ajax_save_conditional_rules() {
		check_ajax_referer( 'wc_pao_conditional_logic', 'nonce' );
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}
		
		$rules = isset( $_POST['rules'] ) ? $_POST['rules'] : array();
		$context = isset( $_POST['context'] ) ? sanitize_text_field( $_POST['context'] ) : 'global';
		
		// Save rules to database
		$result = update_option( 'wc_pao_conditional_rules_' . $context, $rules );
		
		if ( $result ) {
			wp_send_json_success( array(
				'message' => __( 'Rules saved successfully', 'woocommerce-product-addons-extra-digital' )
			) );
		} else {
			wp_send_json_error( array(
				'message' => __( 'Error saving rules', 'woocommerce-product-addons-extra-digital' )
			) );
		}
	}
	
	/**
	 * AJAX: Load conditional logic rules
	 */
	public function ajax_load_conditional_rules() {
		check_ajax_referer( 'wc_pao_conditional_logic', 'nonce' );
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}
		
		$context = isset( $_POST['context'] ) ? sanitize_text_field( $_POST['context'] ) : 'global';
		$context_id = isset( $_POST['context_id'] ) ? absint( $_POST['context_id'] ) : 0;
		
		// Load rules from database
		$option_key = 'wc_pao_conditional_rules_' . $context;
		if ( $context_id ) {
			$option_key .= '_' . $context_id;
		}
		
		$rules = get_option( $option_key, array() );
		
		wp_send_json_success( array(
			'rules' => $rules
		) );
	}
	
	/**
	 * AJAX: Test conditional logic rules
	 */
	public function ajax_test_conditional_rules() {
		check_ajax_referer( 'wc_pao_conditional_logic', 'nonce' );
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}
		
		$rules = isset( $_POST['rules'] ) ? $_POST['rules'] : array();
		
		// Validate rules structure
		$errors = array();
		$warnings = array();
		
		foreach ( $rules as $rule_id => $rule ) {
			// Check required fields
			if ( empty( $rule['target'] ) ) {
				$errors[] = sprintf( __( 'Rule %s: No target addon selected', 'woocommerce-product-addons-extra-digital' ), $rule_id );
			}
			
			if ( empty( $rule['conditions'] ) ) {
				$errors[] = sprintf( __( 'Rule %s: No conditions defined', 'woocommerce-product-addons-extra-digital' ), $rule_id );
			}
			
			if ( empty( $rule['actions'] ) ) {
				$errors[] = sprintf( __( 'Rule %s: No actions defined', 'woocommerce-product-addons-extra-digital' ), $rule_id );
			}
			
			// Check for circular dependencies
			if ( ! empty( $rule['conditions'] ) ) {
				foreach ( $rule['conditions'] as $condition ) {
					if ( $condition['type'] === 'rule' && $condition['target_rule'] === $rule_id ) {
						$errors[] = sprintf( __( 'Rule %s: Circular dependency detected', 'woocommerce-product-addons-extra-digital' ), $rule_id );
					}
				}
			}
		}
		
		if ( ! empty( $errors ) ) {
			wp_send_json_error( array(
				'errors' => $errors,
				'warnings' => $warnings
			) );
		} else {
			wp_send_json_success( array(
				'message' => __( 'All rules validated successfully', 'woocommerce-product-addons-extra-digital' ),
				'warnings' => $warnings
			) );
		}
	}
	
	/**
	 * AJAX: Get addon list for selectors
	 */
	public function ajax_get_addon_list() {
		check_ajax_referer( 'wc_pao_conditional_logic', 'nonce' );
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}
		
		$context = isset( $_POST['context'] ) ? sanitize_text_field( $_POST['context'] ) : 'global';
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		
		$addons = array();
		
		if ( $context === 'global' || $context === 'category' ) {
			// Get global addon groups
			$global_groups = WC_Product_Addons_Groups::get_all_global_groups();
			
			foreach ( $global_groups as $group ) {
				// Check both 'addons' and 'fields' keys as the structure might vary
				$group_addons = ! empty( $group['addons'] ) ? $group['addons'] : ( ! empty( $group['fields'] ) ? $group['fields'] : array() );
				
				if ( ! empty( $group_addons ) ) {
					foreach ( $group_addons as $addon ) {
						$addon_id = ! empty( $addon['name'] ) ? sanitize_title( $addon['name'] ) : 'addon_' . uniqid();
						$addon_name = ! empty( $addon['name'] ) ? $addon['name'] : ( ! empty( $addon['title'] ) ? $addon['title'] : 'Unnamed Addon' );
						$addon_type = ! empty( $addon['type'] ) ? $addon['type'] : 'unknown';
						
						$addons[] = array(
							'id' => $addon_id,
							'name' => $addon_name,
							'type' => $addon_type,
							'group' => ! empty( $group['name'] ) ? $group['name'] : 'Global Group'
						);
					}
				}
			}
			
			// If no global groups found, let's also check for product-level addons on all products
			if ( empty( $addons ) ) {
				// Get a few sample products to check for addons
				$products = wc_get_products( array( 'limit' => 10, 'status' => 'publish' ) );
				
				foreach ( $products as $product ) {
					$product_addons = WC_Product_Addons_Helper::get_product_addons( $product->get_id() );
					
					foreach ( $product_addons as $addon ) {
						$addon_id = ! empty( $addon['name'] ) ? sanitize_title( $addon['name'] ) : 'addon_' . uniqid();
						$addon_name = ! empty( $addon['name'] ) ? $addon['name'] : ( ! empty( $addon['title'] ) ? $addon['title'] : 'Unnamed Addon' );
						$addon_type = ! empty( $addon['type'] ) ? $addon['type'] : 'unknown';
						
						$addons[] = array(
							'id' => $addon_id,
							'name' => $addon_name . ' (Product: ' . $product->get_name() . ')',
							'type' => $addon_type,
							'product_id' => $product->get_id()
						);
					}
				}
			}
			
			// Add some demo addons if none exist
			if ( empty( $addons ) ) {
				$addons = array(
					array(
						'id' => 'size',
						'name' => 'Size',
						'type' => 'multiple_choice',
						'group' => 'Demo Addons'
					),
					array(
						'id' => 'color',
						'name' => 'Color',
						'type' => 'multiple_choice',
						'group' => 'Demo Addons'
					),
					array(
						'id' => 'gift_message',
						'name' => 'Gift Message',
						'type' => 'custom_textarea',
						'group' => 'Demo Addons'
					),
					array(
						'id' => 'gift_wrapping',
						'name' => 'Gift Wrapping',
						'type' => 'checkbox',
						'group' => 'Demo Addons'
					),
					array(
						'id' => 'custom_price',
						'name' => 'Name Your Price',
						'type' => 'custom_price',
						'group' => 'Demo Addons'
					)
				);
			}
		} elseif ( $context === 'product' && $product_id ) {
			// Get product-specific addons
			$product_addons = WC_Product_Addons_Helper::get_product_addons( $product_id );
			
			foreach ( $product_addons as $addon ) {
				$addon_id = ! empty( $addon['name'] ) ? sanitize_title( $addon['name'] ) : 'addon_' . uniqid();
				$addon_name = ! empty( $addon['name'] ) ? $addon['name'] : ( ! empty( $addon['title'] ) ? $addon['title'] : 'Unnamed Addon' );
				$addon_type = ! empty( $addon['type'] ) ? $addon['type'] : 'unknown';
				
				$addons[] = array(
					'id' => $addon_id,
					'name' => $addon_name,
					'type' => $addon_type
				);
			}
			
			// Add demo addons if none exist
			if ( empty( $addons ) ) {
				$addons = array(
					array(
						'id' => 'size',
						'name' => 'Size (Product Addon)',
						'type' => 'multiple_choice'
					),
					array(
						'id' => 'custom_text',
						'name' => 'Personalization',
						'type' => 'custom_text'
					)
				);
			}
		}
		
		wp_send_json_success( array(
			'addons' => $addons
		) );
	}
	
	/**
	 * AJAX: Save conditional logic rule
	 */
	public function ajax_save_rule() {
		check_ajax_referer( 'wc_pao_conditional_logic', 'security' );
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}
		
		global $wpdb;
		
		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
		$rule_data = isset( $_POST['rule_data'] ) ? $_POST['rule_data'] : array();
		
		// Validate rule data
		if ( empty( $rule_data['name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Rule name is required', 'woocommerce-product-addons' ) ) );
		}
		
		// Prepare data for database
		$rule_type = sanitize_text_field( $rule_data['scope'] );
		$scope_id = null;
		
		if ( $rule_type !== 'global' && ! empty( $rule_data['scope_targets'] ) ) {
			// For multiple targets, we'll create separate rules
			$scope_targets = array_map( 'absint', $rule_data['scope_targets'] );
		} else {
			$scope_targets = array( null );
		}
		
		$conditions = wp_json_encode( $rule_data['conditions'] );
		$actions = wp_json_encode( $rule_data['actions'] );
		
		$table_name = $wpdb->prefix . 'wc_product_addon_rules';
		
		if ( $rule_id ) {
			// Update existing rule
			$result = $wpdb->update(
				$table_name,
				array(
					'rule_name' => sanitize_text_field( $rule_data['name'] ),
					'rule_type' => $rule_type,
					'conditions' => $conditions,
					'actions' => $actions,
					'updated_at' => current_time( 'mysql' )
				),
				array( 'rule_id' => $rule_id ),
				array( '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			// Insert new rule(s)
			foreach ( $scope_targets as $scope_id ) {
				$result = $wpdb->insert(
					$table_name,
					array(
						'rule_name' => sanitize_text_field( $rule_data['name'] ),
						'rule_type' => $rule_type,
						'scope_id' => $scope_id,
						'conditions' => $conditions,
						'actions' => $actions,
						'created_by' => get_current_user_id(),
						'created_at' => current_time( 'mysql' ),
						'updated_at' => current_time( 'mysql' )
					),
					array( '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
				);
			}
		}
		
		if ( false !== $result ) {
			wp_send_json_success( array(
				'message' => __( 'Rule saved successfully', 'woocommerce-product-addons' )
			) );
		} else {
			wp_send_json_error( array(
				'message' => __( 'Error saving rule', 'woocommerce-product-addons' )
			) );
		}
	}
	
	/**
	 * AJAX: Get all rules
	 */
	public function ajax_get_rules() {
		check_ajax_referer( 'wc_pao_conditional_logic', 'security' );
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}
		
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'wc_product_addon_rules';
		$rules = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY rule_type, priority ASC, rule_id DESC" );
		
		$formatted_rules = array();
		
		foreach ( $rules as $rule ) {
			$conditions = json_decode( $rule->conditions, true );
			$actions = json_decode( $rule->actions, true );
			
			// Format scope label
			$scope_label = ucfirst( $rule->rule_type );
			if ( $rule->scope_id ) {
				if ( $rule->rule_type === 'product' ) {
					$product = wc_get_product( $rule->scope_id );
					if ( $product ) {
						$scope_label .= ': ' . $product->get_name();
					}
				} elseif ( $rule->rule_type === 'category' ) {
					$term = get_term( $rule->scope_id, 'product_cat' );
					if ( $term && ! is_wp_error( $term ) ) {
						$scope_label .= ': ' . $term->name;
					}
				}
			}
			
			// Format conditions summary
			$conditions_summary = array();
			foreach ( $conditions as $condition ) {
				if ( ! empty( $condition['type'] ) ) {
					$conditions_summary[] = $this->format_condition_summary( $condition );
				}
			}
			
			// Format actions summary
			$actions_summary = array();
			foreach ( $actions as $action ) {
				if ( ! empty( $action['type'] ) ) {
					$actions_summary[] = $this->format_action_summary( $action );
				}
			}
			
			$formatted_rules[] = array(
				'id' => $rule->rule_id,
				'name' => $rule->rule_name,
				'scope' => $rule->rule_type,
				'scope_label' => $scope_label,
				'priority' => $rule->priority,
				'status' => $rule->enabled ? 'active' : 'inactive',
				'status_label' => $rule->enabled ? __( 'Active', 'woocommerce-product-addons' ) : __( 'Inactive', 'woocommerce-product-addons' ),
				'conditions_summary' => implode( ' AND ', $conditions_summary ),
				'actions_summary' => implode( ', ', $actions_summary )
			);
		}
		
		wp_send_json_success( $formatted_rules );
	}
	
	/**
	 * AJAX: Get single rule
	 */
	public function ajax_get_rule() {
		check_ajax_referer( 'wc_pao_conditional_logic', 'security' );
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}
		
		global $wpdb;
		
		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
		
		if ( ! $rule_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid rule ID', 'woocommerce-product-addons' ) ) );
		}
		
		$table_name = $wpdb->prefix . 'wc_product_addon_rules';
		$rule = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE rule_id = %d", $rule_id ) );
		
		if ( ! $rule ) {
			wp_send_json_error( array( 'message' => __( 'Rule not found', 'woocommerce-product-addons' ) ) );
		}
		
		$rule_data = array(
			'id' => $rule->rule_id,
			'name' => $rule->rule_name,
			'scope' => $rule->rule_type,
			'scope_targets' => array(),
			'conditions' => json_decode( $rule->conditions, true ),
			'actions' => json_decode( $rule->actions, true ),
			'condition_logic' => 'AND' // Default for now
		);
		
		// Get scope targets
		if ( $rule->scope_id ) {
			if ( $rule->rule_type === 'product' ) {
				$product = wc_get_product( $rule->scope_id );
				if ( $product ) {
					$rule_data['scope_targets'][$rule->scope_id] = $product->get_name();
				}
			} elseif ( $rule->rule_type === 'category' ) {
				$term = get_term( $rule->scope_id, 'product_cat' );
				if ( $term && ! is_wp_error( $term ) ) {
					$rule_data['scope_targets'][$rule->scope_id] = $term->name;
				}
			}
		}
		
		wp_send_json_success( $rule_data );
	}
	
	/**
	 * AJAX: Delete rule
	 */
	public function ajax_delete_rule() {
		check_ajax_referer( 'wc_pao_conditional_logic', 'security' );
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}
		
		global $wpdb;
		
		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
		
		if ( ! $rule_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid rule ID', 'woocommerce-product-addons' ) ) );
		}
		
		$table_name = $wpdb->prefix . 'wc_product_addon_rules';
		$result = $wpdb->delete( $table_name, array( 'rule_id' => $rule_id ), array( '%d' ) );
		
		if ( false !== $result ) {
			wp_send_json_success( array( 'message' => __( 'Rule deleted successfully', 'woocommerce-product-addons' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Error deleting rule', 'woocommerce-product-addons' ) ) );
		}
	}
	
	/**
	 * AJAX: Toggle rule status
	 */
	public function ajax_toggle_rule() {
		check_ajax_referer( 'wc_pao_conditional_logic', 'security' );
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}
		
		global $wpdb;
		
		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
		
		if ( ! $rule_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid rule ID', 'woocommerce-product-addons' ) ) );
		}
		
		$table_name = $wpdb->prefix . 'wc_product_addon_rules';
		
		// Get current status
		$current_status = $wpdb->get_var( $wpdb->prepare( "SELECT enabled FROM {$table_name} WHERE rule_id = %d", $rule_id ) );
		
		if ( null === $current_status ) {
			wp_send_json_error( array( 'message' => __( 'Rule not found', 'woocommerce-product-addons' ) ) );
		}
		
		// Toggle status
		$new_status = $current_status ? 0 : 1;
		$result = $wpdb->update(
			$table_name,
			array( 'enabled' => $new_status ),
			array( 'rule_id' => $rule_id ),
			array( '%d' ),
			array( '%d' )
		);
		
		if ( false !== $result ) {
			wp_send_json_success( array( 
				'status' => $new_status ? 'active' : 'inactive',
				'message' => __( 'Rule status updated', 'woocommerce-product-addons' )
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Error updating rule status', 'woocommerce-product-addons' ) ) );
		}
	}
	
	/**
	 * AJAX: Duplicate rule
	 */
	public function ajax_duplicate_rule() {
		check_ajax_referer( 'wc_pao_conditional_logic', 'security' );
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}
		
		global $wpdb;
		
		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
		
		if ( ! $rule_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid rule ID', 'woocommerce-product-addons' ) ) );
		}
		
		$table_name = $wpdb->prefix . 'wc_product_addon_rules';
		$rule = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE rule_id = %d", $rule_id ), ARRAY_A );
		
		if ( ! $rule ) {
			wp_send_json_error( array( 'message' => __( 'Rule not found', 'woocommerce-product-addons' ) ) );
		}
		
		// Remove the ID and update timestamps
		unset( $rule['rule_id'] );
		$rule['rule_name'] = $rule['rule_name'] . ' (Copy)';
		$rule['created_at'] = current_time( 'mysql' );
		$rule['updated_at'] = current_time( 'mysql' );
		$rule['created_by'] = get_current_user_id();
		
		$result = $wpdb->insert( $table_name, $rule );
		
		if ( false !== $result ) {
			wp_send_json_success( array( 'message' => __( 'Rule duplicated successfully', 'woocommerce-product-addons' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Error duplicating rule', 'woocommerce-product-addons' ) ) );
		}
	}
	
	/**
	 * AJAX: Get all addons for dropdown
	 */
	public function ajax_get_all_addons() {
		check_ajax_referer( 'wc_pao_conditional_logic', 'security' );
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}
		
		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
		$include_products = isset( $_GET['include_products'] ) ? (bool) $_GET['include_products'] : true;
		$addons = array(
			'global' => array(),
			'products' => array(),
			'organized' => array()
		);
		
		// Get global addon groups
		$global_groups = WC_Product_Addons_Groups::get_all_global_groups();
		
		foreach ( $global_groups as $group ) {
			// Fix: Use 'fields' instead of 'addons'
			if ( ! empty( $group['fields'] ) ) {
				foreach ( $group['fields'] as $addon ) {
					$addon_id = sanitize_title( $addon['name'] ) . '_global_' . $group['id'];
					$options = array();
					
					// Process addon options if they exist
					if ( ! empty( $addon['options'] ) && is_array( $addon['options'] ) ) {
						foreach ( $addon['options'] as $index => $option ) {
							$options[] = array(
								'value' => isset( $option['label'] ) ? $option['label'] : 'Option ' . ($index + 1),
								'label' => isset( $option['label'] ) ? $option['label'] : 'Option ' . ($index + 1),
								'price' => isset( $option['price'] ) ? $option['price'] : 0
							);
						}
					}
					
					$addon_data = array(
						'name' => $addon['name'],
						'display_name' => $addon['name'] . ' (Global: ' . $group['name'] . ')',
						'type' => $addon['type'],
						'field_name' => isset( $addon['field_name'] ) ? $addon['field_name'] : sanitize_title( $addon['name'] ),
						'options' => $options,
						'source' => 'global',
						'group_id' => $group['id'],
						'group_name' => $group['name']
					);
					
					$addons['global'][$addon_id] = $addon_data;
					$addons['organized'][$addon_id] = $addon_data;
				}
			}
		}
		
		// Get product-specific addons from multiple products if requested
		if ( $include_products ) {
			if ( $product_id > 0 ) {
				// Get specific product addons
				$this->add_product_addons_to_list( $addons, $product_id );
			} else {
				// Get addons from products that have them
				$products_with_addons = $this->get_products_with_addons();
				foreach ( $products_with_addons as $prod_id ) {
					$this->add_product_addons_to_list( $addons, $prod_id );
				}
			}
		}
		
		wp_send_json_success( $addons );
	}
	
	/**
	 * Add product-specific addons to the addons list
	 */
	private function add_product_addons_to_list( &$addons, $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}
		
		$product_addons = get_post_meta( $product_id, '_product_addons', true );
		if ( is_array( $product_addons ) && ! empty( $product_addons ) ) {
			$product_title = $product->get_name();
			
			foreach ( $product_addons as $index => $addon ) {
				$addon_id = sanitize_title( $addon['name'] ) . '_product_' . $product_id . '_' . $index;
				$options = array();
				
				// Process addon options if they exist
				if ( ! empty( $addon['options'] ) && is_array( $addon['options'] ) ) {
					foreach ( $addon['options'] as $opt_index => $option ) {
						$options[] = array(
							'value' => isset( $option['label'] ) ? $option['label'] : 'Option ' . ($opt_index + 1),
							'label' => isset( $option['label'] ) ? $option['label'] : 'Option ' . ($opt_index + 1),
							'price' => isset( $option['price'] ) ? $option['price'] : 0
						);
					}
				}
				
				$addon_data = array(
					'name' => $addon['name'],
					'display_name' => $addon['name'] . ' (Product: ' . $product_title . ')',
					'type' => $addon['type'],
					'field_name' => isset( $addon['field_name'] ) ? $addon['field_name'] : sanitize_title( $addon['name'] ),
					'options' => $options,
					'source' => 'product',
					'product_id' => $product_id,
					'product_name' => $product_title
				);
				
				if ( ! isset( $addons['products'][$product_id] ) ) {
					$addons['products'][$product_id] = array(
						'product_name' => $product_title,
						'addons' => array()
					);
				}
				
				$addons['products'][$product_id]['addons'][$addon_id] = $addon_data;
				$addons['organized'][$addon_id] = $addon_data;
			}
		}
	}
	
	/**
	 * Get products that have addons
	 */
	private function get_products_with_addons( $limit = 50 ) {
		global $wpdb;
		
		$product_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT post_id 
			FROM {$wpdb->postmeta} 
			WHERE meta_key = '_product_addons' 
			AND meta_value != '' 
			AND meta_value != 'a:0:{}' 
			LIMIT %d",
			$limit
		) );
		
		return array_map( 'absint', $product_ids );
	}
	
	/**
	 * AJAX: Search categories
	 */
	public function ajax_search_categories() {
		check_ajax_referer( 'wc_pao_conditional_logic', 'security' );
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}
		
		$term = isset( $_GET['term'] ) ? sanitize_text_field( $_GET['term'] ) : '';
		
		$categories = get_terms( array(
			'taxonomy' => 'product_cat',
			'hide_empty' => false,
			'search' => $term,
			'number' => 20
		) );
		
		$results = array();
		
		foreach ( $categories as $category ) {
			$results[] = array(
				'id' => $category->term_id,
				'text' => $category->name
			);
		}
		
		wp_send_json( $results );
	}
	
	/**
	 * Format condition summary for display
	 */
	private function format_condition_summary( $condition ) {
		$type_labels = array(
			'addon_field' => __( 'Add-on field', 'woocommerce-product-addons' ),
			'addon_selected' => __( 'Add-on option', 'woocommerce-product-addons' ),
			'product_price' => __( 'Product price', 'woocommerce-product-addons' ),
			'product_stock' => __( 'Product stock', 'woocommerce-product-addons' ),
			'product_category' => __( 'Product category', 'woocommerce-product-addons' ),
			'cart_total' => __( 'Cart total', 'woocommerce-product-addons' ),
			'cart_quantity' => __( 'Cart quantity', 'woocommerce-product-addons' ),
			'user_role' => __( 'User role', 'woocommerce-product-addons' ),
			'user_logged_in' => __( 'User logged in', 'woocommerce-product-addons' ),
			'current_date' => __( 'Current date', 'woocommerce-product-addons' ),
			'current_time' => __( 'Current time', 'woocommerce-product-addons' ),
			'day_of_week' => __( 'Day of week', 'woocommerce-product-addons' )
		);
		
		$type_label = isset( $type_labels[$condition['type']] ) ? $type_labels[$condition['type']] : $condition['type'];
		
		return $type_label;
	}
	
	/**
	 * Format action summary for display
	 */
	private function format_action_summary( $action ) {
		$type_labels = array(
			'show_addon' => __( 'Show add-on', 'woocommerce-product-addons' ),
			'hide_addon' => __( 'Hide add-on', 'woocommerce-product-addons' ),
			'show_option' => __( 'Show option', 'woocommerce-product-addons' ),
			'hide_option' => __( 'Hide option', 'woocommerce-product-addons' ),
			'set_price' => __( 'Set price', 'woocommerce-product-addons' ),
			'adjust_price' => __( 'Adjust price', 'woocommerce-product-addons' ),
			'make_required' => __( 'Make required', 'woocommerce-product-addons' ),
			'make_optional' => __( 'Make optional', 'woocommerce-product-addons' ),
			'set_label' => __( 'Change label', 'woocommerce-product-addons' ),
			'set_description' => __( 'Change description', 'woocommerce-product-addons' )
		);
		
		$type_label = isset( $type_labels[$action['type']] ) ? $type_labels[$action['type']] : $action['type'];
		
		return $type_label;
	}
	
	/**
	 * AJAX: Update rule priorities
	 */
	public function ajax_update_rule_priorities() {
		check_ajax_referer( 'wc_pao_conditional_logic', 'security' );
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}
		
		global $wpdb;
		
		$rule_ids = isset( $_POST['rule_ids'] ) ? array_map( 'absint', $_POST['rule_ids'] ) : array();
		
		if ( empty( $rule_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No rules provided', 'woocommerce-product-addons' ) ) );
		}
		
		$table_name = $wpdb->prefix . 'wc_product_addon_rules';
		
		// Update priorities - lower position in array = higher priority (higher number)
		// This way rules at the bottom of the list have higher priority
		$total_rules = count( $rule_ids );
		$success_count = 0;
		
		foreach ( $rule_ids as $index => $rule_id ) {
			// Calculate priority: last item gets highest priority
			$priority = $total_rules - $index;
			
			$result = $wpdb->update(
				$table_name,
				array( 
					'priority' => $priority,
					'updated_at' => current_time( 'mysql' )
				),
				array( 'rule_id' => $rule_id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
			
			if ( false !== $result ) {
				$success_count++;
			}
		}
		
		if ( $success_count === count( $rule_ids ) ) {
			wp_send_json_success( array(
				'message' => sprintf( 
					__( 'Updated priorities for %d rules', 'woocommerce-product-addons' ),
					$success_count
				)
			) );
		} else {
			wp_send_json_error( array(
				'message' => sprintf(
					__( 'Updated %d of %d rules', 'woocommerce-product-addons' ),
					$success_count,
					count( $rule_ids )
				)
			) );
		}
	}
}
