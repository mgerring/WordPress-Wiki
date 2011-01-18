<?php
class WikiDashboardWidget {
	function WikiDashboardWidget() {
		$this->WikiHelper = new WikiHelpers();
	}
	
	function dashboard_widget_function() {
	    global $wpdb;
	    $posts = $wpdb->get_results($wpdb->prepare("select * from $wpdb->posts where ID in (
	                    select post_id from $wpdb->postmeta where
	                    meta_key = 'wiki_page' and meta_value = 1)
	                    or post_type in ('wiki') order by post_modified desc limit 5"));
		include(WPWIKI_FILE_PATH.'/views/dashboard_widget.php');
	}
	
	function dashboard_widget_hook() {
		wp_add_dashboard_widget(
			'wpw_dashboard_widget', 
			'Recent contributions to Wiki', 
			array($this,'dashboard_widget_function')
		);
	}
}
?>