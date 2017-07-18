=== FeedBurnerCount ===
Contributors: Appletalk
Donate link: http://devthought.com/projects/wordpress/feedburnercount/
Tags: feedburner, feeds, count, rss, counter, admin
Requires at least: 2.2
Tested up to: 2.7.1
Stable tag: 0.1

A well-optimized and reliable plugin that connects to the FeedBurner Awareness API to retrieve your readers count, that you can print out in plain text.

== Description ==

Features:

* Easy installation and setup
* Use `<?php echo fbc_count() ?>` to output the count. No unneeded options, no extra markup, place it wherever you want.
* If feed count cannot be retrieved, you can choose to display a custom 'N/A' text or keep the last retrieved count.
* Performance optimized for retrieval and parsing of the result.
* Average count calculation between dates. Since FeedBurner produces different readers count for each day, you can display to your users an average of, for example, the last 30 days.

== Installation ==

1. Upload `feedburnercount.php` to the `/wp-content/plugins/` directory
2. Activate the plugin through the **Plugins** menu in WordPress
3. Adjust its settings according to your preferences under **Settings**
4. Place `<?php echo fbc_count() ?>` to print out the readers count in your templates

== Frequently Asked Questions ==

= Help! It's not working =

Check the HTML code of the page (by viewing the source in your browser), and find the place where fbc_count() is called. A HTML comment might be there explaining the cause.

= Isn't there a plugin like this already? =

Yes, but it was unmaintained and needed improvement. This one is much more reliable and flexible.