<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WC_Tealium_Integration extends WC_Google_Analytics {
	
	/**
	 * Init and hook in the integration.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->id                    = 'google_analytics';
		$this->method_title          = __( 'Google Analytics', 'woocommerce-google-analytics-integration' );
		$this->method_description    = __( 'Google Analytics is a free service offered by Google that generates detailed statistics about the visitors to a website.', 'woocommerce-google-analytics-integration' );
		$this->dismissed_info_banner = get_option( 'woocommerce_dismissed_info_banner' );

		// Load the settings
		$this->init_form_fields();
		$this->init_settings();
		$constructor = $this->init_options();

		// // Contains snippets/JS tracking code
		include_once('class-wc-tealium-integration-js.php');
        WC_Tealium_Integration_JS::get_instance( $constructor );

		// Display an info banner on how to configure WooCommerce
		if ( is_admin() ) {
//			include_once( 'class-wc-google-analytics-info-banner.php' );
//			WC_Google_Analytics_Info_Banner::get_instance( $this->dismissed_info_banner, $this->ga_id );
		}

		// Admin Options
		add_filter( 'woocommerce_tracker_data', array( $this, 'track_options' ) );
		add_action( 'woocommerce_update_options_integration_google_analytics', array( $this, 'process_admin_options') );
		add_action( 'woocommerce_update_options_integration_google_analytics', array( $this, 'show_options_info') );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_assets') );

		// Tracking code
		add_action( 'wp_head', array( $this, 'tracking_code_display' ), 999999 );

		// Event tracking code
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'add_to_cart' ) );
		add_action( 'wp_footer', array( $this, 'loop_add_to_cart' ) );
		add_action( 'woocommerce_after_cart', array( $this, 'remove_from_cart' ) );
		add_action( 'woocommerce_after_mini_cart', array( $this, 'remove_from_cart' ) );
		add_filter( 'woocommerce_cart_item_remove_link', array( $this, 'remove_from_cart_attributes' ), 10, 2 );
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'listing_impression' ) );
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'listing_click' ) );
		add_action( 'woocommerce_after_single_product', array( $this, 'product_detail' ) );
		add_action( 'woocommerce_after_checkout_form', array( $this, 'checkout_process' ) );

		// utm_nooverride parameter for Google AdWords
		add_filter( 'woocommerce_get_return_url', array( $this, 'utm_nooverride' ) );
		
		//parent::__construct();
	}

	public function tracking_code_display() {
		global $wp;
		$display_ecommerce_tracking = false;

		// origin
		//if ( is_admin() || current_user_can( 'manage_options' ) || ! $this->ga_id ) {
		
		if ( is_admin() || current_user_can( 'manage_options' ) ) {	
			return;
		}

		// Check if is order received page and stop when the products and not tracked
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
		return "<!-- WooCommerce Tealium Integration -->
		" . WC_Tealium_Integration_JS::get_instance()->header() . "
		<script type='text/javascript'>" . WC_Google_Analytics_TEALIUM_JS::get_instance()->load_analytics() . "</script>
		<!-- /WooCommerce Tealium Integration -->";
	}

	protected function get_ecommerce_tracking_code( $order_id ) {
		// Get the order and output tracking code
		$order = new WC_Order( $order_id );

		$code = WC_Tealium_Integration_JS::get_instance()->load_analytics( $order );
		$code .= WC_Tealium_Integration_JS::get_instance()->add_transaction( $order );

		// Mark the order as tracked
		update_post_meta( $order_id, '_ga_tracked', 1 );

		return "
		<!-- WooCommerce Google Analytics via Tealium Integration -->
		" . WC_Tealium_Integration_JS::get_instance()->header() . "
		<script type='text/javascript'>$code</script>
		<!-- /WooCommerce Google Analytics via Tealium Integration -->
		";
	}

	private function disable_tracking( $type ) {
		// origin
		//if ( is_admin() || current_user_can( 'manage_options' ) || ( ! $this->ga_id ) || 'no' === $type ) {
		
		if ( is_admin() || current_user_can( 'manage_options' ) || 'no' === $type ) {
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

		// Add single quotes to allow jQuery to be substituted into _trackEvent parameters
		$parameters = array();
		$parameters['category'] = "'" . __( 'Products', 'woocommerce-google-analytics-integration' ) . "'";
		$parameters['action']   = "'" . __( 'Add to Cart', 'woocommerce-google-analytics-integration' ) . "'";
		$parameters['label']    = "'" . esc_js( $product->get_sku() ? __( 'SKU:', 'woocommerce-google-analytics-integration' ) . ' ' . $product->get_sku() : "#" . $product->id ) . "'";

		if ( ! $this->disable_tracking( $this->ga_enhanced_ecommerce_tracking_enabled ) ) {
			$code = "" . WC_Tealium_Integration_JS::get_instance()->tracker_var() . "( 'ec:addProduct', {";
			$code .= "'id': '" . esc_js( $product->get_sku() ? $product->get_sku() : $product->id ) . "',";
			$code .= "'quantity': $( 'input.qty' ).val() ? $( 'input.qty' ).val() : '1'";
			$code .= "} );";
			$parameters['enhanced'] = $code;
		}

        WC_Tealium_Integration_JS::get_instance()->event_tracking_code( $parameters, '.single_add_to_cart_button' );

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

        WC_Tealium_Integration_JS::get_instance()->remove_from_cart();
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

		// Add single quotes to allow jQuery to be substituted into _trackEvent parameters
		$parameters = array();
		$parameters['category'] = "'" . __( 'Products', 'woocommerce-google-analytics-integration' ) . "'";
		$parameters['action']   = "'" . __( 'Add to Cart', 'woocommerce-google-analytics-integration' ) . "'";
		$parameters['label']    = "($(this).data('product_sku')) ? ('SKU: ' + $(this).data('product_sku')) : ('#' + $(this).data('product_id'))"; // Product SKU or ID

		if ( ! $this->disable_tracking( $this->ga_enhanced_ecommerce_tracking_enabled ) ) {
			$code = "" . WC_Tealium_Integration_JS::get_instance()->tracker_var() . "( 'ec:addProduct', {";
			$code .= "'id': ($(this).data('product_sku')) ? ('SKU: ' + $(this).data('product_sku')) : ('#' + $(this).data('product_id')),";
			$code .= "'quantity': $(this).data('quantity')";
			$code .= "} );";
			$parameters['enhanced'] = $code;
		}

        WC_Tealium_Integration_JS::get_instance()->event_tracking_code( $parameters, '.add_to_cart_button:not(.product_type_variable, .product_type_grouped)' );
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
        WC_Tealium_Integration_JS::get_instance()->listing_impression( $product, $woocommerce_loop['loop'] );
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
        WC_Tealium_Integration_JS::get_instance()->listing_click( $product, $woocommerce_loop['loop'] );
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
        WC_Tealium_Integration_JS::get_instance()->product_detail( $product );
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

        WC_Tealium_Integration_JS::get_instance()->checkout_process( WC()->cart->get_cart() );
	}
	
}
