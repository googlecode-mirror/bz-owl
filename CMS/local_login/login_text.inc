<?php
	require_once '../CMS/navi.inc';
	
	if ((isset($_SESSION['user_logged_in'])) &&	($_SESSION['user_logged_in']))
	{
		echo 'already logged in';
		die();
	}
	
	echo '<p class="first_p"><strong>Never enter any login information from a different website at the following form!</strong>';
	echo ' The following form is only there to use your local registration and does only serve local registration purposes.</p>';
	echo '<p><strong>It is strongly discouraged to enter any global login information below.</strong></p>';
	echo "\n";
	require_once 'form.inc';
	echo '<p>Note: Only global login will give you more than standard permissions at the moment.</p>' . "\n";
?>