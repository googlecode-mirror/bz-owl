<?php
	class addUtils
	{
		var $connection;
		var $display_name;
		var $site;
		var $recipients;
		var $recipients_ids = Array();
		var $all_users_exist = true;
				
		function setConnection($conn)
		{
			$this->connection = $conn;
		}
		function getConnection()
		{
			return $this->connection;
		}
		
		function getDisplayName()
		{
			return $this->display_name;
		}
		
		function setDisplayName($name)
		{
			$this->display_name = $name;
		}
		
		function getRecipients()
		{
			return $this->recipients;
		}
		
		function setRecipients(&$users)
		{
			$this->recipients = $users;
		}
		
		function getRecipientsIDs()
		{
			return $this->recipients_ids;
		}
		
		function addRecipientID(&$users)
		{
			$this->recipients_ids[] = $users;
		}
		
		function getSite()
		{
			return $this->site;
		}
		
		function setSite(&$information)
		{
			$this->site = $information;
		}
		
		function getAllUsersExist()
		{
			return $this->all_users_exist;
		}
		
		function setAllUsersExist()
		{
			$this->all_users_exist = true;
		}
		
		function setNotAllUsersExist()
		{
			$this->all_users_exist = false;
		}
	}
	
	// this file handles adding new entries into table $table_name of database
	
	// verify recipients array using this function
	function verifyRecipients($item, $key, $utils)
	{
		$site = $utils->getSite();
		
		if (isset($_POST['teamid']))
		{
			// example query: SELECT `id` FROM `players` WHERE `name`='ts' AND `suspended`='0' AND `teamid`='1'
			$query = 'SELECT `id` FROM `players` WHERE `id`=' . "'" . sqlSafeString($item) . "'";
			$query .= ' AND `suspended`=' . "'" . sqlSafeString('0') . "'";
			$query .= ' AND `teamid`=' . "'" . sqlSafeString($_POST['teamid']) . "'";
		} else
		{
			// example query: SELECT `id` FROM `players` WHERE `name`='ts' AND `suspended`='0'
			$query = 'SELECT `id` FROM `players` WHERE `name`=' . "'" . sqlSafeString($item) . "'";
			$query .= ' AND `suspended`=' . "'" . sqlSafeString('0') . "'";
		}
		if ($result = @$site->execute_query($site->db_used_name(), 'players', $query, $utils->getConnection()))
		{
			$rows = (int) mysql_num_rows($result);
			if ($rows === 0)
			{
				// there is no playerid for that user (not registered)
				$utils->setNotAllUsersExist();
				// remove non existing players from the recipient array
				$tmp_array = $utils->getRecipients();
				unset($tmp_array[$key]);
				$utils->setRecipients($tmp_array);
			}
			$tmp_array = Array();
			while($row = mysql_fetch_array($result))
			{
				$utils->addRecipientID($row['id']);
			}
		}
	}
	
	// read the array using helper function
	function write_hidden_input_element($item, $key, $utils)
	{
		$site = $utils->getSite();
		
		// example query: SELECT `id` FROM `players` WHERE `name`='ts'
		$query = 'SELECT `id` FROM `players` WHERE `name`=' . "'" . sqlSafeString($item) . "'";
		
		if ($result = @$site->execute_query($site->db_used_name(), 'players', $query, $utils->getConnection()))
		{
			$rows = mysql_num_rows($result);
			if ($rows > 0)
			{
				if ($utils->getDisplayName())
				{
					echo '<p>' . htmlentities($item) . '</p>';
				}
				echo '<p><input type="hidden" name="to' . ((int) $key) .  '" value="' . (htmlentities($item)) . '"></p>' . "\n";
			} else
			{
				// there is no playerid for that user (not registered)
				$utils->setNotAllUsersExist();
				$tmp_array = $utils->getRecipients();
				unset($tmp_array[$key]);
				$utils->setRecipients($tmp_array);
			}
		}
	}
	
	// initialise values
	$utils = new addUtils();
	$utils->setConnection($connection);
	$utils->setSite($site);
	$recipients = false;
	$subject = '';
	if (isset($_POST['subject']))
	{
		$subject = $_POST['subject'];
	}
	
	// check again for adding entry permission, just in case
	if ($_SESSION[$entry_add_permission])
	{
		$known_recipients = Array ();
		
		if (!(isset($_POST['teamid'])))
		{
			// a new recipient was either added or removed and thus do not show the preview yet
			// FIXME: no way to remove recipients yet
			if (isset($_POST['add_recipient']))
			{
				// echo '<p>variable previewSeen was reset because first recipient was added</p>';
				// echo 'submit set:' . (isset($_POST['submit']));
				$previewSeen = 0;
			}
			// we support up to a fixed number of recipients
			// we set the max to 20 (20-0) here
			// TODO: Put the value in the global settings file
			
			// FIXME: this should probably be done:
			// html_entity_decode(urldecode( EACH VALUE ))
			for($count = 0; $count < 20; $count++)
			{
				$variable_name = 'to' . $count;
				// fill the recipients array with values
				//				echo '<p>' . $variable_name . ' ' . (htmlentities(urldecode($_POST[$variable_name]))) . '</p>';
				if ((isset($_POST[$variable_name])) && (!(strcmp ($_POST[$variable_name], '') == 0)))
				{
					// fill the array with values
					$one_recipient = '';
					if (isset($_POST[$variable_name]))
					{
						$one_recipient = (html_entity_decode(urldecode($_POST[$variable_name])));
						$known_recipients[] = $one_recipient;
					}
				}
				// remove duplicates
				$known_recipients = array_unique($known_recipients);
				
			}
		} else
		{
			// get list of players belonging to the team to be messaged
			$query = 'SELECT `id` FROM `players`';
			$query .= ' WHERE `suspended`=' . "'" . sqlSafeString('0') . "'";
			$query .= ' AND `teamid`=' . "'" . sqlSafeString($_POST['teamid']) . "'";
			
			if ($result = @$site->execute_query($site->db_used_name(), 'players', $query, $utils->getConnection()))
			{
				while($row = mysql_fetch_array($result))
				{
					$known_recipients[] = $row['id'];
				}
			}
		}
		$utils->setRecipients($known_recipients);
		
		if (count($known_recipients) > 0)
		{
			$recipients = true;
		}
		
		// make sure to show the error message if and only if recipients were sent with the request
		if (!$recipients && isset($_POST['add_recipient']))
		{
			// go back to compositing mode because there are no recipients
			echo '<p>None of the specified users did exist. Please check your recipients.<p>' . "\n";
			$previewSeen = 0;
		}
		
		
		if (($message_mode) && ($previewSeen > 0) && (!($recipients)))
		{
			// do not send messages without recipients
			$previewSeen = 0;
			echo '<p>Please specify at least one recipient in order to send the message.<p>';
		}
		
		// just in case sanity check
		if (isset($_GET['add']))
		{
			$announcement = '';
			if (isset($_POST['announcement']))
			{
				$announcement = (html_entity_decode(urldecode($_POST['announcement'])));
			}
			if ($message_mode)
			{
				if ($previewSeen > 0)
				{
					$timestamp = date('Y-m-d H:i:s');
				}
			} else
			{
				$timestamp = '';
				if (isset($_POST['timestamp']))
				{
					$timestamp = (html_entity_decode(urldecode($_POST['timestamp'])));
				}
				if ((!$allow_different_timestamp) || (!(strcmp($timestamp, '') === 0)))
				{
					$timestamp = date('Y-m-d H:i:s');
				}
			}
			
			// FIXME: only use author information in announcement mode and even then only when $previewSeen==2
			if (isset($_POST['author']))
			{
				$author = (html_entity_decode(urldecode($_POST['author'])));
			}
			
			// handle shown author of entry
			if ((isset($_SESSION[$author_change_allowed])) && ($_SESSION[$author_change_allowed]))
			{
				if (!(isset($author)))
				{
					$author = $_SESSION['username'];
				}
			} else
			{
				// this should be always used in private message mode
				$author = $_SESSION['username'];
			}
			
			if (!(isset($author)))
			{
				// no anonymous posts and therefore cancel request
				echo 'no anonymous posts';
				$previewSeen = 0;
			}
			
			// make sure the magic key matches
			// each form has a unique id to prevent accepting the same information twice
			// the latter could be done by users clicking on forms somewhere else
			$new_randomkey_name = '';
			if (isset($_POST['key_name']))
			{
				$new_randomkey_name = html_entity_decode($_POST['key_name']);
			}
			$randomkeysmatch = $site->compare_keys($randomkey_name, $new_randomkey_name);
			
			if (!$randomkeysmatch && $previewSeen > 1)
			{
				// give possibility to go back to main view
				echo '<p><a href="./">[overview]</a><p>';
				echo '<p>The magic key does not match, it looks like you came from somewhere else or your session expired.';
				echo ' Going back to compositing mode</p>' . "\n";
				$previewSeen = 0;
			}
			
			// in case recipients were specified, check their existance
			if ($message_mode && $recipients)
			{
				// walk through the array values
				$utils->setDisplayName(false);
				$utils->setAllUsersExist();
				
				// the result of the function will be stored in the class accessed by $utils
				array_walk($known_recipients, 'verifyRecipients', $utils);
				// use the result
				if (!($utils->getAllUsersExist()))
				{
					echo '<p>Not all of the specified users did exist. Please check your recipients.<p>' . "\n";
					// overwrite some values in order to go back to compose mode
					$previewSeen = 0;
					$known_recipients = $utils->getRecipients();
					if (count($known_recipients) === 0)
					{
						echo 'no recipients';
						$recipients = false;
					}
					
				}
			}
			
			
			// FIXME: form element not yet opened
			// FIXME: move to a line between valid form start and end place
			if ($previewSeen > 0)
			{
				echo '<p><input type="hidden" name="timestamp" value="' . urlencode(htmlentities(($timestamp))) . '"></p>' . "\n";
			}
						
			// $previewSeen === 2 means we're about to insert the data
			if ($previewSeen === 2)
			{
				if ($message_mode)
				{
					if ($recipients)
					{
						// example query: INSERT INTO `messages_storage` (`author`, `author_id`, `subject`, `timestamp`, `message`, `from_team`, `recipients`) 
						// VALUES ('ts', '1194', 'test2', '2009-11-10 23:15:00', 'guildo hat euch lieb', '0', '16 17')
						$user_id = 0;
						if (getUserID() > 0)
						{
							$user_id = sqlSafeString(getUserID());
						}
						
						if (isset($_POST['teamid']))
						{
							$query = 'INSERT INTO `messages_storage` (`author`, `author_id`, `subject`, `timestamp`, `message`, `from_team`, `recipients`) VALUES (';
							$query .= "'" . sqlSafeString($author) . "'" . ', ' . "'" . $user_id . "'" . ', ' . "'" . sqlSafeString($subject) . "'" . ', ';
							$query .= "'" . sqlSafeString($timestamp) . "'" . ', ' . "'" . sqlSafeString($announcement) . "'" . ', 1, ' . "'" . sqlSafeString((int) htmlspecialchars_decode($_POST['teamid'])) . "'" . ')';
						} else
						{
							$query = 'INSERT INTO `messages_storage` (`author`, `author_id`, `subject`, `timestamp`, `message`, `from_team`, `recipients`) VALUES (';
							$query .= "'" . sqlSafeString($author) . "'" . ', ' . "'" . $user_id . "'" . ', ' . "'" . sqlSafeString($subject) . "'" . ', ';
							$query .= "'" . sqlSafeString($timestamp) . "'" . ', ' . "'" . sqlSafeString($announcement) . "'" . ', 0, ' . "'" . sqlSafeString(implode(' ', ($utils->getRecipientsIDs()))) . "'" . ')';
						}
						$message_sent = true;
						if ($result = (@$site->execute_query($site->db_used_name(), 'messages_storage', $query, $connection)))
						{
							$rowId = (int) mysql_insert_id($connection);
							// id from new entry
							// caution, always gets information of last query, means also from another page
							// thus the comment was even after the line, to call it as soon as possible after the query
							// TODO: find out if there is an alternative
							
							// mark the message as message sent to an entire team
							$query = 'INSERT INTO `messages_team_connection` (`msgid`, `teamid`)';
							$query .= ' VALUES (' . "'" . sqlSafeString($rowId) . "'" . ', ' . "'" . sqlSafeString((int) htmlspecialchars_decode($_POST['teamid'])) . "'" . ')';
							$result = @$site->execute_query($site->db_used_name(), 'messages_team_connection', $query, $connection);
							
							if (isset($_POST['teamid']))
							{
								foreach ($known_recipients as $one_recipient)
								{
									// delivery to inbox of the current player in the team messaged
									$query = 'INSERT INTO `messages_users_connection` (`msgid`, `playerid`, `in_inbox`, `in_outbox`) VALUES (';
									$query .= "'" . sqlSafeString($rowId) . "'" . ', ' . "'" . sqlSafeString($one_recipient) . "'" . ', 1, 0)';
									// usually the result should be freed for performance reasons but mysql does not return a resource for insert queries
									$tmp_result = @$site->execute_query($site->db_used_name(), 'messages_users_connection', $query, $connection);									
								}
							} else
							{
								foreach ($known_recipients as $one_recipient)
								{
									// get unique id from the specified player name in recipient list
									// example query: SELECT * FROM `players` WHERE `name`='ts'
									$query = 'SELECT * FROM `players` WHERE `name`=' . "'" . sqlSafeString($one_recipient) . "'";
									$result = @$site->execute_query($site->db_used_name(), 'players', $query, $connection);
									
									// the message needs to be send to the recipients
									$recipientId = 0;
									// deliver the message to each recipient, one by one
									while($row = mysql_fetch_array($result))
									{
										$recipientId = (int) $row['id'];
										// deliver the message to the inbox of the current player in recipient list
										// example query: INSERT INTO `messages_users_connection` (`msgid`, `playerid`, `in_inbox`, `in_outbox`) VALUES ('2', '1194', 1, 0)
										$query = 'INSERT INTO `messages_users_connection` (`msgid`, `playerid`, `in_inbox`, `in_outbox`) VALUES (';
										$query .= "'" . $rowId . "'" . ', ' . "'" . $recipientId . "'" . ', 1, 0)';
										// usually the result should be freed for performance reasons but mysql does not return a resource for insert queries
										$tmp_result = @$site->execute_query($site->db_used_name(), 'messages_users_connection', $query, $connection);
									}
								}
							}
							// put the message into the outbox of the user
							// mark it as read because the user already saw the preview when compositing the message
							// example query: INSERT INTO `messages_users_connection` (`msgid`, `playerid`, `in_inbox`, `in_outbox`, `msg_unread`) VALUES ('2', '1194', 0, 1, 0)
							$query = 'INSERT INTO `messages_users_connection` (`msgid`, `playerid`, `in_inbox`, `in_outbox`, `msg_unread`) VALUES (';
							$query .= "'" . $rowId . "'" . ', ' . "'" . $user_id . "'" . ', 0, 1, 0)';
							// immediately free the result for performance reasons
							$result = $site->execute_query($site->db_used_name(), 'messages_users_connection', $query, $connection);
							if (!($result))
							{
								$message_sent = false;
							}
							
							// reset the pointer to beginning of array
							reset($known_recipients);
							echo '<p><a href="./" class="button">overview</a><p>';
							if ($message_sent)
							{
								echo '<p>Message was sent.</p>';
							} else
							{
								echo '<p>An error occured that prevented the message from being sent. Please report this to an admin with steps to reproduce.</p>';
							}
						} else
						{
							echo "<p>Seems like adding entry failed.</p>";
							echo '<p><a href="./">[overview]</a><p>' . "\n";
						}
					}
				} else
				{
					if (isset ($recipients))
					{
						$query = 'INSERT INTO ' . $table_name . ' (timestamp, author, announcement) VALUES (';
						$query = $query . "'" . sqlSafeString($timestamp) . "'" . ',' . "'" . sqlSafeString($author) . "'" . ',' . "'" . sqlSafeString($announcement) . "'"	 .')';
						
					} else
					{
						$query = 'INSERT INTO ' . $table_name . ' (timestamp, author, announcement) VALUES (';
						$query = $query . "'" . sqlSafeString($timestamp) . "'" . ',' . "'" . sqlSafeString($author) . "'" . ',' . "'" . sqlSafeString($announcement) . "'"	 .')';
					}
					if ((@$site->execute_query($site->db_used_name(), $table_name, $query, $connection)))
					{
						echo "<p>Updating: No problems occured, changes written!</p>\n";
						echo '<p><a href="./">[overview]</a><p>' . "\n";
					} else
					{
						echo "<p>Seems like adding entry failed.</p>";
						echo '<p><a href="./">[overview]</a><p>' . "\n";
					}
				}
			}
			
			
			$pfad = (pathinfo(realpath('./')));
			$name = $pfad['basename'];
			
			if (($previewSeen===1) && ($previewSeen!==2))
			{
//				echo "<form action=\x22" . baseaddress() . $name . '/?add' . "\x22 method=\x22post\x22>\n";
				echo '<p>Preview:</p>' . "\n";
				
				// We are doing the preview by echoing the info
				if ($message_mode)
				{
					echo '<div class="msg_area">' . "\n";
					echo '<div class="msg_view_full">' . "\n";
					
					echo '	<div class="msg_header_full">' . "\n";
					echo '		<span class="msg_subject">' .  htmlentities($subject) . '</span>' . "\n";
					echo '		<span class="msg_author"> by ' .  htmlentities($author) . '</span>' . "\n";
					echo '		<span class="msg_timestamp"> at ' .	 htmlentities($timestamp) . '</span>' . "\n";
					echo '	</div>' . "\n";
					// appending to string with . broken here, need to use seperate echo lines
					echo '	<div class="msg_contents">';
					echo $site->linebreaks(bbcode($announcement));
					echo '</div>' . "\n";
					echo '</div>' . "\n";
				} else
				{
					echo '<div class="article">' . "\n";
					if ($message_mode)
					{
						echo '<div class="to">' . "\n";
						echo htmlentities(($recipients));
					}
					echo '<div class="timestamp">' . "\n";
					if ($message_mode)
					{
						// timestamp automatically generated and not set by any user
						echo $timestamp;
					} else
					{
						echo $timestamp;
					}
					echo '</div>' . "\n";
					echo '<div class="author"> By: ';
					echo htmlentities($author);
					echo '</div>' . "\n";
					echo '<hr>';
					echo bbcode($announcement);
					echo "</div>\n\n";
				}
				if ($message_mode)
				{
					echo '<form class="msg_buttons" action="' . baseaddress() . $name . '/?add' . '" method="post">' . "\n";
					echo '<p><input type="hidden" name="subject" value="' . (htmlentities($subject)) . '"></p>' ."\n";
				} else
				{
					echo '<form action="' . baseaddress() . $name . '/?add' . '" method="post">' . "\n";
				}
				
				
				// FIXME: double logic, not quite right here
				if ($message_mode && !isset($_POST['teamid']))
				{
					// walk through the array values
					$utils->setDisplayName(false);
					$utils->setAllUsersExist();
					array_walk($known_recipients, 'write_hidden_input_element', $utils);
					if (!($utils->getAllUsersExist()))
					{
						echo '<p>Not all of the specified users did exist. Please check your recipients.<p>' . "\n";
						// overwrite some values in order to go back to compose mode
						$previewSeen = 0;
						$known_recipients = $utils->getRecipients();
					}
				} else
				{
					echo '<p><input type="hidden" name="timestamp" value="' . urlencode(htmlentities(($timestamp))) . '"></p>' . "\n";
				}
				
				// keep the information in case user confirms by using invisible form items
				echo '<p><input type="hidden" name="announcement" value="' . urlencode(htmlentities($announcement)) . '"></p>' ."\n";
				echo '<p><input type="hidden" name="preview" value="' . '2' . '"></p>' ."\n";
								
				if ((isset($_SESSION[$author_change_allowed])) && ($_SESSION[$author_change_allowed]))
				{
					echo '<p><input type="hidden" name="author" value="' . urlencode(htmlentities($author)) . '"></p>' . "\n";
				}
				echo '<p><input type="hidden" name="announcement" value="' . urlencode(htmlentities($announcement)) . '"></p>' . "\n";
				
				
				$new_randomkey_name = $randomkey_name . microtime();
				$new_randomkey = $site->set_key($new_randomkey_name);
				echo '<p><input type="hidden" name="key_name" value="' . htmlentities($new_randomkey_name) . '"></p>' . "\n";
				echo '<p><input type="hidden" name="' . sqlSafeString($randomkey_name) . '" value="';
				echo urlencode(($_SESSION[$new_randomkey_name])) . '"></p>' . "\n";
				if ($message_mode)
				{
					if (isset($_POST['teamid']))
					{
						echo '<p><input type="hidden" name="teamid" value="' . htmlentities(urldecode(htmlspecialchars_decode($_POST['teamid']))) . '"></p>' . "\n";
					}
					echo '<p><input type="submit" value="' . 'Send the private message' . '"></p>' . "\n";
				} else
				{
					echo '<p><input type="submit" value="' . 'Confirm changes' . '"></p>' . "\n";
				}
			} else
			{
				// $previewSeen === 0 means we just decided to add something but did not fill it out yet
				if ($previewSeen===0)
				{
					echo '<form action="' . baseaddress() . $name . '/?add' . '" method="post">' . "\n";
					
					print '<table style="text-align: left; width: 100%;" border="0" cellpadding="2" cellspacing="2"><tbody>';
					
					// timestamp
					if ($allow_different_timestamp)
					{
						echo "<tr><td style=\x22vertical-align: top;\x22>timestamp:</td><td style=\x22vertical-align: top;\x22><input name=\x22timestamp\x22></td></tr>\n";
					}
					if ($message_mode)
					{
						echo '<tr><td style="vertical-align: top;">Send message to:</td><td style="vertical-align: top;">';
						if ($recipients)
						{
							// walk through the array values
							$utils->setDisplayName(true);
							array_walk($known_recipients, 'write_hidden_input_element', $utils);
							
							echo '<tr><td style="vertical-align: top;">Enter one more recipient:</td><td style="vertical-align: top;">';
							echo '<input name="to' . count($known_recipients) . '" size="82" maxlength="255" accept-charset="UTF-8"></td>' . "\n";
						} else
						{
							if (isset($_GET['teamid']))
							{
								// query the team name for given team id
								$query = 'SELECT `name` FROM `teams` WHERE `id`=' . sqlSafeString((int) urldecode(htmlspecialchars_decode($_GET['teamid']))) . ' LIMIT 1';
								
								if (!($team_name_result = @$site->execute_query($site->db_used_name(), 'teams', $query, $connection)))
								{
									// query was bad, error message was already given in $site->execute_query(...)
									$site->dieAndEndPage('');
								}
								
								$rows = (int) mysql_num_rows($team_name_result);
								
								if (!($rows === 1))
								{
									mysql_free_result($team_name_result);
									echo '<p>Error: The specified team ' . sqlSafeString(htmlentities($_POST['teamid'])) . ' does not exist!</p>' . "\n";
									$site->dieAndEndPage('');
								}
								
								$team_name = '(no team name)';
								// extract the team name in question
								while($row = mysql_fetch_array($team_name_result))
								{
									$team_name = $row['name'];
								}
								
								mysql_free_result($team_name_result);
								echo '<input name="to0" disabled="disabled" size="82" maxlength="255" accept-charset="UTF-8" value="' . htmlentities($team_name) . '">' . "\n";
							} else
							{
								echo '<input name="to0" size="82" maxlength="255" accept-charset="UTF-8">' . "\n";
							}
						}
						
						// only one team per team message
						if (!(isset($_GET['teamid'])))
						{
							echo '<input type="submit" name="add_recipient" value="' . 'Add recipient' . '"></tr>' . "\n";
						}
						
						// new form begins
						echo '<tr><td style="vertical-align: top;">Subject:</td><td style="vertical-align: top;">';
						echo '<input name="subject" size="82" maxlength="1000" value="' . (htmlentities($subject));
						echo '" accept-charset="UTF-8"></td></tr>' . "\n";
					}
					
					// announcement, it may be set when adding another recipient in private message mode
					echo '<tr><td style="vertical-align: top;">announcement:</td><td style="vertical-align: top;">';
					echo '<textarea cols="71" rows="20" name="announcement" accept-charset="UTF-8">' . (urldecode($announcement));
					echo '</textarea>';
					echo '</td></tr></tbody></table>' . "\n";
					
					
					// author
					if ((isset($_SESSION[$author_change_allowed])) && ($_SESSION[$author_change_allowed]))
					{
						echo 'Author: <textarea cols="71" rows="1" name="author" accept-charset="UTF-8">' . urlencode(htmlentities($_SESSION['username'])) . "</textarea><br>\n";
					} else
					{
						// FIXME: better idea to compute just at the moment the action in form has been finally confirmed by user
						echo '<input type="hidden" name="author" value="' . urlencode(htmlentities($author)) . '"><br>' . "\n";
					}
					
					if (isset($_GET['teamid']))
					{
						echo '<input type="hidden" name="teamid" value="' . htmlspecialchars(urlencode($_GET['teamid'])) . '">' . "\n";
					}
					
					echo '<input type="hidden" name="preview" value="' . '1' . '"><br>' . "\n";
					echo '<input type="submit" value="' . 'Preview' . '">' . "\n";
				}
			}
			// if there was a form opened, close it now
			if (($previewSeen===0) || ($previewSeen===1))
			{
				
				
				// end form
				echo "</form>\n";
			}
			if ($previewSeen===1)
			{
				echo '</div>' . "\n\n"; 
			}
		}
	}
?>