<?php
	// Keks setzen zum Pruefen, ob Kekse auf Klientseite erlaubt
	$ausgabe = '';
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
		$kekse = false;
		// Beim erneuten Aufruf (Eingabe) kann man pruefen
		foreach ($_COOKIE as $key => $value)
		{
			if (strcasecmp($key, 'cookies') == 0)
			{
				// Kekse sind aktiviert
				$kekse = true;
			}
		}
		if ($kekse == false)
		{
			// Kekse sind nicht erlaubt -> SIDs mit GET benutzen
			// SIDs werden sonst nur fuer die Zugriffsverwaltung
			// benutzt
			ini_set ('session.use_trans_sid', 1);
			$_SESSION['stylesheet'] = $stylesheet;
		} else
		{
			ini_set ('session.use_trans_sid', 0);
			@setcookie('stylesheet', $stylesheet, time()+60*60*24*30, basepath(), domain(), 0);
		}
	}
	
	ini_set ('session.name', 'SID');
	session_start();
	
	$ausgabe .= ob_get_contents();
	ob_end_clean();
	// Ausgabenpuffer kann jetzt geschrieben werden
	echo $ausgabe;
	
	echo "<!DOCTYPE HTML PUBLIC \x22-//W3C//DTD HTML 4.01//EN\x22 \x22http://www.w3.org/TR/html4/strict.dtd\x22>\n";
	echo "<html>\n<head>\n";
	echo "  <meta content=\x22text/html; charset=ISO-8859-1\x22 http-equiv=\x22content-type\x22>\n";
	if (strlen($stylesheet) > 0)
	{
		echo '  <link href="../styles/';
		echo $stylesheet;
		echo '" rel="stylesheet" type="text/css">' . "\n";
	} else
	{
		include '../stylesheet.inc';
	}
	?>
<title>Config</title>
</head>
<body>
<?php
	require realpath('../CMS/navi.inc');
?>
<p>This is the user configuration section.</p>
<p>Please note: ONLY Snow and White look good, the other ones are just a technology demo</p>
<form enctype="application/x-www-form-urlencoded" method="get" action="<?php
	
	// the address depends on where the file resides
	$url = baseaddress() . 'Config/';
	echo $url;
?>">
<p>Theme:
  <select name="stylesheet">
<?php
	define ('GEWAEHLT', ' selected="selected"');
	
	echo '    <option';
	if (strcmp($stylesheet, 'black') == 0)
	{
		echo GEWAEHLT;
	}
	echo '>black</option>' . "\n";
	
	echo '    <option';
	if (strcmp($stylesheet, 'Tangerine On White') == 0)
	{
		echo GEWAEHLT;
	}
	echo '>Tangerine On White</option>' . "\n";
	
	echo '    <option';
	if (strcmp($stylesheet, 'Tangerine On Ice') == 0)
	{
		echo GEWAEHLT;
	}
	echo '>Tangerine On Ice</option>' . "\n";
	
	echo '    <option';
	if (strcmp($stylesheet, 'Sky On White') == 0)
	{
		echo GEWAEHLT;
	}
	echo '>Sky On White</option>' . "\n";
	
	echo '    <option';
	if (strcmp($stylesheet, 'White') == 0)
	{
		echo GEWAEHLT;
	}
	echo '>White</option>' . "\n";
	
	echo '    <option';
	if (strcmp($stylesheet, 'Snow') == 0)
	{
		echo GEWAEHLT;
	}
	echo '>Snow</option>' . "\n";	
	
?>
  </select>
  <input type="submit" value="Submit changes">
</p>
</form>
</div>
</body>
</html>
