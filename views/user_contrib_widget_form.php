<p>
	<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?><br />
	    <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
	</label>
</p>
<p>
	<label for="<?php echo $this->get_field_id('nos'); ?>"><?php _e('Number of posts to show:'); ?>
	    <input name="<?php echo $this->get_field_name('nos'); ?>" id="<?php echo $this->get_field_id('nos'); ?>" class="widefat" type="text" size="3" maxlength="3" value="<?php echo $nos; ?>" />
	</label>
</p>