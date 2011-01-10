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
			'add_new' => _x('Add New'),
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
			'description'=>_x('Wiki-enabled page. Users with permission can edit this page.'),
			'public'=>true,
			'capability_type'=>'wiki_page',
			'supports' => array('title','editor','author','thumbnail','excerpt','comments','revisions','custom-fields','page-attributes'),
			'hierarchical' => true,
			'rewrite' => array('slug' => 'wiki', 'with_front' => FALSE)
		);
	}
		
	function register() {
		//register_post_type('wiki', $this->post_type_options);
	}
	
	function set_permissions() {
		global $wp_roles;

		foreach ($wp_roles->get_names() as $role => $name) {
			$role_object = get_role($role);
			foreach ($this->permissions as $cap => $grant) {
				if ($cap == 'publish_wiki_pages' && $role == 'wiki_editor')
					continue;
				else
					$role_object->add_cap($cap);
			}
		}
	}

}
?>