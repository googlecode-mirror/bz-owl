<?php
	// this file is meant to be moved to web/index.php
	// once the new content system is working
	function pageAddonFixed($path)
	{
		if (strcmp($path, 'Login/') === 0)
		{
			return true;
		}
		
		return false;
	}
	
	function addonToUse($path)
	{
		global $db;
		
		$query = $db->prepare('SELECT `addon` FROM `CMS` WHERE `requestPath`=? LIMIT 1');
		$db->execute($query, $path)
		
		$addon = $db->fetchRow($query);
		$db->free($query);
		
		return $addon;
	}
	
	function loadAddon($addon)
	{
		global $tmpl;
		
		$file = dirname(__FILE) . '/CMS/add-ons/' . $addon;
		if (file_exists($file))
		{
			include($file);
		} else
		{
			// the path could not be found in database
			header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
			$tmpl = setTemplate('NoPerm');
			$tmpl->done('This page does not exist.');
		}
	}
	
	$path = $_GET['path'];
	if (!pageAddon($path))
	{
		// init common classes
		require('site.php');
		$site = new site();
		
		// load the add-on
		loadAddon(addonToUse($path));
	}
?>