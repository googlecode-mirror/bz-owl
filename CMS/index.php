<?php
	// do not attach SID to URL as this can cause security problems
    // especially when a user shares an URL that includes a SID
	// use cookies as workaround
	ini_set ('session.use_trans_sid', 0);
	ini_set ('session.name', 'SID');
	@session_start();
	
	require_once 'Modulliste.php';
	$module = aktive_Anmeldungsmodule();
	$output = '';
	
	// load description presented to user
	require_once 'index.inc';
	
	if (!(isset($_SESSION['user_logged_in'])) || !($_SESSION['user_logged_in']))
	{
		// load modules to check input and buffer output
        // the buffer is neccessary because the modules might need to set cookies for instance
		if (isset($module['bzbb']) && ($module['bzbb']))
		{
			ob_start();
			include_once 'bzbb_login/index.php';
			$output .= ob_get_contents();
			ob_end_clean();
		}
		
		if (isset($module['local']) && ($module['local']))
		{
			ob_start();
			include_once 'local_login/index.php';
			$output .= ob_get_contents();
			ob_end_clean();
		}		
	}
	
	
	if (!(strcmp($output, '') == 0))
	{
		// buffer can now be written
		echo $output;
	}
	
	if ((strcmp($output, '') == 0) && (isset($_SESSION['user_logged_in'])) && $_SESSION['user_logged_in'])
	{
		require_once '../CMS/navi.inc';	
		echo '<p>Login was already successful.</p>' . "\n";
		$site->dieAndEndPage('');
	}
	
	if (!(isset($_SESSION['user_logged_in'])) || !($_SESSION['user_logged_in']))
	{
		if (isset($module['bzbb']) && ($module['bzbb']))
		{
			include_once 'bzbb_login/login_text.inc';
		}
		
		if (isset($module['local']) && ($module['local']))
		{
			include_once 'local_login/login_text.inc';
		}
	}
?>