<?php
/**
 * Plugin Name: WooCommerce  Tealium Integration
 * Author: Vitali Korezki
 * Author URI: http://true-metrics.com
 * Version: 1.0.0
 * License: GPLv2 or later
 * Text Domain: woocommerce-tealium-integration
 * Domain Path: languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! class_exists( 'WC_Google_Analytics_Integration_Via_Tealium' ) ) :

    // Add term page
    function pippin_taxonomy_add_new_meta_field() {
        // this will add the custom meta field to the add new term page
        ?>
        <div class="form-field">
            <label for="term_meta[custom_term_meta]"><?php _e( 'Custom page_type', 'pippin' ); ?></label>
            <input type="text" name="term_meta[custom_term_meta]" id="term_meta[custom_term_meta]" value="">
            <p class="description"><?php _e( 'Custom page_type for tealium','pippin' ); ?></p>
        </div>
        <?php
    }
    add_action( 'product_cat_add_form_fields', 'pippin_taxonomy_add_new_meta_field', 10, 2 );

    add_action( 'genres_add_form_fields', 'pippin_taxonomy_add_new_meta_field', 10, 2 );

function pippin_taxonomy_edit_meta_field($term) {

    // put the term ID into a variable
    $t_id = $term->term_id;

    // retrieve the existing value(s) for this meta field. This returns an array
    $term_meta = get_option( "taxonomy_$t_id" ); ?>
    <tr class="form-field">
        <th scope="row" valign="top"><label for="term_meta[custom_term_meta]"><?php _e( 'Custom page_type', 'pippin' ); ?></label></th>
        <td>
            <input type="text" name="term_meta[custom_term_meta]" id="term_meta[custom_term_meta]" value="<?php echo esc_attr( $term_meta['custom_term_meta'] ) ? esc_attr( $term_meta['custom_term_meta'] ) : ''; ?>">
            <p class="description"><?php _e( 'Custom page_type for tealium','pippin' ); ?></p>
        </td>
    </tr>
    <?php
}
add_action( 'product_cat_edit_form_fields', 'pippin_taxonomy_edit_meta_field', 10, 2 );



    function save_taxonomy_custom_meta( $term_id ) {
        if ( isset( $_POST['term_meta'] ) ) {
            $t_id = $term_id;
            $term_meta = get_option( "taxonomy_$t_id" );
            $cat_keys = array_keys( $_POST['term_meta'] );
            foreach ( $cat_keys as $key ) {
                if ( isset ( $_POST['term_meta'][$key] ) ) {
                    $term_meta[$key] = $_POST['term_meta'][$key];
                }
            }
            // Save the option array.
            update_option( "taxonomy_$t_id", $term_meta );
        }
    }
    add_action( 'edited_product_cat', 'save_taxonomy_custom_meta', 10, 2 );
    add_action( 'create_product_cat', 'save_taxonomy_custom_meta', 10, 2 );


    add_action( 'edited_genres', 'save_taxonomy_custom_meta', 10, 2 );
    add_action( 'create_genres', 'save_taxonomy_custom_meta', 10, 2 );












//add extra fields to category edit form hook
add_action ( 'edit_category_form_fields', 'extra_category_fields');
//add extra fields to category edit form callback function
function extra_category_fields( $tag ) {    //check for existing featured ID
    $t_id = $tag->term_id;
    $cat_meta = get_option( "category_$t_id");
    ?>
    <tr class="form-field">
        <th scope="row" valign="top"><label for="cat_Image_url"><?php _e('Custom page_type'); ?></label></th>
        <td>
            <input type="text" name="Cat_meta[custom_post_type]" id="Cat_meta[custom_post_type]" size="3" style="width:60%;" value="<?php echo $cat_meta['custom_post_type'] ? $cat_meta['custom_post_type'] : ''; ?>"><br />
            <span class="description"><?php _e('Custom page_type for tealium'); ?></span>
        </td>
    </tr>

    <?php
}
// save extra category extra fields hook
add_action ( 'edited_category', 'save_extra_category_fileds');
   // save extra category extra fields callback function
function save_extra_category_fileds( $term_id ) {
    if ( isset( $_POST['Cat_meta'] ) ) {
        $t_id = $term_id;
        $cat_meta = get_option( "category_$t_id");
        $cat_keys = array_keys($_POST['Cat_meta']);
        foreach ($cat_keys as $key){
            if (isset($_POST['Cat_meta'][$key])){
                $cat_meta[$key] = $_POST['Cat_meta'][$key];
            }
        }
        //save the option array
        update_option( "category_$t_id", $cat_meta );
    }
}

    function myplugin_add_custom_box() {
        $screens = array( 'post', 'page', 'product' );
        foreach ( $screens as $screen )
            add_meta_box( 'myplugin_sectionid', 'Custom page_type', 'myplugin_meta_box_callback', $screen );
    }
    add_action('add_meta_boxes', 'myplugin_add_custom_box');

    /* HTML код блока */
    function myplugin_meta_box_callback() {
        global $post;
        // Используем nonce для верификации
        wp_nonce_field( plugin_basename(__FILE__), 'myplugin_noncename' );
        $values = get_post_meta( $post->ID, '_my_meta_value_key',true );

        // Поля формы для введения данных
        echo '<label for="myplugin_new_field">' . __("Custom page_type for tealium", 'myplugin_textdomain' ) . '</label> ';
        echo '<input type="text" id= "myplugin_new_field" name="myplugin_new_field" value="'. $values .'" size="25" />';
    }

    /* Сохраняем данные, когда пост сохраняется */
    function myplugin_save_postdata( $post_id ) {
        // проверяем nonce нашей страницы, потому что save_post может быть вызван с другого места.
        if ( ! wp_verify_nonce( $_POST['myplugin_noncename'], plugin_basename(__FILE__) ) )
            return $post_id;

        // проверяем, если это автосохранение ничего не делаем с данными нашей формы.
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
            return $post_id;

        // проверяем разрешено ли пользователю указывать эти данные
        if ( 'page' == $_POST['post_type'] && ! current_user_can( 'edit_page', $post_id ) ) {
            return $post_id;
        } elseif( ! current_user_can( 'edit_post', $post_id ) ) {
            return $post_id;
        }

        // Убедимся что поле установлено.
        if ( ! isset( $_POST['myplugin_new_field'] ) )
            return;

        // Все ОК. Теперь, нужно найти и сохранить данные
        // Очищаем значение поля input.
        $my_data = sanitize_text_field( $_POST['myplugin_new_field'] );

        // Обновляем данные в базе данных.
        update_post_meta( $post_id, '_my_meta_value_key', $my_data );
    }
    add_action( 'save_post', 'myplugin_save_postdata' );

class WC_Google_Analytics_Integration_Via_Tealium {


	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin.
	 */
	private function __construct() {
		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Checks with WooCommerce is installed.
		if ( class_exists( 'WC_Integration' ) && defined( 'WOOCOMMERCE_VERSION' ) && version_compare( WOOCOMMERCE_VERSION, '2.1-beta-1', '>=' ) ) {
            include_once 'includes/class-wc-google-analytics.php';
		    include_once 'includes/class-wc-google-analytics-via-tealium.php';
			include_once 'includes/class-wc-google-analytics-tealium-datalayer.php';

			// Register the integration.
			add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
		}
	}

	/**
	 * Return an instance of this class.
	 *
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @return void
	 */
	public function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'woocommerce-google-analytics-integration-via-tealium' );

		load_textdomain( 'woocommerce-google-analytics-integration-via-tealium', trailingslashit( WP_LANG_DIR ) . 'woocommerce-google-analytics-integration-via-tealium/woocommerce-google-analytics-integration-via-tealium-' . $locale . '.mo' );
		load_plugin_textdomain( 'woocommerce-google-analytics-integration-via-tealium', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * WooCommerce fallback notice.
	 *
	 * @return string
	 */
	public function woocommerce_missing_notice() {
		echo '<div class="error"><p>' . sprintf( __( 'WooCommerce Google Analytics depends on the last version of %s to work!', 'woocommerce-google-analytics-integration-via-tealium' ), '<a href="http://www.woothemes.com/woocommerce/" target="_blank">' . __( 'WooCommerce', 'woocommerce-google-analytics-integration-via-tealium' ) . '</a>' ) . '</p></div>';
	}

	/**
	 * Add a new integration to WooCommerce.
	 *
	 * @param  array $integrations WooCommerce integrations.
	 *
	 * @return array               Google Analytics integration.
	 */
	public function add_integration( $integrations ) {
		$integrations[] = 'WC_Google_Analytics_Tealium';
		$integrations[] = 'WC_Google_Analytics_Tealium_DataLayer';

		return $integrations;
	}
}

add_action( 'plugins_loaded', array( 'WC_Google_Analytics_Integration_Via_Tealium', 'get_instance' ), 10 );

endif;
