<?php
class WikiController {
	
	function get_author($post) {
		$tmp = get_userdata($post->post_author);
		
		if ($tmp->ID > 0):
			return $tmp->display_name;
		else:
			$anon_meta = get_post_meta($post->ID, '_wpw_anon_meta', true);
			return 'anonymous ('.$anon_meta['ip'].', '.$anon_meta['hostname'].')';
		endif;
	}
	
	function post_revisions() {
		global $post, $current_user, $role;
		if(wiki_back_compat('front_end_check')) {
		$wpw_options = get_option('wpw_options');
		$revisions = get_posts('post_status=any&post_type=revision&post_parent='.$post->ID.'&numberposts='.$wpw_options['number_of_revisions']);
		
		//Most recent revision
		$date = date(__('m/d/y g:i a'), mktime($post->post_modified));
		
		$author = wpw_get_author($post);
		
		$latest_revision = sprintf(__('Latest revision (@ %1s by %2s)'), $post->post_modified, $author);
		
		$output = '<a href="'.get_permalink($post->ID).'">'.$latest_revision.'</a><br />';
		
		//If we have revisions...
		if($revisions) {
			//Loop through them!
			foreach ($revisions as $revision) {
				if( @wp_get_post_autosave($post->ID)->ID != $revision->ID) {
					
					$author = wpw_get_author($revision);
					
					$date = date(__('m/d/y g:i a'), mktime($revision->post_modified) );
					$revision_title = sprintf(__('Revision @ %1s by %2s'), $date, $author);
					$output.= '<a href="'.get_permalink($post->ID).'?revision='.$revision->ID.'">'.$revision_title.'</a><br />';
				}
			}
		}
		return $output;
		}
	}
	
	function table_of_contents($content) {
	
		//This creates the Table of Contents
	
		global $wpdb,$post;
		$wpw_options = get_option('wpw_options');
		(get_post_meta($post->ID, '_wiki_page_toc', true) == 1) ? $toc = true : $toc = false;
		
		if (!wiki_back_compat('front_end_check')) {
			return $content;
		}
	
	    // Check whether table of contents is set or not
		// second condition checks: are we on the front page and
		// is front page displaying set. - tony@irational.org
		if ( !$toc || is_front_page() && !$wpw_options['show_toc_onfrontpage'] ) {
			return $content;
		}
	
		preg_match_all("|<h2>(.*)</h2>|", $content, $h2s, PREG_PATTERN_ORDER);
		$content = preg_replace("|<h2>(.*)</h2>|", "<a name='$1'></a><h2>$1</h2>", $content);
		$content = preg_replace("|<h3>(.*)</h3>|", "<a name='$1'></a><h3>$1</h3>", $content);
		$h2s = $h2s[1];
		$content = str_replace("\n", "::newline::", $content);
		preg_match_all("|</h2>(.*)<h2>|U", $content, $h3s_contents, PREG_PATTERN_ORDER);
		
		//The following lines are really ugly for finding <h3> after the last </h2> please tidy it up if u know a better solution, and please let us know about it.
	
		$last_h2_pos = explode('</h2>', $content);
		$last_h2_pos = array_pop($last_h2_pos);
		$last_h2_pos[1] = $last_h2_pos;
		$h3s_contents[1][] = $last_h2_pos;
		if (!is_array($h3s_contents[1])) {
			$h3s_contents[1] = array();
		}
		array_push($h3s_contents[1], $last_h2_pos);
		foreach ($h3s_contents[1] as $key => $h3s_content) {
			preg_match_all("|<h3>(.*)</h3>|U", $h3s_content, $h3s[$key], PREG_PATTERN_ORDER);
		}
		$table = "<ol class='content_list'>";
		foreach($h2s as $key => $h2) {
			$table .= "<li><a href='#$h2'>".($key+1)." ".$h2."</a></li>";
			if (!empty($h3s[$key][1])) {
				foreach($h3s[$key][1] as $key1 => $h3) {
					$table .= "<li class='lvl2'><a href='#$h3'>".($key+1).".".($key1+1)." ".$h3."</a></li>";
				}
			}
		}
		$table .= "</ol>";
		$content = str_replace("::newline::", "\n", $content);
		return "<div class='contents alignright'><h3>".__('Contents')."</h3><p> &#91; <a class='show' onclick='toggle_hide_show(this)'>".__('hide')."</a> &#93; </p>$table</div>".$content;
	}
	
	
	function styles() {
	    echo "<link href='". PLUGIN_URL ."/".WPWIKI_DIR_NAME."/style.css' rel='stylesheet' type='text/css' />";
	}
	
	function scripts() {
	   wp_enqueue_script("jquery");
	   wp_enqueue_script('wordpress-wiki', PLUGIN_URL ."/".WPWIKI_DIR_NAME."/wordpress-wiki.js");
	}
}

?>