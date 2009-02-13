<?php
/*
Plugin Name:WordPress Wiki
Plugin URI: http://wordpress.org/extend/plugins/wordpress-wiki/
Description: Add Wiki functionality to your wordpress site.
Version: 0.5
Author: Instinct Entertainment
Author URI: http://www.instinct.co.nz
/* Major version for "major" releases */


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

/**
*  The following roles and capabilities code has been removed because it does not work. If you can help with this, please do.
*/

global $wp_roles;

if ( ! isset($wp_roles) )
	$wp_roles = new WP_Roles();

if ( ! get_role('wiki_editor')){
	$role_capabilities = array('read'=>true
//	,'edit_posts'=>true
//	,'edit_others_posts'=>true
//	,'edit_published_posts'=>true
//	,'delete_posts'=>true
//	,'delete_published_posts'=>true
//	,'publish_posts'=>true
	,'publish_pages'=>true
	,'delete_pages'=>true
	,'edit_pages'=>true
	,'edit_others_pages'=>true
	,'edit_published_pages'=>true
	,'delete_published_pages'=>true
	,'edit_wiki'=>true);
    $wp_roles->add_role('wiki_editor', 'Wiki Editor',$role_capabilities);
}


$role = get_role('administrator');
$role->add_cap('edit_wiki');

function wiki_post_revisions($content='') {
	global $post, $current_user, $role;
	if ( !$post = get_post( $post->ID ) )
		return $content;
	$initial_post_id = $post->ID;
	if($post->post_type == 'revision' && ($post->post_parent > 0)) {
		if(!$post = get_post( $post->post_parent ))
			return $content;
	}
	
	$defaults = array( 'parent' => false, 'right' => false, 'left' => false, 'format' => 'list', 'type' => 'all' );
	extract( wp_parse_args( $args, $defaults ), EXTR_SKIP );
	$type = 'revision';
	switch ( $type ) {
	case 'autosave' :
		if ( !$autosave = wp_get_post_autosave( $post->ID ) )
			return $content;
		$revisions = array( $autosave );
		break;
	case 'revision' : // just revisions - remove autosave later
	case 'all' :
	default :
		if ( !$revisions = wp_get_post_revisions( $post->ID ) )
			return $content;
		break;
	}

		//echo("<pre>".print_r($revisions,true)."</pre>");
	$titlef = _c( '%1$s by %2$s|post revision 1:datetime, 2:name' );

	if ( $parent )
		array_unshift( $revisions, $post );

	$rows = '';
	$class = false;
	$can_edit_post = current_user_can( 'edit_post', $post->ID );
	//Track the first iteration as this is the current version auther who is different from the original
	$k=0;
	foreach ( $revisions as $revision ) {
		$is_selected = '';
		if ( !current_user_can( 'read_post', $revision->ID ) )
			continue;
		if ( 'revision' === $type && wp_is_post_autosave( $revision ) )
			continue;
		if($initial_post_id == $revision->ID ) {
			$is_selected = "class='selected-revision'";
		}
		$date = wiki_post_revision_title( $revision );
		$name = get_author_name( $revision->post_author );
		$title = sprintf( $titlef, $date, $name );
		$rows .= "\t<li $is_selected >$title</li>\n";
		if($k==0)
			// Current author
			$post_author=$name;
		$k++;
	}
	$wpsc_members_data = get_post_meta($post->ID,'wiki_page');
	if (current_user_can('edit_wiki') && (is_array($wpsc_members_data) && ($wpsc_members_data[0] == 1)) && current_user_can('edit_pages')) {
            $link = get_permalink($post_id);
            $output .= "<h4>". 'Post Revisions'."</h4>";
            $output .= "<ul class='post-revisions'>\n";
	    $output .= "\t<li $is_selected ><a href='".$link."'>Current revision</a> by ".$post_author." - <a href='".get_edit_post_link( $post->ID )."'>Edit this page</a></li>\n";
            $output .= $rows;
            $output .= "</ul>";
        }
	return $content.$output;
}

function wiki_post_revision_title( $revision, $link = true ) {
	if ( !$revision = get_post( $revision ) )
		return $revision;

	if ( !in_array( $revision->post_type, array( 'post', 'page', 'revision' ) ) )
		return false;

	$datef = _c( 'j F, Y @ G:i|revision date format');
	$autosavef = __( '%s [Autosave]' );
	$currentf	= __( '%s [Current Revision]' );

	$date = date_i18n( $datef, strtotime( $revision->post_modified_gmt . ' +0000' ) );
	if ( $link ) {
		$link = get_permalink($revision->post_parent)."?revision=".$revision->ID;
		$date = "<a href='$link'>$date</a>";
	}

	if ( !wp_is_post_revision( $revision ) )
		$date = sprintf( $currentf, $date );
	elseif ( wp_is_post_autosave( $revision ) )
		$date = sprintf( $autosavef, $date );

	return $date;
}


function wiki_substitute_in_revision_id($query) {
	/**
	* 	This function substitutes the revision ID for the post ID
	*  we need to set $query->is_single and $query->is_page to false, otherwise it cannot select the revisions
	*/
	if(((int)$_GET['revision'] > 0) && ($query->is_single == true)) {
		$query->query_vars['page_id'] = $_GET['revision'];
		$query->query_vars['pagename'] = null;
		$query->query_vars['post_type'] = 'revision';
		$query->is_single = false;
	} else if(((int)$_GET['revision'] > 0) && ($query->is_page == true)) {
		$query->query_vars['page_id'] = $_GET['revision'];
		$query->query_vars['pagename'] = null;
		$query->query_vars['post_type'] = 'revision';
		$query->is_single = false;
		$query->is_page = false;
// 		echo("<pre>".print_r($query,true)."</pre>");
	}
}

function wiki_view_sql_query($query) {
	/**
	* 	This function makes wordpress treat a revision as a single post
	*/
	global $wp_query;
	if((int)$_GET['revision'] > 0 ) {
		$wp_query->is_single= true;
// 		echo("<pre>".print_r($wp_query,true)."</pre>");
	}
	return $query;
}



/**
*  wiki page metabox section starts
*/
function wiki_metabox_module() {
		/**
		*  this function creates the HTML for the wiki page metabox module
		*/
  	global $wpdb, $post_meta_cache;
  	
  	if(is_numeric($_GET['post'])) {
    	$post_ID = (int)$_GET['post'];
    	$wpsc_members_data = get_post_meta($post_ID,'wiki_page');
    	if(is_array($wpsc_members_data) && ($wpsc_members_data[0] == 1)) {
    	 	$checked_status = "checked='checked'";

            $wiki_toc_data = get_post_meta($post_ID,'wiki_page_toc');
            if(is_array($wiki_toc_data) && ($wiki_toc_data[0] == 1)) {
        	 	$wiki_toc_status = "checked='checked'";
            } else {
                $wiki_toc_status = "";
            }
    	} else {
    	  	$checked_status = "";
            $wiki_toc_status = "disabled";
		}
	} else {
    	$checked_status = "";
        $wiki_toc_status = "disabled";
	}
?>
		<div id="postvisibility" class="postbox closed">
				<h3> <?php _e('Wordpress Wiki', 'wiki_page')?> </h3>
		<div class="inside">
		<div id="postvisibility">
<?php
	if (IS_WP25)
		echo "<label class='selectit' for='wiki_page'>
				<input id='wiki_page_check' type='hidden' value='1' name='wiki_page_check' />
				<input id='wiki_page' type='checkbox' $checked_status value='1' name='wiki_page' onchange = 'check_toc();' />
				This page/post is a wiki friendly page and may be edited by authors and contributors.
				</label><br />
                <lable class = 'selectit' for = 'wiki_toc'>
				<input id='wiki_toc' type='checkbox' $wiki_toc_status value='1' name='wiki_toc' />
                Enable Table of Contents
                </label>";
?>
		</div></div></div>
<?php
}
function wiki_metabox_module_submit($post_ID) {
		/**
		*  this function saves the HTML for the wiki page metabox module
		*/
  global $wpdb;
  if(is_numeric($post_ID) && ($_POST['wiki_page_check'] == 1)) {
    if(isset($_POST['wiki_page']) && ($_POST['wiki_page'] == 1)) {
      $wpsc_members_value = 1;
		} else {
      $wpsc_members_value = 0;
		}
    
    if(isset($_POST['wiki_toc']) && ($_POST['wiki_toc'] == 1)) {
        $wiki_toc_value = 1;
    } else {
        $wiki_toc_value = 0;
    }

    $wpsc_check_members_data = $wpdb->get_var("SELECT `meta_id` FROM `".$wpdb->postmeta."` WHERE `post_id` IN('".$post_ID."') AND `meta_key` IN ('wiki_page') LIMIT 1");
    if(is_numeric($wpsc_check_members_data) && ($wpsc_check_members_data > 0)) {
      update_post_meta($post_ID, 'wiki_page', $wpsc_members_value);
		} else {
      add_post_meta($post_ID, 'wiki_page', $wpsc_members_value);		}
    
    $wpsc_check_toc_data = $wpdb->get_var("SELECT `meta_id` FROM `".$wpdb->postmeta."` WHERE `post_id` IN('".$post_ID."') AND `meta_key` IN ('wiki_page_toc') LIMIT 1");
    if(is_numeric($wpsc_check_toc_data) && ($wpsc_check_toc_data > 0)) {
      update_post_meta($post_ID, 'wiki_page_toc', $wiki_toc_value);
		} else {
      add_post_meta($post_ID, 'wiki_page_toc', $wiki_toc_value);		}

        // need to change the custom fields value too, else it tries to reset what we just did.
        if(is_array($_POST['meta'])) {
            foreach($_POST['meta'] as $meta_key=>$meta_data) {
                if($meta_data['key'] == 'wiki_page') {
                  $_POST['meta'][$meta_key]['value'] = $wpsc_members_value;
                }
                if($meta_data['key'] == 'wiki_page_toc') {
                  $_POST['meta'][$meta_key]['value'] = $wiki_toc_value;
                }
            }
        }
    }
}


/**
*  wiki page metabox module ends
*/

function wiki_exclude_pages_filter($excludes) {
	global $wpdb;
	// get the list of excluded pages and merge them with the current list
	$excludes = array_merge((array)$excludes, (array)$wpdb->get_col("SELECT DISTINCT `post_id` FROM `".$wpdb->postmeta."` WHERE `meta_key` IN ( 'wiki_page' ) AND `meta_value` IN ( '1' )"));
	return $excludes;
}

function table_of_contents($content) {
	/**
	* 	This creates the Table of Contents
	*/
	global $wpdb,$post;
	$wpsc_members_data = get_post_meta($post->ID,'wiki_page');
	if ($wpsc_members_data[0] != '1') {
		return $content;
	}

    // Check whether table of contents is set or not
	$wiki_toc_data = get_post_meta($post->ID,'wiki_page_toc');
	if ($wiki_toc_data[0] != '1') {
		return $content;
	}

	preg_match_all("|<h2>(.*)</h2>|", $content, $h2s, PREG_PATTERN_ORDER);
	$content = preg_replace("|<h2>(.*)</h2>|", "<a name='$1'></a><h2>$1</h2>", $content);
	$content = preg_replace("|<h3>(.*)</h3>|", "<a name='$1'></a><h3>$1</h3>", $content);
	$h2s = $h2s[1];
	$content = str_replace("\n", "::newline::", $content);
	preg_match_all("|</h2>(.*)<h2>|U", $content, $h3s_contents, PREG_PATTERN_ORDER);
	
	/**
	* 	The following lines are really ugly for finding <h3> after the last </h2> please tidy it up if u know a better solution, and please let us know about it.
	*/
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
	return "<div class='contents'><h3>Contents</h3>[<a class='show' onclick='toggle_hide_show(this)'>hide</a>]$table</div>".$content;
}

function wp_wiki_head() {
	/**
	* 	Include CSS
	*/

    echo "<link href='". PLUGIN_URL . "wordpress-wiki/style.css' rel='stylesheet' type='text/css' />";
}

/**
 * Enqueue Scripts
 */
function wiki_enqueue_scripts() {
   wp_enqueue_script("jquery");
   wp_enqueue_script('wordpress-wiki', PLUGIN_URL . "wordpress-wiki/wordpress-wiki.js");
}

//Feed Functions

/**
 * Add new feed to WordPress
 * @global <type> $wp_rewrite
 */
function wiki_add_feed(  ) {
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
    global $wpdb;

    $posts = $wpdb->get_results($wpdb->prepare("select * from $wpdb->posts where ID in (
                    select post_id from $wpdb->postmeta where
                    meta_key = 'wiki_page' and meta_value = 1)
                    and post_type in ('post','page') order by post_modified desc"));

    header('Content-type: text/xml; charset=' . get_option('blog_charset'), true);
    echo '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?'.'>';
?>
<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
>

<channel>
	<title><?php print(__('Recently modifiyed wiki pages for : ')); bloginfo_rss('name'); ?></title>
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
 * function to output the contents of our Dashboard Widget
 */
function wiki_dashboard_widget_function() {
    global $wpdb;

	// Display whatever it is you want to show
    $posts = $wpdb->get_results($wpdb->prepare("select * from $wpdb->posts where ID in (
                    select post_id from $wpdb->postmeta where
                    meta_key = 'wiki_page' and meta_value = 1)
                    and post_type in ('post','page') order by post_modified desc limit 5"));
?>
    <div class="rss-widget">
    <ul>
<?php
    if (count($posts) > 0) {
        foreach ($posts as $post) {
?>
        <li><a href = "<?php echo get_permalink($post->ID)?>"><?php echo $post->post_title ?></a></li>
<?php
        }
    } else {
?>
        <li><?php _e("No contributions yet.") ?></li>
<?php
    }
?>
    </ul>
    </div>
<?php
}

/**
 * function to hook
 */
function wiki_dashboard_widget() {
	wp_add_dashboard_widget('wiki_dashboard_widget', 'Recent contributions to Wiki', 'wiki_dashboard_widget_function');
}

/**
 * Fetch the posts edited by the current user
 * @global <type> $userdata
 * @global <type> $wpdb
 * @param <type> $nos
 */
function wiki_get_user_posts($nos) {
	global $userdata;
    global $wpdb;

	get_currentuserinfo();
    $nos = ($nos <= 0) ? 10: $nos;
    $count = 0;

    $posts = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE post_author = %d and post_status = 'publish' and post_type in ('post','page')",  $userdata->ID));
?>
    <ul>
<?php
    foreach ($posts as $post) {
        if (get_post_meta($post->ID, "wiki_page", "true") == "1") {
            printf("<li><a href = '%s'>%s</a></li>", get_permalink($post->ID) ,$post->post_title);
            $count++;
            if ($count == $nos) {
                break;
            }
        }
    }

    if ($count == 0) {
?>
        <li> <?php _e("You have made no contributions"); ?> </li>
<?php
    }
?>
    </ul>
<?php
}

/**
 * Widget Initialization
 * @return <type>
 */
function wiki_widget_myc_init() {

    if(!function_exists('register_sidebar_widget')) { return; }

    /**
     * Widget Calls this function
     * @param <type> $args
     */
    function wiki_widget_myc($args) {
        global $userdata;
        get_currentuserinfo();

        if ($userdata->ID == 0 || trim($userdata->ID == "")) {
            return;
        } else {
            extract($args);
            $widget_options = get_option('widget_myc');
            $widget_title = $widget_options['title'];
            $widget_nos = $widget_options['nos'];

            echo $before_widget . $before_title . $widget_title . $after_title;
            wiki_get_user_posts($widget_nos);
            echo $after_widget;
        }
    }

    /**
     * Widget Control function
     */
    function wiki_widget_myc_control() {
        $options = $newoptions = get_option('widget_myc');
        if ( $_POST["myc-submit"] ) {
                $newoptions['title'] = strip_tags(stripslashes($_POST["myc-title"]));
                $newoptions['nos'] = strip_tags(stripslashes($_POST["myc-nos"]));
        }
        if ( $options != $newoptions ) {
                $options = $newoptions;
                update_option('widget_myc', $options);
        }
        $title = attribute_escape($options['title']);
        $nos = absint(attribute_escape($options['nos']));
        $nos = ($nos <= 0) ? 10: $nos;
    ?>
        <p>
        <label for="myc-title"><?php _e('Title:'); ?><br />
            <input style="width: 250px;" id="myc-title" name="myc-title" type="text" value="<?php echo $title; ?>" />
        </label>
        </p>
        <p>
        <label for="myc-nos"><?php _e('Number of posts to show:'); ?>
            <input name="myc-nos" id="myc-nos" type="text" size = "3" maxlength="3" value="<?php echo $nos; ?>" />
        </label>
        </p>
        <input type="hidden" id="myc-submit" name="myc-submit" value="1" />
    <?php
    }

	$widget_ops = array('classname' => 'widget_my_contributions', 'description' => __( "My Contributions widget for WordPress Wiki Plugin") );
    wp_register_sidebar_widget('my_contributions', __('My Contributions'), 'wiki_widget_myc', $widget_ops);
    wp_register_widget_control('my_contributions', __('My Contributions'),'wiki_widget_myc_control', 300, 100);
}

/**
 * Build links from shortcodes
 * @param <type> $content
 * @return <type> modifiyed content
 */
function wiki_build_links($content) {
    global $post;

    if (get_post_meta($post->ID, "wiki_page", "true") == "1") {
        // If it is a wiki post or page, then parse the content and build links
        $pattern = '/(\[\[([^\]]*)\]\])/i';
        return preg_replace_callback($pattern, "wiki_callback_func", $content);
    } else {
        //If it is not a wiki post or page then return the content.
        return $content;
    }
}

/**
 * Call back function for regex
 * @param <type> $m
 * @return <type> link
 */
function wiki_callback_func($m) {
    global $post;

    $splited = explode("|", $m[2]);
    if (count($splited) == 2) {
        $link_text = trim($splited[1]);
        $link_slug = trim($splited[0]);
    } else {
        $link_slug = $link_text = $m[2];
    }

    $link = get_permalink_by_title($link_slug);
    if (!$link) {
        // If there is no post with that title

        if ($post->post_type == "page") {
            $link = get_bloginfo("wpurl") . "/wp-admin/page-new.php" ;
        } else {
            $link = get_bloginfo("wpurl") . "/wp-admin/post-new.php" ;
        }
    }
    return "<a href = '" . $link . "' >" . $link_text . "</a>";
}

/**
 * Get Permalink by post title
 * @global <type> $wpdb
 * @param <type> $page_title
 * @return <type> permalink
 */
function get_permalink_by_title($page_title) {
      global $wpdb;
      $post = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type in ('post','page')", $page_title ));
      if ( $post )
          return get_permalink($post);

      return NULL;
}

/**
 * Template tag which can be added in the 404 page
 */
function wiki_404() {
    $not_found = str_replace("/", "", $_SERVER['REQUEST_URI']);
    echo "<p>" . __("Sorry, the page with title ") . $not_found . __(" is not created yet. Click") . '<a href="' . get_bloginfo('wpurl') . '/wp-admin/post-new.php">' . __("here") . '</a>' . __(" to create a new page with that title.") . "</p";
}

add_action('edit_form_advanced','wiki_metabox_module');
add_action('edit_page_form', 'wiki_metabox_module');

add_action('edit_post', 'wiki_metabox_module_submit');
add_action('publish_post', 'wiki_metabox_module_submit');
add_action('save_post', 'wiki_metabox_module_submit');
add_action('edit_page_form', 'wiki_metabox_module_submit');

add_action('pre_get_posts', 'wiki_substitute_in_revision_id');
add_filter('posts_request', 'wiki_view_sql_query');
add_filter('the_content', 'table_of_contents', 9);
add_filter('the_content', 'wiki_post_revisions', 11);

//add css
add_action('wp_head', 'wp_wiki_head');
add_action('init', 'wiki_enqueue_scripts', 9);

// Feeds
add_action('init', 'wiki_add_feed', 11);

// Hoook into the 'wp_dashboard_setup' action to register our other functions
add_action('wp_dashboard_setup', 'wiki_dashboard_widget' );

add_action('plugins_loaded', 'wiki_widget_myc_init');
add_filter("the_content", "wiki_build_links", 999);
?>