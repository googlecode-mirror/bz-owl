<?php
	class newsSystem
	{
		function __construct($title, $path)
		{
			global $site;
			global $tmpl;
			global $user;
			
			if (!isset($site))
			{
				require_once (dirname(dirname(dirname(__FILE__))) . '/site.php');
				$site = new site();
			}
			
			// FIXME: fallback to default permission name until add-on system is completly implemented
			$entry_edit_permission = 'allow_edit_news';
			
			$tmpl->setTitle($title);
			
			// find out which template should be used
			// fallback template is static
			$templateToUse = 'News';
			
			// use suggested name in $page_title
			if (isset($path))
			{
				if (strcmp($path, '/') === 0)
				{
					// force call this template home
					$templateToUse = 'Home';
				} else
				{
					$templateToUse = $title;
				}
				
				// revert back to default if file does not exist
				if (!(file_exists(dirname(dirname(dirname(__FILE__))) . '/styles/'
								  . str_replace(' ', '%20', htmlspecialchars($user->getStyle())) . '/'
								  . $templateToUse . '.tmpl.html')))
				{
					$templateToUse = 'News';
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
				$tmpl->setTemplate($templateToUse . '?edit');
			} else
			{
				$tmpl->setTemplate($templateToUse);
				$this->readContent($path, $author, $last_modified, false);
/* 				$tmpl->addMSG($this->readContent($path, $author, $last_modified, false)); */
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
					// initialise variables
					$confirmed = 0;
					$content = '';
					
					// set their values in case the POST variables are set
					if (isset($_POST['confirmationStep']))
					{
						$confirmed = intval($_POST['confirmationStep']);
					}
					if (isset($_POST['editPageAgain']) && strlen($_POST['editPageAgain']) > 0)
					{
						// user looked at preview but chose to edit the message again
						$confirmed = 0;
					}
					if (isset($_POST['staticContent']))
					{
						$content = htmlspecialchars_decode($_POST['staticContent'], ENT_COMPAT);
					}
					
					// sanity check variabless
					$test = $this->sanityCheck($confirmed);
					switch ($test)
					{
							// use bbcode if available
						case (true && $confirmed === 1 && $config->value('bbcodeLibAvailable')):
							$this->insertEditText(true);
							$tmpl->addMSG($tmpl->encodeBBCode($content));
							break;
							
							// else raw output
						case (true && $confirmed === 1 && !$config->value('bbcodeLibAvailable')):
							$this->insertEditText(true);
							$tmpl->addMSG($content);
							break;
							
							// use this as guard to prevent selection of noperm or nokeymatch cases
						case (strlen($test) < 2):
							$this->insertEditText(false);
							break;
							
						case 'noperm':
							$tmpl->addMSG('You need write permission to edit the content.');
							break;
							
						case 'nokeymatch':
							$this->insertEditText(false);
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
							$this->writeContent($content, $page_title);
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
		}
		
		
		function sanityCheck(&$confirmed)
		{
			global $tmpl;
			
			if (!$this->hasEditPermission())
			{			
				// editing cancelled due to missing user permission
				$confirmed = 0;
				return 'noperm';
			}
			
			// no need to check for a key match if no content was supplied
			if (($confirmed > 0) && !$this->randomKeyMatch($confirmed))
			{
				// editing cancelled due to random key mismatch
				$confirmed = 0;
				return 'nokeymatch';
			}
			
			return true;
		}
		
		function insertEditText($readonly=false)
		{
			global $tmpl;
			global $page_title;
			global $author;
			global $last_modified;
			
			$content = '';
			if (isset($_POST['staticContent']))
			{
				$content = $_POST['staticContent'];
			} else
			{
				$content = $this->readContent($page_title, $author, $last_modified, true);
			}
			
			switch($readonly)
			{
				case true:
					$tmpl->setCurrentBlock('EDIT_HIDDEN_WHILE_PREVIEW');
					$tmpl->setVariable('RAW_CONTENT_HERE',  htmlspecialchars($content, ENT_COMPAT, 'UTF-8'));
					$tmpl->parseCurrentBlock();
					break;
					
				default:
					$tmpl->touchBlock('EDIT_AREA');
					$tmpl->setCurrentBlock('USER_ENTERED_CONTENT');
					$tmpl->setVariable('RAW_CONTENT_HERE', htmlspecialchars($content, ENT_COMPAT, 'UTF-8'));
					$tmpl->parseCurrentBlock();
					$this->editor();
					break;
			}
		}
		
		function editor()
		{
			global $config;
			global $tmpl;
			
			$tmpl->setCurrentBlock('USER_NOTE');
			
			if ($config->value('bbcodeLibAvailable'))
			{
				$tmpl->setVariable('EDIT_MODE_NOTE', 'Keep in mind to use BBCode instead of HTML or XHTML.');
				$tmpl->parseCurrentBlock();
				
				include dirname(dirname(__FILE__)) . '/bbcode_buttons.php';
				$bbcode = new bbcode_buttons();
				
				$buttons = $bbcode->showBBCodeButtons('staticContent');
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
				if ($config->value('useXhtml'))
				{
					$tmpl->setVariable('EDIT_MODE_NOTE', 'Keep in mind the home page currently uses XHTML, not HTML or BBCode.');
				} else
				{
					$tmpl->setVariable('EDIT_MODE_NOTE', 'Keep in mind the home page currently uses HTML, not XHTML or BBCode.');
				}
				$tmpl->parseCurrentBlock();
			}	
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
		
		function readContent($path, &$author, &$last_modified, $raw=false)
		{
			global $site;
			global $tmpl;
			global $db;
			
			// initialise return variable so any returned value will be always in a defined state
			$content = '';
			$offset = 0;
			if (!$raw)
			{
				$content = 'No content available yet.';
				$query = $db->SQL('SELECT `title`,`timestamp`,`author`,`msg`'
								  . ' FROM `news` ORDER BY `timestamp` DESC'
								  . ' LIMIT ' . intval($offset) .', 21');
			} else
			{
				$query = $db->SQL('SELECT `title`,`timestamp`,`author`,`raw_msg`'
								  . ' FROM `news` ORDER BY `timestamp` DESC'
								  . ' LIMIT ' . intval($offset) .', 21');
			}
			$db->execute($query, $offset);
			$rows = $db->fetchAll($query);
			$db->free($query);
			
			// process query result array
			$n = count($rows);
			if ($n > 0)
			{
				$tmpl->setCurrentBlock('NEWSBOX');
				
				for($i = 1; $i < $n; $i++)
				{
					$tmpl->setVariable('TITLE', (strcmp($rows[$i]['title'], '') === 0) ? 'News' : $rows[$i]['title']);
					$tmpl->setVariable('AUTHOR', $rows[$i]['author']);
					$tmpl->setVariable('TIME', $rows[$i]['timestamp']);
					
					$tmpl->setVariable('CONTENT', $rows[$i]['msg']);
				$author = $rows[$i]['author'];
				if ($raw)
				{
					$content = $rows[$i]['raw_msg'];
				} else
					{
					$content = $rows[$i]['msg'];
					}
					
					$tmpl->parseCurrentBlock();
				}
			}
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
			$db->execute($query, $page_title);
			
			// number of rows
			$rows = $db->rowCount($query);
			$date_format = date('Y-m-d H:i:s');
			if ($rows < ((int) 1))
			{
				// no entry in table regarding current page
				// thus insert new data
				$query = ('INSERT INTO `static_pages` (`author`, `page_name`, `raw_content`, `content`, `last_modified`) VALUES ('
						  . $db->quote($user->getID())
						  . ', ' . $db->quote($page_title)
						  . ', ' . $db->quote($content));
				if ($config->value('bbcodeLibAvailable'))
				{
					$query .= ', ' . $db->quote($tmpl->encodeBBCode($content));
				} else
				{
					$query .= ', ' . $db->quote($content);
				}
				$query .= ', ' . $db->quote($date_format) . ')';
				
				//			$db->prepare($query);
				//			$db->execute();
			} else
			{
				// either 1 or more entries found, just assume there is only one
				$query = ('UPDATE `static_pages` SET `author`=' . $db->quote($user->getID())
						  . ', `raw_content`=' . $db->quote($content));
				if ($config->value('bbcodeLibAvailable'))
				{
					$query .= ', `content`=' . $db->quote($tmpl->encodeBBCode($content));
				} else
				{
					$query .= ', `content`=' . $db->quote($content);
				}			
				$query .= ', `last_modified`=' . $db->quote($date_format);
				$query .= ' WHERE `page_name`=' . $db->quote($page_title);
				$query .= ' LIMIT 1';
			}
			
			$result = $db->SQL($query);
		}
	}
?>