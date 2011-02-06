<?php
	require_once dirname(dirname(__FILE__)) . '/permissions.php';
			
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
			$tmpl->done('User names must be using less than 50 but more than 0 <abbr title="characters">chars</abbr>.' . "\n");
		}
		
		// get player id
		$query = 'SELECT `id`';
		if ($config->value('forceExternalLoginOnly'))
		{
			$query .= ', `external_playerid` ';
		}
		$query .= ' FROM `players` WHERE `name`=' . sqlSafeStringQuotes($loginname);
		// only one player tries to login so only fetch one entry, speeds up login a lot
		$query .= ' LIMIT 1';
		// execute query
		$result = $db->SQL($query);
		
		
		// initialise with reserved player id 0 (no player)
		$playerid = (int) 0;
		$convert_to_external_login = true;
		while($row = mysql_fetch_array($result))
		{
			$playerid = $row['id'];
			if ($config->value('forceExternalLoginOnly') && !(strcmp(($row['external_playerid']), '') === 0))
			{
				$convert_to_external_login = false;
			}
		}
		mysql_free_result($result);
		
		// local login tried but external login forced in settings
		if (!$convert_to_external_login && $config->value('forceExternalLoginOnly'))
		{
			$msg = '<span class="unread_messages">You already enabled ';
			if (isset($module['bzbb']) && ($module['bzbb']))
			{
				$url = urlencode(baseaddress() . 'Login/' . '?bzbbauth=%TOKEN%,%USERNAME%');
				$msg .= '<a href="' . htmlspecialchars('http://my.bzflag.org/weblogin.php?action=weblogin&url=') . $url;						
				$msg .= '">global (my.bzflag.org/bb/) login</a>';
			} else
			{
				$msg .= 'external logins';
			}
			$msg .= ' for this account.</span>' . "\n";
			
			$tmpl->done($msg);
		}
		
		
		if (intval($playerid) === 0)
		{
			$user->logout();
			
			$tmpl->done('The specified user is not registered. You may want to <a href="./">try logging in again</a>.');
		}
		
		// get password from database in order to compare it with the user entered password
		$query = 'SELECT `password`, `password_encoding` FROM `players_passwords` WHERE `playerid`=' . sqlSafeStringQuotes($playerid);
		// only one player tries to login so only fetch one entry, speeds up login a lot
		$query .= ' LIMIT 1';
		
		// execute query
		if (!($result = @$site->execute_query('players_passwords', $query)))
		{
			// query failed
			$tmpl->done('Could not retrieve password for you in database.');
		}
		
		// initialise without md5 (hash functions could cause collisions despite passwords will not match)
		$password_md5_encoded = false;
		// no password is default and will not match any password set
		$password_database = '';
		while($row = mysql_fetch_array($result))
		{
			if (strcmp($row['password_encoding'],'md5') === 0)
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
				$tmpl->done('<p class="first_p">Passwords must be using less than 32 but more than 9 <abbr title="characters">chars</abbr>.'
					  . ' You may want to <a href="./">try logging in again</a>.</p>' . "\n");
			}
		} else
		{
			// generate md5 hash of user entered password
			$pw = md5($pw);
		}
		
		if (!(strcmp($password_database, $pw) === 0))
		{
			// TODO: automatically log these cases and lock account for some hours after several unsuccessful tries
			$tmpl->done('Your password does not match the stored password.'
						. ' You may want to <a href="./">try logging in again</a>.' . "\n");
		}
		
		// sanity checks passed -> login successful
		
		// standard permissions for user
		$_SESSION['username'] = $loginname;
		$_SESSION['user_logged_in'] = true;
		$internal_login_id = $playerid;
		
		// permissions for private messages
		allow_add_messages();
		allow_delete_messages();
		
		// username and password did match but there might be circumstances
		// where the caller script decides the login was not successful, though
	}
?>
