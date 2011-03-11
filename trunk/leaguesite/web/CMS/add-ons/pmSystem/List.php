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
		
		function displayRecipient(&$recipient, $key, array &$values)
		{
			global $config;
			global $tmpl;
			global $db;
			
			
			// $values: array($fromTeam, $queryTeamName, $queryPlayerName, $n)
			$recipientID = intval($recipient);
			$recipient = array();
			if ($values[0])
			{
				$db->execute($values[1], $recipientID);
				$recipientName = $db->fetchAll($values[1]);
				$db->free($values[1]);
				
				if (count($recipientName) < 1)
				{
					$recipientName['0']['name'] = 'ERROR: Could not find out team name';
				}
				$recipient['link'] = ($config->value('baseaddress') . 'Teams/?profile='
									  . htmlent($recipientID));
			} else
			{
				$db->execute($values[2], $recipientID);
				$recipientName = $db->fetchAll($values[2]);
				$db->free($values[2]);
				
				if (count($recipientName) < 1)
				{
					$recipientName['0']['name'] = 'ERROR: Could not find out player name';
				}
				
				$recipient['link'] = ($config->value('baseaddress') . 'Players/?profile='
									  . htmlent($recipientID));
			}
			
			$recipient['name'] = $recipientName['0']['name'];
			$recipient['seperator'] = $values[3] > $key ? true : false;
		}
		
		function folderNav($folder)
		{
			global $tmpl;
			
			// show currently selected mail folder
			$tmpl->assign('curFolder', $folder);
/*
			
			if (strcmp($folder, 'inbox') === 0)
			{
				$tmpl->touchBlock('INBOX_SELECTED');
				$tmpl->touchBlock('OUTBOX_NOT_SELECTED');
			} else
			{
				$tmpl->touchBlock('INBOX_NOT_SELECTED');
				$tmpl->touchBlock('OUTBOX_SELECTED');
			}
*/
		}
		
		function showMail($folder, $id)
		{
			global $config;
			global $tmpl;
			global $user;
			global $db;
			
			// set the template
			$tmpl->setTemplate('PMView');
			$tmpl->assign('title', 'Mail #' . $id);
			
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
			$query = $db->prepare('SELECT `msgid` FROM `messages_users_connection`'
								  . ' WHERE `playerid`=? AND `msgid`<?'
								  . ' AND `in_' . $folder . '`=' . "'1'"
								  . ' ORDER BY `id` DESC LIMIT 1');
			$db->execute($query, array($user->getID(), intval($_GET['view'])));
			$prevMSG = $db->fetchAll($query);
			$db->free($query);
			
			if (count($prevMSG) > 0)
			{
				$tmpl->assign('prevMsg', $prevMSG[0]['msgid']);
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
				$tmpl->assign('nextMsg', $nextMSG[0]['msgid']);
			}
			unset($nextMSG);
			
			if (count($rows) < 1)
			{
				// keep the error message generic to avoid
				$tmpl->assign('errorMsg', 'This message either does not exist or you do not have permission to view the message.');
				$tmpl->display('NoPerm');
			}
			
			// create PM view
			$tmpl->assign('subject', $rows[0]['subject']);
			$authorLink = ($rows[0]['from_team']) ?
						   '../Teams/?profile=' . intval($rows[0]['author_id'])
						   :
						   '../Players/?profile=' . intval($rows[0]['author_id']);
						   
			$tmpl->assign('authorLink', $authorLink);
			$tmpl->assign('authorName', $rows[0]['author']);
			$tmpl->assign('time', $rows[0]['timestamp']);
			$tmpl->assign('content', $tmpl->encodeBBCode($rows[0]['message']));
			
			// collect recipient list
			$recipients = explode(' ', $rows[0]['recipients']);
			$fromTeam = strcmp($rows[0]['from_team'], '0') !== 0;
			$queryTeamName = $db->prepare('SELECT `name` FROM `teams` WHERE `id`=?');
			$queryPlayerName = $db->prepare('SELECT `name` FROM `players` WHERE `id`=?');
			$countRecipients = count($recipients) -1;
			array_walk($recipients, 'self::displayRecipient'
					   , array($fromTeam, $queryTeamName, $queryPlayerName, $countRecipients));
			$tmpl->assign('recipients', $recipients);
			
			// reply buttons
			$fromTeam = strcmp($rows[0]['from_team'], '0') !== 0;
			if ($fromTeam)
			{
				$tmpl->assign('teamID', intval($recipients[0]));
/*
				$tmpl->setVariable('BASEADDRESS', $config->value('baseaddress'));
				$tmpl->setVariable('MSGID', intval($_GET['view']));
				$tmpl->setVariable('TEAMID', intval($recipients[0]));
				$tmpl->parseCurrentBlock();
*/
			}
			
			
			$tmpl->assign('msgID', intval($_GET['view']));
/*
			if (count($recipients) > 1)
			{
				$tmpl->setVariable('REPLY_PLAYER_OR_PLAYERS', 'players');
			} else
			{
				$tmpl->setVariable('REPLY_PLAYER_OR_PLAYERS', 'player');
			}
			$tmpl->parseCurrentBlock();
*/
			
			
/*
			$tmpl->setCurrentBlock('PMVIEW');
			$tmpl->setVariable('PM_TIME', $rows[0]['timestamp']);
			$tmpl->setVariable('PM_CONTENT', $tmpl->encodeBBCode($rows[0]['message']));
			$tmpl->setVariable('BASEADDRESS', $config->value('baseaddress'));
			$tmpl->setVariable('MSGID', $id);
			$tmpl->setVariable('MSG_FOLDER', $folder);
			$tmpl->parseCurrentBlock();
*/
			
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
				$tmpl->assign('showNewButton', true);
			}
			
			// show currently selected mail folder
			$this->folderNav($folder);
			
			// show the overview
			$offset = 0;
			if (isset($_GET['i']))
			{
				$offset = intval($_GET['i']);
			}
			$query = $db->prepare('SELECT `messages_storage`.`id`'
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
				$messages = array();
				for ($i = 0; $i < $n; $i++)
				{
					$messages[$i]['userProfile'] = ($config->value('baseaddress')
													. 'Players/?profile=' . $rows[$i]['author_id']);
					$messages[$i]['userName'] = $rows[$i]['author'];
					
					if (strcmp($rows[$i]['msg_status'], 'new') === 0)
					{
						$messages[$i]['unread'] = true;
					}
					if (strcmp($folder, 'inbox') !== 0)
					{
						$messages[$i]['link'] = '?view=' . $rows[$i]['id'] . '&amp;folder=' . $folder;
					} else
					{
						$messages[$i]['link'] = '?view=' . $rows[$i]['id'];
					}
					$messages[$i]['subject'] = $rows[$i]['subject'];
					$messages[$i]['time'] = $rows[$i]['timestamp'];
					
					// collect recipient list
					$recipients = explode(' ', $rows[$i]['recipients']);
					$fromTeam = strcmp($rows[$i]['from_team'], '0') !== 0;
					$queryTeamName = $db->prepare('SELECT `name` FROM `teams` WHERE `id`=?');
					$queryPlayerName = $db->prepare('SELECT `name` FROM `players` WHERE `id`=?');
					$countRecipients = count($recipients) -1;
					array_walk($recipients, 'self::displayRecipient'
							   , array($fromTeam, $queryTeamName, $queryPlayerName, $countRecipients));
					$messages[$i]['recipients'] = $recipients;

				}
				$tmpl->assign('messages', $messages);
								
				if ($offset > 0 || $showNextMSGButton)
				{
					if ($offset > 0)
					{
						// show previous messages
						$tmpl->assign('offsetPrev', intval($offset-200));
					}
					if ($showNextMSGButton)
					{
						// show next messages
						$tmpl->assign('offsetNext', strval($offset+200));
					}
				}
			} elseif ($offset > 0)
			{
/* 				$tmpl->addMSG('No additional messages in ' . htmlent($folder) . 'found.'); */
			} else
			{
/* 				$tmpl->addMSG('No messages in ' . htmlent($folder) . '.'); */
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
