<?php
class WikiUserContribWidget extends WP_Widget {

	function WikiUserContribWidget() {
		$widget_ops = array(
			'classname' => 'widget_my_contributions', 
			'description' => __( "My Contributions widget for WordPress Wiki Plugin")
		);
		$this->WP_Widget('wiki_user_contrib', 'Wiki User Contributions', $widget_ops);
		$this->WikiHelper = new WikiHelpers();
	}
	
	function widget($args, $instance) {
		global $userdata;
		get_currentuserinfo();
		if ($userdata->ID == 0 || trim($userdata->ID == "")) return;
	    global $wpdb;
	    extract($args);
	    $title = apply_filters('widget_title', $instance['title']);
		get_currentuserinfo();
	    $nos = ($instance['nos'] <= 0) ? 10: $instance['nos'];
	    $count = 0;
	    $posts = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE post_author = %d and post_status = 'publish' and post_type in ('post','page','wiki')",  $userdata->ID));
		include(WPWIKI_FILE_PATH.'/views/user_contrib_widget.php');
	}
	
	function update($new_instance, $old_instance) {
		$instance = $old_instance;		
		$instance['title'] = strip_tags(stripslashes($new_instance["title"]));
		$instance['nos'] = strip_tags(stripslashes($new_instance["nos"]));
		return $instance;
	}
	
	function form($instance) {
        $title = esc_attr($instance['title']);
        $nos = ($instance['nos'] <= 0) ? 10: esc_attr($instance['nos']);
		include(WPWIKI_FILE_PATH.'/views/user_contrib_widget_form.php');
    }
}
?>