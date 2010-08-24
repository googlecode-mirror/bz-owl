<?php
	// this is only a siteoptions_path example file
	
	// copy it to the path specified within siteinfo.php and
	// edit the return values appropriately to get the site running
	// do the same for the siteoptions example
	
	// __FILE__ is a PHP 5 constant that points to the current file (in this case siteinfo.php)
	require_once (realpath(dirname(dirname(dirname(__FILE__))) . '/siteoptions.php'));
?>