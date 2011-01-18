<?php
class WikiNotifications {
	function __construct() {
		$this->WikiNotifications();
	}
	
	function WikiNotifications() {
		if (!wp_next_scheduled('cron_email_hook'))
	    	wp_schedule_event( time(), 'weekly', 'cron_email_hook' );
	}
	/**
	 * wiki_page_edit_notification 
	 * @global <type> $wpdb
	 * @param <type> $pageID
	 * @return NULL
	 */
	function page_edit_notification($pageID) {
	    global $wpdb;
	    $wpw_options = get_option('wpw_options');
	    if($wpw_options['email_admins'] == 1){
	  
			$emails = $this->getAllAdmins();
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
		
	function more_reccurences() {
	    return array(
	        'weekly' => array('interval' => 604800, 'display' => 'Once Weekly'),
	        'fortnightly' => array('interval' => 1209600, 'display' => 'Once Fortnightly'),
	    );
	}
	
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

}
?>