<?php
	class newsSystem
	{
		function __construct($title, $path)
		{
			global $entry_edit_permission;
			global $entry_delete_permission;
			global $site;
			global $tmpl;
			global $user;
			
			if (!isset($site))
			{
				require_once (dirname(dirname(dirname(__FILE__))) . '/site.php');
				$site = new site();
			}
			
			// FIXME: fallback to default permission name until add-on system is completly implemented
			$entry_add_permission = 'allow_add_news';
			$entry_edit_permission = 'allow_edit_news';
			$entry_delete_permission = 'allow_delete_news';
			
			
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
			
			if ($user->hasPermission($entry_edit_permission) && isset($_GET['edit']))
			{
				// remove the slashes if magic quotes are sadly on
				if ($site->magic_quotes_on())
				{
					stripslashes($_POST);
				}
				if (!$tmpl->setTemplate($templateToUse . '?edit'))
				{
					$tmpl->noTemplateFound();
				}
				include(dirname(dirname(dirname(__FILE__))) . '/classes/editor.php');
				$editor = new editor($this);
				$editor->edit();
				$tmpl->render();
				die();
			}
			
			
			if ($user->hasPermission($entry_add_permission) && isset($_GET['add']))
			{
				// user has permission to add news to the page and requests it
				$tmpl->setTemplate($templateToUse . '?edit');
				$this->add();
				$tmpl->render();
				die();
			}
			
			// user looks at page in read mode
			$tmpl->setTemplate($templateToUse);
			
			$tmpl->setCurrentBlock('USERADDBUTTON');
			$tmpl->setVariable('PERMISSION_BASED_ADD_BUTTON',
							   '<a href="./?add" class="button">Add message</a>');
			$tmpl->parseCurrentBlock();
			$this->readContent($path, $author, $last_modified, false);
			
			// done, render page
			$tmpl->render();
		}
		
		
		function add()
		{
			echo('blub');
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
			global $editor;
			
			if (isset($_POST['staticContent']))
			{
				$content = $_POST['staticContent'];
			} else
			{
				$content = $this->readContent($page_title, $author, $last_modified, true);
			}
			
			print_r($content);
			
			switch($readonly)
			{
				case true:
					$tmpl->setCurrentBlock('EDIT_HIDDEN_WHILE_PREVIEW');
					$tmpl->setVariable('RAW_CONTENT_HERE',  htmlspecialchars($content['raw_msg']
																			 , ENT_COMPAT, 'UTF-8'));
					$tmpl->parseCurrentBlock();
					break;
				
				default:
					$tmpl->setCurrentBlock('USER_ENTERED_CONTENT');
					$tmpl->setVariable('RAW_CONTENT_HERE', htmlspecialchars($content['raw_msg']
																			, ENT_COMPAT, 'UTF-8'));
					$tmpl->setCurrentBlock('EDIT_AREA');
					$tmpl->setVariable('TIMESTAMP', htmlspecialchars($content['timestamp']
																	, ENT_COMPAT, 'UTF-8'));
					$tmpl->setVariable('TITLE', htmlspecialchars($content['title']
																 , ENT_COMPAT, 'UTF-8'));
					$tmpl->parseCurrentBlock();
					break;
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
		
		function readContent($path, &$author, &$last_modified, $edit=false)
		{
			global $entry_edit_permission;
			global $entry_delete_permission;
			global $tmpl;
			global $user;
			global $db;
			
			// initialise return variable so any returned value will be always in a defined state
			$content = '';
			$offset = 0;
			if (!$edit)
			{
				$content = 'No content available yet.';
				$query = $db->SQL('SELECT `title`,`timestamp`,`author`,`msg`'
								  . ' FROM `news` ORDER BY `timestamp` DESC'
								  . ' LIMIT ' . intval($offset) .', 21');
			} else
			{
				$query = $db->SQL('SELECT `title`,`timestamp`,`author`,`raw_msg`'
								  . ' FROM `news` ORDER BY `timestamp` DESC'
								  . ' LIMIT ' . intval($offset) .', 1');
			}
			$db->execute($query, $offset);
			$rows = $db->fetchAll($query);
			$db->free($query);
			
			if ($edit)
			{
				// let the edit insertion function pass it to the editor class
				return $rows[0];
			}
			
			// process query result array
			$n = count($rows);
			if ($n > 0)
			{
				$showButtons = false;
				if (!$edit
					&& $user->hasPermission($entry_edit_permission)
					|| $user->hasPermission($entry_delete_permission))
				{
					$buttons = ($user->hasPermission($entry_edit_permission))?
								'<a class="button" href="./?edit">edit</a>' : '';
					$buttons .= ($user->hasPermission($entry_edit_permission)
								 && $user->hasPermission($entry_delete_permission))?
								' ' : '';
					$buttons .= ($user->hasPermission($entry_delete_permission))?
								'<a class="button" href="./?delete">delete</a>' : '';
				}
				if (isset($buttons))
				{
					$showButtons = true;
				}
				
				$tmpl->setCurrentBlock('NEWSBOX');
				for($i = 1; $i < $n; $i++)
				{
					if ($showButtons)
					{
						$tmpl->setCurrentBlock('USERBUTTONS');
						$tmpl->setVariable('PERMISSION_BASED_BUTTONS', $buttons);
						$tmpl->parseCurrentBlock();
						$tmpl->setCurrentBlock('NEWSBOX');
					}
					
					$tmpl->setVariable('TITLE', (strcmp($rows[$i]['title'], '') === 0)?
									   'News' : $rows[$i]['title']);
					$tmpl->setVariable('AUTHOR', $rows[$i]['author']);
					$tmpl->setVariable('TIME', $rows[$i]['timestamp']);
					
					$edit ? $tmpl->setVariable('CONTENT', $rows[$i]['raw_msg'])
						 : $tmpl->setVariable('CONTENT', $rows[$i]['msg']);
					$author = $rows[$i]['author'];
					if ($edit)
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
		
		function writeContent(&$content, $page_title, $table)
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
				$query = $db->prepare('INSERT INTO `?`'
									  . ' (`author`, `page_name`, `raw_content`, `content`, `last_modified`)'
									  . ' VALUES (?, ?, ?, ?, ?)');
				$args = array($table, $user->getID(), $page_title, $content);
				if ($config->value('bbcodeLibAvailable'))
				{
					$args[] = $tmpl->encodeBBCode($content);
				} else
				{
					$args[] .= $db->quote($content);
				}
				$args[] = $date_format;
				
				//			$db->prepare($query);
				//			$db->execute();
			} else
			{
				// either 1 or more entries found, just assume there is only one
				$query = $db->prepare('UPDATE `?` SET `author`?=' . $db->quote($user->getID())
									  . ', `raw_content`=?' . $db->quote($content)
									  . ', `content`=?'
									  . ', `last_modified`=?'
									  . ' WHERE `page_name`=?'
									  . ' LIMIT 1');
				$args = array($table, $user->getID(), $content);
				if ($config->value('bbcodeLibAvailable'))
				{
					$args[] = $tmpl->encodeBBCode($content);
				} else
				{
					$args[] = $db->quote($content);
				}
				$ags[] = $date_format;
				$args[] = $page_title;
			}
			
			$result = $db->execute($query, $args);
		}
	}
?>