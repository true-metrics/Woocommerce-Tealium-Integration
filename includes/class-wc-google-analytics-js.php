<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Google_Analytics_Tealium_JS  {

	private static $instance;

	private static $options;


	public static function get_instance( $options = array() ) {
		return null === self::$instance ? ( self::$instance = new self( $options ) ) : self::$instance;
	}


	public function __construct( $options = array() ) {
		self::$options = $options;
	}


	public static function get( $option ) {
		return self::$options[$option];
	}


	public static function tracker_var() {
		return apply_filters( 'woocommerce_ga_tracker_variable', 'ga' );
	}


	public static function header() {
		return "<script type='text/javascript'>
			var gaProperty = '" . esc_js( self::get( 'ga_id' ) ) . "';
			var disableStr = 'ga-disable-' + gaProperty;
			if ( document.cookie.indexOf( disableStr + '=true' ) > -1 ) {
				window[disableStr] = true;
			}
			function gaOptout() {
				document.cookie = disableStr + '=true; expires=Thu, 31 Dec 2099 23:59:59 UTC; path=/';
				window[disableStr] = true;
			}
		</script>";
	}
	

	public static function load_analytics( $order = false ) {
		$logged_in = is_user_logged_in() ? 'yes' : 'no';
		if ( 'yes' === self::get( 'ga_use_universal_analytics' ) ) {
			add_action( 'wp_footer', array( 'WC_Google_Analytics_TEALIUM_JS', 'universal_analytics_footer' ) );
			return self::load_analytics_universal( $logged_in );
		} else {
			add_action( 'wp_footer', array( 'WC_Google_Analytics_TEALIUM_JS', 'classic_analytics_footer' ) );
			return self::load_analytics_classic( $logged_in, $order );
		}
	}
	

	public static function load_analytics_universal( $logged_in ) {
		return '';
		
		$domainname = self::get( 'ga_set_domain_name' );
		
		if ( ! empty( $domainname ) ) {
			$set_domain_name = esc_js( self::get( 'ga_set_domain_name' ) );
		} else {
			$set_domain_name = 'auto';
		}

		$support_display_advertising = '';
		if ( 'yes' === self::get( 'ga_support_display_advertising' ) ) {
			$support_display_advertising = "" . self::tracker_var() . "( 'require', 'displayfeatures' );";
		}

		$anonymize_enabled = '';
		if ( 'yes' === self::get( 'ga_anonymize_enabled' ) ) {
			$anonymize_enabled = "" . self::tracker_var() . "( 'set', 'anonymizeIp', true );";
		}

		/*$code = "(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
		(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
		m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
		})(window,document,'script','//www.google-analytics.com/analytics.js','" . self::tracker_var() . "');

		" . self::tracker_var() . "( 'create', '" . esc_js( self::get( 'ga_id' ) ) . "', '" . $set_domain_name . "' );" .*/
		
		$code = $support_display_advertising .
		$anonymize_enabled . "
		" . self::tracker_var() . "( 'set', 'dimension1', '" . $logged_in . "' );\n";

		if ( 'yes' === self::get( 'ga_enhanced_ecommerce_tracking_enabled' ) ) {
			$code .= "" . self::tracker_var() . "( 'require', 'ec' );";
		} else {
			$code .= "" . self::tracker_var() . "( 'require', 'ecommerce', 'ecommerce.js');";
		}

		return $code;
	}

	public static function universal_analytics_footer() {
		// wc_enqueue_js( "" . self::tracker_var() . "( 'send', 'pageview' ); ");
	}
	
	
	

	function add_transaction( $order ) {
		if ( 'yes' == self::get( 'ga_use_universal_analytics' ) ) {
			if ( 'yes' === self::get( 'ga_enhanced_ecommerce_tracking_enabled' ) ) {
				return self::add_transaction_enhanced( $order );
			} else {
				return self::add_transaction_universal( $order );
			}
		} else {
			return self::add_transaction_classic( $order );
		}
	}


	function add_transaction_classic( $order ) {
		global $utagdata;
		
		$code = "_gaq.push(['_addTrans',
			'" . esc_js( $order->get_order_number() ) . "', 	// order ID - required
			'" . esc_js( get_bloginfo( 'name' ) ) . "',  		// affiliation or store name
			'" . esc_js( $order->get_total() ) . "',   	    	// total - required
			'" . esc_js( $order->get_total_tax() ) . "',    	// tax
			'" . esc_js( $order->get_total_shipping() ) . "',	// shipping
			'" . esc_js( $order->billing_city ) . "',       	// city
			'" . esc_js( $order->billing_state ) . "',      	// state or province
			'" . esc_js( $order->billing_country ) . "'     	// country
		]);";

		// Order items
		if ( $order->get_items() ) {
			foreach ( $order->get_items() as $item ) {
				$code .= self::add_item_classic( $order, $item );
			}
		}

		$code .= "_gaq.push(['_trackTrans']);";
		return $code;
	}


	function add_transaction_universal( $order ) {
		$code = "" . self::tracker_var() . "('ecommerce:addTransaction', {
			'id': '" . esc_js( $order->get_order_number() ) . "',         // Transaction ID. Required
			'affiliation': '" . esc_js( get_bloginfo( 'name' ) ) . "',    // Affiliation or store name
			'revenue': '" . esc_js( $order->get_total() ) . "',           // Grand Total
			'shipping': '" . esc_js( $order->get_total_shipping() ) . "', // Shipping
			'tax': '" . esc_js( $order->get_total_tax() ) . "',           // Tax
			'currency': '" . esc_js( $order->get_order_currency() ) . "'  // Currency
		});";

		// Order items
		if ( $order->get_items() ) {
			foreach ( $order->get_items() as $item ) {
				$code .= self::add_item_universal( $order, $item );
			}
		}

		$code .= "" . self::tracker_var() . "('ecommerce:send');";
		return $code;
	}


	function add_transaction_enhanced( $order ) {
		$code = "" . self::tracker_var() . "( 'set', '&cu', '" . esc_js( $order->get_order_currency() ) . "' );";

		// Order items
		if ( $order->get_items() ) {
			foreach ( $order->get_items() as $item ) {
				$code .= self::add_item_enhanced( $order, $item );
			}
		}

		$code .= "" . self::tracker_var() . "( 'ec:setAction', 'purchase', {
			'id': '" . esc_js( $order->get_order_number() ) . "',
			'affiliation': '" . esc_js( get_bloginfo( 'name' ) ) . "',
			'revenue': '" . esc_js( $order->get_total() ) . "',
			'tax': '" . esc_js( $order->get_total_tax() ) . "',
			'shipping': '" . esc_js( $order->get_total_shipping() ) . "'
		} );";

		return $code;
	}


	function add_item_classic( $order, $item ) {
		$_product = $order->get_product_from_item( $item );

		$code = "_gaq.push(['_addItem',";
		$code .= "'" . esc_js( $order->get_order_number() ) . "',";
		$code .= "'" . esc_js( $_product->get_sku() ? $_product->get_sku() : $_product->id ) . "',";
		$code .= "'" . esc_js( $item['name'] ) . "',";
		$code .= self::product_get_category_line( $_product );
		$code .= "'" . esc_js( $order->get_item_total( $item ) ) . "',";
		$code .= "'" . esc_js( $item['qty'] ) . "'";
		$code .= "]);";

		return $code;

	}


	function add_item_universal( $order, $item ) {
		$_product = $order->get_product_from_item( $item );

		$code = "" . self::tracker_var() . "('ecommerce:addItem', {";
		$code .= "'id': '" . esc_js( $order->get_order_number() ) . "',";
		$code .= "'name': '" . esc_js( $item['name'] ) . "',";
		$code .= "'sku': '" . esc_js( $_product->get_sku() ? $_product->get_sku() : $_product->id ) . "',";
		$code .= "'category': " . self::product_get_category_line( $_product );
		$code .= "'price': '" . esc_js( $order->get_item_total( $item ) ) . "',";
		$code .= "'quantity': '" . esc_js( $item['qty'] ) . "'";
		$code .= "});";

		return $code;
	}


	function add_item_enhanced( $order, $item ) {
		$_product = $order->get_product_from_item( $item );

		$code = "" . self::tracker_var() . "( 'ec:addProduct', {";
		$code .= "'id': '" . esc_js( $_product->get_sku() ? $_product->get_sku() : $_product->id ) . "',";
		$code .= "'name': '" . esc_js( $item['name'] ) . "',";
		$code .= "'category': " . self::product_get_category_line( $_product );
		$code .= "'price': '" . esc_js( $order->get_item_total( $item ) ) . "',";
		$code .= "'quantity': '" . esc_js( $item['qty'] ) . "'";
		$code .= "});";

		return $code;
	}


	private static function product_get_category_line( $_product ) {
		if ( is_array( $_product->variation_data ) && ! empty( $_product->variation_data ) ) {
			$code = "'" . esc_js( woocommerce_get_formatted_variation( $_product->variation_data, true ) ) . "',";
		} else {
			$out = array();
			$categories = get_the_terms( $_product->id, 'product_cat' );
			if ( $categories ) {
				foreach ( $categories as $category ) {
					$out[] = $category->name;
				}
			}
			$code = "'" . esc_js( join( "/", $out ) ) . "',";
		}

		return $code;
	}


	function remove_from_cart() {
		echo( "
			<script>
			jQuery(document).ready(function() {
				$( '.remove' ).click( function() {
					" . self::tracker_var() . "( 'ec:addProduct', {
						'id': ($(this).data('product_sku')) ? ('SKU: ' + $(this).data('product_sku')) : ('#' + $(this).data('product_id')),
						'quantity': $(this).parent().parent().find( '.qty' ).val() ? $(this).parent().parent().find( '.qty' ).val() : '1',
					} );
					" . self::tracker_var() . "( 'ec:setAction', 'remove' );
					" . self::tracker_var() . "( 'send', 'event', 'UX', 'click', 'remove from cart' );
				});
			});
			</script>
		" );
	}


	function product_detail( $product ) {
		global $utagdata;
		$utagdata['product_list'] = array();
		$utagdata['product_list'][] = array(
			'id' => esc_js( $product->get_sku() ? $product->get_sku() : $product->id ),
			'name' => esc_js( $product->get_title() ),
			'category' => self::product_get_category_line( $product ),
			'price' => esc_js( $product->get_price() )
		);
		
		
		wc_enqueue_js( "
			" . self::tracker_var() . "( 'ec:addProduct', {
				'id': '" . esc_js( $product->get_sku() ? $product->get_sku() : $product->id ) . "',
				'name': '" . esc_js( $product->get_title() ) . "',
				'category': " . self::product_get_category_line( $product ) . "
				'price': '" . esc_js( $product->get_price() ) . "',
			} );

			" . self::tracker_var() . "( 'ec:setAction', 'detail' );" );
	}

	function checkout_process( $cart ) {
		$code = "";

		foreach ( $cart as $cart_item_key => $cart_item ) {
			$product     = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			$code .= "" . self::tracker_var() . "( 'ec:addProduct', {
				'id': '" . esc_js( $product->get_sku() ? $product->get_sku() : $product->id ) . "',
				'name': '" . esc_js( $product->get_title() ) . "',
				'category': " . self::product_get_category_line( $product ) . "
				'price': '" . esc_js( $product->get_price() ) . "',
				'quantity': '" . esc_js( $cart_item['quantity'] ) . "'
			} );";
		}

		$code .= "" . self::tracker_var() . "( 'ec:setAction','checkout' );";
		wc_enqueue_js( $code );
	}
}
