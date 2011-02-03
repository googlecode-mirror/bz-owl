<?php
	// handle user related data
	class user
	{
		function identifyAccount()
		{
			global $site;
			
			// is the user already registered at this site?
			$query = ('SELECT `id`, '
					  // only need an external login id in case an external login was performed by the viewing player
					  // but look it up to find out if user is global login enabled
					  // NOTE: this is only done as fallback in case the login module does not already handle it
					  . '`external_playerid`, '
					  . '`status` FROM `players` WHERE `name`=' . sqlSafeStringQuotes($_SESSION['username'])
					  // only one player tries to login so only fetch one entry, speeds up login a lot
					  . ' LIMIT 1');
			$result = $site->execute_query('players', $query);
			
			$rows_num_accounts = (int) mysql_num_rows($result);
			$suspended_mode = '';
			$convert_to_external_login = false;
			if ($rows_num_accounts > 0)
			{
				// find out if a username got locked before doing updates
				// e.g. inappropriate username got renamed by admins
				// and then someone else tries to join using this username, 
				// causing a reset of the other username to be reset to the inappropriate one
				while ($row = mysql_fetch_array($result))
				{
					$_SESSION['viewerid'] = (int) $row['id'];
					$suspended_mode = $row['status'];
					if (strcmp(($row['external_playerid']), '') === 0)
					{
						$convert_to_external_login = $site->convert_users_to_external_login();
					}
				}
				mysql_free_result($result);
			} elseif (isset($_SESSION['external_id']) && $_SESSION['external_id'])
			{
				// name is not known, check if id is already in the database
				// $rows_num_accounts === 0
				$query = ('SELECT `id`, `status` FROM `players` WHERE `external_playerid`='
						  . sqlSafeStringQuotes($_SESSION['external_id']) . ' LIMIT 1');
				if (!($result = @$site->execute_query('players', $query)))
				{
					$this->logout;
					$tmpl->done('Could not find out if such a user exists in DB.');
				}
				
				$rows_num_accounts = (int) mysql_num_rows($result);
				if ($rows_num_accounts > 0)
				{
					while ($row = mysql_fetch_array($result))
					{
						$_SESSION['viewerid'] = (int) $row['id'];
						$suspended_mode = $row['status'];
						// reset back to default as there is nothing to do in that regard
						$convert_to_external_login = false;
					}
					mysql_free_result($result);
				}
			}
			
			// logout the user if identify failed
			if ($this->getID() === 0)
			{
				$this->logout();
			}
		}
		
		
		// id > 0 means a user is logged in
		function getID()
		{
			$userid = 0;
			print_r($_SESSION);
			
			if ($this->loggedIn())
			{
				if (isset($_SESSION['viewerid']))
				{
					$userid = $_SESSION['viewerid'];
				}
			}
			return (int) $userid;
		}
		
		function loggedIn()
		{
			return (isset($_SESSION['user_logged_in']) && ($_SESSION['user_logged_in'] === true));
		}
		
		
		// logout the user
		function logout()
		{
			$_SESSION['user_logged_in'] = false;
			unset($_SESSION['user_logged_in']);
			$_SESSION['viewerid'] = -1;
		}
	}
?>
