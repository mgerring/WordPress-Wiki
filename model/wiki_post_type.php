<?php

class WikiPostType {
	
	var $labels;
	var $permissions;
	var $post_type_options;
	
	function __construct() {
		$this->WikiPostType();
	}
	
	function WikiPostType() {
		$this->labels = array(
			'name' => _x('Wiki Pages', 'wiki general name'),
			'singular_name' => _x('Wiki Page', 'wiki singular name'),
			'add_new' => __('Add New'),
			'add_new_item' => __('Add New Wiki Page'),
			'edit_item' => __('Edit Wiki Page'),
			'new_item' => __('New Wiki Page'),
			'view_item' => __('View Wiki Page'),
			'search_items' => __('Search Wiki Pages'),
			'not_found' =>  __('No Wiki Pages found'),
			'not_found_in_trash' => __('No Wiki Pages found in Trash'), 
			'parent_item_colon' => ''
		);
		
		$this->permissions = array(
			'edit_wiki'=>true,
			'edit_wiki_page'=>true,
			'edit_wiki_pages'=>true,
			'edit_others_wiki_pages'=>true,
			'publish_wiki_pages'=>true,
			'delete_wiki_page'=>true,
			'delete_others_wiki_pages'=>false
		);
		
		$this->post_type_options = array(
			'label'=> 'Wiki Page',
			'labels'=>$this->labels,
			'description'=>__('Wiki-enabled page. Users with permission can edit this page.'),
			'public'=>true,
			'capability_type'=>'wiki_page',
			'supports' => array('title','editor','author','thumbnail','excerpt','comments','revisions','custom-fields','page-attributes'),
			'hierarchical' => true,
			'rewrite' => array('slug' => 'wiki', 'with_front' => FALSE)
		);
	}
		
	function register() {
		register_post_type('wiki', $this->post_type_options);
	}
	
	function set_permissions() {
		$wp_roles = new WP_Roles();
		$all_roles = $wp_roles->get_names();
		
		foreach ($all_roles as $role => $name) {
			
			$role_object = get_role($role);
			foreach ($this->permissions as $cap => $grant) {
				if ($cap == 'publish_wiki_pages' && $role == 'wiki_editor')
					continue;
				else
					$role_object->add_cap($cap);
			}
		}
		
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
	function page_cap($wp_blogcaps, $reqd_caps, $args) {
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

}
?>