<div class="wpw-dashboard">
	<ul>
<?php if (count($posts) > 0):
    	foreach ($posts as $post):
			$name =	$this->WikiHelper->get_author($post);
?>

		<li>
			<a href = "<?php echo get_permalink($post->ID)?>"><?php echo $post->post_title ?></a> (<?php echo $name; ?>)		</li>

<?php endforeach; else: ?>

		<li><?php _e("No contributions yet.") ?></li>
	
<?php endif; ?>
	
	</ul>
</div>
