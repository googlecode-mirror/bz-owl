<?php
	ini_set ('session.use_trans_sid', 0);
	ini_set ('session.name', 'SID');
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
	
	$allow_any_match_action = false;
	if (isset($_SESSION['allow_any_match_action']))
	{
		if (($_SESSION['allow_any_match_action']) === true)
		{
			$allow_any_match_action = true;
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
	if ($allow_any_match_action)
	{
		echo '<a class="button" href="./?enter">Enter a new match</a>' . "\n";
	}
	
	// TODO: make the limit dynamic so users can browse the entire list of matches
	$query = 'SELECT * FROM `matches` ORDER BY `timestamp` DESC LIMIT 0,200';
	if (!($result = @$site->execute_query($site->db_used_name(), 'matches', $query, $connection)))
	{
		$site->dieAndEndPage('The list of matches could not be displayed because of an SQL/database connectivity problem.');
	}
	
	if ((int) mysql_num_rows($result) === 0)
	{
		echo '<p>No matches have been played yet.</p>' . "\n";
		setTableUnchanged($site, $connection);
		$site->dieAndEndPage('');
	}
	
	echo '<table class="table_matches_played">' . "\n";
	echo '<caption>Matches played</caption>' . "\n";
	echo '<tr>' . "\n";
	echo '	<th>Time</th>' . "\n";
	echo '	<th>Teams</th>' . "\n";
	echo '	<th>Result</th>' . "\n";
	if ($allow_any_match_action)
	{
		echo '<th>Allowed actions</th>' . "\n";
	}		
	echo '</tr>' . "\n\n";
	while($row = mysql_fetch_array($result))
	{
		echo '<tr class="matches_overview">' . "\n";
		echo '<td>';
		echo htmlentities($row['timestamp']);
		echo '</td>' . "\n" . '<td>';
		
		// get name of first team
		team_name_from_id($site, $connection, $row['team1_teamid']);
		
		// seperator showing that opponent team will be named soon
		echo ' versus ';
		
		// get name of second team
		team_name_from_id($site, $connection, $row['team2_teamid']);
		
		// done with the table field, go to next field
		echo '</td>' . "\n" . '<td>';
		
		echo htmlentities($row['team1_points']);
		echo ' - ';
		echo htmlentities($row['team2_points']);
		echo '</td>' . "\n";
		
		// TODO: use more permissions
		if ($allow_any_match_action)
		{
			echo '<td><a class="button" href="./?edit=' . htmlspecialchars($row['id']) . '">Edit match result</a> <a class="button" href="./?delete=' . htmlspecialchars(urlencode($row['id'])) . '">Delete match</a></td>' . "\n";
		}
		
		
		echo '</tr>' . "\n\n";
	}
	// query result no longer needed
	mysql_free_result($result);
	
	// no more matches to display
	echo '</table>' . "\n";
	
	if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'])
	{
		setTableUnchanged($site, $connection);
	}
?>
</div>
</body>
</html>