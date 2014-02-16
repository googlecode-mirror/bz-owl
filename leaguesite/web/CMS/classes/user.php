<?php
	// handle user related data
	class user
	{
		private $country;
		private $lastLoginTimestamp;
		private $location;
		private $origUserId = 0;
		private $userid = 0;
		private $teamid;
		private $status;
		
		public function __construct($userid = 0)
		{
			// instance based on current user should be built
			$this->origUserId = (int) $userid;
			$this->userid = (int) $userid;
			
			// set default permissions for all users one time
			// make sure not to do it more than once because it would reset perms then
			// TODO: getPermission should return default permission instead
			if (!isset($_SESSION['defaultPermissionsSet']))
			{
				$this->setDefaultPermissions();
				$_SESSION['defaultPermissionsSet'] = true;
			}
		}
		
		// add a user to a team
		// checks join permission of user
		// input: a teamid (integer)
		// return: true on success, false otherwise (boolean)
		public function addTeamMembership($teamid)
		{
			$team = new team($teamid);
			
			if (!$team->exists())
			{
				return false;
			}
			
			// find out if team allows anyone to join
			if (static::getAllowedToJoinTeam($teamid))
			{
				// join the team
				$this->teamid = $teamid;
				
				// delete all invitations to team for this user
				// Do not allow to join using one invite then leaving and joining same team using next invite
				invitation::deleteOldInvitations();
				$invitations = invitation::getInvitationsForTeam($this->origUserId, $teamid);
				foreach($invitations AS $invitation)
				{
					$invitation.delete();
				}
				
				return true;
			}
			
			return false;
			
			if ($team->exists($team->getOpen()))
			{
				// anyone can join the team
				// just set the teamid
				$this->teamid = $teamid;
			} else
			{
				// team is closed, valid invitation required
				invitations::deleteOldInvitations();
				$invitations = invitations::getInvitationsForTeam($this->origUserId, $teamid);
				
				// check if user has an invitation
				if (count($invitations) === 0)
				{
					return false;
				}
				$this->teamid = $teamid;
				// delete all invitations of team for user
				// A user is not meant to join using one invite then leaving and joining again
				foreach($invitations AS $invitation)
				{
					$invitation.delete();
				}
			}
			
			return true;
		}
		
		// permanently delete user from database
		public function delete()
		{
			global $db;
			
			$query = $db->prepare('DELETE FROM `users` WHERE `id`=:userid LIMIT 1');
			if ($db->execute($query, array(':userid' => array($this->userid, PDO::PARAM_INT))))
			{
				$query = $db->prepare('DELETE FROM `users_passwords` WHERE `userid`=:userid LIMIT 1');
				if ($db->execute($query, array(':userid' => array($this->userid, PDO::PARAM_INT))))
				{
					$query = $db->prepare('DELETE FROM `users_profile` WHERE `userid`=:userid LIMIT 1');
					if ($db->execute($query, array(':userid' => array($this->userid, PDO::PARAM_INT))))
					{
						$query = $db->prepare('DELETE FROM `visits` WHERE `userid`=:userid LIMIT 1');
						if ($db->execute($query, array(':userid' => array($this->userid, PDO::PARAM_INT))))
						{
							return true;
						}
					}
				}
			}
			return false;
		}
		
		// does user in database exist
		// return: true or false (boolean)
		public function exists()
		{
			global $db;
			
			$query = $db->prepare('SELECT `id` FROM `users` WHERE `id`=:userid LIMIT 1');
			if ($db->execute($query, array(':userid' => array($this->userid, PDO::PARAM_INT))))
			{
				$row = $db->fetchRow($query);
				$db->free($query);
				
				return $row === false ? false : true;
			}
			return false;
		}
		
		// get ids of all users
		// returns array of integers, false on error
		public static function getAllUserIds()
		{
			global $db;
			
			
			$query = $db->prepare('SELECT `id` FROM `users`');
			if ($db->execute($query))
			{
				$ids = array();
				while ($row = $db->fetchRow($query))
				{
					$ids[] = $row['id'];
				}
				$db->free($query);
				return $ids;
			}
			return false;
		}
		
		// can user join a certain team
		// input: teamid (integer) of the team in question
		// output: true = allowed to join, false = not allowed to join (boolean);
		public function getAllowedToJoinTeam($teamid)
		{
			// not logged in user can never join
			if ($this->origUserId === 0)
			{
				return false;
			}
			
			// investigate team
			$team = new team($teamid);
			
			// can not join closed team
			if ($team->getStatus() === 'deleted')
			{
				return false;
			}
			
			require_once(dirname(__FILE__) . '/invitation.php');
			return $this->getPermission('allow_join_any_team') || (new team($teamid))->getOpen() || invitation::getInvitationsForTeam($this->origUserId, $teamid);
		}
		
		// get location of user
		// return: instance of country class, false (boolean) on error
		public function getCountry()
		{
			global $db;
			
			if (isset($this->country))
			{
				return $this->country;
			}
			
			$query = $db->prepare('SELECT `location` FROM `users_profile` WHERE `userid`=:userid LIMIT 1');
			if ($db->execute($query, array(':userid' => array($this->userid, PDO::PARAM_INT))))
			{
				$row = $db->fetchRow($query);
				$db->free($query);
				
				$this->country = (int) $row['location'];
				
				return new \country($this->country);
			}
			return false;
		}
		
		// get instance of current user
		public static function getCurrentUser()
		{
			return new static(static::getCurrentUserId());
		}
		
		// get id of visiting user
		public static function getCurrentUserId()
		{
			$userid = 0;
			
			if (static::getCurrentUserLoggedIn() && isset($_SESSION['viewerid']))
			{
				$userid = $_SESSION['viewerid'];
			}
			
			return (int) $userid;
		}
		
		// is visiting user logged in?
		public static function getCurrentUserLoggedIn()
		{
			return (isset($_SESSION['user_logged_in']) && ($_SESSION['user_logged_in'] === true));
		}
		
		// get the user's id
		// return: internal id (integer) of user
		public function getID()
		{
			return $this->userid;
		}

		// searches for internal id that is mapped to external id
		// input: external id (variable type)
		// return: internal id (integer)
		public static function getIdByExternalId($id)
		{
			global $db;
			
			// internal id is an integer of length 11 by definition
			$internalID = 0;
			
			$query = $db->prepare('SELECT `id` FROM `users` WHERE `external_id`=:id LIMIT 1');
			// the id can be of any data type and of any length
			// just assume it's a 50 characters long string
			$db->execute($query, array(':id' => array($id, PDO::PARAM_STR, 50)));
			
			if ($row = $db->fetchRow($query))
			{
				$internalID = intval($row['id']);
			}
			$db->free($query);
			
			return $internalID;
		}
		
		// find out the internal id bases on username
		// input: username (string)
		// return: array of matching internal ids.
		public static function getIdByName($name)
		{
			global $db;
			
			
			// internal id is an array of integers of length 11 by database definition
			$internalID = array();
			
			// search for name in player database, there can be several matches
			$query = $db->prepare('SELECT `id`, `external_id` FROM `users` WHERE `name`=:name');
			
			// the id can be of any data type and of any length
			// just assume it's a 50 characters long string
			$db->execute($query, array(':name' => array($name, PDO::PARAM_STR, 50)));
			
			while ($row = $db->fetchRow($query))
			{
				$internalID[] = array('id' => intval($row['id']),
									  'external_id' => strval($row['external_id']));
			}
			$db->free($query);
			
			return $internalID;
		}
		
		// time of user registration
		// return: YYYY-MM-DD HH:MM:SS (string) of registration, false (boolean) on error
		public function getJoinTimestampStr()
		{
			global $db;
			
			
			$query = $db->prepare('SELECT `joined` FROM `users_profile` WHERE `userid`=:userid LIMIT 1');
			if ($db->execute($query, array(':userid' => array($this->userid, PDO::PARAM_INT))))
			{
				$row = $db->fetchRow($query);
				$db->free($query);
				
				$this->userJoinedTimestamp = $row['joined'];
				
				return $this->userJoinedTimestamp;
			}
			return false;
		}
		
		// time as string user last logged in
		// return: YYYY-MM-DD HH:MM:SS (string) of last login, false (boolean) on error
		public function getLastLoginTimestampStr()
		{
			global $db;
			
			
			$query = $db->prepare('SELECT `last_login` FROM `users_profile` WHERE `userid`=:userid LIMIT 1');
			if ($db->execute($query, array(':userid' => array($this->userid, PDO::PARAM_INT))))
			{
				$row = $db->fetchRow($query);
				$db->free($query);
				
				$this->lastLoginTimestamp = $row['last_login'];
				
				return $this->lastLoginTimestamp;
			}
			return false;
		}
		
		// obtain status of user
		// valid status values: 'active', 'deleted', 'login disabled', 'banned'
		// returns false on error
		public function getStatus()
		{
			global $db;
			
			
			if (isset($this->status))
			{
				return $this->status;
			}
			
			$query = $db->prepare('SELECT `status` FROM `users` WHERE `id`=:userid LIMIT 1');
			if ($db->execute($query, array(':userid' => array($this->origUserId, PDO::PARAM_INT))))
			{
				$row = $db->fetchRow($query);
				$db->free($query);
				
				$this->status = $row['status'];
				
				return $this->status;
			}
			return false;
		}
		
		public static function setCurrentUserID($id=0)
		{
			$_SESSION['viewerid'] = intval($id);
			$_SESSION['user_logged_in'] = (intval($id) > 0) ?  true : false;
		}
		
		// sets the user's id (integer)
		function setID($userid)
		{
			$this->userid = (int) $userid;
		}
		
		function getTheme()
		{
			global $config;
			
			// set default theme based on site preferences
			if ($this->getMobile())
			{
				$default_theme = $config->getValue('defaultMobileTheme');
			} else
			{
				$default_theme = $config->getValue('defaultTheme');
			}
			
			// find out which theme to use
			$theme = $default_theme;
			if (isset($_SESSION['theme']))
			{
				// use theme chosen this session
				$theme = $_SESSION['theme'];
			} else
			{
				// otherwise use cookie
				if (isset($_COOKIE['theme']))
				{
					// cookies turned on
					$theme = $_COOKIE['theme'];
				}
			}
			
			// clean theme name
			if (!preg_match('/^[0-9A-Za-z ]+$/', $theme))
			{
				$theme = $default_theme;
			}
			
			if (!(file_exists(dirname(dirname(dirname(__FILE__))) . '/themes/' . $theme . '/' . $theme . '.css')))
			{
				// stylesheet in question does not exist, go back to default
				$theme = $default_theme;
				
				// save theme
				$this->saveTheme($theme);
			}
			
			if (strcasecmp($theme, '') == 0)
			{
				// nothing is set, go back to default
				$theme = $default_style;
			}
			
			if (!(isset($_SESSION['themeSaved'])) || !$_SESSION['themeSaved'])
			{
				$this->saveTheme($theme);
			}
			
			return $theme;
		}
		
		// find out if user owns a permission
		// return: permission value (flexible type), false if permission unknown
		function getPermission($permission)
		{
			if (isset($_SESSION[$permission]))
			{
				return $_SESSION[$permission];
			}
			
			return false;
		}
		
		function getName($userid = 0)
		{
			global $config;
			global $db;
			
			// returns current user if no userid specified, otherwise name of user of supplied userid
			if ($userid === 0)
			{
				$id = $this->userid;
			} else
			{
				$id = $userid;
			}
			
			if ($id === 0)
			{
				return $config->getValue('displayedSystemUsername');
			}
			
			
			// collect name from database
			$query = $db->prepare('SELECT `name` FROM `users` WHERE `id`=:id LIMIT 1');
			if ($db->execute($query, array(':id' => array($id, PDO::PARAM_INT))))
			{
				$userName = $db->fetchRow($query);
				$db->free($query);
				
				return $userName['name'];
			}
			
			// error handling: log error and show it in end user visible result
			$db->logError((__FILE__) . ': getName(' . strval($userid) . ') failed: transformed into (.' . $id . ').'); 
			return '$user->getName(' . strval($userid) . ') failed.';
		}
		
		// check if user belongs to a certain team
		// input: teamid (integer)
		// output: true if member, false otherwise
		public function getMemberOfTeam($teamid)
		{
			global $db;
			
			
			// no user is ever member of team with reserved id 0
			if ($teamid === 0)
			{
				return false;
			}
			
			
			// collect teamid of user from database
			$query = $db->prepare('SELECT `teamid` FROM `users` WHERE `id`=:userid LIMIT 1');
			if ($db->execute($query, array(':userid' => array($this->origUserId, PDO::PARAM_INT))))
			{
				$userName = $db->fetchRow($query);
				$db->free($query);
				
				return ((int) $userName['teamid']) === $teamid;
			}
			
			return false;
		}
		
		// find out userids belonging to a team
		// input: teamid (integer)
		// output: array of userids (int), false on error
		public static function getMemberIdsOfTeam($teamid)
		{
			global $db;
			
			$query = $db->prepare('SELECT `id` FROM `users` WHERE `teamid`=:teamid');
			if ($db->execute($query, array(':teamid' => array($teamid, PDO::PARAM_INT))))
			{
				$uids = array();
				while ($row = $db->fetchRow($query))
				{
					$uids[] = $row['id'];
				}
				$db->free($query);
				
				return $uids;
			}
			
			return false;
		}
		
		// find out how many members currently belong to the team
		// if teamid is 0 then it will list number of teamless users
		// return: number of members (integer), false on error (boolean)
		public static function getNumberOfTeamMembers($teamid = 0)
		{
			global $db;
			
			$query = $db->prepare('SELECT COUNT(*) AS `numMembers` FROM `users` WHERE `teamid`=:teamid');
			if ($db->execute($query, array(':teamid' => array($teamid, PDO::PARAM_INT))))
			{
				$numMembers = 0;
				$row = $db->fetchRow($query);
				$numMembers = $row['numMembers'];
				$db->free($query);
				
				return $numMembers;
			}
			
			return false;
		}
		
		// is user in no team
		// output: false if user belongs to a team, false otherwise (bool)
		public function getIsTeamless()
		{
			global $db;
			
			$teamless = false;
			$query = $db->prepare('SELECT `id` FROM `users` WHERE `id`=:userid AND `teamid`=:teamid');
			// if user belongs to team with reserved id 0 then user is in no team
			if ($db->execute($query, array(':userid' => array($this->origUserId, PDO::PARAM_INT),
										   ':teamid' => array(0, PDO::PARAM_INT))))
			{
				if ($db->fetchRow($query))
				{
					$teamless = true;
				}
				$db->free($query);
			}
			
			return $teamless;
		}
		
		// removes user from team
		// return: true if possible, false otherwise
		public function removeTeamMembership($teamid)
		{
			$this->teamid = 0;
			
			return true;
		}
		
		// sets a permission with the specified value
		// if the permission already exists, its value will be overwritten
		// otherwise a new permission with the value will be registered
		function setPermission($permission, $value=true)
		{
			$_SESSION[$permission] = $value;
		}
		
		
		function setDefaultPermissions()
		{
			// sets user's permisssions to default ones
			
			// can change debug sql setting
			$this->setPermission('allow_change_debug_sql', false);
			
			// set all permission to default values
			// permissions for news page
			$this->setPermission('allow_set_different_news_author', false);
			$this->setPermission('allow_add_news', false);
			$this->setPermission('allow_edit_news', false);
			$this->setPermission('allow_delete_news', false);
			
			// permissions for all static pages
			$this->setPermission('allow_edit_static_pages', false);
			
			// permissions for gallery pages
			$this->setPermission('allow_list_gallery', true);
			$this->setPermission('allow_view_gallery', true);
			$this->setPermission('allow_add_gallery', false);
			$this->setPermission('allow_edit_gallery', false);
			$this->setPermission('allow_delete_gallery', false);

			// permissions for bans page
			$this->setPermission('allow_set_different_bans_author', false);
			$this->setPermission('allow_add_bans', false);
			$this->setPermission('allow_edit_bans', false);
			$this->setPermission('allow_delete_bans', false);
			
			// permissions for private messages
			$this->setPermission('allow_add_messages', false);
			// private messages are never supposed to be edited at all by a 3rd person
			$this->setPermission('allow_edit_messages', false);
			$this->setPermission('allow_delete_messages', false);
			
			// team permissions
			$this->setPermission('allow_kick_any_team_members', false);
			$this->setPermission('allow_edit_any_team_profile', false);
			$this->setPermission('allow_delete_any_team', false);
			$this->setPermission('allow_invite_in_any_team', false);
			$this->setPermission('allow_join_any_team', false);
			$this->setPermission('allow_reactivate_teams', false);
			
			// user permissions
			$this->setPermission('allow_edit_any_user_profile', false);
			$this->setPermission('allow_add_admin_comments_to_user_profile', false);
			$this->setPermission('allow_ban_any_user', false);
			
			// visits log permissions
			$this->setPermission('allow_view_user_visits', false);
			
			// match permissions
			$this->setPermission('allow_add_match', false);
			$this->setPermission('allow_edit_match', false);
			$this->setPermission('allow_delete_match', false);
			
			// server tracker permissions
			$this->setPermission('allow_watch_servertracker', false);
			
			// TODO permissions
			$this->setPermission('allow_view_todo', false);
			$this->setPermission('allow_edit_todo', false);
			
			// aux permissions
			$this->setPermission('IsAdmin', false);
		}
		
		
		function saveTheme($theme)
		{
			global $config;
			
			// save theme for two months
			setcookie('theme', $theme, time()+60*60*24*30*2, $config->getValue('basepath'), $config->getValue('domain'), 0);
			
			// save it in session based variable if setting cookie failed
			// it could fail because user did not accept cookie for instance
			$_SESSION['theme'] = $theme;
			
			// mark it saved
			$_SESSION['themeSaved'] = true;
		}
		
		
		function getMobile()
		{
			// this switch should be used sparingly and only in cases where content would not fit on the display
			if (isset($_SERVER['HTTP_USER_AGENT']))
			{
				$browser = $_SERVER['HTTP_USER_AGENT'];
				if (preg_match("/.(Mobile|mobile)/", $browser))
				{
					// mobile browser
					return true;
				} else {
					return false;
				}
			} else
			{
				return false;
			}
		}
		
		public function getLoggedIn()
		{
			return parent::getCurrentUserLoggedIn();
		}
		
		// logout the user
		function logout()
		{
			global $db;
			
			
			// remove user from online user list
			$query = $db->prepare('DELETE FROM `online_users` WHERE `userid`=:uid');
			$db->execute($query, array(':uid' => array($this->getID(), PDO::PARAM_INT)));
			
			// fill login related session variables with default data
			$_SESSION['user_logged_in'] = false;
			$_SESSION['viewerid'] = -1;
			
			// reset permissions back to standard
			$this->setDefaultPermissions();
		}
		
		function removeAllPermissions()
		{
			// delete bzid
			unset($_SESSION['bzid']);
			
			// no external id by default
			$_SESSION['external_id'] = 0;
			
			// assume local login by default
			$this->setPermission('external_login', false);
			
			// can change debug sql setting
			$this->setPermission('allow_change_debug_sql', false);
			
			// set all permission to false by default
			// permissions for news page
			$this->setPermission('allow_set_different_news_author', false);
			$this->setPermission('allow_add_news', false);
			$this->setPermission('allow_edit_news', false);
			$this->setPermission('allow_delete_news', false);
			
			// permissions for all static pages
			$this->setPermission('allow_edit_static_pages', false);
			
			// permissions for bans page
			$this->setPermission('allow_set_different_bans_author', false);
			$this->setPermission('allow_add_bans', false);
			$this->setPermission('allow_edit_bans', false);
			$this->setPermission('allow_delete_bans', false);
			
			// permissions for private messages
			$this->setPermission('allow_add_messages', false);
			// private messages are never supposed to be edited at all by a 3rd person
			$this->setPermission('allow_edit_messages', false);
			$this->setPermission('allow_delete_messages', false);
			
			// team permissions
			$this->setPermission('allow_kick_any_team_members', false);
			$this->setPermission('allow_edit_any_team_profile', false);
			$this->setPermission('allow_delete_any_team', false);
			$this->setPermission('allow_invite_in_any_team', false);
			$this->setPermission('allow_join_any_team', false);
			$this->setPermission('allow_reactivate_teams', false);
			
			
			// user permissions
			$this->setPermission('allow_edit_any_user_profile', false);
			$this->setPermission('allow_add_admin_comments_to_user_profile', false);
			$this->setPermission('allow_ban_any_user', false);
			
			// visits log permissions
			$this->setPermission('allow_view_user_visits', false);
			
			// match permissions
			$this->setPermission('allow_add_match', false);
			$this->setPermission('allow_edit_match', false);
			$this->setPermission('allow_delete_match', false);
			
			// server tracker permissions
			$this->setPermission('allow_watch_servertracker', false);
			
			// TODO permissions
			$this->setPermission('allow_view_todo', false);
			$this->setPermission('allow_edit_todo', false);
			
			// aux permissions
			$this->setPermission('IsAdmin', false);
		}
		
		// update user in db
		// returns true if update is successful
		public function update()
		{
			global $db;
			
			
			// teamid 0 is reserved, not to be used
			// update not possible on new entry
			if ($this->userid === 0 || $this->origUserId === 0)
			{
				return false;
			}
			
			if (isset($this->name))
			{
				$this->name = $this->getName();
			}
			
			// update id
			$query = $db->prepare('UPDATE `users` SET `id`=:id WHERE `id`=:origid');
			if (!$db->execute($query, array(':id' => array($this->userid, PDO::PARAM_INT),
										   ':origid' => array($this->origUserId, PDO::PARAM_INT))))
			{
				return false;
			}
			$this->origUserId = $this->userid;
			
			// update status
			if (isset($this->status))
			{
				$query = $db->prepare('UPDATE `users` SET `status`=:status WHERE `id`=:id');
				if (!$db->execute($query, array(':status' => array($this->status, PDO::PARAM_INT),
											   ':id' => array($this->userid, PDO::PARAM_INT))))
				{
					return false;
				}
			}
			
			// update team memberships
			if (isset($this->teamid))
			{
				$query = $db->prepare('UPDATE `users` SET `teamid`=:teamid WHERE `id`=:id');
				if (!$db->execute($query, array(':teamid' => array($this->teamid, PDO::PARAM_INT),
											   ':id' => array($this->userid, PDO::PARAM_INT))))
				{
					return false;
				}
			}
			
			return true;
		}
	}
?>
