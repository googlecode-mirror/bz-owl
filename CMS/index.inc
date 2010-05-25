<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
        "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
  <meta content="text/html; charset=utf-8" http-equiv="content-type">
<?php
	require (dirname(dirname(__FILE__)) . '/stylesheet.inc');
	echo '  <title>';
	// display the title specified in $page_title
	// the latter is defined in the script that includes this file
	if (!(strcmp($display_page_title, '') === 0))
	{
		echo $display_page_title;
	} else
	{
		echo 'No page title set';
	}
	echo '</title>' . "\n";

?>
</head>
<body>
