<?php
add_action('admin_menu', 'wpw_register_options_page');
//add_action('admin_init', 'erm_promo_options_page');

function wpw_check_option($option, $index, $val) {
	if (isset($option[$index]) && $option[$index] == $val)
		echo 'checked="checked"';
}

function wpw_check_meta($post, $index, $val) {
	if (get_post_meta($post, $index, true) == $val)
		echo 'checked="checked"';
}

function wpw_register_options_page() {
	
	$page = add_options_page( 
		'Wiki Options', 	//Options page title
		'Wiki Options', 	//"
		'switch_themes', 	//Permissions
		'wpw-options', 		//Slug
		'wpw_options_page' 	//Callback
	);
	//add_action('admin_print_scripts-'.$page,'scc_post_order_scripts'); //In case it needs scripts
	//add_action('admin_print_styles-'.$page,'scc_post_order_styles'); //In case it needs styles
	register_setting('wpw_options_group','wpw_options'); //We're only going to register one option and store serialized data in it
}

function wpw_upgrade_check() {
	if ( get_option( 'numberOfRevisions' ) != false ) {
		return true;
	} else {
		$wiki = get_role('wiki_editor');
		if ( $wiki->has_cap('edit_posts') ) {
			return true;	
		} else {
			return false;
		}
	}
}

function wpw_options_page() {
	//TODO: This options page needs to provide a means to upgrade old WP-Wiki installs.
	//	1. For installs on 3.0, upgrade wiki pages to custom post types
	//	2. For all installs, upgrade options. They are:
	//		wiki_email_admins
	//		wiki_show_toc_onfrontpage
	//		wiki_cron_email
	global $wp_version;
	$wpw_options = get_option('wpw_options');
?>	

<div class="wrap">
	<div id="icon-options-general" class="icon32"></div>
	<h2><?php _e('WordPress Wiki Options'); ?></h2>
	<form action="options.php" method="post">
		
	<?php settings_fields('wpw_options_group'); ?>
		<table class="form-table">
	
	<?php 
		//If we need upgrading...
		
		if( wpw_upgrade_check() & $wpw_options['wpw_upgrade'] != 'do_upgrade') {
			echo '<p>'.__('An older version of WordPress Wiki was detected. Before using WordPress Wiki, you\'ll need to upgrade your existing installation.').'</p>';
			if ($wp_version >= 3.0) {
				echo '<p>'.__('Your old Wiki pages will be upgraded to custom post types and placed in the trash.').'</p>';
			}
			echo '<p><strong>'.__('WARNING: YOU CANNOT UNDO THIS ACTION. BACK UP YOUR DATA BEFORE UPGRADING!').'</strong></p>';
			echo '<input type="hidden" name="wpw_options[wpw_upgrade]" value="do_upgrade" />';
			echo '<input type="submit" value="Upgrade!" />';
		}
		
		//OK, now we're upgrading...
		
		elseif ( $wpw_options['wpw_upgrade'] == 'do_upgrade')  {
			wpw_upgrade();
		} else {
		
		//If not...
	?>	
		
			<tr valign="top">
				<th scope="row">
				<?php _e('Number of revisions'); ?>
				<p><em><?php _e('Number of revisions to show on the \'View History\' page'); ?></em></p>
				</th>
				<td>
					<input type="text" name="wpw_options[number_of_revisions]" 
						value="<?php echo $wpw_options['number_of_revisions']; ?>" />
				</td>
			</tr>

			<tr valign="top">
				<th scope="row">
				<?php _e('Show Table of Contents on front page'); ?>
				<p><em><?php _e('Whether to show the Table of Contents (if it\'s enabled) on your site\'s index page if a Wiki page is displayed on it.'); ?></em></p>
				</th>
				<td><input type="checkbox" name="wpw_options[show_toc_on_front_page]" <?php wpw_check_option($wpw_options, 'show_toc_on_front_page', "1"); ?> value="1" /></td>
			</tr>
			
			<tr valign="top">
				<th scope="row">
				<?php _e('Email notification'); ?>
				<p><em><?php _e('Notify administrators when wiki pages are edited'); ?></em></p>
				</th>
				<td><input type="checkbox" name="wpw_options[email_admins]" <?php wpw_check_option($wpw_options, 'email_admins', "1"); ?> value="1" /></td>
			</tr>
			
			<tr valign="top">
				<th scope="row">
				<?php _e('Weekly email notification'); ?>
				<p><em><?php _e('Send weekly email to admins with most recent changes to Wikis'); ?></em></p>
				</th>
				<td><input type="checkbox" name="wpw_options[cron_email]" <?php wpw_check_option($wpw_options, 'cron_email', "1"); ?> value="1" /></td>
			</tr>
			
			<!--tr valign="top">
				<th scope="row">
					Submit revisions for review before publishing
					<p><em>Check this box if you want to review edits submitted
					to your wiki pages before making them live on the site.</em></p>
				</th>
				
				<td><input type="checkbox" name="wpw_options[revision_pending]" <?php wpw_check_option($wpw_options, "revision_pending", "true"); ?> value="true" /></td>
			</tr-->
			
		<input type="hidden" name="wpw_options[wpw_upgrade]" value="done_gone_and_upgraded" />

		<tr>
			<td><input class="button-primary" type="submit" value="Update Options" /></td>
		</tr>
<?php } //end upgrade conditional thingy ?>
		</table>

	</form>
<?php	
  	echo '</div>';
}

function wpw_upgrade_wiki_editor() {
	global $wp_roles;
	$wiki = get_role('wiki_editor');
	if ($wiki->has_cap('edit_posts')) {
		remove_role('wiki_editor');
		add_role( 'wiki_editor', 'Wiki Editor', array('read' => true) );
	}
	echo "Succesfully upgraded Wiki Editor role";
}

function wpw_upgrade() {
	//Alternate form in case we're upgrading.
	
	global $wp_version;
	
	$wpw_options = get_option('wpw_options');
	$test = get_posts('post_type=wiki');
	if ( $wp_version >= 3.0 && empty( $test ) ) {
		$old_wikis = get_pages('meta_key=_wiki_page&meta_value=1');
		foreach($old_wikis as $wiki) {
			$wiki->post_type = 'wiki';
			$wiki->ID = null;
			if (wp_insert_post($wiki) != false) {
				echo "<p>".__('Wiki page')." ".$wiki->post_title." ".__('successfully upgraded to Wiki post type.')."</p><br />";
				wp_delete_post($wiki->ID);
			}
		}
	}
	
	wpw_upgrade_wiki_editor();

	if( !isset($wpw_options['number_of_revisions']) && get_option('numberOfRevisions') != false ) {
		echo '<input type="hidden" name="wpw_options[number_of_revisions]" value="'.get_option('numberOfRevisions').'" />';
	} elseif (empty($wpw_options['number_of_revisions'])) {
		echo '<input type="hidden" name="wpw_options[number_of_revisions]" value="5" />';
	}
	
	delete_option('numberOfRevisions');

	if( !isset($wpw_options['email_admins']) && get_option('wiki_email_admins') != false ) {
		echo '<input type="hidden" name="wpw_options[email_admins]" value="'.get_option('wiki_email_admins').'" />';
	} elseif (empty($wpw_options['email_admins'])) {
		echo '<input type="hidden" name="wpw_options[email_admins]" value="0" />';
	}
	
	delete_option('wiki_email_admins');
	
	if( !isset($wpw_options['show_toc_onfrontpage']) && get_option('wiki_show_toc_onfrontpage') != false ) {
		echo '<input type="hidden" name="wpw_options[show_toc_onfrontpage]" value="'.get_option('wiki_show_toc_onfrontpage').'" />';
	} elseif (empty($wpw_options['show_toc_onfrontpage'])) {
		echo '<input type="hidden" name="wpw_options[show_toc_onfrontpage]" value="0" />';
	}
	
	delete_option('wiki_show_toc_onfrontpage');
	
	if( !isset($wpw_options['cron_email']) && get_option('wiki_cron_email') != false ) {
		echo '<input type="hidden" name="wpw_options[cron_email]" value="'.get_option('wiki_cron_email').'" />';
	} elseif (empty($wpw_options['cron_email'])) {
		echo '<input type="hidden" name="wpw_options[cron_email]" value="0" />';
	}
	
	delete_option('wiki_cron_email');
	
	echo '<input type="hidden" name="wpw_options[wpw_upgrade]" value="done_gone_and_upgraded" />';
	
	echo '
	<tr>
		<td><input class="button-primary" type="submit" value="'.__('Finish Upgrade').'" /></td>
	</tr>
	';
}

//On page update/edit

//add_action('post_submitbox_misc_actions','wpw_wiki_page_actions');
add_action('save_post','wpw_replace_current_with_pending');

function wpw_replace_current_with_pending($id) {
	//$revision = get_posts('include='.$id.'&post_status=pending');
	//var_dump($revision[0]);
	if(!isset($_POST['wpw_is_admin']))
		return;
	
	if(!isset($_POST['wpw_change_to_wiki']))
		unset($GLOBALS['wpw_prevent_recursion']);
		
	global $wp_version;
	
	if($wp_version < 3.0) {
		if(isset($_POST['wpw_is_wiki']) && $_POST['wpw_is_wiki'] == "true" )
			update_post_meta($id, '_wiki_page', 1);
		else
			delete_post_meta($id, '_wiki_page');
	}
	
	if(wiki_back_compat('check_no_post',$id)) {
		if(isset($_POST['wpw_toc']) && $_POST['wpw_toc'] == "true" )
			update_post_meta($id, '_wiki_page_toc', 1);
		else
			delete_post_meta($id, '_wiki_page_toc');
		
		if(isset($_POST['wpw_approve_revision']) && $_POST['wpw_approve_revision'] == "true" ) {
			$_POST['wpw_approve_revision'] = null; //Prevent recursion!
			$revision = get_post($id);
			//var_dump($revision);
			$n_post = array();
			$n_post['ID'] = $revision->post_parent;
			$n_post['post_content'] = $revision->post_content;
			wp_update_post($n_post);
		}
	}
	
	if ($wp_version >= 3.0 && isset($_POST['wpw_change_to_wiki']) && !isset($GLOBALS['wpw_prevent_recursion'])) {
		$GLOBALS['wpw_prevent_recursion'] = true;
		$id_we_are_changing = $_POST['wpw_change_wiki_id'];
		$update_post = get_post($id_we_are_changing, 'ARRAY_A');
		unset($update_post['ID']);
		unset($update_post['post_parent']);
		$update_post['post_type'] = 'wiki';
		$update_post['post_status'] = 'publish';
		$new = wp_insert_post($update_post);
		wp_delete_post($id_we_are_changing, true);
		wp_redirect( get_edit_post_link($new, 'go_to_it') );
	}

	//echo print_r($_POST, true).get_option('wiki_email_admins');
}


///BUH


/* Use the admin_menu action to define the custom boxes */
add_action('admin_menu', 'wpw_add_custom_box');

/* Use the save_post action to do something with the data entered */
//add_action('save_post', 'myplugin_save_postdata');



//Hook functions into AJAX here as well



/* Adds a custom section to the "advanced" Post and Page edit screens */
function wpw_add_custom_box() {
    //Wordpress 2.6 + -- this is the only one we need
    global $wp_version;
    if ($wp_version < 3.0) {
	    add_meta_box( 'wpw_meta_box', __( 'Wiki Options', 'wp-wiki' ), 
	                'wpw_meta_box_inner', 'page', 'side', 'high' );
	} else {
		add_meta_box( 'wpw_meta_box', __( 'Wiki Options', 'wp-wiki' ), 
	                'wpw_meta_box_inner', 'wiki', 'side', 'high' );

		add_meta_box( 'wpw_meta_box', __( 'Wiki Options', 'wp-wiki' ), 
	                'wpw_meta_box_inner_pages', 'page', 'side', 'high' );
	}
}
   
/* Prints the inner fields for the custom post/page section */
function wpw_meta_box_inner() {
	echo '<input type="hidden" name="wpw_is_admin" value="1" />';
	global $wp_version;
	if($wp_version < 3.0) { 
	?>
		<h5><?php _e('Wiki Page'); ?></h5>	
		<input type="checkbox" <?php wpw_check_meta($_GET['post'], '_wiki_page', 1); ?> name="wpw_is_wiki" value="true" />
		<label for="wpw_is_wiki"><?php _e('This is a Wiki page. Logged in users can edit its content.'); ?></label>

	<?php  
	}
	
	if(wiki_back_compat('check_no_post',@$_GET['post'])) {
	?>
		<h5><?php _e('Wiki Table of Contents'); ?></h5>	
		<input type="checkbox" <?php wpw_check_meta(@$_GET['post'], '_wiki_page_toc', 1); ?> name="wpw_toc" value="true" />
		<label for="wpw_toc"><?php _e('Show table of contents'); ?></label>
		
		<br />
		
		<?php if( wiki_back_compat('check_post_parent' , @$_GET['post']) ) { ?>
			<h5><?php _e('Approve Revision'); ?></h5>	
			<input type="checkbox" name="wpw_approve_revision" value="true" />
			<label for="wpw_approve_revision"><?php _e('Approve this revision'); ?></label>
		
		<?php }
	}
}

function wpw_meta_box_inner_pages() {
	echo '<input type="hidden" name="wpw_is_admin" value="1" />';
	global $wp_version;
?>
		<h5><?php _e('Wiki Page'); ?></h5>	
		<input type="checkbox" name="wpw_change_to_wiki" value="true" />
		<label for="wpw_change_to_wiki"><?php _e('This is a Wiki page. Logged in users can edit its content.'); ?></label>
		<input type="hidden" name="wpw_change_wiki_id" value="<?php echo $_GET['post']; ?>" />
<?php  

}

/* When the post is saved, saves our custom data */
function myplugin_save_postdata( $post_id ) {

	// verify this came from the our screen and with proper authorization,
	// because save_post can be triggered at other times
	
	if ( !wp_verify_nonce( $_POST['myplugin_noncename'], plugin_basename(__FILE__) )) {
	return $post_id;
	}
	
	// verify if this is an auto save routine. If it is our form has not been submitted, so we dont want
	// to do anything
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) 
	return $post_id;
	
	
	// Check permissions
	if ( 'page' == $_POST['post_type'] ) {
	if ( !current_user_can( 'edit_page', $post_id ) )
	  return $post_id;
	} else {
	if ( !current_user_can( 'edit_post', $post_id ) )
	  return $post_id;
	}
	
	// OK, we're authenticated: we need to find and save the data
	
	$mydata = substr($_POST['ge_slide_ids'],0,-1);
	
	$mydata = explode(',',$mydata);
	
	if (is_array($mydata)) {
		foreach ($mydata as $key=>$value) {
			if (is_null($value) || $value=="") { 
        		unset($mydata[$key]); 
      		}
      	}
	}
	
	$mydata = implode(',',$mydata);
	
	// Do something with $mydata 
	// probably using add_post_meta(), update_post_meta(), or 
	// a custom table (see Further Reading section below)

	update_post_meta($post_id, 'slide', $mydata);
   
	return $post_id;
}

//End meta box. TODO: Turn this into a plugin!!!
?>