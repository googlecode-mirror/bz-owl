<?php
	require_once '../CMS/permissions.php';
			
	$pw = '';
	if (isset($_POST['pw']))
	{
		$pw = $_POST['pw'];
	}
	
	$loginname = '';
	if (isset($_POST['loginname']))
	{
		$loginname = $_POST['loginname'];
	}
	
	if (isset($_POST['pw']) && isset($_POST['loginname']))
	{
		// initialise permissions
		no_permissions();
		
		$correctUser = false;
		$correctPw = false;
		
		
		$lenLogin = strlen($loginname);
		if (($lenLogin > 50) || ($lenLogin < 1))
		{
			require_once '../CMS/navi.inc';
			echo '<p>User names must be using less than 50 but more than 0 <abbr title="characters">chars</abbr>.</p>' . "\n";
			$site->dieAndEndPage('');
		}
		
		// get player id
		$query = 'SELECT `id` FROM `players` WHERE `name`=' . "'" . sqlSafeString($loginname) . "'";
		// only one player tries to login so only fetch one entry, speeds up login a lot
		$query .= ' LIMIT 1';
		
		// execute query
		if (!($result = @$site->execute_query($site->db_used_name(), 'players', $query, $connection)))
		{
			require_once '../CMS/navi.inc';
			// query failed
			$site->dieAndEndPage(('Could not get id for name ' . sqlSafeString($loginname)));
		}
		
		// initialise with reserved player id 0 (no player)
		$playerid = (int) 0;
		while($row = mysql_fetch_array($result))
		{
			$playerid = $row['id'];
		}
		mysql_free_result($result);
		
		// get password from database in order to compare it with the user entered password
		$query = 'SELECT `password`, `password_md5_encoded` FROM `players_passwords` WHERE `playerid`=' . "'" . sqlSafeString($playerid) . "'";
		// only one player tries to login so only fetch one entry, speeds up login a lot
		$query .= ' LIMIT 1';
		
		// execute query
		if (!($result = @$site->execute_query($site->db_used_name(), 'players_passwords', $query, $connection)))
		{
			require_once '../CMS/navi.inc';
			// query failed
			$site->dieAndEndPage(('Could not get password for player with id ' . sqlSafeString($playerid)));
		}
		
		// initialise without md5 (hash functions will cause collisions despite passwords will not match)
		$password_md5_encoded = false;
		// no password is default and will not match any password set
		$password_database = '';
		while($row = mysql_fetch_array($result))
		{
			if (((int) $row['password_md5_encoded']) === 1)
			{
				$password_md5_encoded = true;
			}
			$password_database = $row['password'];
		}
		mysql_free_result($result);
		
		$lenPw = strlen($pw);
		
		// webleague imported passwords have unknown length limitations 
		if (!$password_md5_encoded)
		{
			if (($lenPw < 10) || ($lenPw > 32))
			{
				require_once '../CMS/navi.inc';
				echo '<p>Passwords must be using less than 32 but more than 9 <abbr title="characters">chars</abbr>.</p>' . "\n";
				$site->dieAndEndPage('');
			}
		} else
		{
			// generate md5 hash of user entered password
			$pw = md5($pw);
		}
		
		if (!(strcmp($password_database, $pw) === 0))
		{
			// TODO: automatically log these cases and lock account for some hours after several unsuccessful tries
			require_once '../CMS/navi.inc';
			echo '<p>Your password does not match the stored password.</p>' . "\n";
			$site->dieAndEndPage('');
		}
		
		// sanity checks passed -> login successful
		
		// standard permissions for user
		$_SESSION['username'] = $loginname;
		$_SESSION['user_logged_in'] = true;
		$internal_login_id = $playerid;
		
		// permissions for private messages
		allow_add_messages();
		allow_delete_messages();
		
		require_once '../CMS/navi.inc';
		echo '<p>Local login successful.</p>' . "\n";
	}
?>
