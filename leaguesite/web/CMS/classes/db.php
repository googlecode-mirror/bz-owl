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
			global $config;
			
			return $this->connection = mysql_pconnect($config->value('dbHost')
													  , $config->value('dbUser')
													  , $config->value('dbPw'));
		}
	
		function getDebugSQL()
		{
			global $config;
			
			if (isset($_SESSION['debugSQL']))
			{
				return ($_SESSION['debugSQL']);
			} else
			{
				return $config->value('debugSQL');
			}
		}
		
		function selectDB($db, $connection=false)
		{
			// choose database
			if (!(mysql_select_db($db, $this->connection)))
			{
				die('<p>Could not select database!<p>');
			}
			return true;
		}
		
		function SQL($query, $file=false, $errorUserMSG='')
		{
			global $tmpl;
			
			$result = mysql_query($query, $this->connection);
			
			if (!$result)
			{
				// print out the raw error in debug mode
				if ($this->getDebugSQL())
				{
					echo'<p>Query ' . htmlent($query) . ' is probably not valid SQL.</p>' . "\n";
					echo mysql_error();
				}
				
				// log the error
				if ($file !== false)
				{
					$this->logError($file, $query . sqlSafeStringQuotes(mysql_error()));
				} else
				{
					$this->logError($query . sqlSafeStringQuotes(mysql_error()));
				}
				
				if (strlen($errorUserMSG) > 0)
				{
					$tmpl->done($errorUserMSG);
				}
				
				$tmpl->done('Error: Could not process query.');
			}
			
			return $result;
		}
	}
	
	
	// misc class
	class db_import
	{
		function db_import_name()
		{
			return database_to_be_imported();
		}
		function old_website()
		{
			return old_website_name();
		}
	}
?>
