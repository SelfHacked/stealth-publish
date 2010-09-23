<?php
/*
Plugin Name: Stealth Publish
Version: 1.0
Plugin URI: http://coffee2code.com/wp-plugins/stealth-publish
Author: Scott Reilly
Author URI: http://coffee2code.com
Description: Prevent specified posts from being featured on the front page or in feeds.

Posts that are assigned a custom field of "stealth-publish" with a value of "1" will no longer be featured on the front page of
the blog, nor will the post be included in any feeds.  Beneficial in instances where you want to publish new content 
without any fanfare and just want the post added to archive and category pages and its own permalink page.

NOTE: Use of other plugins making their own queries against the database to find posts will likely allow a post to appear on
the front page.  But use of the standard WordPress functions for retrieving posts (as done for the main posts query) should
not allow stealth published posts to appear on the home page.

Compatible with WordPress 1.5+, 2.0+, 2.1+, 2.2+, 2.3+, and 2.5.

=>> Read the accompanying readme.txt file for more information.  Also, visit the plugin's homepage
=>> for more information and the latest updates

Installation:

1. Download the file http://coffee2code.com/wp-plugins/stealth-publish.zip and unzip it into your 
/wp-content/plugins/ directory.
2. Activate the plugin through the 'Plugins' admin menu in WordPress
3. For posts that you do not want to be featured on the front page and feeds, assign them a 
custom field of "stealth-publish" with a value of "1"

*/

/*
Copyright (c) 2007-2008 by Scott Reilly (aka coffee2code)

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

/* Returns an array of post IDs that are to be stealth published */
function find_stealth_published_post_ids() {
	global $wpdb, $stealth_published_posts;
	$stealth_publish_meta_key = 'stealth-publish';
	$stealth_publish_sql = "SELECT DISTINCT ID FROM $wpdb->posts AS p 
						LEFT JOIN $wpdb->postmeta AS pm ON (p.ID = pm.post_id)
						WHERE pm.meta_key = '$stealth_publish_meta_key' AND pm.meta_value = '1'
						GROUP BY pm.post_id";
	$stealth_published_posts = $wpdb->get_col($stealth_publish_sql);
	return $stealth_published_posts;
}

/* Modifies the WP query to exclude stealth published posts from feeds and the home page */
function stealth_publish_where($where) {
	global $wpdb;
	// That third conditional check is a bit hacky.  It's there in the event a query_posts() (or similar) query from the front
	//		page is called that undermines is_home() (such as when querying for posts in a particular category)
	if ( is_home() || is_feed() ||
	(trailingslashit(get_option('siteurl')) == trailingslashit('http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'])) ) {
		$stealth_published_posts = implode(',', find_stealth_published_post_ids());
		if (!empty($stealth_published_posts))
			$where .= " AND $wpdb->posts.ID NOT IN ($stealth_published_posts)";
	}
	return $where;
}

add_filter('posts_where', 'stealth_publish_where');
?>