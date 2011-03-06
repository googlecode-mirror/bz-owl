<?php
	function pageAddonFixed($path)
	{
		if (strcmp($path, 'Login/') === 0)
		{
			return true;
		}
		
		return false;
	}
	
	function addonToUse($path, &$title)
	{
		global $db;
		
		
		$query = $db->prepare('SELECT `addon`, `title` FROM `CMS` WHERE `requestPath`=? LIMIT 1');
		$db->execute($query, $path);
		
		$row = $db->fetchRow($query);
		$db->free($query);
		
		if (count($row) > 0)
		{
			$addon = $row['addon'];
			$title = $row['title'];
			
			return $addon;
		}
		
		return false;
	}
	
	function loadAddon($addon, $title, $path)
	{
		global $site;
		global $tmpl;
		
		$file = dirname(__FILE__) . '/CMS/add-ons/' . $addon
				. '/' . $addon . '.php';
		echo($file);
		if (file_exists($file))
		{
		echo(' loaded with path ' . $path);
			// init the addon
			include($file);
			$addon = new $addon($title, $path);
		} else
		{
			// the path could not be found in database
			header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
			$tmpl->setTemplate('NoPerm');
			$tmpl->done('This page does not exist.');
		}
	}
	
	// if path specified use it, otherwise default to root
	$path = (isset($_GET['path']))?$_GET['path']:'/';
	
	if (!pageAddonFixed($path))
	{
		// init common classes
		require('CMS/site.php');
		$site = new site();
		
		$title = 'Untitled';
		
		// load the add-on
		loadAddon(addonToUse($path, $title), $title, $path);
	}
?>
