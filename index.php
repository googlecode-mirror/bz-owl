<?php	
    // the current page to be edited
    $page_title = './';
	
	$randomkey_name = ('randomkey_static_pages_' . $page_title);
    //	$entry_add_permission = 'allow_add_messages';
	
	// do not allow editing messages
	$entry_edit_permission = 'allow_edit_static_pages';
	
	include_once('CMS/announcements/static_website_content.php');
?>