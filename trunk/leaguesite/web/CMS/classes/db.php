<?php
	// handle database related data
	class database
	{
		private $connection;
		
		// id > 0 means a user is logged in
		function getConnection()
		{
			return $this->connection;
		}
		
		function createConnection()
		{
			global $site;
			
			return $this->connection = mysql_pconnect('127.0.0.1', $site->mysqluser(), $site->mysqlpw());;
		}
		
		function selectDB($db)
		{
			mysql_select_db($db, $this->connection);
		}
	}
?>
