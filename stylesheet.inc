<?php
	define("default_stylesheet", 'White');
	
	$stylesheet = default_stylesheet;
	foreach ($_COOKIE as $key => $value)
	{
		if (strcasecmp($key, 'stylesheet') == 0)
		{
			// cookies turned on
			$stylesheet = $value;
		}
	}
	
	if (isset($_SESSION['stylesheet']))
	{
		$stylesheet = $_SESSION['stylesheet'];
	}
	
	if (strcasecmp($stylesheet, '') == 0)
	{
		// nothing is set, go back to default
		$stylesheet = default_stylesheet;
	}
	
	require_once 'CMS/siteinfo.php';
	//host and path
	$url = baseaddress();
	
	echo '  <link href="' . $url;
	// Need to append file extension as only
	// apache allows not using it by default
	echo 'styles/' . $stylesheet . '.css"';
	echo ' rel="stylesheet" type="text/css">' . "\n";
?>
