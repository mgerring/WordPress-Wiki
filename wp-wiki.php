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
// this filter must be applied after Role Scoper's because we're changing the cap
*/

global $wp_version;

//include the admin page
include('wpw-admin-menu.php');
//include the class up here so it doesn't get re-declared- fixes issue #4 on GitHub. Thanks Nexiom!
include('lib/WPW_WikiParser.php');

//include component controllers
include('controllers/wiki_pages.php');
include('controllers/wiki_notifications.php');
include('controllers/wiki_feed.php');

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
$WikiNotifications = new WikiNotifications();
$WikiFeed = new WikiFeed();

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
	//Manage permissions for versions prior to 3.0
	add_filter('user_has_cap', array($WikiPostType, 'page_cap'), 100, 3);
endif;

//Front-end editor
add_action('wp', array($WikiPageController, 'set_query'));
add_action('template_redirect', array($WikiPageController, 'invoke_editor'));
add_action('init', array($WikiPageController, 'create_new_and_redirect'));
add_action('wp', array($WikiPageController, 'fake_page'));

//Ajax functions
add_action('wp_ajax_ajax_save',array($WikiPageController,'ajax_save'));
add_action('wp_ajax_nopriv_ajax_save',array($WikiPageController,'ajax_save'));

//if JavaScript isn't available...
if( !defined('DOING_AJAX') && isset($_POST['wpw_editor_content']) )
	add_action('init',array($WikiPageController,'no_js_save'));

//Notifications
add_action('save_post', array($WikiNotifications,'page_edit_notification'));
add_action('cron_email_hook', array($WikiNotifications,'cron_email'));

//Feed
add_action('init', array($WikiFeed, 'add_feed'), 11);

?>