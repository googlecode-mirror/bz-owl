<?php
	class pmDisplay
	{
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
		
		function displayRecipient($recipient, $key, $team=false)
		{
			global $config;
			global $tmpl;
			global $db;
			
			if ($team)
			{
				$query = $db->prepare('SELECT `name` FROM `teams` WHERE `id`=?');
				$db->execute($query, $recipient);
				$recipientName = $db->fetchAll($query);
				$db->free($query);
				
				$tmpl->setVariable('MSG_ONE_RECIPIENT', '<a href="' . ($config->value('baseaddress')
																	   . 'Teams/?profile=' . htmlent($recipient)) . '">'
								   . $recipientName['0']['name'] . '</a>');
			} else
			{
				$query = $db->prepare('SELECT `name` FROM `players` WHERE `id`=?');
				$db->execute($query, $recipient);
				$recipientName = $db->fetchAll($query);
				$db->free($query);
				
				$tmpl->setVariable('MSG_ONE_RECIPIENT', '<a href="' . ($config->value('baseaddress')
																	   . 'Players/?profile=' . htmlent($recipient)) . '">'
								   . $recipientName['0']['name'] . '</a>');
			}
			
			$db->execute($query, $recipient);
			$recipientName = $db->fetchAll($query);
			$db->free($query);
			
			$tmpl->setVariable('MSG_ONE_RECIPIENT', '<a href="' . ($config->value('baseaddress')
																   . 'Players/?profile=' . htmlent($recipient)) . '">'
							   . $recipientName['0']['name'] . '</a>');
			
			$tmpl->parseCurrentBlock();
		}
		
		function folderNav($folder)
		{
			global $tmpl;
			
			// show currently selected mail folder
			if (strcmp($folder, 'inbox') === 0)
			{
				$tmpl->touchBlock('INBOX_SELECTED');
				$tmpl->touchBlock('OUTBOX_NOT_SELECTED');
			} else
			{
				$tmpl->touchBlock('INBOX_NOT_SELECTED');
				$tmpl->touchBlock('OUTBOX_SELECTED');
			}
		}
		
		function showMail($folder, $id)
		{
			global $config;
			global $tmpl;
			global $user;
			global $db;
			
			// set the template
			$tmpl->setTemplate('PMView');
			$tmpl->setTitle('Mail #' . $id);
			
			// show currently selected mail folder
			$this->folderNav($folder);			
			
			// collect the necessary data
			$query = $db->prepare('SELECT `subject`'
								  . ',IF(`messages_storage`.`author_id`<>0'
								  . ',(SELECT `name` FROM `players` WHERE `id`=`author_id`),?) AS `author`'
								  . ',IF(`messages_storage`.`author_id`<>0,'
								  . '(SELECT `status` FROM `players` WHERE `id`=`author_id`),'
								  . "''" . ') AS `author_status`'
								  . ',`author_id`,`timestamp`,`message`,`messages_storage`.`from_team`'
								  . ',`messages_storage`.`recipients`'
								  . ' FROM `messages_storage`,`messages_users_connection`'
								  . ' WHERE `messages_storage`.`id`=`messages_users_connection`.`msgid`'
								  . ' AND `messages_users_connection`.`playerid`=?'
								  . ' AND `messages_users_connection`.`in_' . $folder . '`=' . "'1'"
								  . ' AND `messages_storage`.`id`=? LIMIT 1');
			$db->execute($query, array($config->value('displaySystemUsername'), $user->getID(), $id));
			
			$rows = $db->fetchAll($query);
			$db->free($query);
			
			
			// create PM navigation
			$tmpl->setCurrentBlock('PMNAV');
			$query = $db->prepare('SELECT `msgid` FROM `messages_users_connection`'
								  . ' WHERE `playerid`=? AND `msgid`<?'
								  . ' AND `in_' . $folder . '`=' . "'1'"
								  . ' ORDER BY `id` DESC LIMIT 1');
			$db->execute($query, array($user->getID(), intval($_GET['view'])));
			$prevMSG = $db->fetchAll($query);
			$db->free($query);
			if (count($prevMSG) > 0)
			{
				$tmpl->setCurrentBlock('PREV_MSG');
				$tmpl->setVariable('MSGID', $prevMSG[0]['msgid']);
				$tmpl->parseCurrentBlock();
			}
			unset($prevMSG);
			
			$query = $db->prepare('SELECT `msgid` FROM `messages_users_connection`'
								  . ' WHERE `playerid`=? AND `msgid`>?'
								  . ' AND `in_' . $folder . '`=' . "'1'"
								  . ' ORDER BY `id` LIMIT 1');
			$db->execute($query, array($user->getID(), intval($_GET['view'])));
			$nextMSG = $db->fetchAll($query);
			$db->free($query);
			if (count($nextMSG) > 0)
			{
				$tmpl->setCurrentBlock('NEXT_MSG');
				$tmpl->setVariable('MSGID', $nextMSG[0]['msgid']);
				$tmpl->parseCurrentBlock();
			}
			unset($nextMSG);
//			$tmpl->setCurrentBlock('PMNAV');
//			$tmpl->parseCurrentBlock();
			
			
			// create PM view
			$tmpl->setCurrentBlock('PMVIEW');
			$tmpl->setVariable('PM_SUBJECT', $rows[0]['subject']);
			
			$tmpl->setVariable('PM_SUBJECT', $rows[0]['subject']);
			$tmpl->setVariable('PM_AUTHOR', $rows[0]['author']);
			
			// collect recipient list
			$tmpl->setCurrentBlock('MSG_RECIPIENTS');
			$recipients = explode(' ', $rows[0]['recipients']);
			$fromTeam = strcmp($rows[0]['from_team'], '0') !== 0;
			array_walk($recipients, 'self::displayRecipient', $fromTeam);
			
			
			// reply buttons
			$fromTeam = strcmp($rows[0]['from_team'], '0') !== 0;
			if ($fromTeam)
			{
				$tmpl->setCurrentBlock('REPLY_TEAM');
				$tmpl->setVariable('BASEADDRESS', $config->value('baseaddress'));
				$tmpl->setVariable('MSGID', intval($_GET['view']));
				$tmpl->setVariable('TEAMID', intval($recipients[0]));
				$tmpl->parseCurrentBlock();
			}
			
			$tmpl->setCurrentBlock('REPLY_PLAYERS');
			$tmpl->setVariable('BASEADDRESS', $config->value('baseaddress'));
			$tmpl->setVariable('MSGID', intval($_GET['view']));
			if (count($recipients) > 0)
			{
				$tmpl->setVariable('REPLY_PLAYER_OR_PLAYERS', 'players');
			} else
			{
				$tmpl->setVariable('REPLY_PLAYER_OR_PLAYERS', 'player');
			}
			$tmpl->parseCurrentBlock();
			
			
			// back to PMVIEW
			$tmpl->setCurrentBlock('PMVIEW');
			$tmpl->setVariable('PM_TIME', $rows[0]['timestamp']);
			$tmpl->setVariable('PM_CONTENT', $tmpl->encodeBBCode($rows[0]['message']));
			$tmpl->setVariable('BASEADDRESS', $config->value('baseaddress'));
			$tmpl->parseCurrentBlock();
			
			// mark the message as read for the current user
			$query = $db->prepare('UPDATE LOW_PRIORITY `messages_users_connection`'
								  . 'SET `msg_status`=' . "'read'"
								  . ' WHERE `msgid`=?'
								  . ' AND `in_' . $folder . '`=' . "'1'"
								  . ' AND `playerid`=?'
								  . ' LIMIT 1');
			$db->execute($query, array($id, $user->getID()));
		}
		
		function showMails($folder)
		{
			global $config;
			global $tmpl;
			global $user;
			global $db;
			
			// set the template
			$tmpl->setTemplate('PMList');
			
			if ($_SESSION['allow_add_messages'])
			{
				$tmpl->setCurrentBlock('USERBUTTONS');
				$tmpl->setVariable('PERMISSION_BASED_BUTTONS', '<a class="button" href="./?add">New Mail</a>');
				$tmpl->parseCurrentBlock();
			}
			
			// show currently selected mail folder
			$this->folderNav($folder);
			
			// show the overview
			$offset = 0;
			if (isset($_GET['i']))
			{
				$offset = intval($_GET['i']);
			}
			$query = $db->prepare('SELECT `messages_users_connection`.`msgid`'
								  . ',`messages_storage`.`author_id`'
								  . ',IF(`messages_storage`.`author_id`<>0,(SELECT `name` FROM `players` WHERE `id`=`author_id`)'
								  . ',?) AS `author`'
								  . ',IF(`messages_storage`.`author_id`<>0,(SELECT `status` FROM `players` WHERE `id`=`author_id`),'
								  . "'" . "'" . ') AS `author_status`'
								  . ',`messages_users_connection`.`msg_status`'
								  . ',`messages_storage`.`subject`'
								  . ',`messages_storage`.`timestamp`'
								  . ',`messages_storage`.`from_team`'
								  . ',`messages_storage`.`recipients`'
								  . ' FROM `messages_users_connection`,`messages_storage`'
								  . ' WHERE `messages_storage`.`id`=`messages_users_connection`.`msgid`'
								  . ' AND `messages_users_connection`.`playerid`=?'
								  . ' AND `in_' . $folder . '`=' . "'1'"
								  . ' ORDER BY `messages_users_connection`.`id` DESC'
								  . ' LIMIT ' . $offset . ', 201');
			$db->execute($query, array($config->value('displayedSystemUsername'), $user->getID()));
			$rows = $db->fetchAll($query);
			$db->free($query);
			
			$n = count($rows);
			// last row is only a lookup row
			// to find out whether to display next messages button
			$showNextMSGButton = false;
			if ($n > 200)
			{
				$n = 200;
				$showNextMSGButton = true;
			}
			if ($n > 0)
			{
				$tmpl->setCurrentBlock('PMLIST');
				for ($i = 0; $i < $n; $i++)
				{
					$tmpl->setVariable('USER_PROFILE_LINK', ($config->value('baseaddress')
									  . 'Players/?profile=' . $rows[$i]['author_id']));
					$tmpl->setVariable('USER_NAME', $rows[$i]['author']);
					$tmpl->setVariable('MSG_LINK', ($config->value('baseaddress')
									  . 'Messages/?view=' . $rows[$i]['msgid']));
					$tmpl->setVariable('MSG_SUBJECT', $rows[$i]['subject']);
					$tmpl->setVariable('MSG_TIME', $rows[$i]['timestamp']);
					
					// collect recipient list
					$tmpl->setCurrentBlock('MSG_RECIPIENTS');
					$recipients = explode(' ', $rows[$i]['recipients']);
					$fromTeam = strcmp($rows[$i]['from_team'], '0') !== 0;
					array_walk($recipients, 'self::displayRecipient', $fromTeam);
					$tmpl->parseCurrentBlock();
					
					
					// back to PMLIST
					$tmpl->setCurrentBlock('PMLIST');
					$tmpl->parseCurrentBlock();
				}
				
				if ($offset > 0 || $showNextMSGButton)
				{
					$tmpl->setCurrentBlock('PM_NAV_BUTTONS');
					if ($offset > 0)
					{
						// show previous messages
						$tmpl->setCurrentBlock('PREV_BUTTON');
						$tmpl->setVariable('MSG_FOLDER', $folder);
						$tmpl->setVariable('OFFSET_PREV', intval($offset-200));
						$tmpl->parseCurrentBlock();
					}
					if ($showNextMSGButton)
					{
						// show next messages
						$tmpl->setCurrentBlock('NEXT_BUTTON');
						$tmpl->setVariable('MSG_FOLDER', $folder);
						$tmpl->setVariable('OFFSET_NEXT', strval($offset+200));
						$tmpl->parseCurrentBlock();
					}
					$tmpl->setCurrentBlock('PM_NAV_BUTTONS');
					$tmpl->parseCurrentBlock();
				}
			} elseif ($offset > 0)
			{
				$tmpl->addMSG('No additional messages in ' . htmlent($folder) . 'found.');
			} else
			{
				$tmpl->addMSG('No messages in ' . htmlent($folder) . '.');
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
	}
?>