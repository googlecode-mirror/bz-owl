<?php
	ini_set ('session.use_trans_sid', 0);
	ini_set ('session.name', 'SID');
	session_start();
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
<?php
	include('../stylesheet.inc');
	require_once ('../CMS/siteinfo.php');
	
	echo '  <link href="http://' . domain() . basepath() . 'news.css" rel="stylesheet" type="text/css">' . "\n";
	
	$pfad = (pathinfo(realpath('./')));
	$name = $pfad['basename'];
	print '  <title>' . $name . '</title>' . "\n";
?>
</head>
<body>
<?php
	require realpath('../CMS/navi.inc');
	
	$site = new siteinfo();
	
	$connection = $site->connect_to_db();
	
	// check if user did login
	$logged_in = false;
	if (isset($_SESSION['user_logged_in']))
	{
		$logged_in = $_SESSION['user_logged_in'];
	}
	
	// only logged in users can read messages
	// usually the permission system should take care of permissions anyway
	// but just throw one more sanity check at it, for the sake of it
	// it also helps to print out a nice message to the user
	if ($message_mode && !$logged_in)
	{
		echo '<p>You need to login in order to view your private messages.</p>' . "\n";
		die("\n</div>\n</body>\n</html>");
	}
	
	// any of the variables is set and the user is not logged in
	if (((isset($_GET['add'])) || (isset($_GET['edit'])) || isset($_GET['delete'])) && (!$logged_in))
	{
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
				$query = $query . 'PRIMARY KEY  (`id`)' . "\n";
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
			$query = 'SHOW TABLES LIKE ' . "'" . $table_name_msg_user_connection . "'";
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
				//									   PRIMARY KEY  (`id`)
				//									   ) ENGINE=MyISAM DEFAULT CHARSET=utf8	
				$query = 'CREATE TABLE `' . $table_name_msg_user_connection . '` (' . "\n";
				$query = $query . '`id` int(11) unsigned NOT NULL auto_increment,' . "\n";
				$query = $query . '`msgid` int(11) unsigned NOT NULL,' . "\n";
				$query = $query . '`playerid` int(11) unsigned NOT NULL,' . "\n";
				$query = $query . '`in_inbox` bit(1) NOT NULL,' . "\n";
				$query = $query . '`in_outbox` bit(1) NOT NULL,' . "\n";
				$query = $query . 'PRIMARY KEY  (`id`)' . "\n";
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
				//										PRIMARY KEY  (`id`)
				//										) ENGINE=MyISAM DEFAULT CHARSET=utf8			
				$query = 'CREATE TABLE `' . $table_name . '` (' . "\n";
				$query = $query . '`id` int(11) unsigned NOT NULL auto_increment,' . "\n";
				$query = $query . '`timestamp` varchar(20) default NULL,' . "\n";
				$query = $query . '`author` varchar(255) default NULL,' . "\n";
				$query = $query . '`announcement` text,' . "\n";
				$query = $query . 'PRIMARY KEY  (`id`)' . "\n";
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
		echo '<a class="button" href="./?add">new entry</a><br><br>' . "\n";
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
			echo '<div class=folder_selection>' . "\n";
			require_once('msgUtils.php');
			$msgDisplay = new folderDisplay();
			if ((strcmp($folder, 'inbox') == 0) || (strcmp($folder, '') == 0))
			{
				// inbox displayed
				echo 'inbox!';
				echo ' <a href="./?folder=outbox">outbox</a>';
			} else
			{
				if (strcmp($folder, 'outbox') == 0)
				{
					// outbox displayed
					echo '<a href="./?folder=inbox">inbox</a>';
					echo ' outbox!';
				}
			}
			echo "\n" . '</div>' . "\n";
			$msgDisplay->displayMessageFolder($folder, $connection, $site, $logged_in);
		} else
		{
			// take care the table(s) do exist and if not create them
			db_create_when_needed($site, $connection, $message_mode, $table_name, $table_name_msg_user_connection);
			
			
			// the "LIMIT 0,15" part of query means only the first fifteen entries are received
			$result = mysql_query('SELECT * FROM ' . $table_name . ' ORDER BY id DESC LIMIT 0,15', $connection);
			if (!$result)
			{
				if ($site->debug_sql())
				{
					print mysql_error();
				}
				die("Query is not valid SQL.");
			}
			
			$rows = (int) mysql_num_rows($result);
			if ($rows === 0)
			{
				echo '<p>No entries made yet.</p>' . "\n";
				$site-dieAndEndPage('');
			}
			
			// read each entry, row by row
			while($row = mysql_fetch_array($result))
			{
				if ($_SESSION[$entry_edit_permission])
				{
					$currentId = $row["id"];
					echo '<a href="./?edit=' . $currentId . '">[edit]</a>' . "\n";
				}
				if ($_SESSION[$entry_delete_permission])
				{
					$currentId = $row["id"];
					echo '<a href="./?delete=' . $currentId . '">[delete]</a>' . "\n";
				}
				echo '<div class="article">' . "\n";
				echo '<div class="timestamp">' . "\n";
				printf("%s", htmlentities($row["timestamp"]));
				echo '</div>' . "\n";
				echo '<div class="author">';
				printf("By: %s", htmlentities($row["author"]));
				echo '</div>' . "\n";
				echo '<hr>';
				printf("<p>%s</p>\n", htmlentities($row["announcement"]));
				echo "</div>\n\n";
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