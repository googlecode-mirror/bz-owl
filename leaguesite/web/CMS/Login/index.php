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
	
	// init siteinfo class if not already done
	if (!isset($site))
	{
		require_once dirname(dirname(__FILE__)) . '/siteinfo.php';
		$site = new siteinfo();
	}
	
	
	require dirname(dirname(__FILE__)) . '/classes/user.php';
	$user = new user();
	
	
	// magic quote band-aid for POST variables
	if (magic_quotes_on())
	{
		stripslashes($_POST);
	}
	
	
	$tmpl = new template('Login');
	
	
	// login was tried
	if (loginSuccessful())
	{
		$tmpl->addMSG('<p class="first_p">Login was successful!</p>');
		$tmpl->addMSG('<p>Your profile page can be found <a href="../Players/?profile=' . $user_id . '">here</a>.</p>');
	}
	
	// login was tried..but already successful
	if ($user->loggedIn())
	{
		$tmpl->done('Login was already successful.');
	}
	
	if (!loginTried())
	{
		displayLoginOptions();
	} else
	{
		loadLoginModule();
	}
	
	
	// $user_id is not set in case no login/registration was performed
	if ($user->getID() > 0)
	{
		$tmpl->addMSG('<p class="first_p">Login was successful!</p>');
		$tmpl->addMSG('<p>Your profile page can be found <a href="../Players/?profile=' . $user_id . '">here</a>.</p>');
	} else
	{
		// perform a logout just in case anything went wrong.
		$user->logout();
	}
	
	
	// done, render page
	$tmpl->render();
	
	die();
	
	
	
	function loadLoginModule()
	{
		global $module;
		global $site;
		global $tmpl;
		
		// load modules to check input and buffer output
		// the buffer is neccessary because the modules might need to set cookies for instance
		if (isset($module['bzbb']) && ($module['bzbb']))
		{
			include_once 'bzbb_login/index.php';
		}
		
		if (isset($module['local']) && ($module['local']))
		{
			include_once 'local_login/index.php';
		}
	}
	
	function loginTried()
	{
		if (isset($_POST['loginname']) || isset($_GET['bzbbauth']))
		{
			return true;
		}
		return false;
	}
	
	function loginSuccessful()
	{
		global $user;
		
		$auth_performed = false;
		
		if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'])
		{
			$auth_performed = true;
		}
		
		echo 'calling identifyAccount';
		
		// if account can not be identified, it willl logout the user
		$user->identifyAccount();
		
		if (!$auth_performed && isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'])
		{
			return true;
		}
		
		return false;
	}
	
	function displayLoginOptions()
	{
		global $site;
		global $tmpl;
		global $module;
		
		
		if (!(isset($_SESSION['user_logged_in'])) || !($_SESSION['user_logged_in']))
		{
			// user explicitly does not want an external login and confirmed it already
			if (!(isset($_POST['local_login_wanted']) && $_POST['local_login_wanted']))
			{
				if (isset($module['bzbb']) && ($module['bzbb']))
				{
					include 'bzbb_login/login_text.php';
				}
			}
			
			if (!( (isset($_GET['bzbbauth'])) && ($_GET['bzbbauth']) ))
			{
				if (!(isset($_POST['local_login_wanted']) && $_POST['local_login_wanted']) && isset($module['local']) && ($module['local']))
				{
					$tmpl->addMSG('<strong>or</strong>');
					$tmpl->addMSG($site->return_self_closing_tag('br'));
					$tmpl->addMSG($site->return_self_closing_tag('br'));
				}
			}
			
			if (isset($module['local']) && ($module['local']))
			{
				include_once 'local_login/login_text.php';
			}
		}
	}
	
	
	
	
	
	
	$output = '';
	
	// load description presented to user
	$page_title = 'ts-CMS';
	require_once dirname(dirname(__FILE__)) . '/index.inc';
	
	$auth_performed = false;
	
	ob_start();

	if (!(isset($_SESSION['user_logged_in'])) || !($_SESSION['user_logged_in']))
	{
		// load modules to check input and buffer output
		// the buffer is neccessary because the modules might need to set cookies for instance
		if (isset($module['bzbb']) && ($module['bzbb']))
		{
			include_once 'bzbb_login/index.php';
		}
		
		if (isset($module['local']) && ($module['local']))
		{
			include_once 'local_login/index.php';
		}
		
	}
	
	if (!$auth_performed && isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'])
	{
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
		
		if (!( (isset($_GET['bzbbauth'])) && ($_GET['bzbbauth']) ))
		{
			if (!(isset($_POST['local_login_wanted']) && $_POST['local_login_wanted']) && isset($module['local']) && ($module['local']))
			{
				echo '<strong>or</strong>';
				$site->write_self_closing_tag('br');
				$site->write_self_closing_tag('br');
			}
		}
		
		if (isset($module['local']) && ($module['local']))
		{
			include_once 'local_login/login_text.inc';
		}
	}
?>