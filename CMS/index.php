<?php
	// Keine SID an URL anhaengen, sonst eventuell Sicherheitsproblem
	// bei Weitergabe einer URL -> Kekse nutzen
	ini_set ('session.use_trans_sid', 0);
	ini_set ('session.name', 'SID');
	@session_start();
	
	require_once 'Modulliste.php';
	$module = aktive_Anmeldungsmodule();
	$ausgabe = '';
	
	// load description presented to user
	require_once 'index.inc';
	
	if (!(isset($_SESSION['user_logged_in'])) || !($_SESSION['user_logged_in']))
	{
		// Module zur Pruefung laden und Ausgaben puffern
		if (isset($module['bzbb']) && ($module['bzbb']))
		{
			ob_start();
			include_once 'bzbb_login/index.php';
			$ausgabe .= ob_get_contents();
			ob_end_clean();
		}
		
		if (isset($module['local']) && ($module['local']))
		{
			ob_start();
			include_once 'local_login/index.php';
			$ausgabe .= ob_get_contents();
			ob_end_clean();
		}		
	}
	
	
	if (!(strcmp($ausgabe, '') == 0))
	{
		// Ausgabenpuffer kann jetzt geschrieben werden
		echo $ausgabe;
	}
	
	if ((strcmp($ausgabe, '') == 0) && (isset($_SESSION['user_logged_in'])) && $_SESSION['user_logged_in'])
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