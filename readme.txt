=== Stealth Publish  ===
Contributors: coffee2code
Donate link: http://coffee2code.com
Tags: post, archive, feed, feature, home
Requires at least: 1.5
Tested up to: 2.5
Stable tag: trunk
Version: 1.0

Prevent specified posts from being featured on the front page or in feeds.

== Description ==

Prevent specified posts from being featured on the front page or in feeds.  Beneficial in instances where you want to publish new content without any fanfare and just want the post added to archive and category pages and its own permalink page.

Posts that are assigned a custom field of "stealth-publish" with a value of "1" will no longer be featured on the front page of the blog, nor will the post be included in any feeds.

NOTE: Use of other plugins making their own queries against the database to find posts will likely allow a post to appear on the front page.  But use of the standard WordPress functions for retrieving posts (as done for the main posts query) should not allow stealth published posts to appear on the home page.

== Installation ==

1. Unzip `stealth-publish.zip` inside the `/wp-content/plugins/` directory, or upload `stealth-publish.php` into `/wp-content/plugins/`
1. Activate the plugin through the 'Plugins' admin menu in WordPress
1. For posts that you do not want to be featured on the front page and feeds, assign them a custom field of "stealth-publish" with a value of "1"

== Frequently Asked Questions ==

= Why would I want to stealth publish a post? =

This is probably the kind of thing that you would recognize the need for or you don't.  It's beneficial in instances where you want to publish new content without any fanfare and just want the post added to archive and category pages and its own permalink page.
