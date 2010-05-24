<?php
	if ($_SESSION['isAdmin'])
	{
		die();
	}
?>

<div class="content">

<p>Das hier ist das Anmeldungsmoduls des auf dieser Seite eingesetzten <abbr title="Content Management System">CMS</abbr>.</p>
<p>Es stehen folgende M&ouml;glichkeiten zum Anmelden zur Verf&uuml;gung:</p>
<ul>

<?php
	require_once 'Modulliste.php';
	$module = aktive_Anmeldungsmodule();
	
	if (isset($module['bzbb']) && ($module['bzbb']))
	{
		include 'bzbb_login/anmeldetext.inc';
	}
	
	if (isset($module['bzbb']) && ($module['bzbb']))
	{
		include 'local_login/anmeldetext.inc';
	}
	
	echo "</ul>\n";
?>