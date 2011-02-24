<?php
/**
 * @package Stealth_Publish
 * @author Scott Reilly
 * @version 2.1
 */
/*
Plugin Name: Stealth Publish
Version: 2.1
Plugin URI: http://coffee2code.com/wp-plugins/stealth-publish/
Author: Scott Reilly
Author URI: http://coffee2code.com
Text Domain: stealth-publish
Description: Prevent specified posts from being featured on the front page or in feeds, and from notifying external services of publication.

Compatible with WordPress 2.9+, 3.0+, 3.1+

=>> Read the accompanying readme.txt file for instructions and documentation.
=>> Also, visit the plugin's homepage for additional information and updates.
=>> Or visit: http://wordpress.org/extend/plugins/stealth-publish/

*/

/*
Copyright (c) 2007-2011 by Scott Reilly (aka coffee2code)

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation
files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy,
modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR
IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

if ( !class_exists( 'c2c_StealthPublish' ) ) :

class c2c_StealthPublish {

	private static $field = 'stealth_publish';
	private static $meta_key = '_stealth-publish'; // Filterable via 'stealth_publish_meta_key' filter
	private static $stealth_published_posts = array(); // For memoization
	private static $textdomain = 'stealth-publish';
	private static $textdomain_subdir = 'lang';

	/**
	 * Constructor
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'do_init' ) );
	}

	/**
	 * Register actions/filters and allow for configuration
	 */
	public static function do_init() {
		self::load_textdomain();
		self::$meta_key = esc_attr( apply_filters( 'stealth_publish_meta_key', self::$meta_key ) );
		add_filter( 'posts_where',                 array( __CLASS__, 'stealth_publish_where' ), 1, 2 );
		//add_action( 'pre_get_posts',               array( __CLASS__, 'maybe_exclude_stealth_posts' ) );
		add_action( 'post_submitbox_misc_actions', array( __CLASS__, 'add_ui' ) );
		add_filter( 'wp_insert_post_data',         array( __CLASS__, 'save_stealth_publish_status' ), 2, 2 );
		add_action( 'publish_post',                array( __CLASS__, 'publish_post' ), 1, 1 );
	}

	/*
	// This approach (instead of hooking 'posts_where') is more efficient, allowing WP to handle excluding the stealth posts
	// itself during the primary query (and no need to run an additional query) to find stealth post IDs to exclude.
	// However, it requires that ALL posts be assigned the custom field, which means hooking activation
	public static function maybe_exclude_stealth_posts( $wpquery ) {
		if ( $wpquery->is_home || $wpquery->is_feed ||
			( trailingslashit( get_option( 'siteurl' ) ) == trailingslashit( 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] ) ) ) {
			$wpquery->query_vars['meta_compare'] = '!=';
			$wpquery->query_vars['meta_key'] = self::$meta_key;
			$wpquery->query_vars['meta_value'] = '1';
		}
	}
	*/

	/**
	 * Loads the localization textdomain for the plugin.
	 *
	 * @since 2.0
	 *
	 * @return void
	 */
	public static function load_textdomain() {
		$subdir = empty( self::$textdomain_subdir ) ? '' : ( '/' . self::$textdomain_subdir );
		load_plugin_textdomain( self::$textdomain, false, basename( dirname( __FILE__ ) ) . $subdir );
	}

	/**
	 * Draws the UI to prompt user if stealth publish should be enabled for the post.
	 *
	 * @since 2.0
	 *
	 * @return void (Text is echoed.)
	 */
	public static function add_ui() {
		global $post;
		$value = get_post_meta( $post->ID, self::$meta_key, true );
		$checked = checked( $value, '1', false );
		echo "<div class='misc-pub-section'><label class='selectit c2c-stealth-publish' for='" . self::$field . "' title='";
		esc_attr_e( 'If checked, the post will not appear on the front page or in the main feed.', self::$textdomain );
		echo "'>\n";
		echo "<input id='" . self::$field . "' type='checkbox' $checked value='1' name='" . self::$field . "' />\n";
		_e( 'Stealth publish?', self::$textdomain );
		echo '</label></div>' . "\n";
	}

	/**
	 * Update the value of the stealth publish custom field, but only if it is supplied.
	 *
	 * @since 2.0
	 *
	 * @param array $data Data
	 * @param array $postarr Array of post fields and values for post being saved
	 * @return array The unmodified $data
	 */
	public static function save_stealth_publish_status( $data, $postarr ) {
		if ( isset( $postarr['post_type'] ) && ( 'revision' != $postarr['post_type'] ) ) {
			$new_value = isset( $postarr[self::$field] ) ? $postarr[self::$field] : '';
			update_post_meta( $postarr['ID'], self::$meta_key, $new_value );
		}
		return $data;
	}

	/**
	 * Returns an array of post IDs that are to be stealth published
	 *
	 * @since 1.0
	 *
	 * @return array Post IDs of all stealth published posts
	 */
	public static function find_stealth_published_post_ids() {
		if ( !empty( self::$stealth_published_posts ) )
			return self::$stealth_published_posts;

		global $wpdb;
		$sql = "SELECT DISTINCT ID FROM $wpdb->posts AS p
				LEFT JOIN $wpdb->postmeta AS pm ON (p.ID = pm.post_id)
				WHERE pm.meta_key = %s AND pm.meta_value = '1'
				GROUP BY pm.post_id";
		self::$stealth_published_posts = $wpdb->get_col( $wpdb->prepare( $sql, self::$meta_key ) );
		return self::$stealth_published_posts;
	}

	/**
	 * Modifies the WP query to exclude stealth published posts from feeds and the home page
	 *
	 * @since 1.0
	 *
	 * @param string $where The current WHERE condition string
	 * @param WP_Query $wp_query The query object (not provided by WP prior to WP 3.0)
	 * @return string The potentially amended WHERE condition string to exclude stealth published posts
	 */
	public static function stealth_publish_where( $where, $wp_query = null ) {
		global $wpdb;
		if ( !$wp_query )
			global $wp_query;

		// The third condition is for when a query_posts() (or similar) query from the front page is called that
		// undermines is_home() (such as when querying for posts in a particular category)
		if ( $wp_query->is_home || $wp_query->is_feed ||
			( trailingslashit( get_option( 'siteurl' ) ) == trailingslashit( 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] ) ) ) {
			$stealth_published_posts = implode( ',', self::find_stealth_published_post_ids() );
			if ( !empty( $stealth_published_posts ) )
				$where .= " AND $wpdb->posts.ID NOT IN ($stealth_published_posts)";
		}
		return $where;
	}

	/**
	 * Handles silent publishing if the associated checkbox is checked.
	 *
	 * @since 2.0
	 *
	 * @param int $post_id Post ID
	 * @return void
	 */
	public static function publish_post( $post_id ) {
		if ( isset( $_POST[self::$field] ) && $_POST[self::$field] && (bool) apply_filters( 'stealth_publish_silent', true, $post_id ) )
			define( 'WP_IMPORTING', true );
	}

} // end class

c2c_StealthPublish::init();

endif;
?>