<?php
	class loginSystem
	{
		private $moduleOutput = '';
		
		public function __construct()
		{
			global $tmpl;
			global $user;
			
			
			// abort process if user already logged in
			if (user::getCurrentUserId() > 0)
			{
				$this->moduleOutput[] = ('You are already logged in. '
										 . 'If you want to login with a different account '
										 . 'you must first logout.');
				return;
			}
			
			// if no module chosen, display login text of all modules
			// NOTE: certain modules may suppress their login text, depending on circumstances
			if (isset($_GET['module']) === false)
			{
				$this->getLoginText();
				return;
			}
			
			// if requested module is unavailable print error msg
			// append module login text to provide a choice to continue
			if (($module = $this->getRequestedModule($_GET['module'])) === false)
			{
				$this->moduleOutput[] = 'An error occurred, module name not accepted.';
				$this->getLoginText($modules);
				return;
			}
			
			if (isset($_GET['action']) === false)
			{
				$this->moduleOutput[] = 'An error occurred, module action not specified.';
				return;
			}
			
			// activate module code based on user requested action
			include_once(dirname(__FILE__) . '/modules/' . $module . '/' . $module . '.php');
			$moduleInstance = new $module();
			
			switch($_GET['action'])
			{
				case 'form':
					$this->moduleOutput = $moduleInstance->showForm();
					break;
				case 'login':
					if ($moduleInstance->validateLogin($message))
					{
						$this->doLogin($moduleInstance, $module);
					}
					if (strlen($message) > 0)
					{
						$this->moduleOutput[] = $message;
					}
					break;
				default: 
					$this->moduleOutput = 'Unknown module action requested, request not accepted.';
			}
		}
		
		public function __destruct()
		{
			global $tmpl;
			
			
			$tmpl->assign('modules', $this->moduleOutput);
			$tmpl->display('loginSystem');
		}
		
		
		public static function getModules()
		{
			$modules = array();
			
			// first scan the files in the modules directory
			$modules = scandir(dirname(__FILE__) . '/modules/');
			foreach ($modules as $i => $curFile)
			{
				// remove entry from array if it's no folder
				if (!is_dir(dirname(__FILE__) . '/modules/' . $curFile))
				{
					unset($modules[$i]);
					continue;
				}
				
				// remove module if DEACTIVATED file is found in its root folder
				if (file_exists(dirname(__FILE__) . '/modules/' . $curFile . '/DEACTIVATED'))
				{
					unset($modules[$i]);
					continue;
				}
				
				// filter reserved directory names
				switch ($curFile)
				{
					case (strcasecmp('.', $curFile) === 0):
						unset($modules[$i]);
						break;
					
					case (strcasecmp('..', $curFile) === 0):
						unset($modules[$i]);
						break;
					
					case (strcasecmp('.svn', $curFile) === 0):
						unset($modules[$i]);
						break;
				}
			}
			
			return $modules;
		}		
		
		private function getLoginText()
		{
			$modules = $this->getModules();
			$n = count($modules);
			$firstLoginModuleText = true;
			
			for ($i = 1; $i <= $n; $i++)
			{
				if ($module = array_shift($modules))
				{
					include_once(dirname(__FILE__) . '/modules/' . $module . '/' . $module . '.php');
					$class = new $module();
					
					if ($firstLoginModuleText === false)
					{
						$this->moduleOutput[] = '<p><strong>or</strong></p>';
					}
					
					$this->moduleOutput[] = $class->showLoginText();
					
					$firstLoginModuleText = false;
				}
			}
		}
		
		
		private function getRequestedModule($moduleName)
		{
			// search user supplied module name for invalid data
			
			// return false if invalid data was found
			return (isset($moduleName)
					&& preg_match('/^[0-9A-Za-z]+$/', $moduleName)
					&& file_exists(dirname(__FILE__) . '/modules/' . $moduleName))
			? $moduleName : false;
		}
		
		
		private function doLogin($moduleInstance, $moduleName)
		{
			global $config;
			
			
			// if used login module is not local, then an external login has been used
			$externalLogin = strcasecmp($moduleName, 'local') !== 0;
			// init user id to reserved value 0
			$uid = 0;
			
			// load operations framework
			include(dirname(__FILE__) . '/classes/userOperations.php');
			$userOperations = new userOperations();
			
			
			if ($externalLogin)
			{
				// lookup internal id using external id
				$uid = \user::getIdByExternalId($moduleInstance->getID());
			} else
			{
				// local login id is equal to internal login id by definition
				$uid = $moduleInstance->getID();
			}
			
			
			// if uid is 0 this means
			// either new user
			// or user already registered using local login
			if ($uid === 0)
			{
				if ($externalLogin)
				{
					// try to recover uid by username based db lookup
					$uid_list = \user::getIdByName($moduleInstance->getName());
					
					// iterate through the list, trying to update old callsigns
					// and hoping to find the proper user account for this login attempt
					foreach ($uid_list as $one_uid)
					{
						// check external login id for match with external login module id
						// $moduleInstance->getID() must have a valid value if login got approved
						// by the external login module used
						$user = new \user($one_uid);
						$servicematch = false;
						foreach ($user->getExternalIds AS $eservice)
						{
							// only act on matching service type
							if ($eservice->service === $moduleInstance->getType)
							{
								$servicematch = true;
								
								if ($eservice->euid !== $moduleInstance->getID())
								{
									// try to resolve the name conflict by updating a username that might be forgotten
									$userOperations->updateUserName($one_uid, $eservice->euid,
																	$moduleInstance->getName());
								} else
								{
									$uid = $one_uid;
									break;
								}
							}
						}
						
						if (!$servicematch)
						{
							$uid = $one_uid;
						}
					}
					unset($servicematch);
					unset($eservice);
					unset($uid_list);
					unset($one_uid);
				}
				
				
				// init newUser to false (do not send welcome message by default)
				$newUser = false;
				
				// find out if an internal id can be found for callsign
				$newUser = ($uid !== 0) ? false: true;
				
				if ($newUser)
				{
					// a new user, be happy :)
					
					if ($config->getValue('login.welcome.summary'))
					{
						$this->moduleOutput[] = strval($config->getValue('login.welcome.summary'));
					} else
					{
						$this->moduleOutput[] = 'Welcome and thanks for registering on this website.';
					}
					
					// register the account on db
					if ($uid = $userOperations->registerAccount($moduleInstance, $externalLogin))
					{
						// send welcome message if registering was successful
						\pm::sendWelcomeMessage($uid);
					}
				} else
				{
					// existing account with no external login
					
					// call logout as bandaid for erroneous login modules
					$user->logout();
					$this->moduleOutput[] = ('This account does not have any external logins enabled. '
											 . 'You may try using '
											 . '<a href="./?module=local&amp;action=form">local login</a>'
											 . ' first.');
					
					// login failed without any possibility to recover from user error
					return false;
				}
				
				// does a user try to log in using reserved id 0?
				if ($uid === 0)
				{
					// call logout as bandaid for erroneous login modules
					// these may log the user in, even though they never should
					$user->logout();
					$this->moduleOutput[] = 'An internal error occurred: $uid === 0 on login.';
					return false;
				}
			}
			
			$user = new \user($uid);
			
			// re-activate deleted accounts
			// stop processing disabled/banned or broken accounts
			// call logout as bandaid for erroneous login modules
			$status = $user->getStatus();
			switch ($status)
			{
				case 'active': break;
				case 'deleted':	$user->setStatus('active'); break;
				case 'login disabled':
					$this->moduleOutput[] = 'Your account is disabled: No login possible.';
					$user->logout();
					return false;
					break;
					// TODO: implement site wide ban list
				case 'banned':
					$this->moduleOutput[] = 'You have been banned from this website.';
					$user->logout();
					return false;
					break;
				default:
					$this->moduleOutput[] = ('The impossible happened: Account status is'
											 . htmlent($status) . '.');
					$user->logout();
					return false;
			}
			
			if ($uid > 0)
			{
				// update username first because online user list uses the name directly instead of an id
				//hmm, uid := $moduleInstance->getID()
				$userOperations->updateUserName($uid,
												($externalLogin ? $moduleInstance->getID() : 0),
												$moduleInstance->getName());
				user::setCurrentUserID($uid);
				$moduleInstance->givePermissions();
				$userOperations->addToVisitsLog($uid);
				$user->setLastLogin();
				$user->update();
				$userOperations->addToOnlineUserList($moduleInstance->getName(), $uid);
				invitation::deleteOldInvitations();
				
				$this->moduleOutput[] = 'Login was successful!';
				return true;
			} else
			{
				$user->logout();
			}
			
			return false;
		}
		
		public function logFailedLoginAttempt($name, $service, $reason='unknown')
		{
			global $db;
			
			
			// a module called this function to log a failed login attempt
			// name is the name passed by user (not reliable information)
			// service is the module/service that failed
			// reason is the reported reason of an enum
			// check db structure about what reason to report as the reason is only passed through
			
			// do nothing as long the queries are not fully implemented
			return;
			$query = $db->prepare('INSERT INTO `users_rejected_logins` (`name`,`ip-address`,`forwarded_for`,`host`,`timestamp`,`reason`)'
								  . ' VALUES (:name, :ip, :forwarded, :host, :timestamp, :reason)');
			
			if (!$db->execute($query, array(':name'			=> $name,
											':ip'			=> $_SERVER['REMOTE_ADDR'],
											':forwarded'	=> getenv('HTTP_X_FORWARDED_FOR'),
											':host'			=> gethostbyaddr($_SERVER['REMOTE_ADDR']),
											':timestamp'	=> date('Y-m-d H:i:s'),
											':reason'		=> $reason)))
			{
				$output = ('Could not register invalid login of '. htmlent($name) .' in database. Please report this to an admin.');
				return;
			}
		}
	}
?>
