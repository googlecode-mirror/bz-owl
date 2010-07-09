<?php
	// do not attach SID to URL as this can cause security problems
	// especially when a user shares an URL that includes a SID
	// use cookies as workaround
	ini_set ('session.use_trans_sid', 0);
	ini_set ('session.name', 'SID');
	ini_set('session.gc_maxlifetime', '7200');
	@session_start();
	
	require_once 'login_module_list.php';
	$module = active_login_modules();
	$output = '';
	
	// load description presented to user
	$page_title = 'ts-CMS';
	require_once 'index.inc';
	
	$auth_performed = false;
	
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
		
		if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'])
		{
			$auth_performed = true;
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
		echo '<div class="static_page_box">' . "\n";
		echo '<p class="first_p">Login was already successful.</p>' . "\n";
		$site->dieAndEndPage();
	}
	
	if (!(isset($_SESSION['user_logged_in'])) || !($_SESSION['user_logged_in']))
	{
		// user explicitly does not want an external login and confirmed it already
		if (!(isset($_POST['local_login_wanted']) && $_POST['local_login_wanted']))
		{
			if (isset($module['bzbb']) && ($module['bzbb']))
			{
				include_once 'bzbb_login/login_text.inc';
			}
		}
		
		if (isset($module['local']) && ($module['local']))
		{
			include_once 'local_login/login_text.inc';
		}
	}
?>