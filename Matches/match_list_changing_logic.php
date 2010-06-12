<?php
	// always be careful with $_SERVER['PHP_SELF']) because custom links can change the original page
	if (preg_match("/match_list_changing_logic.php/i", $_SERVER['PHP_SELF']))
	{
		die("This file is meant to be only included by other files!");
	}
	
	
	$randomkey_name = 'randomkey_matches';
	$viewerid = (int) getUserID();
		
	$allow_add_match = false;
	if (isset($_SESSION['allow_add_match']))
	{
		if (($_SESSION['allow_add_match']) === true)
		{
			$allow_add_match = true;
		}
	}
	
	$allow_edit_match = false;
	if (isset($_SESSION['allow_edit_match']))
	{
		if (($_SESSION['allow_edit_match']) === true)
		{
			$allow_edit_match = true;
		}
	}
	
	$allow_delete_match = false;
	if (isset($_SESSION['allow_delete_match']))
	{
		if (($_SESSION['allow_delete_match']) === true)
		{
			$allow_delete_match = true;
		}
	}
	
	function getTableChanged($site, $connection)
	{
		// compare the checksum to make sure the database was not checked before doing major changes
		if (isset($_POST['confirmed']))
		{
			$checksum = get_table_checksum($site, $connection);
			
			// there was no checksum saved yet
			if (!isset($_SESSION['checksum_matches']))
			{
				$_SESSION['checksum_matches'] = $checksum;
				return false;
			}
			
			if (!(strcmp($checksum, $_SESSION['checksum_matches']) === 0))
			{
				// checksum does not match -> table changes happened
				$_SESSION['checksum_matches'] = $checksum;
				
				echo '<p>There have been either matches deleted, entered or changed since you viewed at the editing form.</p>';
				echo '<p>Make sure you really want to proceed.</p>';
				return true;
			}
			return false;
		}
		return false;
	}
	
	function similarMatchEntered($site, $connection, $newerMatches = true)
	{
		// equal case should never happen
		$comparisonOperator = '>';
		if (!($newerMatches))
		{
			$comparisonOperator = '<';
		}
		
		// similar match entered already?
		// strategy: ask for one match before the entered one and one after the one to be entered and do not let the database engine do the comparison
		$query = 'SELECT `id`,`timestamp`,`team1_teamid`,`team2_teamid`,`team1_points`,`team2_points` FROM `matches`';
		$query .= ' WHERE (`timestamp`' . sqlSafeString($comparisonOperator) . "'" . sqlSafeString($_POST['match_day']). ' ' . sqlSafeString($_POST['match_time']) . "'";
		// sorting needed
		$query .= ') ORDER BY `timestamp` DESC';
		// only comparing nearest match in time
		$query .= ' LIMIT 0,1';
		
		if (!($result = @$site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
		{
			echo '<a class="button" href="./">overview</a>' . "\n";
			$site->dieAndEndPage('Unfortunately there seems to be a database problem and thus comparing timestamps (using operator '
								 . sqlSafeString($comparisonOperator) . ') of matches failed.');
		}
		
		// initialise values
		// casting the values to 0 is important
		// (a post variable having no value means it has to be set to 0 to successfully compare values here)
		$timestamp = '';
		$team_id1 = (int) $_POST['team_id1'];
		$team_id2 = (int) $_POST['team_id2'];
		$team_id1_matches = false;
		$team_id2_matches = false;
		$team1_points = (int) $_POST['team1_points'];
		$team2_points = (int) $_POST['team2_points'];
		$team1_points_matches = false;
		$team2_points_matches = false;
		
		while($row = mysql_fetch_array($result))
		{
			// we can save comparisons using a helper variable
			$team_ids_swapped = false;
			$timestamp = $row['timestamp'];
			$team_id1_matches = (((int) strcmp($row['team1_teamid'],$team_id1)) === 0);
			if (!$team_id1_matches)
			{
				$team_ids_swapped = true;
				$team_id1_matches = (((int) strcmp($row['team1_teamid'],$team_id2)) === 0);
			}
			
			if ($team_ids_swapped)
			{
				$team_id2_matches = (((int) strcmp($row['team2_teamid'],$team_id1)) === 0);
			} else
			{
				$team_id2_matches = (((int) strcmp($row['team2_teamid'],$team_id2)) === 0);
			}
			
			// same thing with the points
			if ($team_ids_swapped)
			{
				$team1_points_matches = (((int) strcmp($row['team1_points'],$team2_points)) === 0);
			} else
			{
				$team1_points_matches = (((int) strcmp($row['team2_teamid'],$team1_points)) === 0);
			}
			
			if ($team_ids_swapped)
			{
				$team2_points_matches = (((int) strcmp($row['team2_points'],$team1_points)) === 0);
			} else
			{
				$team2_points_matches = (((int) strcmp($row['team2_teamid'],$team2_points)) === 0);
			}
		}
		mysql_free_result($result);

		// useful debug output in case algorithm does not work as expected
//		echo '$team_id1_matches:' . $team_id1_matches;
//		echo '<br>$team_id2_matches:' . $team_id2_matches;
//		echo '<br>$team1_points_matches:' . $team1_points_matches;
//		echo '<br>$team2_points_matches:' . $team2_points_matches;
		// compare values and see if there was a similar match found
		if ($team_id1_matches && $team_id2_matches && $team1_points_matches && $team2_points_matches)
		{
			echo '<p>The nearest ';
			if ($newerMatches)
			{
				echo 'newer ';
			} else
			{
				echo 'older ';
			}
			echo ' match in the database is quite similar:</p>';
			// use the post data as much as possible instead of looking up the same data in the database
			echo '<p>At ' . $timestamp . ' teams ';
			team_name_from_id($site, $connection, $team_id1);
			echo ' - ';
			team_name_from_id($site, $connection, $team_id2);
			echo ' with result ' . htmlentities($team1_points) . ' - ' . htmlentities($team2_points) . '</p>';
			echo "\n";
			return true;
		}
		
		return false;
	}
	
	function get_score_at_that_time($site, $connection, $teamid, $timestamp, $viewerid, $equal=false)
	{
		$query = 'SELECT `team1_teamid`,`team2_teamid`,`team1_new_score`,`team2_new_score` FROM `matches`';
		$query .= ' WHERE `timestamp`<';
		if ($equal)
		{
			$query .= '=';
		}
		$query .= sqlSafeStringQuotes($timestamp);
		$query .= ' AND (`team1_teamid`=' . sqlSafeStringQuotes($teamid) . ' OR `team2_teamid`=' . sqlSafeStringQuotes($teamid) . ')';
		$query .= ' ORDER BY `timestamp` DESC LIMIT 0,1';
		if (!($result = $site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
		{
			echo '<a class="button" href="./">overview</a>' . "\n";
			$site->dieAndEndPage('Unfortunately there seems to be a database problem and thus comparing timestamps of matches failed.');
		}
		
		// write the score of team into variable score
		// default score is 1200
		$score = 1200;
		$rows = mysql_num_rows($result);
		if ($rows > 0)
		{
			while($row = mysql_fetch_array($result))
			{
				if (((int) $row['team1_teamid']) === $teamid)
				{
					$score = $row['team1_new_score'];
				} else
				{
					$score = $row['team2_new_score'];
				}
			}
			mysql_free_result($result);
		}
		
		// return the searched value
		return (int) $score;
	}
	
	function compute_scores($team_id1, $team_id2, &$score_a, &$score_b, $caps_a, $caps_b, &$diff, &$team_stats_changes=array())
	{
		global $site;
		
		/* A:  Using the old ratings oldA and oldB, the win probability for team A is calculated:
		 prob=1.0 / (1 + 10 ^ ((oldB-oldA)/400.0));
		 score= 1 if A wins, 0.5 for draw, 0 if B wins
		 The change in the ratings is then calculated by:
		 diff=50*(score-prob);
		 After that some rounding magic to integer is applied and the new ratings are calculated:
		 newA=oldA+diff, newB=oldB-diff; */
		
		// TODO: remove debug comment
		if ($site->debug_sql())
		{
			echo 'computed scores before: ' . $score_a . ', ' . $score_b;
		}
		
		if (is_array($team_stats_changes))
		{
			// write down the team id's of the teams in question
			// as the query does not compare the old total and the new total score
			// we can only use this as a mark where to check for changes later
			$team_stats_changes[$team_id1] = '';
			$team_stats_changes[$team_id2] = '';
		}
		
		if (is_numeric($score_a) && is_numeric($score_b))
		{
			$score_a = intval($score_a);
			$score_b = intval($score_b);
			$prob = 1.0 / (1 + pow(10, (($score_b-$score_a)/400.0)));
			$score = 0;
			
			if ($caps_a > $caps_b) // team a wins
			{
				$score= 1;
			} else if ($caps_a == $caps_b) // draw
			{
				$score = 0.5;
			} else // team b wins
			{
				$score = 0;
			}
			$diff=50*($score-$prob);
			
			// do not forget to round the values to integers
			$score_a = round($score_a + $diff);
			$score_b = round($score_b - $diff);
			
			// compute absolute value of rounded difference
			$diff = abs(round($diff));
		}
		
		if ($site->debug_sql())
		{
			echo ' values after ' . $score_a . ', ' .  $score_b . ', ' . $caps_a . ', ' . $caps_b . ', ' . $diff . '';
		}
	}
	
	function unlock_tables($site, $connection)
	{
		$query = 'UNLOCK TABLES';
		if (!($site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
		{
			echo '<a class="button" href="./">overview</a>' . "\n";
			$site->dieAndEndPage('Unfortunately unlocking tables failed. This likely leads to an access problem to database!');
		}
	}
	
	if (isset($_GET['enter']) || isset($_GET['edit']) || isset($_GET['delete']))
	{
		echo '<a class="button" href="./">overview</a>' . "\n";
		
		if ($viewerid === 0)
		{
			if (isset($_GET['enter']))
			{
				echo '<p>You need to login in order to enter matches!</p>';
			}
			if (isset($_GET['edit']))
			{
				echo '<p>You need to login in order to edit matches!</p>';
			}
			if (isset($_GET['delete']))
			{
				echo '<p>You need to login in order to delete matches!</p>';
			}
			$site->dieAndEndPage('');
		}
		
		if (!$allow_add_match && isset($_GET['enter']))
		{
			$site->dieAndEndPage('You (id=' . sqlSafeString($viewerid) . 'have no permissions to enter new matches!');
		}
		if (!$allow_edit_match && isset($_GET['edit']))
		{
			$site->dieAndEndPage('You (id=' . sqlSafeString($viewerid) . 'have no permissions to edit matches!');
		}
		if (!$allow_delete_match && isset($_GET['delete']))
		{
			$site->dieAndEndPage('You (id=' . sqlSafeString($viewerid) . 'have no permissions to delete matches!');
		}
		
		$confirmed = (int) 0;
		if (isset($_POST['confirmed']))
		{
			$confirmed = (int) $_POST['confirmed'];
		}
		
		// someone is trying to break the form
		if (($confirmed < 0) || ($confirmed > 3))
		{
			$site->dieAndEndPage('Your (id='. $viewerid. ') attempt to insert wrong data into the form was detected.');
		}
		
		
		if ($confirmed > 0)
		{
			// when entering or editing a match the first step is looking at the match form
			// deleting only requires to confirm once and thus we can skip the preview step
			// (that would be usually confirmed === 1)
			if (isset($_GET['delete']))
			{
				$confirmed = (int) 2;
			}
			
			// check if the supplied date is correct
			
			// read out day
			$match_day = '';
			if (isset($_POST['match_day']))
			{
				$match_day = $_POST['match_day'];
			}
			
			// read out time of day
			$match_time = '';
			if (isset($_POST['match_time']))
			{
				$match_time = $_POST['match_time'];
			}
			
			// sanity checks regarding date not necessary when deleting matches
			if (!(isset($_GET['delete'])))
			{
				// sample day: 2009-12-15
				if (!(preg_match("/(0|1|2|3|4|5|6|7|8|9){4,}-(0|1|2|3|4|5|6|7|8|9){2}-(0|1|2|3|4|5|6|7|8|9){2}/", $match_day)))
				{
					echo '<p>Please make sure your specified date is in correct format. Do not forget leading zeros.</p>' . "\n";
					$confirmed = (int) 0;
				}
			}
		}
		
		
		// also no need for time sanity check when deleting matches
		if (!(isset($_GET['delete'])))
		{
			if ($confirmed > 0)
			{
				// sanity checks regarding time format
				// sample time: 15:21:35
				if (!(preg_match("/(0|1|2|3|4|5|6|7|8|9){2}:(0|1|2|3|4|5|6|7|8|9){2}:(0|1|2|3|4|5|6|7|8|9){2}/", $match_time)))
				{
					echo '<p>Please make sure your specified time is in correct format. Do not forget leading zeros.</p>' . "\n";
					$confirmed = (int) 0;
				}
			}
		}
		
		if ($confirmed > 0)
		{
			// FIXME: MORE sanity checks regarding date and time format
			// e.g. check if the format is YYYY-MM-DD HH:MM:SS and if the date is in future or older than 2 months
			// get the unix timestamp from the date and time
			if (!($specifiedTime = strtotime($match_day . ' ' . $match_time)))
			{
				echo '<p>Please make sure your specified date and time is valid!</p>' . "\n";
				$confirmed = (int) 0;
			}
			
			// look up if the day does exist in Gregorian calendar
			if (!(checkdate(date('m', $specifiedTime), date('d', $specifiedTime), date('Y', $specifiedTime))))
			{
				echo '<p>Please make sure your specified date and time is a valid Gregorian date.</p>' . "\n";
				$confirmed = (int) 0;
			}
			
			// is match in the future?
			$curTime = (int) strtotime('now');
			if ((((int) $specifiedTime) - $curTime) >= 0)
			{
				echo '<p>You tried to enter, edit or delete a match that would have been played in the future.';
				echo ' Only matches in the past can be entered, edited or deleted.</p>' . "\n";
				$confirmed = (int) 0;
			}
		}
		
		if ($confirmed > 0)
		{
			// is match older than 2 months?
			$eightWeeksAgo = (int) strtotime('now -8 weeks');
			if (((int) $specifiedTime) <= $eightWeeksAgo)
			{
				echo '<p>You tried to enter, edit or delete a match that is older than 8 weeks.';
				echo ' Only matches played in the last 8 weeks can be entered, edited or deleted.</p>' . "\n";
				$confirmed = (int) 0;
			}

			
			// check if team ids are ok
			$team_id1 = 0;
			if (isset($_POST['team_id1']))
			{
				$team_id1 = (int) $_POST['team_id1'];
			}
			
			$team_id2 = 0;
			if (isset($_POST['team_id2']))
			{
				$team_id2 = (int) $_POST['team_id2'];
			}
			
			// extract team id list from match to do sanity checks
			if (isset($_GET['delete']))
			{
				$query = 'SELECT `team1_teamid`, `team2_teamid` FROM `matches` WHERE `id`=' . "'" . sqlSafeString((int) $_GET['delete']) . "'";
				if (!($result = $site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
				{
					$site->dieAndEndPage('Could not find out the id list of teams specified in the match that should be deleted');
				}
				
				while($row = mysql_fetch_array($result))
				{
					$team_id1 = $row['team1_teamid'];
					$team_id2 = $row['team2_teamid'];
				}
			}
			
			// matches against deleted teams can not be entered, edited or deleted
			// check $team_id1
			$query = 'SELECT `deleted` FROM `teams_overview` WHERE `deleted`=' . "'" . sqlSafeString('2') . "'";
			$query .= ' AND `teamid`=' . "'" . sqlSafeString($team_id1) . "'";
			if (!($result_active = @$site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
			{
				// query was bad, error message was already given in $site->execute_query(...)
				$site->dieAndEndPage('could not find out if team with id ' . sqlSafeString($team_id1) . ' is active.');
			}
			
			// walk through results
			while($row = mysql_fetch_array($result_active))
			{
				// now we know the current team is deleted
				$site->dieAndEndPage('User (id=' . sqlSafeString($viewerid) . ') tried to modify a match against team with id '
									 . sqlSafeString($team_id1) . ' (deleted team).');
			}
			mysql_free_result($result_active);
			
			// check $team_id2
			$query = 'SELECT `deleted` FROM `teams_overview` WHERE `deleted`=' . "'" . sqlSafeString('2') . "'";
			$query .= ' AND `teamid`=' . "'" . sqlSafeString($team_id2) . "'";
			if (!($result_active = @$site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
			{
				// query was bad, error message was already given in $site->execute_query(...)
				$site->dieAndEndPage('could not find out if team with id ' . sqlSafeString($team_id2) . ' is active.');
			}
			
			// walk through results
			while($row = mysql_fetch_array($result_active))
			{
				// now we know the current team is deleted
				$site->dieAndEndPage('User (id=' . sqlSafeString($viewerid) . ') tried to modify a match against team with id '
									 . sqlSafeString($team_id2) . ' (deleted team).');
			}
			mysql_free_result($result_active);
			
			
			
			// we also need to find out if the teams do exist at all
			// check $team_id1
			$query = 'SELECT `id` FROM `teams` WHERE `id`=' . "'" . sqlSafeString($team_id1) . "'";
			// id is a unique identifier and therefore there will always be only one team at max with the same id
			$query .= ' LIMIT 1';
			if (!($result_exists = @$site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
			{
				// query was bad, error message was already given in $site->execute_query(...)
				$site->dieAndEndPage('could not find out if team with id ' . sqlSafeString($team_id1) . ' exists.');
			}
			if ((int) mysql_num_rows($result_exists) < 1)
			{
				$site->dieAndEndPage('User (id=' . sqlSafeString($viewerid) . ') tried to modify a match against team with id '
									 . sqlSafeString($team_id1) . ' (team does not exist).');
			}
			mysql_free_result($result_exists);
			
			// check $team_id2
			$query = 'SELECT `id` FROM `teams` WHERE `id`=' . "'" . sqlSafeString($team_id2) . "'";
			// id is a unique identifier and therefore there will always be only one team at max with the same id
			$query .= ' LIMIT 1';
			if (!($result_exists = @$site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
			{
				// query was bad, error message was already given in $site->execute_query(...)
				$site->dieAndEndPage('could not find out if team with id ' . sqlSafeString($team_id2) . ' exists.');
			}
			if ((int) mysql_num_rows($result_exists) < 1)
			{
				$site->dieAndEndPage('User (id=' . sqlSafeString($viewerid) . ') tried to modify a match against team with id '
									 . sqlSafeString($team_id2) . ' (team does not exist).');
			}
			mysql_free_result($result_exists);
			
			
			if (!(isset($_GET['delete'])) && $team_id1 === $team_id2)
			{
				// both two teams must be different except when compositing the entry
				echo '<p>Error: Both teams must not be equal and counted as official match. That would have been a funmatch.</p>' . "\n";
				$site->dieAndEndPage('');
			}
			
			if (!(isset($_GET['delete'])) && (($team_id1 === 0) || ($team_id2 === 0)))
			{
				// entering wrong data on purpose is not good, this case should even be impossible through the GUI
				$site->dieAndEndPage('Neither team of the ones being specified for entering a match should have the id 0. This incident was created by user with id '
									 . sqlSafeString($viewerid));
			}
			
			// ready to insert the data
			// get number of points made by team 1
			$team1_points = 0;
			if (isset($_POST['team1_points']))
			{
				$team1_points = (int) $_POST['team1_points'];
			}
			
			// get number of points made by team 2
			$team2_points = 0;
			if (isset($_POST['team2_points']))
			{
				$team2_points = (int) $_POST['team2_points'];
			}
			
			// this check is also used to initialise the checksum in the session
			if (getTableChanged($site, $connection))
			{
				// never enter new data in case the table has changed -> probable inconsistencies
				if ($confirmed > 0)
				{
					$confirmed = (int) 1;
				}
			}
			
			
			// FIXME: check if teams are deleted
			if ($confirmed > 1)
			{
				$timestamp = ($_POST['match_day']) . ' ' . sqlSafeString($_POST['match_time']);
				
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
			}
		}
	}
	
	// get the id of the match in question
	$match_id = 0;
	if (isset($_GET['edit']) || isset($_GET['delete']))
	{
		if (isset($_GET['edit']))
		{
			$match_id = (int) $_GET['edit'];
		}
		if (isset($_GET['delete']))
		{
			$match_id = (int) $_GET['delete'];
		}
		
		
		if ($match_id === 0)
		{
			if (isset($_GET['edit']))
			{
				$site->dieAndEndPage('You (id=' . sqlSafeString($viewerid) . ') tried to edit the reserved match with id 0.');
			}
			if (isset($_GET['delete']))
			{
				$site->dieAndEndPage('You (id=' . sqlSafeString($viewerid) . ') tried to delete the reserved match with id 0.');
			}
		}
		
		if ($match_id < 0)
		{
			if (isset($_GET['edit']))
			{
				$site->dieAndEndPage('You (id=' . sqlSafeString($viewerid) . ') tried to edit the reserved match with id ' . sqlSafeString($match_id). '.');
			}
			if (isset($_GET['delete']))
			{
				$site->dieAndEndPage('You (id=' . sqlSafeString($viewerid) . ') tried to delete the reserved match with id ' . sqlSafeString($match_id). '.');
			}
		}
	}
	
	
	if (isset($_GET['enter']))
	{
		if ($confirmed > 0)
		{
			// checked if there is already a match entered at that time
			// scores depend on the order, two matches done at the same time lead to undefined behaviour
			$query = 'SELECT `timestamp` FROM `matches` WHERE `timestamp`=' . sqlSafeStringQuotes(($_POST['match_day'])  . ' ' . ($_POST['match_time']));
			if (!($result = @$site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
			{
				echo '<a class="button" href="./">overview</a>' . "\n";
				$site->dieAndEndPage('Unfortunately there seems to be a database problem and thus comparing timestamps (using equal operator) of matches failed.');
			}
			
			$rows = (int) mysql_num_rows($result);
			mysql_free_result($result);
			
			if ($rows > 0)
			{
				// go back to the first step of entering a match
				echo '<p>There is already a match entered at that exact time.';
				echo ' There can be only one finished at the same time because the scores depend on the order of the played matches.</p>' . "\n";
				// just warn them and let them enter it all again by hand
				echo 'Please enter the match with a different time.</p>' . "\n";
				echo '<form enctype="application/x-www-form-urlencoded" method="post" action="?enter">' . "\n";
				echo '	<div><input type="hidden" name="confirmed" value="0"></div>' . "\n";
				// pass the match values to the next page so the previously entered data can be set default for the new form
				echo '	<input type="hidden" name="match_day" value="';
				if (isset($_POST['match_day']))
				{
					echo htmlspecialchars($_POST['match_day']);
				}
				echo '">' . "\n";
				
				echo '	<input type="hidden" name="match_time" value="';
				if (isset($_POST['match_time']))
				{
					echo htmlspecialchars($_POST['match_time']);
				}
				echo '">' . "\n";
				
				echo '	<input type="hidden" name="team_id1" value="';
				if (isset($_POST['team_id1']))
				{
					echo htmlspecialchars((int) $_POST['team_id1']);
				}
				echo '">' . "\n";
				
				echo '	<input type="hidden" name="team_id2" value="';
				if (isset($_POST['team_id2']))
				{
					echo htmlspecialchars((int) $_POST['team_id2']);
				}
				echo '">' . "\n";
				
				echo '	<input type="hidden" name="team1_points" value="';
				if (isset($_POST['team1_points']))
				{
					echo htmlspecialchars($_POST['team1_points']);
				}
				echo '">' . "\n";
				
				echo '	<input type="hidden" name="team2_points" value="';
				if (isset($_POST['team2_points']))
				{
					echo htmlspecialchars($_POST['team2_points']);
				}
				echo '">' . "\n";
				
				echo '	<div><input type="submit" name="match_cancel" value="Cancel and change match data" id="send"></div>' . "\n";
				echo '</form>' . "\n";
				$site->dieAndEndPage('');
			}
		}
	}
	
	// only when entering a match we can use the score from the team overview
	// in all other cases we are forced to recalculate all scores
	// beginning from the position of the match in question
	if (isset($_GET['enter']))
	{
		if ($confirmed === 2)
		{			
			// checked if there are newer matches already entered
			$query = 'SELECT * FROM `matches` WHERE `timestamp`>' . sqlSafeStringQuotes(($_POST['match_day'])  . ' ' . ($_POST['match_time']));
			if (!($result = @$site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
			{
				echo '<a class="button" href="./">overview</a>' . "\n";
				$site->dieAndEndPage('Unfortunately there seems to be a database problem and thus comparing timestamps of matches failed.');
			}
			
			// in case there are no newer matches the number of rows in query result is 0
			$rows = (int) mysql_num_rows($result);
			mysql_free_result($result);
			
			// find out if there are similar matches
			$similarMatchFound = false;
			$similarMatchFound = similarMatchEntered($site, $connection, true);
			if (!($similarMatchFound))
			{
				// look for a possible last show stopper
				$similarMatchFound = similarMatchEntered($site, $connection, false);
			} else
			{
				// add space between last similar match and the one probably following
				$site->write_self_closing_tag('br');
				
				// only call the function for user information, ignore result
				similarMatchEntered($site, $connection, false);
			}
			
			// if a similar match was found, ask for user confirmation (again!)
			if ($similarMatchFound)
			{
				echo '<form enctype="application/x-www-form-urlencoded" method="post" action="?enter">';
				// confirmed value of 3 will disable the previous similarity check -> no infinite confirmation loop
				echo '<div><input type="hidden" name="confirmed" value="3"></div>' . "\n";
				
				$new_randomkey_name = $randomkey_name . microtime();
				$new_randomkey = $site->set_key($new_randomkey_name);
				echo '<div><input type="hidden" name="key_name" value="' . htmlspecialchars($new_randomkey_name) . '"></div>' . "\n";
				echo '<div><input type="hidden" name="' . htmlspecialchars($randomkey_name) . '" value="';
				echo urlencode(($_SESSION[$new_randomkey_name])) . '"></div>' . "\n";
				
				echo '	<input type="hidden" name="match_day" value="';
				if (isset($_POST['match_day']))
				{
					echo htmlspecialchars($_POST['match_day']);
				}
				echo '">' . "\n";
				
				echo '	<input type="hidden" name="match_time" value="';
				if (isset($_POST['match_time']))
				{
					echo htmlspecialchars($_POST['match_time']);
				}
				echo '">' . "\n";
				
				echo '	<input type="hidden" name="team_id1" value="';
				if (isset($_POST['team_id1']))
				{
					echo htmlspecialchars((int) $_POST['team_id1']);
				}
				echo '">' . "\n";
				
				echo '	<input type="hidden" name="team_id2" value="';
				if (isset($_POST['team_id2']))
				{
					echo htmlspecialchars((int) $_POST['team_id2']);
				}
				echo '">' . "\n";
				
				echo '	<input type="hidden" name="team1_points" value="';
				if (isset($_POST['team1_points']))
				{
					echo htmlspecialchars($_POST['team1_points']);
				}
				echo '">' . "\n";
				
				echo '	<input type="hidden" name="team2_points" value="';
				if (isset($_POST['team2_points']))
				{
					echo htmlspecialchars($_POST['team2_points']);
				}
				echo '">' . "\n";
				echo '<div><input type="submit" name="match_enter_confirmed" value="Really enter the new match now!" id="send"></div>' . "\n";
				echo '</form>' . "\n";
				
				// do not enter, wait for confirmation
				$site->dieAndEndPage('');
			}
		}
	}
	
	// force recalculating process when any match should be edited or deleted
	if (isset($_GET['edit']) || isset($_GET['delete']))
	{
		$rows = 1;
	}
	
	if ((isset($_GET['enter']) || isset($_GET['edit']) || isset($_GET['delete'])) && ($confirmed > 1))
	{
		// only show a confirmation question, the matches entered in different chronological order is not too unusual and perfectly valid
		// just imagine someone takes a while to report to a referee where the other teams were quicker reporting
		if ($rows > 0)
		{
			// should go through old list of matches and recompute all further scores from that point
			// concurrent access could alter the table while much of the data inside the table is recalculated
			// as most of the data in table depends on each other we must not access it in a concurrent way
			
			// any call of unlock_tables(...) will unlock the table
			$query = 'LOCK TABLES `matches` WRITE, `teams_overview` WRITE, `teams_profile` WRITE';
			if (!($result = @$site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
			{
				echo '<a class="button" href="./">overview</a>' . "\n";
				unlock_tables($site, $connection);
				$site->dieAndEndPage('Unfortunately locking the matches table failed and thus altering the list of matches was cancelled.');
			}
			
			// first get the score of team 1 at that time
			// remember the first team entered in the form must not be the first team in the match entry of database
			$team1_new_score = get_score_at_that_time($site, $connection, $team_id1, $timestamp, $viewerid);
			// find out the score for team 2 like done above for team 1
			$team2_new_score = get_score_at_that_time($site, $connection, $team_id2, $timestamp, $viewerid);
			// mark score might has changed for these teams
			$team_stats_changes[$team_id1];
			$team_stats_changes[$team_id2];
			
			// we got the score for both team 1 and team 2 at that point
			// thus we can enter the match at this point
			$diff = 0;
			$team_stats_changes = array();
			compute_scores($team_id1, $team_id2, $team1_new_score, $team2_new_score, $team1_points, $team2_points, $diff, $team_stats_changes);
			
//			// write down old score
//			$team_stats_changes[$team_id1]['old_score'] = $team1_new_score;
//			$team_stats_changes[$team_id2]['old_score'] = $team2_new_score;
			
			// insert new entry
			if (isset($_GET['enter']))
			{
				$query = 'INSERT INTO `matches` (`timestamp`, `team1_teamid`,';
				$query .= ' `team2_teamid`, `team1_points`, `team2_points`, `team1_new_score`, `team2_new_score`)';
				$query .= ' VALUES (' . sqlSafeStringQuotes($timestamp) . ', ' . sqlSafeStringQuotes($team_id1) .', ';
				$query .= sqlSafeStringQuotes($team_id2) . ', ' . sqlSafeStringQuotes($team1_points) . ', ' . sqlSafeStringQuotes($team2_points);
				$query .= ', ' . sqlSafeStringQuotes($team1_new_score) . ', ' . sqlSafeStringQuotes($team2_new_score);
				$query .= ')';
				if (!($result = $site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
				{
					unlock_tables($site, $connection);
					$site->dieAndEndPage('The match reported by user with id ' . sqlSafeString($viewerid) . ' could not be entered due to a sql problem!');
				}
				
				require_once('update_match_stats.php');
				update_match_stats_entered($team_id1, $team_id2, $team1_points, $team2_points, $site, $connection);
				
				// done with the entering of that one match
				echo '<p>The match was entered successfully.</p>' . "\n";
			}
			
			// update match draw, won and lost count (match edited case)
			
			// first find out which team won the match before editing the table
			$query = '';
			// find out the appropriate team id list for the edited match
//			$query = 'SELECT `team1_teamid`, `team2_teamid`, `team1_points`, `team2_points`, team1_new_score, team2_new_score FROM `matches`';
			$query = 'SELECT `team1_teamid`, `team2_teamid`, `team1_points`, `team2_points` FROM `matches`';
			$query .= ' WHERE `id`=' . "'" . sqlSafeString($match_id) . "'";
			if (!($result = $site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
			{
				unlock_tables($site, $connection);
				$site->dieAndEndPage('Could not find out id for team 1 given match id ' . sqlSafeString($match_id) . ' due to a sql problem!');
			}
			
			if (isset($_GET['edit']))
			{
				require_once('team_match_count.php');
				
				// initialise variables
				$team1_checkid = 0;
				$team2_checkid = 0;
				$team1_points_before = 0;
				$team2_points_before = 0;
				
				while($row = mysql_fetch_array($result))
				{
					$team1_checkid = (int) $row['team1_teamid']; // team id 1 before
					$team2_checkid = (int) $row['team2_teamid']; // team id 2 before
					$team1_points_before = (int) $row['team1_points'];
					$team2_points_before = (int) $row['team2_points'];
				}
				mysql_free_result($result);
				
				// if a team did not participate in the newer version, it will be marked inside the following function
				update_team_match_edit($team1_points_before, $team2_points_before,
									   $team1_points, $team2_points,
									   $team1_checkid, $team2_checkid,
									   $team_id1, $team_id2);
			}
			
			if (isset($_GET['delete']))
			{
				// initialise variables
				$team1_points_before = 0;
				$team2_points_before = 0;
				
				// fill variables with values from query
				while($row = mysql_fetch_array($result))
				{
					$team1_points_before = (int) $row['team1_points'];
					$team2_points_before = (int) $row['team2_points'];
				}
				mysql_free_result($result);
			}
			
			// editing means the entry in question should be updated with the data provided by request
			if (isset($_GET['edit']))
			{
				// update match table (perform the editing)
				$query = 'UPDATE `matches` SET `timestamp`=' . "'" . sqlSafeString($timestamp) . "'";
				$query .= ',`team1_teamid`=' . "'" . sqlSafeString($team_id1) . "'";
				$query .= ',`team2_teamid`=' . "'" . sqlSafeString($team_id2) . "'";
				$query .= ',`team1_points`=' . "'" . sqlSafeString($team1_points) . "'";
				$query .= ',`team2_points`=' . "'" . sqlSafeString($team2_points) . "'";
				$query .= ',`team1_new_score`=' . "'" . sqlSafeString($team1_new_score) . "'";
				$query .= ',`team2_new_score`=' . "'" . sqlSafeString($team2_new_score) . "'";
				// use current row id to access the entry
				$query .= ' WHERE `id`=' . "'" . sqlSafeString($match_id) . "'";
				// only one row needs to be updated
				$query .= ' LIMIT 1';
				
				if (!($result = $site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
				{
					unlock_tables($site, $connection);
					$site->dieAndEndPage('The match reported by user with id ' . sqlSafeString($viewerid) . ' could not be edited due to a sql problem!');
				}
				
//				// score has been updated, log it
//				$team_stats_changes[$team_id1]['new_score'] = $team1_new_score;
//				$team_stats_changes[$team_id2]['new_score'] = $team2_new_score;
				// done with the entering of that one match
				echo '<p>The match was edited successfully.</p>' . "\n";
			}
			
			if (isset($_GET['delete']))
			{
				// originally team 1 won
				if ($team1_points_before > $team2_points_before)
				{
					// update team 1 data
					$query = 'UPDATE `teams_profile` SET ';
					$query .= '`num_matches_won`=`num_matches_won`-' . "'" . sqlSafeString('1') . "'";
					$query .= ', `num_matches_played`=`num_matches_played`-' . "'" . sqlSafeString('1') . "'";
					$query .= ' WHERE (`teamid`=' . "'" . sqlSafeString($team_id1) . "'" . ')';
					// only one team needs to be updated
					$query .= ' LIMIT 1';
					if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
					{
						unlock_tables($site, $connection);
						$site->dieAndEndPage('Could not update win/played count for team with id ' . sqlSafeString($team_id1) . ' due to a sql problem!');
					}
					// update team 2 data
					$query = 'UPDATE `teams_profile` SET ';
					$query .= '`num_matches_lost`=`num_matches_lost`-' . "'" . sqlSafeString('1') . "'";
					$query .= ', `num_matches_played`=`num_matches_played`-' . "'" . sqlSafeString('1') . "'";
					$query .= ' WHERE (`teamid`=' . "'" . sqlSafeString($team_id2) . "'" . ')';
					// only one team needs to be updated
					$query .= ' LIMIT 1';
					if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
					{
						unlock_tables($site, $connection);
						$site->dieAndEndPage('Could not update win/played count for team with id ' . sqlSafeString($team_id2) . ' due to a sql problem!');
					}
				}
				
				// originally team 2 won
				if ($team1_points_before < $team2_points_before)
				{
					// update team 1 data
					$query = 'UPDATE `teams_profile` SET ';
					$query .= '`num_matches_lost`=`num_matches_lost`-' . "'" . sqlSafeString('1') . "'";
					$query .= ', `num_matches_played`=`num_matches_played`-' . "'" . sqlSafeString('1') . "'";
					$query .= ' WHERE (`teamid`=' . "'" . sqlSafeString($team_id1) . "'" . ')';
					// only one team needs to be updated
					$query .= ' LIMIT 1';
					if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
					{
						unlock_tables($site, $connection);
						$site->dieAndEndPage('Could not update lost/played count for team with id ' . sqlSafeString($team_id1) . ' due to a sql problem!');
					}
					// update team 2 data
					$query = 'UPDATE `teams_profile` SET ';
					$query .= '`num_matches_won`=`num_matches_won`-' . "'" . sqlSafeString('1') . "'";
					$query .= ', `num_matches_played`=`num_matches_played`-' . "'" . sqlSafeString('1') . "'";
					$query .= ' WHERE (`teamid`=' . "'" . sqlSafeString($team_id2) . "'" . ')';
					// only one team needs to be updated
					$query .= ' LIMIT 1';
					if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
					{
						unlock_tables($site, $connection);
						$site->dieAndEndPage('Could not update lost/played count for team with id ' . sqlSafeString($team_id2) . ' due to a sql problem!');
					}
				}
				
				// originally the match ended in a draw
				if ($team1_points_before === $team2_points_before)
				{
					// update team 1 data
					$query = 'UPDATE `teams_profile` SET ';
					$query .= '`num_matches_draw`=`num_matches_draw`-' . "'" . sqlSafeString('1') . "'";
					$query .= ', `num_matches_played`=`num_matches_played`-' . "'" . sqlSafeString('1') . "'";
					$query .= ' WHERE (`teamid`=' . "'" . sqlSafeString($team_id1) . "'" . ')';
					// only one team needs to be updated
					$query .= ' LIMIT 1';
					if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
					{
						unlock_tables($site, $connection);
						$site->dieAndEndPage('Could not update draw/played count for team with id ' . sqlSafeString($team_id1) . ' due to a sql problem!');
					}
					// update team 2 data
					$query = 'UPDATE `teams_profile` SET ';
					$query .= '`num_matches_draw`=`num_matches_draw`-' . "'" . sqlSafeString('1') . "'";
					$query .= ', `num_matches_played`=`num_matches_played`-' . "'" . sqlSafeString('1') . "'";
					$query .= ' WHERE (`teamid`=' . "'" . sqlSafeString($team_id2) . "'" . ')';
					// only one team needs to be updated
					$query .= ' LIMIT 1';
					if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
					{
						unlock_tables($site, $connection);
						$site->dieAndEndPage('Could not update draw/played count for team with id ' . sqlSafeString($team_id2) . ' due to a sql problem!');
					}					
				}
				
				$query = 'DELETE FROM `matches` WHERE `id`=' . sqlSafeStringQuotes($match_id);
				// only one row needs to be updated
				$query .= ' LIMIT 1';
				
				if (!($result = $site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
				{
					unlock_tables($site, $connection);
					$site->dieAndEndPage('The match reported by user with id ' . sqlSafeString($viewerid) . ' could not be deleted due to a sql problem!');
				}
				
				// done with the entering of that one match
				echo '<p>The match was deleted successfully.</p>' . "\n";
			}
			
			
			// all matches played later must be updated in correct order
			// otherwise the score of teams would be no longer correct
			$query = 'SELECT `id`,`timestamp`,`team1_teamid`,`team2_teamid`,`team1_new_score`,`team2_new_score`,`team1_points`,`team2_points` FROM `matches`';
			$query .= ' WHERE `timestamp`>' . "'" . sqlSafeString($_POST['match_day']) . ' ' . sqlSafeString($_POST['match_time']) . "'";
			$query .= ' ORDER BY `timestamp`';
			if (!($result = @$site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
			{
				echo '<a class="button" href="./">overview</a>' . "\n";
				unlock_tables($site, $connection);
				$site->dieAndEndPage('Unfortunately there seems to be a database problem and thus comparing timestamps of matches failed.');
			}
			
//			$rows = (int) mysql_num_rows($result);
//			if ($rows === 0)
//			{
//				
//			}
			
			$team1_old_score = 1200;
			$team2_old_score = 1200;
			// each match needs to be recomputed to make sure scores are being up-to-date
			while($row = mysql_fetch_array($result))
			{
				// both team id's are needed
				$team_id1 = (int) $row['team1_teamid'];
				$team_id2 = (int) $row['team2_teamid'];
				// FIXME: remove debug comment
				echo '$team_id1: ' . $team_id1 . '<br>';
				echo '$team_id2: ' . $team_id2 . '<br>';
				
				// the POINTS of the game are needed, too..
				$team1_points = $row['team1_points'];
				$team2_points = $row['team2_points'];
				
				// and it is also required to know when the match happened
				$timestamp = $row['timestamp'];
				
				// get team scores at that point
				$team1_new_score = get_score_at_that_time($site, $connection, $team_id1, $timestamp, $viewerid);
				$team2_new_score = get_score_at_that_time($site, $connection, $team_id2, $timestamp, $viewerid);
				// mark these teams as having an updated score
				echo 'marking team scores as changed...get_score_at_that_time<br>';
				$team_stats_changes[$team_id1] = '';
				$team_stats_changes[$team_id2] = '';
				
				// FIXME: remove debug comment
				echo '$team1_new_score: ' . $team1_new_score . '<br>';
				echo '$team2_new_score: ' . $team2_new_score . '<br>';
				
				
				$diff = 0;
				compute_scores($team_id1, $team_id2, $team1_new_score,$team2_new_score, $team1_points, $team2_points, $diff, $team_stats_changes);
				
				// update score if necessary
				if (!($diff === 0))
				{
					$query = 'UPDATE `matches` SET `team1_new_score`=' . "'" . sqlSafeString($team1_new_score) . "'";
					$query .= ',`team2_new_score`=' . "'" . sqlSafeString($team2_new_score) . "'";
					// use current row id to access the entry
					$query .= ' WHERE `id`=' . "'" . sqlSafeString($row['id']) . "'";
					// only one row needs to be updated
					$query .= ' LIMIT 1';
					if (!($result_update = @$site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
					{
						// query was bad, error message was already given in $site->execute_query(...)
						unlock_tables($site, $connection);
						$site->dieAndEndPage('Updating team scores in nested query update of team scores failed. One needs to use the backup.');
						// FIXME: entire matches should now be locked to get attention!
					}
				}
			}
			mysql_free_result($result);
			// remove write lock
			unlock_tables($site, $connection);
			
			// lock table matches for read access only, lock teams_overview with write access to copy the data from matches
			$query = 'LOCK TABLES `matches` READ, `teams_overview` WRITE';
			if (!($result = @$site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
			{
				echo '<a class="button" href="./">overview</a>' . "\n";
				unlock_tables($site, $connection);
				$site->dieAndEndPage('Unfortunately locking the matches table failed and thus entering the match was cancelled.');
			}
			
			// the scores should now be updated in the matches table but not in the team overview
			
			// first get the list of teams
			$query = 'SELECT `id`,`teamid`,`score` FROM `teams_overview`';
			if (!($result = @$site->execute_query($site->db_used_name(), 'teams_overview', $query, $connection)))
			{
				// query was bad, error message was already given in $site->execute_query(...)
				unlock_tables($site, $connection);
				$site->dieAndEndPage('Updating team scores in nested query update of team scores failed. One needs to use the backup.');
				// FIXME: entire matches should now be locked to get attention!
			}
			
			while($row = mysql_fetch_array($result))
			{
				$new_score = get_score_at_that_time($site, $connection, ((int) $row['teamid']), $timestamp, $viewerid, true);
				// as the matches are sorted by date in the last loop iteration we will get the old scores of both team1 and team2
				
				// keep track of score changes
				if (isset($team_stats_changes[$row['teamid']]))
				{
					$team_stats_changes[$row['teamid']]['old_score'] = $row['score'];
					$team_stats_changes[$row['teamid']]['new_score'] = $new_score;
				}
				
				// TODO: safe the scores before the update in an array to display a nice difference table for status before and after update
				$query = 'UPDATE `teams_overview` SET `score`=';
				$query .= sqlSafeStringQuotes($new_score);
				// use current row id to access the entry
				$query .= ' WHERE `id`=' . "'" . sqlSafeString($row['id']) . "'";
				// only one row is updated per loop iteration
				$query .= ' LIMIT 1';
				if (!($result_update = @$site->execute_query($site->db_used_name(), 'teams_overview', $query, $connection)))
				{
					// query was bad, error message was already given in $site->execute_query(...)
					unlock_tables($site, $connection);
					$site->dieAndEndPage('Updating team scores failed.');
				}
			}
			mysql_free_result($result);
			
			// we're done
			// TODO: show score differences
			echo '<pre>';
			print_r($team_stats_changes);
			echo '</pre>';
			echo '<p>All team scores were updated sucessfully.</p>' . "\n";
			// unlock all tables so site will still work
			unlock_tables($site, $connection);
			$site->dieAndEndPage('');
		} else
		{
			// just enter the new match..not that much to do
			// the check regarding changed table checksum prevents from forgetting to recompute match scores of other teams
			
			$diff = 0;
			$team1_new_score = 1200;
			$team2_new_score = 1200;
			
			// get score of first team
			
			// if we enter a new match and it is at the first position
			// we can just use the data from team overview as shortcut
			$query = 'SELECT `score` FROM `teams_overview` WHERE `teamid`=' . "'" . sqlSafeString($team_id1) . "'";
			if (!($result = @$site->execute_query($site->db_used_name(), 'teams_overview', $query, $connection)))
			{
				$site->dieAndEndPage('Could not get score of team with id ' . sqlSafeString($team_id1) . ', requested by user with id ' . sqlSafeString($viewerid) . '.');
			}
			
			while($row = mysql_fetch_array($result))
			{
				$team1_new_score = $row['score'];
			}
			mysql_free_result($result);
		}
		
		$query = 'SELECT `score` FROM `teams_overview` WHERE `teamid`=' . "'" . sqlSafeString($team_id2) . "'";
		if (!($result = @$site->execute_query($site->db_used_name(), 'teams_overview', $query, $connection)))
		{
			$site->dieAndEndPage('Could not get score of team with id ' . sqlSafeString($team_id2)
								 . ', requested by user with id ' . sqlSafeString($viewerid) . '.');
		}
		
		while($row = mysql_fetch_array($result))
		{
			$team2_new_score = $row['score'];
		}
		mysql_free_result($result);
		
		$team_stats_changes = array();
		$team_stats_changes[$team_id1]['old_score'] = $team1_new_score;
		$team_stats_changes[$team_id2]['old_score'] = $team2_new_score;
		compute_scores($team_id1, $team_id2, $team1_new_score,$team2_new_score, $team1_points, $team2_points, $diff, $team_stats_changes);
		
		if (isset($_GET['enter'])  && ($confirmed > 1))
		{
			// only one match to be entered
			$query = 'INSERT INTO `matches` (`timestamp`, `team1_teamid`, `team2_teamid`, `team1_points`, `team2_points`, `team1_new_score`, `team2_new_score`)';
			$query .= ' VALUES (' . "'" . sqlSafeString($match_day . ' ' . $match_time) . "'" . ', ' . "'" . sqlSafeString($team_id1) . "'" .', ';
			$query .= "'" . sqlSafeString($team_id2) . "'" . ', ' . "'" . sqlSafeString($team1_points) . "'" . ', ' . "'" . sqlSafeString($team2_points) . "'";
			$query .= ', ' . "'" . sqlSafeString($team1_new_score) . "'" . ', ' . "'" . sqlSafeString($team2_new_score) . "'";
			$query .= ')';
			if (!($result = @$site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
			{
				$site->dieAndEndPage('The match reported by user with id ' . sqlSafeString($viewerid)
									 . ' could not be reported due to a sql problem!');
			}
			
			// match was entered successfully, now update the score in teams overview
			$query = 'UPDATE `teams_overview` SET `score`=' . "'" . sqlSafeString($team1_new_score) . "'";
			$query .= ', deleted=' . "'" . sqlSafeString('1') . "'";
			$query .= ' WHERE `teamid`=' . "'" . sqlSafeString($team_id1). "'";
			if (!($result = @$site->execute_query($site->db_used_name(), 'teams_overview', $query, $connection)))
			{
				$site->dieAndEndPage('The match reported by user with id ' . sqlSafeString($viewerid)
									 . ' was entered but the team score of team with id ' . sqlSafeString($team_id1). ' could not be updated!');
			}
			$query = 'UPDATE `teams_overview` SET `score`=' . "'" . sqlSafeString($team2_new_score) . "'";
			$query .= ', deleted=' . "'" . sqlSafeString('1') . "'";
			$query .= ' WHERE `teamid`=' . "'" . sqlSafeString($team_id2). "'";
			if (!($result = @$site->execute_query($site->db_used_name(), 'teams_overview', $query, $connection)))
			{
				$site->dieAndEndPage('The match reported by user with id ' . sqlSafeString($viewerid)
									 . ' was entered but the team score of team with id ' . sqlSafeString($team_id2). ' could not be updated!');
			}
			
			require_once('update_match_stats.php');
			update_match_stats_entered($team_id1, $team_id2, $team1_points, $team2_points, $site, $connection);
			
			// done with altering match table
			echo '<p>The match was entered successfully.</p>' . "\n";
			
			// display some summary to the user about new scores team names and diff
			// TODO: list the stuff mentioned in the comment above
			
			// log the changes
			$team_stats_changes[$team_id1]['new_score'] = $team1_new_score;
			$team_stats_changes[$team_id2]['new_score'] = $team2_new_score;
			
			// difference of score between before and after changing match list is always positive (absolute value)
			// &plusmn; displays a +- symbol
			echo 'diff is &plusmn; ' . htmlentities($diff);
			print_r($team_stats_changes);
			
			
			// do maintenance after a match has been entered
			// a check inside the maintenance logic will make sure it will be only performed one time per day at max
			require_once('../CMS/maintenance/index.php');
		}
		$site->dieAndEndPage('');
	}
	
	if ($confirmed === 1)
	{
		// show preview
		
		// checked if there are newer matches already entered
		$query = 'SELECT * FROM `matches` WHERE `timestamp`>' . "'" . sqlSafeString($_POST['match_day']) . ' ' . sqlSafeString($_POST['match_time']) . "'";
		if (!($result = @$site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
		{
			echo '<a class="button" href="./">overview</a>' . "\n";
			$site->dieAndEndPage('Unfortunately there seems to be a database problem and thus comparing timestamps of matches failed.');
		}
		
		// in case there are no newer matches the number of rows in query result is 0
		$rows = (int) mysql_num_rows($result);
		
		// only show a confirmation question, the case is not too unusual and 
		if ($rows > 0)
		{
			echo '<p>There are already newer matches in the database. Do you want to enter the match?<p/>';
		}
		
		// TODO: Display summary again
		
		echo '<form enctype="application/x-www-form-urlencoded" method="post" action="?';
		if (isset($_GET['enter']))
		{
			echo 'enter">';
		}
		if (isset($_GET['edit']))
		{
			echo 'edit=' . urlencode($match_id) . '">';
		}
		if (isset($_GET['delete']))
		{
			echo 'delete=' . urlencode($match_id) . '">';
		}
		echo "\n";
		
		echo '<div><input type="hidden" name="confirmed" value="2"></div>' . "\n";
		
		$new_randomkey_name = $randomkey_name . microtime();
		$new_randomkey = $site->set_key($new_randomkey_name);
		echo '<div><input type="hidden" name="key_name" value="' . htmlspecialchars($new_randomkey_name) . '"></div>' . "\n";
		echo '<div><input type="hidden" name="' . htmlspecialchars($randomkey_name) . '" value="';
		echo urlencode(($_SESSION[$new_randomkey_name])) . '"></div>' . "\n";
		echo '<input type="hidden" name="match_day" value="';
		if (isset($_POST['match_day']))
		{
			echo htmlspecialchars($_POST['match_day']);
		}
		echo '">' . "\n";
		
		echo '<input type="hidden" name="match_time" value="';
		if (isset($_POST['match_time']))
		{
			echo htmlspecialchars($_POST['match_time']);
		}
		echo '">' . "\n";
		
		echo '<input type="hidden" name="team_id1" value="';
		if (isset($_POST['team_id1']))
		{
			echo htmlspecialchars((int) $_POST['team_id1']);
		}
		echo '">' . "\n";
		
		echo '<input type="hidden" name="team_id2" value="';
		if (isset($_POST['team_id2']))
		{
			echo htmlspecialchars((int) $_POST['team_id2']);
		}
		echo '">' . "\n";
		
		echo '<input type="hidden" name="team1_points" value="';
		if (isset($_POST['team1_points']))
		{
			echo htmlspecialchars($_POST['team1_points']);
		}
		echo '">' . "\n";
		
		echo '<input type="hidden" name="team2_points" value="';
		if (isset($_POST['team2_points']))
		{
			echo htmlspecialchars($_POST['team2_points']);
		}
		echo '">' . "\n";
		
		if (isset($_GET['enter']))
		{
			echo '<div><input type="submit" name="match_enter_confirmed" value="Confirm to enter the new match" id="send"></div>' . "\n";
		}
		if (isset($_GET['edit']))
		{
			echo '<div><input type="submit" name="match_edit_confirmed" value="Confirm to edit the match" id="send"></div>' . "\n";
		}		
		echo '</form>' . "\n";		
		
		$site->dieAndEndPage('');
	}
	
	if (isset($_GET['edit']) || isset($_GET['delete']))
	{
		// retrieve the informations about the matches that are to be edited or to be deleted
		$query = 'SELECT * FROM `matches` WHERE `id`=' . "'" . sqlSafeString($match_id) . "'" . ' LIMIT 1';
		if (!($result = @$site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
		{
			$site->dieAndEndPage('The information about the matches in question could not be retrieved because of an SQL/database connectivity problem.');
		}
		
		while($row = mysql_fetch_array($result))
		{
			$team1_points = (int) $row['team1_points'];
			$team2_points = (int) $row['team2_points'];
			
			$team1_teamid = (int) $row['team1_teamid'];
			$team2_teamid = (int) $row['team2_teamid'];
			
			// compute both time and day from timestamp data
			$timestamp = $row['timestamp'];
			$offset = strpos($timestamp, ' ');
			$match_day = substr($timestamp, 0, $offset);
			$match_time = substr($timestamp, ($offset+1));
		}
	}
	if (isset($_GET['enter']))
	{
		if (isset($_POST['team1_points']))
		{
			$team1_points = (int) $_POST['team1_points'];
		}
		if (isset($_POST['team2_points']))
		{
			$team2_points = (int) $_POST['team2_points'];
		}
		
		if (isset($_POST['team_id1']))
		{
			$team1_teamid = (int) $_POST['team_id1'];
		}
		if (isset($_POST['team_id2']))
		{
			$team2_teamid = (int) $_POST['team_id2'];
		}
	}
	
	if (isset($_GET['enter']) || isset($_GET['edit']) || isset($_GET['delete']))
	{
		if ($confirmed === 0)
		{
			// display editing form
			echo '<form enctype="application/x-www-form-urlencoded" method="post" action="?';
			if (isset($_GET['enter']))
			{
				echo 'enter">';
			}
			if (isset($_GET['edit']))
			{
				echo 'edit=' . urlencode($match_id) . '">';
			}
			if (isset($_GET['delete']))
			{
				echo 'delete=' . urlencode($match_id) . '">';
			}
			echo "\n";
			
			echo '<div><input type="hidden" name="confirmed" value="1"></div>' . "\n";
			
			$query = 'SELECT `teams`.`id`,`teams`.`name` FROM `teams`,`teams_overview`';
			$query .= ' WHERE (`teams_overview`.`deleted`<>' . "'" . sqlSafeString('2') . "'" . ')';
			$query .= ' AND `teams`.`id`=`teams_overview`.`teamid`';
			
			if (!($result = @$site->execute_query($site->db_used_name(), 'teams', $query, $connection)))
			{
				// query was bad, error message was already given in $site->execute_query(...)
				$site->dieAndEndPage('');
			}
			
			$rows = (int) mysql_num_rows($result);
			
			// only show a confirmation question, the case is not too unusual and 
			if ($rows < 1)
			{
				echo '<p class="first_p">There are no teams in the database. A valid match requires at least 2 teams<p/>';
				$site->dieAndEndPage('');
			}
			
			if ($rows < 2)
			{
				echo '<p class="first_p">There is only 1 team in the database. A valid match requires at least 2 teams<p/>';
				$site->dieAndEndPage('');
			}			
			
			$team_name_list = Array();
			$team_id_list = Array();
			while($row = mysql_fetch_array($result))
			{
				$team_name_list[] = $row['name'];
				$team_id_list[] = $row['id'];
			}
			mysql_free_result($result);
			
			$list_team_id_and_name = Array();
			
			$list_team_id_and_name[] = $team_id_list;
			$list_team_id_and_name[] = $team_name_list;
			
			$n = ((int) count($team_id_list)) - 1;
			
			echo '<p><label for="visits_team_id1">First team: </label>' . "\n";
			echo '<span><select id="visits_team_id1" name="team_id1';
			if (isset($_GET['delete']))
			{
				echo '" disabled="disabled';
			}
			echo '">' . "\n";
			
			$n = ((int) count($team_id_list)) - 1;
			for ($i = 0; $i <= $n; $i++)
			{
				echo '<option value="';
				// no htmlentities because team id 0 is reserved
				echo $list_team_id_and_name[0][$i];
				if (isset($team1_teamid) && ((int) $list_team_id_and_name[0][$i] === $team1_teamid))
				{
					echo '" selected="selected';
				}
				echo '">' . $list_team_id_and_name[1][$i];
				echo '</option>' . "\n";
			}
			
			echo '</select></span>' . "\n";
			echo '<label for="match_points_team1">Points: </label>' . "\n";
			echo '<span><input type="text" id="match_points_team1" name="team1_points"';
			if (isset($team1_points))
			{
				echo ' value="' . htmlentities($team1_points) . '"';
			}
			if (isset($_GET['delete']))
			{
				echo ' readonly="readonly"';
			}
			echo '></span></p>' . "\n\n";
			
			echo '<p><label for="visits_team_id2">Second team: </label>' . "\n";
			echo '<span><select id="visits_team_id2" name="team_id2';
			if (isset($_GET['delete']))
			{
				echo '" disabled="disabled';
			}
			
			echo '">' . "\n";
			
			$n = ((int) count($team_id_list)) - 1;
			for ($i = 0; $i <= $n; $i++)
			{
				echo '<option value="';
				// no htmlentities because team id 0 is reserved
				echo $list_team_id_and_name[0][$i];
				if (isset($team2_teamid) && ((int) $list_team_id_and_name[0][$i] === $team2_teamid))
				{
					echo '" selected="selected';
				}
				echo '">' . $list_team_id_and_name[1][$i];
				echo '</option>' . "\n";
			}
			echo '</select></span>' . "\n";
			
			echo '<label for="match_points_team2">Points: </label>' . "\n";
			echo '<span><input type="text" id="match_points_team2" name="team2_points"';
			if (isset($team2_points))
			{
				echo ' value="' . htmlentities($team2_points) . '"';
			}
			if (isset($_GET['delete']))
			{
				echo ' readonly="readonly"';
			}			
			echo '></span></p>' . "\n\n";
			
			echo '<p><label for="match_day">Day: </label>' . "\n";
			echo '<span><input type="text" id="match_day" name="match_day" value="';
			if (isset($match_day))
			{
				echo htmlentities($match_day);
			} else
			{
				if (isset($_POST['match_day']))
				{
					echo htmlentities($_POST['match_day']);
				} else
				{
					echo date('Y-m-d');
				}
			}
			if (isset($_GET['delete']))
			{
				echo '" readonly="readonly';
			}
			echo '"></span></p>' . "\n\n";
			
			echo '<p><label for="match_time">Time: </label>' . "\n";
			echo '<span><input type="text" id="match_time" name="match_time" value="';
			if (isset($match_time))
			{
				echo htmlentities($match_time);
			} else
			{
				if (isset($_POST['match_time']))
				{
					echo htmlentities($_POST['match_time']);
				} else
				{
					echo date('H:i:s');
				}
			}
			if (isset($_GET['delete']))
			{
				echo '" readonly="readonly';
			}
			echo '"></span></p>' . "\n\n";
			if (isset($_GET['enter']))
			{
				echo '<div><input type="submit" name="match_enter_unconfirmed" value="Enter the new match" id="send"></div>' . "\n";
			}
			if (isset($_GET['edit']))
			{
				echo '<div><input type="submit" name="match_edit_unconfirmed" value="Edit the match" id="send"></div>' . "\n";
			}
			if (isset($_GET['delete']))
			{
				// the preview step is skipped when deleting a match because at this point the preview was already shown
				// thus create a key to prevent automated match deletion now
				$new_randomkey_name = $randomkey_name . microtime();
				$new_randomkey = $site->set_key($new_randomkey_name);
				echo '<div><input type="hidden" name="key_name" value="' . htmlspecialchars($new_randomkey_name) . '"></div>' . "\n";
				echo '<div><input type="hidden" name="' . htmlspecialchars($randomkey_name) . '" value="';
				echo urlencode(($_SESSION[$new_randomkey_name])) . '"></div>' . "\n";				
				echo '<div><input type="submit" name="match_delete_confirmed" value="Delete the match" id="send"></div>' . "\n";
			}
			
			
			echo '</form>' . "\n";
			
			
			// done with the first step form
			$site->dieAndEndPage('');
		}
	}
?>