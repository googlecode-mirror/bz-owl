<?php
	ini_set ('session.use_trans_sid', 0);
	ini_set ('session.name', 'SID');
	ini_set('session.gc_maxlifetime', '7200');
	@session_start();
	
	$display_page_title = 'Visits log';
	require_once (dirname(dirname(__FILE__)) . '/CMS/index.inc');
	require realpath('../CMS/navi.inc');
	
	$site = new siteinfo();
	
	$connection = $site->connect_to_db();
	$randomkey_name = 'randomkey_user';
	$viewerid = (int) getUserID();
	
	$allow_view_user_visits = false;
	if (isset($_SESSION['allow_view_user_visits']))
	{
		if (($_SESSION['allow_view_user_visits']) === true)
		{
			$allow_view_user_visits = true;
		}
	}
	
	// in any case you need to be logged in to view the visits log
	if ($viewerid === 0)
	{
		echo '<p class="first_p">You need to login in order to view the visits log!</p>';
		$site->dieAndEndPageTable('');
	}
	
	// only allow looking when having the permission
	if ($allow_view_user_visits === false)
	{
		$site->dieAndEndPageTable('You (id=' . sqlSafeString($viewerid) . 'have no permissions to view the visits log!');
	}
	
	// form letting search for ip-address or host
	// this form is considered not to be dangerous, thus no key checking at all and also using the get method
	echo "\n" . '<form enctype="application/x-www-form-urlencoded" method="get" action="./">' . "\n";
	
	// input string
	echo '<div style="display:inline"><label for="visit_search_string">Search for:</label> ' . "\n";
	echo '<span><input type="text" id="visit_search_string" name="search_string"';
	if (isset($_GET['search']))
	{
		echo ' value="' . $_GET['search_string'] . '"';	
	}
	echo '>';
	echo '</span></div> ' . "\n";
	
	// looking for either ip-address or host?
	echo '<div style="display:inline"><label for="visit_search_type">result type:</label> ' . "\n";
	echo '<span><select id="visit_search_type" name="search_type">';
	
	
	// avoid to let the user enter a custom table column at all costs
	// only let them switch between ip-address and host search
	
	// search for ip-address by default
	$search_type = 'ip-address';
	$search_ip = true;
	$search_host = false;
	$search_name = false;
	
	if (isset($_GET['search_type']))
	{
		// not searching for ip-address
		if (!(strcmp('ip-address', $search_type) === 0))
		{
			$search_ip = false;
			$search_host = true;
			// not searching for ip-address
			if (!(strcmp('host', $search_type) === 0))
			{
				$search_host = false;
				$search_name = true;
			}
		}
	}
	
	echo '<option';
	if ($search_ip)
	{
		$search_type = 'ip-address';
		echo ' selected="selected"';
	}
	echo '>ip-address</option>';
	
	echo '<option';
	if ($search_host)
	{
		$search_type = 'host';
		echo ' selected="selected"';
	}
	echo '>host</option>';
	
	echo '<option';
	if ($search_name)
	{
		$search_type = 'name';
		echo ' selected="selected"';
	}
	echo '>name</option>';
	
	echo '</select></span>';
	
	echo ' <label for="visit_search_result_amount">Entries:</label> ';
	echo '<span><select id="visit_search_result_amount" name="search_result_amount">';
	echo '<option>200</option>';
	echo '<option>400</option>';
	echo '<option>800</option>';
	echo '<option>1600</option>';
	echo '<option>3200</option>';
	echo '</select></span>';
	echo '</div> ' . "\n";
	
	echo '<div style="display:inline"> <input type="submit" name="search" value="Search" id="send"></div>' . "\n";
	echo '</form>' . "\n";
	
	// search for either ip-address or host
	if (isset($_GET['search']))
	{
		echo '<a class="button" href="./">overview</a>' . "\n";
		
		// search for nothing by default
		$search_expression = '';
		if (isset($_GET['search_string']))
		{
			$search_expression = $_GET['search_string'];
		}
		// people like to use * as wildcard
		$search_expression = str_replace('*', '%', $search_expression);
		
		// get list of last 200 visits
		$query = 'SELECT `visits`.`playerid`,`players`.`name`,`visits`.`ip-address`,`visits`.`host`,`visits`.`timestamp` FROM `visits`,`players` ';
		$query .= 'WHERE `visits`.`playerid`=`players`.`id`';
		
		if (!($search_name))
		{
			$query .= ' AND `visits`.`' . sqlSafeString($search_type);
		} else
		{
			$query .= ' AND `players`.`' . sqlSafeString($search_type);
		}
		
		if ($search_name)
		{
			$query .= '` LIKE ' . sqlSafeStringQuotes($search_expression);
		} else
		{
			$query .= '` LIKE ' . "'" . sqlSafeString($search_expression) . '%' . "'";
		}
		
		// how many resulting rows does the user wish?
		// assume 200 by default
		$num_results = 200;
		if (isset($_GET['search_result_amount']))
		{
			if ($_GET['search_result_amount'] > 0)
			{
				// cast result to int to avoid SQL injections
				$num_results = (int) $_GET['search_result_amount'];
			}
		}
		$query .= ' ORDER BY `visits`.`id` DESC LIMIT 0,' . sqlSafeString($num_results);
		
		if (!($result = @$site->execute_query($site->db_used_name(), 'visits, players', $query, $connection)))
		{
			// query was bad, error message was already given in $site->execute_query(...)
			$site->dieAndEndPageTable('');
		}
		
		// sadly while searching the no results case should be handled
		if ((int) mysql_num_rows($result) < 1)
		{
			mysql_free_result($result);
			echo '<p>There were no matches for that expression in the visits log.</p>';
			$site->dieAndEndPageTable('');
		}
		
		echo "\n" . '<table id="table_team_members" class="big">' . "\n";
		echo '<caption>Search related visits log entries</caption>' . "\n";
		echo '<tr>' . "\n";
		echo '	<th>Name</th>' . "\n";
		echo '	<th>ip-address</th>' . "\n";
		echo '	<th>host</th>' . "\n";
		echo '	<th>login time</th>' . "\n";
		echo '</tr>' . "\n\n";
		
		// print out each entry
		while($row = mysql_fetch_array($result))
		{
			echo '<tr>' . "\n";
			echo '	<td><a href="./?profile=' . htmlspecialchars($row['playerid']) . '">';
			echo $row['name'];
			echo '</a></td>' . "\n";
			echo '	<td>' . htmlentities($row['ip-address']) . '</td>' . "\n";
			echo '	<td>' . htmlentities($row['host']) . '</td>' . "\n";
			echo '	<td>' . htmlentities($row['timestamp']) . '</td>' . "\n";
			echo '</tr>' . "\n";
		}
		mysql_free_result($result);
		echo '</table>' . "\n";
		
		// done with the search
		$site->dieAndEndPageTable('');
	}
	
	if (isset($_GET['profile']))
	{
		$profile = $_GET['profile'];
		
		// need an overview button to enable navigation within the page
		echo '<a class="button" href="./">overview</a>' . "\n";
		
		if ($profile < 0)
		{
			echo '<p>You tried to view the visits log of a not existing user!</p>';
			$site->dieAndEndPageTable('');
		}
		
		if ($profile === 0)
		{
			echo '<p>The user id 0 is reserved for not logged in players and thus no user with that id could ever exist.</p>' . "\n";
			$site->dieAndEndPageTable('');
		}
		
		$query = 'SELECT `name` FROM `players` WHERE `players`.`id`=' . sqlSafeStringQuotes($profile) . ' LIMIT 1';
		if (!($result = @$site->execute_query($site->db_used_name(), 'players', $query, $connection)))
		{
			$site->dieAndEndPageTable('<p>It seems like the name of player with id ' . sqlSafeStringQuotes(htmlent($profile)) . ' can not be accessed for an unknown reason.</p>');
		}
		
		// existance test of user skipped intentionally
		// if the user does not exist, there will be no visits for him
		
		// sanity checks passed
		
		// get the name of the player in question
		$player_name = '(no player name)';
		while ($row = mysql_fetch_array($result))
		{
			$player_name = $row['name'];
		}
		mysql_free_result($result);
		
		// collect visits list of that player
		// example query: SELECT `ip-address`, `host` FROM `visits` WHERE `playerid`='16'
		$query = 'SELECT `ip-address`, `host`, `timestamp` FROM `visits` WHERE `playerid`=' . "'" . sqlSafeString($profile) . "'";
		// only get first 200 entries by default
		$query .= ' ORDER BY `id` DESC ';
		$query .= ' LIMIT 0,200';
		if (!($result = @$site->execute_query($site->db_used_name(), 'visits', $query, $connection)))
		{
			// query was bad, error message was already given in $site->execute_query(...)
			$site->dieAndEndPageTable('');
		}
		
		if ((int) mysql_num_rows($result) < 1)
		{
			$site->dieAndEndPageTable('There are no visits by this user (id=' . sqlSafeString(htmlent($profile)) . '). Make sure the user is not deleted.');
		}
		
		// format the output with a nice table
		echo "\n" . '<table id="table_team_members" class="big">' . "\n";
		echo '<caption>Visits log of player ' . $player_name . '</caption>' . "\n";
		echo '<tr>' . "\n";
		echo '	<th>Name</th>' . "\n";
		echo '	<th>ip-address</th>' . "\n";
		echo '	<th>host</th>' . "\n";
		echo '	<th>login time</th>' . "\n";
		echo '</tr>' . "\n\n";
		
		// print out each entry
		while($row = mysql_fetch_array($result))
		{
			echo '<tr>' . "\n";
			echo '	<td> ' . $player_name . '</td>' . "\n";
			echo '	<td>' . htmlentities($row['ip-address']) . '</td>' . "\n";
			echo '	<td>' . htmlentities($row['host']) . '</td>' . "\n";
			echo '	<td>' . htmlentities($row['timestamp']) . '</td>' . "\n";
			echo '</tr>' . "\n";
		}
		mysql_free_result($result);
		echo '</table>' . "\n";
		
		// done with the player profile
		$site->dieAndEndPageTable('');
	}
	
	// display visits log overview
		
	// get list of last 200 visits
	$query = 'SELECT `visits`.`playerid`,`players`.`name`,`visits`.`ip-address`,`visits`.`host`,`visits`.`timestamp` FROM `visits`,`players`';
	$query .= ' WHERE `visits`.`playerid`=`players`.`id` ORDER BY `visits`.`id` DESC  LIMIT 0,200';
	if (!($result = @$site->execute_query($site->db_used_name(), 'visits, players', $query, $connection)))
	{
		// query was bad, error message was already given in $site->execute_query(...)
		$site->dieAndEndPageTable('');
	}
	
	// for performance reasons the case with no visits will be skipped
	// in that case the table would have no entries
	// however as someone needs to login there should be always at least one ip-address in the table
	// except the table got deleted after someone logged in and the user then looks at the visits log
	// format the output with a nice table
	echo "\n" . '<table id="table_team_members" class="big">' . "\n";
	echo '<caption>Visits log of all players</caption>' . "\n";
	echo '<tr>' . "\n";
	echo '	<th>Name</th>' . "\n";
	echo '	<th>ip-address</th>' . "\n";
	echo '	<th>host</th>' . "\n";
	echo '	<th>login time</th>' . "\n";
	echo '</tr>' . "\n\n";
	
	// print out each entry
	while($row = mysql_fetch_array($result))
	{
		echo '<tr>' . "\n";
		echo '	<td><a href="./?profile=' . htmlspecialchars($row['playerid']) . '">';
		echo $row['name'];
		echo '</a></td>' . "\n";
		echo '	<td>' . htmlentities($row['ip-address']) . '</td>' . "\n";
		echo '	<td>' . htmlentities($row['host']) . '</td>' . "\n";
		echo '	<td>' . htmlentities($row['timestamp']) . '</td>' . "\n";
		echo '</tr>' . "\n";
	}
	mysql_free_result($result);
	echo '</table>' . "\n";
?>
</div>
</body>
</html>
