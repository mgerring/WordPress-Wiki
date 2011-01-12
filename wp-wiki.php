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
    define('PLUGIN_URL', WP_CONTENT_URL . '/plugins');
if (!defined('PLUGIN_PATH'))
    define('PLUGIN_PATH', WP_CONTENT_DIR . '/plugins');

define('WPWIKI_FILE_PATH', dirname(__FILE__));
define('WPWIKI_DIR_NAME', basename(WPWIKI_FILE_PATH));

include('wiki_helpers.php');

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
add_action('template_redirect',array($WikiPageController, 'invoke_editor'));

//Ajax functions
add_action('wp_ajax_ajax_save',array($WikiPageController,'ajax_save'));
add_action('wp_ajax_nopriv_ajax_save',array($WikiPageController,'ajax_save'));

//if JavaScript isn't available...
if( !defined('DOING_AJAX') && isset($_POST['wpw_editor_content']) )
	add_action('init',array($WikiPageController,'no_js_save'));


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