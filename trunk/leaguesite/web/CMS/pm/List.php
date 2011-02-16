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
				$tmpl->setVariable('MSG_ONE_RECIPIENT_LINK', ($config->value('baseaddress')
															  . 'Teams/?profile=' . htmlent($recipient)));
			} else
			{
				$query = $db->prepare('SELECT `name` FROM `players` WHERE `id`=?');
				$tmpl->setVariable('MSG_ONE_RECIPIENT_LINK', ($config->value('baseaddress')
															  . 'Players/?profile=' . htmlent($recipient)));
			}
			
			$db->execute($query, $recipient);
			$recipientName = $db->fetchAll($query);
			$db->free($query);
			
			$tmpl->setVariable('MSG_ONE_RECIPIENT', $recipientName['0']['name']);
			
			$tmpl->parseCurrentBlock();
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
			$tmpl->setCurrentBlock('PMVIEW');
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
			
			// show the overview
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
								  . ' ORDER BY `messages_users_connection`.`id` DESC');
			$db->execute($query, array($config->value('displayedSystemUsername'), $user->getID()));
			
			$tmpl->setCurrentBlock('PMLIST');
			while ($row = $db->fetchRow($query))
			{
				$tmpl->setVariable('USER_PROFILE_LINK', ($config->value('baseaddress') . 'Players/?profile=' . $row['author_id']));
				$tmpl->setVariable('USER_NAME', $row['author']);
				$tmpl->setVariable('MSG_LINK', ($config->value('baseaddress') . 'Messages/?view=' . $row['msgid']));
				$tmpl->setVariable('MSG_SUBJECT', $row['subject']);
				$tmpl->setVariable('MSG_TIME', $row['timestamp']);
				
				// collect recipient list
				$tmpl->setCurrentBlock('MSG_RECIPIENTS');
				$recipients = explode(' ', $row['recipients']);
				$fromTeam = strcmp($row['from_team'], '0') !== 0;
				array_walk($recipients, 'self::displayRecipient', $fromTeam);
				
				// back to PMLIST
				$tmpl->setCurrentBlock('PMLIST');
				$tmpl->parseCurrentBlock();
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