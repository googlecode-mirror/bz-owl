<?php
	// this is plain text!
	header('Content-Type: text/plain');
	
	require realpath('../CMS/siteinfo.php');
	
	$site = new siteinfo();
	
	$connection = $site->connect_to_db();
	
	// display teams
	$query = ('SELECT `teams`.`id`,`teams`.`name` FROM `teams`,`teams_overview`'
			  . ' WHERE `teams_overview`.`teamid`=`teams`.`id` AND `teams_overview`.`deleted`<'
			  . sqlSafeStringQuotes(2));
	if (!($result = @$site->execute_silent_query($site->db_used_name(), 'teams,teams_overview', $query, $connection)))
	{
		$site->dieAndEndPage('It seems like the team profile can not be accessed for an unknown reason.');
	}
	while ($row = mysql_fetch_array($result))
	{
		echo 'TE: ' . $row['id'] . ', ' . $row['name'] . "\n";
	}
	mysql_free_result($result);
	
	$query = ('SELECT `id`,`teamid`,`name` FROM `players`'
			  . ' WHERE `players`.`suspended`<'
			  . sqlSafeStringQuotes(1));
	if (!($result = @$site->execute_silent_query($site->db_used_name(), 'players', $query, $connection)))
	{
		$site->dieAndEndPage('It seems like the player profile can not be accessed for an unknown reason.');
	}
	while ($row = mysql_fetch_array($result))
	{
		echo 'PL: ' . $row['teamid'] . ', ' . $row['id' ]. ', ' . $row['name'] . "\n";
	}
	mysql_free_result($result);
	
	// done with outputting stats
?>