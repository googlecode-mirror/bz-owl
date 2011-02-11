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
	
	function displayRecipient($recipient, $fromTeam=false)
	{
		global $tmpl;
		global $db;
		
		if ($fromTeam)
		{
			$query = $db->prepare('SELECT `name` FROM `teams` WHERE `id`=?');
		} else
		{
			$query = $db->prepare('SELECT `name` FROM `players` WHERE `id`=?');
		}
		$result = $db->execute($query, $recipient);
//		$row = $db->fetchNextRow($result);
//		
//		return $row['name'];
	}
	
	function showMails($folder)
	{
		global $config;
		global $tmpl;
		global $user;
		global $db;
		
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
							  . ' AND `in_' . $folder . '`=' . "'" . '1' . "'"
							  . ' ORDER BY `messages_users_connection`.`id` ');
		$result = $db->execute($query, $db->quoteArray(array($config->value('displayedSystemUsername'), $user->getID())));
		
		$tmpl->setCurrentBlock('PMLIST');
		while ($row = $db->fetchNextRow($query))
		{
			$tmpl->setVariable('USER_PROFILE_LINK', (baseaddress() . 'Players/?profile=' . $row['author_id']));
			$tmpl->setVariable('USER_NAME', $row['author']);
			$tmpl->setVariable('MSG_LINK', (baseaddress() . 'Messages/?view=' . $row['msgid']));
			$tmpl->setVariable('MSG_SUBJECT', $row['subject']);
			$tmpl->setVariable('MSG_TIME', $row['timestamp']);
			if (strcmp($row['from_team'], '0') === 0)
			{
				$tmpl->setVariable('MSG_RECIPIENTS_LINK', (baseaddress() . 'Players/?profile=' . $row['recipients']));
			} else
			{
				$tmpl->setVariable('MSG_RECIPIENTS_LINK', (baseaddress() . 'Teams/?'));
			}
			$recipients = explode(' ', $row['recipients']);
			array_walk($recipients, 'displayRecipient', (strcmp($row['from_team'], '0') === 0));
//			$tmpl->setVariable('MSG_RECIPIENTS', $recipients);
			
			
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
		
	
	$tmpl = new template();
	
	// find out which template should be used
	if ($user->getID() < 1)
	{
		$tmpl->setTemplate('NoPerm');
		$tmpl->done('You have insufficient permissions for this action.');
	}
	$tmpl->setTemplate('PMList');
	
	// show messages in current mail folder
	// inbox is default
	$folder = 'inbox';
	if (isset($_GET['folder']) && strcmp($_GET['folder'], 'outbox') === 0)
	{
		$folder = 'outbox';
	}
	
	showMails($folder);
	
	$tmpl->render();
