<?php
	if ($_SESSION['isAdmin'])
	{
		die();
	}
?>

<div class="content">

<p class="first_p">This is the login module of the used <abbr title="Content Management System">CMS</abbr>here.</p>
<p>The following possibilities to login are available:</p>
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