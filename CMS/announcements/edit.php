<?php
	// this file handles editing new entries in table $table_name of database
	
	// check again for editing entry permission, just in case
	if ($_SESSION[$entry_edit_permission])
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
			
			
			$announcement = (html_entity_decode(urldecode($_POST['announcement'])));
			$timestamp = (html_entity_decode(urldecode($_POST['timestamp'])));
			$author = (html_entity_decode(urldecode($_POST['author'])));
			
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
					echo '<p><a href="./">[overview]</a><p>' . "\n";
				} else
				{
					echo "Seems like editing failed.";
					echo '<p><a href="./">[overview]</a><p>' . "\n";
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
				echo '<div class="timestamp">' . "\n";
				echo htmlentities($timestamp);
				echo '</div>' . "\n";
				echo '<div class="author"> By: ';
				echo htmlentities($author);
				echo '</div>' . "\n";
				echo '<hr>';
				echo htmlentities($announcement);
				echo "</div>\n\n";
				
				// keep the information in case user confirms by using invisible form items
				echo "<input type=\x22hidden\x22 name=\x22announcement\x22 value=\x22" . urlencode(htmlentities($announcement)) . "\x22><br>\n";
				echo "<input type=\x22hidden\x22 name=\x22preview\x22 value=\x22" . '2' . "\x22><br>\n";
				echo "<input type=\x22hidden\x22 name=\x22timestamp\x22 value=\x22" . urlencode(htmlentities(($timestamp))) . "\x22><br>\n";
				echo "<input type=\x22hidden\x22 name=\x22author\x22 value=\x22" . urlencode(htmlentities($author)) . "\x22><br>\n";
				echo "<input type=\x22hidden\x22 name=\x22announcement\x22 value=\x22" . urlencode(htmlentities($announcement)) . "\x22><br>\n";
				
				echo "<input type=\x22hidden\x22 name=\x22" . $randomkey_name . "\x22 value=\x22";
				echo urlencode(($_SESSION[$randomkey_name])) . "\x22><br>\n";
				echo "<input type=\x22submit\x22 value=\x22" . 'Confirm changes' . "\x22>\n";
			} else
			{
				// $previewSeen == 0 means we just decided to add something but did not fill it out yet
				if ($previewSeen==0)
				{
					$query = 'SELECT * from ' . $table_name . ' WHERE id=' . sqlSafeString($currentId) . ' ORDER BY id LIMIT 0,2';
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
						print '<table style="text-align: left; width: 100%;" border="0" cellpadding="2" cellspacing="2"><tbody>';
						echo "<tr><td style=\x22vertical-align: top;\x22>timestamp:</td><td style=\x22vertical-align: top;\x22>";
						echo "<input name=\x22timestamp\x22 value=\x22" . htmlentities(urldecode($timestamp)) . "\x22>$buffer</input></p></td></tr>\n";
						
						// announcement
						echo "<tr><td style=\x22vertical-align: top;\x22>announcement:</td><td style=\x22vertical-align: top;\x22>";
						echo "<textarea cols=\x2275\x22 rows=\x2220\x22 name=\x22announcement\x22>" . htmlentities(urldecode($announcement));
						echo '</textarea></td></tr>' . "\n";
						
						// author
						if ($_SESSION[$author_change_allowed])
						{
							echo '<tr><td style="vertical-align: top;">Author:</td><td style="vertical-align: top;">';
							echo '<input name="author" value="' . htmlentities(urldecode($author)) . '"></input>' . "\n";
							echo '</td></tr>' . "\n";
						} else
						{
							echo '<input type="hidden" name="author" value="' . htmlentities(urldecode($author)) . '"><br>' . "\n";
						}
						
						echo '<input type="hidden" name="preview" value="' . '1' . '"><br>' . "\n";
						echo '<input type="submit" value="' . 'Preview' . '">' . "\n";
						echo '</tbody></table>' . "\n";
						
						// if there was a form opened, close it now
						if (($previewSeen==0) || ($previewSeen==1))
						{
							echo "</form>\n";
						}
					}
				}
			}
		}
	}
?>