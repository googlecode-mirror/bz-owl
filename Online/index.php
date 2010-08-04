<?php
	ini_set ('session.use_trans_sid', 0);
	ini_set ('session.name', 'SID');
	ini_set('session.gc_maxlifetime', '7200');
	@session_start();
	$path = (pathinfo(realpath('./')));
	$name = $path['basename'];
	
	$display_page_title = $name;
	require_once (dirname(dirname(__FILE__)) . '/CMS/index.inc');
	require '../CMS/navi.inc';
	
	if (!isset($site))
	{
		$site = new siteinfo();
	}
	
	echo '<div class="static_page_box">' . "\n";
	
	$connection = $site->connect_to_db();
	// choose database
	mysql_select_db($site->db_used_name(), $connection);
	$table_name = 'online_users';
	
	// find out if table exists
	$query = 'SHOW TABLES LIKE ' . sqlSafeStringQuotes($table_name);
	$result = mysql_query($query, $connection);
	$rows = mysql_num_rows($result);
	// done
	mysql_free_result($result);
	
	if ($rows < 1)
	{
		echo '<p class="first_p">Table does not exist. Attempting to create table.<p>';
		
		// query will be
		//CREATE TABLE `online_users` (
		//							 `id` int(11) NOT NULL auto_increment,
		//							 `bzid` int(11) NOT NULL,
		//							 `username` varchar(50) NOT NULL,
		//							 `last_activity` timestamp NOT NULL default '0000-00-00 00:00:00',
		//							 PRIMARY KEY  (`id`)
		//							 ) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8
		$query = 'CREATE TABLE `' . $table_name . '` (';
		$query = $query . '`id` int(11) NOT NULL auto_increment,' . "\n";
		$query = $query . '`bzid` int(11) NOT NULL,' . "\n";
		$query = $query . '`username` varchar(50) NOT NULL,' . "\n";
		$query = $query . '`last_activity` timestamp NOT NULL default ' . "'" . '0000-00-00 00:00:00' . "'" . ',' . "\n";
		$query = $query . 'PRIMARY KEY  (`id`)' . "\n";
		$query = $query . ') ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8' . "\n";
		if (!(@$site->execute_query($site->db_used_name(), $table_name, $query, $connection)))
		{
			echo '<p>Creation of table failed.</p>';
			die("\n</div>\n</body>\n</html>");
		}
	}
	
	$onlineUsers = false;
	$query = 'SELECT * FROM `' . sqlSafeString($table_name) . '`';	
	if ($result = (@$site->execute_query($site->db_used_name(), $table_name, $query, $connection)))
	{
		$onlineUsers = true;
	} else
	{
		$onlineUsers = false;
		mysql_free_result($result);
	}
	
	// use the resulting data
	if ($result)
	{
		$rows = mysql_num_rows($result);
		// by definition this is a joke but online guests are not shown by default
		if ($rows < 1)
		{
			echo '<div class="online_user">No logged in user at the moment</div>';
		} else
		{
			
			date_default_timezone_set($site->used_timezone());
			// convert $result resource to array
			$users = Array();
			while($row = mysql_fetch_array($result))
			{
				$users[] = Array( 'playerid' => $row['playerid'], 'username' => $row['username'], 'last_activity' => $row['last_activity']);
			}
			
			// output the contents of array
			foreach ($users as $v1)
			{
				echo '<div class="online_user"><a href="../Players/' . '?profile=' . ((int) htmlentities($v1['playerid'])) .'">';
				echo htmlentities($v1['username']) . '</a>';
//				if (phpversion() >= ('5.3'))
//				{
				$datetime1 = new DateTime($v1['last_activity']);
				$datetime2 = new DateTime(date('Y-m-d H:i:s'));
				
				$diff = $datetime1->diff($datetime2);
				$diff = $diff->format('%Y-%m-%d %H:%i:%s');
				$cmp_diff = explode(' ', $diff);
				$cmp_diff = explode(':', $cmp_diff[1]);
				if ((intval($cmp_diff[0]) > 0)
					|| (intval($cmp_diff[1]) > 0)
					|| (intval($cmp_diff[2]) > 0))
				{
					echo ' ' . $cmp_diff[0] . ':' . $cmp_diff[1] . ':' . $cmp_diff[2] . ' idle ';
				}
				
//				}
				echo ' (last access at ' . htmlentities($v1['last_activity']) . ')</div>';
			}
		}
		mysql_free_result($result);
	}
	mysql_close($connection);
?>

</div>
</div>
</body>
</html>