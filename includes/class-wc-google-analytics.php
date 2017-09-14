<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class WC_Google_Analytics extends WC_Integration {


	public function __construct() {
		$this->id                    = 'google_analytics';
		$this->method_title          = __( 'Google Analytics', 'woocommerce-google-analytics-integration' );
		$this->method_description    = __( 'Google Analytics is a free service offered by Google that generates detailed statistics about the visitors to a website.', 'woocommerce-google-analytics-integration' );
		$this->dismissed_info_banner = get_option( 'woocommerce_dismissed_info_banner' );

		// Load the settings
		$this->init_form_fields();
		$this->init_settings();
		$constructor = $this->init_options();

		include_once('class-wc-tealium-integration-js.php');
		WC_Google_Analytics_Tealium_JS::get_instance( $constructor );

		if ( is_admin() ) {
//			include_once( 'class-wc-google-analytics-info-banner.php' );
//            WC_Google_Analytics_Info_Banner::get_instance( $this->dismissed_info_banner, $this->ga_id );
		}

		add_filter( 'woocommerce_tracker_data', array( $this, 'track_options' ) );
		add_action( 'woocommerce_update_options_integration_google_analytics', array( $this, 'process_admin_options') );
		add_action( 'woocommerce_update_options_integration_google_analytics', array( $this, 'show_options_info') );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_assets') );

		add_action( 'wp_head', array( $this, 'tracking_code_display' ), 999999 );

		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'add_to_cart' ) );
		add_action( 'wp_footer', array( $this, 'loop_add_to_cart' ) );
		add_action( 'woocommerce_after_cart', array( $this, 'remove_from_cart' ) );
		add_action( 'woocommerce_after_mini_cart', array( $this, 'remove_from_cart' ) );
		add_filter( 'woocommerce_cart_item_remove_link', array( $this, 'remove_from_cart_attributes' ), 10, 2 );
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'listing_impression' ) );
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'listing_click' ) );
		add_action( 'woocommerce_after_single_product', array( $this, 'product_detail' ) );
		add_action( 'woocommerce_after_checkout_form', array( $this, 'checkout_process' ) );

		add_filter( 'woocommerce_get_return_url', array( $this, 'utm_nooverride' ) );
	}


	public function init_options() {
		$options = array(
			'ga_id',
			'ga_set_domain_name',
			'ga_standard_tracking_enabled',
			'ga_support_display_advertising',
			'ga_use_universal_analytics',
			'ga_anonymize_enabled',
			'ga_ecommerce_tracking_enabled',
			'ga_enhanced_ecommerce_tracking_enabled',
			'ga_enhanced_remove_from_cart_enabled',
			'ga_enhanced_product_impression_enabled',
			'ga_enhanced_product_click_enabled',
			'ga_enhanced_checkout_process_enabled',
			'ga_enhanced_product_detail_view_enabled',
			'ga_event_tracking_enabled'
		);

		$constructor = array();
		foreach ( $options as $option ) {
			$constructor[ $option ] = $this->$option = $this->get_option( $option );
		}

		return $constructor;
	}


	public function init_form_fields() {
		$this->form_fields = array(
			'ga_id' => array(
				'title'       => __( 'Google Analytics ID', 'woocommerce-google-analytics-integration' ),
				'description' => __( 'Log into your Google Analytics account to find your ID. e.g. <code>UA-XXXXX-X</code>', 'woocommerce-google-analytics-integration' ),
				'type'        => 'text',
				'placeholder' => 'UA-XXXXX-X',
				'default'     => get_option( 'woocommerce_ga_id' ) // Backwards compat
			),
			'ga_set_domain_name' => array(
				'title' 			=> __( 'Set Domain Name', 'woocommerce-google-analytics-integration' ),
				'description' 		=> sprintf( __( '(Optional) Sets the <code>_setDomainName</code> variable. <a href="%s">See here for more information</a>.', 'woocommerce-google-analytics-integration' ), 'https://developers.google.com/analytics/devguides/collection/gajs/gaTrackingSite#multipleDomains' ),
				'type' 				=> 'text',
				'default' 			=> ''
			),
			'ga_standard_tracking_enabled' => array(
				'title'         => __( 'Tracking Options', 'woocommerce-google-analytics-integration' ),
				'label'         => __( 'Enable Standard Tracking', 'woocommerce-google-analytics-integration' ),
				'description'   =>  __( 'This tracks session data such as demographics, system, etc. You don\'t need to enable this if you are using a 3rd party Google analytics plugin.', 'woocommerce-google-analytics-integration' ),
				'type'          => 'checkbox',
				'checkboxgroup' => 'start',
				'default'       => get_option( 'woocommerce_ga_standard_tracking_enabled' ) ? get_option( 'woocommerce_ga_standard_tracking_enabled' ) : 'no'  // Backwards compat
			),
			'ga_support_display_advertising' => array(
				'label'         => __( '"Display Advertising" Support', 'woocommerce-google-analytics-integration' ),
				'description'   => sprintf( __( 'Set the Google Analytics code to support Display Advertising. %sRead more about Display Advertising%s.', 'woocommerce-google-analytics-integration' ), '<a href="https://support.google.com/analytics/answer/2700409" target="_blank">', '</a>' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => get_option( 'woocommerce_ga_support_display_advertising' ) ? get_option( 'woocommerce_ga_support_display_advertising' ) : 'no'  // Backwards compat
			),
			'ga_use_universal_analytics' => array(
				'label'         => __( 'Enable Universal Analytics', 'woocommerce-google-analytics-integration' ),
				'description'   => sprintf( __( 'Uses Universal Analytics instead of Classic Google Analytics. If you have <strong>not</strong> previously used Google Analytics on this site, check this box. Otherwise, %sfollow step 1 of the Universal Analytics upgrade guide.%s Enabling this setting will take care of step 2. %sRead more about Universal Analytics%s. Universal Analytics must be enabled to enable enhanced eCommerce.', 'woocommerce-google-analytics-integration' ), '<a href="https://developers.google.com/analytics/devguides/collection/upgrade/guide" target="_blank">', '</a>', '<a href="https://support.google.com/analytics/answer/2790010?hl=en" target="_blank">', '</a>' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => get_option( 'woocommerce_ga_use_universal_analytics' ) ? get_option( 'woocommerce_ga_use_universal_analytics' ) : 'no'  // Backwards compat
			),
			'ga_anonymize_enabled' => array(
				'label'         => __( 'Anonymize IP addresses.', 'woocommerce-google-analytics-integration' ),
				'description'   => sprintf( __( 'Enabling this option is mandatory in certain countries due to national privacy laws. %sRead more about IP Anonymization%s.', 'woocommerce-google-analytics-integration' ), '<a href="https://support.google.com/analytics/answer/2763052" target="_blank">', '</a>' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => 'yes'
			),
			'ga_ecommerce_tracking_enabled' => array(
				'label' 			=> __( 'Purchase Transactions', 'woocommerce-google-analytics-integration' ),
				'description' 			=> __( 'This requires a payment gateway that redirects to the thank you/order received page after payment. Orders paid with gateways which do not do this will not be tracked.', 'woocommerce-google-analytics-integration' ),
				'type' 				=> 'checkbox',
				'checkboxgroup'		=> 'start',
				'default' 			=> get_option( 'woocommerce_ga_ecommerce_tracking_enabled' ) ? get_option( 'woocommerce_ga_ecommerce_tracking_enabled' ) : 'yes'  // Backwards compat
			),
			'ga_event_tracking_enabled' => array(
				'label' 			=> __( 'Add to Cart Events', 'woocommerce-google-analytics-integration' ),
				'type' 				=> 'checkbox',
				'checkboxgroup'		=> '',
				'default' 			=> 'yes'
			),

			'ga_enhanced_ecommerce_tracking_enabled' => array(
				'title'         => __( 'Enhanced eCommerce', 'woocommerce-google-analytics-integration' ),
				'label'         => __( 'Enable Enhanced eCommerce ', 'woocommerce-google-analytics-integration' ),
				'description'   => sprintf( __( 'Enhanced eCommerce allows you to measure more user interactions with your store, including: product impressions, product detail views, starting the checkout process, adding cart items, and removing cart items. Universal Analytics must be enabled for Enhanced eCommerce to work. Before enabling this setting, turn on Enhanced eCommerce in your Google Analytics dashboard. <a href="%s">See here for more information</a>.', 'woocommerce-google-analytics-integration' ), 'https://support.google.com/analytics/answer/6032539?hl=en' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => 'no'
			),

			// Enhanced eCommerce Settings

			'ga_enhanced_remove_from_cart_enabled' => array(
				'label' 			=> __( 'Remove from Cart Events', 'woocommerce-google-analytics-integration' ),
				'type' 				=> 'checkbox',
				'checkboxgroup'		=> '',
				'default' 			=> 'yes',
				'class'             => 'enhanced-setting'
			),

			'ga_enhanced_product_impression_enabled' => array(
				'label' 			=> __( 'Product Impressions from Listing Pages', 'woocommerce-google-analytics-integration' ),
				'type' 				=> 'checkbox',
				'checkboxgroup'		=> '',
				'default' 			=> 'yes',
				'class'             => 'enhanced-setting'
			),

			'ga_enhanced_product_click_enabled' => array(
				'label' 			=> __( 'Product Clicks from Listing Pages', 'woocommerce-google-analytics-integration' ),
				'type' 				=> 'checkbox',
				'checkboxgroup'		=> '',
				'default' 			=> 'yes',
				'class'             => 'enhanced-setting'
			),

			'ga_enhanced_product_detail_view_enabled' => array(
				'label' 			=> __( 'Product Detail Views', 'woocommerce-google-analytics-integration' ),
				'type' 				=> 'checkbox',
				'checkboxgroup'		=> '',
				'default' 			=> 'yes',
				'class'             => 'enhanced-setting'
			),

			'ga_enhanced_checkout_process_enabled' => array(
				'label' 			=> __( 'Checkout Process Initiated', 'woocommerce-google-analytics-integration' ),
				'type' 				=> 'checkbox',
				'checkboxgroup'		=> '',
				'default' 			=> 'yes',
				'class'             => 'enhanced-setting'
			),
		);
	}


	function show_options_info() {
		$this->method_description .= "<div class='notice notice-info'><p>" . __( 'Please allow Google Analytics 24 hours to start displaying results.', 'woocommerce-google-analytics-integration' ) . "</p></div>";

		if ( isset( $_REQUEST['woocommerce_google_analytics_ga_ecommerce_tracking_enabled'] ) && true === (bool) $_REQUEST['woocommerce_google_analytics_ga_ecommerce_tracking_enabled'] ) {
			$this->method_description .= "<div class='notice notice-info'><p>" . __( 'Please note, for transaction tracking to work properly, you will need to use a payment gateway that redirects the customer back to a WooCommerce order received/thank you page.', 'woocommerce-google-analytics-integration' ) . "</div>";
		}
	}


	function track_options( $data ) {
		$data['wc-google-analytics'] = array(
			'standard_tracking_enabled'   => $this->ga_standard_tracking_enabled,
			'support_display_advertising' => $this->ga_support_display_advertising,
			'use_universal_analytics'     => $this->ga_use_universal_analytics,
			'anonymize_enabled'           => $this->ga_anonymize_enabled,
			'ecommerce_tracking_enabled'  => $this->ga_ecommerce_tracking_enabled,
			'event_tracking_enabled'      => $this->ga_event_tracking_enabled
		);
		return $data;
	}


	function load_admin_assets() {
		$screen = get_current_screen();
		if ( 'woocommerce_page_wc-settings' !== $screen->id ) {
			return;
		}

		if ( empty( $_GET['tab'] ) ) {
			return;
		}

		if ( 'integration' !== $_GET['tab'] ) {
			return;
		}

		wp_enqueue_script( 'wc-google-analytics-admin-enhanced-settings', plugins_url(  '/assets/js/admin-enhanced-settings.js', dirname(__FILE__) ) );
	}


	public function tracking_code_display() {
		global $wp;
		$display_ecommerce_tracking = false;

		if ( is_admin() || current_user_can( 'manage_options' ) || ! $this->ga_id ) {
			return;
		}

		if ( is_order_received_page() && 'yes' === $this->ga_ecommerce_tracking_enabled ) {
			$order_id = isset( $wp->query_vars['order-received'] ) ? $wp->query_vars['order-received'] : 0;
			if ( 0 < $order_id && 1 != get_post_meta( $order_id, '_ga_tracked', true ) ) {
				$display_ecommerce_tracking = true;
				echo $this->get_ecommerce_tracking_code( $order_id );
			}
		}

		if ( is_woocommerce() || is_cart() || ( is_checkout() && ! $display_ecommerce_tracking ) ) {
			$display_ecommerce_tracking = true;
			echo $this->get_standard_tracking_code();
		}

		if ( ! $display_ecommerce_tracking && 'yes' === $this->ga_standard_tracking_enabled ) {
			echo $this->get_standard_tracking_code();
		}
	}


	protected function get_standard_tracking_code() {
		return "<!-- WooCommerce Google Analytics Integration -->
		" . WC_Google_Analytics_Tealium_JS::get_instance()->header() . "
		<script type='text/javascript'>" . WC_Google_Analytics_Tealium_JS::get_instance()->load_analytics() . "</script>
		<!-- /WooCommerce Google Analytics Integration -->";
	}


	protected function get_ecommerce_tracking_code( $order_id ) {
		$order = new WC_Order( $order_id );

		$code = WC_Google_Analytics_Tealium_JS::get_instance()->load_analytics( $order );
		$code .= WC_Google_Analytics_Tealium_JS::get_instance()->add_transaction( $order );

		update_post_meta( $order_id, '_ga_tracked', 1 );

		return "
		" . WC_Google_Analytics_Tealium_JS::get_instance()->header() . "
		<script type='text/javascript'>$code</script>
		";
	}


	private function disable_tracking( $type ) {
		if ( is_admin() || current_user_can( 'manage_options' ) || ( ! $this->ga_id ) || 'no' === $type ) {
			return true;
		}
	}


	public function add_to_cart() {
		if ( $this->disable_tracking( $this->ga_event_tracking_enabled ) ) {
			return;
		}
		if ( ! is_single() ) {
			return;
		}

		global $product;

		$parameters = array();
		$parameters['category'] = "'" . __( 'Products', 'woocommerce-google-analytics-integration' ) . "'";
		$parameters['action']   = "'" . __( 'Add to Cart', 'woocommerce-google-analytics-integration' ) . "'";
		$parameters['label']    = "'" . esc_js( $product->get_sku() ? __( 'SKU:', 'woocommerce-google-analytics-integration' ) . ' ' . $product->get_sku() : "#" . $product->id ) . "'";

		if ( ! $this->disable_tracking( $this->ga_enhanced_ecommerce_tracking_enabled ) ) {
			$code = "" . WC_Google_Analytics_Tealium_JS::get_instance()->tracker_var() . "( 'ec:addProduct', {";
			$code .= "'id': '" . esc_js( $product->get_sku() ? $product->get_sku() : $product->id ) . "',";
			$code .= "'quantity': $( 'input.qty' ).val() ? $( 'input.qty' ).val() : '1'";
			$code .= "} );";
			$parameters['enhanced'] = $code;
		}

		WC_Google_Analytics_Tealium_JS::get_instance()->event_tracking_code( $parameters, '.single_add_to_cart_button' );
	}


	public function remove_from_cart() {
		if ( $this->disable_tracking( $this->ga_use_universal_analytics ) ) {
			return;
		}

		if ( $this->disable_tracking( $this->ga_enhanced_ecommerce_tracking_enabled ) ) {
			return;
		}

		if ( $this->disable_tracking( $this->ga_enhanced_remove_from_cart_enabled ) ) {
			return;
		}

		WC_Google_Analytics_Tealium_JS::get_instance()->remove_from_cart();
	}


	public function remove_from_cart_attributes( $url, $key ) {
		if ( strpos( $url,'data-product_id' ) !== false ) {
			return $url;
		}

		$item = WC()->cart->get_cart_item( $key );
		$product = $item['data'];
		$url = str_replace( 'href=', 'data-product_id="' . esc_attr( $product->id ) . '" data-product_sku="' . esc_attr( $product->get_sku() )  . '" href=', $url );
		return $url;
	}


	public function loop_add_to_cart() {

		if ( $this->disable_tracking( $this->ga_event_tracking_enabled ) ) {
			return;
		}

		$parameters = array();
		$parameters['category'] = "'" . __( 'Products', 'woocommerce-google-analytics-integration' ) . "'";
		$parameters['action']   = "'" . __( 'Add to Cart', 'woocommerce-google-analytics-integration' ) . "'";
		$parameters['label']    = "($(this).data('product_sku')) ? ('SKU: ' + $(this).data('product_sku')) : ('#' + $(this).data('product_id'))"; // Product SKU or ID

		if ( ! $this->disable_tracking( $this->ga_enhanced_ecommerce_tracking_enabled ) ) {
			$code = "" . WC_Google_Analytics_Tealium_JS::get_instance()->tracker_var() . "( 'ec:addProduct', {";
			$code .= "'id': ($(this).data('product_sku')) ? ('SKU: ' + $(this).data('product_sku')) : ('#' + $(this).data('product_id')),";
			$code .= "'quantity': $(this).data('quantity')";
			$code .= "} );";
			$parameters['enhanced'] = $code;
		}

		WC_Google_Analytics_Tealium_JS::get_instance()->event_tracking_code( $parameters, '.add_to_cart_button:not(.product_type_variable, .product_type_grouped)' );
	}


	public function listing_impression() {
		if ( $this->disable_tracking( $this->ga_use_universal_analytics ) ) {
			return;
		}

		if ( $this->disable_tracking( $this->ga_enhanced_ecommerce_tracking_enabled ) ) {
			return;
		}

		if ( $this->disable_tracking( $this->ga_enhanced_product_impression_enabled ) ) {
			return;
		}

		global $product, $woocommerce_loop;
		WC_Google_Analytics_Tealium_JS::get_instance()->listing_impression( $product, $woocommerce_loop['loop'] );
	}


	public function listing_click() {
		if ( $this->disable_tracking( $this->ga_use_universal_analytics ) ) {
			return;
		}

		if ( $this->disable_tracking( $this->ga_enhanced_ecommerce_tracking_enabled ) ) {
			return;
		}

		if ( $this->disable_tracking( $this->ga_enhanced_product_click_enabled ) ) {
			return;
		}

		global $product, $woocommerce_loop;
		WC_Google_Analytics_Tealium_JS::get_instance()->listing_click( $product, $woocommerce_loop['loop'] );
	}


	public function product_detail() {
		if ( $this->disable_tracking( $this->ga_use_universal_analytics ) ) {
			return;
		}

		if ( $this->disable_tracking( $this->ga_enhanced_ecommerce_tracking_enabled ) ) {
			return;
		}

		if ( $this->disable_tracking( $this->ga_enhanced_product_detail_view_enabled ) ) {
			return;
		}

		global $product;
		WC_Google_Analytics_Tealium_JS::get_instance()->product_detail( $product );

	}

	public function checkout_process( $checkout ) {
		if ( $this->disable_tracking( $this->ga_use_universal_analytics ) ) {
			return;
		}

		if ( $this->disable_tracking( $this->ga_enhanced_ecommerce_tracking_enabled ) ) {
			return;
		}

		if ( $this->disable_tracking( $this->ga_enhanced_checkout_process_enabled ) ) {
			return;
		}

		WC_Google_Analytics_Tealium_JS::get_instance()->checkout_process( WC()->cart->get_cart() );
	}

	public function utm_nooverride( $return_url ) {
		$return_url = remove_query_arg( 'utm_nooverride', $return_url );

		$return_url = add_query_arg( 'utm_nooverride', '1', $return_url );

		return $return_url;
	}
}
