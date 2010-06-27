<?php
	@require_once '../CMS/siteinfo.php';
	$site = new siteinfo();
	$connection = $site->connect_to_db();
	
	// choose database
	mysql_select_db($site->db_used_name(), $connection);
	
	$path = (pathinfo(realpath('./')));
	$display_page_title = $path['basename'];
	
	require '../CMS/index.php';
	
	// set the date and time
	date_default_timezone_set('Europe/Berlin');
	
	// only perform the operation if user logs is and not on reload
	if ($auth_performed && (isset($_SESSION['user_logged_in'])))
	{
		// delete expired invitations
		$query = 'DELETE LOW_PRIORITY FROM `invitations` WHERE `expiration`<=';
		$query .= sqlSafeStringQuotes(date('Y-m-d H:i:s'));
		if (!$result = $site->execute_query($site->db_used_name(), 'invitations', $query, $connection))
		{
			$site->dieAndEndPage('Could not delete expired invitations.');
		}
	}
	unset($auth_performed);
	
	if ((isset($_SESSION['user_logged_in'])) && ($_SESSION['user_logged_in']))
	{
		// is the user already registered at this site?
		$query = 'SELECT `id`, `suspended` FROM `players` WHERE';
		// local login will define external_playerid to be 0
		// FIXME: all users with local login have the external playerid with value 0 but the value used to access the users must be unique
		if (isset($internal_login_id))
		{
			// internal login
			$query .= ' `id`=' . sqlSafeStringQuotes($internal_login_id);
		} else
		{
			// external login
			$query .= ' `external_playerid`=' . sqlSafeStringQuotes($_SESSION['external_id']);
		}
		// only one player tries to login so only fetch one entry, speeds up login a lot
		$query .= ' LIMIT 1';
		if (!($result = @$site->execute_query($site->db_used_name(), 'players', $query, $connection)))
		{
			require_once '../CMS/navi.inc';
			$site->dieAndEndPage('Could not get account data for external_playerid ' . sqlSafeString($_SESSION['external_id']) . '.');
		}
		
		$rows_num_accounts = (int) mysql_num_rows($result);
		$suspended_mode = 0;
		while($row = mysql_fetch_array($result))
		{
			$_SESSION['viewerid'] = (int) $row['id'];
			$suspended_mode = (int) $row['suspended'];
		}
		mysql_free_result($result);
		
		// suspended mode:
		// 0 describes an active account
		// 1 describes an account that got deleted during maintenance
		// 2 describes an account that got disabled by admins
		// 3 describes that the owner of that account should be banned from the entire site, if possible
		if ($suspended_mode === (int) 1)
		{
			$query = 'UPDATE `players` SET `suspended`=' . "'" . sqlSafeString('0') . "'";
			$query .= ' WHERE `id`=' . "'" . sqlSafeString(getUserID()) . "'";
			if (!($result = @$site->execute_query($site->db_used_name(), 'players', $query, $connection)))
			{
				$site->dieAndEndPage('Could not reactivate deleted account with id ' . sqlSafeString(getUserID()) . '.');
			}
			$suspended_mode = (int) 0;
		}
		if ($suspended_mode > (int) 1)
		{
			// remove the logged in flag
			$_SESSION['user_logged_in'] = false;
			require_once '../CMS/navi.inc';
			if ($suspended_mode === (int) 2)
			{
				echo '<p>Login for this account was disabled by admins.</p>';
			}
			if ($suspended_mode === (int) 3)
			{
				// FIXME: BAN FOR REAL!!!!
				echo '<p>Admins specified you should be banned from the entire site.</p>';
			}
			echo "\n";
			// skip updates if the user has a disabled login or is banned (inappropriate callsign for instance)
			$site->dieAndEndPage('');
		}
		
		require_once '../CMS/navi.inc';
		if (isset($_SESSION['external_login']) && ($_SESSION['external_login']))
		{
			if ($rows_num_accounts === 0)
			{
				echo '<p class="first_p">Adding user to database…</p>' . "\n";
				// example query: INSERT INTO `players` (`external_playerid`, `teamid`, `name`) VALUES('1194', '0', 'ts')
				$query = 'INSERT INTO `players` (`external_playerid`, `teamid`, `name`) VALUES(';
				$query .= "'" . sqlSafeString($_SESSION['external_id']) . "'" . ', ' . "'" . '0' . "'" . ', ' . sqlSafeStringQuotes(htmlent($_SESSION['username'])) .')';
				if ($insert_result = @$site->execute_query($site->db_used_name(), 'players', $query, $connection))
				{
					$query = 'SELECT `id` FROM `players` WHERE `external_playerid`=' . "'" . sqlSafeString($_SESSION['external_id']) . "'";
					if ($id_result = @$site->execute_query($site->db_used_name(), 'players', $query, $connection))
					{
						$rows = mysql_num_rows($id_result);
						while($row = mysql_fetch_array($id_result))
						{
							$_SESSION['viewerid'] = (int) $row['id'];
						}
						if ($rows === 1)
						{
							// user inserted without problems
							echo '<p>You have been added to the list of players at this site. Thanks for visiting our site.</p>';
						} else
						{
							// FIXME: report automatically to admins
							$_SESSION['viewerid'] = -1;
							echo '<p>Unfortunately there seems to be a database problem and thus a unique id can not be retrieved for your account. ';
							echo 'Please try again later.</p>' . "\n";
							echo '<p>If the problem persists please tell an admin</p>';
						}
						mysql_free_result($id_result);
					}
				} else
				{
					// apologise, the user is new and we all like newbies
					// FIXME: report automatically to admins
					$_SESSION['viewerid'] = -1;
					echo '<p>Unfortunately there seems to be a database problem and thus you can not be added to the list of players at this site. ';
					echo 'Please try again later.</p>' . "\n";
					echo '<p>If the problem persists please tell an admin</p>';
				}
				
				// adding player profile entry
				$query = 'INSERT INTO `players_profile` (`playerid`, `joined`, `location`) VALUES (';
				$query .= sqlSafeStringQuotes(getUserID()) . ', ' . sqlSafeStringQuotes(date('Y-m-d H:i:s'));
				$query .= ', ' . sqlSafeStringQuotes('1') . ')';
				if (!(@$site->execute_query($site->db_used_name(), 'players_profile', $query, $connection)))
				{
					// FIXME: report automatically
					echo '<p>Unfortunately there seems to be a database problem and thus creating your profile page failed. ';
					echo '<p>Please report this to admins</p>';
				}
			} else
			{
				// user is not new, update his callsign with new callsign supplied from login
				$query = 'UPDATE `players` SET `name`=' . sqlSafeStringQuotes(htmlent($_SESSION['username'])) . ' WHERE `external_playerid`=' . "'" . sqlSafeString($_SESSION['external_id']) . "'";
				// each user has only one entry in the database
				$query .= ' LIMIT 1';
				if (!($update_result = @$site->execute_query($site->db_used_name(), 'players', $query, $connection)))
				{
					echo '<p>Unfortunately there seems to be a database problem which prevents the system from updating your callsign.';
					echo ' Please report this to an admin.</p>';
				}
			}
		}
		
		// bzflag auth specific code, thus use bzid value directly
		if (isset($_SESSION['bzid']) && isset($_SESSION['username']))
		{
			// find out if someone else once used the same callsign
			// update the callsign from the other player in case he did
			// example query: SELECT `external_playerid` FROM `players` WHERE (`name`='ts') AND (`external_playerid` <> '1194') AND (`external_playerid` <> '') AND (`suspended` < '2')
			// FIXME sql query should be case insensitive (SELECT COLLATION(VERSION()) returns utf8_general_ci)
			// FIXME: find out if this depends on platform
			$query = 'SELECT `external_playerid` FROM `players` WHERE (`name`=' . sqlSafeStringQuotes(htmlent($_SESSION['username'])) . ')';
			$query .= ' AND (`external_playerid` <> ' . sqlSafeStringQuotes($_SESSION['external_id']) . ')';
			// do not update users with local login
			$query .= ' AND (`external_playerid` <> ' . "'" . "'" . ')';
			// skip updates for banned or disabled accounts (inappropriate callsign for instance)
			$query .= ' AND (`suspended` < ' . sqlSafeStringQuotes('2') . ')';
			if ($result = $site->execute_query($site->db_used_name(), 'players', $query, $connection))
			{
				$errno = 0;
				$errstr = '';
				while($row = mysql_fetch_array($result))
				{
					// create a new cURL resource
					$ch = curl_init();
					
					// set URL and other appropriate options
					curl_setopt($ch, CURLOPT_URL, 'http://my.bzflag.org/bzidtools.php?action=name&value=' . ((int) $row['external_playerid']));
					curl_setopt($ch, CURLOPT_HEADER, 0);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					
					// grab URL and pass it to the browser
					$output = curl_exec($ch);
					
					// close cURL resource, and free up system resources
					curl_close($ch);
					
					// update the entry with the result from the bzidtools.php script
					// example query: UPDATE `players` SET `name`='moep' WHERE `external_playerid`='1885';
					$query = 'UPDATE `players` SET `name`=' . "'" . sqlSafeString(htmlent($output)) . "'" . ' WHERE `external_playerid`=' . "'" . sqlSafeString((int) $row['bzid']) . "'";
					if (!($update_result = @$site->execute_query($site->db_used_name(), 'players', $query, $connection)))
					{
						// trying to update the players old callsign failed
						// FIXME: Report automatically to admins
						echo '<p>Unfortunately there seems to be a database problem which prevents the system from updating the old callsign of another user.';
						echo ' However you curently own that callsign so now there will be two users with the callsign in the table and people will have problems to distinguish you two!<p>';
						echo '<p>Please report this to an admin.</p>';
					}
				}
			} else
			{
				// FIXME: should be reported to admins automatically
				echo '<p>Finding other members who had the same name failed. This is a database problem. Please report this to an admin!</p>' . "\n";
			}
		}
	}
	
	
	if ((isset($_SESSION['external_login']) && ($_SESSION['external_login'])) || (isset($internal_login_id)))
	{
		// update last visited entry
		$query = 'UPDATE `players_profile` SET `last_visit`=' . "'" . sqlSafeString(date('Y-m-d H:i:s')) . "'";
		if (isset($_SESSION['external_login']) && ($_SESSION['external_login']))
		{
			$query .= ' WHERE `playerid`=' . "'" . sqlSafeString(getUserID()) . "'";
		} else
		{
			$query .= ' WHERE `playerid`=' . "'" . sqlSafeString($internal_login_id) . "'";
		}
		// only one user account needs to be updated
		$query .= ' LIMIT 1';
		@$site->execute_query($site->db_used_name(), 'players_profile', $query, $connection);		
	}
	
	
	if ((!(isset($_SESSION['user_in_online_list'])) || !($_SESSION['user_in_online_list'])) &&  ((isset($_SESSION['user_logged_in'])) && ($_SESSION['user_logged_in'])))
	{
		$_SESSION['user_in_online_list'] = true;
		$curDate = sqlSafeString(date('Y-m-d H:i:s'));
		
		// find out if table exists
		$query = 'SHOW TABLES LIKE ' . "'" . 'online_users' . "'";
		$result = @mysql_query($query, $connection);
		$rows = @mysql_num_rows($result);
		// done
		mysql_free_result($result);
		
		$onlineUsers = false;
		if ($rows > 0)
		{
			// no need to create table in case it does not exist
			// any interested viewer looking at the online page will create it
			$onlineUsers = true;
		}
		
		// use the resulting data
		if ($onlineUsers)
		{
			$query = 'SELECT * FROM `online_users` WHERE `playerid`=' . "'" . sqlSafeString(getUserID()) . "'";
			$result = mysql_query($query, $connection);
			$rows = mysql_num_rows($result);
			// done
			mysql_free_result($result);
			
			$onlineUsers = false;
			if ($rows > 0)
			{
				// already logged in
				$query = 'DELETE FROM `online_users` WHERE `playerid`=' . "'" . sqlSafeString(getUserID()) . "'";
				// ignore result
				$result = mysql_query($query, $connection);
				if (!($result))
				{
					die('Could not remove already logged in user from online user table. Database broken?');
				}
			}
			
			// insert logged in user into online_users table
			$query = 'INSERT INTO `online_users` (`playerid`, `username`, `last_activity`) Values';
			$query .= '(' . sqlSafeStringQuotes(getUserID()) . ', ' . sqlSafeStringQuotes(htmlent($_SESSION['username'])) . ', ' . "'" . $curDate . "'" . ')';	
			$site->execute_query($site->db_used_name(), 'online_users', $query, $connection);
			
			// do maintenance in case a user still belongs to a deleted team (database problem)
			$query = 'SELECT `teams_overview`.`deleted` FROM `teams_overview`, `players` WHERE `teams_overview`.`teamid`=`players`.`teamid` AND `players`.`id`=';
			$query .= "'" . sqlSafeString(getUserID()) . "'";
			$query .= ' AND `teams_overview`.`deleted`>' . "'" . sqlSafeString('2') . "'";
			// only the player that just wants to log-in is dealt with
			$query .= ' LIMIT 1';
			if ($result = $site->execute_query($site->db_used_name(), 'teams_overview, players', $query, $connection))
			{
				while($row = mysql_fetch_array($result))
				{
					if ((int) $row['deleted'] === 1)
					{
						// mark who was where, to easily restore an unwanted team deletion
						$query = 'UPDATE `players` SET `last_teamid`=`players`.`teamid`';
						$query .= ', `teamid`=' . "'" . sqlSafeString('0') . "'";
						$query .= ' WHERE `id`=' . "'" . sqlSafeString(getUserID()) . "'";
						$site->execute_query($site->db_used_name(), 'players', $query, $connection);
					}
				}
				mysql_free_result($result);
			}
			
			// insert to the visits log of the player
			$ip_address = getenv('REMOTE_ADDR');
			$host = gethostbyaddr($ip_address);
			// try to detect original ip-address in case proxies are used
			if (!(strcmp(getenv('HTTP_X_FORWARDED_FOR'), '') === 0))
			{
				$ip_address .= ' (forwarded for: ' . getenv('HTTP_X_FORWARDED_FOR') . ')';
			}
			$query = 'INSERT INTO `visits` (`playerid`,`ip-address`,`host`,`timestamp`) VALUES (';
			$query .= "'" . sqlSafeString(getUserID()) . "'" . ', ' . "'" . sqlSafeString($ip_address) . "'" . ', ' . "'" . sqlSafeString($host) . "'" . ', ' . "'" . $curDate . "'" . ')';
			$site->execute_query($site->db_used_name(), 'visits', $query, $connection);
		}
	}
	$site->dieAndEndPage('');
?>