<?php
class WikiPageController {
	
	function __construct() {
		$this->WikiPageController();
	} 
	
	function WikiPageController() {
		add_filter('wp_insert_post_data',array($this,'save_code'), '99');
		$this->WikiHelper = new WikiHelpers();
	}
	
	function post_revisions() {
		global $post, $current_user, $role;
		if($this->WikiHelper->is_wiki('front_end_check')) {
		$wpw_options = get_option('wpw_options');
		$revisions = get_posts('post_status=any&post_type=revision&post_parent='.$post->ID.'&numberposts='.$wpw_options['number_of_revisions']);
		
		//Most recent revision
		$date = date(__('m/d/y g:i a'), mktime($post->post_modified));
		
		$author = $this->WikiHelper->get_author($post);
		
		$latest_revision = sprintf(__('Latest revision (@ %1s by %2s)'), $post->post_modified, $author);
		
		$output = '<a href="'.get_permalink($post->ID).'">'.$latest_revision.'</a><br />';
		
		//If we have revisions...
		if($revisions) {
			//Loop through them!
			$count = 0;
			foreach ($revisions as $revision) {
				if( @wp_get_post_autosave($post->ID)->ID != $revision->ID) {
					
					$author = $this->WikiHelper->get_author($revision);
					
					$date = date(__('m/d/y g:i a'), mktime($revision->post_modified) );
					$revision_title = sprintf(__('Revision @ %1s by %2s'), $date, $author);
					$output.= '<a href="'.get_permalink($post->ID).'?revision='.$revision->ID.'">'.$revision_title.'</a><br />';
					$count++;	
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
		
		if (!$this->WikiHelper->is_wiki('front_end_check')) {
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
    	wp_enqueue_style('wordpress-wiki', PLUGIN_URL ."/".WPWIKI_DIR_NAME."/static/style.css");
    	if ( is_rtl() )
        	wp_enqueue_style('wordpress-wiki-rtl', PLUGIN_URL ."/".WPWIKI_DIR_NAME."/static/rtl.css");
	}
	
	function scripts() {
	   wp_enqueue_script("jquery");
	   wp_enqueue_script('wordpress-wiki', PLUGIN_URL ."/".WPWIKI_DIR_NAME."/static/wordpress-wiki.js");
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
	
	function invoke_editor() {
		global $post;
		if ( $this->WikiHelper->is_wiki('front_end_check') ) {
			$wpw_options = get_option('wpw_options');
			remove_filter('the_content', 'wpautop');
			remove_filter('the_content', 'wptexturize');
			add_action('get_header', array($this,'styles'));
			add_action('get_header', array($this,'scripts'), 9);
			if ( !$this->WikiHelper->is_restricted() ) {
				add_filter('the_content',array($this, 'substitute_in_revision_content'),11);
				add_filter('the_content',array($this,'front_end_interface'),12);
				add_action('wp_footer',array($this,'inline_editor'));
			} else {
				add_filter('the_content',array($this,'wpw_nope') );
			}
		}
	}

	
	//First, if the user isn't logged in
	
	function wpw_nope($content) {
		global $post;
		$content = $this->get_content($content);
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
		return '<div id="wpw_view_history_div" '.$class.'>'.$this->post_revisions().'</div>';
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
	
	function front_end_interface($content) {
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
		if ($this->WikiHelper->is_wiki('check_no_post',$post_id) & get_post_meta($post_id,'_wiki_page_toc_on_by_default', true) != 1) {
			update_post_meta($post_id,'_wiki_page_toc',1);
			update_post_meta($post_id,'_wiki_page_toc_on_by_default',1);
		}
	}
	
	function save_post() {
		if (!$this->WikiHelper->is_restricted() && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'wpw_edit_form')) {
			$wpw_options = get_option('wpw_options');
			extract($_POST);
			$n_post = get_post($wpw_id, ARRAY_A); 
			$return = array();
				$n_post['post_content'] = $wpw_editor_content;

				if (!is_user_logged_in())
					$n_post['post_author'] = 0;

				if( isset($wpw_options['revision_pending']) && $wpw_options['revision_pending'] == "true" ):
					
					$n_post['post_parent'] = $wpw_id;
					$n_post['post_status'] = 'pending';
					$n_post['post_type'] = 'revision';
					$return['message'] = "Revision submitted for review.";
				else:
					$n_post['ID'] = $wpw_id;
					$return['message'] = "Post saved.";
				endif;

				if (!is_user_logged_in()):
					$wpw_anon_meta = array(
						'ip' => $_SERVER['REMOTE_ADDR'],
						'hostname' => $_SERVER['REMOTE_HOST']
					);
					
					add_post_meta($n_id, '_wpw_anon_meta', $wpw_anon_meta);
				endif;
				$return['status'] = wp_insert_post( $n_post );
				return $return;
		} else {
			//This is the error message that displays if a user has no credentials to edit pages.
			die(__('You don\'t have permission to do that.'));
		}
	}
	
	function ajax_save() {
		if (0 != $return = $this->save_post())
			die($return['message']);
	}
	
	function no_js_save() {
		if ( isset( $_POST['wpw_editor_content'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'wpw_edit_form' ) ):
			if ( $this->save_post() ):
				$post->wpw_post_saved = true;
			endif;
		endif;
	}
	
	//Code shamelessly stolen from here: http://www.blogseye.com/2010/05/creating-fake-wordpress-posts-on-the-fly/comment-page-1/#comment-253
	function fake_page() {
		global $wp_query, $post;
	  	if($wp_query->is_404 && isset($_GET['redlink']) && $_GET['redlink'] == 1 ) {
	  		$new_title = strip_tags($_GET['title']);
			$new_page_nonce = wp_create_nonce('wpw_new_page_nonce');
			$get_params = '?new_wiki_page=true&nonce='.$new_page_nonce.'&title='.$new_title;
			$new_link = '<a href="'.get_bloginfo('url').'/wiki/new'.$get_params.'">Click here to create it.</a>';
			$id=-42; // need an id
			$post = new stdClass();
				$post->ID= $id;
				$post->post_category= array('Uncategorized'); //Add some categories. an array()???
				$post->post_content='A wiki page with the title '.$new_title.' could not be found. '.$new_link; //The full text of the post.
				$post->post_excerpt= $post->post_content; //For all your post excerpt needs.
				$post->post_status='publish'; //Set the status of the new post.
				$post->post_title= 'New Wiki Page'; //The title of your post.
				$post->post_type='page'; //Sometimes you might want to post a page.
				$post->comment_status = 'open';
				$post->post_date = date('Y-m-d H:i:s', time());
			$wp_query->queried_object=$post;
			$wp_query->post=$post;
			$wp_query->found_posts = 1;
			$wp_query->post_count = 1;
			$wp_query->max_num_pages = 1;
			$wp_query->is_single = 1;
			$wp_query->is_404 = false;
			$wp_query->is_posts_page = false;
			$wp_query->posts = array($post);
			$wp_query->is_page = true;
			$wp_query->page= 1;
			//$wp_query->is_post=true;
			//$wp_query->page=false;
		}
	}
	
	function create_new_and_redirect() {
	    //echo 'workin?';
	    if (isset($_GET['new_wiki_page']) && $_GET['new_wiki_page'] == 'true' && wp_verify_nonce($_GET['nonce'], 'wpw_new_page_nonce')) {
	
	    global $wp_version;
	    global $wpdb;
	
	    $new_wiki = array();
	
	    $title = strip_tags($_GET['title']);
	    $pieces = explode(':',$title,2);
	    if (count($pieces) == 2) {
	            list($namespace,$topic) = $pieces;
	            $namespace = strtolower(preg_replace('/[ -]+/', '-', $namespace));
	            $parent_id = $wpdb->get_var('SELECT id FROM `' . $wpdb->posts . '` WHERE post_name = "' . $namespace .'"');
	            if ($parent_id)
	                    $new_wiki['post_parent'] = $parent_id;
	    }
	    else {  
	            $namespace = '';
	            $topic = $title;
	    }
	    $topic = strtolower(preg_replace('/[ -]+/', '-', $topic));
	    $url = get_option('siteurl') . '/wiki/' . ($namespace ? $namespace.'/' : '') . $topic;
	
	    $new_wiki['post_name'] = $topic;
	    $new_wiki['post_title'] = $title;
	    $new_wiki['post_content'] = 'Click the "Edit" tab to add content to this page.';
	    $new_wiki['guid'] = $url;
	    $new_wiki['post_status'] = 'publish';
	
	    if ($wp_version >= 3.0) {
	            $new_wiki['post_type'] = 'wiki';
	    }
	
	    $new_wiki_id = wp_insert_post($new_wiki);
	
	    if($wp_version <= 3.0) {
	            update_post_meta($new_wiki_id, '_wiki_page', 1);
	    }
	
	    wp_redirect( $url );
	    exit();
	    }
	}
	
	function anon_meta_save_as_revision($revision_id) {
		
		$old_meta = get_post_meta(wp_is_post_revision($revision_id), '_wpw_anon_meta', true);
		
		if(!empty($old_meta)) {
			add_metadata('post', $revision_id, '_wpw_anon_meta', $old_meta);
			delete_post_meta(wp_is_post_revision($revision_id), '_wpw_anon_meta', $old_meta);
		}
	
	}

}

?>