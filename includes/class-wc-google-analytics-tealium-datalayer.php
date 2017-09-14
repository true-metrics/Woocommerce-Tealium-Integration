<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


class WC_Google_Analytics_Tealium_DataLayer
{

    public function __construct()
    {
        // Add Tealium functions
        add_action('tealium_addToDataObject', array($this, 'add_datalayer'));
        add_filter('tealium_removeExclusions', array($this, 'remove_exclusions'));
    }

    private static function product_get_category_line($_product)
    {
        if (is_array($_product->variation_data) && !empty($_product->variation_data)) {
            $code = "'" . esc_js(woocommerce_get_formatted_variation($_product->variation_data, true)) . "',";
        } else {
            $out = array();
            $categories = get_the_terms($_product->id, 'product_cat');
            if ($categories) {
                foreach ($categories as $category) {
                    $out[] = $category->name;
                }
            }
            $code = "'" . esc_js(join("/", $out)) . "',";
        }

        return $code;
    }

    private function get_page_name()
    {
        global $post;
        $name = get_the_title();

        if (is_product()) {
            $name = $post->post_title;
        } else if (is_product_category()) {
            $name = single_cat_title('', false);
        } else if (is_404()) {
            $name = "404 Not Found";
        } else if (is_search()) {
            $name = "search results";
        } else if (is_category()) {
            $name = "category";
        }
        else if (is_archive()) {
            $name = "archive";
        }
        return $name;

    }

    private function get_page_type()
    {
        global $wp_query;
        global $post;
        $type = 'page';


        if (is_product()) {
            $type = 'product';
        } else if ((is_home()) || (is_front_page())) {
            $type = "home";
        } else if (is_product_category()) {
            $type = "category";

            $queried_object = get_queried_object();
            $term_id = $queried_object->term_id;
            $option = get_option("taxonomy_$term_id");
            $value = $option['custom_term_meta'];
            if ($value) {
                $type = $value;
            }

        }  else if (is_category()) {
            $type = "category";
            $queried_object = get_queried_object();
            $term_id = $queried_object->term_id;
            $cat_meta = get_option("category_$term_id");
            $value = $cat_meta['custom_post_type'];
            if ($value) {
                $type = $value;
            }

        } else if (is_archive()) {
            $type = "archive";
            $queried_object = get_queried_object();
            $term_id = $queried_object->term_id;
            $cat_meta = get_option("category_$term_id");
            $value = $cat_meta['custom_post_type'];
            if ($value) {
                $type = $value;
            }

        } else if (is_404()) {
            $type = "404";
        } else if (is_account_page()) {
            $type = "customer account";
        } else if (is_checkout()) {
            $type = "checkout";
        } else if (is_cart()) {
            $type = 'cart';
        } else if (is_search()) {
            $type = "search";
        } elseif ($wp_query->is_single) {
            $type = ($wp_query->is_attachment) ? 'attachment' : get_query_var('post_type');
        } elseif ($wp_query->is_tag) {
            $type = 'tag';
        } elseif ($wp_query->is_tax) {
            if (isset($wp_query->query) && isset($wp_query->query['product_cat'])) {
                $type = 'product_category';
            } else {
                $type = 'tax';
            }
        } elseif ($wp_query->is_archive) {
            if ($wp_query->is_day) {
                $type = 'day';
            } elseif ($wp_query->is_month) {
                $type = 'month';
            } elseif ($wp_query->is_year) {
                $type = 'year';
            } elseif ($wp_query->is_author) {
                $type = 'author';
            } else {
                $type = 'archive';
            }
        }


        if ((is_product()) || (is_page()) || (is_single()) || (is_shop())) {
            $values = get_post_meta($post->ID, '_my_meta_value_key');
            if ($values) {
                $type = $values[0];
            }
        }


        //            $utagdata['tealiumAccoun'] = get_option( 'tealiumAccount' );
        //            $utagdata['tealiumProfile'] = get_option( 'tealiumProfile' );
        //            $utagdata['tealiumEnvironment'] = get_option( 'tealiumEnvironment' );


        return $type;

    }


    private function add_datalayer_listitem($key, $value = '')
    {
        global $utagdata;
        $utagdata[$key] = (isset($utagdata[$key])) ? $utagdata[$key] : array();
        $utagdata[$key][] = $value;
        return $this;

    }


    function add_transaction($order)
    {
        $this->add_transaction_enhanced($order);
        return $this;
        // if ( 'yes' == self::get( 'ga_use_universal_analytics' ) ) {
        // 	if ( 'yes' === self::get( 'ga_enhanced_ecommerce_tracking_enabled' ) ) {
        // 		return self::add_transaction_enhanced( $order );
        // 	} else {
        // 		return self::add_transaction_universal( $order );
        // 	}
        // } else {
        // 	return self::add_transaction_classic( $order );
        // }
    }

    function add_transaction_enhanced($order)
    {
        // Order items
        if ($order->get_items()) {
            foreach ($order->get_items() as $item) {
                $this->add_item_enhanced($order, $item);
            }
        }
        $utagdata['page_type'] = 'purchase';
        $utagdata['order_id'] = esc_js($order->get_order_number());
        $utagdata['order_affiliation'] = esc_js(get_bloginfo('name'));
        $utagdata['order_total'] = esc_js($order->get_total());                // revenue
        $utagdata['order_tax'] = esc_js($order->get_total_tax());
        $utagdata['order_shipping'] = esc_js($order->get_total_shipping());

        return $this;
    }


    function add_item_enhanced($order, $item)
    {
        $product = $order->get_product_from_item($item);

        $this->add_product_datalayer($product)
            ->replace_datalayer_listitem('product_price', esc_js($order->get_item_total($item)))
            ->add_datalayer_listitem('product_quantity', esc_js($item['qty']));

        return $this;
    }


    function checkout_process($cart = null)
    {
        global $utagdata;

        $cart = (!$cart) ? WC()->cart->get_cart() : $cart;
        $code = "";

        foreach ($cart as $cart_item_key => $cart_item) {
            $product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);

            $this->add_product_datalayer($product)
                ->add_datalayer_listitem('product_quantity', esc_js($cart_item['quantity']));
        }

        $utagdata['page_type'] = 'checkout';
        return $this;
    }

    function get_user_info()
    {
        global $utagdata;
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $user_name = $current_user->user_firstname;
            $user_name .= ' ' . $current_user->user_lastname;
            $user_mail = $current_user->user_email;
            $user_id = $current_user->ID;

            $utagdata['customer_id'] = $user_id;
            $utagdata['customer_name'] = $user_name;
            $utagdata['customer_email'] = $user_mail;
            $utagdata['customer_type'] = 'Private';
        }
    }

    function get_product_info($post)
    {
        $id = $post->ID;
        $meta = get_post_meta($id);
        $cat_list = '';
        $slug_list = '';

        $cat_terms = get_the_terms($id, 'product_cat');
        foreach ($cat_terms as $cat_term) {
            $cat_list .= $cat_term->slug . ',';
        }

        $slug_terms = get_the_terms($id, 'product_brands');
        foreach ($slug_terms as $slug_term) {
            $slug_list .= $slug_term->slug . ',';
        }

        $cat_list = mb_substr($cat_list, 0, -1);
        $slug_list = mb_substr($slug_list, 0, -1);

        $price = number_format($meta['_regular_price'][0], 2, '.', ' ');
        $list_price = number_format($meta['_price'][0], 2, '.', ' ');
        $sale_price = number_format($meta['_sale_price'][0], 2, '.', ' ');
        $original_price = number_format($meta['_regular_price'][0], 2, '.', ' ');
        $unit_price = number_format($meta['_sale_price'][0], 2, '.', ' ');

        $this->add_datalayer_listitem('product_id', esc_js($id));
        $this->add_datalayer_listitem('product_brand', esc_js($slug_list));
        $this->add_datalayer_listitem('product_category', esc_js($cat_list));
        $this->add_datalayer_listitem('product_sku', esc_js($meta['_sku'][0]));
        $this->add_datalayer_listitem('product_name', esc_js($post->post_title));
        $this->add_datalayer_listitem('product_list_price', esc_js($list_price));
        $this->add_datalayer_listitem('product_sale_price', esc_js($sale_price));
        $this->add_datalayer_listitem('product_original_price', esc_js($original_price));
        $this->add_datalayer_listitem('product_unit_price', esc_js($unit_price));
        $this->add_datalayer_listitem('product_price', esc_js($price));
    }


    function get_cart_contents()
    {

        global $utagdata;
        global $woocommerce;
        $woocart = (array)$woocommerce->cart;
        $productData = array();
        $coupons_list = '';

        $cart_items = $woocommerce->cart->cart_contents_count;
        $cart_value = $woocommerce->cart->cart_contents_total;
        $cart_value = number_format($cart_value, 2, '.', ' ');
        $productData['cart_total_items'] = $cart_items;
        $productData['cart_total_value'] = $cart_value;

        $coupon = $woocart['applied_coupons'];
        if ($coupon) {
            $discount = $woocart['discount_cart'];
            $discount = number_format($discount, 2, '.', '');
            foreach ($coupon as $coupon_item) {
                $coupons_list .= $coupon_item . ',';
            }
            $coupons_list = mb_substr($coupons_list, 0, -1);

            $this->add_datalayer_listitem('discount_cart', esc_js($discount));
            $this->add_datalayer_listitem('applied_coupons', esc_js($coupons_list));

        }

        // Get cart product IDs, SKUs, Titles etc.
        foreach ($woocart['cart_contents'] as $cartItem) {
            $productMeta = new WC_Product($cartItem['product_id']);

            $id = $cartItem['product_id'];
            $meta = get_post_meta($id);

            $cat_list = '';
            $slug_list = '';

            $cat_terms = get_the_terms($id, 'product_cat');
            foreach ($cat_terms as $cat_term) {
                $cat_list .= $cat_term->slug . ',';
            }

            $slug_terms = get_the_terms($id, 'product_brands');
            foreach ($slug_terms as $slug_term) {
                $slug_list .= $slug_term->slug . ',';
            }

            $cat_list = mb_substr($cat_list, 0, -1);
            $slug_list = mb_substr($slug_list, 0, -1);

            $price = number_format($meta['_regular_price'][0], 2, '.', '');
            $list_price = number_format($meta['_price'][0], 2, '.', '');
            $sale_price = number_format($meta['_sale_price'][0], 2, '.', '');
            $original_price = number_format($meta['_regular_price'][0], 2, '.', '');
            $unit_price = number_format($meta['_sale_price'][0], 2, '.', '');

            $this->add_datalayer_listitem('product_category', esc_js($cat_list));
            $this->add_datalayer_listitem('product_brand', esc_js($slug_list));
            $this->add_datalayer_listitem('product_id', esc_js($id));
            $this->add_datalayer_listitem('product_sku', esc_js($productMeta->sku));
            $this->add_datalayer_listitem('product_name', esc_js($productMeta->post->post_title));
            $this->add_datalayer_listitem('product_quantity', esc_js($cartItem['quantity']));
            $this->add_datalayer_listitem('product_list_price', esc_js($list_price));
            $this->add_datalayer_listitem('product_original_price', esc_js($original_price));
            $this->add_datalayer_listitem('product_price', esc_js($price));
            $this->add_datalayer_listitem('product_sale_price', esc_js($sale_price));
            $this->add_datalayer_listitem('product_unit_price', esc_js($unit_price));
        }
        $utagdata = array_merge($utagdata, $productData);
    }

    function get_search_page_info()
    {
        $searchQuery = get_search_query();
        $searchResults = new WP_Query('s=' . str_replace(' ', '+', $searchQuery . '&showposts=-1'));
        $searchCount = $searchResults->post_count;
        wp_reset_query();
        $this->add_datalayer_listitem('search_keyword', esc_js($searchQuery));
        $this->add_datalayer_listitem('search_results', esc_js($searchCount));
    }

    function get_category_info()
    {
        $name = single_cat_title('', false);
        $this->add_datalayer_listitem('page_category', esc_js($name));
        $this->add_datalayer_listitem('page_product_category', esc_js($name));
    }

    public function add_datalayer()
    {
        $type = $this->get_page_type();
        $name = $this->get_page_name();

        global $wp_query;
        global $post;
        global $utagdata;
        global $woocommerce;
        $utagdata = array();

        $meta = get_post_meta($post->ID);
        $values = get_post_meta($post->ID, '_my_meta_value_key');
        echo '<pre>';
        print_r($values);
        echo '</pre>';

        $this->get_user_info();


        if (is_product()) {
            $this->get_product_info($post);
        } else if (is_cart() || is_checkout()) {
            $this->get_cart_contents();
        } else if (is_search()) {
            $this->get_search_page_info();
        } else if (is_product_category()) {
            $this->get_category_info();
        }


        $utagdata['page_name'] = $name;
        $utagdata['page_type'] = $type;
        $utagdata['site_currency'] = get_woocommerce_currency();
        $utagdata['site_id'] = get_option('tealiumAccount');
        $utagdata['site_region'] = $woocommerce->countries->get_base_country();

        echo '<pre>';
        print_r($utagdata);
        echo '</pre>';

        // Check if is order received page and stop when the products and not tracked
        if (is_order_received_page()) {
            $order_id = isset($wp->query_vars['order-received']) ? $wp->query_vars['order-received'] : 0;
            if (0 < $order_id && 1 != get_post_meta($order_id, '_ga_tracked', true)) {
                // Mark the order as tracked
                update_post_meta($order_id, '_ga_tracked', 1);

                $display_ecommerce_tracking = true;

                // Get the order and output tracking code
                $order = new WC_Order($order_id);
                $this->add_transaction($order);
            }
        }


    }

    public function remove_exclusions($utagdata)
    {
        return $utagdata;

        $exclusions = get_option('tealiumExclusions');
        if (!empty($exclusions)) {

            // Convert list to array and trim whitespace
            $exclusions = array_map('trim', explode(',', $exclusions));

            foreach ($exclusions as $exclusion) {
                if (array_key_exists($exclusion, $utagdata)) {
                    // Remove from utag data array
                    unset($utagdata[$exclusion]);
                }
            }
        }
        return $utagdata;
    }
}
