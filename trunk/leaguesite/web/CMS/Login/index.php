<?php
	// do not attach SID to URL as this can cause security problems
	// especially when a user shares an URL that includes a SID
	// use cookies as workaround
	ini_set ('session.use_trans_sid', 0);
	ini_set ('session.name', 'SID');
	ini_set('session.gc_maxlifetime', '7200');
	@session_start();
	
	require_once 'login_module_list.php';
	$module = active_login_modules();
	
	// init siteinfo class if not already done
	if (!isset($site))
	{
		require_once dirname(dirname(__FILE__)) . '/site.php';
		$site = new site();
	}
	
//	require dirname(dirname(__FILE__)) . '/classes/user.php';
//	$user = new user();
	
	
	// magic quote band-aid for POST variables
	if ($site->magic_quotes_on())
	{
		stripslashes($_POST);
	}
	
	
	$tmpl = new template('Login');

	// no need to do anything
	if ($user->loggedIn())
	{
		$tmpl->done('Login was already successful.');
	}
	
	if (!loginTried())
	{
		displayLoginOptions();
	} else
	{
		// process login attempt
		loadLoginModule();
		
		// verify login module data
		if (loginSuccessful())
		{
			$tmpl->addMSG('<p class="first_p">Login was successful!</p>'
						. '<p>Your profile page can be found <a href="../Players/?profile=' . $user->getID() . '">here</a>.</p>');
		} else
		{
			// module itself should create an error message in case of failure
			// however if module error reporting would fail, handle this generic case here
			$tmpl->addMSG('<p class="first_p">Login failed in a login module for unknown reason!</p>');
		}
	}
	
	// perform a logout just in case anything went wrong.
	
	
	// done, render page
	$tmpl->render();
	
	die();
	
	function logoutAndAbort($msg='')
	{
		global $user;
		global $tmpl;
		
		$user->logout();
		$tmpl->done($msg);
	}
	
	function loadLoginModule()
	{
		global $module;
		global $config;
		global $site;
		global $tmpl;
		global $db;
		
		// load modules to check input and buffer output
		// the buffer is neccessary because the modules might need to set cookies for instance
		if (isset($module['bzbb']) && ($module['bzbb']))
		{
			include_once 'bzbb_login/index.php';
		}
		
		if (isset($module['local']) && ($module['local']))
		{
			include_once 'local_login/index.php';
		}
	}
	
	function loginTried()
	{
		if (isset($_POST['loginname']) || isset($_GET['bzbbauth']))
		{
			return true;
		}
		return false;
	}
	
	function loginSuccessful()
	{
		global $user;
		
		$auth_performed = false;
		
		if ($user->loggedIn())
		{
			$auth_performed = true;
			
			
			// do all remaining stuff in one flat function
			// TODO: split it up into seperate functions
			doAllTheRest();
			
			
			// if account can not be identified, it willl logout the user
			if ($user->getID() === 0)
			{
				$user->logout();
			}
		}
		
		
		
		if ($auth_performed && $user->loggedIn())
		{
			return true;
		}
		
		return false;
	}
	
	function displayLoginOptions()
	{
		global $site;
		global $config;
		global $tmpl;
		global $module;
		
		
		if (!(isset($_SESSION['user_logged_in'])) || !($_SESSION['user_logged_in']))
		{
			// user explicitly does not want an external login and confirmed it already
			if (!(isset($_POST['local_login_wanted']) && $_POST['local_login_wanted']))
			{
				if (isset($module['bzbb']) && ($module['bzbb']))
				{
					include 'bzbb_login/login_text.php';
				}
			}
			
			if (!( (isset($_GET['bzbbauth'])) && ($_GET['bzbbauth']) ))
			{
				if (!(isset($_POST['local_login_wanted']) && $_POST['local_login_wanted']) && isset($module['local']) && ($module['local']))
				{
					$tmpl->addMSG('<strong>or</strong>');
					$tmpl->addMSG($tmpl->return_self_closing_tag('br'));
					$tmpl->addMSG($tmpl->return_self_closing_tag('br'));
				}
			}
			
			if (isset($module['local']) && ($module['local']))
			{
				include_once 'local_login/login_text.php';
			}
		}
	}
	
	function doAllTheRest()
	{
		global $site;
		global $tmpl;
		global $user;
		global $config;
		global $db;
		
		// set the date and time
		date_default_timezone_set($config->value('timezone'));
		
		// only perform the operation if user logs in and not on reload
		if ($user->loggedIn())
		{
			// delete expired invitations
			$query = 'DELETE LOW_PRIORITY FROM `invitations` WHERE `expiration`<=';
			$query .= sqlSafeStringQuotes(date('Y-m-d H:i:s'));
			if (!$result = $db->SQL($query))
			{
				$msg .= 'Could not delete expired invitations.';
				logoutAndAbort($msg);
			}
		}
		
		// in no case an empty username is allowed
		if (isset($_SESSION['username']) && (strlen($_SESSION['username']) < 2))
		{
			logoutAndAbort('<p>Any username is required to be at least 2 chars long</p>');
		}
		
		if ((isset($_SESSION['user_logged_in'])) && ($_SESSION['user_logged_in']))
		{
			// is the user already registered at this site?
			$query = ('SELECT `id`, '
					  // only need an external login id in case an external login was performed by the viewing player
					  // but look it up to find out if user is global login enabled
					  // NOTE: this is only done as fallback in case the login module does not already handle it
					  . ' `external_playerid`, '
					  . '`status` FROM `players` WHERE `name`=' . sqlSafeStringQuotes($_SESSION['username'])
					  // only one player tries to login so only fetch one entry, speeds up login a lot
					  . ' LIMIT 1');
			if (!($result = $db->SQL($query)))
			{
				$msg = ('Could not get account data for external_playerid ' . sqlSafeString($_SESSION['external_id']) . '.');
				logoutAndAbort($msg);
			}
			
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
				$query = 'SELECT `id`, `status` FROM `players` WHERE `external_playerid`=' . sqlSafeStringQuotes($_SESSION['external_id']) . ' LIMIT 1';
				if (!($result = @$db->SQL($query)))
				{
					$msg = ('Could not find out if player already has an account with id ' . sqlSafeString($_SESSION['external_id']) . ' (renamed user?).');
					logoutAndAbort($msg);
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
			
			// check if it is a false positive (no password stored in database)
			// this can happen in case the user got imported from another database
			if ($convert_to_external_login)
			{
				$query = ('SELECT `password` FROM `players_passwords` WHERE `players_passwords`.`playerid`='
						  . sqlSafeStringQuotes($_SESSION['viewerid']) . ' LIMIT 1');
				if (!($result = $db->SQL($query)))
				{
					$msg = ('Could not find out if password is set for local account with id ' . sqlSafeString($_SESSION['external_id']) . '.');
					logoutAndAbort($msg);
				}
				
				$rows_num_accounts = (int) mysql_num_rows($result);
				while ($row = mysql_fetch_array($result))
				{
					if (strcmp(($row['password']), '') === 0)
					{
						// yes, it was indeed a false positive
						$convert_to_external_login = false;
					}
				}
				mysql_free_result($result);
			}
			
			if (isset($_SESSION['external_id']) && $_SESSION['external_id'] && ($convert_to_external_login))
			{
				$msg .= '<form action="' . baseaddress() . 'Login/'. '" method="post">' . "\n";
				$msg .= 'The account you tried to login to does not support ';
				if (isset($module['bzbb']) && ($module['bzbb']))
				{
					$msg .= 'the my.bzflag.org/bb/ (global) login';
				} else
				{
					$msg .= 'external logins';
				}
				$msg .= '. You may update the account first by using your local login.</p>' . "\n";
				$msg .= '<p>In case someone other than you owns the local account then you need to contact an admin to solve the problem.' . "\n";
				
				$output_buffer2 = '';
				ob_start();
				$account_needs_to_be_converted = true;
				include_once('../CMS/local_login/login_text.inc');
				// write output buffer to message and clean buffer
				$msg .= ob_get_clean() . "\n";
				ob_end_clean();
				
				logoutAndAbort($msg);
			}
			
			if (isset($_SESSION['viewerid']) && ((int) $_SESSION['viewerid'] === (int) 0)
				&& ((strcmp($suspended_mode, 'login disabled') === 0) || strcmp($suspended_mode, 'banned') === 0))
			{
				$msg .= 'There is a user that got banned/disabled by admins with the same username in the database already. Please choose a different username!';
				logoutAndAbort($msg);
			}
			// dealing only with the current player from this point on
			
			// cache this variable to speed up further access to the value
			$user_id = $user->getID();
			
			
			// suspended mode: active; deleted; login disable; banned
			if (strcmp($suspended_mode, 'deleted') === 0)
			{
				$suspended_mode = 'active';
				$query = 'UPDATE `players` SET `status`=' . sqlSafeStringQuotes($suspended_mode);
				$query .= ' WHERE `id`=' . sqlSafeStringQuotes($user_id) . ' LIMIT 1';
				if (!($result = $db->SQL($query)))
				{
					$msg .= 'Could not reactivate deleted account with id ' . sqlSafeString($user_id) . '.';
					logoutAndAbort($msg);
				}
			}
			if (strcmp($suspended_mode, 'login disabled') === 0)
			{
				$msg .= 'Login for this account was disabled by admins.';
				// skip updates if the user has a disabled login (inappropriate callsign for instance)
				logoutAndAbort($msg);
			}
			if (strcmp($suspended_mode, 'banned') === 0)
			{
				$msg .=  'Admins specified you should be banned from the entire site.';
				// FIXME: BAN FOR REAL!!!!
				// skip updates if the user is banned (inappropriate callsign for instance)
				logoutAndAbort($msg);
			}
			unset($suspended_mode);
			
			if (isset($_SESSION['external_login']) && ($_SESSION['external_login']))
			{
				if ($rows_num_accounts === 0)
				{
					$msg .= '<p class="first_p">Adding user to database…</p>' . "\n";
					// example query: INSERT INTO `players` (`external_playerid`, `teamid`, `name`) VALUES ('1194', '0', 'ts')
					$query = 'INSERT INTO `players` (`external_playerid`, `teamid`, `name`) VALUES (';
					$query .= sqlSafeStringQuotes($_SESSION['external_id']) . ', ' . "'" . '0' . "'" . ', ' . sqlSafeStringQuotes(htmlent($_SESSION['username'])) .')';
					if ($insert_result = $db->SQL($query))
					{
						$query = 'SELECT `id` FROM `players` WHERE `external_playerid`=' . sqlSafeStringQuotes($_SESSION['external_id']);
						if ($id_result = $db->SQL($query))
						{
							$rows = mysql_num_rows($id_result);
							while($row = mysql_fetch_array($id_result))
							{
								$_SESSION['viewerid'] = (int) $row['id'];
							}
							mysql_free_result($id_result);
							$user_id = $user->getID();
							if ($rows === 1)
							{
								// user inserted without problems
								$msg .= '<p>You have been added to the list of players at this site. Thanks for visiting our site.</p>';
								
								// write welcome mail!
								
								// lock messages_storage because of mysql_insert_id() usage
								$query = 'LOCK TABLES `messages_storage` WRITE';
								if (!($result = $db->SQL($query)))
								{
									// query was bad, error message was already given in $db->SQL(...)
									$msg .= 'Could not lock the messages_storage table.';
									logoutAndAbort($msg);
								}
								
								// create the welcome message in database
								$query = 'INSERT INTO `messages_storage` (`author_id`, `subject`, `timestamp`, `message`, `from_team`, `recipients`) VALUES ';
								$query .= '(' . sqlSafeStringQuotes ('0');
								$query .= ', ' . sqlSafeStringQuotes('Welcome!') . ', ' . sqlSafeStringQuotes(date('Y-m-d H:i:s'));
								$query .= ', ' . sqlSafeStringQuotes('Welcome and thanks for registering at this website!' . "\n"
																	 . 'In the FAQ you can find the most important informations about organising and playing matches.'
																	 . "\n\n" .
																	 'See you on the battlefield.');
								$query .= ', ' . sqlSafeStringQuotes('0') . ', ' . sqlSafeStringQuotes($user_id) . ')';
								if (!($result = $db->SQL($query)))
								{
									// query was bad, error message was already given in $db->SQL(...)
									$msg .= 'Could not create the welcome mail.';
									logoutAndAbort($msg);
								}
								
								// get the msgid generated from database
								$msgid = mysql_insert_id($db->getConnection());
								
								// unlock messages_storage because mysql_insert_id() was used and is no longer needed
								$query = 'UNLOCK TABLES';
								if (!($result = $db->SQL($query)))
								{
									$msg .= 'Could not unlock the messages_storage table.';
									logoutAndAbort($msg);
								}
								// send the invitation message to user
								$query = 'INSERT INTO `messages_users_connection` (`msgid`, `playerid`, `in_inbox`, `in_outbox`) VALUES ';
								$query .= '(' . sqlSafeStringQuotes($msgid) . ', ' . sqlSafeStringQuotes($user_id);
								$query .= ', ' . sqlSafeStringQuotes('1');
								$query .= ', ' . sqlSafeStringQuotes('0') . ')';
								if (!($result = $db->SQL($query)))
								{
									$msg .= 'Could not send the welcome mail.';
									logoutAndAbort($msg);
								}
								
								// message sent
							} else
							{
								$msg .= ('Unfortunately there seems to be a database problem and thus a unique id can not be retrieved for your account. '
										 . ' Please try again later.</p>' . "\n"
										 . '<p>If the problem persists please tell an admin');
								logoutAndAbort($msg);
							}
						}
					} else
					{
						// apologise, the user is new and we all like newbies
						$msg .= ('Unfortunately there seems to be a database problem and thus you (id='
								 . htmlent($user_id)
								 . ') can not be added to the list of players at this site. '
								 . 'Please try again later.</p>' . "\n"
								 . '<p>If the problem persists please report it to an admin');
						logoutAndAbort($msg);
					}
					
					// adding player profile entry
					$query = 'INSERT INTO `players_profile` (`playerid`, `joined`, `location`) VALUES (';
					$query .= sqlSafeStringQuotes($user_id) . ', ' . sqlSafeStringQuotes(date('Y-m-d H:i:s'));
					$query .= ', ' . sqlSafeStringQuotes('1') . ')';
					if (!($db->SQL($query)))
					{
						$msg .= ('Unfortunately there seems to be a database problem and thus creating your profile page (id='
								 . htmlent($user_id)
								 . ') failed. Please report this to admins.');
						logoutAndAbort($msg);
					}
				} else
				{
					// user is not new, update his callsign with new callsign supplied from external login
					
					// check for collisions with all local accounts
					$query = ('SELECT `external_playerid` FROM `players` WHERE `name`='
							  . sqlSafeStringQuotes(htmlent($_SESSION['username'])));
					if (!($result = $db->SQL($query)))
					{
						$msg = ('Could not find out if external_playerid is set for all accounts having name ' . sqlSafeString(htmlent($_SESSION['username'])) . '.');
						logoutAndAbort($msg);
					}
					$local_name_collisions = false;
					while ($row = mysql_fetch_array($result))
					{
						if (strcmp(($row['external_playerid']), '') === 0)
						{
							// yes, it was indeed a false positive
							$local_name_collisions = true;
						}
					}
					mysql_free_result($result);
					
					if ($local_name_collisions)
					{
						// non-resolvable collisions found, reset username of current user
						$query = ('SELECT `name` FROM `players` WHERE `external_playerid`='
								  . sqlSafeStringQuotes($_SESSION['external_id']) . ' LIMIT 1');
						if (!($result = $db->SQL($query)))
						{
							$msg = ('Could not find out if external_playerid is set for all accounts having name ' . sqlSafeString(htmlent($_SESSION['username'])) . '.');
							logoutAndAbort($msg);
						}
						while ($row = mysql_fetch_array($result))
						{
							$_SESSION['username'] = htmlent_decode($row['name']);
						}
						mysql_free_result($result);
						// print out a warning to the user, mentioning the non-updated callsign
						$msg .= '<p>Your callsign was not updated because there is already another local account in the database with the same callsign.</p>';
					} else
					{
						// update name in case there is no collision
						$query = 'UPDATE `players` SET `name`=' . sqlSafeStringQuotes(htmlent($_SESSION['username']));
						if (isset($_SESSION['external_login']) && ($_SESSION['external_login']))
						{
							$query .= ' WHERE `external_playerid`=' . sqlSafeStringQuotes($_SESSION['external_id']);
						} else
						{
							$query .= ' WHERE `id`=' . sqlSafeStringQuotes($_SESSION['viewerid']);
						}
						// each user has only one entry in the database
						$query .= ' LIMIT 1';
						if (!($update_result = $db->SQL($query)))
						{
							$msg .= ('Unfortunately there seems to be a database problem which prevents the system from updating your callsign (id='
									 . htmlent($user_id)
									 . '). Please report this to an admin.</p>');
							logoutAndAbort($msg);
						}
					}
					unset($local_name_collisions);
				}
			} else
			{
				// local login
				if (isset($internal_login_id))
				{
					if (isset($convert_to_external_login) && $convert_to_external_login)
					{
						// user is not new, update his callsign with new external playerid supplied from login
						
						// external_playerid was empty, set it to the external value obtained by bzidtools
						// create a new cURL resource
						$ch = curl_init();
						
						// set URL and other appropriate options
						$url = ('http://my.bzflag.org/bzidtools2.php?action=id&value=' . urlencode(strtolower($_SESSION['username'])));
						curl_setopt($ch, CURLOPT_URL, $url);
						curl_setopt($ch, CURLOPT_HEADER, 0);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
						
						// grab URL and pass it to the browser
						$output = curl_exec($ch);
						
						// close cURL resource, and free up system resources
						curl_close($ch);
						
						// update the entry with the result from the bzidtools2.php script
						if ((strlen($output) > 9) && (strcmp(substr($output, 0, 9), 'SUCCESS: ') === 0))
						{
							// example query: UPDATE `players` SET `external_playerid`='external_id' WHERE `id`='internal_id';
							$query = 'UPDATE `players` SET `external_playerid`=' . sqlSafeStringQuotes(htmlent(substr($output, 9)));
							$query .= ' WHERE `id`=' . sqlSafeStringQuotes($internal_login_id);
							// each user has only one entry in the database
							$query .= ' LIMIT 1';
							if (!($update_result = $db->SQL($query)))
							{
								$msg = ('Unfortunately there seems to be a database problem'
										. ' which prevents the system from setting your external playerid (id='
										. htmlent($user_id)
										. '). Please report this to an admin.');
								logoutAndAbort($msg);
							}
							$msg .= 'Congratulations, you enabled ';
							if (isset($module['bzbb']) && ($module['bzbb']))
							{
								$msg .= 'the my.bzflag.org/bb/ (global) login';
							} else
							{
								$msg .= 'external logins';
							}
							$msg .= ' for this account.' . "\n";
						} else
						{
							$msg = ('Unfortunately the bzidtools2.php script failed'
									. ' which prevents the system from setting your external playerid (id='
									. htmlent($user_id)
									. '). The bzidtool2.php call was '
									. sqlSafeStringQuotes($url)
									. '. Please report this to an admin.');
							logoutAndAbort($msg);
						}
					}
					
					echo $msg;
					if ($site->force_external_login_when_trying_local_login())
					{
						logoutAndAbort('');
					}
				}
			}
			
			// bzflag auth specific code, thus use bzid value directly
			if (isset($_SESSION['bzid']) && isset($_SESSION['username']))
			{
				// find out if someone else once used the same callsign
				// update the callsign from the other player in case he did
				// example query: SELECT `external_playerid` FROM `players` WHERE (`name`='ts') AND (`external_playerid` <> '1194')
				// AND (`external_playerid` <> '') AND (`status`='active' OR `status`='deleted')
				// FIXME: sql query should be case insensitive (SELECT COLLATION(VERSION()) returns utf8_general_ci)
				$query = 'SELECT `external_playerid` FROM `players` WHERE (`name`=' . sqlSafeStringQuotes(htmlent($_SESSION['username'])) . ')';
				$query .= ' AND (`external_playerid` <> ' . sqlSafeStringQuotes($_SESSION['external_id']) . ')';
				// do not update users with local login
				$query .= ' AND (`external_playerid` <> ' . "''" . ')';
				// skip updates for banned or disabled accounts (inappropriate callsign for instance)
				$query .= ' AND (`status`=' . sqlSafeStringQuotes('active') . ' OR `status`=' . sqlSafeStringQuotes('deleted') . ')';
				if ($result = $db->SQL($query))
				{
					$errno = 0;
					$errstr = '';
					while($row = mysql_fetch_array($result))
					{
						// create a new cURL resource
						$ch = curl_init();
						
						// set URL and other appropriate options
						curl_setopt($ch, CURLOPT_URL, 'http://my.bzflag.org/bzidtools2.php?action=name&value=' . sqlSafeString((int) $row['external_playerid']));
						curl_setopt($ch, CURLOPT_HEADER, 0);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
						
						// grab URL and pass it to the browser
						$output = curl_exec($ch);
						
						// close cURL resource, and free up system resources
						curl_close($ch);
						
						// update the entry with the result from the bzidtools2.php script
						if ((strlen($output) > 10) && (strcmp(substr($output, 0, 9), 'SUCCESS: ') === 0))
						{
							// example query: UPDATE `players` SET `name`='moep' WHERE `external_playerid`='external_id';
							$query = 'UPDATE `players` SET `name`=' . sqlSafeStringQuotes(htmlent(substr($output, 9)));
						} else
						{
							// example query: UPDATE `players` SET `name`='moep ERROR: SELECT username_clean FROM bzbb3_users WHERE user_id=uidhere' WHERE `external_playerid`='external_id';
							$query = 'UPDATE `players` SET `name`=' . sqlSafeStringQuotes(htmlent($_SESSION['username']) . ' ' . htmlent($output));
						}
						$query .= ' WHERE `external_playerid`=' . sqlSafeStringQuotes((int) $row['bzid']);
						
						if (!($update_result = $db->SQL($query)))
						{
							// trying to update the players old callsign failed
							$msg = ('Unfortunately there seems to be a database problem which prevents the system from updating the old callsign of another user.'
									. ' However you curently own that callsign so now there will be two users with the callsign in the table and people will'
									. 'have problems to distinguish you two!</p>'
									. '<p>Please report this to an admin.');
							logoutAndAbort($msg);
						}
					}
				} else
				{
					$msg = ('Finding other members who had the same name '
							. sqlSafeStringQuotes(htmlent($_SESSION['username']))
							. 'failed. This is a database problem. Please report this to an admin!');
					logoutAndAbort($msg);
				}
			}
		}
		
		
		if (isset($_SESSION['user_logged_in']) && ($_SESSION['user_logged_in']))
		{
			// update last login entry
			$query = 'UPDATE `players_profile` SET `last_login`=' . sqlSafeStringQuotes(date('Y-m-d H:i:s'));
			if (isset($_SESSION['external_login']) && ($_SESSION['external_login']))
			{
				$query .= ' WHERE `playerid`=' . sqlSafeStringQuotes($user_id);
			} else
			{
				$query .= ' WHERE `playerid`=' . sqlSafeStringQuotes($internal_login_id);
			}
			// only one user account needs to be updated
			$query .= ' LIMIT 1';
			$db->SQL($query);		
		}
		
		
		if ((!(isset($_SESSION['user_in_online_list'])) || !($_SESSION['user_in_online_list'])) &&  ((isset($_SESSION['user_logged_in'])) && ($_SESSION['user_logged_in'])))
		{
			$_SESSION['user_in_online_list'] = true;
			$curDate = sqlSafeStringQuotes(date('Y-m-d H:i:s'));
			
			// find out if table exists
			$query = 'SHOW TABLES LIKE ' . "'" . 'online_users' . "'";
			$result = @mysql_query($query);
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
				$query = 'SELECT * FROM `online_users` WHERE `playerid`=' . sqlSafeStringQuotes($user_id);
				$result = mysql_query($query);
				$rows = mysql_num_rows($result);
				// done
				mysql_free_result($result);
				
				$onlineUsers = false;
				if ($rows > 0)
				{
					// already logged in
					$query = 'DELETE FROM `online_users` WHERE `playerid`=' . sqlSafeStringQuotes($user_id);
					// ignore result
					$result = mysql_query($query);
					if (!($result))
					{
						$msg .= 'Could not remove already logged in user from online user table. Database broken?';
						logoutAndAbort($msg);
					}
				}
				
				// insert logged in user into online_users table
				$query = 'INSERT INTO `online_users` (`playerid`, `username`, `last_activity`) Values';
				$query .= '(' . sqlSafeStringQuotes($user_id) . ', ' . sqlSafeStringQuotes(htmlent($_SESSION['username'])) . ', ' . $curDate . ')';	
				$db->SQL($query);
				
				// do maintenance in case a user still belongs to a deleted team (database problem)
				$query = 'SELECT `teams_overview`.`deleted` FROM `teams_overview`, `players` WHERE `teams_overview`.`teamid`=`players`.`teamid` AND `players`.`id`=';
				$query .= sqlSafeStringQuotes($user_id);
				$query .= ' AND `teams_overview`.`deleted`>' . sqlSafeStringQuotes('2');
				// only the player that just wants to log-in is dealt with
				$query .= ' LIMIT 1';
				if ($result = $db->SQL($query))
				{
					while($row = mysql_fetch_array($result))
					{
						if ((int) $row['deleted'] === 1)
						{
							// mark who was where, to easily restore an unwanted team deletion
							$query = 'UPDATE `players` SET `last_teamid`=`players`.`teamid`';
							$query .= ', `teamid`=' . sqlSafeStringQuotes('0');
							$query .= ' WHERE `id`=' . sqlSafeStringQuotes($user_id);
							$db->SQL($query);
						}
					}
					mysql_free_result($result);
				}
				
				// insert to the visits log of the player
				$ip_address = getenv('REMOTE_ADDR');
				$host = gethostbyaddr($ip_address);
				$query = ('INSERT INTO `visits` (`playerid`,`ip-address`,`host`,`forwarded_for`,`timestamp`) VALUES ('
						  . sqlSafeStringQuotes($user_id)
						  . ', ' . sqlSafeStringQuotes(htmlent($ip_address))
						  . ', ' . sqlSafeStringQuotes(htmlent($host))
						  // try to detect original ip-address in case proxies are used
						  . ',' . sqlSafeStringQuotes(htmlent(getenv('HTTP_X_FORWARDED_FOR')))
						  . ', ' . $curDate
						  . ')');
				$db->SQL($query);
			}
		}
	}
?>