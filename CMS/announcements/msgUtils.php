<?php	
	function formatOverviewText($text, $id, $folder, $unread)
	{
		if ($unread)
		{
			return '<a class="msg_overview_unread" href="./?folder=' . urlencode($folder) . '&amp;view=' . htmlentities((int) $id) . '">' . htmlent($text) . '</a>';
		} else
		{
			return '<a href="./?folder=' . urlencode($folder) . '&amp;view=' . htmlentities((int) $id) . '">' . htmlent($text) . '</a>';
		}
	}
	
	function find_recipient_player(&$item, $key, $connection)
	{
		// $item is passed by reference and therefore changes to $item will be passed back to the caller function
		
		// find out player name by the specified player id in the variable named $item
		$query = 'SELECT `name` FROM `players` WHERE `id`=' . "'" . sqlSafeString($item) . "'" . ' LIMIT 1';
		$result = @mysql_query($query, $connection);
		
		// initialise the variable with an error message
		// if the query is successfull the variable will be overwritten with the player name
		$item = 'Unknown player!';
		
		// read each entry, row by row
		while($row = mysql_fetch_array($result))
		{
			$item = htmlent($row['name']);
		}
		mysql_free_result($result);
	}
	
	function find_recipient_team(&$item, $key, $connection)
	{
		// $item is passed by reference and therefore changes to $item will be passed back to the caller function
		
		// find out team name by the specified team id in the variable named $item		  
		$query = 'SELECT `name` FROM `teams` WHERE `id`=' . "'" . sqlSafeString($item) . "'" . ' LIMIT 1';
		$result = @mysql_query($query, $connection);
		
		// initialise the variable with an error message
		// if the query is successfull the variable will be overwritten with the player name
		$item = 'Unknown team!';
		
		// read each entry, row by row
		while($row = mysql_fetch_array($result))
		{
			$item = htmlentities($row['name'], ENT_COMPAT, 'UTF-8');
		}
		mysql_free_result($result);		   
	}
	
	function displayMessage($id, $site, $connection, $folder)
	{
		// display a single message (either in inbox or outbox) in all its glory
		
		// example query: SELECT * FROM `messages_storage` WHERE `id`='5'
		$query = 'SELECT * FROM `messages_storage` WHERE `id`=';
		$query .= "'" . sqlSafeString((int) $id) . "'";
		$result = $site->execute_query($site->db_used_name(), 'messages_storage', $query, $connection);
		
		if ((int) mysql_num_rows($result) > 1)
		{
			die('There can not be more than 1 message viewed at the same time on the same page.');
		}
		
		while($row = mysql_fetch_array($result))
		{
			echo '<div class="msg_view_full">' . "\n";
			
			echo '	<div class="msg_header_full">' . "\n";
			echo '		<span class="msg_subject">' .  htmlentities($row["subject"], ENT_COMPAT, 'UTF-8') . '</span>' . "\n";
			echo '		<span class="msg_author"> by ' .  htmlentities($row["author"], ENT_COMPAT, 'UTF-8') . '</span>' . "\n";
			echo '		<span class="msg_timestamp"> at ' .	 htmlentities($row['timestamp']) . '</span>' . "\n";
			echo '	</div>' . "\n";
			// adding to string using . will put the message first, then the div tag..which is wrong
			echo '	<div class="msg_contents">';
			echo $site->linebreaks(htmlentities($row['message'], ENT_COMPAT, 'UTF-8'), $site);
			echo '</div>' . "\n";
			echo '</div>' . "\n\n";
		}
		mysql_free_result($result);
		
		// folder is NULL in case a message is either being deleted or being sent
		if (!($folder === NULL) && (!(strcmp($folder, 'outbox') === 0)))
		{
			// mark the message as unread
			$query = 'UPDATE LOW_PRIORITY `messages_users_connection` SET `msg_unread`=' . "'";
			$query .= sqlSafeString(0) . "'" . ' WHERE `msgid`=' . "'" . sqlSafeString((int) $id) . "'";
			$query .= ' AND `in_' . $folder . '`=' . "'" . '1' . "'";
			// silently ignore the result, it's not a resource anyway so it can't even be dropped
			$site->execute_query($site->db_used_name(), 'messages_users_connection', $query, $connection);
		}
	}
	
	// read the array using helper function
	function displayMessageSummary($item, $key, $connection)
	{
		// variable folder lost --> have to restore
		// FIXME: find out if better way, like sharing variable in class, is possible!
		
		$folder = '';
		if (isset($_GET['folder']))
		{
			$folder = $_GET['folder'];
		}
		
		// default folder is inbox
		if (strcmp($folder, '') == 0)
		{
			$folder = 'inbox';
		}
		if (!(strcmp($folder, 'inbox') === 0))
		{
			$folder = 'outbox';
		}
		$box_name = sqlSafeString('in_' . $folder);
		
		// TODO: needs to be outside of this function
		// Are there messages to display so we need an overview at all?
		$user_id = (sqlSafeString((int) $item));
		$query = 'SELECT * FROM `messages_storage`, `messages_users_connection` WHERE `messages_storage`.`id`=`messages_users_connection`.`msgid`';
		$query .= ' AND `messages_storage`.`id`=' . "'" . sqlSafeString($user_id) . "'" . ' AND `' . $box_name	.'`=' . "'" . '1' . "'";
		$query .= ' ORDER BY `messages_storage`.`id` LIMIT 0,1';
		$result = @mysql_query($query, $connection);
		
		// read each entry, row by row
		while($row = mysql_fetch_array($result))
		{
			// FIXME: display possibility to delete messages in the summary (checkboxes in each row and one button at the end of the list)
			
			// TODO: implement delete button in each row
			// TODO: implement class in stylesheets
			echo '<tr class="msg_overview">' . "\n";
			$currentId = $row['msgid'];
			$unread = $row['msg_unread'];
			// TODO: implement class in stylesheets
			echo '	<td class="msg_overview_subject">' . (formatOverviewText($row["author"], $currentId, $folder, $unread)) . '</td>' . "\n";
			// TODO: implement class in stylesheets
			echo '	<td class="msg_overview_timestamp">' . (formatOverviewText($row["subject"], $currentId, $folder, $unread)) . '</td>' . "\n";
			// TODO: implement class in stylesheets
			echo '	<td class="msg_overview_author">' . (formatOverviewText($row["timestamp"], $currentId, $folder, $unread)) . '</td>' . "\n";
			
			echo '	<td class="msg_overview_recipients">';
			if (!(strcmp($folder, 'inbox') == 0))
			{
				// save the recipients as an array
				$recipients = explode(' ', $row['recipients']);
				
				//print_r($recipients);
				// TODO: implement class msg_overview_recipients in stylesheets
				if (strcmp('0', $row['from_team']) == 0)
				{
					// message has one or more players as recipient(s)
					array_walk($recipients, 'find_recipient_player', $connection);
				} else
				{
					// message has one or more teams as recipient(s)
					array_walk($recipients, 'find_recipient_team', $connection);
				}
				echo (implode('<span class="msg_recipient_seperator">, </span>', $recipients));
			}
			echo '</td>' . "\n";
			echo '</tr>' . "\n\n";
			
		}
		// query results are no longer needed
		mysql_free_result($result);
	}
	
	class folderDisplay
	{
		// display a message folder well formatted
		function displayMessageFolder($folder, $connection, $site)
		{
			// default folder is inbox
			if (strcmp($folder, '') === 0)
			{
				$folder = 'inbox';
			}
			
			// put some values into variables to make query more generic
			$box_name = sqlSafeString('in_' . $folder);
			$user_id = 0;
			
			if (getUserID() > 0)
			{
				$user_id = sqlSafeString(getUserID());
			}
			
			if (isset($_GET['view']))
			{
				// display a certain message
				
				// sanity checks needed before displaying
				// make sure the id is an int
				$id = (int) sqlSafeString($_GET['view']);
				
				// make sure the one who wants to read the message has actually permission to read it
				// example query: SELECT * FROM `messages_users_connection` WHERE `msgid`='2' AND `playerid`='1194' AND `in_inbox`='1' ORDER BY id LIMIT 0,1'
				$query = 'SELECT * FROM `messages_users_connection` WHERE `msgid`=';
				$query .= "'" . $id . "'" . ' AND `playerid`=';
				$query .= "'" . $user_id . "'" . ' AND `' . $box_name  .'`=' . "'" . '1' . "'" . ' ORDER BY id ';
				// the "LIMIT 0,1" part of query means only the first entry is received
				$query .= 'LIMIT 0,1';
				
				$result = $site->execute_query($site->db_used_name(), 'messages_users_connection', $query, $connection);
				$rows = (int) mysql_num_rows($result);
				mysql_free_result($result);
				if ($rows === 1)
				{
					echo '<div class="msg_area">';
					// display the message chosen by user
					displayMessage($id, $site, $connection, $folder);
					
					echo '<div class="msg_view_button_list">' . "\n";
					// if the message is in inbox the user might want to reply to the message
					if (strcmp($folder, 'inbox') === 0)
					{
						// FIXME
						echo '<form class="msg_buttons" action="' . baseaddress() . $site->base_name() . '/?add&amp;=';
						echo '" method="post">' . "\n";
						echo '<p><input type="submit" value="Reply"></p>' . "\n";
						echo '</form>' . "\n";
					}
					
					// the user might want to delete the message
					echo '<form class="msg_buttons" action="' . baseaddress() . $site->base_name() . '/?delete=' . ((int) $id) . '&amp;folder=';
					echo $folder . '" method="post">' . "\n";
					echo '<p><input type="submit" value="Delete this message"></p>' . "\n";
					echo '</form>' . "\n";
					echo '</div>' . "\n";
					
					echo '</div>' . "\n";
				} else
				{
					echo 'You have no permission to view the message';
					// TODO: silently report the incident to admins
				}
				
			} else
			{
				// show the overview
				$query = 'SELECT `msgid` FROM `messages_users_connection` WHERE `playerid`=';
				$query .= "'" . $user_id . "'" . ' AND `' . $box_name  .'`=' . "'" . '1' . "'" . ' ORDER BY id ';
				// newest messages first please
				$query .= 'DESC ';
				// limit the output to the requested rows to speed up displaying
				$query .= 'LIMIT ';
				// the "LIMIT 0,200" part of query means only the first 200 entries are received
				// the range of shown messages is set by the GET variable i
				$view_range = (int) 0;
				if (isset($_GET['i']))
				{
					if (((int) $_GET['i']) > 0)
					{
						$view_range = (int) $_GET['i'];
						$query .=  $view_range . ',';
					} else
					{
						// force write 0 for value 0 (speed)
						// and 0 for negative values (security: DBMS error handling prevention)
						$query .= '0,';
					}
				} else
				{
					// no special value set -> write 0 for value 0 (speed)
					$query .= '0,';
				}
				$query .= ((int) $view_range)+201;
				
				$result = $site->execute_query($site->db_used_name(), 'messages_users_connection', $query, $connection);
				
				$rows = (int) mysql_num_rows($result);
				$show_next_messages_button = false;
				if ($rows > 200)
				{
					$show_next_messages_button = true;
				}
				if ($rows < 1)
				{
					echo '<div class="msg_overview">No messages in ' . $folder . '.</div>';
					mysql_free_result($result);
				} else
				{
					// display message overview
					$msgid_list = Array ();
					// read each entry, row by row
					while($row = mysql_fetch_array($result))
					{
						$msgid_list[] = $row['msgid'];
					}
					// query results are no longer needed
					mysql_free_result($result);
					
					// are more than 200 rows in the result?
					if ($show_next_messages_button)
					{
						// only show 200 messages, not 201
						// NOTE: array_pop would not work on a resource (e.g. $result)
						array_pop($msgid_list);
					}
					
					// table of messages
					// FIXME: Implement class in stylesheet
					echo "\n" . '<table class="table_msg_overview">' . "\n";
					echo '<caption>Messages in ' . $folder . '</caption>' . "\n";
					echo '<tr>' . "\n";
					echo '	<th>Author</th>' . "\n";
					echo '	<th>Subject</th>' . "\n";
					echo '	<th>Date</th>' . "\n";
					if (!(strcmp($folder, 'inbox') === 0))
					{
						// user is not looking not at inbox
						echo '	<th>Receipient(s)</th>' . "\n";
					}
					echo '</tr>' . "\n\n";
					
					// walk through the array values
					array_walk($msgid_list, 'displayMessageSummary', $connection);
					
					echo '</table>' . "\n";
					// look up if next and previous buttons are needed to look at all messages in overview
					if ($show_next_messages_button || ($view_range !== (int) 0))
					{
						// browse previous and next entries, if possible
						echo "\n" . '<p>'  . "\n";
						
						if ($view_range !== (int) 0)
						{
							echo '	<a href="./?folder=';
							echo $folder;
							echo '&i=';
							echo ((int) $view_range)-200;
							echo '">Previous messages</a>' . "\n";
						}
						if ($show_next_messages_button)
						{
							
							echo '	<a href="./?folder=';
							echo $folder;
							echo '&i=';
							echo ((int) $view_range)+200;
							echo '">Next messages</a>' . "\n";
						}
						echo '</p>' . "\n";
					}
				}
			}
		}
	}
?>