<?php
	// set a cookie to test if client accepts cookies
	$output_buffer = '';
	ob_start();
	require realpath('../CMS/siteinfo.php');
	@setcookie('cookies', "allowed", 0, basepath() . 'Config/', domain(), 0);
	
	$stylesheet = '';
	if (isset($_GET['stylesheet']))
	{
		$stylesheet=$_GET['stylesheet'];
	}
	
	if (strlen($stylesheet) > 0)
	{
		$cookies = false;
		// if script is called again (content in $stylesheet), one can test if cookies are activated
		foreach ($_COOKIE as $key => $value)
		{
			if (strcasecmp($key, 'cookies') == 0)
			{
				// cookies are activated
				$cookies = true;
			}
		}
		if ($cookies == false)
		{
			// cookies are not allowed -> use SIDs with GET
			// SIDs are used elsewhere only for permission system
			ini_set ('session.use_trans_sid', 1);
			$_SESSION['stylesheet'] = $stylesheet;
		} else
		{
			ini_set ('session.use_trans_sid', 0);
			@setcookie('stylesheet', $stylesheet, time()+60*60*24*30, basepath(), domain(), 0);
		}
	}
	
	ini_set ('session.name', 'SID');
	ini_set('session.gc_maxlifetime', '7200');
	session_start();
	
	$output_buffer .= ob_get_contents();
	ob_end_clean();
	// write output buffer
	echo $output_buffer;
	
	echo "<!DOCTYPE HTML PUBLIC \x22-//W3C//DTD HTML 4.01//EN\x22 \x22http://www.w3.org/TR/html4/strict.dtd\x22>\n";
	echo "<html>\n<head>\n";
	echo "  <meta content=\x22text/html; charset=ISO-8859-1\x22 http-equiv=\x22content-type\x22>\n";
	if (strlen($stylesheet) > 0)
	{
		// we have a new stylesheet chosen by user
		echo '  <link href="../styles/';
		echo $stylesheet;
		echo '" rel="stylesheet" type="text/css">' . "\n";
	} else
	{
		// use previously used stylesheet
		
		require_once '../CMS/siteinfo.php';
		$site = new siteinfo();
		
		include '../stylesheet.inc';
	}
	?>
<title>Config</title>
</head>
<body>
<?php
	require realpath('../CMS/navi.inc');
?>
<p class="first_p">This is the user configuration section.</p>
<?php
	// allow turning on or off SQL debug output
	if (isset($_SESSION['allow_change_debug_sql']) && $_SESSION['allow_change_debug_sql'])
	{
		// $site has been instantiated in navi.inc
		if ($site->debug_sql())
		{
			// SQL debuggin currently on
			
			if (isset($_GET['debug']))
			{
				if ((int) $_GET['debug'] === 0)
				{
					// user wishes to turn off SQL debugging
					echo '<a href=".?debug=1">Turn on SQL debugging this session</a>' . "\n";
					$_SESSION['debug_sql'] = false;
				} else
				{
					// user wishes to turn on SQL debugging
					echo '<a href=".?debug=0">Turn off SQL debugging this session</a>' . "\n";
					$_SESSION['debug_sql'] = true;
				}
			} else
			{
				echo '<a href=".?debug=0">Turn off SQL debugging this session</a>' . "\n";
			}
		} else
		{
			// SQL debuggin currently off
			
			if (isset($_GET['debug']))
			{
				if ((int) $_GET['debug'] === 0)
				{
					echo htmlent((int) $_GET['debug']);
					// user wishes to turn off SQL debugging
					echo '<a href=".?debug=1">Turn on SQL debugging this session</a>' . "\n";
					$_SESSION['debug_sql'] = false;
				} else
				{
					// user wishes to turn on SQL debugging
					echo '<a href=".?debug=0">Turn off SQL debugging this session</a>' . "\n";
					$_SESSION['debug_sql'] = true;
				}
			} else
			{
				echo '<a href=".?debug=1">Turn on SQL debugging this session</a>' . "\n";
			}
		}
	}
?>
<p>Please note: Stylesheets are not finalised yet. Snow and White were designed for mobile devices in particular, the other ones are in flux at the moment.</p>
<form enctype="application/x-www-form-urlencoded" method="get" action="<?php
	
	// the address depends on where the file resides
	$url = baseaddress() . 'Config/';
	echo $url;
?>">
<p>Theme:
  <select name="stylesheet">
<?php
	define ('SELECTED', ' selected="selected"');
	
	echo '    <option';
	if (strcmp($stylesheet, 'White') == 0)
	{
		echo SELECTED;
	}
	echo '>White</option>' . "\n";
	
	echo '    <option';
	if (strcmp($stylesheet, 'Snow') == 0)
	{
		echo SELECTED;
	}
	echo '>Snow</option>' . "\n";
	echo '    <option';
	if (strcmp($stylesheet, 'Ups Layout') == 0)
	{
		echo SELECTED;
	}
	echo '>Ups Layout</option>' . "\n";
	
?>
  </select>
  <input type="submit" value="Submit changes">
</p>
</form>
</div>
</body>
</html>
