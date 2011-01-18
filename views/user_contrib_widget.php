<?php 
	echo $before_widget;
	if ( $title )
		echo $before_title . $title . $after_title;
?>
<ul>
<?php foreach ($posts as $post):
        if ($this->WikiHelper->is_wiki('check_no_post',$post->ID)) {
            printf("<li><a href = '%s'>%s</a></li>", get_permalink($post->ID) ,$post->post_title);
            $count++;
            if ($count == $nos) {
                break;
            }
        }
    endforeach;

    if ($count == 0):
?>
	<li> <?php _e("You have made no contributions"); ?> </li>
<?php endif; ?>
</ul>
<?php echo $after_widget; ?>