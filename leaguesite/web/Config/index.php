<?php
	// set a cookie to test if client accepts cookies
	$output_buffer = '';
	ob_start();
	require dirname(dirname(__FILE__)) . '/CMS/site.php';
	$site = new site();
	@setcookie('cookies', 'allowed', 0, $config->value('basepath') . 'Config/', domain(), 0);
	
	$theme = '';
	if (isset($_GET['theme']))
	{
		$theme=$_GET['theme'];
		
		// check if theme stylesheet file does exist
		if (!file_exists(dirname(dirname(__FILE__)) .'/styles/'
						 . str_replace(' ', '%20', htmlspecialchars($theme) . '/')
						 . str_replace(' ', '%20', htmlspecialchars($theme) . '.css')))
		{
			$theme = '';
		}
		
	}
	
	if (strlen($theme) > 0)
	{
		$cookies = false;
		// if script is called again (content in $theme), one can test if cookies are activated
		foreach ($_COOKIE as $key => $value)
		{
			if (strcasecmp($key, 'cookies') == 0)
			{
				// cookies are activated
				$cookies = true;
			}
		}
		if ($cookies == false)
		{
			// cookies are not allowed -> use SIDs with GET
			// SIDs are used elsewhere only for permission system
			ini_set ('session.use_trans_sid', 1);
			$_SESSION['theme'] = $theme;
		} else
		{
			ini_set ('session.use_trans_sid', 0);
			@setcookie('theme', $theme, time()+60*60*24*30, $config->value('basepath'), domain(), 0);
		}
	}
	
	$output_buffer .= ob_get_contents();
	ob_end_clean();
	// write output buffer
	echo $output_buffer;
	
	// read out installed themes instead of defining a fixed list in source code
	
	// first scan the files in the styles directory
	$themes = scandir(dirname(dirname(__FILE__)) . '/styles/');
	foreach ($themes as $i => $curFile)
	{
		// remove entry from array if it's no folder
		if (!is_dir(dirname(dirname(__FILE__)) . '/styles/' . $curFile))
		{
			unset($themes[$i]);
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
		if (!file_exists(dirname(dirname(__FILE__)) . '/styles/' . $curFile . '/' . $curFile . '.css'))
		{
			unset($themes[$i]);
		}
	}
	unset($curFile);
	
	
	if (isset($theme))
	{
		$tmpl->setTemplate('', $theme);
	} else
	{
		$tmpl->setTemplate('', '');
	}
	
	
	$tmpl->setCurrentBlock('cell');
	
	if (strlen($theme) > 0)
	{
		$s = $theme;
	} else
	{
		$s = $user->getStyle();
	}
	foreach ($themes as $theme)
	{
		$tmpl->setVariable('THEME', $theme);
		$tmpl->setVariable('SELECTED', 	($theme==$s?' selected="selected"':''));
		$tmpl->parseCurrentBlock();
	}
	unset($s);
	
	
	function RepositoryVersion()
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
	
	$tmpl->setCurrentBlock('repository');
	$tmpl->setVariable('REPOSITORYVERSION', RepositoryVersion());
	
	$tmpl->render();
?>
