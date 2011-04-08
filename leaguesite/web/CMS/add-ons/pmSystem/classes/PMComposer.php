<?php
	class PMComposer
	{
		private $users = array();
		private $teams = array();
		private $subject = 'Enter subject here';
		private $content = 'Enter message here';
		private $timestamp = '';
		private $usernameQuery;
		private $teamnameQuery;
		
		
		function __construct()
		{
			global $db;
		
			$this->timestamp = date('Y-m-d H:i:s');
			$this->usernameQuery = $db->prepare('SELECT `id`, `name` FROM `players` WHERE `name`=? LIMIT 1');
			$this->teamnameQuery = $db->prepare('SELECT `id`, `name` FROM `teams` WHERE `name`=? LIMIT 1');
		}
		
		
		function getSubject()
		{
			return $this->subject;
		}
		
		function setSubject($subject)
		{
			$this->subject = $subject;
		}
		
		
		function getContent()
		{
			return $this->content;
		}
		
		function setContent($content)
		{
			$this->content = $content;
		}
		
		
		function getTimestamp()
		{
			return $this->timestamp;
		}
		
		function setTimestamp($timestamp)
		{
			$this->timestamp = $timestamp;
		}
		
		
		function addUserID($id)
		{
			$this->users[] = array('id' => $id);
		}
		
		function addUserName($recipientName, $preview=false)
		{
			global $db;
		
		
			// remove double entries, invalid names etc
		
			// lookup if username is already in recipient array
			$alreadyAdded = false;
			foreach ($this->users as $oneRecipient)
			{
				// assume case insensitive usernames
				if (strcasecmp(($preview) ? $oneRecipient['name'] : $oneRecipient,
						$recipientName) === 0)
				{
					$alreadyAdded = true;
				}
			}
			
			if (!$alreadyAdded)
			{
				$db->execute($this->usernameQuery, htmlent($recipientName));
				if ($row = $db->fetchRow($this->usernameQuery))
				{
					// assume case preserving usernames
					$this->users[] = ($preview) ? array('id'=>$row['id'],
						'name'=>$row['name'],
						'link' => '../Players/?profile=' . $row['id'])
					: $row['name'];
					unset($row);
				}
				$db->free($this->usernameQuery);
			}
		}
		
		
		function addTeamName($recipientName, $preview=false)
		{
			global $db;
			
			
			// remove double entries, invalid names etc
			
			// lookup if username is already in recipient array
			$alreadyAdded = false;
			foreach ($this->teams as $oneRecipient)
			{
				// assume case insensitive usernames
				if (strcasecmp(($preview) ? $oneRecipient['name'] : $oneRecipient,
						$recipientName) === 0)
				{
					$alreadyAdded = true;
				}
			}
			
			if (!$alreadyAdded)
			{
				$db->execute($this->teamnameQuery, htmlent($recipientName));
				if ($row = $db->fetchRow($this->teamnameQuery))
				{
					// assume case preserving usernames
					$this->teams[] = ($preview) ? array('id'=>$row['id'],
						'name'=>$row['name'],
						'link' => '../Teams/?profile=' . $row['id'])
					: $row['name'];
					unset($row);
				}
				$db->free($this->teamnameQuery);
			}
		}
		
		
		function countUsers()
		{
			return count($this->users);
		}
		
		function countTeams()
		{
			return count($this->teams);
		}
		
		
		function getUserIDs()
		{
			global $db;
			
			
			// initialise variables
			$recipientIDs = array();
			$query = $db->prepare('SELECT `id` FROM `players` WHERE `name`=?');
			
			// no need for queries to find out id's if id of first item already set
			if ((count($this->users) > 0) && isset($this->users[0]['id']))
			{
				foreach ($this->users as $oneRecipient)
				{
					$recipientIDs[] = $oneRecipient['id'];
				}
				
				return $recipientIDs;
			}
			
			// id's not in array, have to look them up
			foreach ($this->users as $oneRecipient)
			{
				$db->execute($query, $oneRecipient['name']);
				
				if ($row = $db->fetchRow($query))
				{
					$recipientIDs[] = $row['id'];
				}
				
				$db->free($query);
			}
			
			return $recipientIDs;
		}
		
		function getUserNames()
		{
			return $this->users;
		}
		
		function getTeamNames()
		{
			return $this->teams;
		}
		
		// send private message to players and teams
		// if an error occurs, $error will contain its description and the function will return false
		function send($author_id=0, $from_team=0,
				$msg_replied_team=0, $ReplyToMSGID=0,
				&$error='')
		{
			global $config;
			global $db;
			
			
			// remove duplicates
			if ($this->removeDuplicates($this->users) || $this->removeDuplicates($this->teams))
			{
				// back to overview to let them check
				$error = '<p>Some double entries were removed. Please check your recipients.<p>';
				return false;
			}
			
			if (strlen($this->content) === 0)
			{
				$error = '<p>You must specify a message text in order to send a message.</p>';
				return false;
			}
			
			$recipients = array();
			foreach ($this->users as $player)
			{
				$recipients[] = $player['id'];
			}
			
			// add the players belonging to the specified teams to the recipients array
			foreach ($this->teams as $teamid)
			{
				$tmp_players = $this->usersInTeam($teamid);
				foreach ($tmp_players as $playerID)
				{
					$recipients[] = $playerID;
				}
			}
			
			// remove possible duplicates
			@$this->removeDuplicates($recipients);
			
			// put message in database
			$query = $db->prepare('INSERT INTO `pmSystem.Msg.Storage`'
								  . ' (`author_id`, `subject`, `timestamp`, `message`)'
								  . ' VALUES (?, ?, ?, ?)');
			
			// lock tables for critical section
			$db->SQL('LOCK TABLES `pmSystem.Msg.Storage` WRITE');
			$db->SQL('SET AUTOCOMMIT = 0');
			
			// do the insert
			$db->execute($query, array($author_id, htmlent($this->subject), $this->timestamp, $this->content));
			$db->free($query);
			$db->SQL('COMMIT');
			
			// find out generated id
			$queryLastID = $db->SQL('SELECT `id` FROM `pmSystem.Msg.Storage` ORDER BY `id` DESC LIMIT 1');
			$rowId = $db->fetchRow($queryLastID);
			$rowId = intval($rowId['id']);
			$db->free($queryLastID);
			$db->SQL('COMMIT');
			
			// unlock tables as critical section passed
			$db->SQL('UNLOCK TABLES');
			$db->SQL('SET AUTOCOMMIT = 1');
			
			
			// add teams as visible recipients
			$query = $db->prepare('INSERT INTO `pmSystem.Msg.Recipients.Teams`'
								  . '(`msgid`, `teamid`)'
								  . 'VALUES (?, ?)');
			foreach ($this->teams as $team)
			{
					$db->execute($query, array($rowId, $team));
					$db->free($query);
			}
			unset($team);
			
			// add users as visible recipients
			// be careful to not overwrite global variable $user
			$query = $db->prepare('INSERT INTO `pmSystem.Msg.Recipients.Users`'
								  . '(`msgid`, `userid`)'
								  . 'VALUES (?, ?)');
			$userIDs = $this->getUserIDs();
			foreach ($userIDs as $userID)
			{
					$db->execute($query, array($rowId, $userID));
					$db->free($query);
			}
			unset($userID);
			
			
			// prepare recipients for SQL statement
			$recipientsSQL = is_array($recipients) ? implode(' ', $recipients) : $recipients;
			
			foreach ($recipients as $recipient)
			{
				// put message in people's inbox
				if ($ReplyToMSGID > 0)
				{
					// this is a reply
					$query = $db->prepare('INSERT INTO `pmSystem.Msg.Users`'
										  . '(`msgid`, `playerid`, `in_inbox`, `in_outbox`, `msg_replied_to_msgid)'
										  . 'VALUES (?, ?, ?, ?, ?)');
					$db->execute($query, array($rowId, $recipient, 1, 0, $ReplyToMSGID));
					$db->free($query);
				} else
				{
					// this is a new message
					$query = $db->prepare('INSERT INTO `pmSystem.Msg.Users`'
										  . ' (`msgid`, `playerid`, `in_inbox`, `in_outbox`)'
										  . ' VALUES (?, ?, ?, ?)');
					$db->execute($query, array($rowId, $recipient, 1, 0));
					$db->free($query);
				}
				
				// put message in sender's outbox
				if ($author_id > 0)
				{
					// system did not send the message but a human
					$query = $db->prepare('INSERT INTO `pmSystem.Msg.Users`'
										  . ' (`msgid`, `playerid`, `in_inbox`, `in_outbox`)'
										  . ' VALUES (?, ?, ?, ?)');
					$db->execute($query, array($rowId, $author_id, 0, 1));
				}
			}
			
			return true;
		}
		
		function removeDuplicates(array &$someArray)
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
			global $db;
			
			$teamid = intval($teamid);
			$result = array();
			
			if ($teamid < 1)
			{
				// no valid team id -> return empty player list
				return $result;
			}
			
			$query = $db->prepare('SELECT `id` FROM `players` WHERE `teamid`=?');
			$db->execute($query, $teamid);
			
			while ($row = $db->fetchRow($query))
			{
				$result[] = $row['id'];
			}
			
			return $result;
		}
	}
?>
