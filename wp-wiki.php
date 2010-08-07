<?php
/*
Plugin Name:WordPress Wiki
Plugin URI: http://wordpress.org/extend/plugins/wordpress-wiki/
Description: Add Wiki functionality to your wordpress site.
Version: 0.9b
Author: Instinct Entertainment/Matthew Gerring
Author URI: http://www.instinct.co.nz
/* Major version for "major" releases */

add_filter('user_has_cap', 'wiki_page_cap', 100, 3);// this filter must be applied after Role Scoper's because we're changing the cap

//add css
add_action('wp_head', 'wp_wiki_head');
add_action('init', 'wiki_enqueue_scripts', 9);

// Feeds
add_action('init', 'wiki_add_feed', 11);

//Post Types
if(function_exists('register_post_type') && $wp_version >= 3.0)
	add_action('init','register_wiki_post_type');

// Hoook into the 'wp_dashboard_setup' action to register our other functions
add_action('wp_dashboard_setup', 'wiki_dashboard_widget' );

//hook to check whether a page has been edited
add_action('save_post', 'wiki_page_edit_notification');


//include the admin page
include('wpw-admin-menu.php');

/**
* Guess the wp-content and plugin urls/paths
*/
if ( !defined('WP_CONTENT_URL') )
    define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
if ( !defined('WP_CONTENT_DIR') )
    define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );

if (!defined('PLUGIN_URL'))
    define('PLUGIN_URL', WP_CONTENT_URL . '/plugins/');
if (!defined('PLUGIN_PATH'))
    define('PLUGIN_PATH', WP_CONTENT_DIR . '/plugins/');
if(get_option('numberOfRevisions') == NULL){
	add_option('numberOfRevisions', 5, '', 'yes' );
}
if(get_option('wiki_email_admins') == NULL){
	add_option('wiki_email_admins', 0, '', 'yes');
}
if(get_option('wiki_show_toc_onfrontpage') == NULL){
	add_option('wiki_show_toc_onfrontpage', 0, '', 'yes');
}
if(get_option('wiki_cron_last_email_date') == NULL){
	add_option('wiki_cron_last_email_date', date('Y-m-d G:i:s') , '', 'yes');
}
define('WPWIKI_FILE_PATH', dirname(__FILE__));
define('WPWIKI_DIR_NAME', basename(WPWIKI_FILE_PATH));

//This checks if we're working with a wiki page, rather than running two seperate checks for backwards compatibility

function wiki_back_compat($switch,$input = null) {
	global $wp_version, $post;
	if ($switch == 'front_end_check') {
		if ($wp_version < 3.0) {
			if (get_post_meta($post->ID,'_wiki_page',true) == 1)
				return true;
			else
				return false;
		} else {
			if ($post->post_type == 'wiki')
				return true;
			else
				return false;
		}
	} elseif ($switch == 'check_no_post') {
		if ($wp_version < 3.0) {
			if (get_post_meta($input,'_wiki_page',true) == 1)
				return true;
			else
				return false;
		} else {
			$post_to_check = get_post($input);
			if ($post_to_check->post_type == 'wiki')
				return true;
			else
				return false;
		}
	} elseif ($switch == 'check_post_parent') {
		
		$post = get_post($input);
		
		if ($post->post_parent != 0)
			return false;
		else
			return false;
		
	} else {
		return false;
	}
	return false;
}

function register_wiki_post_type() {

	$labels = array(
		'name' => _x('Wiki Pages', 'wiki general name'),
		'singular_name' => _x('Wiki Page', 'wiki singular name'),
		'add_new' => _x('Add New', 'Wiki Page'),
		'add_new_item' => __('Add New Wiki Page'),
		'edit_item' => __('Edit Wiki Page'),
		'new_item' => __('New Wiki Page'),
		'view_item' => __('View Wiki Page'),
		'search_items' => __('Search Wiki Pages'),
		'not_found' =>  __('No Wiki Pages found'),
		'not_found_in_trash' => __('No Wiki Pages found in Trash'), 
		'parent_item_colon' => ''
	  );
	
	register_post_type('wiki',array(
		'label'=> 'Wiki Page',
		'labels'=>$labels,
		'description'=>'Wiki-enabled page. Users with permission can edit this page.',
		'public'=>true,
		'capability_type'=>'page',
		'supports' => array('title','editor','author','thumbnail','excerpt','comments','revisions','custom-fields','page-attributes'),
		'hierarchical' => true
	));
}

global $wp_roles;

if ( ! isset($wp_roles) )
	$wp_roles = new WP_Roles();

if ( ! get_role('wiki_editor')){
	$role_capabilities = array(
		'read'=>true
	//	,'edit_posts'=>true
	//	,'edit_others_posts'=>true
	//	,'edit_published_posts'=>true
	//	,'delete_posts'=>true
	//	,'delete_published_posts'=>true
	//	,'publish_posts'=>true
		,'publish_pages'=>true
	//	,'delete_pages'=>true
		,'edit_pages'=>true
		,'edit_others_pages'=>true
		,'edit_published_pages'=>true
		,'delete_published_pages'=>true
		,'edit_wiki'=>true);
    $wp_roles->add_role('wiki_editor', 'Wiki Editor',$role_capabilities);
}

$role = get_role('wiki_editor');
$role->add_cap('edit_wiki');
$role->add_cap('edit_pages');
//$role->add_cap('edit_post');
$role->add_cap('edit_others_posts');
$role->add_cap('edit_published_posts');

function wiki_post_revisions() {
	global $post, $current_user, $role;
	if(wiki_back_compat('front_end_check')) {
	$wpw_options = get_option('wpw_options');
	$revisions = get_posts('post_status=any&post_type=revision&post_parent='.$post->ID.'&numberposts='.$wpw_options['number_of_revisions']);
	
	//Most recent revision
	$date = date(__('m/d/y g:i a'), mktime($post->post_modified));
	$latest_revision = sprintf(__('Latest revision (@ %1s by %2s)'), $post->post_modified, get_userdata($post->post_author)->display_name);
	$output = '<a href="'.get_permalink($post->ID).'">'.$latest_revision.'</a><br />';
	
	//If we have revisions...
	if($revisions) {
		//Loop through them!
		foreach ($revisions as $revision) {
			if(wp_get_post_autosave($post->ID)->ID != $revision->ID) {
				$author = get_userdata($revision->post_author);
				$date = date(__('m/d/y g:i a'), mktime($revision->post_modified));
				$revision_title = sprintf(__('Revision @ %1s by %2s'), $date, $author->display_name);
				$output.= '<a href="'.get_permalink($post->ID).'?revision='.$revision->ID.'">'.$revision_title.'</a><br />';
			}
		}
	}
	return $output;
	}
}


//Not sure what this is for...
/*
function wiki_exclude_pages_filter($excludes) {
	global $wpdb;
	// get the list of excluded pages and merge them with the current list
	$excludes = array_merge((array)$excludes, (array)$wpdb->get_col("SELECT DISTINCT `post_id` FROM `".$wpdb->postmeta."` WHERE `meta_key` IN ( 'wiki_page' ) AND `meta_value` IN ( '1' )"));
	return $excludes;
}
*/

function wpw_table_of_contents($content) {
	/**
	* 	This creates the Table of Contents
	*/
	global $wpdb,$post;
	$wpw_options = get_option('wpw_options');
	
	if (!wiki_back_compat('front_end_check')) {
		return $content;
	}

    // Check whether table of contents is set or not
	// second condition checks: are we on the front page and
	// is front page displaying set. - tony@irational.org
	if ( $wiki_toc_data != 1
		|| (is_front_page() && !$wpw_options['show_toc_onfrontpage']) ) {
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

function wp_wiki_head() {
	/**
	* 	Include CSS
	*/

    echo "<link href='". PLUGIN_URL ."/".WPWIKI_DIR_NAME."/style.css' rel='stylesheet' type='text/css' />";
}

/**
 * Enqueue Scripts
 */
function wiki_enqueue_scripts() {
   wp_enqueue_script("jquery");
   wp_enqueue_script('wordpress-wiki', PLUGIN_URL ."/".WPWIKI_DIR_NAME."/wordpress-wiki.js");
}

//Feed Functions

/**
 * Add new feed to WordPress
 * @global <type> $wp_rewrite
 */
function wiki_add_feed(  ) {
    if (function_exists('load_plugin_textdomain')) {
        $plugin_dir = basename(dirname(__FILE__));
        load_plugin_textdomain('wordpress_wiki', '', $plugin_dir);
    }

    global $wp_rewrite;
    add_feed('wiki', 'wiki_create_feed');
    add_action('generate_rewrite_rules', 'wiki_rewrite_rules');
    $wp_rewrite->flush_rules();
}

/**
 * Modify feed rewrite rules
 * @param <type> $wp_rewrite
 */
function wiki_rewrite_rules( $wp_rewrite ) {
  $new_rules = array(
    'feed/(.+)' => 'index.php?feed='.$wp_rewrite->preg_index(1)
  );
  $wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
}

/**
 * This function creates the actual feed
 */
function wiki_create_feed() {
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
	<title><?php print(__('Recently modifiyed wiki pages for : ')); bloginfo_rss('name'); ?></title>
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

/**
 * wiki_page_edit_notification 
 * @global <type> $wpdb
 * @param <type> $pageID
 * @return NULL
 */
function wiki_page_edit_notification($pageID) {
    global $wpdb;
    $wpw_options = get_option('wpw_options');
    if($wpw_options['email_admins'] == 1){
  
		$emails = getAllAdmins();
		$sql = "SELECT post_title, guid FROM ".$wpdb->prefix."posts WHERE ID=".$pageID;
		$subject = "Wiki Change";
		$results = $wpdb->get_results($sql);
	
		$pageTitle = $results[0]->post_title;
		$pagelink = $results[0]->guid;
		
		$message = sprintf(__("A Wiki Page has been modified on %s."),get_option('home'),$pageTitle);
		$message .= "\n\r";
		$message .= sprintf(__("The page title is %s"), $pageTitle); 
		$message .= "\n\r";
		$message .= __('To visit this page, ').'<a href='.$pagelink.'>'.__('click here').'</a>';
		//exit(print_r($emails, true));
		foreach($emails as $email){
			wp_mail($email, $subject, $message);
	    } 
    }
}
/**
 * getAllAdmins 
 * @global <type> $wpdb
 * @param <type> NULL
 * @return email addresses for all administrators
 */
function getAllAdmins(){
	global $wpdb;
	$sql = "SELECT ID from $wpdb->users";
	$IDS = $wpdb->get_col($sql);

	foreach($IDS as $id){
		$user_info = get_userdata($id);
		if($user_info->user_level == 10){
			$emails[] = $user_info->user_email;
		
		}
	}
	return $emails;
}

/**
 * Template tag which can be added in the 404 page
 */
function wiki_404() {
    $not_found = str_replace("/", "", $_SERVER['REQUEST_URI']);
    echo "<p>" . __("Sorry, the page with title ") . $not_found . __(" is not created yet. Click") . '<a href="' . get_bloginfo('wpurl') . '/wp-admin/post-new.php">' . __("here") . '</a>' . __(" to create a new page with that title.") . "</p";
}


/**
 * If page edit capabilities are checked for a wiki page, grant them if current user has edit_wiki cap.
 * @global <type> $wp_query
 * @param <array> $wp_blogcaps : current user's blog-wide capabilities
 * @param <array> $reqd_caps : primitive capabilities being tested / requested
 * @param <array> $args = array:
 * 				 $args[0] = original capability requirement passed to current_user_can (possibly a meta cap)
 * 				 $args[1] = user being tested
 * 				 $args[2] = object id (could be a postID, linkID, catID or something else)
 * @return <array> capabilities as array key
 */
function wiki_page_cap($wp_blogcaps, $reqd_caps, $args) {
	static $busy;
	if ( ! empty($busy) )	// don't process recursively
		return $wp_blogcaps;
	
	$busy = true;
	
	// Note: add edit_private_pages if you want the edit_wiki cap to satisfy that check also.
	if ( ! array_diff( $reqd_caps, array( 'edit_pages', 'edit_others_pages', 'edit_published_pages' ) ) ) {

		// determine page ID
		if ( ! empty($args[2]) )
			$page_id = $args[2];
		elseif ( ! empty($_GET['post']) )
			$page_id = $_GET['post'];
		elseif ( ! empty($_POST['ID']) )
			$page_id = $_POST['ID'];
		elseif ( ! empty($_POST['post_ID']) )
			$page_id = $_POST['post_ID'];
		elseif ( ! is_admin() ) {
			global $wp_query;
			if ( ! empty($wp_query->post->ID) ) {
				$page_id = $wp_query->post->ID;
			}
		}
		
		if ( ! empty($page_id) ) {
			global $current_user, $scoper;

			if ( ! empty($scoper) && function_exists('is_administrator_rs') && ! is_administrator_rs() && $scoper->cap_defs->is_member('edit_wiki') ) {
				// call Role Scoper has_cap filter directly because recursive calling of has_cap filter confuses WP
				$user_caps = $scoper->cap_interceptor->flt_user_has_cap( $current_user->allcaps, array('edit_wiki'), array('edit_wiki', $current_user->ID, $page_id ) );
			} else
				$user_caps = $current_user->allcaps;

			if ( ! empty( $user_caps['edit_wiki'] ) ) {
				// Static-buffer the metadata to avoid performance toll from multiple cap checks.
				static $wpsc_members_data;

				if ( ! isset($wpsc_members_data) )
					$wpsc_members_data = array();

				// If the page in question is a wiki page, give current user credit for all page edit caps.
				if ( is_array($wpsc_members_data) && wiki_back_compat('check_no_post',$page_id) ) {
					$wp_blogcaps = array_merge( $wp_blogcaps, array_fill_keys($reqd_caps, true) );
				}
			}
		}
	}

	$busy = false;
	return $wp_blogcaps;
}

function more_reccurences() {
    return array(
        'weekly' => array('interval' => 604800, 'display' => 'Once Weekly'),
        'fortnightly' => array('interval' => 1209600, 'display' => 'Once Fortnightly'),
    );
}

add_filter('cron_schedules', 'more_reccurences');

if (!wp_next_scheduled('cron_email_hook')) {
    wp_schedule_event( time(), 'weekly', 'cron_email_hook' );
}

add_action( 'cron_email_hook', 'cron_email' );

function cron_email() {
	$wpw_options = get_options('wpw_options');
    
    if ($wpw_options['cron_email'] == 1) {
        $last_email = $wpw_options['cron_last_email_date'];
        
		$emails = getAllAdmins();
		$sql = "SELECT post_title, guid FROM ".$wpdb->prefix."posts WHERE post_modifiyed > ".$last_email;
        
		$subject = "Wiki Change";
		$results = $wpdb->get_results($sql);
	
        $message = " The following Wiki Pages has been modified on '".get_option('home')."' \n\r ";
        if ($results) {
            foreach ($results as $result) {
                $pageTitle = $result->post_title;
                $pagelink = $result->guid;
                $message .= "Page title is ".$pageTitle.". \n\r To visit this page <a href='".$pagelink."'> click here</a>.\n\r\n\r";
                //exit(print_r($emails, true));
                foreach($emails as $email){
                    wp_mail($email, $subject, $message);
                }
            }
        }
        $wpw_options['cron_last_email_date'] = date('Y-m-d G:i:s');
        update_option('wpw_options', serialize($wpw_options));
    }
}

add_action('wp','wpw_set_query');

function wpw_set_query() {
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

add_action('get_header','wpw_invoke_editor');
add_action('wp_print_styles','wpw_print_styles');

function wpw_print_styles() {
	echo '<style type="text/css">
				#wpw_tab_nav {
					list-style-type:none;
					text-align:right;
					width:100%;
				}
				#wpw_tab_nav li {
					display:inline;
					margin-left:10px;
				}
				#wpw_tab_nav li a {
					text-decoration:none;
				}
				#wpw_tab_nav li.ui-tabs-selected {
					font-weight:bold;
					border-bottom:1px dotted #000;
				}
				.ui-tabs .ui-tabs-hide {
     				display: none;
				}
		</style>';
}


function wpw_invoke_editor() {
	global $post;
	if ( wiki_back_compat('front_end_check') ) {
		$wpw_options = get_option('wpw_options');
		if ( current_user_can('edit_posts') ) 	{
			add_filter('the_content','wpw_substitute_in_revision_content',11);
			add_filter('the_content','wpw_wiki_interface',12);
			add_action('wp_footer','wpw_inline_editor');
			wp_enqueue_script('jquery-ui-tabs');
		
			if (!isset($wpw_options['alt_syntax'])) {
				wp_enqueue_script('nicedit',plugin_dir_url(__FILE__).'nicedit/nicEdit.js','','',true);	
			}
		} else {
			add_filter('the_content','wpw_nope');
		}
	}
}
add_action('wp_ajax_wpw_ajax_save','wpw_ajax_save');

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
function wpw_substitute_in_revision_content($content) {
	global $post;
	if(isset($post->is_revision) && $post->is_revision == true) {
		$content = $post->revision_content;
		return $content;
	} else {
		return $content;
	}
}

function wpw_wiki_parser($content, $title) {
	global $post;
	include('lib/WPW_WikiParser.php');
	$wiki_parser = new WPW_WikiParser();
	$wiki_parser->reference_wiki = get_bloginfo('url').'/wiki/';
	$wiki_parser->suppress_linebreaks = true;	
	$content = $wiki_parser->parse($post->post_content, $title);
	$content = wpautop($content);	
	return $content;
}

function wpw_wiki_interface($content) {
	global $post;
	
	get_option('wpw_options');
	
	//Create the "edit" interface
	$textarea = '<textarea style="width:100%;height:200px;" id="area1">'.$post->post_content.'</textarea>';
	
	//Handle revisions
	(isset($post->revision_warning)) ? $warning = $post->revision_warning : $warning = false;
	
	//Massage the content
	//var_dump($post->post_content);
	$content = wpw_wiki_parser($content,$post->post_title);
	$content = wpw_table_of_contents($content);
	
	// table_of_contents($content)
	
	return '
		<div id="wpw_tabs">
		<ul id="wpw_tab_nav">
			<li id="wpw_read_link"><a href="#wpw_content">Read</a></li>
			<li><a href="#wpw_edit">Edit</a></li>
			<li><a href="#wpw_view_history">View History</a></li>
		</ul>
		'.$warning.'
		<div id="wpw_content">'.$content.'</div>
		
		<div id="wpw_edit">'.$textarea.'<button id="wpw_save">Save</button></div>
		
		<div id="wpw_view_history">'.wiki_post_revisions().'</div>
		</div>
	';
}

function wpw_inline_editor() {
	global $post;
	$wpw_options = get_option('wpw_options');
	$nicedit_icons_path = plugin_dir_url(__FILE__).'nicedit/nicEditorIcons.gif';
	$wpw_ajax_url = admin_url('admin-ajax.php');
	$get_content_to_save = '$("#area1").val()';
	$nicedit = false;
	
	$ajax_args = '	action : "wpw_ajax_save",
					wpw_editor_content : '.$get_content_to_save.',
					wpw_id : "'.$post->ID.'"';
	
	if ( isset($wpw_options['revision_pending']) && $wpw_options['revision_pending'] == "true" )
		$ajax_args .= ', wpw_revision_stack: "1"';
		
	
	echo '
	<script type="text/javascript">
		jQuery(document).ready(function($) {
			
			'.$nicedit.'
				
			$("#wpw_save").click(function() {
				data = {
					'.$ajax_args.'
				};
				$.post("'.$wpw_ajax_url.'", data, function(results) {
					$("#wpw_view_history").load(location.href+" #wpw_view_history>*", function() {
						$("#wpw_content").load(location.href+" #wpw_content>*", function() {
							alert(results);
						});
					});
				});
			});
			$("#wpw_tabs").tabs();
		});
	</script>
	';
}

function wpw_save_code($match) {
	return '<nowiki>'.htmlentities($match).'</nowiki>';
}

function wpw_ajax_save() {
	if (current_user_can('edit_posts')) {
	if ($_POST != null) {
		extract($_POST);
	}
	//First, save everything marked as code
	$regex = '/(?<=^code>|pre>|%%%).+?(?=<\/$1$>)/sm';
	$wpw_editor_content = preg_replace_callback($regex, 'wpw_save_code',  $wpw_editor_content);
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
		
		$n_post['post_content'] = apply_filters('wp_insert_post_data',$wpw_editor_content);
		/*
		if ($commit != 1) {
			$n_post['post_content'] .='[swrmeta dob="'.$dob.'" loc="'.$loc.'" state="'.$state.'" sum_content="'.htmlspecialchars($sum_content).'" lnk1="'.$lnk1.'" lnk2="'.$lnk2.'" lnk3="'.$lnk3.'"]';
		}
		*/
		$n_post['ID'] = $wpw_id;
	// Insert the post into the database
		if (wp_update_post( $n_post ))
			die('Post saved!');
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


//Code shamelessly stolen from here: http://www.blogseye.com/2010/05/creating-fake-wordpress-posts-on-the-fly/comment-page-1/#comment-253
function wpw_fake_page() {
	global $wp_query, $post;
  	if($wp_query->is_404 && isset($_GET['redlink']) && $_GET['redlink'] == 1 ) {
  		$new_title = strip_tags($_GET['title']);
  		if (current_user_can('edit_posts')) {
  			$new_page_nonce = wp_create_nonce('wpw_new_page_nonce');
  			$get_params = '?new_wiki_page=true&nonce='.$new_page_nonce.'&title='.$new_title;
  			$new_link = '<a href="'.get_bloginfo('url').'/wiki/new'.$get_params.'">Click here to create it.</a>';
  		} else {
  			$new_link = '<a href="'.wp_login_url(curPageURL()).'">Log in or register an account to create it.</a>';
  		}
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

add_action('init','wpw_create_new_and_redirect');

function wpw_create_new_and_redirect() {
	//echo 'workin?';
	if (isset($_GET['new_wiki_page']) && $_GET['new_wiki_page'] == 'true' && wp_verify_nonce($_GET['nonce'], 'wpw_new_page_nonce')) {
	global $wp_version;
	$new_wiki = array();
	$new_wiki['post_title'] = $_GET['title'];
	$new_wiki['post_status'] = 'publish';
	
	if ($wp_version >= 3.0) {
		$new_wiki['post_type'] = 'wiki';
	}
	
	$new_wiki_id = wp_insert_post($new_wiki);
	
	if($wp_version <= 3.0) {
		update_post_meta($new_wiki_id, '_wiki_page', 1);
	}
	header('Location: '.get_bloginfo('url').'?p='.$new_wiki_id);
	} else {
		//echo 'didnt work!!';
	} 
}

function wpw_show_me() {
	global $wp_query, $post;
	echo '<pre>Query';
	var_dump($wp_query);
	echo '</pre>';
	echo '<pre>Post';
	var_dump($post);
	echo '</pre>';
}
add_action('wp', 'wpw_fake_page');
//add_action('wp', 'wpw_show_me');

function curPageURL() {
 $pageURL = 'http';
 if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
 $pageURL .= "://";
 if ($_SERVER["SERVER_PORT"] != "80") {
  $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
 } else {
  $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
 }
 return $pageURL;
}
?>