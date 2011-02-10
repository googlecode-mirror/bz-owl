<?php
	require_once (dirname(dirname(__FILE__)) . '/site.php');
	$site = new site();
	
	if (isset($_GET['add']))
	{
		require_once dirname(__FILE__) . '/pmAdd.php';
	} elseif (isset($_GET['edit']))
	{
		require_once dirname(__FILE__) . '/pmEdit.php';
	} elseif (isset($_GET['delete']))
	{
		require_once dirname(__FILE__) . '/pmDelete.php';
	} else
	{
		require_once dirname(__FILE__) . '/List.php';
	}
?>
