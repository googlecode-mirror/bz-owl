<?php
	ini_set ('session.use_trans_sid', 0);
	ini_set ('session.name', 'SID');
	ini_set('session.gc_maxlifetime', '7200');
	session_start();
	
	$path = (pathinfo(realpath('./')));
	$display_page_title = $path['basename'];
	$page_title = $display_page_title;
	
	require_once (dirname(dirname(__FILE__)) . '/siteinfo.php');
	$site = new siteinfo();
	
	if (strcmp($page_title, '') === 0)
	{
		echo '<div class="static_page_box">' . "\n";
		$site->dieAndEndPage('Error: No page title specified!');;
	}
	
	if (isset($_GET['edit']))
	{
		$display_page_title = 'Page content editor: ' . $page_title;
	}
	require_once (dirname(dirname(__FILE__)) . '/index.inc');
	
	
	// next line also sets $connection = $site->connect_to_db();
	require (dirname(dirname(__FILE__)) . '/navi.inc');
	
	// check if user did login
	$logged_in = false;
	if (isset($_SESSION['user_logged_in']))
	{
		$logged_in = $_SESSION['user_logged_in'];
	}
	
	if (!(isset($message_mode)))
	{
		$message_mode = false;
	}
	
	if (!(isset($allow_different_timestamp)))
	{
		$allow_different_timestamp = false;
	}
	
	// only logged in users can read messages
	// usually the permission system should take care of permissions anyway
	// but just throw one more sanity check at it, for the sake of it
	// it also helps to print out a nice message to the user
	if ($message_mode && !$logged_in)
	{
		echo '<div class="static_page_box">' . "\n";
		echo '<p>You need to login in order to view your private messages.</p>' . "\n";
		die("\n</div>\n</body>\n</html>");
	}
	
	// any of the variables is set and the user is not logged in
	if (((isset($_GET['add'])) || (isset($_GET['edit'])) || isset($_GET['delete'])) && (!$logged_in))
	{
		echo '<div class="static_page_box">' . "\n";
		echo '<p>You need to login in order to change any content of the website.</p>' . "\n";
		die("\n</div>\n</body>\n</html>");
	}
	
	function db_create_when_needed($site, $connection, $message_mode, $table_name, $table_name_msg_user_connection)
	{
		// choose database
		mysql_select_db($site->db_used_name(), $connection);
		
		// cache if we were successful
		$success = false;
		
		if ($message_mode)
		{
			//set up table structure for private messages when needed
			$query = 'SHOW TABLES LIKE ' . "'" . $table_name . "'";
			$result = mysql_query($query, $connection);
			$rows = mysql_num_rows($result);
			// done
			mysql_free_result($result);
			
			if ($rows < 1)
			{
				echo '<p>Table does not exist. Attempting to create table.<p>';
				
				// query will be
				// CREATE TABLE `messages_storage` (
				//								 `id` int(11) unsigned NOT NULL auto_increment,
				//								 `timestamp` varchar(20) default NULL,
				//								 `author` varchar(255) default NULL,
				//								 `announcement` varchar(1000) default NULL,
				//								 `from_team` bit(1) default NULL,
				//								 PRIMARY KEY  (`id`)
				//								 ) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8
				$query = 'CREATE TABLE `' . $table_name . '` (' . "\n";
				$query = $query . '`id` int(11) unsigned NOT NULL auto_increment,' . "\n";
				$query = $query . '`timestamp` varchar(20) default NULL,' . "\n";
				$query = $query . '`author` varchar(255) default NULL,' . "\n";
				$query = $query . '`announcement` varchar(1000) default NULL,' . "\n";
				$query = $query . '`from_team` bit(1) default NULL,' . "\n";
				$query = $query . 'PRIMARY KEY	(`id`)' . "\n";
				$query = $query . ') ENGINE=MyISAM DEFAULT CHARSET=utf8';
				if (@$site->execute_query($site->db_used_name(), $table_name, $query, $connection))
				{
					$success = true;
				} else
				{
					echo "<p>Creation of table failed.</p>";
					die("\n</div>\n</body>\n</html>");
				}
			}
			
			// do the same for the table that connects the messages to its users
			$query = 'SHOW TABLES LIKE ' . sqlSafeStringQuotes($table_name_msg_user_connection);
			$result = mysql_query($query, $connection);
			$rows = mysql_num_rows($result);
			// done
			mysql_free_result($result);
			
			if ($rows < 1)
			{
				echo '<p>Table does not exist. Attempting to create table.<p>';
				
				// query will be
				// CREATE TABLE `messages_connect_users` (
				//									   `id` int(11) unsigned NOT NULL auto_increment,
				//									   `msgid` int(11) unsigned NOT NULL,
				//									   `playerid` int(11) unsigned NOT NULL,
				//									   `in_inbox` bit(1) NOT NULL,
				//									   `in_outbox` bit(1) NOT NULL,
				//									   PRIMARY KEY	(`id`)
				//									   ) ENGINE=MyISAM DEFAULT CHARSET=utf8 
				$query = 'CREATE TABLE `' . $table_name_msg_user_connection . '` (' . "\n";
				$query = $query . '`id` int(11) unsigned NOT NULL auto_increment,' . "\n";
				$query = $query . '`msgid` int(11) unsigned NOT NULL,' . "\n";
				$query = $query . '`playerid` int(11) unsigned NOT NULL,' . "\n";
				$query = $query . '`in_inbox` bit(1) NOT NULL,' . "\n";
				$query = $query . '`in_outbox` bit(1) NOT NULL,' . "\n";
				$query = $query . 'PRIMARY KEY	(`id`)' . "\n";
				$query = $query . ') ENGINE=MyISAM DEFAULT CHARSET=utf8';
				if ((@$site->execute_query($site->db_used_name(), $table_name, $query, $connection)))
				{
					$success = true;
				} else
				{
					echo "<p>Creation of table failed.</p>";
					die("\n</div>\n</body>\n</html>");
				}
			}
			// done setting up table structure for private messages
		} else
		{
			// set up table structure for announcements like news or bans when needed
			$query = 'SHOW TABLES LIKE ' . "'" . $table_name . "'";
			$result = mysql_query($query, $connection);
			$rows = mysql_num_rows($result);
			// done
			mysql_free_result($result);
			
			
			if ($rows < 1)
			{
				echo '<p>Table does not exist. Attempting to create table.<p>';
				
				// query will be
				//			CREATE TABLE `$table_name` (
				//										`id` int(11) unsigned NOT NULL auto_increment,
				//										`timestamp` varchar(20) default NULL,
				//										`author` varchar(255) default NULL,
				//										`announcement` text,
				//										PRIMARY KEY	 (`id`)
				//										) ENGINE=MyISAM DEFAULT CHARSET=utf8			
				$query = 'CREATE TABLE `' . $table_name . '` (' . "\n";
				$query = $query . '`id` int(11) unsigned NOT NULL auto_increment,' . "\n";
				$query = $query . '`timestamp` varchar(20) default NULL,' . "\n";
				$query = $query . '`author` varchar(255) default NULL,' . "\n";
				$query = $query . '`announcement` text,' . "\n";
				$query = $query . 'PRIMARY KEY	(`id`)' . "\n";
				$query = $query . ') ENGINE=MyISAM DEFAULT CHARSET=utf8';
				if ((@$site->execute_query($site->db_used_name(), $table_name, $query, $connection)))
				{
					$success = true;
				} else
				{
					echo "<p>Creation of table failed.</p>";
					die("\n</div>\n</body>\n</html>");
				}
			}
		}
		if ($success)
		{
			echo '<p>Updating: The missing table(s) were created successfully!</p>' . "\n";
			echo '<p><a class="button" href="./">overview</a><p>' . "\n";
		}
	}
	
	
	// random key to prevent third party sites from using links to post content
	// generate only one key in order to allow multiple tabs
	
	// the key is generated by setting variable $randomkey_name
	if (!(isset($_SESSION[$randomkey_name])))
	{
		// this should be good enough as all we need is something that can not be guessed without much tries
		$_SESSION[$randomkey_name] = $key = rand(0, getrandmax());
	}
	
	// cast to int to prevent possible security problems
	$previewSeen = 0;
	if (isset($_POST['preview']))
	{
		$previewSeen = (int) $_POST['preview'];
	}
	
	if (!$connection)
	{
		if ($site->debug_sql())
		{
			print mysql_error();
		}
		die("Connection to database failed.");
	}
	// connection successful
	
	// take care to either add, edit or delete and not doing all at the same time
	
	// user is able to add new entries
	if ((isset($_SESSION[$entry_add_permission]) && ($_SESSION[$entry_add_permission])) && (!isset($_GET['add'])) && (!(isset($_GET['edit']))) && (!(isset($_GET['delete']))))
	{
		echo '<a class="button" href="./?add">new entry</a>';
		$site->write_self_closing_tag('br');
		$site->write_self_closing_tag('br');
		echo "\n";
	}
	
	// overview link
	if (isset($_GET['add']) || isset($_GET['edit']) || isset($_GET['delete']))
	{
		if ($message_mode && (!((strcmp($folder, 'inbox') == 0) || (strcmp($folder, '') == 0))))
		{
			// back button might lead to the deletion form, show link to last viewed folder
			echo '<p class="first_p"><a class="button" href="./?folder=' . htmlspecialchars($folder) . '">overview</a><p>';
		} else
		{
			echo '<p class="first_p"><a class="button" href="./">overview</a></p>';
		}
	}
	
	// handle adding new item
	if ((isset($_SESSION[$entry_add_permission]) && ($_SESSION[$entry_add_permission])) && (isset($_GET['add'])) && (!(isset($_GET['edit']))) && (!(isset($_GET['delete']))))
	{
		require_once('add.php');
	}
	
	// handle editing item
	if ((isset($_SESSION[$entry_edit_permission]) && ($_SESSION[$entry_edit_permission])) && (isset($_GET['edit'])) && (!(isset($_GET['add']))) && (!(isset($_GET['delete']))))
	{
		require_once('edit.php');
	}
	
	
	// handle deleting item
	if ((isset($_SESSION[$entry_delete_permission]) && ($_SESSION[$entry_delete_permission])) && (isset($_GET['delete'])) && (!(isset($_GET['add']))) && (!(isset($_GET['edit']))))
	{
		require_once('delete.php');
	}
	
	if ((!(isset($_GET['add']))) && (!(isset($_GET['edit']))) && (!(isset($_GET['delete']))))
	{
		// show existing entries at the bottom of page
		
		// choose database
		mysql_select_db($site->db_used_name(), $connection);
		
		// display depends on current mode
		if ($message_mode)
		{
			echo '<div class="folder_selection">' . "\n";
			require_once('msgUtils.php');
			$msgDisplay = new folderDisplay();
			if ((strcmp($folder, 'inbox') == 0) || (strcmp($folder, '') == 0))
			{
				// inbox displayed
				if (isset($_GET['view']))
				{
					echo '<a href="./?folder=inbox">inbox!</a>';
				} else
				{
					echo 'inbox!';
				}
				echo ' <a href="./?folder=outbox">outbox</a>';
			} else
			{
				if (strcmp($folder, 'outbox') == 0)
				{
					// outbox displayed
					echo '<a href="./?folder=inbox">inbox</a>';
					if (isset($_GET['view']))
					{
						echo ' <a href="./?folder=outbox">outbox!</a>';
					} else
					{
						echo ' outbox!';
					}
				}
			}
			echo "\n" . '</div>' . "\n";
			$msgDisplay->displayMessageFolder($folder, $connection, $site, $logged_in);
		} else
		{
			// take care the table(s) do exist and if not create them
			// FIXME: do this in maintenance
//			db_create_when_needed($site, $connection, $message_mode, $table_name, $table_name_msg_user_connection);
			
			
			// the "LIMIT 0,15" part of query means only the first fifteen entries are received
			$query = 'SELECT * FROM ' . $table_name . ' ORDER BY id DESC LIMIT 0,15';
			$result = ($site->execute_query($site->db_used_name(), $table_name, $query, $connection));
			if (!$result)
			{
				$site->dieAndEndPage();
			}
			
			$rows = (int) mysql_num_rows($result);
			if ($rows === 0)
			{
				echo '<p class="first_p">No entries made yet.</p>' . "\n";
				$site-dieAndEndPage('');
			}
			
			// read each entry, row by row
			while($row = mysql_fetch_array($result))
			{
				if ((isset($_SESSION[$entry_edit_permission])) && ($_SESSION[$entry_edit_permission]))
				{
					$currentId = $row["id"];
					echo '<a href="./?edit=' . $currentId . '">[edit]</a>' . "\n";
				}
				if ((isset($_SESSION[$entry_delete_permission])) && ($_SESSION[$entry_delete_permission]))
				{
					$currentId = $row["id"];
					echo '<a href="./?delete=' . $currentId . '">[delete]</a>' . "\n";
				}
				echo '<div class="article">' . "\n";
				echo '<div class="article_header">' . "\n";
				echo '<div class="timestamp">';
				printf("%s", htmlentities($row["timestamp"]));
				echo '</div>' . "\n";
				echo '<div class="author">';
				printf("By: %s", htmlent($row["author"]));
				echo '</div>' . "\n";
				echo '</div>' . "\n";
				echo '<p>';
				echo $site->linebreaks(htmlent($row['announcement']));
				echo '</p>' . "\n";
				echo "</div>\n\n";
				$site->write_self_closing_tag('br');
			}
			// done
			mysql_free_result($result);
		}
	}
	mysql_close($connection);
?>

</div>
</body>
</html>