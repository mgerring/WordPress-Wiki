<?php
class WikiPageController {

	global $WikiHelper;
	
	
	function __construct() {
		$this->WikiPageController();
	} 
	
	function WikiPageController() {
		add_filter('wp_insert_post_data','wpw_save_code', '99');
	}
	//actions and filters
	//add_action('get_header','invoke_editor');

	//add_action('wp_ajax_ajax_save',array($this,'ajax_save');
	//add_action('wp_ajax_nopriv_ajax_save',array($this,'ajax_save');
	
	
	
	

	
	function post_revisions() {
		global $post, $current_user, $role;
		if($WikiHelper->is_wiki('front_end_check')) {
		$wpw_options = get_option('wpw_options');
		$revisions = get_posts('post_status=any&post_type=revision&post_parent='.$post->ID.'&numberposts='.$wpw_options['number_of_revisions']);
		
		//Most recent revision
		$date = date(__('m/d/y g:i a'), mktime($post->post_modified));
		
		$author = $WikiHelper->get_author($post);
		
		$latest_revision = sprintf(__('Latest revision (@ %1s by %2s)'), $post->post_modified, $author);
		
		$output = '<a href="'.get_permalink($post->ID).'">'.$latest_revision.'</a><br />';
		
		//If we have revisions...
		if($revisions) {
			//Loop through them!
			foreach ($revisions as $revision) {
				if( @wp_get_post_autosave($post->ID)->ID != $revision->ID) {
					
					$author = $WikiHelper->get_author($revision);
					
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
		
		if (!$WikiHelper->is_wiki('front_end_check')) {
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
		wp_enqueue_style('wordpress-wiki', PLUGIN_URL ."/".WPWIKI_DIR_NAME."/style.css");
	}
	
	function scripts() {
	   wp_enqueue_script("jquery");
	   wp_enqueue_script('wordpress-wiki', PLUGIN_URL ."/".WPWIKI_DIR_NAME."/wordpress-wiki.js");
	   wp_enqueue_script('jquery-ui-tabs');
	}
	
	

	function set_query() {
		if(isset($_GET['revision'])) {
			global $post;
			$revision_data = get_post($_GET['revision']);
			$revision_author = get_userdata($revision_data->post_author);
			
			$warning = '<div id="wpw_read_revision_warning">'.__('Currently working with revision').' @ '.$revision_data->post_modified.' '.__('by').' '.$revision_author->display_name.'. <a href="'.get_permalink($post->ID).'">'.__('Current version').'</a></div>';
			$post->is_revision = true;
			$post->revision_content = $revision_data->post_content;
			$post->revision_author = $revision_author->display_name;
			$post->revision_warning = $warning;
		}
	}
	
	if( !defined('DOING_AJAX') && isset($_POST['wpw_editor_content']) )
		add_action('init','wpw_no_js_save');
	
	
	
	function invoke_editor() {
		global $post;
		if ( $WikiHelper->is_wiki('front_end_check') ) {
			$wpw_options = get_option('wpw_options');
			//if ( current_user_can('edit_wiki') ) {
				remove_filter('the_content', 'wpautop');
				remove_filter('the_content', 'wptexturize');
				add_action('wp_head', 'styles');
				add_action('wp_head', 'scripts', 9);
				add_filter('the_content',array($this, 'substitute_in_revision_content'),11);
				add_filter('the_content',array($this,'interface'),12);
				add_action('wp_footer',array($this,'inline_editor'));
					
			//} else {
			//	add_filter('the_content','wpw_nope');
			//}
		}
	}

	
	//First, if the user isn't logged in
	
	function wpw_nope($content) {
		global $post;
		$content = wpw_wiki_parser($content, $post->post_title);
		$content = wpw_table_of_contents($content);
		$message = __('This page is a Wiki!');
		$message .= '&nbsp;<a href="'.wp_login_url(get_permalink($post->ID)).'">'.__('Log in or register an account to edit.').'</a>';
		return $content.$message;
	}
	
	//If the user is logged in
	function substitute_in_revision_content($content) {
		global $post;
		if(isset($post->is_revision) && $post->is_revision == true) {
			$content = $post->revision_content;
			return $content;
		} else {
			return $content;
		}
	}
	
	function wiki_parser($content) {
		global $post;
		$wiki_parser = new WPW_WikiParser();
		$wiki_parser->reference_wiki = get_bloginfo('url').'/wiki/';
		$wiki_parser->suppress_linebreaks = true;	
		$content = $wiki_parser->parse($content, $post->post_title);
		$content = wpautop($content);	
		return $content;
		unset($wiki_parser);
	}
	
	function get_content($content, $class = null ){
		global $post;
		return '<div id="wpw_read_div" '.$class.'>'.$this->table_of_contents( wptexturize( $this->wiki_parser($content) ) ).'</div>';	
	}
	
	function get_edit($content, $class = null ){
		global $post;
		return '<div id="wpw_edit_div" '.$class.'>
					<form action="" method="post">
						<textarea name="wpw_editor_content" style="width:100%;height:200px;" id="area1">'.$content.'</textarea>
						'.wp_nonce_field('wpw_edit_form').'
						<input type="submit" value="save" id="wpw_save" />
						<input type="hidden" value="'.$post->ID.'" name="wpw_id" />
					</form>
				</div>';
	}
	
	function get_history($content, $class = null){
		return '<div id="wpw_view_history_div" '.$class.'>'.wiki_post_revisions().'</div>';
	}
	
	function get_section($content = null, $section, $class) {
		if ($content == null):
			global $post;
			$content = $post->post_content;
		endif;
		
		if (in_array($section, array('content','edit','history'))):
			$func = 'get_'.$section;
			return $this->$func($content, $class);
		endif;
	}
	
	function interface($content) {
		global $post;
		
		get_option('wpw_options');
		
		$wiki_interface = array('content','edit','history');
		$return = "";
		$interface = "content";
		
		if ( in_array( @$_GET['wpw_action'], $wiki_interface ) )
			$interface = $_GET['wpw_action'];
		
		(isset($post->revision_warning)) ? $warning = $post->revision_warning : $warning = false;
		(isset($post->wpw_post_saved)) ? $update = "Post updated!" : $update = false;
		
		foreach( $wiki_interface as $wiki ):
			
			if ( $interface != $wiki ):
				$class = 'class="wpw-hide-it"';
			else:
				$class = null;
			endif;
				
			$return .= $this->get_section( $content, $wiki, $class );
		
		endforeach;
			
		return 
			$update.'
			<div id="wpw_tabs">
			<ul id="wpw_tab_nav">
				<li><a id="wpw_read" href="?wpw_action=content">Read</a></li>
				<li><a id="wpw_edit" href="?wpw_action=edit">Edit</a></li>
				<li><a id="wpw_view_history" href="?wpw_action=history">View History</a></li>
			</ul>
			'.$warning
			 .$return.'
			</div>
		';
	}
	
	
	//Inline editor not implemented yet
	function inline_editor() {
		global $post;
		$wpw_options = get_option('wpw_options');
		$nicedit_icons_path = plugin_dir_url(__FILE__).'nicedit/nicEditorIcons.gif';
		$wpw_ajax_url = admin_url('admin-ajax.php');
		$get_content_to_save = '$("#area1").val()';
		$nicedit = false;
		
		$ajax_args = '	action : "ajax_save",
						wpw_editor_content : '.$get_content_to_save.',
						wpw_id : "'.$post->ID.'",
						_wpnonce : $("#_wpnonce").val()';
		
		if ( isset($wpw_options['revision_pending']) && $wpw_options['revision_pending'] == "true" )
			$ajax_args .= ', wpw_revision_stack: "1"';
			
		
		echo '
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				
				'.$nicedit.'
					
				$("#wpw_save").click(function( e ) {
					e.preventDefault();
					data = {
						'.$ajax_args.'
					};
					$.post("'.$wpw_ajax_url.'", data, function(results) {
						$("#wpw_view_history_div").load(location.href+" #wpw_view_history_div>*", function() {
							$("#wpw_read_div").load(location.href+" #wpw_read_div>*", function() {
								alert(results);
							});
						});
					});
				});
				
				$("#wpw_tab_nav li a").each(function() {
					$(this).attr( "href", "#"+$(this).attr( "id" )+"_div" );
				});
				
				$(window).load(function() {
					$(".wpw-hide-it").removeClass("wpw-hide-it");
					$("#wpw_tabs").tabs();
				});	
			});
		</script>
		';
	}
	
	function save_code($data,$postarr = Array()) {	
		$regex = '/(?<=^code>|pre>|%%%).+?(?=<\/$1$>)/sm';
		$data['post_content'] = preg_replace_callback($regex, array($this, 'nowiki'),  $data['post_content']);
		return $data;
	}
	
	function nowiki($match) {
		return '<nowiki>'.htmlentities($match).'</nowiki>';
	}
	
	
	
	function set_toc($post_id) {
		if ($WikiHelper->is_wiki('check_no_post',$post_id))
			update_post_meta($post_id,'_wiki_page_toc',1);
	}
	
	function save_post() {
		if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'wpw_edit_form')) {
			if ($_POST['wpw_editor_content'] != null) {
				extract($_POST);
			}
			//First, save everything marked as code
			
			/*
			//Checks to see if changes in the draft page are being committed to the parent page.
			if ($commit == 1) {
				// If so, we use the parent ID.
				$pid = $bio_id;
			} else {
				//Otherwise, we need to check if we're reverting the draft to a prior revision.
				//This bit of code takes our revision ID and gets the ID of the page it belongs to.
				$rev = wp_is_post_revision($draft_id);
				if ($rev) {
					$pid = $rev;
				} else {
				//If we're making new changes to the draft, and not committing it, and not working from a revision, we simply
				//use the ID passed along with the form.
				$pid = $draft_id;
				}
			}
			*/
			$n_post = array();
			//if (!isset($wpw_revision_stack)) {
				
				$n_post['post_content'] = $wpw_editor_content;
				/*
				if ($commit != 1) {
					$n_post['post_content'] .='[swrmeta dob="'.$dob.'" loc="'.$loc.'" state="'.$state.'" sum_content="'.htmlspecialchars($sum_content).'" lnk1="'.$lnk1.'" lnk2="'.$lnk2.'" lnk3="'.$lnk3.'"]';
				}
				*//*
				if (!is_user_logged_in())
					$n_post['post_author'] = 0;
	
				$n_post['ID'] = $wpw_id;
			// Insert the post into the database
				$n_id = wp_update_post( $n_post );
				
				if (!is_user_logged_in()):
					$wpw_anon_meta = array(
						'ip' => $_SERVER['REMOTE_ADDR'],
						'hostname' => $_SERVER['REMOTE_HOST']
					);
					
					add_post_meta($n_id, '_wpw_anon_meta', $wpw_anon_meta);
				endif;
				
				return $n_id;
			/*
			} else {
				$n_post = array();
				$n_post['post_parent'] = $wpw_id;
				$n_post['post_title'] = get_the_title($wpw_id);
				$n_post['post_content'] = clean_pre(apply_filters('wp_insert_post_data',htmlspecialchars_decode($wpw_editor_content)));
				//$n_post['post_content'] = $wpw_editor_content;
				$n_post['post_status'] = 'pending';
					//$n_post['post_author'] = 1;
				$n_post['post_type'] = 'wiki';
					//$n_post['page_template'] = 'wiki.php';
				// Insert the post into the database
				if (wp_insert_post( $n_post ))
					die('Post submitted for review!');
			
			*/
			/*
			$bio_meta_keys = array('dob','loc','state','lnks','notes');
			foreach ($bio_meta_keys as $key => $value) {
				update_post_meta($pid, $value, strip_tags($$value), FALSE);
			}
			update_post_meta($pid, 'sum_content',htmlspecialchars_decode($sum_content));
			*/
		} else {
			//This is the error message that displays if a user has no credentials to edit pages.
			die(__('You don\'t have permission to do that.'));
		}
	}
	
	function ajax_save() {
		if (wpw_save_post())
			die('Post saved!');
	}
	
	function no_js_save() {
		if ( isset( $_POST['wpw_editor_content'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'wpw_edit_form' ) ):
			if ( wpw_save_post() ):
				$post->wpw_post_saved = true;
			endif;
		endif;
	}
}

?>