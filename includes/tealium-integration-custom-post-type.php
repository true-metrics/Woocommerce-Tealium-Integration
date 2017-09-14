<?php

function add_new_meta_field()
{
// this will add the custom meta field to the add new term page
?>
<div class="form-field">
    <label for="term_meta[custom_term_meta]"><?php _e('Custom page_type', 'pippin'); ?></label>
    <input type="text" name="term_meta[custom_term_meta]" id="term_meta[custom_term_meta]" value="">
    <p class="description"><?php _e('Custom page_type for tealium', 'pippin'); ?></p>
</div>
<?php
}

add_action('product_cat_add_form_fields', 'add_new_meta_field', 10, 2);

add_action('genres_add_form_fields', 'add_new_meta_field', 10, 2);

function taxonomy_edit_meta_field($term)
{

    // put the term ID into a variable
    $t_id = $term->term_id;

    // retrieve the existing value(s) for this meta field. This returns an array
    $term_meta = get_option("taxonomy_$t_id"); ?>
    <tr class="form-field">
        <th scope="row" valign="top"><label
                for="term_meta[custom_term_meta]"><?php _e('Custom page_type', 'pippin'); ?></label></th>
        <td>
            <input type="text" name="term_meta[custom_term_meta]" id="term_meta[custom_term_meta]"
                   value="<?php echo esc_attr($term_meta['custom_term_meta']) ? esc_attr($term_meta['custom_term_meta']) : ''; ?>">
            <p class="description"><?php _e('Custom page_type for tealium', 'pippin'); ?></p>
        </td>
    </tr>
    <?php
}

add_action('product_cat_edit_form_fields', 'taxonomy_edit_meta_field', 10, 2);


function save_taxonomy_custom_meta($term_id)
{
    if (isset($_POST['term_meta'])) {
        $t_id = $term_id;
        $term_meta = get_option("taxonomy_$t_id");
        $cat_keys = array_keys($_POST['term_meta']);
        foreach ($cat_keys as $key) {
            if (isset ($_POST['term_meta'][$key])) {
                $term_meta[$key] = $_POST['term_meta'][$key];
            }
        }
        // Save the option array.
        update_option("taxonomy_$t_id", $term_meta);
    }
}

add_action('edited_product_cat', 'save_taxonomy_custom_meta', 10, 2);
add_action('create_product_cat', 'save_taxonomy_custom_meta', 10, 2);


add_action('edited_genres', 'save_taxonomy_custom_meta', 10, 2);
add_action('create_genres', 'save_taxonomy_custom_meta', 10, 2);


//add extra fields to category edit form hook
add_action('edit_category_form_fields', 'extra_category_fields');
//add extra fields to category edit form callback function
function extra_category_fields($tag)
{    //check for existing featured ID
    $t_id = $tag->term_id;
    $cat_meta = get_option("category_$t_id");
    ?>
    <tr class="form-field">
        <th scope="row" valign="top"><label for="cat_Image_url"><?php _e('Custom page_type'); ?></label></th>
        <td>
            <input type="text" name="Cat_meta[custom_post_type]" id="Cat_meta[custom_post_type]" size="3"
                   style="width:60%;"
                   value="<?php echo $cat_meta['custom_post_type'] ? $cat_meta['custom_post_type'] : ''; ?>"><br/>
            <span class="description"><?php _e('Custom page_type for tealium'); ?></span>
        </td>
    </tr>

    <?php
}

// save extra category extra fields hook
add_action('edited_category', 'save_extra_category_fileds');
// save extra category extra fields callback function
function save_extra_category_fileds($term_id)
{
    if (isset($_POST['Cat_meta'])) {
        $t_id = $term_id;
        $cat_meta = get_option("category_$t_id");
        $cat_keys = array_keys($_POST['Cat_meta']);
        foreach ($cat_keys as $key) {
            if (isset($_POST['Cat_meta'][$key])) {
                $cat_meta[$key] = $_POST['Cat_meta'][$key];
            }
        }
        update_option("category_$t_id", $cat_meta);
    }
}

function myplugin_add_custom_box()
{
    $screens = array('post', 'page', 'product');
    foreach ($screens as $screen)
        add_meta_box('myplugin_sectionid', 'Custom page_type', 'myplugin_meta_box_callback', $screen);
}

add_action('add_meta_boxes', 'myplugin_add_custom_box');

/* HTML код блока */
function myplugin_meta_box_callback()
{
    global $post;
    wp_nonce_field(plugin_basename(__FILE__), 'myplugin_noncename');
    $values = get_post_meta($post->ID, '_my_meta_value_key', true);

    // Поля формы для введения данных
    echo '<label for="myplugin_new_field">' . __("Custom page_type for tealium", 'myplugin_textdomain') . '</label> ';
    echo '<input type="text" id= "myplugin_new_field" name="myplugin_new_field" value="' . $values . '" size="25" />';
}

function myplugin_save_postdata($post_id)
{
    if (!wp_verify_nonce($_POST['myplugin_noncename'], plugin_basename(__FILE__)))
        return $post_id;

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return $post_id;

    if ('page' == $_POST['post_type'] && !current_user_can('edit_page', $post_id)) {
        return $post_id;
    } elseif (!current_user_can('edit_post', $post_id)) {
        return $post_id;
    }

    // Убедимся что поле установлено.
    if (!isset($_POST['myplugin_new_field']))
        return;

    $my_data = sanitize_text_field($_POST['myplugin_new_field']);

    update_post_meta($post_id, '_my_meta_value_key', $my_data);
}

add_action('save_post', 'myplugin_save_postdata');