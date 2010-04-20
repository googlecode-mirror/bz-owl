<?php
ini_set ('session.use_trans_sid', 1);
ini_set ('session.name', 'SID');
session_start();
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
  <meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
<?php
include('stylesheet.inc');
?>
  <link href="news.css" rel="stylesheet" type="text/css">
  <title>Home</title>
</head>
<body>
<?php
	$buffer = '';
	require realpath('CMS/navi.inc');
	
	if ((isset($_SESSION['IsAdmin'])) && ($_SESSION['IsAdmin']))
	{
		echo '<a href="static_leaguesite/">[edit]</a><br>' . "\n";
	}
	
	$handle = @fopen ('static_leaguesite/news.txt', 'r');
	if ( $handle ){
		while (!feof($handle)){
			$buffer .= fgets($handle, 4096);
		}
		fclose($handle);
		print $buffer;
	}
	else{
		print "Failed to open news archive.<br>\n";
	}
?>

</div>
</body>
</html>