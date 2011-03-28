<?php
/*
Plugin Name:WordPress Wiki
Plugin URI: http://wordpress.org/extend/plugins/wordpress-wiki/
Description: Add Wiki functionality to your wordpress site.
Version: 1.0.1
Author: Dan Milward/Matthew Gerring
Author URI: http://www.instinct.co.nz
*/

class WP_Wiki {
	function __construct() {
		$this->WP_Wiki();
	}
	
	function WP_Wiki() {
		global $wp_version;
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
		
		//include component controllers
		include(WPWIKI_FILE_PATH.'/model/wiki_post_type.php');
		include(WPWIKI_FILE_PATH.'/controllers/wiki_pages.php');
		include(WPWIKI_FILE_PATH.'/controllers/wiki_notifications.php');
		include(WPWIKI_FILE_PATH.'/controllers/wiki_feed.php');
		include(WPWIKI_FILE_PATH.'/controllers/wiki_admin.php');
		include(WPWIKI_FILE_PATH.'/controllers/wiki_dashboard_widget.php');
		include(WPWIKI_FILE_PATH.'/controllers/wiki_user_contrib_widget.php');
		include(WPWIKI_FILE_PATH.'/wiki_helpers.php');
		
		//include Wiki Parser class here so it doesn't get re-declared- fixes issue #4 on GitHub. Thanks Nexiom!
		include(WPWIKI_FILE_PATH.'/lib/wpw_wikiparser.php');
		
		
		
		//Enables Wiki Pages
		$WikiPostType = new WikiPostType();
		
		//Create classes for our components. This will be changed to allow filtering in a future release.
		$WikiPageController = new WikiPageController();
		$WikiNotifications = new WikiNotifications();
		$WikiFeed = new WikiFeed();
		$WikiAdmin = new WikiAdmin();
		$WikiDashboardWidget = new WikiDashboardWidget();
		
		//Version-specific actions and filters
		
		if ($wp_version >= 3.0):
			//Register the post type
			add_action('init', array($WikiPostType,'register') );
			
			//Set permissions
			add_action('init', array($WikiPostType,'set_permissions') );
			
			//Make Table of Contents on by default for Wiki post type
			add_action('publish_wiki',array($WikiPageController,'set_toc'), 12);
			
			//Make Table of Contents on by default for pages marked as Wikis
			add_action('publish_page',array($WikiPageController,'set_toc'));
		else:
			//Make Table of Contents on by default for pages marked as Wikis
			add_action('publish_page',array($WikiPageController,'set_toc'));
			//Manage permissions for versions prior to 3.0
			add_filter('user_has_cap', array($WikiPostType, 'page_cap'), 100, 3);
		endif;
		
		//Front-end editor
		add_action('wp', array($WikiPageController, 'set_query'));
		add_action('template_redirect', array($WikiPageController, 'invoke_editor'));
		add_action('init', array($WikiPageController, 'create_new_and_redirect'));
		add_action('wp', array($WikiPageController, 'fake_page'));
		add_action('_wp_put_post_revision', array($WikiPageController,'anon_meta_save_as_revision'), 10);
		
		//Ajax functions
		add_action('wp_ajax_ajax_save',array($WikiPageController,'ajax_save'));
		add_action('wp_ajax_nopriv_ajax_save',array($WikiPageController,'ajax_save'));
		
		//if JavaScript isn't available...
		if( !defined('DOING_AJAX') && isset($_POST['wpw_editor_content']) )
			add_action('init',array($WikiPageController,'no_js_save'));
		
		//Notifications
		add_action('save_post', array($WikiNotifications,'page_edit_notification'));
		add_action('cron_email_hook', array($WikiNotifications,'cron_email'));
		add_filter('cron_schedules', array($WikiNotifications,'more_reccurences'));
		
		//Feed
		add_action('init', array($WikiFeed, 'add_feed'), 11);
		
		//Admin pages
		add_action('admin_menu', array($WikiAdmin,'register_options_page'));
		add_action('publish_wiki', array($WikiAdmin,'replace_current_with_pending'), 11);
		add_action('publish_page', array($WikiAdmin,'replace_current_with_pending'), 11);
		add_action('admin_menu', array($WikiAdmin,'add_custom_box'));
		
		//Widgets
		add_action('widgets_init', create_function('', 'return register_widget("WikiUserContribWidget");'));
		add_action('wp_dashboard_setup', array($WikiDashboardWidget, 'dashboard_widget_hook') );
	}
}
$WP_Wiki = new WP_Wiki();

//That's all she wrote!
?>