<span class="logo"></span>

<ul class="navigation">
<?php
	require_once 'siteinfo.php';
	require_once 'permissions.php';
	$site = new siteinfo();
	
	$unread_messages = false;
	
	// set the date and time
	date_default_timezone_set('Europe/Berlin');
	
	$connection = $site->connect_to_db();
	
	// remove expired sessions from the list of online users
	$query ='SELECT `playerid`, `last_activity` FROM `online_users`';
	if (!($result = @$site->execute_silent_query($site->db_used_name(), 'online_users', $query, $connection)))
	{
		// query was bad, error message was already given in $site->execute_query(...)
		$site->dieAndEndPage('<p>Could not get list of online users from database.</p>');
	}
	if (((int) mysql_num_rows($result)) > 0)
	{
		while($row = mysql_fetch_array($result))
		{
			$saved_timestamp = $row['last_activity'];
			$old_timestamp = strtotime($saved_timestamp);
			$now = (int) strtotime("now");
			// is entry more than two hours old?
			if ($now - $old_timestamp > (60*60*2))
			{
				$query = 'DELETE LOW_PRIORITY FROM `online_users` WHERE `last_activity`=';
				$query .= "'" . sqlSafeString($saved_timestamp) . "'";
				if (!($result_delete = @$site->execute_silent_query($site->db_used_name(), 'online_users', $query, $connection)))
				{
					// query was bad, error message was already given in $site->execute_query(...)
					$site->dieAndEndPage('<p>Could delete old online users from database.</p>');
				}
			}
		}
	}
	mysql_free_result($result);
	
	// update activity data
	if (getUserID() > 0)
	{
		// the execution of the query is not that time critical and it happens often -> LOW_PRIORITY
		$query = 'UPDATE LOW_PRIORITY `online_users` SET `last_activity`=' . "'";
		$query .= sqlSafeString(date('Y-m-d H:i:s')) . "'" . ' WHERE `playerid`=' . "'" . sqlSafeString(getUserID()) . "'";
		@mysql_select_db($site->db_used_name(), $connection);
		@mysql_query($query, $connection);
		
		// are there unread messages?
		$query = 'SELECT `id` FROM `messages_users_connection` WHERE `msg_unread`=' . "'" . '1' . "'" . ' AND `playerid`=' . "'" . sqlSafeString(getUserID()) . "'"; 
		$result = @mysql_query($query, $connection);
		$rows = (int) @mysql_num_rows($result);
		if ($rows > 0)
		{
			$unread_messages = true;
		}
		mysql_free_result($result);
	}
	
	define ('BASISPFAD', baseaddress());
	
	function schreibeVerweis($verzeichnis, $titel)
	{
		echo '<li><a href="';
		echo (BASISPFAD . $verzeichnis . '/">' . $titel . '</a></li>' . "\n");
	}
	
	$name = $site->base_name();
	
	// public_html auf FreeBSD oder Sites auf Mac OS X
	$ganzOben = (strcmp($name, 'public_html') == 0) || (strcmp($name, 'Sites') == 0) || (strcmp($name, 'leaguesite') == 0) || (strcmp($name, 'ts') == 0);
	
	if (!(isset($_SESSION['user_logged_in'])) || !($_SESSION['user_logged_in']))
	{
		if (strcmp($name, 'Login') == 0)
		{
			echo '<li>Login</li>' . "\n";
		} else
		{
			schreibeVerweis('Login', 'Login');
		}
	}
	
	if ($ganzOben)
	{
		echo '<li>Home</li>' . "\n";
	} else
	{
		echo "<li><a href=\x22" . BASISPFAD . "\x22>Home</a></li>\n";
	}
	
	if ((isset($_SESSION['user_logged_in'])) && ($_SESSION['user_logged_in']))
	{
		if (strcmp($name, 'Messages') == 0)
		{
			echo '<li>Bz mail</li>' . "\n";
		} else
		{
			if ($unread_messages)
			{
				schreibeVerweis('Messages', '<span class="unread_messages">Bz mail</span>');
			} else
			{
				echo schreibeVerweis('Messages', 'Bz mail');
			}
		}
	}
	
	if (strcmp($name, 'News') == 0)
	{
		echo '<li>News</li>' . "\n";
	} else
	{
		schreibeVerweis('News', 'News');
	}
	
	if (strcmp($name, 'Matches') == 0)
	{
		echo '<li>Matches</li>' . "\n";
	} else
	{
		schreibeVerweis('Matches', 'Matches');
	}
	
	if (strcmp($name, 'Teams') == 0)
	{
		echo '<li>Teams</li>' . "\n";
	} else
	{
		schreibeVerweis('Teams', 'Teams');
	}
	
	if (strcmp($name, 'Players') == 0)
	{
		echo '<li>Players</li>' . "\n";
	} else
	{
		schreibeVerweis('Players', 'Players');
	}
	
	if ((isset($_SESSION['allow_view_user_visits'])) && ($_SESSION['allow_view_user_visits']))
	{
		if (strcmp($name, 'Visits') == 0)
		{
			echo '<li>Visits log</li>' . "\n";
		} else
		{
			echo schreibeVerweis('Visits', 'Visits log');
		}
	}
	
	if (strcmp($name, 'Rules') == 0)
	{
		echo '<li>Rules</li>' . "\n";
	} else
	{
		schreibeVerweis('Rules', 'Rules');
	}
	
	if (strcmp($name, 'FAQ') == 0)
	{
		echo '<li>FAQ</li>' . "\n";
	} else
	{
		schreibeVerweis('FAQ', 'FAQ');
	}
	
	if (strcmp($name, 'Links') == 0)
	{
		echo '<li>Links</li>' . "\n";
	} else
	{
		schreibeVerweis('Links', 'Links');
	}
	
	if (strcmp($name, 'Contact') == 0)
	{
		echo '<li>Contact</li>' . "\n";
	} else
	{
		schreibeVerweis('Contact', 'Contact');
	}
	
	if (strcmp($name, 'Bans') == 0)
	{
		echo '<li>Bans</li>' . "\n";
	} else
	{
		schreibeVerweis('Bans', 'Bans');
	}
	
//	if (strcmp($name, 'Server') == 0)
//	{
//		echo '<li>Bans</li>' . "\n";
//	} else
//	{
//		schreibeVerweis('Servertracker', 'Server');
//	}

	
	if (strcmp($name, 'Config') == 0)
	{
		echo '<li>Config</li>' . "\n";
	} else
	{
		schreibeVerweis('Config', 'Config');
	}
?>
</ul>

<div class="content">

<?php
	echo '<div class="userbuttons">' . "\n";
	if (!(strcmp($name, 'Online') == 0))
	{
		echo '<span class="onlineUsers">';
		echo '<a href="' . baseaddress() . 'Online/' . '">online users</a>';
		echo '</span>' . "\n";
	}
	
	if ((isset($_SESSION['user_logged_in'])) && ($_SESSION['user_logged_in']))
	{
		echo '<span class="logout">';
		echo '<a href="' . baseaddress() . 'Logout/' . '">Logout</a>';
		echo '</span>' . "\n";
	}
	echo '</div>' . "\n\n" ;
?>