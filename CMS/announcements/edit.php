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
				echo '<div class="timestamp">';
				echo htmlentities($timestamp);
				echo '</div>' . "\n";
				echo '<div class="author"> By: ';
				echo htmlentities($author, ENT_COMPAT, 'UTF-8');
				echo '</div>' . "\n";
				echo '<hr>';
				echo htmlentities($announcement, ENT_COMPAT, 'UTF-8');
				echo "</div>\n\n";
				
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
											  . '" value="' . urlencode(($_SESSION[$randomkey_name])) . '"')
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
					print '<table style="text-align: left; width: 100%;" border="0" cellpadding="2" cellspacing="2"><tbody>';
					echo "<tr><td style=\x22vertical-align: top;\x22>timestamp:</td><td style=\x22vertical-align: top;\x22>";
					echo '<input name="timestamp" value="' . htmlentities(urldecode($timestamp)) . '"></input></p></td></tr>' . "\n";
					
					// announcement
					echo "<tr><td style=\x22vertical-align: top;\x22>announcement:</td><td style=\x22vertical-align: top;\x22>";
					echo "<textarea cols=\x2275\x22 rows=\x2220\x22 name=\x22announcement\x22>" . htmlent(urldecode($announcement));
					echo '</textarea></td></tr>' . "\n";
					
					// author
					if ($_SESSION[$author_change_allowed])
					{
						echo '<tr><td style="vertical-align: top;">Author:</td><td style="vertical-align: top;">';
						echo '<input name="author" value="' . htmlent(urldecode($author)) . '"></input>' . "\n";
						echo '</td></tr>' . "\n";
					} else
					{
						echo '<input type="hidden" name="author" value="' . htmlent(urldecode($author)) . '"><br>' . "\n";
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
?>