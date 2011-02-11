<?php
	// handle database related data and functions
	class database
	{
		private $connection;
		private $pdo;
		
		// id > 0 means a user is logged in
		function getConnection()
		{
			return $this->connection;
		}
		
		function createConnection()
		{
			global $site;
			global $config;
			
			return $this->pdo = new PDO(
								  'mysql:host='. $config->value('dbHost') . ';dbname=' . $config->value('dbName'),
								  $config->value('dbUser'),
								  $config->value('dbPw'),
								  array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
			
//			return $this->connection = mysql_pconnect($config->value('dbHost')
//													  , $config->value('dbUser')
//													  , $config->value('dbPw'));
		}
		
		function logError($error)
		{
			// TODO: implement it
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
		
		function quoteArrayValues(array $ar)
		{
			foreach($ar as &$oneValue)
			{
				$oneValue = "'" . $oneValue . "'";
			}
			
			return $ar;
		}
		
		function selectDB($db, $connection=false)
		{
			if (isset($this->pdo))
			{
				$this->SQL('USE `' . $db . '`');
			} else
			{
				// choose database
				if (!(mysql_select_db($db, $this->connection)))
				{
					die('<p>Could not select database!<p>');
				}
			}
			return true;
		}
		
		function SQL($query, $file=false, $errorUserMSG='')
		{
			global $tmpl;
			
			if ($this->getDebugSQL() && isset($tmpl))
			{
				$tmpl->addMSG('executing query: '. $query);
			}
			
			$result = $this->pdo->query($query);
//			$result = mysql_query($query, $this->connection);
			
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
					$this->logError($file, $query . $this->escape(mysql_error()));
				} else
				{
					$this->logError($query . $this->escape(mysql_error()));
				}
				
				if (strlen($errorUserMSG) > 0)
				{
					$tmpl->done($errorUserMSG);
				}
				
				$tmpl->done('Error: Could not process query.');
			}
			
			return $result;
		}
		
		
		function execute($query, $inputParameters)
		{
			if (!is_array($inputParameters))
			{
				$inputParameters = array ($inputParameters);
			}
			$result = $query->execute($inputParameters);
			
			return $result;
		}
		
		function prepare($query)
		{
			return $this->pdo->prepare($query);;
		}
		
		function fetchNextRow($queryResult)
		{
			$queryResult->errorInfo();
			return $queryResult->fetch();
		}
		
		function fetchAll($queryResult)
		{
			return $queryResult->fetchAll();
		}
		
		function rowCount($queryResult)
		{
			return $queryResult->rowCount();
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
