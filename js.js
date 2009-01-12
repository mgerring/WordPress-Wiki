function toggle_hide_show() {
	if (jQuery("#content_list").css('display')!='none') {
		jQuery('#content_list').hide();
		jQuery('#hide_show').html('show');
	} else {
		jQuery('#content_list').show();
		jQuery('#hide_show').html('hide');
	}
}