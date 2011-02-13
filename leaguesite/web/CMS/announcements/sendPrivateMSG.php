<?php
	// send private message to players and teams
	// if an error occurs, $error will contain its description and the function will return false
	function sendPrivateMSG($players=array(), $teams=array(),
							$author_id=0, $subject='Enter subject here',
							$timespamp, $message, $from_team=0,
							$msg_replied_team=0, $ReplyToMSGID=0, &$error='')
	{
		
		// remove duplicates
		if (removeDuplicates($players) || removeDuplicates($teams))
		{
			// back to overview to let them check
			$error = '<p>Some double entries were removed. Please check your recipients.<p>';
			return false;
		}
		
		if (strlen($message) === 0)
		{
			$error = '<p>You must specify a message text in order to send a message.</p>';
			return false;
		}
		
		$recipients = array();
		// add the players belonging to the specified teams to the recipients array
		foreach ($teams as $teamid)
		{
			$tmp_players = playersInTeam($teamid);
			foreach ($tmp_players as $playerID)
			{
				$recipients[] = $playerID;
			}
		}
		
		// remove possible duplicates
		@removeDuplicates($recipients);
		
		foreach ($recipients as $recipient)
		{
			// put message in database
			$query = ('INSERT INTO `messages_storage`'
					  . ' (`author_id`, `subject`, `timestamp`, `message`, `from_team`, `recipients`) VALUES ('
					  . sqlSafeStringQuotes($user_id) . ', ' . sqlSafeStringQuotes(htmlent($subject)) . ', '
					  . sqlSafeStringQuotes($timestamp) . ', ' . sqlSafeStringQuotes($message) . ', ' . $from_team . ', '
					  . sqlSafeStringQuotes(implode(' ', ($utils->getRecipientsIDs()))) . ')');
			$result = $site->execute_query('messages_storage', $query, $connection, __FILE__);
			$rowId = (int) mysql_insert_id($connection);
			
			// put message in people's inbox
			if ($ReplyToMSGID > 0)
			{
				// this is a reply
				$query = ('INSERT INTO `messages_users_connection` (`msgid`, `playerid`, `in_inbox`, `in_outbox`'
						  . ', `msg_replied_to_msgid) VALUES ('
						  . sqlSafeStringQuotes($rowId) . ', ' . sqlSafeStringQuotes($recipient) . ', 1, 0, ' . $ReplyToMSGID);

			} else
			{
				// this is a new message
				$query = ('INSERT INTO `messages_users_connection` (`msgid`, `playerid`, `in_inbox`, `in_outbox`) VALUES ('
						  . sqlSafeStringQuotes($rowId) . ', ' . sqlSafeStringQuotes($recipient) . ', 1, 0');
			}
			$result = @$site->execute_query('messages_users_connection', $query, $connection, __FILE__);
			
			// put message in sender's outbox
			if ($author_id > 0)
			{
				// system did not send the message but a human
				$query = ('INSERT INTO `messages_users_connection` (`msgid`, `playerid`, `in_inbox`, `in_outbox`) VALUES ('
						  . sqlSafeStringQuotes($rowId) . ', ' . sqlSafeStringQuotes($author_id) . ', 0, 1');
				$result = @$site->execute_query('messages_users_connection', $query, $connection, __FILE__);
			}
		}
		
		return true;
	}
	
	
	function removeDuplicates(&$someArray)
	{
		$dup_check = count($someArray);
		// array_unique is case sensitive, thus the loading of name from database
		$players = array_unique($someArray);
		if (!($dup_check === (count($someArray))))
		{
			// duplicates were removed
			return true;
		}
		
		// neither duplicates found nor removed
		return false;
	}
	
	function playersInTeam($teamid)
	{
		$result = Array ();
		
		if (intval($teamid) < 1)
		{
			// no valid team id -> return empty player list
			return $result;
		}
		
		$query = 'SELECT `id` FROM `players` WHERE `teamid`=' . sqlSafeStringQuotes(intval($teamid));
		$result = @$site->execute_query('players', $query, $connection, __FILE__);
		while ($row = mysql_fetch_array($result))
		{
			$result[] = $row['id'];
		}
		
		return $result;
	}
?>