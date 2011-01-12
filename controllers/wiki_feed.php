<?php
class WikiFeed {
	/**
	 * Add new feed to WordPress
	 * @global <type> $wp_rewrite
	 */
	function add_feed(  ) {
	    if (function_exists('load_plugin_textdomain')) {
	        $plugin_dir = basename(dirname(__FILE__));
	        load_plugin_textdomain('wordpress_wiki', '', $plugin_dir);
	    }
	
	    global $wp_rewrite;
	    add_feed('wiki', array($this,'create_feed'));
	    add_action('generate_rewrite_rules', array($this,'rewrite_rules');
	    $wp_rewrite->flush_rules();
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
	
	    header('Content-type: text/xml; charset=' . get_option('blog_charset'), true);
	    echo '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?'.'>';
	?>
	<rss version="2.0"
		xmlns:content="http://purl.org/rss/1.0/modules/content/"
		xmlns:wfw="http://wellformedweb.org/CommentAPI/"
		xmlns:dc="http://purl.org/dc/elements/1.1/"
	>
	
	<channel>
		<title><?php print(__('Recently modified wiki pages for : ')); bloginfo_rss('name'); ?></title>
		<link><?php bloginfo_rss('url') ?></link>
		<description><?php bloginfo_rss("description") ?></description>
		<pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', get_lastpostmodified('GMT'), false); ?></pubDate>
		<generator>http://wordpress.org/?v=<?php bloginfo_rss('version'); ?></generator>
		<language><?php echo get_option('rss_language'); ?></language>
	<?php
			if (count($posts) > 0) {
				foreach ($posts as $post) {
					$content = '
	                            <p>'.__('wiki URL: ').'<a href="'. get_permalink($post->ID).'">'.$post->post_title.'</a></p>
	                    <p>'.__('Modifiyed By: ').'<a href="'. get_author_posts_url($post->post_author).'">'. get_author_name($post->post_author).'</a></p>
					';
	?>
		<item>
			<title><![CDATA[<?php print(htmlspecialchars($post->post_title)); ?>]]></title>
	        <link><![CDATA[<?php print(get_permalink($post->ID)); ?>]]></link>
			<pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', $post->post_date_gmt, false); ?></pubDate>
			<guid isPermaLink="false"><?php print($post->ID); ?></guid>
			<description><![CDATA[<?php print($content); ?>]]></description>
			<content:encoded><![CDATA[<?php print($content); ?>]]></content:encoded>
		</item>
	<?php $items_count++; if (($items_count == get_option('posts_per_rss')) && !is_date()) { break; } } } ?>
	</channel>
	</rss>
	<?php
			die();
	}

}
?>