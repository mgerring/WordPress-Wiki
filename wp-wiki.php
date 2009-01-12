<?php
/*
Plugin Name:WordPress Wiki
Plugin URI: http://wordpress.org/extend/plugins/wordpress-wiki/
Description: Add Wiki functionality to your wordpress site.
Version: 0.2
Author: Instinct Entertainment
Author URI: http://www.instinct.co.nz
/* Major version for "major" releases */



/**
*  The following roles and capabilities code has been removed because it does not work. If you can help with this, please do.
*/
/*
global $wp_roles;

if ( ! isset($wp_roles) )
	$wp_roles = new WP_Roles();

$role = get_role('wiki_editor');
$role->add_cap('edit_wiki');
$role->add_cap('read');

$role = get_role('administrator');
$role->add_cap('edit_wiki');
*/

//exit("<pre>".print_r($role,1)."</pre>");
function wiki_post_revisions($content='') {
	global $post, $current_user, $role;
	if ( !$post = get_post( $post_id ) )
		return;
	$initial_post_id = $post->ID;
	if($post->post_type == 'revision' && ($post->post_parent > 0)) {
		if(!$post = get_post( $post->post_parent ))
			return;
	}
	
	$defaults = array( 'parent' => false, 'right' => false, 'left' => false, 'format' => 'list', 'type' => 'all' );
	extract( wp_parse_args( $args, $defaults ), EXTR_SKIP );
	$type = 'revision';
	switch ( $type ) {
	case 'autosave' :
		if ( !$autosave = wp_get_post_autosave( $post->ID ) )
			return;
		$revisions = array( $autosave );
		break;
	case 'revision' : // just revisions - remove autosave later
	case 'all' :
	default :
		if ( !$revisions = wp_get_post_revisions( $post->ID ) )
			return;
		break;
	}

		//echo("<pre>".print_r($revisions,true)."</pre>");
	$titlef = _c( '%1$s by %2$s|post revision 1:datetime, 2:name' );

	if ( $parent )
		array_unshift( $revisions, $post );

	$rows = '';
	$class = false;
	$can_edit_post = current_user_can( 'edit_post', $post->ID );
	foreach ( $revisions as $revision ) {
		$is_selected = '';
		if ( !current_user_can( 'read_post', $revision->ID ) )
			continue;
		if ( 'revision' === $type && wp_is_post_autosave( $revision ) )
			continue;
		if($initial_post_id == $revision->ID ) {
			$is_selected = "class='selected-revision'";
		}
		$date = wiki_post_revision_title( $revision );
		$name = get_author_name( $revision->post_author );
		$title = sprintf( $titlef, $date, $name );
		$rows .= "\t<li $is_selected >$title</li>\n";
	}
	$wpsc_members_data = get_post_meta($post->ID,'wiki_page');
	
	
	/**
	*  The following roles and capabilities code has been removed because it does not work. If you can help with this, please do.
	*/
	/*
$role = get_role('wiki_editor');
	if ($wpsc_members_data[0] == '1') {
		
		$role->add_cap('edit_posts');
		$role->add_cap('edit_pages');
	} else {
		$role->remove_cap('edit_posts');
		$role->remove_cap('edit_pages');
		$role->remove_cap('edit_wiki');
		$current_user->remove_cap('edit_posts');
		$current_user->remove_cap('edit_pages');
		$current_user->remove_cap('edit_wiki');
		echo "123123";
	}
	exit("<pre>".print_r($current_user->allcaps,1)."</pre>");
*/
	if (/*current_user_can('edit_wiki') &&*/ current_user_can('edit_pages')) {
	    $link = get_permalink($post_id);
	    $output .= "<h4>". 'Post Revisions'."</h4>";
	    $output .= "<ul class='post-revisions'>\n";
	    $output .= "\t<li $is_selected ><a href='".$link."'>Current revision</a> by ".get_author_name($post->post_author)." - <a href='".get_edit_post_link( $post->ID )."'>edit</a></li>\n";
	    $output .= $rows;
	    $output .= "</ul>";
	}
	return $content.$output;
}

function wiki_post_revision_title( $revision, $link = true ) {
	if ( !$revision = get_post( $revision ) )
		return $revision;

	if ( !in_array( $revision->post_type, array( 'post', 'page', 'revision' ) ) )
		return false;

	$datef = _c( 'j F, Y @ G:i|revision date format');
	$autosavef = __( '%s [Autosave]' );
	$currentf	= __( '%s [Current Revision]' );

	$date = date_i18n( $datef, strtotime( $revision->post_modified_gmt . ' +0000' ) );
	if ( $link ) {
		$link = get_permalink($revision->post_parent)."?revision=".$revision->ID;
		$date = "<a href='$link'>$date</a>";
	}

	if ( !wp_is_post_revision( $revision ) )
		$date = sprintf( $currentf, $date );
	elseif ( wp_is_post_autosave( $revision ) )
		$date = sprintf( $autosavef, $date );

	return $date;
}


function wiki_substitute_in_revision_id($query) {
	/**
	* 	This function substitutes the revision ID for the post ID
	*  we need to set $query->is_single and $query->is_page to false, otherwise it cannot select the revisions
	*/
	if(((int)$_GET['revision'] > 0) && ($query->is_single == true)) {
		$query->query_vars['page_id'] = $_GET['revision'];
		$query->query_vars['pagename'] = null;
		$query->query_vars['post_type'] = 'revision';
		$query->is_single = false;
	} else if(((int)$_GET['revision'] > 0) && ($query->is_page == true)) {
		$query->query_vars['page_id'] = $_GET['revision'];
		$query->query_vars['pagename'] = null;
		$query->query_vars['post_type'] = 'revision';
		$query->is_single = false;
		$query->is_page = false;
// 		echo("<pre>".print_r($query,true)."</pre>");
	}
}

function wiki_view_sql_query($query) {
	/**
	* 	This function makes wordpress treat a revision as a single post
	*/
	global $wp_query;
	if((int)$_GET['revision'] > 0 ) {
		$wp_query->is_single= true;
// 		echo("<pre>".print_r($wp_query,true)."</pre>");
	}
	return $query;
}



/**
*  wiki page metabox section starts
*/
function wiki_metabox_module() {
		/**
		*  this function creates the HTML for the wiki page metabox module
		*/
  	global $wpdb, $post_meta_cache;
  	
  	if(is_numeric($_GET['post'])) {
    	$post_ID = (int)$_GET['post'];
    	$wpsc_members_data = get_post_meta($post_ID,'wiki_page');
    	if(is_array($wpsc_members_data) && ($wpsc_members_data[0] == 1)) {
    	 	$checked_status = "checked='checked'";
    	} else {
    	  	$checked_status = "";
		}
	} else {
    	$checked_status = "";
	}
?>
		<div id="postvisibility" class="postbox closed">
				<h3> <?php _e('Wordpress Wiki', 'wiki_page')?> </h3>
		<div class="inside">
		<div id="postvisibility">
<?php	
	if (IS_WP25)
		echo "<label class='selectit' for='wiki_page'>
				<input id='wiki_page_check' type='hidden' value='1' name='wiki_page_check' />
				<input id='wiki_page' type='checkbox' $checked_status value='1' name='wiki_page' />
				This page/post is a wiki friendly page and may be edited by authors and contributors.
				</label>";
		echo "</div></div></div>";
}
  
function wiki_metabox_module_submit($post_ID) {
		/**
		*  this function saves the HTML for the wiki page metabox module
		*/
  global $wpdb;
  if(is_numeric($post_ID) && ($_POST['wiki_page_check'] == 1)) {
    if(isset($_POST['wiki_page']) && ($_POST['wiki_page'] == 1)) {
      $wpsc_members_value = 1;
		} else {
      $wpsc_members_value = 0;
		}
    
    $wpsc_check_members_data = $wpdb->get_var("SELECT `meta_id` FROM `".$wpdb->postmeta."` WHERE `post_id` IN('".$post_ID."') AND `meta_key` IN ('wiki_page') LIMIT 1");
    if(is_numeric($wpsc_check_members_data) && ($wpsc_check_members_data > 0)) {
      update_post_meta($post_ID, 'wiki_page', $wpsc_members_value);
		} else {
      add_post_meta($post_ID, 'wiki_page', $wpsc_members_value);		}
    
    // need to change the custom fields value too, else it tries to reset what we just did.
    if(is_array($_POST['meta'])) {
      foreach($_POST['meta'] as $meta_key=>$meta_data) {
        if($meta_data['key'] == 'wiki_page') {
          $_POST['meta'][$meta_key]['value'] = $wpsc_members_value;
				}
			}
		}
	}
}


/**
*  wiki page metabox module ends
*/

function exclude_pages_filter($excludes) {
	global $wpdb;
	// get the list of excluded pages and merge them with the current list
	$excludes = array_merge((array)$excludes, (array)$wpdb->get_col("SELECT DISTINCT `post_id` FROM `".$wpdb->postmeta."` WHERE `meta_key` IN ( 'wiki_page' ) AND `meta_value` IN ( '1' )"));
	return $excludes;
}

function table_of_contents($content) {
	/**
	* 	This creates the Table of Contents
	*/
	global $wpdb,$post;
	$wpsc_members_data = get_post_meta($post->ID,'wiki_page');
	if ($wpsc_members_data[0] != '1') {
		return $content;
	}
	preg_match_all("|<h2>(.*)</h2>|", $content, $h2s, PREG_PATTERN_ORDER);
	$content = preg_replace("|<h2>(.*)</h2>|", "<a name='$1'></a><h2>$1</h2>", $content);
	$content = preg_replace("|<h3>(.*)</h3>|", "<a name='$1'></a><h3>$1</h3>", $content);
	$h2s = $h2s[1];
	$content = str_replace("\n", "::newline::", $content);
	preg_match_all("|</h2>(.*)<h2>|U", $content, $h3s_contents, PREG_PATTERN_ORDER);
	
	/**
	* 	The following lines are really ugly for finding <h3> after the last </h2> please tidy it up if u know a better solution, and please let us know about it.
	*/
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
	$table = "<ol id='content_list'>";
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
	return "<div class='contents'><h3>Contents</h3>[<a class='hide' id='hide_show' onclick='toggle_hide_show()'>hide</a>]$table</div>".$content;
}

function wp_wiki_head() {
	/**
	* 	This adds in the CSS and Javascript for the wiki plugin
	*/
	echo "<link href='".get_option('siteurl')."/wp-content/plugins/wp-wiki/style.css' rel='stylesheet' type='text/css' />";
	echo "<script src='".get_option('siteurl')."/wp-content/plugins/wp-wiki/js.js' language='javascript'></script>";
	echo "<script src='".get_option('siteurl')."/wp-content/plugins/wp-wiki/jquery.js' language='javascript'></script>";
}


add_action('edit_form_advanced','wiki_metabox_module');
add_action('edit_page_form', 'wiki_metabox_module');

add_action('edit_post', 'wiki_metabox_module_submit');
add_action('publish_post', 'wiki_metabox_module_submit');
add_action('save_post', 'wiki_metabox_module_submit');
add_action('edit_page_form', 'wiki_metabox_module_submit');

add_action('pre_get_posts', 'wiki_substitute_in_revision_id');
add_filter('posts_request', 'wiki_view_sql_query');
add_filter('the_content', 'table_of_contents');
add_filter('the_content', 'wiki_post_revisions');

//add css
add_action('wp_head', 'wp_wiki_head');
?>
