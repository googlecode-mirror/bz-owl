<?php
	// FIXME: FALLBACK FOR NOW
	if (isset($_GET['add']) || isset($_GET['edit']) || isset($_GET['delete']))
	{
		require_once dirname(dirname(__FILE__)) . '/siteinfo.php';
		$site = new siteinfo();
		
		include dirname(dirname(__FILE__)) . '/announcements/index.php';
		
		die();
	}
	
	require_once (dirname(dirname(__FILE__)) . '/site.php');
	$site = new site();
	
	// find out which template should be used
	if ($user->getID() < 1)
	{
		$tmpl->setTemplate('NoPerm');
		$tmpl->done('You have insufficient permissions for this action.');
	}
	
	// show messages in current mail folder
	// inbox is default
	$folder = 'inbox';
	if (isset($_GET['folder']) && strcmp($_GET['folder'], 'outbox') === 0)
	{
		$folder = 'outbox';
	}
	
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
		$display = new pmDisplay();
			
		switch(isset($_GET['view']))
		{
			case true: $display->showMail($folder, intval($_GET['view'])); break;
			default: $display->showMails($folder); break;
		
		}
	}
	
	$tmpl->render();
?>