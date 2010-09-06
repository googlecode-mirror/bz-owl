<div class="logo"></div>

<ul class="navigation">
<?php
	require_once 'siteinfo.php';
	require_once 'permissions.php';
	if (!isset($site))
	{
		$site = new siteinfo();
	}
	
	function dieCustom($reason)
	{
		global $site;
		
		$site->log_error($reason);
		echo $reason . "\n";
		echo '</ul>' . "\n" . '</body>' . "\n" . '<html>' . "\n";
		die();
	}
	
	$unread_messages = false;
	
	// set the date and time
	date_default_timezone_set($site->used_timezone());
	
	$connection = $site->connect_to_db();
	
	// remove expired sessions from the list of online users
	$query ='SELECT `playerid`, `last_activity` FROM `online_users`';
	if (!($result = @$site->execute_silent_query('online_users', $query, $connection)))
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
				$query .= sqlSafeStringQuotes($saved_timestamp);
				if (!($result_delete = @$site->execute_silent_query('online_users', $query, $connection)))
				{
					$site->dieAndEndPage('<p>Could delete old online users from database.</p>');
				}
			}
		}
	}
	mysql_free_result($result);
	
	// update activity data
	$logged_in = true;
	if (getUserID() > 0)
	{
		// the execution of the query is not that time critical and it happens often -> LOW_PRIORITY
		$query = 'UPDATE LOW_PRIORITY `online_users` SET `last_activity`=';
		$query .= sqlSafeStringQuotes(date('Y-m-d H:i:s')) . ' WHERE `playerid`=' . sqlSafeStringQuotes(getUserID());
		@mysql_select_db($site->db_used_name(), $connection);
		@mysql_query($query, $connection);
		
		// are there unread messages?
		// are there unread messages?
		$query = ('SELECT `id` FROM `messages_users_connection` WHERE `msg_status`='
				  . sqlSafeStringQuotes('new')
				  . ' AND `playerid`=' . sqlSafeStringQuotes(getUserID())
				  . ' LIMIT 1');
		$result = @mysql_query($query, $connection);
		$rows = (int) @mysql_num_rows($result);
		if ($rows > 0)
		{
			$unread_messages = true;
		}
		mysql_free_result($result);
	} else
	{
		$logged_in = false;
	}
	
	define ('BASEPATH', baseaddress());
	
	function writeLink($folder, $title, $current=false)
	{
		echo '<li>';
		if (!$current)
		{
			echo '<a href="' . (BASEPATH . $folder) . '">';
		} elseif (count($_GET) > 0)
		{
			echo '<a class="current_nav_entry" href="' . (BASEPATH . $folder) . '">';
		}
		echo $title;
		if (!$current || (count($_GET) > 0))
		{
			echo '</a>';
		}
		echo '</li>' . "\n";
	}
	
	$name = $site->base_name();
	
	// public_html on FreeBSD or Sites on Mac OS X
	// top level dir depends on siteconfig
	$ganzOben = (strcmp($name, 'public_html') == 0) || (strcmp($name, 'Sites') == 0) || (strcmp($name, 'leaguesite') == 0) || (strcmp($name, 'ts') == 0);
	
	if (!$logged_in)
	{
		writeLink('Login/', 'Login', (strcmp($name, 'Login') == 0));
	}
	
	if ($ganzOben)
	{
		echo '<li>Home</li>' . "\n";
	} else
	{
		echo '<li><a href="' . BASEPATH . '">Home</a></li>' . "\n";
	}
	
	if ((isset($_SESSION['user_logged_in'])) && ($_SESSION['user_logged_in']))
	{
		if ($unread_messages)
		{
			writeLink('Messages/', '<span class="unread_messages">Mail</span>');
		} else
		{
			writeLink('Messages/', 'Mail', (strcmp($name, 'Messages') == 0));
		}
	}
	
	writeLink('News/', 'News', (strcmp($name, 'News') == 0));
	
	writeLink('Matches/', 'Matches', (strcmp($name, 'Matches') == 0));
	
	writeLink('Teams/', 'Teams', (strcmp($name, 'Teams') == 0));
	
	writeLink('Players/', 'Players', (strcmp($name, 'Players') == 0));
	
	if ($logged_in && (isset($_SESSION['allow_view_user_visits'])) && ($_SESSION['allow_view_user_visits']))
	{
		writeLink('Visits/', 'Visits', (strcmp($name, 'Visits') == 0));
	}
	
	writeLink('Rules/', 'Rules', (strcmp($name, 'Rules') == 0));
	
	writeLink('FAQ/', 'FAQ', (strcmp($name, 'FAQ') == 0));
	
	writeLink('Links/', 'Links', (strcmp($name, 'Links') == 0));
	
	writeLink('Contact/', 'Contact', (strcmp($name, 'Contact') == 0));
	
	writeLink('Bans/', 'Bans', (strcmp($name, 'Bans') == 0));

	if ($logged_in && (isset($_SESSION['allow_watch_servertracker'])) && ($_SESSION['allow_watch_servertracker']))
	{
		writeLink('Servertracker/', 'Servers', (strcmp($name, 'Servertracker') == 0));
	}
	
	writeLink('Config/', 'Config', (strcmp($name, 'Config') == 0));
?>
</ul>

<?php
	$online_users_page_viewed = true;
	if (!(strcmp($name, 'Online') == 0))
	{
		$online_users_page_viewed = false;
	}
	
	if ($logged_in || !$online_users_page_viewed)
	{
		echo '<div class="userbuttons">' . "\n";
		
		// list of servers
		echo '<ul class="Servers">Offi servers: ';
		$query = ('SELECT SUM(`cur_players_total`) AS `cur_players_total`'
				  . ' FROM `servertracker`'
				  . ' ORDER BY `id`');
		if (!($result = $site->execute_silent_query('servertracker', $query, $connection)))
		{
			$site->dieAndEndPageNoBox('Could not find out servername and serveraddress to be updated.');
		}
		while ($row = mysql_fetch_array($result))
		{
			writeLink('Servertracker/', ('{' . $row['cur_players_total'] . '}'));
		}
		mysql_free_result($result);
		
		// run the update script:
		// >/dev/null pipes output to nowhere
		// & lets the script run in the background
		exec('php ' . dirname(__FILE__) . '/servertracker_query_backend.php >/dev/null &');
		echo '</ul>';
	}
	
	if (!$online_users_page_viewed)
	{
		echo '<span class="onlineUsers">';
		$query = 'SELECT count(`playerid`) AS `num_players` FROM `online_users`';
		if (!($result = @$site->execute_silent_query('online_users', $query, $connection)))
		{
			$site->dieAndEndPage('<p>Could not get number of online users from database.</p>');
		}
		
		$n_users = (mysql_fetch_array($result));
		if (intval($n_users['num_players']) === 1)
		{
			echo '<a href="' . baseaddress() . 'Online/' . '">' . '1 online user</a>';
		} else
		{
			echo '<a href="' . baseaddress() . 'Online/' . '">' . $n_users['num_players'] . ' online users</a>';
		}
		echo '</span>' . "\n";
		
		mysql_free_result($result);
		unset($n_users);
	}
	
	if ($logged_in)
	{
		echo '<span class="logout">';
		echo '<a href="' . baseaddress() . 'Logout/' . '">Logout</a>';
		echo '</span>' . "\n";
	}
	echo '</div>' . "\n\n" ;
?>
<div class="content">
