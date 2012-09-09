<?php
	class teamOpponents
	{
		function __construct()
		{
		
		}
		
		
		private function rankScore($score)
		{
			switch ($score)
			{
				case ($score >1900):
					return 'score1900';
					
				case ($score >1800):
					return 'score1800';
					
				case ($score >1700):
					return 'score1700';
					
				case ($score >1600):
					return 'score1600';
					
				case ($score >1500):
					return 'score1500';
					
				case ($score >1400):
					return 'score1400';
					
				case ($score >1300):
					return 'score1300';
					
				case ($score >1200):
					return 'score1200';
					
				case ($score >1100):
					return 'score1100';
					
				case ($score >1000):
					return 'score1000';
					
				case ($score >900):
					return 'score900';
					
				case ($score >800):
					return 'score800';
					
				case ($score <700):
					return 'score700';
			}
		}
		
		
		private function addToTeamList(&$row)
		{
				// rename db result fields and assemble some additional informations
				
				$prepared = array();
				$prepared['profileLink'] = './?profile=' . $row['teamid'];
				$prepared['name'] = $row['name'];
				$prepared['score'] = $row['score'];
				$prepared['scoreClass'] = $this->rankScore($row['score']);
				$prepared['matchSearchLink'] = ('../Matches/?search_string=' . $row['name']
												. '&amp;search_type=team+name'
												. '&amp;search_result_amount=20'
												. '&amp;search=Search');
				$prepared['matchCount'] = $row['num_matches_played'];
				$prepared['memberCount'] = $row['member_count'];
				$prepared['leaderLink'] = '../Players/?profile=' . $row['leader_userid'];
				$prepared['leaderName'] = $row['leader_name'];
				$prepared['activityNew'] = $row['activityNew'];
				$prepared['activityOld'] = $row['activityOld'];
				$prepared['created'] = $row['created'];
				
				// return result data
				return $prepared;
		}
		
		
		public function showOpponentStats($teamid)
		{
			global $tmpl;
			global $user;
			global $db;
			
			
			if (!$tmpl->setTemplate('teamSystemOpponentStats'))
			{
				$tmpl->noTemplateFound();
				die();
			}
			$tmpl->assign('title', 'Team opponent statistics');
			$tmpl->assign('teamid', $teamid);
			
			
			// get team name for specified teamid
			$params = array(':teamid' => array($teamid, PDO::PARAM_INT));
			$query = $db->prepare('SELECT `name` FROM `teams` WHERE `id`=:teamid LIMIT 0,1');
			if (!$db->execute($query, $params))
			{
				$tmpl->assign('teamName', 'Error: No team name found for specified teamid.');
				$tmpl->display();
				$db->logError('FATAL ERROR: Invalid query to find out team name in '
							  . __FILE__ . ': function: showOpponentStats');
				die();
			}
			
			// if teamName not set, template has to present an error and stop output, too
			if ($row = $db->fetchRow($query))
			{
				$tmpl->assign('teamName', $row['name']);
			} else
			{
				$tmpl->display();
				die();
			}
			$db->free($query);
			unset($row);
			
			
			// collect team opponent informations for specified teamid
			$teamOpponents = array();
			$query = $db->prepare('SELECT * FROM `matches` '
								  . 'WHERE `team1ID`=:teamid OR `team2ID`=:teamid');
			$db->execute($query, $params);
			while ($row = $db->fetchRow($query))
			{
				$teamOpponents[] = $this->addToTeamList($row);
			}
			$db->free($query);
			unset($row);
			
			// pass the opponent data to template
			$tmpl->assign('teamOpponents', $teamOpponents);
						
/*			$team = array();
			while ($row = $db->fetchRow($query))
			{
				$teamLeader = intval($row['leader_userid']);
				
				
				$team['profileLink'] = './?profile=' . $row['id'];
				$team['name'] = $row['name'];
				$team['score'] = $row['score'];
				$team['scoreClass'] = $this->rankScore($row['score']);
				$team['matchSearchLink'] = ('../Matches/?search_string=' . $row['name']
												. '&amp;search_type=team+name'
												. '&amp;search_result_amount=20'
												. '&amp;search=Search');
				$team['matchCount'] = $row['num_matches_played'];
				$team['memberCount'] = $row['member_count'];
				$team['leaderLink'] = '../Players/?profile=' . $row['leader_userid'];
				$team['leaderName'] = $row['leader_name'];
				$team['activityNew'] = $row['activityNew'];
				$team['activityOld'] = $row['activityOld'];
				$team['created'] = $row['created'];
				
				$team['wins'] = intval($row['num_matches_won']);
				$team['draws'] = intval($row['num_matches_draw']);
				$team['losses'] = intval($row['num_matches_lost']);
				$team['total'] = $team['wins'] + $team['draws'] + $team['losses'];
				
				$tmpl->assign('teamDescription', $row['description']);
			}
			$db->free($query);
			$tmpl->assign('team', $team);
			
			$tmpl->assign('showPMButton', $user->getID() > 0 ? true : false);
			
			
			$showMemberActionOptions = false;
			if ($user->getID() === $teamLeader
				|| $user->getPermission('allow_kick_any_team_members'))
			{
				$showMemberActionOptions = true;
			}
			$members = array();
			$query = $db->prepare('SELECT `players`.`name` AS `player_name`'
								  . ', `players`.`id` AS `userid`'
								  . ', (SELECT `name` FROM `countries`'
								  . ' WHERE `countries`.`id`=`users_profile`.`location`'
								  . ' AND `users_profile`.`playerid`=`players`.`id` LIMIT 1)'
								  . ' AS `country_name`'
								  . ', (SELECT `flagfile` FROM `countries`'
								  . ' WHERE `countries`.`id`=`users_profile`.`location`'
								  . ' AND `users_profile`.`playerid`=`players`.`id` LIMIT 1)'
								  . ' AS `flagfile`'
								  . ', `users_profile`.`joined`'
								  . ', `users_profile`.`last_login`'
								  . ' FROM `players`,`users_profile`'
								  . 'WHERE `players`.`id`=`users_profile`.`playerid`'
								  . ' AND `teamid`=?');
			$db->execute($query, $teamid);
			while ($row = $db->fetchRow($query))
			{
				// rename db result fields and assemble some additional informations
				// use a temporary array for better readable (but slower) code
				$prepared = array();
				if (!$showMemberActionOptions && $user->getID() === intval($row['userid']))
				{
					$showMemberActionOptions = true;
				}
				$prepared['profileLink'] = '../Players/?profile=' . $row['userid'];
				$prepared['userName'] = $row['player_name'];
				$prepared['permissions'] = $teamLeader === intval($row['userid']) ? 'Leader' : 'Standard';
				$prepared['countryName'] = $row['country_name'];
				if (strlen($row['flagfile']) > 0)
				{
					$prepared['countryFlag'] = '../Flags/' . $row['flagfile'];
				}
				$prepared['joined'] = $row['joined'];
				
				// show leave/kick links if permission is given
				// a team leader can not leave or be kicked
				// you must first give someone else leadership
				if (($user->getID() === $teamLeader
					|| $user->getID() === intval($row['userid'])
					|| $user->getPermission('allow_kick_any_team_members'))
					&& $user->getID() !== $teamLeader)
				{
					$prepared['removeLink'] = './?remove=' . intval($row['userid']);
					
					if ($user->getID() === intval($row['userid']))
					{
						$prepared['removeDescription'] = 'Leave team';
					} else
					{
						$prepared['removeDescription'] = 'Kick member from team';
					}
				}
				
				// append current member data
				$members[] = $prepared;
			}
			$db->free($query);
			unset($prepared);
			$tmpl->assign('members', $members);
			$tmpl->assign('showMemberActionOptions', $showMemberActionOptions);
			
			
			// show last entered matches
			$matches = array();
			
			// show available options if any available
			$allowEdit = $user->getPermission('allow_edit_match');
			$allowDelete = $user->getPermission('allow_delete_match');
			$tmpl->assign('showMatchActionOptions', $allowEdit || $allowDelete);
			$tmpl->assign('allowEdit', $allowEdit);
			$tmpl->assign('allowDelete', $allowDelete);
			
			// get match data
			// sort the data by id to find out if abusers entered data a loong time in the past
			$query = $db->prepare('SELECT `timestamp`,`team1ID`,`team2ID`,'
								  . '(SELECT `name` FROM `teams` WHERE `id`=`team1ID`) AS `team1_name`'
								  . ',(SELECT `name` FROM `teams` WHERE `id`=`team2ID`) AS `team2_name`'
								  . ',`team1_points`,`team2_points`,`userid`'
								  . ',(SELECT `players`.`name` FROM `players`'
								  . ' WHERE `players`.`id`=`matches`.`userid`)'
								  . ' AS `playername`'
								  . ',`matches`.`id`'
								  . ' FROM `matches` WHERE `matches`.`team1ID`=?'
								  . ' OR `matches`.`team2ID`=?'
								  . ' ORDER BY `id` DESC LIMIT 0,10');
			$db->execute($query, array($teamid, $teamid));
			while ($row = $db->fetchRow($query))
			{
				// rename db result fields and assemble some additional informations
				// use a temporary array for better readable (but slower) code
				$prepared = array();
 				$prepared['time'] = $row['timestamp'];
				$prepared['team1Link'] = '../Teams/?profile=' . $row['team1ID'];
				$prepared['team2Link'] = '../Teams/?profile=' . $row['team2ID'];
				$prepared['teamName1'] = $row['team1_name'];
				$prepared['teamName2'] = $row['team2_name'];
				$prepared['score1'] = $row['team1_points'];
				$prepared['score2'] = $row['team2_points'];
				$prepared['lastModBy'] = $row['playername'];
				
				if ($allowEdit)
				{
					$prepared['editLink'] = '../Matches/?edit=' . $row['id'];
				}
				if ($allowDelete)
				{
					$prepared['deleteLink'] = '../Matches/?delete=' . $row['id'];
				}
				
				$matches[] = $prepared;
			}
			$tmpl->assign('matches', $matches);
*/
		}
	}
?>
