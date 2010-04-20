<?php
	ini_set ('session.use_trans_sid', 0);
	ini_set ('session.name', 'SID');
	session_start();
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
<?php
	include('../stylesheet.inc');
	
	$pfad = (pathinfo(realpath('./')));
	$name = $pfad['basename'];
	print '  <title>' . $name . '</title>' . "\n";
?>
</head>
<body>
<?php
require realpath('../CMS/navi.inc');
?>
Platzhalter
</div>
</body>
</html>
