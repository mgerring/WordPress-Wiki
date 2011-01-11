<?php
/*
Plugin Name:WordPress Wiki
Plugin URI: http://wordpress.org/extend/plugins/wordpress-wiki/
Description: Add Wiki functionality to your wordpress site.
Version: 0.9RC1
Author: Instinct Entertainment/Matthew Gerring
Author URI: http://www.instinct.co.nz
/* Major version for "major" releases */

/*
add_filter('user_has_cap', 'wiki_page_cap', 100, 3);// this filter must be applied after Role Scoper's because we're changing the cap
*/

global $wp_version;


// Feeds
add_action('init', 'wiki_add_feed', 11);

//Post Types

	add_action('init','register_wiki_post_type');

// Hoook into the 'wp_dashboard_setup' action to register our other functions
//add_action('wp_dashboard_setup', 'wiki_dashboard_widget' );

//hook to check whether a page has been edited
add_action('save_post', 'wiki_page_edit_notification');


//include the admin page
include('wpw-admin-menu.php');
//include the class up here so it doesn't get re-declared- fixes issue #4 on GitHub. Thanks Nexiom!
include('lib/WPW_WikiParser.php');

include('controllers/wiki_pages.php');

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

define('WPWIKI_FILE_PATH', dirname(__FILE__));
define('WPWIKI_DIR_NAME', basename(WPWIKI_FILE_PATH));

$WikiHelper = new WikiHelper();
$WikiPageController = new WikiPageController();

//This checks if we're working with a wiki page, rather than running two seperate checks for backwards compatibility

//NEW!

//Version-specific actions and filters

if ($wp_version >= 3.0):
	//Include the Wiki custom post type
	include('model/wiki_post_type.php');
	$WikiPostType = new WikiPostType();
	
	//Register the post type
	add_action('init', array($WikiPostType,'register') );
	
	//Set permissions
	add_action('init', array($WikiPostType,'set_permissions') );
	
	//Make Table of Contents on by default for Wiki post type
	add_action('publish_wiki',array($WikiPageController,'wpw_set_toc'));
	
	//Make Table of Contents on by default for pages marked as Wikis
	add_action('publish_page',array($WikiPageController,'wpw_set_toc'));
else:
	//Make Table of Contents on by default for pages marked as Wikis
	add_action('publish_page',array($WikiPageController,'wpw_set_toc'));
endif;

//Front-end editor
add_action('wp',array($WikiPageController, 'set_query'));
add_action('get_header',array($WikiPageController, 'invoke_editor'));

//Ajax functions
add_action('wp_ajax_ajax_save',array($WikiPageController,'ajax_save');
add_action('wp_ajax_nopriv_ajax_save',array($WikiPageController,'ajax_save');


/*
function wpw_get_author($post) {
	$tmp = get_userdata($post->post_author);
	
	if ($tmp->ID > 0):
		return $tmp->display_name;
	else:
		$anon_meta = get_post_meta($post->ID, '_wpw_anon_meta', true);
		return 'anonymous ('.$anon_meta['ip'].', '.$anon_meta['hostname'].')';
	endif;
}

function wiki_post_revisions() {
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


function wpw_table_of_contents($content) {

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


function wp_wiki_head() {
	//Include CSS
    echo "<link href='". PLUGIN_URL ."/".WPWIKI_DIR_NAME."/style.css' rel='stylesheet' type='text/css' />";
}

	//Enqueue Scripts
function wiki_enqueue_scripts() {
   wp_enqueue_script("jquery");
   wp_enqueue_script('wordpress-wiki', PLUGIN_URL ."/".WPWIKI_DIR_NAME."/wordpress-wiki.js");
}
*/

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
	$wpw_options = get_option('wpw_options');
    
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
/*
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

if( !defined('DOING_AJAX') && isset($_POST['wpw_editor_content']) )
	add_action('init','wpw_no_js_save');

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
				.ui-tabs .ui-tabs-hide, .wpw-hide-it {
     				display: none!important;
				}
		</style>';
}


function wpw_invoke_editor() {
	global $post;
	if ( wiki_back_compat('front_end_check') ) {
		$wpw_options = get_option('wpw_options');
		//if ( current_user_can('edit_wiki') ) {
			remove_filter('the_content', 'wpautop');
			remove_filter('the_content', 'wptexturize');
			add_filter('the_content','wpw_substitute_in_revision_content',11);
			add_filter('the_content','wpw_wiki_interface',12);
			add_action('wp_footer','wpw_inline_editor');
			wp_enqueue_script('jquery-ui-tabs');
		
			if (!isset($wpw_options['alt_syntax']))
				wp_enqueue_script('nicedit',plugin_dir_url(__FILE__).'nicedit/nicEdit.js','','',true);	
				
		//} else {
		//	add_filter('the_content','wpw_nope');
		//}
	}
}
add_action('wp_ajax_wpw_ajax_save','wpw_ajax_save');
add_action('wp_ajax_nopriv_wpw_ajax_save','wpw_ajax_save');

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

function wpw_wiki_parser($content) {
	global $post;
	$wiki_parser = new WPW_WikiParser();
	$wiki_parser->reference_wiki = get_bloginfo('url').'/wiki/';
	$wiki_parser->suppress_linebreaks = true;	
	$content = $wiki_parser->parse($content, $post->post_title);
	$content = wpautop($content);	
	return $content;
	unset($wiki_parser);
}

function wpw_get_content($content, $class = null ){
	global $post;
	return '<div id="wpw_read_div" '.$class.'>'.wpw_table_of_contents( wptexturize( wpw_wiki_parser($content) ) ).'</div>';	
}

function wpw_get_edit($content, $class = null ){
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

function wpw_get_history($content, $class = null){
	return '<div id="wpw_view_history_div" '.$class.'>'.wiki_post_revisions().'</div>';
}

function wpw_get_section($content = null, $section, $class) {
	if ($content == null):
		global $post;
		$content = $post->post_content;
	endif;
	
	if (in_array($section, array('content','edit','history'))):
		$func = 'wpw_get_'.$section;
		return $func($content, $class);
	endif;
}

function wpw_wiki_interface($content) {
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
			
		$return .= wpw_get_section( $content, $wiki, $class );
	
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

function wpw_inline_editor() {
	global $post;
	$wpw_options = get_option('wpw_options');
	$nicedit_icons_path = plugin_dir_url(__FILE__).'nicedit/nicEditorIcons.gif';
	$wpw_ajax_url = admin_url('admin-ajax.php');
	$get_content_to_save = '$("#area1").val()';
	$nicedit = false;
	
	$ajax_args = '	action : "wpw_ajax_save",
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

add_filter('wp_insert_post_data','wpw_save_code', '99');

function wpw_save_code($data,$postarr = Array()) {	
	$regex = '/(?<=^code>|pre>|%%%).+?(?=<\/$1$>)/sm';
	$data['post_content'] = preg_replace_callback($regex, 'wpw_nowiki',  $data['post_content']);
	return $data;
}

function wpw_nowiki($match) {
	return '<nowiki>'.htmlentities($match).'</nowiki>';
}

add_action('publish_wiki','wpw_set_toc');

function wpw_set_toc($post_id) {
	if (wiki_back_compat('check_no_post',$post_id))
		update_post_meta($post_id,'_wiki_page_toc',1);
}

function wpw_save_post() {
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
		*//*
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
		*//*
	} else {
		//This is the error message that displays if a user has no credentials to edit pages.
		die(__('You don\'t have permission to do that.'));
	}
}

function wpw_ajax_save() {
	if (wpw_save_post())
		die('Post saved!');
}

function wpw_no_js_save() {
	if ( isset( $_POST['wpw_editor_content'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'wpw_edit_form' ) ):
		if ( wpw_save_post() ):
			$post->wpw_post_saved = true;
		endif;
	endif;
}
*/

//Code shamelessly stolen from here: http://www.blogseye.com/2010/05/creating-fake-wordpress-posts-on-the-fly/comment-page-1/#comment-253
function wpw_fake_page() {
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

add_action('init','wpw_create_new_and_redirect');

function wpw_create_new_and_redirect() {
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

function wpw_anon_meta($atts) {
	global $post;
	
	extract(shortcode_atts(array(
		'ip' => '',
		'hostname' => '',
	), $atts));

	$post->anon_ip = $ip;
	$post->anon_hostname = $hostname;
}

add_action('_wp_put_post_revision','wpw_anon_meta_save_as_revision', 10);

function wpw_anon_meta_save_as_revision($revision_id) {
	
	$old_meta = get_post_meta(wp_is_post_revision($revision_id), '_wpw_anon_meta', true);
	
	if(!empty($old_meta)) {
		add_metadata('post', $revision_id, '_wpw_anon_meta', $old_meta);
		delete_post_meta(wp_is_post_revision($revision_id), '_wpw_anon_meta', $old_meta);
	}

}

?>