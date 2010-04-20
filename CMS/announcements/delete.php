<?php
	// this file handles deleting entries from table $table_name of database
	
	// check again for delete entry permission, just in case
	if ($_SESSION[$entry_delete_permission])
	{
		if (isset($_GET['delete']))
		{
			// cast the entry in order to close possible security holes
			$currentId = (int) ($_GET['delete']);
			// starting index in databse is 1
			if ($currentId < 1)
			{
				$currentId = 1;
			}
		}
		
		// the variable $currentId could now be used savely
		// use a form with a random generated key to edit data in order to
		// prevent third party websites from editing entries by links
		
		// make sure the magic key matches
		$randomkeysmatch = $site->compare_keys($randomkey_name);
		
		// $previewSeen == 1 means we're about to delete the data
		if (($previewSeen == 1) && $randomkeysmatch)
		{
			if ($message_mode)
			{
				// give possibility to go back to overview because the back button leads to the deletion form
				echo '<p><a href="./?folder=' . htmlspecialchars($folder) . '">[overview]</a><p>';
				
				// the request string contains the playerid, which takes care of permissions
				$user_id = 0;
				$box_name = sqlSafeString('in_' . $folder);
				$user_id = sqlSafeString(getUserID());
				
				// delete the message in the user's folder by his own request
				// example query: DELETE FROM `messages_users_connection` WHERE `playerid`='1194' `msgid`='66' AND `in_inbox`='1'
				$query = 'DELETE FROM `messages_users_connection` WHERE `playerid`=' . "'" . ($user_id) . "'";
				$query .= ' AND `msgid`=' . "'" . sqlSafeString($currentId) . "'" . ' AND `' . $box_name . '`=' . "'" . 1 . "'";
				$result = $site->execute_query($site->db_used_name(), 'messages_users_connection', $query, $connection);
				if ($result)
				{
					// give feedback, the user might want to know deleting was successfull so far
					echo '<p>The chosen message was deleted from your ' . htmlentities($folder) . '.</p>';
				}
				
				// IMPORTANT: Do the query _after_ the deletion of the message entry or resubmitting the form will break the script!
				// get the list of messages, we need to know if more than one user stores the message
				// example query: SELECT `id` FROM `messages_users_connection` WHERE `msgid`='66' LIMIT 0,1
				$message_is_stored_several_times = true;
				$query = 'SELECT `id` FROM `messages_users_connection` WHERE `msgid`=' . "'" . sqlSafeString($currentId) . "'" . ' LIMIT 0,1';
				$result = $site->execute_query($site->db_used_name(), $table_name, $query, $connection);
				if ($result)
				{
					$rows = (int) mysql_num_rows($result);
					if ($rows < 1)
					{
						$message_is_stored_several_times = false;
					}
				}
				
				// if the message was only saved by one user we can actually delete the message itself now
				if (!$message_is_stored_several_times)
				{
					if ($site->debug_sql())
					{
						echo '<p>This message was owned by only one player. Deleting the actual message now.</p>';
					}
					// example query: DELETE FROM `messages_storage` WHERE `id`='11'
					$query = 'DELETE FROM `messages_storage` WHERE `id`=' . "'" . sqlSafeString($currentId) . "'";
					$result = $site->execute_query($site->db_used_name(), 'messages_storage', $query, $connection);
					if ($result)
					{
						if ($site->debug_sql())
						{
							// give feedback, the user might want to know deleting was entirely successful
							echo '<p>The actual message was deleted successfully!</p>';
						}
					}
				}
			} else
			{
				$query = 'DELETE FROM ' . $table_name .' WHERE id=' . sqlSafeString($currentId);
				$result = $site->execute_query($site->db_used_name(), $table_name, $query, $connection);
				if ($result)
				{
					echo '<p>Deleting: No problems occured, entry deleted!</p>' . "\n";
					echo '<p><a href="./">[overview]</a><p>' . "\n";
					$previewSeen=0;
				} else
				{
					echo '<p>Seems like deletion failed.<ü>';
					echo '<p><a href="./">[overview]</a><p>' . "\n";
				}
			}
		} else
		{
			if ($message_mode)
			{
				echo '<form class="deletion_preview" action="' . baseaddress() . $site->base_name() . '/?delete=' . sqlSafeString($currentId) . '&amp;folder=';
				echo $folder . '" method="post">' . "\n";
				echo 'Are you sure to delete the following message? <input type="submit" value="Delete message">';
			} else
			{
				echo '<form class="deletion_preview" action="' . baseaddress() . $name . '/?delete=' . $currentId . '" method="post">' . "\n";
				echo 'Are you sure to delete the following entry? <input type="submit" value="Delete entry">';
			}
			echo '<input type="hidden" name="preview" value="' . '1' . '">' . "\n";
			
			// random key
			echo "<input type=\x22hidden\x22 name=\x22" . $randomkey_name . "\x22 value=\x22";
			echo urlencode(($_SESSION[$randomkey_name])) . "\x22>\n";
			
			echo '</form>' . "\n";
			if ($message_mode)
			{
				require_once('msgUtils.php');
				displayMessage(sqlSafeString($currentId), $site, $connection, NULL);
			} else
			{
				// the "LIMIT 0,1" part of query means only the first entry is received
				// this speeds up the query as there is only one row as result anyway
				$query = 'SELECT * FROM `' . $table_name . '` WHERE `id`=' . "'" . sqlSafeString($currentId) . "'" . ' LIMIT 0,1';
				$result = ($site->execute_query($site->db_used_name(), $table_name, $query, $connection));
				if (!$result)
				{
					die("Query is not valid SQL.");
				}
				
				$rows = mysql_num_rows($result);
				// there is only one row as result
				if ($rows === 1)
				{
					// read each entry, row by row
					while($row = mysql_fetch_array($result))
					{
						// display the row to the user
						echo '<div class="article">' . "\n";
						echo '<div class="timestamp">' . "\n";
						printf("%s", $row["timestamp"]);
						echo '</div>' . "\n";
						echo '<div class="author">';
						printf("By: %s", $row["author"]);
						echo '</div>' . "\n";
						echo '<hr>';
						printf("<p>%s</p>\n", $row["announcement"]);
						echo "</div>\n\n";
					}
					// done
					mysql_free_result($result);
				}
			}
		}
	}
?>