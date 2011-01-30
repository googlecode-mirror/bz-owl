<?php
	ini_set ('session.use_trans_sid', 0);
	ini_set ('session.name', 'SID');
	ini_set('session.gc_maxlifetime', '7200');
	session_start();
	
	
	function sanityCheck(&$confirmed)
	{
		global $tmpl;
		
		if (!hasEditPermission())
		{			
			// editing cancelled due to missing user permission
			$confirmed = 0;
			return 'noperm';
		}
		
		echo $confirmed;
		// no need to check for a key match if no content was supplied
		if (($confirmed > 0) && !randomKeyMatch($confirmed))
		{
			// editing cancelled due to random key mismatch
			$confirmed = 0;
			return 'nokeymatch';
		}
		
		return true;
	}
	
	function hasEditPermission()
	{
		global $entry_edit_permission;
		
		if ((isset($_SESSION[$entry_edit_permission])) && ($_SESSION[$entry_edit_permission]))
		{
			return true;
		} else
		{
			return false;
		}
	}
	
	function randomKeyMatch(&$confirmed)
	{
		global $site;
		
		$randomKeyValue = '';
		$randomKeyName = '';
		
		if (isset($_POST['key_name']))
		{
			$randomKeyName = html_entity_decode($_POST['key_name']);
			
			if (isset($_POST[$randomKeyName]))
			{
				$randomKeyValue = html_entity_decode($_POST[$randomKeyName]);
			}
		}
		
		return $randomkeysmatch = $site->validateKey($randomKeyName, $randomKeyValue);
	}
	
	require_once (dirname(dirname(__FILE__)) . '/siteinfo.php');
	$site = new siteinfo();
	
	// find stylesheet even if this thing is on.
	if (magic_quotes_on())
	{
		stripslashes($_COOKIE);
	}
	
	// force call this template home
	// otherwise we'd need a custom name per setup
	// as top level dir could be named different
	
	if (isset($_GET['edit']))
	{
		// remove the slashes if this function is sadly on
		if (magic_quotes_on())
		{
			stripslashes($_POST);
		}
		
		$tmpl = new template('Home?edit');
		$tmpl->setCurrentBlock('USER_ENTERED_CONTENT');
		if (isset($_POST['staticContent']))
		{
			$tmpl->setVariable('RAW_CONTENT_HERE', $_POST['staticContent']);
		} else
		{
			$tmpl->setVariable('RAW_CONTENT_HERE', readContent($page_title, $author, $last_modified, true));
		}
	} else
	{
		$tmpl = new template('Home');
		$tmpl->addMSG(readContent($page_title, $author, $last_modified, false));
	}
	
	$tmpl->parseCurrentBlock();
	
	
	if ((isset($_SESSION[$entry_edit_permission])) && ($_SESSION[$entry_edit_permission]))
	{
		// user has permission to edit the page
		if (!isset($_GET['edit']))
		{
			// user looks at page in read mode
			$tmpl->setCurrentBlock('USERBUTTONS');
			$tmpl->setVariable('PERMISSION_BASED_BUTTONS', '<a href="./?edit" class="button">edit</a>');
			$tmpl->parseCurrentBlock();
		}
		
		if (isset($_GET['edit']))
		{
			$tmpl->setCurrentBlock('USER_NOTE');
			
			if ($site->bbcode_lib_available())
			{
				$tmpl->setVariable('EDIT_MODE_NOTE', 'Keep in mind to use BBCode instead of HTML or XHTML.');
				$tmpl->parseCurrentBlock();
				
				include dirname(dirname(__FILE__)) . '/bbcode_buttons.php';
				$bbcode = new bbcode_buttons();
				$bbcode->showBBCodeButtons();
				
				$buttons = $bbcode->showBBCodeButtons();
				$tmpl->setCurrentBlock('STYLE_BUTTONS');
				foreach ($buttons as $button)
				{
					$tmpl->setVariable('BUTTONS_TO_FORMAT', $button);
					$tmpl->parseCurrentBlock();
				}
				
				// forget no longer needed variables
				unset($button);
				unset($buttons);
				unset($bbcode);
			} else
			{
				if ($site->use_xtml())
				{
					$tmpl->setVariable('EDIT_MODE_NOTE', 'Keep in mind the home page currently uses XHTML, not HTML or BBCode.');
				} else
				{
					$tmpl->setVariable('EDIT_MODE_NOTE', 'Keep in mind the home page currently uses HTML, not XHTML or BBCode.');
				}
				$tmpl->parseCurrentBlock();
			}
			
			
			// initialise variables
			$confirmed = 0;
			$content = '';
			
			// set their values in case the POST variables are set
			if (isset($_POST['confirmationStep']))
			{
				$confirmed = intval($_POST['confirmationStep']);
			}
			if (isset($_POST['editPageAgain']))
			{
				// user looked at preview but chose to edit the message again
				$confirmed = 0;
			}
			if (isset($_POST['staticContent']))
			{
				$content = $_POST['staticContent'];
			}
			
			// sanity check variabless
			$test = sanityCheck($confirmed);
			switch ($test)
			{
				// use bbcode if available
				case (true && $confirmed === 1 && $site->bbcode_lib_available()):
					$tmpl->addMSG($site->bbcode($content));
					break;
					
				// else raw output
				case (true && $confirmed === 1 && !$site->bbcode_lib_available()):
					$tmpl->addMSG($content);
					break;
				
				// use this as guard to prevent selection of noperm or nokeymatch cases
				case (strlen($test) < 2):
					break;
				
				case 'noperm':
					$tmpl->addMSG('You need write permission to edit the content.');
					break;
					
				case 'nokeymatch':
					$tmpl->addMSG('The magic key does not match, it looks like you came from somewhere else or your session expired.');
					break;			
			}
			unset($test);
			
			// increase confirmation step by one so we get to the next level
			$tmpl->setCurrentBlock('PREVIEW_VALUE');
			$tmpl->setVariable('PREVIEW_VALUE_HERE', $confirmed+1);
			$tmpl->parseCurrentBlock();
			
			
			switch ($confirmed)
			{
				case 1:
					$tmpl->setCurrentBlock('PREVIEW_BUTTON');
					$tmpl->setVariable('SUBMIT_BUTTON_TEXT', 'Write changes');
					$tmpl->parseCurrentBlock();
					break;
				
				case 2:
					writeContent($content, $page_title);
				
				default:
					$tmpl->setCurrentBlock('PREVIEW_BUTTON');
					$tmpl->setVariable('SUBMIT_BUTTON_TEXT', 'Preview');
					$tmpl->parseCurrentBlock();
			}
			
			
			$randomKeyName = $randomkey_name . microtime();
			// convert some special chars to underscores
			$randomKeyName = strtr($randomKeyName, array(' ' => '_', '.' => '_'));
			$randomkeyValue = $site->set_key($randomKeyName);
			$tmpl->setCurrentBlock('KEY');
			$tmpl->setVariable('KEY_NAME', $randomKeyName);
			$tmpl->setVariable('KEY_VALUE', urlencode($_SESSION[$randomKeyName]));
			$tmpl->parseCurrentBlock();
			
			
			// there is no step lower than 0
			if ($confirmed < 0)
			{
				$confirmed = 0;
			}
			$tmpl->setCurrentBlock('PREVIEW_VALUE');
			// increase confirmation step by one so we get to the next level
			$tmpl->setVariable('PREVIEW_VALUE_HERE', $confirmed+1);
			$tmpl->parseCurrentBlock();
		}
	}
	
	
	// done, render page
	$tmpl->render();
	
	
	
	die();
	
	function readContent($page_title, &$author, &$last_modified, $raw=false)
	{
		global $site;
		global $connection;
		
		// initialise return variable so any returned value will be always in a defined state
		$content = '';
		if (!$raw)
		{
			$content = 'No content available yet.';
		}
		
		$query = 'SELECT * FROM `static_pages` WHERE `page_name`=' . sqlSafeStringQuotes($page_title) . ' LIMIT 1';
		if (!($result = @$site->execute_query('static_pages', $query, $connection)))
		{
			$site->dieAndEndPage('An error occured getting content for page ' . $page_title . '!');
		}
		
		// process query result array
		while ($row = mysql_fetch_array($result))
		{	 
			$author = $row['author'];
			$last_modified = $row['last_modified'];
			if ($raw && $site->bbcode_lib_available())
			{
				$content = $row['raw_content'];
			} else
			{
				$content = $row['content'];
			}
		}
		
		mysql_free_result($result);
		
		return $content;
	}
	
	function writeContent(&$content, $page_title)
	{
		global $connection;
		global $site;
		
		if (strcmp($content, '') === 0)
		{
			// empty content
			$query = 'DELETE FROM `static_pages` WHERE `page_name`=' . sqlSafeStringQuotes($page_title);
			if (!($result = @$site->execute_query('static_pages', $query, $connection)))
			{
				$site->dieAndEndPage('An error occured deleting content for page ' . $page_title . '!');
			}
			return;
		}
		
		$query = 'SELECT `id` FROM `static_pages` WHERE `page_name`=' . sqlSafeStringQuotes($page_title) . ' LIMIT 1';
		if (!($result = @$site->execute_query('static_pages', $query, $connection)))
		{
			$site->dieAndEndPage('An error occured getting content for page ' . $page_title . '!');
		}
		
		// number of rows
		$rows = (int) mysql_num_rows($result);
		$date_format = date('Y-m-d H:i:s');
		if ($rows < ((int) 1))
		{
			// no entry in table regarding current page
			// thus insert new data
			// getUserID() is a function from siteinfo.php that identifies the current user
			$query = ('INSERT INTO `static_pages` (`author`, `page_name`, `raw_content`, `content`, `last_modified`) VALUES ('
					  . sqlSafeStringQuotes(getUserID())
					  . ', ' . sqlSafeStringQuotes($page_title)
					  . ', ' . sqlSafeStringQuotes($content));
			if ($site->bbcode_lib_available())
			{
				$query .= ', ' . sqlSafeStringQuotes($site->bbcode($content));
			} else
			{
				$query .= ', ' . sqlSafeStringQuotes($content);
			}
			$query .= ', ' . sqlSafeStringQuotes($date_format) . ')';
		} else
		{
			// either 1 or more entries found, just assume there is only one
			$query = ('UPDATE `static_pages` SET `author`=' . sqlSafeStringQuotes(getUserID())
					  . ', `raw_content`=' . sqlSafeStringQuotes($content));
			if ($site->bbcode_lib_available())
			{
				$query .= ', `content`=' . sqlSafeStringQuotes($site->bbcode($content));
			} else
			{
				$query .= ', `content`=' . sqlSafeStringQuotes($content);
			}			
			$query .= ', `last_modified`=' . sqlSafeStringQuotes($date_format);
			$query .= ' WHERE `page_name`=' . sqlSafeStringQuotes($page_title);
			$query .= ' LIMIT 1';
		}
		
		if (!($result = @$site->execute_query('static_pages', $query, $connection)))
		{
			$site->dieAndEndPage('An error occured updating content for page ' . $page_title
								 . ' by user ' . sqlSafeString(getUserID()) . '!');
		}
	}