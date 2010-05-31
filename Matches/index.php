<?php
	ini_set ('session.use_trans_sid', 0);
	ini_set ('session.name', 'SID');
	ini_set('session.gc_maxlifetime', '7200');
	session_start();
	?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta content="text/html; charset=UTF-8" http-equiv="content-type">
<?php
	include('../stylesheet.inc');
	
	$pfad = (pathinfo(realpath('./')));
	$name = $pfad['basename'];
	print '	 <title>' . $name . '</title>' . "\n";
?>
</head>
<body>
<?php
	require ('../CMS/navi.inc');
	
	$site = new siteinfo();
	$connection = $site->connect_to_db();
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

	
	function get_table_checksum($site, $connection)
	{
		$query = 'CHECKSUM TABLE `matches`';
		
		if (!($result = @$site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
		{
			// a severe problem with the table exists
			$site->dieAndEndPage('Checksum of the matches could not be generated');
		}
		
		$checksum = '';
		while($row = mysql_fetch_array($result))
		{
			$checksum = $row['Checksum'];
		}
		
		return $checksum;
	}
	
	function setTableUnchanged($site, $connection)
	{
		$_SESSION['checksum_matches'] = get_table_checksum($site, $connection);
	}
		
	function team_name_from_id($site, $connection, $id)
	{
		$query = 'SELECT `name` FROM `teams` WHERE id=';
		$query .= "'" . sqlSafeString((int) $id) . "'";
		// only one team name as result
		$query .= ' LIMIT 1';
		
		// execute query
		if (!($result_team_name = @$site->execute_silent_query($site->db_used_name(), 'matches', $query, $connection)))
		{
			$site->dieAndEndPage('The name of matching team with id ' . htmlentities($row['team1_teamid']) . ' could not be displayed because of an SQL/database connectivity problem.');
		}
		
		// no rows in team name query result is not valid
		if ((int) mysql_num_rows($result_team_name) === 0)
		{
			echo '<span style="font-weight: bold">No team name for id ' . htmlentities($row['team1_teamid']) . '!</span>' . "\n";
			setTableUnchanged($site, $connection);
			echo '</td>' . "\n" . '<td></td>' . "\n" . '</tr>' . "\n";
			$site->dieAndEndPage('');
		}
		while($row_team_name = mysql_fetch_array($result_team_name))
		{
			echo htmlentities($row_team_name['name']);
		}
		mysql_free_result($result_team_name);		
	}
	
	if (isset($_GET['enter']) || isset($_GET['edit']) || isset($_GET['delete']))
	{
		include_once('match_list_changing_logic.php');
		// all the operations requested have been dealt with
		$site->dieAndEndPage('');
	}
		
	// TODO: use more permissions
	if ($allow_add_match)
	{
		echo '<a class="button" href="./?enter">Enter a new match</a>' . "\n";
	}
	
	// TODO: make the limit dynamic so users can browse the entire list of matches
	$query = 'SELECT * FROM `matches` ORDER BY `timestamp` ';
	// newest matches first please
	$query .= 'DESC ';
	// limit the output to the requested rows to speed up displaying
	$query .= 'LIMIT ';
	// the "LIMIT 0,200" part of query means only the first 200 entries are received
	// the range of shown matches is set by the GET variable i
	$view_range = (int) 0;
	if (isset($_GET['i']))
	{
		if (((int) $_GET['i']) > 0)
		{
			$view_range = (int) $_GET['i'];
			$query .=  $view_range . ',';
		} else
		{
			// force write 0 for value 0 (speed)
			// and 0 for negative values (security: DBMS error handling prevention)
			$query .= '0,';
		}
	} else
	{
		// no special value set -> write 0 for value 0 (speed)
		$query .= '0,';
	}
	$query .= ((int) $view_range)+201;
	
	if (!($result = @$site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
	{
		$site->dieAndEndPage('The list of matches could not be displayed because of an SQL/database connectivity problem.');
	}
	
	$rows = (int) mysql_num_rows($result);
	$show_next_matches_button = false;
	if ($rows > 200)
	{
		$show_next_messages_button = true;
	}
	if ($rows === (int) 0)
	{
		echo '<p>No matches have been played yet.</p>' . "\n";
		setTableUnchanged($site, $connection);
		$site->dieAndEndPage('');
	}
	unset($rows);
	
	echo '<table class="table_matches_played">' . "\n";
	echo '<caption>Matches played</caption>' . "\n";
	echo '<tr>' . "\n";
	echo '	<th>Time</th>' . "\n";
	echo '	<th>Teams</th>' . "\n";
	echo '	<th>Result</th>' . "\n";
	// show edit/delete links in a new table column if user has permission to use these
	// adding matches is not done from within the table
	if ($allow_edit_match || $allow_delete_match)
	{
		echo '	<th>Allowed actions</th>' . "\n";
	}
	echo '</tr>' . "\n\n";
	// display message overview
	$matchid_list = Array (Array ());
	// read each entry, row by row
	$current_match_row = 0;
	while($row = mysql_fetch_array($result))
	{
		$matchid_list[$current_match_row]['timestamp'] = $row['timestamp'];
		$matchid_list[$current_match_row]['team1_teamid'] = $row['team1_teamid'];
		$matchid_list[$current_match_row]['team2_teamid'] = $row['team2_teamid'];
		$matchid_list[$current_match_row]['team1_points'] = $row['team1_points'];
		$matchid_list[$current_match_row]['team2_points'] = $row['team2_points'];
		$matchid_list[$current_match_row]['id'] = $row['id'];
		$matchid_list[$current_match_row]['team2_points'] = $row['team2_points'];

		$current_match_row++;
	}
	unset($current_match_row);
	// query result no longer needed
	mysql_free_result($result);
	
	// are more than 200 rows in the result?
	if ($show_next_matches_button)
	{
		// only show 200 messages, not 201
		// NOTE: array_pop would not work on a resource (e.g. $result)
		array_pop($matchid_list);
	}
	
	// walk through the array values
	foreach($matchid_list as $match_entry)
	{
		echo '<tr class="matches_overview">' . "\n";
		echo '<td>';
		echo htmlentities($match_entry['timestamp']);
		echo '</td>' . "\n" . '<td>';
		
		// get name of first team
		team_name_from_id($site, $connection, $match_entry['team1_teamid']);
		
		// seperator showing that opponent team will be named soon
		echo ' versus ';
		
		// get name of second team
		team_name_from_id($site, $connection, $match_entry['team2_teamid']);
		
		// done with the table field, go to next field
		echo '</td>' . "\n" . '<td>';
		
		echo htmlentities($match_entry['team1_points']);
		echo ' - ';
		echo htmlentities($match_entry['team2_points']);
		echo '</td>' . "\n";
		
		// show allowed actions based on permissions
		if ($allow_edit_match || $allow_delete_match)
		{
			echo '<td>';
			if ($allow_edit_match)
			{
				echo '<a class="button" href="./?edit=' . htmlspecialchars($match_entry['id']) . '">Edit match result</a>';
			}
			if ($allow_edit_match && $allow_delete_match)
			{
				echo ' ';
			}
			if ($allow_edit_match)
			{
				echo '<a class="button" href="./?delete=' . htmlspecialchars(urlencode($match_entry['id'])) . '">Delete match</a>';
			}
			echo '</td>' . "\n";
		}
		
		
		echo '</tr>' . "\n\n";
	}
	unset($matchid_list);
	unset($match_entry);
	
	// no more matches to display
	echo '</table>' . "\n";
	
	// look up if next and previous buttons are needed to look at all messages in overview
	if ($show_next_matches_button || ($view_range !== (int) 0))
	{
		// browse previous and next entries, if possible
		echo "\n" . '<p>'  . "\n";
		
		if ($view_range !== (int) 0)
		{
			echo '	<a href="./?i=';
			echo ((int) $view_range)-200;
			echo '">Previous matches</a>' . "\n";
		}
		if ($show_next_matches_button)
		{
			
			echo '	<a href="./?i=';
			echo ((int) $view_range)+200;
			echo '">Next matches</a>' . "\n";
		}
		echo '</p>' . "\n";
	}	
	
	if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'])
	{
		setTableUnchanged($site, $connection);
	}
?>
</div>
</body>
</html>