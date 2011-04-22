<?php
	// set a cookie to test if client accepts cookies
	$output_buffer = '';
	ob_start();
	require dirname(dirname(__FILE__)) . '/CMS/site.php';
	$site = new site();
	@setcookie('cookies', 'allowed', 0, $config->value('basepath') . 'Config/', $config->value('domain'), 0);
	
	$theme = '';
	if (isset($_GET['theme']))
	{
		$theme=$_GET['theme'];
		
		// check if theme stylesheet file does exist
		if (!file_exists(dirname(dirname(__FILE__)) .'/themes/'
						 . str_replace(' ', '%20', htmlspecialchars($theme) . '/')
						 . str_replace(' ', '%20', htmlspecialchars($theme) . '.css')))
		{
			$theme = '';
		}
		
	}
	
	if (strlen($theme) > 0)
	{
		if ($tmpl->existsTemplate($theme, 'Config'))
		{
			$user->saveTheme($theme);
  		}
	}
	
	$output_buffer .= ob_get_contents();
	ob_end_clean();
	// write output buffer
	echo $output_buffer;
	
	
	// read out installed themes instead of defining a fixed list in source code
	
	// first scan the files in the styles directory
	$themes = scandir(dirname(dirname(__FILE__)) . '/themes/');
	foreach ($themes as $i => $curFile)
	{
		// remove entry from array if it's no folder
		if (!is_dir(dirname(dirname(__FILE__)) . '/themes/' . $curFile))
		{
			unset($themes[$i]);
			continue;
		}
		
		// filter reserved directory names
		switch ($curFile)
		{
			case (strcasecmp('.', $curFile) === 0):
				unset($themes[$i]);
				break;
			
			case (strcasecmp('..', $curFile) === 0):
				unset($themes[$i]);
				break;
			
			case (strcasecmp('.svn', $curFile) === 0):
				unset($themes[$i]);
				break;
		}
		
		// filter themes with no stylesheet
		if (isset($themes[$i]) && !file_exists(dirname(dirname(__FILE__)) . '/themes/' . $curFile . '/' . $curFile . '.css'))
		{
			unset($themes[$i]);
		}
		
		// filter unfinished themes if debugSQL is turned off
		if (isset($themes[$i]) && !$config->value('debugSQL') && file_exists(dirname(dirname(__FILE__)) . '/themes/' . $curFile . '/unfinished'))
		{
			unset($themes[$i]);
		}
	}
	unset($curFile);
	
	
	if (!$tmpl->setTemplate('Config', $theme))
	{
		$tmpl->noTemplateFound();
	}
	
	if (strlen($theme) > 0)
	{
		$tmpl->assign('curTheme', $theme);
	} else
	{
		$tmpl->assign('curTheme', $user->getTheme());
	}
	
	// presentation logic in template
	// so just pass the result from business logic
	$tmpl->assign('themes', $themes);
	
	
	function repositoryVersion()
	{
		if (file_exists('../.svn/entries'))
		{
			$handle = fopen('../.svn/entries', 'rb');
			$counter = 1;
			while ($rev = fscanf($handle, "%[a-zA-Z0-9,. ]%[dir]\n%[a-zA-Z0-9,.]"))
			{
				$counter++;
				
				if ($counter > 4)
				{
					// Listing some of them
					list($svn_rev) = $rev;
					break;
				}
			}
			fclose($handle);
			unset($counter);
		}
		
		return $svn_rev;
	}
	
	$tmpl->assign('repositoryVersion', repositoryVersion());
	
	$tmpl->display();
?>
