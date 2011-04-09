<?php
class WikiFeed {
	/**
	 * Add new feed to WordPress
	 * @global <type> $wp_rewrite
	 */
	function add_feed() {
	    if (function_exists('load_plugin_textdomain')) {
	        $plugin_dir = basename(dirname(__FILE__));
	        load_plugin_textdomain('wordpress_wiki', '', $plugin_dir);
	    }
	
	    add_feed('wiki', array($this,'create_feed'));
	    add_action('generate_rewrite_rules', array($this,'feed_rewrite_rules'));
	}
	
	/**
	 * Modify feed rewrite rules
	 * @param <type> $wp_rewrite
	 */
	function feed_rewrite_rules( $wp_rewrite ) {
	  $new_rules = array(
	    'feed/(.+)' => 'index.php?feed='.$wp_rewrite->preg_index(1)
	  );
	  $wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
	}
	
	/**
	 * This function creates the actual feed
	 */
	function create_feed() {
	    global $wpdb, $wp_version;
		if ($wp_version < 3.0) {
			$where ="ID in (select post_id from $wpdb->postmeta where
					meta_key = 'wiki_page' and meta_value = 1)
					and post_type in ('post','page')";
		} else {
			$where ="post_type = 'wiki' AND post_parent = ''";
		}
	    
	    $posts = $wpdb->get_results($wpdb->prepare("select * from $wpdb->posts where $where
	                    order by post_modified desc"));
	
	 	include(WPWIKI_FILE_PATH.'/views/feed.php');
	 }

}
?>