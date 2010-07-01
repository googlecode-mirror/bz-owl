<?php
	// this file handles editing new entries in table $table_name of database
	
	// check again for editing entry permission, just in case
	if (isset($_SESSION[$entry_edit_permission]) && ($_SESSION[$entry_edit_permission]))
	{
		if (isset($_GET['edit']))
		{
			// cast the entry in order to close possible security holes
			$currentId = (int) ($_GET['edit']);
			// starting index in databse is 1
			if ($currentId < 1)
			{
				$currentId = 1;
			}
			
			
			$announcement = '';
			if (isset($_POST['announcement']))
			{
				$announcement = (htmlent_decode(urldecode($_POST['announcement'])));
			}
			$timestamp = '';
			if (isset($_POST['timestamp']))
			{
				$timestamp = (htmlent_decode(urldecode($_POST['timestamp'])));
			}
			$author = '';
			if (isset($_POST['author']))
			{
				$author = (htmlent_decode(urldecode($_POST['author'])));
			}
			
			// handle shown author of entry
			if ($_SESSION[$author_change_allowed])
			{
				if (!(isset($author)))
				{
					$author = $_SESSION['username'];
				}
			} else
			{
				$author = $_SESSION['username'];
			}
			
			if (!(isset($author)))
			{
				// no anonymous posts and therefore cancel request
				$previewSeen = 0;
			}
			
			// make sure the magic key matches
			$randomkeysmatch = $site->compare_keys($randomkey_name);
			
			// $previewSeen == 2 means we're about to insert the data
			if (($previewSeen==2) && $randomkeysmatch)
			{
				if (!$randomkeysmatch)
				{
					echo '<p>The random key did not match, it looks like you came from a different site</p>' . "\n";
				}
				// sqlSafeString is from siteinfo.php
				// needed to prevent SQL injections
				// example: UPDATE news SET timestamp="tes", author="me", announcement="really" WHERE id=7
				$query = 'UPDATE ' . $table_name . ' SET timestamp="' . sqlSafeString($timestamp) . '", author="' . sqlSafeString($author);
				$query = $query . '", announcement="' . sqlSafeString($announcement) . '" WHERE id=' . sqlSafeString($currentId);
				
				if ((@$site->execute_query($site->db_used_name(), $table_name, $query, $connection)))
				{
					echo "Updating: No problems occured, changes written!<br><br>\n";
				} else
				{
					echo "Seems like editing failed.";
				}
			}
			
			$pfad = (pathinfo(realpath('./')));
			$name = $pfad['basename'];
			
			//\x22 == "
			if (($previewSeen==1) && ($previewSeen!=2)){
				echo "<form action=\x22" . baseaddress() . $name . '/?edit=' . $currentId . "\x22 method=\x22post\x22>\n";
				echo 'Preview:' . "\n";
				
				// We are doing the preview by echoing the info
				// FIXME: Do bb code instead of raw html
				// FIXME: This is a lower priority problem because only a minority can edit messages
				echo '<div class="article">' . "\n";
				echo '<div class="article_header">' . "\n";
				echo '<div class="timestamp">';
				echo htmlentities($timestamp);
				echo '</div>' . "\n";
				echo '<div class="author"> By: ';
				echo htmlent($author);
				echo '</div>' . "\n";
				echo '</div>' . "\n";
				echo '<p>' . htmlent($announcement) . '</p>' . "\n";
				echo '</div>' . "\n\n";
				
				// keep the information in case user confirms by using invisible form items
				$site->write_self_closing_tag('input type="hidden" name="announcement" value="' . urlencode(htmlent($announcement)) . '"');
				$site->write_self_closing_tag('br');
				echo "\n";
				$site->write_self_closing_tag('input type="hidden" name="preview" value="2"');
				$site->write_self_closing_tag('br');
				echo "\n";
				$site->write_self_closing_tag('input type="hidden" name="timestamp" value="' . urlencode(htmlent($timestamp)) . '"');
				$site->write_self_closing_tag('br');
				echo "\n";
				$site->write_self_closing_tag('input type="hidden" name="author" value="' . urlencode(htmlent($author)) . '"');
				$site->write_self_closing_tag('br');
				echo "\n";
				$site->write_self_closing_tag('input type="hidden" name="announcement" value="' . urlencode(htmlent($announcement)) . '"');
				$site->write_self_closing_tag('br');
				echo "\n";
				
				$site->write_self_closing_tag('input type="hidden" name="' . $randomkey_name
											  . '" value="' . urlencode(($_SESSION[$randomkey_name])) . '"');
				$site->write_self_closing_tag('br');
				echo "\n";
				$site->write_self_closing_tag('input type="submit" value="Confirm changes"');
				echo "\n";
			} else
			{
				// $previewSeen == 0 means we just decided to add something but did not fill it out yet
				if ($previewSeen==0)
				{
					$query = 'SELECT * from `' . $table_name . '` WHERE `id`=' . sqlSafeStringQuotes($currentId) . ' ORDER BY id LIMIT 1';
					$result = ($site->execute_query($site->db_used_name(), $table_name, $query, $connection));
					if (!$result)
					{
						$site->dieAndEndPage();
					}
					
					// read each entry, row by row
					while($row = mysql_fetch_array($result))
					{
						// overwrite each variable at first request of editing
						// these values come right from the database so perform no sanity checks
						$timestamp = $row["timestamp"];
						$author = $row["author"];
						$announcement = $row["announcement"];
					}
					// done
					mysql_free_result($result);
					
					
					echo "<form action=\x22" . baseaddress() . $name . '/?edit=' . $currentId . "\x22 method=\x22post\x22>\n";
					
					// timestamp
					echo '<div>' . "\n";
					echo '	<label class="msg_edit" for="msg_edit_timestamp">Timestamp:</label> ' . "\n";
					echo '	<span>' . "\n" . '		';
					$site->write_self_closing_tag('input type="text" id="msg_edit_timestamp" name="timestamp" value="' . htmlentities(urldecode($timestamp)) . '"');
					echo '	</span>' . "\n";
					echo '</div>' . "\n";
										
					// announcement
					echo '<div>' . "\n";
					echo '	<label class="msg_edit" for="msg_send_announcement">Message:</label>' . "\n";
					echo '	<span><textarea id="msg_send_announcement" rows="2" cols="30" name="announcement">';
					echo htmlent($announcement);
					echo '</textarea></span>' . "\n";
					echo '</div>' . "\n";
					
					// author
					if ((isset($_SESSION[$author_change_allowed])) && ($_SESSION[$author_change_allowed]))
					{
						echo '<div>' . "\n";
						echo '	<label class="msg_edit" for="msg_send_subject">Author:</label>' . "\n";
						echo '	<span>';
						$site->write_self_closing_tag('input type="text" id="msg_send_subject" maxlength="50" name="author" value="' . htmlent($author) . '"'
													  . ' onfocus="if(this.value==' . "'" . htmlent($author) . "'" . ') this.value=' . "'" . "'" . '"'
													  . ' onblur="if(this.value==' . "'" . "'" . ') this.value=' . "'" . htmlent($author) . "'" . '"');
						echo "\n" . '</span>' . "\n";
						echo '</div>' . "\n";
					} else
					{
//						// FIXME: better idea to compute just at the moment the action in form has been finally confirmed by user
//						echo '<input type="hidden" name="author" value="' . urlencode(htmlentities($author, ENT_COMPAT, 'UTF-8')) . '"><br>' . "\n";
					}
					
					echo '<div>' . "\n";
					$site->write_self_closing_tag('input type="hidden" name="preview" value="' . '1' . '"');
					echo '</div>' . "\n";
					echo '<div>' . "\n";
					$site->write_self_closing_tag('input type="submit" value="' . 'Preview' . '"');
					echo '</div>' . "\n";
				}
			}
			
			// if there was a form opened, close it now
			if (($previewSeen==0) || ($previewSeen==1))
			{
				echo '</form>' . "\n";
				echo '</div>' . "\n";
			}
		}
	}
?>