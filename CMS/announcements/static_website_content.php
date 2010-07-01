<?php
	ini_set ('session.use_trans_sid', 0);
	ini_set ('session.name', 'SID');
	ini_set('session.gc_maxlifetime', '7200');
	session_start();
	
	
	require_once (dirname(dirname(__FILE__)) . '/siteinfo.php');
	$site = new siteinfo();
	
	if (strcmp($page_title, '') === 0)
	{
		$site->dieAndEndPage('Error: No page title specified!');;
	}
	
	if (isset($_GET['edit']))
	{
		$display_page_title = 'Page content editor: ' . $page_title;
	}
	require_once (dirname(dirname(__FILE__)) . '/index.inc');
	
	require (dirname(dirname(__FILE__)) . '/navi.inc');
	
	$site = new siteinfo();
	
	function errormsg()
	{
		echo '<p>You do not have the appropriate password for that user or that user does not exist.</p>' . "\n";
	}
	
	
	// initialise variables
	$previewSeen = '';
	$content = '';
	
	
	// set their values in case the POST variables are set
	if (isset($_POST["preview"]))
	{
		$previewSeen = $_POST['preview'];
	}
	if (isset($_POST['News']))
	{
		$content = $_POST['News'];
	}
	
	
	if ((isset($_SESSION[$entry_edit_permission])) && ($_SESSION[$entry_edit_permission]))
	{
		// user has permission to edit the page
		if (isset($_GET['edit']))
		{
			// user looks at page in edit mode
			echo '<p><a href="./" class="button">Original</a>' . '</p>' ."\n";
			echo '<div class="static_page_box">' . "\n";
		} else
		{
			// user looks at page in read mode
			echo '<a href="./?edit" class="button">edit</a>' . "\n";
			$site->write_self_closing_tag('br');
			echo "\n";
			$site->write_self_closing_tag('br');
		}
	} else
	{
		// user has no permission to edit the page
		if (isset($_GET['edit']))
		{
			// user wants to edit the page
			errormsg();
			// show a button to let the user look at the page in read only mode
			echo '<p><a href="./" class="button">Original</a>' . '</p>' ."\n";
			// stop here or the user will be able to edit the content despite he has no permission
			$site->dieAndEndPage('');
		}
	}
	
	// prevent links letting people modify the page unintentionally
	if (isset($_GET['edit']) && ($previewSeen > 0))
	{
		$new_randomkey_name = '';
		if (isset($_POST['key_name']))
		{
			$new_randomkey_name = html_entity_decode($_POST['key_name']);
		}
		$randomkeysmatch = $site->compare_keys(urldecode($randomkey_name), $new_randomkey_name);
		
		if (!$randomkeysmatch)
		{
			// automatically back to main view
			echo '<p>The magic key does not match, it looks like you came from somewhere else or your session expired.';
			echo ' Going back to compositing mode.</p>' . "\n";
			$previewSeen = 0;
		}
	}
	
	
	function readContent($page_title, $site, $connection, &$author, &$last_modified)
	{
		// initialise return variable so any returned value will be always in a defined state
		$content = '<p class="first_p">No content available yet.</p>';
		
		$query = 'SELECT * FROM `static_pages` WHERE `page_name`=' . "'" . sqlSafeString($page_title) . "'" . ' LIMIT 1';
		if (!($result = @$site->execute_query($site->db_used_name(), 'static_pages', $query, $connection)))
		{
			$site->dieAndEndPage('An error occured getting content for page ' . $page_title . '!');
		}
		
		// process query result array
		while ($row = mysql_fetch_array($result))
		{	 
			$author = $row['author'];
			$last_modified = $row['last_modified'];
			$content = $row['content'];
		}
		
		mysql_free_result($result);
		
		return $content;
	}
	
	function writeContent (&$content, $page_title, $site, $connection)
	{
		$query = 'SELECT `id` FROM `static_pages` WHERE `page_name`=' . "'" . sqlSafeString($page_title) . "'" . ' LIMIT 1';
		if (!($result = @$site->execute_query($site->db_used_name(), 'static_pages', $query, $connection)))
		{
			$site->dieAndEndPage('An error occured getting content for page ' . $page_title . '!');
		}
		
		// number of rows
		$rows = (int) mysql_num_rows($result);
		$date_format = date('Y-m-d H:i:s');
		if ($rows < ((int) 1))
		{
			// no entry in table regarding current page
			// thus insert new data
			$query = 'INSERT INTO `static_pages` (`author`, `page_name`, `content`, `last_modified`) VALUES (';
			// getUserID() is a function from siteinfo.php that identifies the current user
			$query .= "'" . sqlSafeString(getUserID()) . "'";
			$query .= ', ' . "'" . sqlSafeString($page_title) . "'";
			$query .= ', ' . "'" . sqlSafeString($content) . "'";
			$query .= ', ' . "'" . sqlSafeString($date_format) . "'";
			$query .= ')';
		} else
		{
			// either 1 or more entries found, just assume there is only one
			$query = 'UPDATE `static_pages` SET `author`=' . "'" . sqlSafeString(getUserID()) . "'";
			$query .= ', `content`=' . "'" . sqlSafeString($content) . "'";
			$query .= ', `last_modified`=' . "'" . sqlSafeString($date_format) . "'";
			$query .= ' WHERE `page_name`=' . "'" . sqlSafeString($page_title) . "'";
			$query .= ' LIMIT 1';
		}
		
		if (!($result = @$site->execute_query($site->db_used_name(), 'static_pages', $query, $connection)))
		{
			$site->dieAndEndPage('An error occured updating content for page ' . $page_title
								 . ' by user ' . sqlSafeString(getUserID()) . '!');
		}
	}
	
	
	if ($previewSeen==2)
	{
		writeContent($content, $page_title, $site, $connection);
		echo '<p>Updating: No problems occured, changes written successfully!</p>' . "\n";
		$buffer = $content;
	} else
	{
		$author = '';
		$last_modified = '';
		
		$buffer = readContent($page_title, $site, $connection, $author, $last_modified);
	}
	
	if (isset($_GET['edit']))
	{
		echo '<form action="./?edit" method="post" accept-charset="utf-8">' . "\n";
		$new_randomkey_name = $randomkey_name . microtime();
		$new_randomkey = $site->set_key($new_randomkey_name);
		echo '<div>';
		$site->write_self_closing_tag('input type="hidden" name="key_name" value="' . htmlentities($new_randomkey_name) . '"');
		echo '</div>' . "\n";
		echo '<div>';
		$site->write_self_closing_tag('input type="hidden" name="' . htmlentities($randomkey_name) . '" value="'
									  . urlencode(($_SESSION[$new_randomkey_name])) . '"');
		echo '</div>' . "\n";
		
		if (($previewSeen==1) && ($previewSeen!==2))
		{
			echo '<p>Preview:</p>' . "\n";
			echo '<div>';
			echo $content;
			echo '</div>' . "\n";
			echo '<div>';
			$site->write_self_closing_tag('input type="hidden" name="News" value="' . htmlent($content) . '"');
			echo '</div>' . "\n";
			echo '<div>';
			$site->write_self_closing_tag('input type="hidden" name="preview" value="2"');
			echo '</div>' . "\n";
			
			echo '<p>';
			$site->write_self_closing_tag('input type="submit" value="Confirm changes"');
			echo '</p>' . "\n";
		} else
		{
			echo '<p>Put the articles in p-tags and headlines into h1-tags to get their style being applied.</p>' . "\n";
			echo '<div>Keep in mind the home page currently uses HTML, not XHTML.</div>' . "\n";
			echo '<div><textarea cols="75" rows="20" name="News">' . htmlent($buffer) . '</textarea></div>' . "\n";
			echo '<div>';
			$site->write_self_closing_tag('input type="hidden" name="preview" value="1"');
			echo '</div>' . "\n";
			
			echo '<p>';
			$site->write_self_closing_tag('input type="submit" value="Preview"');
			echo '</p>' . "\n";
		}
		echo '</form>' . "\n";
	} else
	{
		echo '<div class="static_page_box">' . "\n";
		echo $buffer;
	}
?>
</div>
</div>
</body>
</html>