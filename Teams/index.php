<?php
	ini_set ('session.use_trans_sid', 0);
	ini_set ('session.name', 'SID');
	ini_set('session.gc_maxlifetime', '7200');
	session_start();
	$path = (pathinfo(realpath('./')));
	$name = $path['basename'];
	
	$display_page_title = $name;
	require_once (dirname(dirname(__FILE__)) . '/CMS/index.inc');
	
	function writeLogo()
	{
		global $profile;
		global $connection;
		global $site;
		
		if (isset($_POST['logo_url']))
		{
			$allowedExtensions = array('.png', '.bmp', '.jpg', '.gif', 'jpeg');
			$logo_url = sqlSafeString($_POST['logo_url']);
			if ((in_array(substr($logo_url, -4), $allowedExtensions)) && (substr($logo_url, 0, 7) == 'http://'))
			{
				// image url exists and has a valid file extension
				$query = 'UPDATE `teams_profile` SET `logo_url`=' . sqlSafeStringQuotes($logo_url);
				$query .= ' WHERE `teamid`=' . sqlSafeStringQuotes($profile);
				if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
				{
					// query was bad, error message was already given in $site->execute_query(...)
					$site->dieAndEndPage('');
				}
			} else
			{
				if (!(strcmp(($_POST['logo_url']), '') === 0))
				{
					echo '<p>Error: Skipping logo setting: Not allowed URL or extension.</p>';
				}
			}
		}
	}
	
	
	function checkNumberRows($result, $site)
	{				
		if (mysql_num_rows($result) > 1)	
		{
			$site->dieAndEndPage('Error: There was more than one user or team with the same id. This should never happen.');
		}
	}
	
	require realpath('../CMS/navi.inc');
	
	$site = new siteinfo();
	
	$connection = $site->connect_to_db();
	$randomkey_name = 'randomkey_team';
	$viewerid = (int) getUserID();
	
	
	$allow_edit_any_team_profile = false;
	$allow_invite_in_any_team = false;
	if (isset($_GET['profile']) || isset($_GET['edit']))
	{
		if (isset($_SESSION['allow_edit_any_team_profile']))
		{
			if (($_SESSION['allow_edit_any_team_profile']) === true)
			{
				$allow_edit_any_team_profile = true;
			}
		}
		if (isset($_SESSION['allow_invite_in_any_team']))
		{
			if (($_SESSION['allow_invite_in_any_team']) === true)
			{
				$allow_invite_in_any_team = true;
			}
		}
	}
	
	$allow_reactivate_teams = false;
	if (isset($_SESSION['allow_reactivate_teams']) && ($_SESSION['allow_reactivate_teams'] == true))
	{
		$allow_reactivate_teams = true;
	}
	
	$team_description = '(no description)';
	if (isset($_GET['join']) || isset($_GET['edit']) || isset($_GET['profile']))
	{
		$query = 'SELECT `description`';
		if (isset($_GET['edit']))
		{
			$query .= ',`logo_url`';
		}
		$query .= 'FROM `teams_profile` WHERE `teamid`=' . "'";
	}
	if (isset($_GET['join']))
	{
		$query .= sqlSafeString((int) $_GET['join']);
		
	}
	if (isset($_GET['edit']))
	{
		$query .= sqlSafeString((int) $_GET['edit']);
		
	}
	if (isset($_GET['profile']))
	{
		$query .= sqlSafeString((int) $_GET['profile']);
	}
	
	if (isset($_GET['join']) || isset($_GET['edit']) || isset($_GET['profile']))
	{
		$query .= "'" . ' LIMIT 0,1';
		if (!($result = @$site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
		{
			// query was bad, error message was already given in $site->execute_query(...)
			$site->dieAndEndPage('');
		}
		
		while($row = mysql_fetch_array($result))
		{
			$team_description = $row['description'];
			if (isset($_GET['edit']))
			{
				$logo_url = $row['logo_url'];
			}
		}
	}
	
	// sanity check to find out if a team does exist
	if (isset($_GET['profile']) || isset($_GET['edit']))
	{
		$profile = 0;
		if (isset($_GET['profile']))
		{
			$profile = (int) $_GET['profile'];
		} else
		{
			$profile = (int) $_GET['edit'];
		}
		
		if ($profile < 0)
		{
			echo '<a class="button" href="./">overview</a>' . "\n";
			echo '<p>This team does not exist!</p>';
			$site->dieAndEndPage('');
		}		
		
		if ($profile === 0)
		{
			echo '<a class="button" href="./">overview</a>' . "\n";
			echo '<p>The team id 0 is reserved for teamless players and thus no team with that id could ever exist.</p>' . "\n";
			$site->dieAndEndPage('');
		}
		
		
		
		$query = 'SELECT `id` FROM `teams` WHERE `id`=' . "'" . sqlSafeString($profile) . "'" . ' LIMIT 1';
		if (!($result = @$site->execute_query($site->db_used_name(), 'teams', $query, $connection)))
		{
			echo '<a class="button" href="./">overview</a>' . "\n";
			$site->dieAndEndPage('<p>It seems like the list of known teams can not be accessed for an unknown reason.</p>');
		}
		
		$rows = (int) mysql_num_rows($result);
		mysql_free_result($result);
		if ($rows ===0)
		{
			// someone tried to view the profile of a non existing user
			echo '<a class="button" href="./">overview</a>' . "\n";
			echo '<p>You tried to view or edit a non existing team!</p>';
			$site->dieAndEndPage('');
		}
	}
	
	// create new teams if requested
	if (isset($_GET['create']) && ($viewerid > 0))
	{
		if (isset($_POST['confirmed']) && (isset($_POST['edit_team_name']) || isset($_POST['team_description'])))
		{
			echo '<a class="button" href="./">overview</a>' . "\n";
			
			// someone is trying to break the form
			// TODO: implement preview
			if (($_POST['confirmed'] < 1) || ($_POST['confirmed'] > 2))
			{
				$site->dieAndEndPage('Your (id='. sqlSafeString($viewerid) . ') attempt to insert wrong data into the form was detected.');
			}
			
			$new_randomkey_name = '';
			if (isset($_POST['key_name']))
			{
				$new_randomkey_name = html_entity_decode($_POST['key_name']);
			}
			$randomkeysmatch = $site->compare_keys($randomkey_name, $new_randomkey_name);						
			if (!($randomkeysmatch))
			{
				echo '<p>The key did not match. It looks like you came from somewhere else.</p>';
				$site->dieAndEndPage('');
			}
			
			// only teamless players can create a new team
			$query = 'SELECT `teamid` FROM `players` WHERE `id`=';
			$query .= "'" . sqlSafeString($viewerid) . "'";
			$query .= ' AND `teamid`=' . "'" . '0' . "'" . ' LIMIT 0,1';
			if (!($result = @$site->execute_query($site->db_used_name(), 'players, teams', $query, $connection)))
			{
				// query was bad, error message was already given in $site->execute_query(...)
				$site->dieAndEndPage('');
			}
			
			// result must be only one row
			checkNumberRows($result, $site);
			$teamless_player_wants_to_create_a_team = false;
			while($row = mysql_fetch_array($result))
			{
				// this should always be true due to the query
				// do the check nevertheless because it might give some extra security
				if ((int) $row['teamid'] === 0)
				{
					$teamless_player_wants_to_create_a_team = true;
				}
			}
			mysql_free_result($result);
			
			if (!($teamless_player_wants_to_create_a_team))
			{
				$site->dieAndEndPage('You (id=' . sqlSafeString($viewerid) . ') can not create a new team because you are already member of a team.');
			}
			
			// check for team name
			if (!(isset($_POST['edit_team_name'])))
			{
				$site->dieAndEndPage('You (id=' . sqlSafeString($viewerid) . ') did not specify a team name while creating a new team.');

			}
			
			// is the team name already used?
			$query = 'SELECT `name` FROM `teams` WHERE `name`=' . "'" . sqlSafeString(htmlent($_POST['edit_team_name'])) . "'" . ' LIMIT 1';
			if (!($result = @$site->execute_query($site->db_used_name(), 'teams', $query, $connection)))
			{
				// query was bad, error message was already given in $site->execute_query(...)
				$site->dieAndEndPage('');
			}
			
			if ((int) mysql_num_rows($result) > 0)
			{
				mysql_free_result($result);
				// team name already used -> do not create team with that name
				echo '<p>The team was not created because there is already a team with that name in the database.</p>' . "\n";
				$site->dieAndEndPage('');
			}
			mysql_free_result($result);
			
			// is the player name already used?
			$query = 'SELECT `teamid` FROM `players` WHERE `id`=' . "'" . sqlSafeString($viewerid) . "'" . ' LIMIT 1';
			if (!($result = @$site->execute_query($site->db_used_name(), 'players', $query, $connection)))
			{
				// query was bad, error message was already given in $site->execute_query(...)
				$site->dieAndEndPage('');
			}
			
			if ((int) mysql_num_rows($result) > 0)
			{
				$tmp_team_id = (int) 0;
				while($row = mysql_fetch_array($result))
				{
					$tmp_team_id = $row['teamid'];
				}				
				if ($tmp_team_id <> 0)
				{
					mysql_free_result($result);
					// the supposed leader is already in a team -> do not create team with him as leader
					echo '<p>The team was not created because the team leader already belongs to a team.</p>' . "\n";
					$site->dieAndEndPage('');
				}
				unset($tmp_team_id);
			}
			mysql_free_result($result);
			
			// create the team itself
			$query = 'INSERT INTO `teams` (`name`, `leader_playerid`) VALUES (' . "'" . sqlSafeString($_POST['edit_team_name']) . "'" . ', ' . "'" . sqlSafeString($viewerid) . "'" . ')';
			if (!($result = @$site->execute_query($site->db_used_name(), 'teams', $query, $connection)))
			{
				// query was bad, error message was already given in $site->execute_query(...)
				$site->dieAndEndPage('');
			}
			
			$query = 'SELECT `id` FROM `teams` WHERE `leader_playerid`=' . "'" . sqlSafeString($viewerid) . "'";
			if (!($result = @$site->execute_query($site->db_used_name(), 'teams', $query, $connection)))
			{
				// query was bad, error message was already given in $site->execute_query(...)
				$site->dieAndEndPage('');
			}
			
			// result must be only one row
			checkNumberRows($result, $site);
			$new_team_id = -1;
			while($row = mysql_fetch_array($result))
			{
				$new_team_id = (int) $row['id'];
			}
			mysql_free_result($result);
			
			if (($new_team_id === (-1)) || ($new_team_id === 0))
			{
				$site->dieAndEndPage('Error: The team created by player (id=' . sqlSafeString($viewerid) . ') has no team id (id=' . sqlSafeString($new_team_id) . ').');
			}
			
			// set up new team permissions
			$query = 'INSERT INTO `teams_permissions` (`teamid`) VALUES (' . "'" . sqlSafeString($new_team_id) . "'" . ')';
			if (!($result = @$site->execute_query($site->db_used_name(), 'teams_permissions', $query, $connection)))
			{
				// query was bad, error message was already given in $site->execute_query(...)
				$site->dieAndEndPage('');
			}
			
			// set up new team overview
			$any_teamless_player_can_join = '0';
			if (isset($_POST['any_teamless_player_can_join']))
			{
				$any_teamless_player_can_join = '1';
			}
			$query = 'INSERT INTO `teams_overview` (`teamid`, `any_teamless_player_can_join`) VALUES (' . "'" . sqlSafeString($new_team_id) . "'" . ', ' . "'";
			$query .= sqlSafeString($any_teamless_player_can_join) . "'" . ')';
			if (!($result = @$site->execute_query($site->db_used_name(), 'teams_overview', $query, $connection)))
			{
				// query was bad, error message was already given in $site->execute_query(...)
				$site->dieAndEndPage('');
			}
			
			// set up team profile
			// set the date and time (for team creation timestamp)
			date_default_timezone_set('Europe/Berlin');
			$query = 'INSERT INTO `teams_profile` (`teamid`, `description`, `created`) VALUES (' . sqlSafeStringQuotes($new_team_id) . ', ';
			$query .= sqlSafeStringQuotes(bbcode($_POST['team_description'])) . ', ' . sqlSafeStringQuotes(date('Y-m-d H:i:s')) . ')';
			if (!($result = @$site->execute_query($site->db_used_name(), 'players', $query, $connection)))
			{
				// query was bad, error message was already given in $site->execute_query(...)
				$site->dieAndEndPage('');
			}
			
			// the founder is now member of the team
			$query = 'UPDATE `players` SET `teamid`=' . "'" . sqlSafeString($new_team_id) . "'";
			$query .= ' WHERE `id`=' . "'" . sqlSafeString($viewerid) . "'";
			if (!($result = @$site->execute_query($site->db_used_name(), 'players', $query, $connection)))
			{
				// query was bad, error message was already given in $site->execute_query(...)
				$site->dieAndEndPage('');
			}
			
			// write logo
			writeLogo();
			
			echo '<p>Your new team was created successfully.</p>';
			$site->dieAndEndPage('');
		}
		echo '<a class="button" href="./">Cancel & back to overview</a>' . "\n";
		echo '<form enctype="application/x-www-form-urlencoded" method="post" action="?create">' . "\n";
		echo '<div><input type="hidden" name="confirmed" value="1"></div>' . "\n";
		
		$new_randomkey_name = $randomkey_name . microtime();
		$new_randomkey = $site->set_key($new_randomkey_name);
		echo '<div><input type="hidden" name="key_name" value="' . htmlentities($new_randomkey_name) . '"></div>' . "\n";
		echo '<div><input type="hidden" name="' . htmlentities($randomkey_name) . '" value="';
		echo urlencode(($_SESSION[$new_randomkey_name])) . '"></div>' . "\n";
		
		// team name
		echo '<p><label for="edit_team_name">Team name: </label>' . "\n";
		echo '<input type="text" maxlength="30" size="30" name="edit_team_name" value="enter team name here" id="edit_team_name"';
		echo ' onFocus="if(this.value==' . "'" . 'enter team name here' . "'" . ') this.value=' . "'" . "'" . '"';
		echo ' onblur="if(this.value==' . "'" . "'" . ') this.value=' . "'" . 'enter team name here' . "'" . '"';
		echo '></p>' . "\n";
		
		// team leader is automatically the creator of the team
		
		// any teamless player allowed to join? default to yes.
		echo '<p><label for="any_teamless_player_can_join">Can any teamless player join the team?</label> <input type="checkbox" name="any_teamless_player_can_join" value="';
		echo '1' . '" id="any_teamless_player_can_join" checked="checked"></p>' . "\n";
		
		// team description
		echo '<div><label for="team_description">Edit team description: </label><span><textarea id="team_description" rows="10" cols="50" name="team_description">';
		echo $team_description . '</textarea></span></div>' . "\n";
		
		// logo/avatar url
		echo '<p><label class="player_edit" for="edit_avatar_url">Avatar URL: </label>';
		
		// quell warning about not initialised variable
		if (!(isset($logo_url)))
		{
			$logo_url = '';
		}
		$site->write_self_closing_tag('input id="edit_avatar_url" type="text" name="logo_url" maxlength="200" size="60" value="'.$logo_url.'"');
		echo '</p>';
		
		echo '<div><input type="submit" name="edit_team_data" value="Submit new team creation" id="send"></div>' . "\n";
		echo '</form>' . "\n";
		$site->dieAndEndPage('');
	}
	
	if (isset($_GET['reactivate']))
	{
		echo '<a class="button" href="./">overview</a>' . "\n";
		
		// no anon team deletion
		if ($viewerid < 1)
		{
			echo '<p>You must login to reactivate the team.</p>' . "\n";
			$site->dieAndEndPage('');
		}
		
		if (!$allow_reactivate_teams)
		{
			echo '<p>You have no permission to reactivate any team</p>';
			$site->dieAndEndPage('');
		}
		
		// get a full list of deleted teams
		// teams_overview.deleted: 0 new; 1 active; 2 deleted; 3 revived
		$query = 'SELECT `teams`.`id`,`teams`.`name` FROM `teams`,`teams_overview`';
		$query .= ' WHERE (`teams_overview`.`deleted`=' . "'" . sqlSafeString('2') . "'";
		$query .= ' AND `teams`.`id`=`teams_overview`.`teamid`)';
		if (!($result_teams = @$site->execute_query($site->db_used_name(), 'teams, teams_overview', $query, $connection)))
		{
			// query was bad, error message was already given in $site->execute_query(...)
			$site->dieAndEndPage('');
		}
		if ((int) mysql_num_rows($result_teams) < 1)
		{
			// not deleted team yet
			echo '<p>There is not a single deleted team in the database</p>' . "\n";
			$site->dieAndEndPage('');
		}
		
		// get a full list of teamless, not deleted players
		// suspended: 0 active; 1 maint-deleted; 2 disabled; 3 banned
		$query = 'SELECT `id`,`name` FROM `players`';
		$query .= ' WHERE (`teamid`=' . "'" . sqlSafeString('0') . "'";
		$query .= ' AND `suspended`<>' . "'" . sqlSafeString('1') . "'" . ')';
		if (!($result_players = @$site->execute_query($site->db_used_name(), 'players', $query, $connection)))
		{
			// query was bad, error message was already given in $site->execute_query(...)
			$site->dieAndEndPage('');
		}
		if ((int) mysql_num_rows($result_players) < 1)
		{
			// not deleted team yet
			echo '<p>There is not a single teamless player in the database</p>' . "\n";
			$site->dieAndEndPage('');
		}
		
		$profile = 0;
		if (isset($_POST['reactivate_team_id']))
		{
			$profile = (int) $_POST['reactivate_team_id'];
		}
		$leader_profile = 0;
		if (isset($_POST['reactivate_team_new_leader_id']))
		{
			$leader_profile = (int) $_POST['reactivate_team_new_leader_id'];
		}
		if (($profile > 0) && ($leader_profile > 0))
		{
			// team to be revived has been chosen
			
			$new_randomkey_name = '';
			if (isset($_POST['key_name']))
			{
				$new_randomkey_name = html_entity_decode($_POST['key_name']);
			}
			$randomkeysmatch = $site->compare_keys($randomkey_name, $new_randomkey_name);			
			if (!($randomkeysmatch))
			{
				echo '<a class="button" href="./">overview</a>' . "\n";
				echo '<p>The key did not match. It looks like you came from somewhere else.</p>';
				$site->dieAndEndPage('');
			}
			
			// no further information about total deleted teams and total teamless players needed
			mysql_free_result($result_players);
			mysql_free_result($result_teams);
			
			// check if the team is deleted
			$query = 'SELECT `teams`.`id`,`teams`.`name` FROM `teams`,`teams_overview`';
			$query .= ' WHERE (`teams`.`id`=' . sqlSafeString(htmlentities($profile));
			$query .= ' AND `teams_overview`.`deleted`=' . sqlSafeStringQuotes('2');
			$query .= ' AND `teams`.`id`=`teams_overview`.`teamid`)';
			if (!($result_teams = @$site->execute_query($site->db_used_name(), 'teams, teams_overview', $query, $connection)))
			{
				// query was bad, error message was already given in $site->execute_query(...)
				$site->dieAndEndPage('');
			}
			if (((int) mysql_num_rows($result_teams) < 1) || ((int) mysql_num_rows($result_teams) > 1))
			{
				// not deleted team yet
				echo '<p class="first_p">There must be exactly one deleted team spefified to revive a team</p>' . "\n";
				$site->dieAndEndPage('');
			}
			
			// check if the new team leader is teamless
			$query = 'SELECT `id`,`name` FROM `players`';
			$query .= ' WHERE (`id`=' . "'" . sqlSafeString(htmlentities($leader_profile)) . "'";
			$query .= ' AND `teamid`=' . "'" . sqlSafeString('0') . "'";
			$query .= ' AND `suspended`<>' . sqlSafeStringQuotes('1') . ')';
			if (!($result_players = @$site->execute_query($site->db_used_name(), 'players', $query, $connection)))
			{
				// query was bad, error message was already given in $site->execute_query(...)
				$site->dieAndEndPage('');
			}
			if (((int) mysql_num_rows($result_players) < 1) || ((int) mysql_num_rows($result_players) > 1))
			{
				// not deleted team yet
				echo '<p class="first_p">There must be exactly one teamless player spefified to revive a team</p>' . "\n";
				$site->dieAndEndPage('');
			}
			// initalise variable with error, will be overwritten if a name was found
			$username = '(error: no username found)';
			while($row = mysql_fetch_array($result_players))
			{
				$username = $row['name'];
			}
			
			// reactivate the team
			$query = 'UPDATE `teams_overview` SET `deleted`=' . sqlSafeStringQuotes('1');
			$query .= ',`member_count`=' . sqlSafeStringQuotes('1');
			$query .= ' WHERE `teamid`=' . "'" . sqlSafeString($profile) . "'";
			if (!($result = @$site->execute_query($site->db_used_name(), 'teams_overview', $query, $connection)))
			{
				$site->dieAndEndPage('The team with id ' . sqlSafeString($profile) .
									 ' could not be revived by player with id ' . sqlSafeString($viewerid). '!');
			}
			
			// set the user's new teamid
			$query = 'UPDATE `players` SET `teamid`=' . "'" . sqlSafeString($profile) . "'";
			$query .= ' WHERE `id`=' . "'" . sqlSafeString($leader_profile). "'";
			if (!($result = @$site->execute_query($site->db_used_name(), 'players', $query, $connection)))
			{
				$site->dieAndEndPage('The player with id ' . sqlSafeString($leader_profile) .
									 ' could not be set to be a member of team with id ' .
									 sqlSafeString($profile). ' by player with id ' . sqlSafeString($viewerid). '!');
			}
			
			// all done
			echo '<p class="first_p">The team was successfully reactivated with ' . htmlent($username) . ' as leader!</p>' . "\n";
			$site->dieAndEndPage('');
		}
		
		
		$team_name_list = Array();
		$team_id_list = Array();
		while($row = mysql_fetch_array($result_teams))
		{
			$team_name_list[] = $row['name'];
			$team_id_list[] = $row['id'];
		}
		mysql_free_result($result_teams);
		
		$list_team_id_and_name = Array();
		
		$list_team_id_and_name[] = $team_id_list;
		$list_team_id_and_name[] = $team_name_list;
		
		$n = ((int) count($team_id_list)) - 1;
		
		echo '<form enctype="application/x-www-form-urlencoded" method="post" action="./?reactivate">' . "\n";
		echo '<p><label for="reactivate_team">Select the team to be reactivated:</label>' . "\n";
		echo '<span><select id="reactivate_team" name="reactivate_team_id">' . "\n";
		
		$n = ((int) count($team_id_list)) - 1;
		for ($i = 0; $i <= $n; $i++)
		{
			echo '<option value="';
			// no htmlentities because team id 0 is reserved
			echo $list_team_id_and_name[0][$i];
			if (isset($leader_of_team_with_id) && ((int) $list_team_id_and_name[0][$i] === $leader_of_team_with_id))
			{
				echo '" selected="selected';
			}
			echo '">' . $list_team_id_and_name[1][$i];
			echo '</option>' . "\n";
		}
		
		echo '</select></span>' . "\n";
				
		echo '<p><label for="reactivate_team_new_leader">Select the new team leader:</label>' . "\n";
		echo '<span><select id="reactivate_team_new_leader" name="reactivate_team_new_leader_id">' . "\n";
		$player_name_list = Array();
		$player_id_list = Array();
		while($row = mysql_fetch_array($result_players))
		{
			$player_name_list[] = $row['name'];
			$player_id_list[] = $row['id'];
		}
		mysql_free_result($result_players);
		
		$list_player_id_and_name = Array();
		
		$list_player_id_and_name[] = $player_id_list;
		$list_player_id_and_name[] = $player_name_list;
		
		$n = ((int) count($player_id_list)) - 1;
		for ($i = 0; $i <= $n; $i++)
		{
			echo '<option value="';
			// no htmlentities because team id 0 is reserved
			echo $list_player_id_and_name[0][$i];
			echo '">' . $list_player_id_and_name[1][$i];
			echo '</option>' . "\n";
		}
		
		echo '</select></span>' . "\n";		
		
		// protection against links
		$new_randomkey_name = $randomkey_name . microtime();
		$new_randomkey = $site->set_key($new_randomkey_name);
		echo '<div><input type="hidden" name="key_name" value="' . htmlentities($new_randomkey_name) . '"></div>' . "\n";
		echo '<div><input type="hidden" name="' . htmlentities($randomkey_name) . '" value="';
		echo urlencode(($_SESSION[$new_randomkey_name])) . '"></div>' . "\n";
		
		echo '<div style="display:inline"><input type="submit" name="reactivate_team" value="Reactivate the team" id="send"></div>' . "\n";
		echo '</form>' . "\n";		
		
		$site->dieAndEndPage('');
	}
	
	if (isset($_GET['join']) && ($viewerid > 0))
	{
		$join_team_id = (int) $_GET['join'];
		
		// silently ignore if someone tries to join nonexisting team
		if ($join_team_id === 0)
		{
			$site->dieAndEndPage('');
		}
		
		// find out if player is already member of a team
		$query = 'SELECT `teamid` FROM `players` WHERE `id`=' . "'" . sqlSafeString($viewerid) . "'" . ' LIMIT 0,1';
		if (!($result = @$site->execute_query($site->db_used_name(), 'teams', $query, $connection)))
		{
			// query was bad, error message was already given in $site->execute_query(...)
			$site->dieAndEndPage('');
		}
		checkNumberRows($result, $site);
		
		// basically following a simple link could let a teamless player join a team
		// FIXME: maybe use a form for joining to prevent the latter
		while($row = mysql_fetch_array($result))
		{
			if (!((int) $row['teamid'] === 0))
			{
				echo '<p>You are already member of a team and thus you can not join another team.</p>';
				$site->dieAndEndPage('');
			}
		}
		mysql_free_result($result);
		
		// FIXME: collects way too much info?
		$query = 'SELECT `teams`.`id`,  `teams`.`name`, `teams_overview`.`any_teamless_player_can_join` FROM `teams`, `teams_overview`';
		$query .= ' WHERE `teams`.`id`=' . "'" . sqlSafeString($join_team_id) . "'";
		$query .= ' AND `teams`.`id`=`teams_overview`.`teamid`';
		if (!($result = @$site->execute_query($site->db_used_name(), 'teams', $query, $connection)))
		{
			// query was bad, error message was already given in $site->execute_query(...)
			$site->dieAndEndPage('');
		}
		checkNumberRows($result, $site);
		
		$team_name = '(no name)';
		if (mysql_num_rows($result) === 1)
		{
			$allowed_to_join = 0;
			$invite_available = false;
			while($row = mysql_fetch_array($result))
			{
				$allowed_to_join = (int) $row['any_teamless_player_can_join'];
				$team_name = $row['name'];
			}
			mysql_free_result($result);
			
			// team not open for all players
			if (!($allowed_to_join === 1))
			{
				// check for invite!
				$query = 'SELECT `invited_playerid`, `expiration` FROM `invitations` WHERE `teamid`=' . "'" . sqlSafeString($join_team_id) . "'";
				$query .= ' AND `invited_playerid`=' . "'" . sqlSafeString($viewerid) . "'" . ' ORDER BY `expiration`';
				if (!($result = @$site->execute_query($site->db_used_name(), 'invitations', $query, $connection)))
				{
					// query was bad, error message was already given in $site->execute_query(...)
					$site->dieAndEndPage('Could not get find out the player id and expiration date list of invited players');
				}
				while($row = mysql_fetch_array($result))
				{
					$allowed_to_join = (int) 1;
					$invite_available = true;
				}
				mysql_free_result($result);
				
				if (!($allowed_to_join === 1))
				{
					echo '<p>You have no permission to join that team</p>';
					$site->dieAndEndPage('');
				}
			}
		} else
		{
			$site->dieAndEndPage('There was more than one team with the same id ('. sqlSafeString($join_team_id) . ' or the team did not exist. This should never happen.');
		}
		
		if (isset($_POST['confirmed']) && ((int) $_POST['confirmed'] === 1))
		{
			echo '<a class="button" href="./">overview</a>' . "\n";
			
			$new_randomkey_name = '';
			if (isset($_POST['key_name']))
			{
				$new_randomkey_name = html_entity_decode($_POST['key_name']);
			}
			$randomkeysmatch = $site->compare_keys($randomkey_name, $new_randomkey_name);						
			if (!($randomkeysmatch))
			{
				echo '<p>The key did not match. It looks like you came from somewhere else.</p>';
				$site->dieAndEndPage('');
			}
			
			if ($allowed_to_join === 1)
			{
				$query = 'UPDATE `players` SET `teamid`=' . "'" . sqlSafeString($join_team_id) . "'";
				$query .= ' WHERE `id`=' . "'" . sqlSafeString($viewerid) . "'";
				if (!($result = @$site->execute_query($site->db_used_name(), 'players', $query, $connection)))
				{
					// query was bad, error message was already given in $site->execute_query(...)
					$site->dieAndEndPage('There was a problem preventing you from joining the team with id ' . htmlentities($join_team_id));
				}
				
				// update member count of team
				$query = 'UPDATE `teams_overview` SET `member_count`=(SELECT COUNT(*) FROM `players` WHERE `players`.`teamid`=';
				$query .= "'" . sqlSafeString($join_team_id) . "'" . ') WHERE `teamid`=';
				$query .= "'" . sqlSafeString($join_team_id) . "'";
				if (!($result = @$site->execute_query($site->db_used_name(), 'teams', $query, $connection)))
				{
					$site->dieAndEndPage('There was a problem updating member count for the team with id ' . htmlentities($join_team_id));
				}
				
				echo '<p>You successfully joined the team ' . $team_name . '!</p>';
				
				// check if a invite was used to join
				if ($invite_available)
				{
					// invite was indeed use, delete the invite
					$query = 'DELETE LOW_PRIORITY FROM `invitations` WHERE `teamid`=';
					$query .= "'" . sqlSafeString($join_team_id) . "'";
					$query .= ' AND `invited_playerid`=' . "'" . sqlSafeString($viewerid) . "'";
					if (!$result = $site->execute_query($site->db_used_name(), 'invitations', $query, $connection))
					{
						$site->dieAndEndPage('Could not delete used invitation for player with id ' . sqlSafeString($viewerid) . '.');
					}
					
				}
			}
			$site->dieAndEndPage('');
		}
		
		echo '<a class="button" href="./">Cancel & back to overview</a>' . "\n";
		$query = 'SELECT `name` FROM `teams`';
		$query .= ' WHERE `id`=' . "'" . sqlSafeString($join_team_id) . "'";
		if (!($result = @$site->execute_query($site->db_used_name(), 'teams', $query, $connection)))
		{
			// query was bad, error message was already given in $site->execute_query(...)
			$site->dieAndEndPage('');
		}
		checkNumberRows($result, $site);
		
		$team_name = '(no name)';
		if (mysql_num_rows($result) === 1)
		{
			$allowed_to_join = 0;
			while($row = mysql_fetch_array($result))
			{
				$team_name = $row['name'];
			}
			mysql_free_result($result);
		} else
		{
			$site->dieAndEndPage('There was more than one team with the same id ('. sqlSafeString($join_team_id) . ' or the team did not exist. This should never happen.');
		}
		echo '<form enctype="application/x-www-form-urlencoded" method="post" action="?join=' . urlencode($join_team_id) . '">' . "\n";
		echo '<div><input type="hidden" name="confirmed" value="1"></div>' . "\n";
		
		$new_randomkey_name = $randomkey_name . microtime();
		$new_randomkey = $site->set_key($new_randomkey_name);
		echo '<div><input type="hidden" name="key_name" value="' . htmlentities($new_randomkey_name) . '"></div>' . "\n";
		echo '<div><input type="hidden" name="' . htmlentities($randomkey_name) . '" value="';
		echo urlencode(($_SESSION[$new_randomkey_name])) . '"></div>' . "\n";
		echo '<p style="display:inline">Do you really want to join the team ' . $team_name . '?</p>' . "\n";
		echo '<div style="display:inline"><input type="submit" name="join_team" value="Join the team" id="send"></div>' . "\n";
		echo '</form>' . "\n";
		$site->dieAndEndPage('');
	}
	
	$team_leader_id = 0;
	$teamid = 0;
	if (isset($_GET['remove']) || isset($_GET['profile']) || isset($_GET['edit']) || isset($_GET['delete']))
	{
		$can_delete_any_team = false;
		if (isset($_SESSION['allow_delete_any_team']) && ($_SESSION['allow_delete_any_team']))
		{
			$can_delete_any_team = true;
		}
		// find out if the user accessing the page has editing permissions
		if (isset($_GET['profile']))
		{
			$teamid = (int) ($_GET['profile']);
		}
		if (isset($_GET['edit']))
		{
			$teamid = (int) ($_GET['edit']);
		}
		if (isset($_GET['delete']))
		{
			$teamid = (int) ($_GET['delete']);
		}
		if (isset($_GET['remove']))
		{
			// we don't know the team, find it out by tracing it using the playerid
			$playerid = (int) $_GET['remove'];
			$query = 'SELECT `teams`.`id` FROM `players`, `teams` WHERE `players`.`id`=';
			$query .= "'" . sqlSafeString($playerid) . "'";
			$query .= ' AND `players`.`teamid` = `teams`.`id` LIMIT 0,1';
			if (!($result = @$site->execute_query($site->db_used_name(), 'players, teams', $query, $connection)))
			{
				// query was bad, error message was already given in $site->execute_query(...)
				$site->dieAndEndPage('');
			}
			
			while($row = mysql_fetch_array($result))
			{
				$teamid = (int) $row['id'];
			}
			mysql_free_result($result);
		}
		
		$query = 'SELECT `leader_playerid` FROM `teams` WHERE `id`=' . "'" .  sqlSafeString($teamid) . "'" . ' LIMIT 0,1';
		if (!($result = @$site->execute_query($site->db_used_name(), 'teams', $query, $connection)))
		{
			// query was bad, error message was already given in $site->execute_query(...)
			$site->dieAndEndPage('');
		}
		
		$rows = mysql_num_rows($result);
		if ($rows > 0)
		{
			while($row = mysql_fetch_array($result))
			{
				$team_leader_id = (int) ($row['leader_playerid']);
			}
		}
		
		$display_user_kick_from_team_buttons = false;
		if (($viewerid > 0) && (($viewerid === $team_leader_id) || (get_allow_kick_any_team_members() === true)))
		{
			// the user has permisson to kick a member from team
			$display_user_kick_from_team_buttons = true;
		}
		mysql_free_result($result);
	}
	
	// someone wants to delete a team
	if (isset($_GET['delete']))
	{
		// no anon team deletion
		if ($viewerid < 1)
		{
			echo '<a class="button" href="./">overview</a>' . "\n";
			echo '<p>You must login to delete the team.</p>' . "\n";
			$site->dieAndEndPage('');
		}
		
		if (!($team_leader_id === $viewerid) && !$can_delete_any_team)
		{
			$site->dieAndEndPage('You (id=' . sqlSafeString($viewerid) . ') attempted to delete a team (id=' . sqlSafeString($teamid) . ') without having permission to do that.');
		}
		
		if (isset($_POST['confirmed']))
		{
			echo '<a class="button" href="./">overview</a>' . "\n";
			if (($_POST['confirmed'] < 1) || ($_POST['confirmed'] > 2))
			{
				$site->dieAndEndPage('Your (id='. sqlSafeString($viewerid) . ') attempt to delete a team by manipulating the form was detected.');
			}
			
			$new_randomkey_name = '';
			if (isset($_POST['key_name']))
			{
				$new_randomkey_name = html_entity_decode($_POST['key_name']);
			}
			$randomkeysmatch = $site->compare_keys($randomkey_name, $new_randomkey_name);
			
			if (!($randomkeysmatch))
			{
				echo '<p>The key did not match. It looks like you came from somewhere else.</p>';
				$site->dieAndEndPage('');
			}
			
			// do the team deletion
			// the team is only marked as deleted because team history is kept
			$query = 'SELECT `deleted` FROM `teams_overview`';
			$query .= ' WHERE `teamid`=' . "'" . sqlSafeString($teamid) . "'";
			$query .= ' LIMIT 1';
			if (!($result = @$site->execute_query($site->db_used_name(), 'teams_overview', $query, $connection)))
			{
				// query was bad, error message was already given in $site->execute_query(...)
				$site->dieAndEndPage('');
			}
			
			while($row = mysql_fetch_array($result))
			{
				if (((int) $row['deleted'] === 1) || ((int) $row['deleted'] === 2) || ((int) $row['deleted'] === 3))
				{
					// mark deleted team as active
					$query = 'UPDATE `teams_overview` SET `deleted`=' . "'" . sqlSafeString('2') . "'";
					$query .= ' ,`member_count`=' . "'" . '0' . "'";
					$query .= ' WHERE `teamid`=' . "'" . sqlSafeString($teamid) . "'";
					if (!($result_update = @$site->execute_query($site->db_used_name(), 'teams_overview', $query, $connection)))
					{
						// query was bad, error message was already given in $site->execute_query(...)
						$site->dieAndEndPage('');
					}
					
				}
				
				if ((int) $row['deleted'] === 0)
				{
					// delete (for real) the new team
					$query = 'DELETE FROM `teams` WHERE `id`=' . "'" . ($teamid) . "'";
					// execute query, ignore result
					@$site->execute_query($site->db_used_name(), 'teams', $query, $connection);
					$query = 'DELETE FROM `teams_overview` WHERE `teamid`=' . "'" . ($teamid) . "'";
					// execute query, ignore result
					@$site->execute_query($site->db_used_name(), 'teams_overview', $query, $connection);
					$query = 'DELETE FROM `teams_permissions` WHERE `teamid`=' . "'" . ($teamid) . "'";
					// execute query, ignore result
					@$site->execute_query($site->db_used_name(), 'teams_permissions', $query, $connection);
					$query = 'DELETE FROM `teams_profile` WHERE `teamid`=' . "'" . ($teamid) . "'";
					// execute query, ignore result
					@$site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection);	
				}
			}
			
			// mark who was where, to easily restore an unwanted team deletion
			$query = 'UPDATE `players` SET `last_teamid`=' . "'" . sqlSafeString($teamid) . "'";
			$query .= ', `teamid`=' . "'" . sqlSafeString('0') . "'";
			$query .= ' WHERE `teamid`=' . "'" . sqlSafeString($teamid) . "'";
			if (!($result_update = @$site->execute_query($site->db_used_name(), 'players', $query, $connection)))
			{
				// query was bad, error message was already given in $site->execute_query(...)
				$site->dieAndEndPage('');
			}
			
			// always remember who deleted what
			$site->dieAndEndPage('You (id=' . sqlSafeString($viewerid) . ') successfully deleted the team (id=' . sqlSafeString($teamid) . ').');
		}
		// display a form, asking for confirmation of team deletion
		echo '<a class="button" href="./">overview</a>' . "\n";
		
		echo '<form enctype="application/x-www-form-urlencoded" method="post" action="?delete=' . urlencode($teamid) . '">' . "\n";
		echo '<div><input type="hidden" name="confirmed" value="1"></div>' . "\n";
		
		$new_randomkey_name = $randomkey_name . microtime();
		$new_randomkey = $site->set_key($new_randomkey_name);
		echo '<div><input type="hidden" name="key_name" value="' . htmlentities($new_randomkey_name) . '"></div>' . "\n";
		echo '<div><input type="hidden" name="' . htmlentities($randomkey_name) . '" value="';
		echo urlencode(($_SESSION[$new_randomkey_name])) . '"></div>' . "\n";
		echo '<p style="display:inline">Do you really want to delete the team?</p>' . "\n";
		echo '<div style="display:inline"><input type="submit" name="delete_team" value="Delete the team" id="send"></div>' . "\n";
		echo '</form>' . "\n";
		$site->dieAndEndPage('');
	}
	
	// someone wants to edit a team
	if (isset($_GET['edit']))
	{
		if ($viewerid < 1)
		{
			echo '<a class="button" href="./">overview</a>' . "\n";
			echo '<p>You must login to edit the team page.</p>' . "\n";
			$site->dieAndEndPage('');
		}
		
		$teamid = (int) $_GET['edit'];
		if ($teamid < 1)
		{
			echo '<a class="button" href="./">overview</a>' . "\n";
			$site->dieAndEndPage('You (id=' . sqlSafeString($viewerid) . ') did not specify a team to edit.');
		}
		
		if (!(($viewerid === $team_leader_id) || $allow_edit_any_team_profile))
		{
			echo '<a class="button" href="./">overview</a>' . "\n";
			$site->dieAndEndPage('You (id=' . sqlSafeString($viewerid) . ') have no permissions to edit the team profile (id=' . sqlSafeString($teamid) . ').');
		}		
		
		$team_name = '(no name)';
		$query = 'SELECT `name` FROM `teams` WHERE `id`=' . "'" .  sqlSafeString($teamid) . "'" . ' LIMIT 0,1';
		if (!($result = @$site->execute_query($site->db_used_name(), 'teams', $query, $connection)))
		{
			// query was bad, error message was already given in $site->execute_query(...)
			$site->dieAndEndPage('');
		}
		
		// check if team exist
		$rows = (int) mysql_num_rows($result);
		if ($rows < (int) 1)
		{
			echo '<a class="button" href="./">overview</a>' . "\n";
			$site->dieAndEndPage('You (id=' . sqlSafeString($viewerid) . ') tried to edit a nonexisting team (id=' . sqlSafeString($teamid) . ').');
		}
		
		// only one team must exist for each id
		// if more than one team exists for a given id then it is no user error but a database problem
		if ($rows > (int) 1)
		{
			echo '<a class="button" href="./">overview</a>' . "\n";
			$site->dieAndEndPage('There is more than one team with the id ' . sqlSafeString($teamid) . ' in the database! This is a database problem, please report it to admins!');
		}
		
		while($row = mysql_fetch_array($result))
		{
			$team_name = $row['name'];
		}
		mysql_free_result($result);
		
		if (isset($_POST['confirmed']) && (isset($_POST['edit_team_name']) || isset($_POST['team_description'])))
		{
			echo '<a class="button" href="./">overview</a>' . "\n";
			
			// someone is trying to break the form
			// TODO: implement preview
			if (($_POST['confirmed'] < 1) || ($_POST['confirmed'] > 2))
			{
				$site->dieAndEndPage('Your (id='. sqlSafeString($viewerid) . ') attempt to insert wrong data into the form was detected.');
			}
						
			// general permissions given, do further sanity checks
			$new_randomkey_name = '';
			if (isset($_POST['key_name']))
			{
				$new_randomkey_name = html_entity_decode($_POST['key_name']);
			}
			$randomkeysmatch = $site->compare_keys($randomkey_name, $new_randomkey_name);
			
			if (!($randomkeysmatch))
			{
				echo '<p>The key did not match. It looks like you came from somewhere else.</p>';
				$site->dieAndEndPage('');
			}
			
			// update the team name
			if (isset($_POST['edit_team_name']))
			{
				// is the team name already used?
				$query = 'SELECT `id` FROM `teams` WHERE `name`=' . "'" . sqlSafeString(htmlent($_POST['edit_team_name'])) . "'" . ' LIMIT 1';
				if (!($result = @$site->execute_query($site->db_used_name(), 'teams', $query, $connection)))
				{
					// query was bad, error message was already given in $site->execute_query(...)
					$site->dieAndEndPage('');
				}
				
				if ((int) mysql_num_rows($result) > 0)
				{
					// is the team owning the name the current team?
					$name_change_tried = true;
					while ($row = mysql_fetch_array($result))
					{
						if (((int) $row['id']) === ((int) $teamid))
						{
							// there was no name changed tried
							$name_change_tried = false;
						}
					}
					mysql_free_result($result);
					// suppress error message when no name change was tried
					if ($name_change_tried)
					{
						// team name already used -> do not change to team name to it
						// note: this does also happen when the team profile is changed but not its name
						echo '<p>The team name was not changed because there is already a team with that name in the database.</p>' . "\n";
					}
				} else
				{
					mysql_free_result($result);
					// team name not used -> set team name to it
					$query = 'UPDATE `teams` SET `name`=' . "'" . sqlSafeString(htmlent($_POST['edit_team_name'])) . "'";
					$query .= ' WHERE `id`=' . "'" . $teamid . "'";
					if (!($result = @$site->execute_query($site->db_used_name(), 'teams', $query, $connection)))
					{
						// query was bad, error message was already given in $site->execute_query(...)
						$site->dieAndEndPage('');
					}
				}
			}
			
			//update team leader
			if (isset($_POST['team_leader']))
			{
				$new_leader = (int) $_POST['team_leader'];
				if ($new_leader  < 1)
				{
					$site->dieAndEndPage('You (id=' . sqlSafeString($viewerid) . ') tried to set a new leader with id lower than 1 (' . sqlSafeString($new_leader) . ')!');
				}
				
				// find out if new leader is member of team
				// first get the list of playerid's belonging to that team
				$query = 'SELECT `id` FROM `players` WHERE `teamid`=' . sqlSafeStringQuotes($teamid);
				if (!($result = @$site->execute_query($site->db_used_name(), 'players', $query, $connection)))
				{
					// query was bad, error message was already given in $site->execute_query(...)
					$site->dieAndEndPage('');
				}
				
				$new_leader_belongs_to_same_team = false;
				while($row = mysql_fetch_array($result))
				{
					if ($new_leader === ((int) $row['id']))
					{
						$new_leader_belongs_to_same_team = true;
					}
				}
				mysql_free_result($result);
				
				if (!$new_leader_belongs_to_same_team)
				{
					// verbose output, because someone tried to enter wrong data on purpose with a manipulated form
					$output = 'You (id=' . sqlSafeString($viewerid) . ') tried to set a new leader (id= ' . sqlSafeString($new_leader);
					$output .= ') that does not belong to same team (id=' . sqlSafeString($teamid) . ')!';
					$site->dieAndEndPage($output);
				}
				
				// sanity checks passed, change the team leader
				$query = 'UPDATE `teams` SET `leader_playerid`=' . sqlSafeStringQuotes($new_leader) . ' WHERE `id`=' . "'" . sqlSafeString($teamid) . "'";
				if (!($result = @$site->execute_query($site->db_used_name(), 'teams', $query, $connection)))
				{
					// query was bad, error message was already given in $site->execute_query(...)
					$site->dieAndEndPage('');
				}
			}
			
			// can any teamless player can join the team?
			if (isset($_POST['any_teamless_player_can_join']))
			{
				// value will only be sent when checkbox is checked and even in that case it only has the following value
				if ((int) $_POST['any_teamless_player_can_join'] === 1)
				{
					// team is open
					// hardcode the value to prevent sql injections
					$query = 'UPDATE `teams_overview` SET `any_teamless_player_can_join`=' . "'" . '1' . "'" . ' WHERE `teamid`=' . "'" . sqlSafeString($teamid) . "'";
					if (!($result = @$site->execute_query($site->db_used_name(), 'teams_overview', $query, $connection)))
					{
						// query was bad, error message was already given in $site->execute_query(...)
						$site->dieAndEndPage('');
					}
				}
			} else
			{
				// the team was closed
				// hardcode the value to prevent sql injections
				$query = 'UPDATE `teams_overview` SET `any_teamless_player_can_join`=' . "'" . '0' . "'" . ' WHERE `teamid`=' . "'" . sqlSafeString($teamid) . "'";
				if (!($result = @$site->execute_query($site->db_used_name(), 'teams_overview', $query, $connection)))
				{
					// query was bad, error message was already given in $site->execute_query(...)
					$site->dieAndEndPage('');
				}
			}
			
			// update team description
			if (isset($_POST['team_description']))
			{
				$query = 'UPDATE `teams_profile` SET `description`=' . "'" . sqlSafeString(bbcode($_POST['team_description'])) . "'";
				$query .= ' WHERE `teamid`=' . "'" . $teamid . "'";
				if (!($result = @$site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
				{
					// query was bad, error message was already given in $site->execute_query(...)
					$site->dieAndEndPage('');
				}
			}
			
			// write logo
			writeLogo();
			
//			echo 'test: ' . (bbcode($_POST['team_description']));
			echo '<p>Changes were successfully written.</p>';
			
			// we're done editing
			$site->dieAndEndPage('');
		}
		
		echo '<a class="button" href="./">Cancel & back to overview</a>' . "\n";
		echo '<form enctype="application/x-www-form-urlencoded" method="post" action="?edit=' . urlencode($teamid) . '">' . "\n";
		echo '<div><input type="hidden" name="confirmed" value="1"></div>' . "\n";
		
		$new_randomkey_name = $randomkey_name . microtime();
		$new_randomkey = $site->set_key($new_randomkey_name);
		echo '<div><input type="hidden" name="key_name" value="' . htmlentities($new_randomkey_name) . '"></div>' . "\n";
		echo '<div><input type="hidden" name="' . htmlentities($randomkey_name) . '" value="';
		echo urlencode(($_SESSION[$new_randomkey_name])) . '"></div>' . "\n";
		
		// team name
		echo '<p><label for="edit_team_name">Change team name: </label>' . "\n";
		echo '<input type="text" maxlength="30" size="30" name="edit_team_name" value="' . ($team_name) . '" id="edit_team_name"></p>' . "\n";
		
		// team leader
		$query = 'SELECT `id`, `name` FROM `players` WHERE `teamid`=' . "'" . sqlSafeString($teamid) . "'";
		if (!($result = @$site->execute_query($site->db_used_name(), 'players', $query, $connection)))
		{
			// query was bad, error message was already given in $site->execute_query(...)
			$site->dieAndEndPage('');
		}
		echo '<p><label for="team_leader">Leader: </label>' . "\n";
		echo '<select name="team_leader" id="team_leader">' . "\n";
		while($row = mysql_fetch_array($result))
		{
			echo '	<option';
			if ((int) $row['id'] === $team_leader_id)
			{
				echo ' selected="selected"';
			}
			echo ' value="' . urlencode($row['id']) . '"';
			echo '>' . ($row['name']);
			echo '</option>' . "\n";
		}
		mysql_free_result($result);
		echo '</select>' . "\n";
		echo '</p>' . "\n";
		
		// any teamless player allowed to join?
		$query = 'SELECT `any_teamless_player_can_join` FROM `teams_overview` WHERE `teamid`=' . "'" . sqlSafeString($teamid) . "'";
		if (!($result = @$site->execute_query($site->db_used_name(), 'teams_overview', $query, $connection)))
		{
			// query was bad, error message was already given in $site->execute_query(...)
			$site->dieAndEndPage('');
		}
		echo '<p><label for="any_teamless_player_can_join">Can any teamless player join the team?</label> <input type="checkbox" name="any_teamless_player_can_join" value="';
		echo '1' . '" id="any_teamless_player_can_join"';
		
		while($row = mysql_fetch_array($result))
		{
			if ((int) $row['any_teamless_player_can_join'] === 1)
			{
				echo ' checked="checked"';
			}
		}
		mysql_free_result($result);
		echo '></p>';
		
		// team description
		echo '<div><label for="team_description">Edit team description: </label><span><textarea id="team_description" rows="10" cols="50" name="team_description">';
		echo $team_description . '</textarea></span></div>';
		
		// logo/avatar url
		echo '<p><label class="player_edit" for="edit_avatar_url">Avatar URL: </label>';
		$site->write_self_closing_tag('input id="edit_avatar_url" type="text" name="logo_url" maxlength="200" size="60" value="'. $logo_url .'"');
		echo '</p>';
		
		echo '<div><input type="submit" name="edit_team_data" value="Submit new team data" id="send"></div>' . "\n";
		echo '</form>' . "\n";
		$site->dieAndEndPage('');
	}
	
	if (isset($_GET['remove']))
	{
		$playerid_to_remove = (int) $_GET['remove'];
		
		if ($viewerid < 1)
		{
			echo '<a class="button" href="./">overview</a>' . "\n";
			echo '<p>You must login to remove a player from team.</p>' . "\n";
			$site->dieAndEndPage('');
		}		
		
		// has the kick request been confirmed?
		$confirmed = 0;
		if (isset($_POST['confirmed']))
		{
			$confirmed = (int) $_POST['confirmed'];
		}
		
		// if the request looks valid investigate further
		if ($confirmed === 1)
		{
			$new_randomkey_name = '';
			if (isset($_POST['key_name']))
			{
				$new_randomkey_name = html_entity_decode($_POST['key_name']);
			}
			$randomkeysmatch = $site->compare_keys($randomkey_name, $new_randomkey_name);			
			if (!($randomkeysmatch))
			{
				echo '<a class="button" href="./">overview</a>' . "\n";
				echo '<p>The key did not match. It looks like you came from somewhere else.</p>';
				$site->dieAndEndPage('');
			}
			
			if ($display_user_kick_from_team_buttons || (($viewerid > 0) && ($viewerid === $playerid_to_remove)))
			{
				// user is allowed to kick members from team
				$query = 'UPDATE `players` SET `teamid`=' . "'" . sqlSafeString('0') . "'";
				$query .= ' WHERE `id`=' . "'" . sqlSafeString((int) $viewerid) . "'";
				if (!($result = @$site->execute_query($site->db_used_name(), 'players', $query, $connection)))
				{
					// query was bad, error message was already given in $site->execute_query(...)
					$site->dieAndEndPage('There was a problem preventing the user with id ('
										 . sqlSafeString($viewerid)
										 . ') from being kicked from team with id ('
										 . htmlentities($join_team_id)) . ')';
				}
				
				// update member count of team
				$query = 'UPDATE `teams_overview` SET `member_count`=(SELECT COUNT(*) FROM `players` WHERE `players`.`teamid`=';
				$query .= "'" . sqlSafeString($teamid) . "'" . ') WHERE `teamid`=';
				$query .= "'" . sqlSafeString($teamid) . "'";
				if (!($result = @$site->execute_query($site->db_used_name(), 'teams_overview, nested players ', $query, $connection)))
				{
					// query was bad, error message was already given in $site->execute_query(...)
					$site->dieAndEndPage('There was a problem updating the member count of team id ('
										 . sqlSafeString($viewerid) . ') from being kicked from team with id ('
										 . htmlentities($join_team_id)) . ')';
				}
				
				echo '<a class="button" href="./">overview</a>' . "\n";
				if ($playerid_to_remove === $viewerid)
				{
					echo '<p>You successfully left the team!</p>';
				} else
				{
					echo '<p>The user was successfully kicked from the team!</p>';
				}
			} else
			{
				// someone tried to kick a member without permissions
				$site->dieAndEndPage('You (' . htmlentities(sqlSafeString($viewerid))
									 . ') are not allowed to kick that member ('
									 . htmlentities($playerid_to_remove) . ') from his team');
			}
			$site->dieAndEndPage('');
		}
		
		echo '<a class="button" href="./">Cancel & back to overview</a>' . "\n";
		
		$query = 'SELECT `name` FROM `players` WHERE `id`=' . "'" . sqlSafeString($playerid_to_remove) . "'";
		if (!($result = @$site->execute_query($site->db_used_name(), 'players', $query, $connection)))
		{
			// query was bad, error message was already given in $site->execute_query(...)
			$site->dieAndEndPage('');
		}
		// take care of potential database problems
		checkNumberRows($result, $site);
		
		// get the name of the player being kicked from the query result
		$playername = '(none)';
		while($row = mysql_fetch_array($result))
		{
			$playername = $row['name'];
		}
		mysql_free_result($result);
		
		// display a form, asking for confirmation of kick
		echo '<form enctype="application/x-www-form-urlencoded" method="post" action="?remove=' . urlencode($playerid_to_remove) . '">' . "\n";
		echo '<div><input type="hidden" name="confirmed" value="1"></div>' . "\n";

		$new_randomkey_name = $randomkey_name . microtime();
		$new_randomkey = $site->set_key($new_randomkey_name);
		echo '<div><input type="hidden" name="key_name" value="' . htmlentities($new_randomkey_name) . '"></div>' . "\n";
		echo '<div><input type="hidden" name="' . htmlentities($randomkey_name) . '" value="';
		echo urlencode(($_SESSION[$new_randomkey_name])) . '"></div>' . "\n";
		
		// let it say leave the team if a member of the team wants to leave it
		if ($playerid_to_remove === $viewerid)
		{
			echo '<p style="display:inline">Do you really want to leave your team?</p>' . "\n";
			echo '<div style="display:inline"><input type="submit" name="kick_user_from_team" value="Leave the team" id="send"></div>' . "\n";
		} else
		{
			echo '<p style="display:inline">Do you really want to kick ' . $playername . ' from the team?</p>' . "\n";
			echo '<div style="display:inline"><input type="submit" name="kick_user_from_team" value="Kick the user" id="send"></div>' . "\n";
		}
		echo '</form>' . "\n";
		$site->dieAndEndPage('');
	}
	
	// someone wants to look at a team profile
	if (isset($_GET['profile']))
	{
		// display team profile page
				
		// clean variable
		$profile = (int) $_GET['profile'];
		echo '<a class="button" href="./">overview</a>' . "\n";
		
		if (($team_leader_id > 0) && ($viewerid > 0))
		{
			echo '<a class="button" href="../Messages/?add' . htmlspecialchars('&') . 'teamid=' . htmlspecialchars(urlencode($profile)) . '">Send message to team</a>' . "\n";
		}
		
		if (($team_leader_id > 0) && $viewerid === $team_leader_id || $allow_edit_any_team_profile)
		{
			echo '<a class="button" href="./?edit=' . (urlencode($profile)) . '">edit</a>' . "\n";
		}
		// need an element displayed with display: block before the team area
		echo '<div class="p"></div>' . "\n";
		
		// join the tables `teams`, `teams_overview` and `teams_profile` using the team's id
		$query = 'SELECT `name`, `score`, `activity`, `member_count`, `num_matches_played`, `num_matches_won`, `num_matches_draw`, `num_matches_lost`, `teams_profile`.`logo_url`, `created`';
		$query .= ' FROM `teams`, `teams_overview`, `teams_profile`';
		$query .= ' WHERE `teams`.`id` = `teams_overview`.`teamid` AND `teams_overview`.`teamid` = `teams_profile`.`teamid` AND `teams`.`id`=';
		$query .= "'" . sqlSafeString($profile) . "'" . ' LIMIT 1';
		if (!($result = @$site->execute_query($site->db_used_name(), 'teams, teams_overview, teams_profile', $query, $connection)))
		{
			// query was bad, error message was already given in $site->execute_query(...)
			$site->dieAndEndPage('');
		}
		
		if ((int) mysql_num_rows($result) > 1)
		{
			// more than one team with the same id!
			// this should never happen
			$site->dieAndEndPage('There was more than one team with that id (' . sqlSafeString($profile) . '). This is a database error, please report it to admins.');
		}
		
		$team_name = '(no name)';
		$number_team_members = 0;
		
		while ($row = mysql_fetch_array($result))
		{
			echo '<div class="team_area">' . "\n";
			echo '	<div class="team_header">' . "\n";
			$team_name = $row['name'];
			// team name empty?
			if (strcmp($team_name, '') === 0)
			{
				$team_name = '(unnamed team)';
			}
			echo '		<div class="team_name">' . $team_name . '</div>' . "\n";
			if (!(strcmp(($row['logo_url']), '') === 0))
			{
				// user entered a logo
				$site->write_self_closing_tag('img class="team_logo" src="' . htmlentities($row['logo_url']) . '" style="max-width:200px; max-height:150px" alt="team logo"');
			}
			echo '		<span class="team_score">Rating: ' . $row['score'] . '</span>' . "\n";
			echo '		<div class="team_activity"><span class="team_activity_announcement">Activity: </span><span class="team_activity">'
				 . strval($row['activity']) . '</span></div>' . "\n";
			$number_team_members = (int) $row['member_count'];
			echo '		<div class="team_member_count"><span class="team_member_count_announcement">Members: </span><span class="team_member_count">'
				 . strval($number_team_members) . '</span></div>' . "\n";
			echo '		<div class="team_created"><span class="team_created_announcement">Created: </span><span class="team_created">'
				 . strval($row['created']) . '</span></div>' . "\n";
			echo'	</div>' . "\n";
			echo '</div>' . "\n";
			
			// matches statistics: won, draw, lost and total
			echo '	<table class="table_team_matches_data">' . "\n";
			echo '	<caption>Match statistics</caption>' . "\n";
			echo '	<tr>' . "\n";
			echo '		<th>Wins</th>' . "\n";
			echo '		<th>Draws</th>' . "\n";
			echo '		<th>Losts</th>' . "\n";
			echo '		<th>Total</th>' . "\n";
			echo '	</tr>' . "\n";
			
			echo '	<tr>' . "\n";
			echo '		<td>' . htmlentities($row['num_matches_won']) . '</td>' . "\n";
			echo '		<td>' . htmlentities($row['num_matches_draw']) . '</td>' . "\n";
			echo '		<td>' . htmlentities($row['num_matches_lost']) . '</td>' . "\n";
			echo '		<td>' . htmlentities($row['num_matches_played']) . '</td>' . "\n";
			echo '	</tr>' . "\n";
			echo '	</table>' . "\n";
			$site->write_self_closing_tag('br');
			
			echo '<div class="team_area">' . "\n";
			echo '	<div class="team_profile_box">' . "\n";
			echo '		<div class="team_profile_header_text">team description</div>' . "\n";
			echo '		<div class="team_description">' . $team_description . '</div>' . "\n";
			echo '	</div>' . "\n";
			echo '</div>' . "\n";
		}
		// query result no longer needed
		mysql_free_result($result);
		
		// no members -> team is dead
		if ($number_team_members === 0)
		{
			// FIXME: Show when (date) the team was deleted
			$site->dieAndEndPage('This team was deleted.');
		}
		
		// example query: SELECT `players`.`id`, `name`, `location`
		// FROM `players`, `players_profile` WHERE `players`.`teamid`='1' AND `players`.`id`=`players_profile`.`playerid`
		$query = 'SELECT `players`.`id`, `name`, `location` FROM `players`, `players_profile` WHERE `players`.`teamid`=' . "'" . sqlSafeString($profile) . "'";
		$query .= '  AND `players`.`id`=`players_profile`.`playerid`';
		if (!($result = @$site->execute_query($site->db_used_name(), 'teams_overview', $query, $connection)))
		{
			// query was bad, error message was already given in $site->execute_query(...)
			$site->dieAndEndPage('');
		}
				
		echo "\n" . '<table class="table_team_members">' . "\n";
		echo '<caption>Members of team ' . $team_name . '</caption>' . "\n";
		echo '<tr>' . "\n";
		echo '	<th>Name</th>' . "\n";
		echo '	<th>Location</th>' . "\n";
		echo '	<th>Permissions</th>' . "\n";
		if ($display_user_kick_from_team_buttons)
		{
			echo '<th>Allowed actions</th>' . "\n";
		}		
		echo '</tr>' . "\n\n";
		while($row = mysql_fetch_array($result))
		{
			echo '<tr class="teams_members_overview">' . "\n";
			echo '<td>';
			echo '<a href="../Players?profile=';
			$currentId = (int) $row['id'];
			echo $currentId . '">' . ($row['name']) . '</a>';
			echo '</td>' . "\n" . '<td>';
			echo $row['location'];
			echo '</td>' . "\n" . '<td>';
			if (($team_leader_id > 0) && $team_leader_id === (int) $row['id'])
			{
				echo 'Leader';
			} else
			{
				// TODO: add team permissions into players database
				echo 'None';
			}
			echo '</td>' . "\n";
			
			// team leader can't be removed from team
			if (($team_leader_id > 0) && ($currentId !== $team_leader_id))
			{
				if (($viewerid > 0) && ($viewerid === ((int) $row['id'])))
				{
					// user could leave his team
					echo '<td><a class="button" href="./?remove=' . $currentId . '">Leave team</a></td>' . "\n";
				} else
				{
					if ($display_user_kick_from_team_buttons)
					{
						// admin or team leader can kick a member from team
						echo '<td><a class="button" href="./?remove=' . $currentId . '">Kick member from team</a></td>' . "\n";						
					}
				}
			} else
			{
				// FIXME: Only display empty table cell when an action is possible, e.g. viewer is either admin or belongs to the team
				// nothing can be done -> display empty table cell
				
				// find out if viewer belongs to the team
				$user_belongs_to_the_viewed_team = false;
				$query = 'SELECT `teamid` FROM `players` WHERE `id`=' . "'" . sqlSafeString($viewerid) . "'" . ' LIMIT 0,1';
				// debug output at this point would break the html definition thus execute the query silently
				if (!($team_origin_of_viewer = @$site->execute_silent_query($site->db_used_name(), 'teams_overview', $query, $connection)))
				{
					// query was bad, error message was already given in $site->execute_query(...)
					$site->dieAndEndPage('');
				}
				
				while($row = mysql_fetch_array($team_origin_of_viewer))
				{
					if (((int) $row['teamid']) === $profile)
					{
						$user_belongs_to_the_viewed_team = true;
					}
				}
				mysql_free_result($team_origin_of_viewer);
				
				if (($viewerid > 0) && ($user_belongs_to_the_viewed_team || (get_allow_kick_any_team_members() === true)))
				{
					echo '<td></td>';
				}
			}
			echo '</tr>' . "\n\n";
		}
		// query result no longer needed
		mysql_free_result($result);
		
		echo '</table>' . "\n";

		if (($team_leader_id > 0) && $viewerid === $team_leader_id || $allow_invite_in_any_team)
		{
			$query = 'SELECT `invited_playerid`, `expiration` FROM `invitations` WHERE `teamid`=' . "'" . sqlSafeString($teamid) . "'";
			$query .= ' ORDER BY `expiration`';
			if (!($result = @$site->execute_query($site->db_used_name(), 'invitations', $query, $connection)))
			{
				// query was bad, error message was already given in $site->execute_query(...)
				$site->dieAndEndPage('Could not get find out the player id and expiration date list of invited players');
			}
			
			$rows = (int) mysql_num_rows($result);
			if ($rows > 0)
			{
				echo "\n" . '<p>Pending invitations:</p>' . "\n";
				echo '<ul>' . "\n";
				
				while($row = mysql_fetch_array($result))
				{
					// get the id of invited player
					$playerid = (int) $row['invited_playerid'];
					$expirationDate = $row['expiration'];
					$query = 'SELECT `name` FROM `players` WHERE `id`=' . "'" . sqlSafeString($playerid) . "'";
					if (!($result_name = @$site->execute_silent_query($site->db_used_name(), 'players', $query, $connection)))
					{
						// query was bad, error message was already given in $site->execute_query(...)
						$site->dieAndEndPage('Could not get find out the corresponding name of player id ' . sqlSafeString($playerid) . ' from invited players');
					}
					// collect the data from query result array
					while($row = mysql_fetch_array($result_name))
					{
						$invited_player_name = $row['name'];
					}
					echo '<li>' . $invited_player_name . ' (expires ' . $expirationDate . ')</li>' . "\n";
				}
				echo '</ul>' . "\n";
			}
		}
		
		// team leader can delete the team
		if ($can_delete_any_team || (($team_leader_id > 0) && ($viewerid === $team_leader_id)))
		{
			echo '<p><a class="button" href="./?delete=' . (urlencode($profile)) . '">delete this team</a></p>' . "\n";
		}
	} else
	{
		// display overview
		
		// reactivate button
		if ($allow_reactivate_teams)
		{
			echo '<a class="button" href="./?reactivate">Reactivate a team</a>' . "\n";
			$site->write_self_closing_tag('br');
			$site->write_self_closing_tag('br');
		}
		
		// is player teamless?
		$player_teamless = false;
		// display a create team button to the teamless players viewing this page
		if ($viewerid > 0)
		{
			// this would also work without the viewerid switch but it would be one unneeded query,
			// thus avoid it due to performance reasons; slow sites are usually not liked
			$query = 'SELECT `teamid` FROM `players` WHERE `id`=' . "'" . sqlSafeString($viewerid) . "'" . ' LIMIT 1';
			if (!($result = @$site->execute_query($site->db_used_name(), 'players', $query, $connection)))
			{
				// query was bad, error message was already given in $site->execute_query(...)
				$site->dieAndEndPage('');
			}
			
			while($row = mysql_fetch_array($result))
			{
				if (((int) $row['teamid']) === 0)
				{
					$player_teamless = true;
				}
			}
			mysql_free_result($result);
			
			if ($player_teamless)
			{
				echo '<a class="button" href="./?create">Create a new team</a>' . "\n";
			}
		}
		
		// list the non deleted teams
		// example query: SELECT `teamid`,`name`, `score`, `member_count`,`any_teamless_player_can_join` FROM `teams`, `teams_overview`
		// WHERE `teams`.`id` = `teams_overview`.`teamid` AND (`teams_overview`.`deleted`='0' OR `teams_overview`.`deleted`='1') ORDER BY `score`		
		$query = 'SELECT `teamid`,`name`, `score`, `member_count`,`any_teamless_player_can_join` FROM `teams`, `teams_overview` ';
		$query .= 'WHERE `teams`.`id` = `teams_overview`.`teamid`';
		$query .= ' AND (`teams_overview`.`deleted`=' . "'" . '0' . "'";
		$query .= ' OR `teams_overview`.`deleted`=' . "'" . '1' . "'" . ')';
		$query .= ' ORDER BY `score` DESC';
		if ($result = @$site->execute_query($site->db_used_name(), 'teams_overview', $query, $connection))
		{
			$rows = (int) mysql_num_rows($result);
			if ($rows === 0)
			{
				echo '<p>There are no teams in the database.</p>';
			}
			
			echo '<table class="table_team_members">' . "\n";
			echo '<caption>List of teams</caption>' . "\n";
			echo '<tr>' . "\n";
			echo '	<th>Name</th>' . "\n";
			echo '	<th>Score</th>' . "\n";
			echo '	<th>Number of members</th>' . "\n";
			
			if ($player_teamless)
			{
				echo '	<th>Allowed actions</th>' . "\n";
			}
			
			echo '</tr>' . "\n";

			while($row = mysql_fetch_array($result))
			{
				echo '<tr class="teams_overview">' . "\n";
				echo '	<td><a href="./?profile=' . $row['teamid'] . '">';
				// team name empty?
				if (strcmp(($row['name']), '') === 0)
				{
					echo '(unnamed team)';
				} else
				{
					echo $row['name'] . '</a></td>' . "\n";
				}
				echo '	<td>' . $row['score'] . '</td>' . "\n";
				echo '	<td>' . $row['member_count'] . '</td>' . "\n";
				if (($viewerid > 0) && ((int) $row['any_teamless_player_can_join'] === 1))
				{					
					// take care of potential database problems
					if ($player_teamless)
					{
						echo '	<td><a class="button" href="./?join=' . $row['teamid'] . '">Join team</a></td>' . "\n";
					}
				}
				else
				{
					// display empty row so there is always the same number of columns within a table
					if ($player_teamless)
					{
						// is the player invited to the team?
						$query = 'SELECT `id` FROM `invitations` WHERE `invited_playerid`=' . "'" . sqlSafeString($viewerid) . "'";
						// the current team in the list
						$query .= ' AND `teamid`=' . "'" . sqlSafeString($row['teamid']) . "'";
						// is the invitation expired?
						$query .= ' AND expiration>' . "'" . sqlSafeString(date('Y-m-d H:i:s')) . "'";
						// there should be only one invitation
						// FIXME: enforce that there will be only one invitation in the player list source code
						$query .= ' LIMIT 1';
						if (!($invited_result = @$site->execute_query($site->db_used_name(), 'invitations', $query, $connection)))
						{
							// query was bad, error message was already given in $site->execute_query(...)
							$site->dieAndEndPage('');
						}
						
						$rows = (int) mysql_num_rows($invited_result);
						mysql_free_result($invited_result);
						if ($rows > 0)
						{
							echo '	<td><a class="button" href="./?join=' . $row['teamid'] . '">Join team using invite</a></td>' . "\n";
						} else
						{
							echo '	<td></td>' . "\n";
						}
					}
				}
				echo '</tr>' . "\n";
			}
			// no more players left to display
			echo '</table>' . "\n";
		}
		mysql_free_result($result);
	}
?>

</div>
</body>
</html>
