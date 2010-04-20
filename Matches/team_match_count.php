<?php
	// always be careful with $_SERVER['PHP_SELF']) because custom links can change the original page
	if (preg_match("/team_match_count/i", $_SERVER['PHP_SELF']))
	{
		die("This file is meant to be only included by other files!");
	}
	
	function update_team_did_not_play_in_edited_version($team1_points_before, $team2_points_before, $team_id1, $site, $connection)
	{
		// team 1 did originally win
		if ($team1_points_before > $team2_points_before)
		{
			// remove a win from team 1 and decrease total match count of team 1 by one
			$query = 'UPDATE `teams_profile` SET ';
			$query .= '`num_matches_won`=`num_matches_won`-' . sqlSafeStringQuotes('1');
			$query .= ', `num_matches_played`=`num_matches_played`-' . sqlSafeStringQuotes('1');
			$query .= ' WHERE (`teamid`=' . sqlSafeStringQuotes($team_id1) . ')';
			// only one team needs to be updated
			$query .= ' LIMIT 1';
			if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
			{
				unlock_tables($site, $connection);
				$site->dieAndEndPage('Could not update win/play count for team with id ' . sqlSafeString($team_id1) . ' due to a sql problem!');
			}
		}
		
		// team 1 did originally loose
		if ($team1_points_before < $team2_points_before)
		{
			// remove a loose from team 1 and decrease total match count of team 1 by one
			$query = 'UPDATE `teams_profile` SET ';
			$query .= '`num_matches_lost`=`num_matches_lost`-' . sqlSafeStringQuotes('1');
			$query .= ', `num_matches_played`=`num_matches_played`-' . sqlSafeStringQuotes('1');
			$query .= ' WHERE (`teamid`=' . sqlSafeStringQuotes($team_id1) . ')';
			// only one team needs to be updated
			$query .= ' LIMIT 1';
			if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
			{
				unlock_tables($site, $connection);
				$site->dieAndEndPage('Could not update lose/play count for team with id ' . sqlSafeString($team_id1) . ' due to a sql problem!');
			}
		}
		
		// team 1 did originally tie
		if ($team1_points_before < $team2_points_before)
		{
			// remove a draw from team 1 and decrease total match count of team 1 by one
			$query = 'UPDATE `teams_profile` SET ';
			$query .= '`num_matches_draw`=`num_matches_draw`-' . sqlSafeStringQuotes('1');
			$query .= ', `num_matches_played`=`num_matches_played`-' . sqlSafeStringQuotes('1');
			$query .= ' WHERE (`teamid`=' . sqlSafeStringQuotes($team_id1) . ')';
			// only one team needs to be updated
			$query .= ' LIMIT 1';
			if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
			{
				unlock_tables($site, $connection);
				$site->dieAndEndPage('Could not update draw/play count for team with id ' . sqlSafeString($team_id1) . ' due to a sql problem!');
			}
		}
	}
	
	function update_team_match_counts($team1_points_before, $team2_points_before, $team_id1, $team1_points, $team2_points, $site, $connection)
	{
		// originally team 1 won
		if ($team1_points_before > $team2_points_before)
		{
			// if team 1 also won in the edited version
			// no changes needed it wins/draws/losts/total stats
			
			// if team 1 lost in the edited version
			// team 1 has one less won match and one more lost match
			if ($team1_points < $team2_points)
			{
				$query = 'UPDATE `teams_profile` SET ';
				$query .= '`num_matches_won`=`num_matches_won`-' . sqlSafeStringQuotes('1');
				$query .= ', `num_matches_lost`=`num_matches_lost`+' . sqlSafeStringQuotes('1');
				$query .= ' WHERE (`teamid`=' . sqlSafeStringQuotes($team_id1) . ')';
				// only one team needs to be updated
				$query .= ' LIMIT 1';
				if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
				{
					unlock_tables($site, $connection);
					$site->dieAndEndPage('Could not update win/lose count for team with id ' . sqlSafeString($team_id1) . ' due to a sql problem!');
				}
			}
			
			// if team 1 tied in the edited version
			// team 1 has one less won match and one more draw
			if ($team1_points === $team2_points)
			{
				$query = 'UPDATE `teams_profile` SET ';
				$query .= '`num_matches_won`=`num_matches_won`-' . sqlSafeStringQuotes('1');
				$query .= ', `num_matches_draw`=`num_matches_draw`+' . sqlSafeStringQuotes('1');
				$query .= ' WHERE (`teamid`=' . sqlSafeStringQuotes($team_id1) . ')';
				// only one team needs to be updated
				$query .= ' LIMIT 1';
				if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
				{
					unlock_tables($site, $connection);
					$site->dieAndEndPage('Could not update win/draw count for team with id ' . sqlSafeString($team_id1) . ' due to a sql problem!');
				}
			}
		}
		
		// originally team 1 lost
		if ($team1_points_before < $team2_points_before)
		{
			// if team 1 won in the edited version
			// team 1 has one less lost match and one more won match
			if ($team1_points < $team2_points)
			{
				$query = 'UPDATE `teams_profile` SET ';
				$query .= '`num_matches_lost`=`num_matches_lost`-' . sqlSafeStringQuotes('1');
				$query .= ', `num_matches_won`=`num_matches_won`+' . sqlSafeStringQuotes('1');
				$query .= ' WHERE (`teamid`=' . sqlSafeStringQuotes($team_id1) . ')';
				// only one team needs to be updated
				$query .= ' LIMIT 1';
				if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
				{
					unlock_tables($site, $connection);
					$site->dieAndEndPage('Could not update lose/win count for team with id ' . sqlSafeString($team_id1) . ' due to a sql problem!');
				}
			}						
			
			// if team 1 also lost in the edited version
			// no changes needed it wins/draws/losts/total stats
			
			// if team 1 tied in the edited version
			// team 1 has one less lost match and one more draw
			if ($team1_points === $team2_points)
			{
				$query = 'UPDATE `teams_profile` SET ';
				$query .= '`num_matches_lost`=`num_matches_lost`-' . sqlSafeStringQuotes('1');
				$query .= ', `num_matches_draw`=`num_matches_draw`+' . sqlSafeStringQuotes('1');
				$query .= ' WHERE (`teamid`=' . sqlSafeStringQuotes($team_id1) . ')';
				// only one team needs to be updated
				$query .= ' LIMIT 1';
				if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
				{
					unlock_tables($site, $connection);
					$site->dieAndEndPage('Could not update lose/draw count for team with id ' . sqlSafeString($team_id1) . ' due to a sql problem!');
				}
			}
		}
	}
	
	function cmp_team1_won()
	{
		if ($team1_points > $team2_points)
		{
			// originally team 1 lost
			if ($team1_points_before < $team2_points_before)
			{
				// update team 1 data
				$query = 'UPDATE `teams_profile` SET ';
				$query .= '`num_matches_won`=`num_matches_won`+' . "'" . sqlSafeString('1') . "'";
				$query .= ', `num_matches_lost`=`num_matches_lost`-' . "'" . sqlSafeString('1') . "'";
				$query .= ' WHERE (`teamid`=' . "'" . sqlSafeString($team_id1) . "'" . ')';
				// only one team needs to be updated
				$query .= ' LIMIT 1';
				if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
				{
					unlock_tables($site, $connection);
					$site->dieAndEndPage('Could not update win/lose count for team with id ' . sqlSafeString($team_id1) . ' due to a sql problem!');
				}
				
				// update team 2 data
				$query = 'UPDATE `teams_profile` SET ';
				$query .= '`num_matches_won`=`num_matches_won`-' . "'" . sqlSafeString('1') . "'";
				$query .= ', `num_matches_lost`=`num_matches_lost`+' . "'" . sqlSafeString('1') . "'";
				$query .= ' WHERE (`teamid`=' . "'" . sqlSafeString($team_id2) . "'" . ')';
				// only one team needs to be updated
				$query .= ' LIMIT 1';
				if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
				{
					unlock_tables($site, $connection);
					$site->dieAndEndPage('Could not update win/lose count for team with id ' . sqlSafeString($team_id2) . ' due to a sql problem!');
				}
			}
			
			// originally the match ended in a draw
			if ($team1_points_before === $team2_points_before)
			{
				// update team 1 data
				$query = 'UPDATE `teams_profile` SET ';
				$query .= '`num_matches_won`=`num_matches_won`+' . "'" . sqlSafeString('1') . "'";
				$query .= ', `num_matches_draw`=`num_matches_draw`-' . "'" . sqlSafeString('1') . "'";
				$query .= ' WHERE (`teamid`=' . "'" . sqlSafeString($team_id1) . "'" . ')';
				// only one team needs to be updated
				$query .= ' LIMIT 1';
				if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
				{
					unlock_tables($site, $connection);
					$site->dieAndEndPage('Could not update win/draw count for team with id ' . sqlSafeString($team_id1) . ' due to a sql problem!');
				}
				
				// update team 2 data
				$query = 'UPDATE `teams_profile` SET ';
				$query .= '`num_matches_lost`=`num_matches_lost`+' . "'" . sqlSafeString('1') . "'";
				$query .= ', `num_matches_draw`=`num_matches_draw`-' . "'" . sqlSafeString('1') . "'";
				$query .= ' WHERE (`teamid`=' . "'" . sqlSafeString($team_id2) . "'" . ')';
				// only one team needs to be updated
				$query .= ' LIMIT 1';
				if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
				{
					unlock_tables($site, $connection);
					$site->dieAndEndPage('Could not update lose/draw count for team with id ' . sqlSafeString($team_id2) . ' due to a sql problem!');
				}
			}
		}
	}
	
	function cmp_team2_won()
	{
		if ($team1_points < $team2_points)
		{
			// originally team 2 lost
			if ($team1_points_before > $team2_points_before)
			{
				// update team 1 data
				$query = 'UPDATE `teams_profile` SET ';
				$query .= '`num_matches_won`=`num_matches_won`-' . "'" . sqlSafeString('1') . "'";
				$query .= ', `num_matches_lost`=`num_matches_lost`+' . "'" . sqlSafeString('1') . "'";
				$query .= ' WHERE (`teamid`=' . "'" . sqlSafeString($team_id1) . "'" . ')';
				// only one team needs to be updated
				$query .= ' LIMIT 1';
				if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
				{
					unlock_tables($site, $connection);
					$site->dieAndEndPage('Could not update win/lose count for team with id ' . sqlSafeString($team_id1) . ' due to a sql problem!');
				}
				
				// update team 2 data
				$query = 'UPDATE `teams_profile` SET ';
				$query .= '`num_matches_won`=`num_matches_won`+' . "'" . sqlSafeString('1') . "'";
				$query .= ', `num_matches_lost`=`num_matches_lost`-' . "'" . sqlSafeString('1') . "'";
				$query .= ' WHERE (`teamid`=' . "'" . sqlSafeString($team_id2) . "'" . ')';
				// only one team needs to be updated
				$query .= ' LIMIT 1';
				if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
				{
					unlock_tables($site, $connection);
					$site->dieAndEndPage('Could not update win/lose count for team with id ' . sqlSafeString($team_id2) . ' due to a sql problem!');
				}
			}
			
			// originally the match ended in a draw
			if ($team1_points_before === $team2_points_before)
			{
				// update team 1 data
				$query = 'UPDATE `teams_profile` SET ';
				$query .= '`num_matches_lost`=`num_matches_lost`+' . "'" . sqlSafeString('1') . "'";
				$query .= ', `num_matches_draw`=`num_matches_draw`-' . "'" . sqlSafeString('1') . "'";
				$query .= ' WHERE (`teamid`=' . "'" . sqlSafeString($team_id1) . "'" . ')';
				// only one team needs to be updated
				$query .= ' LIMIT 1';
				if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
				{
					unlock_tables($site, $connection);
					$site->dieAndEndPage('Could not update lose/draw count for team with id ' . sqlSafeString($team_id1) . ' due to a sql problem!');
				}
				
				// update team 2 data
				$query = 'UPDATE `teams_profile` SET ';
				$query .= '`num_matches_won`=`num_matches_won`+' . "'" . sqlSafeString('1') . "'";
				$query .= ', `num_matches_draw`=`num_matches_draw`-' . "'" . sqlSafeString('1') . "'";
				$query .= ' WHERE (`teamid`=' . "'" . sqlSafeString($team_id2) . "'" . ')';
				// only one team needs to be updated
				$query .= ' LIMIT 1';
				if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
				{
					unlock_tables($site, $connection);
					$site->dieAndEndPage('Could not update win/draw count for team with id ' . sqlSafeString($team_id2) . ' due to a sql problem!');
				}
			}
		}
	}
	
	function cmp_teams_tied()
	{
		if ($team1_points === $team2_points)
		{
			// originally team 1 lost
			if ($team1_points_before < $team2_points_before)
			{
				// update team 1 data
				$query = 'UPDATE `teams_profile` SET ';
				$query .= '`num_matches_draw`=`num_matches_draw`+' . "'" . sqlSafeString('1') . "'";
				$query .= ', `num_matches_lost`=`num_matches_lost`-' . "'" . sqlSafeString('1') . "'";
				$query .= ' WHERE (`teamid`=' . "'" . sqlSafeString($team_id1) . "'" . ')';
				// only one team needs to be updated
				$query .= ' LIMIT 1';
				if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
				{
					unlock_tables($site, $connection);
					$site->dieAndEndPage('Could not update draw/lose count for team with id ' . sqlSafeString($team_id1) . ' due to a sql problem!');
				}
				
				// update team 2 data
				$query = 'UPDATE `teams_profile` SET ';
				$query .= '`num_matches_draw`=`num_matches_draw`+' . "'" . sqlSafeString('1') . "'";
				$query .= ', `num_matches_won`=`num_matches_won`-' . "'" . sqlSafeString('1') . "'";
				$query .= ' WHERE (`teamid`=' . "'" . sqlSafeString($team_id2) . "'" . ')';
				// only one team needs to be updated
				$query .= ' LIMIT 1';
				if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
				{
					unlock_tables($site, $connection);
					$site->dieAndEndPage('Could not update draw/win count for team with id ' . sqlSafeString($team_id2) . ' due to a sql problem!');
				}
			}
			
			// originally team 2 lost
			if ($team1_points_before > $team2_points_before)
			{
				// update team 1 data
				$query = 'UPDATE `teams_profile` SET ';
				$query .= '`num_matches_won`=`num_matches_won`-' . "'" . sqlSafeString('1') . "'";
				$query .= ', `num_matches_draw`=`num_matches_draw`+' . "'" . sqlSafeString('1') . "'";
				$query .= ' WHERE (`teamid`=' . "'" . sqlSafeString($team_id1) . "'" . ')';
				// only one team needs to be updated
				$query .= ' LIMIT 1';
				if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
				{
					unlock_tables($site, $connection);
					$site->dieAndEndPage('Could not update win/draw count for team with id ' . sqlSafeString($team_id1) . ' due to a sql problem!');
				}
				
				// update team 2 data
				$query = 'UPDATE `teams_profile` SET ';
				$query .= '`num_matches_lost`=`num_matches_lost`-' . "'" . sqlSafeString('1') . "'";
				$query .= ', `num_matches_draw`=`num_matches_draw`+' . "'" . sqlSafeString('1') . "'";
				$query .= ' WHERE (`teamid`=' . "'" . sqlSafeString($team_id2) . "'" . ')';
				// only one team needs to be updated
				$query .= ' LIMIT 1';
				if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
				{
					unlock_tables($site, $connection);
					$site->dieAndEndPage('Could not update lose/draw count for team with id ' . sqlSafeString($team_id2) . ' due to a sql problem!');
				}
			}
		}
		
	}
?>