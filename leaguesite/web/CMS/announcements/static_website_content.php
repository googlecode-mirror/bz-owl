<?php
	function sanityCheck(&$confirmed)
	{
		global $tmpl;
		
		if (!hasEditPermission())
		{			
			// editing cancelled due to missing user permission
			$confirmed = 0;
			return 'noperm';
		}
		
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
	
	require_once (dirname(dirname(__FILE__)) . '/site.php');
	$site = new site();
	
	
	// find out which template should be used
	// fallback template is static
	$templateToUse = 'static';
	
	// use suggested name in $page_title
	if (isset($page_title))
	{
		if (strcmp($page_title, '_/') === 0)
		{
			// force call this template home
			$templateToUse = 'Home';
		} else
		{
			$templateToUse = $page_title;
		}
		
		// revert back to default if file does not exist
		if (!(file_exists(dirname(dirname(dirname(__FILE__))) . '/styles/'
						  . str_replace(' ', '%20', htmlspecialchars($user->getStyle())) . '/'
										. $templateToUse . '.tmpl.html')))
		{
			$templateToUse = 'static';
		}
	}
	
	
	
	// otherwise we'd need a custom name per setup
	// as top level dir could be named different
	
	if (isset($_GET['edit']))
	{
		// remove the slashes if magic quotes are sadly on
		if ($site->magic_quotes_on())
		{
			stripslashes($_POST);
		}
		$tmpl = new template($templateToUse . '?edit');
		$tmpl->setCurrentBlock('USER_ENTERED_CONTENT');
		if (isset($_POST['staticContent']))
		{
			$tmpl->setVariable('RAW_CONTENT_HERE', $_POST['staticContent']);
		} else
		{
			$tmpl->setVariable('RAW_CONTENT_HERE', readContent($page_title, $author, $last_modified, true));
		}
		$tmpl->parseCurrentBlock();
	} else
	{
		$tmpl = new template($templateToUse);
		$tmpl->addMSG(readContent($page_title, $author, $last_modified, false));
	}
	
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
			
			if ($config->value('bbcodeLibAvailable'))
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
			if (isset($_POST['editPageAgain']) && ($_POST['editPageAgain'] === 1))
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
				case (true && $confirmed === 1 && $config->value('bbcodeLibAvailable')):
					$tmpl->addMSG($tmpl->bbcode($content));
					break;
					
				// else raw output
				case (true && $confirmed === 1 && !$config->value('bbcodeLibAvailable')):
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
			
			
			// there is no step lower than 0
			if ($confirmed < 0)
			{
				$confirmed = 0;
			}
			
			// increase confirmation step by one so we get to the next level
			$tmpl->setCurrentBlock('PREVIEW_VALUE');
			if ($confirmed > 1)
			{
				$tmpl->setVariable('PREVIEW_VALUE_HERE', 1);
			} else
			{
				$tmpl->setVariable('PREVIEW_VALUE_HERE', $confirmed+1);
			}
			$tmpl->parseCurrentBlock();
			
			switch ($confirmed)
			{
				case 1:
					$tmpl->setCurrentBlock('FORM_BUTTON');
					$tmpl->setVariable('SUBMIT_BUTTON_TEXT', 'Write changes');
					$tmpl->parseCurrentBlock();
					// user may decide not to submit after seeing preview
					$tmpl->setCurrentBlock('EDIT_AGAIN');
					$tmpl->setVariable('EDIT_AGAIN_BUTTON_TEXT', 'Edit again');
					$tmpl->parseCurrentBlock();
					break;
				
				case 2:
					writeContent($content, $page_title);
					$tmpl->addMSG('Changes written successfully.' . $tmpl->linebreaks("\n\n"));
				
				default:
					$tmpl->setCurrentBlock('FORM_BUTTON');
					$tmpl->setVariable('SUBMIT_BUTTON_TEXT', 'Preview');
					$tmpl->parseCurrentBlock();
			}
			
			
			$randomKeyName = $randomkey_name . microtime();
			// convert some special chars to underscores
			$randomKeyName = strtr($randomKeyName, array(' ' => '_', '.' => '_'));
			$randomkeyValue = $site->setKey($randomKeyName);
			$tmpl->setCurrentBlock('KEY');
			$tmpl->setVariable('KEY_NAME', $randomKeyName);
			$tmpl->setVariable('KEY_VALUE', urlencode($_SESSION[$randomKeyName]));
			$tmpl->parseCurrentBlock();
		}
	}
	
	
	// done, render page
	$tmpl->render();
	
	function readContent($page_title, &$author, &$last_modified, $raw=false)
	{
		global $site;
		global $db;
		global $config;
		
		// initialise return variable so any returned value will be always in a defined state
		$content = '';
		if (!$raw)
		{
			$content = 'No content available yet.';
		}
		
		$query = $db->prepare('SELECT * FROM `static_pages` WHERE `page_name`=? LIMIT 1');
		$result = $db->execute($query, $page_title);
		
		// process query result array
		while ($row = $db->fetchNextRow($query))
		{
			$author = $row['author'];
			$last_modified = $row['last_modified'];
			if ($raw && $config->value('bbcodeLibAvailable'))
			{
				$content = $row['raw_content'];
			} else
			{
				$content = $row['content'];
			}
		}
		
		return $content;
	}
	
	function writeContent(&$content, $page_title)
	{
		global $site;
		global $config;
		global $user;
		global $tmpl;
		global $db;
		
		if (strcmp($content, '') === 0)
		{
			// empty content
			$query = $db->prepare('DELETE FROM `static_pages` WHERE `page_name`=?');
			$db->execute($query, $page_title);
			return;
		}
		
		$query = $db->prepare('SELECT `id` FROM `static_pages` WHERE `page_name`=? LIMIT 1');
		$result = $db->execute($query, $page_title);
		
		// number of rows
		$rows = (int) mysql_num_rows($result);
		$date_format = date('Y-m-d H:i:s');
		if ($rows < ((int) 1))
		{
			// no entry in table regarding current page
			// thus insert new data
			$query = ('INSERT INTO `static_pages` (`author`, `page_name`, `raw_content`, `content`, `last_modified`) VALUES ('
					  . $db->escape($user->getID())
					  . ', ' . $db->escape($page_title)
					  . ', ' . $db->escape($content));
			if ($config->value('bbcodeLibAvailable'))
			{
				$query .= ', ' . $db->escape($tmpl->bbcode($content));
			} else
			{
				$query .= ', ' . $db->escape($content);
			}
			$query .= ', ' . $db->escape($date_format) . ')';
			
//			$db->prepare($query);
//			$db->execute();
		} else
		{
			// either 1 or more entries found, just assume there is only one
			$query = ('UPDATE `static_pages` SET `author`=' . $db->escape($user->getID())
					  . ', `raw_content`=' . $db->escape($content));
			if ($config->value('bbcodeLibAvailable'))
			{
				$query .= ', `content`=' . $db->escape($tmpl->bbcode($content));
			} else
			{
				$query .= ', `content`=' . $db->escape($content);
			}			
			$query .= ', `last_modified`=' . $db->escape($date_format);
			$query .= ' WHERE `page_name`=' . $db->escape($page_title);
			$query .= ' LIMIT 1';
		}
		
		$result = $db->SQL($query);
	}