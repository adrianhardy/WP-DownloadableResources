<?php
/*
Plugin Name: Downloadable Resources
Plugin URI: http://www.adrianhardy.co.uk/wordpress/
Description: Simple tool for managing documents. Documents can be categorised and listed for download.
Version: 1.0
Author: Adrian Hardy
Author URI: http://www.adrianhardy.co.uk
License:
*/


namespace AHWP\Plugins;

/**
 * Icons : http://p.yusukekamiyamane.com/icons/attribution/
 *
 * Useful resources:
 * http://mikejolley.com/2012/12/using-the-new-wordpress-3-5-media-uploader-in-plugins/
 * https://github.com/thomasgriffin/New-Media-Image-Uploader
 * http://wordpress.stackexchange.com/questions/78547/display-media-uploader-in-own-plugin-on-wordpress-3-5
 * http://stackoverflow.com/questions/14187611/display-media-uploader-in-own-plugin-on-wordpress-3-5
 * http://wordpress.stackexchange.com/questions/578/adding-a-taxonomy-filter-to-admin-list-for-a-custom-post-type
 * http://wp.smashingmagazine.com/2011/10/04/create-custom-post-meta-boxes-wordpress/
 * @todo Internationalisation using our own translation files
 * @todo proper icon attribution somewhere on the post page / in a readme
 * @todo show category in the post list page (on admin)
 */
class DownloadableResources {

    const POST_TYPE = 'ah-dl-res';
    const URL_META_KEY = 'ah-dl-res-url';
    const NONCE_KEY = 'ah-dl-res-wpnonce';

    protected $nonce_key = '';

    public function __construct() {
        add_action('init', array($this, 'register'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_shortcode('ah-dl-res-url', array(self, 'get_url'));
    }

    /**
     * Register the "downloads" custom post type
     */
    public function register() {
        add_action('post_updated', array($this, 'save_meta'), null, 2 );

        $original_config = $this->get_config();
        // push it through a filter so a theme can change the terminology
        $filtered_config = apply_filters('ah-wp-dl-res-config',$original_config);
        register_post_type(self::POST_TYPE, $filtered_config);

        register_taxonomy(self::POST_TYPE . '_cat',
            array(self::POST_TYPE),
            array('hierarchical' => true,
                'labels' => array(
                    'name' => __( 'Categories' ),
                    'singular_name' => __( 'Category' ),
                    'search_items' =>  __( 'Search Categories' ),
                    'all_items' => __( 'All Categories' ),
                    'parent_item' => __( 'Parent Category' ),
                    'parent_item_colon' => __( 'Parent Category:' ),
                    'edit_item' => __( 'Edit Category' ),
                    'update_item' => __( 'Update Category' ),
                    'add_new_item' => __( 'Add New Category' ),
                    'new_item_name' => __( 'New Category Name')
                ),
                'show_ui' => true,
                'query_var' => true,
                'rewrite' => array( 'slug' => 'documents/categories' ),
            )
        );
    }

    /**
     *
     */
    public function enqueue_admin_scripts() {
        if (get_post_type() == self::POST_TYPE) {
            $base_url = WP_PLUGIN_URL . '/' . basename(dirname(__FILE__));
            wp_enqueue_style(POST_TYPE.'-style',  $base_url . '/style.css', false, '1.0.0' );
            wp_enqueue_script(POST_TYPE.'-script',  $base_url . '/admin.js', false, '1.0.0' );

            // while we're here, let's monkey with the admin area too
            add_filter('enter_title_here', array($this, 'enter_title_here'));
            add_filter('tiny_mce_before_init', array($this, 'tiny_mce_before_init'));
            remove_action('media_buttons', 'media_buttons');
        }
    }

    /**
     * Hook into the 'ah-wp-dl-res-title-prompt' filter if you want to change
     * the message at the top of the new post screen.
     *
     * @param $prompt The original, bland prompt
     * @return String
     */
    public function enter_title_here($prompt) {
        return apply_filters('ah-wp-dl-res-title-prompt','A short name for the document');
    }

    /**
     * Strip out all fancy controls from the content editor. The document
     * description should be a solemn affair.
     *
     */
    function tiny_mce_before_init($settings) {
        $settings['theme_advanced_buttons1'] = 'bold,italic,underline';
        $settings['theme_advanced_buttons2'] = false;
        return $settings;
    }

    /**
     * @return array the CPT configuration
     */
    public function get_config() {
        return array('labels' => array(
            'name' => __('Downloadable Resources'),
            'singular_name' => __('Resource'),
            'all_items' => __('All Resources'),
            'add_new' => __('Add New'),
            'add_new_item' => __('Add New Resource'),
            'edit' => __( 'Edit' ),
            'edit_item' => __('Edit Resource'),
            'new_item' => __('New Resource'),
            'view_item' => __('View Resource'),
            'search_items' => __('Search Resources'),
            'not_found' =>  __('No downloadable resources found in the Database.'),
            'not_found_in_trash' => __('Nothing found in Trash'),
            'parent_item_colon' => '',
            'menu_name' => 'Downloadable Resources'
        ), /* end of arrays */
            'description' => __( 'These documents are made available to the public on the website'), /* Custom Type Description */
            'public' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => true,
            'show_ui' => true,
            'query_var' => true,
            'menu_position' => 30,
            'menu_icon' => plugin_dir_url(__FILE__) . 'drive-download.png',
            'rewrite'	=> array('slug' => 'downloadable-resources', 'with_front' => false),
            'has_archive' => true,
            'capability_type' => 'post',
            'show_in_nav_menus' => true,
            'hierarchical' => false,
            'taxonomies' => array( self::POST_TYPE . '_cat'),
            'supports' => array( 'title', 'editor'),
            'register_meta_box_cb' => array($this, 'register_meta_box')
        );
    }


    /**
     * Add the meta box to the Downloadable Resources post type
     */
    public function register_meta_box() {
        add_meta_box( self::POST_TYPE . '-meta', 'Downloadable Resource URL', array ($this, 'meta_box_html'), self::POST_TYPE, 'side', 'default', '');
    }

    /**
     * Output the meta box HTML which will sit immediately below the editor. The
     * meta box holds an input value to store the location of the attachment which
     * is delivered as the resource.
     */
    public function meta_box_html() {
        // Use nonce for verification
        wp_nonce_field( plugin_basename( __FILE__ ), self::NONCE_KEY );
        $old_meta_value = get_post_meta(get_the_ID(), self::URL_META_KEY, true);

        $key = self::URL_META_KEY;

        $html = <<<EOT
<label for="$key">The address of the downloadable resource</label>
<input type="text" id="$key" name="$key" placeholder="Paste it in or click the button below" style="width:100%" value="$old_meta_value" />
<a href="#" id="ah-dl-res-upload" class="button-primary" style="margin-top:10px">Upload or Select Resource</a>
EOT;
        echo $html;
    }

    /**
     * Write the post meta to the DB. In this case, we're only writing the document's absolute URL.
     *
     * @param $post_id
     * @param $post
     */
    public function save_meta($post_id, $post) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }


        // bomb out if the nonce didn't check out
        if (! (isset($_POST[self::NONCE_KEY]) && wp_verify_nonce($_POST[self::NONCE_KEY], plugin_basename(__FILE__)) ) ) {
            return $post_id;
        }

        // check roles/acls
        $post_type = get_post_type_object($post->post_type);
        if (!current_user_can($post_type->cap->edit_post, $post_id)) {
            return $post_id;
        }

        // add/update/delete meta
        if (isset($_POST[self::URL_META_KEY])) {
            $new_url_meta = esc_url_raw($_POST[self::URL_META_KEY]);
            $old_meta_value = get_post_meta( $post_id, self::URL_META_KEY, true );

            if ($old_meta_value && strlen($new_url_meta) == 0) {
                delete_post_meta($post_id, self::URL_META_KEY);
            }

            if ($new_url_meta && strlen($old_meta_value) == 0) {
                add_post_meta( $post_id, self::URL_META_KEY, $new_url_meta, true);
            }

            if (strlen($new_url_meta) > 0 && strlen($old_meta_value) > 0) {
                update_post_meta($post_id, self::URL_META_KEY, $new_url_meta);
            }

        }

    }

    /**
     * You can either call this in your template/archive code like:
     * echo AHWP\Plugins\DownloadableResources::get_url()
     * or you can use a shortcode:
     * [ah-dl-res-url]
     *
     * @todo check that the current post type is of POST_TYPE
     * @return String
     */
    public static function get_url($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        return get_post_meta($post_id, self::URL_META_KEY, true);
    }


}

$dl = new DownloadableResources();





