<?php
	class userOperations extends database
	{
		public function activateAccount($id)
		{
			$query = $this->prepare('UPDATE `players` SET `status`=:status WHERE `id`=:id');
			$this->execute($query, array(':status' => array('active', PDO::PARAM_STR, 6)));
			$this->free($query);
		}
		
		public function addToVisitsLog($id)
		{
			// insert login of user to visits log
			$ip_address = getenv('REMOTE_ADDR');
			$host = gethostbyaddr($ip_address);
			$query = ('INSERT INTO `visits` (`playerid`,`ip-address`,`host`,`forwarded_for`,`timestamp`) VALUES'
					  . ' (?, ?, ?, ?, ?)');
			$query = $this->prepare($query);
			$args = array($id, htmlent($ip_address), htmlent($host)
						  // try to detect original ip-address in case proxies are used
						  , htmlent(getenv('HTTP_X_FORWARDED_FOR')), date('Y-m-d H:i:s'));
			$this->execute($query, $args);
		}
		
		
		public function findIDByExternalLogin($id)
		{
			// internal id is an integer of length 11 by definition
			$internalID = 0;
			
			$query = $this->prepare('SELECT `id` FROM `players` WHERE `external_id`=:id LIMIT 1');
			// the id can be of any data type and of any length
			// just assume it's a 50 characters long string
			$this->execute($query, array(':id' => array($id, PDO::PARAM_STR, 50)));
			
			if ($row = $this->fetchRow($query))
			{
				$internalID = intval($row['id']);
			}
			$this->free($query);
			
			return $internalID;
		}
		
		
		public function findIDByName($name)
		{
			// internal id is an integer of length 11 by definition
			$internalID = 0;
			
			$query = $this->prepare('SELECT `id` FROM `players` WHERE `name`=:name LIMIT 1');
			// the id can be of any data type and of any length
			// just assume it's a 50 characters long string
			$this->execute($query, array(':name' => array($name, PDO::PARAM_STR, 50)));
			
			if ($row = $this->fetchRow($query))
			{
				$internalID = intval($row['id']);
			}
			$this->free($query);
			
			return $internalID;
		}
		
		
		public function getAccountStatus($id)
		{
			$query = $this->prepare('SELECT `status` FROM `players` WHERE `id`=:uid LIMIT 1');
			$this->execute($query, array(':uid' => array($id, PDO::PARAM_INT)));
			
			// init status to "login disabled" to block login on error
			$status = 'login disableds';
			
			if ($row = $this->fetchRow($query))
			{
				$status = $row['status'];
			}
			$this->free($query);
			
			return $status;
		}
		
		
		public function updateLastLogin($id)
		{
			$query = $this->prepare('UPDATE `players_profile` SET `last_login`=:lastLogin'
									. ' WHERE `playerid`=:uid LIMIT 1');
			$this->execute($query, array(':lastLogin' => array(date('Y-m-d H:i:s'), PDO::PARAM_STR),
										 ':uid' => array($id, PDO::PARAM_INT)));
			
			if ($row = $this->fetchRow($query))
			{
				$status = $row['status'];
			}
			$this->free($query);
			
			return;
		}
		
		
		public function addToOnlineUserList($name, $id)
		{
			// find out if table exists
			$query = $this->SQL('SHOW TABLES LIKE ' . "'" . 'online_users' . "'");
			$numRows = count($this->fetchRow($query));
			$this->free($query);
			
			$onlineUsers = false;
			if ($numRows > 0)
			{
				// no need to create table in case it does not exist
				// any interested viewer looking at the online page will create it
				$onlineUsers = true;
			}
			
			// use the resulting data
			if ($onlineUsers)
			{
				$query = $this->prepare('SELECT * FROM `online_users` WHERE `userid`=?');
				$this->execute($query, $id);
				$rows = $this->rowCount($query);
				// done
				$this->free($query);
				
				$onlineUsers = false;
				if ($rows > 0)
				{
					// already logged in
					// so log him out
					$query = $this->prepare('DELETE FROM `online_users` WHERE `userid`=?');
					if (!$this->execute($query, $id))
					{
						$this->logError('Could not remove already logged in user from online user table (userid=' . strval($id) . ').');
					}
				}
				
				// insert logged in user into online_users table
				$query = $this->prepare('INSERT INTO `online_users` (`userid`, `username`, `last_activity`) Values (?, ?, ?)');
				$date = new DateTime('now', new DateTimeZone('UTC'));
				$curDate = $date->format('Y-m-d H:i:s');
				$args = array($id, $name, $curDate);
				$this->execute($query, $args);
			}
		}
		
		
		public function registerAccount($moduleInstance, $externalLogin=false)
		{
			$uid = false;
			
			// lock tables before registering
			$this->SQL('LOCK TABLES `players` WRITE');
			$this->SQL('SET AUTOCOMMIT = 0');
			
			if ($externalLogin)
			{
				$query = $this->prepare('INSERT INTO `players` (`name`, `external_id`) VALUES (?,?)');
				$this->execute($query, array($moduleInstance->getName(), $moduleInstance->getID()));
			} else
			{
				$query = $this->prepare('INSERT INTO `players` (`name`) VALUES (?)');
				$this->execute($query, $moduleInstance->getName());
			}
			
			// retrieve last insertion id database independently
			// by asking for 1 id, beginning with last one
			$query = $this->SQL('SELECT `id` FROM `players` ORDER BY `id` DESC LIMIT 1');
			$row = $this->fetchRow($query);
			$uid = $row['id'];
			$this->free($query);
			
			// unlock tables after registering
			$this->SQL('UNLOCK TABLES');
			$this->SQL('COMMIT');
			$this->SQL('SET AUTOCOMMIT = 1');
			
			// create empty profile connected to new user id
			$query = $this->prepare('INSERT INTO `players_profile` (`playerid`) VALUES (?)');
			$this->execute($query, $uid);
			
			return $uid;
		}
		
		
		public function sendWelcomeMessage($id)
		{
			global $config;
			
			
			$subject = ($config->getValue('login.welcome.subject') ?
						$config->getValue('login.welcome.subject') :
						'Welcome');
			$content = ($config->getValue('login.welcome.content') ?
						$config->getValue('login.welcome.content') :
						'Welcome and thanks for registering at this website!' . "\n"
									. 'In the FAQ you can find the most important informations'
									. ' about organising and playing matches.' . "\n\n"
									. 'See you on the battlefield.');
			// prepare welcome message
			include(dirname(dirname(dirname(__FILE__))) . '/pmSystem/classes/PMComposer.php');
			$pmComposer = new pmComposer();
			$pmComposer->setSubject($subject);
			$pmComposer->setContent($content);
			$pmComposer->setTimestamp(date('Y-m-d H:i:s'));
			$pmComposer->addUserID($id);
			
			// send it
			$pmComposer->send();
		}
	}
?>
