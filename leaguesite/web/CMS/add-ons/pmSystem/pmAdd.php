<?php
	class pmSystemAddPM extends pmSystemPM
	{
		private $editor;
		private $pm;
		public $randomKeyName = 'pmSystemAddPM';
		
		function __construct()
		{
			global $user;
			global $tmpl;
			
			
			$this->pm = new pm();
			
			// FIXME: fallback to default permission name until add-on system is completly implemented
			$entry_add_permission = 'allow_add_messages';
			
			if (!$user->getPermission($entry_add_permission))
			{
				// no permissions to write a new private message
				$tmpl->display('NoPerm');
				die();
			}
			
			
			include(dirname(dirname(dirname(__FILE__))) . '/classes/editor.php');
			$this->editor = new editor($this);
			$this->editor->addFormatButtons('content');
			$this->editor->edit();
			
			$tmpl->assign('title', 'New PM');
			$tmpl->display('PMAdd');
		}
		
		function insertEditText($readonly=false)
		{
			global $tmpl;
			global $config;
			global $db;
			
			
			if ($readonly || isset($_POST['confirmationStep']))
			{
				// data passed to form -> use it
				
				$query = $db->prepare('SELECT `name` FROM `users` WHERE `id`=? LIMIT 1');
				$db->execute($query, user::getCurrentUserId());
				$author = $db->fetchRow($query);
				if ($author === false)
				{
					$author = 'error: no author could be determined';
				}
				$db->free($query);
			}
			
			// do not drop original message id that a reply would be refering to
			// but drop reply mode (users and teams are already added to recipients at this point)
			$formArgs = '';
			if (isset($_GET['id']))
			{
				$formArgs .= '&amp;id=' . $_GET['id'];
			}
			$tmpl->assign('formArgs', $formArgs);
			
			$tmpl->assign('subject', $this->pm->getSubject());
			$tmpl->assign('time', $this->pm->getTimestamp());
			$tmpl->assign('playerRecipients', $this->pm->getUserNames());
			$tmpl->assign('teamRecipients', $this->pm->getTeamNames());
			$tmpl->assign('rawContent', htmlent($this->pm->getContent()));
			
			switch($readonly)
			{
				case true:
					$tmpl->assign('authorName',  htmlent($author['name']));
					if ($config->getValue('bbcodeLibAvailable'))
					{
						$tmpl->assign('content',  $tmpl->encodeBBCode($this->pm->getContent()));
					} else
					{
						$tmpl->assign('content',  htmlent($this->pm->getContent()));
					}
					$tmpl->assign('showPreview', true);
					// overwrite editor's default text ('Write changes')
					$tmpl->assign('submitText', 'Send PM');
					break;
				
				default:
					$tmpl->assign('showSendForm', true);
					// display the formatting buttons addded by addFormatButtons
					$this->editor->showFormatButtons();
					break;
			}
		}
		
		function randomKeyMatch(&$confirmed)
		{
			global $site;
			
			
			$randomKeyValue = '';
			$randomKeyName = '';
			
			if (isset($_POST['key_name']))
			{
				$randomKeyName = htmlent_decode($_POST['key_name']);
				
				if (isset($_POST[$randomKeyName]))
				{
					$randomKeyValue = htmlent_decode($_POST[$randomKeyName]);
				}
			}
			
			return $randomkeysmatch = $site->validateKey($randomKeyName, $randomKeyValue);
		}
		
		function sanityCheck(&$confirmed)
		{
			global $config;
			global $tmpl;
			global $db;
			
			
			// < 0: undefined, 0: edit screen, 1: preview, 2: send, > 2: undefined
			if ($confirmed < 0 || $confirmed > 2)
			{
				// changed undefined values to a defined state
				$confirmed = 0;
			}
			
			
			if (isset($_GET['userid']) && intval($_GET['userid']) > 0)
			{
				$this->pm->addUserID($_GET['userid'], true);
			}


			if (isset($_GET['teamid']) && intval($_GET['teamid']) > 0)
			{
				$this->pm->addTeamID($_GET['teamid'], true);
			}


			if (isset($_GET['reply']) && isset($_GET['id']) && intval($_GET['id']) > 0)
			{
				// add all original recipients and author or only original author to default recipients
				
				// find out if original message was readable for user
				$query = $db->prepare('SELECT COUNT(*) FROM `pmsystem_msg_users` WHERE `msgid`=? AND `userid`=?');
				$db->execute($query, array($_GET['id'], user::getCurrentUserId()));
				$rows = $db->fetchRow($query);
				$db->free($query);
				
				// silently drop on no permisson issue
				// message to self may be listed twice, for inbox and outbox
				// TODO: output error
				if (count($rows) > 0 && $rows['COUNT(*)'] > 0)
				{
					$query = $db->prepare('SELECT `subject`, `message` FROM `pmsystem_msg_storage`'
										  . ' WHERE `id`=? LIMIT 1');
					$db->execute($query, $_GET['id']);
					$row = $db->fetchRow($query);
					$db->free($query);
					if (count($row) > 0)
					{
						$this->pm->setSubject($row['subject']);
						// quote old message
						$this->pm->setContent(rtrim('> ' . str_replace("\n","\n> ",
													  htmlent_decode($row['message'])), "\n") . "\n\n");
					}
					
					if (strcmp($_GET['reply'], 'all') === 0)
					{
						// add original author to recipients
						$origAuthorQuery = $db->prepare('SELECT `name` FROM `users`'
											  . ' WHERE `id`=(SELECT `author_id` FROM `pmsystem_msg_storage`'
											  . ' WHERE `id`=? LIMIT 1) LIMIT 1');
						$db->execute($origAuthorQuery, intval($_GET['id']));
						while ($row = $db->fetchRow($origAuthorQuery))
						{
							$this->pm->addUserName($row['name']);
						}
						$db->free($origAuthorQuery);
						
						// prepare further recipients queries
						$usersQuery = $db->prepare('SELECT `name`'
												   . ' FROM `pmsystem_msg_recipients_users` LEFT JOIN `users`'
												   . ' ON `pmsystem_msg_recipients_users`.`userid`=`users`.`id`'
												   . ' WHERE `msgid`=?');
						$teamsQuery = $db->prepare('SELECT `name`'
												   . ' FROM `pmsystem_msg_recipients_teams` LEFT JOIN `teams`'
												   . ' ON `pmsystem_msg_recipients_teams`.`teamid`=`teams`.`id`'
												   . ' WHERE `msgid`=?');
						
						// add users to recipients
						$db->execute($usersQuery, intval($_GET['id']));
						while ($row = $db->fetchRow($usersQuery))
						{
							$this->pm->addUserName($row['name']);
						}
						$db->free($usersQuery);
						
						// add teams to recipients
						$db->execute($teamsQuery, intval($_GET['id']));
						while ($row = $db->fetchRow($teamsQuery))
						{
							$this->pm->addTeamName($row['name']);
						}
						$db->free($teamsQuery);
					} elseif (strcmp($_GET['reply'], 'author') === 0)
					{
						// only 1 author, thus no loop
						$query = $db->prepare('SELECT `name` FROM `users`'
											  . ' WHERE `id`=(SELECT `author_id` FROM `pmsystem_msg_storage`'
											  . ' WHERE `id`=? LIMIT 1) LIMIT 1');
						$db->execute($query, intval($_GET['id']));
						$row = $db->fetchRow($query);
						$db->free($query);
						
						$this->pm->addUserName($row['name']);
					}
				}
			}
			
			
			if ($confirmed > 0 || isset($_POST['editPageAgain']))
			{
				// no need to check for a key match if no content was supplied
				if (!$this->randomKeyMatch($confirmed))
				{
					// editing cancelled due to random key mismatch
					$confirmed = 0;
					return 'nokeymatch';
				}
				
				
				if (isset($_POST['subject']) && (strlen(strval($_POST['subject'])) > 0))
				{
					$this->pm->setSubject($_POST['subject']);
				}
				
				if (isset($_POST['content']) && (strlen(strval($_POST['content'])) > 0))
				{
					$this->pm->setContent(strval($_POST['content']));
				}
				
				
				// add all set team recipients
				$i = 0;
				while (isset($_POST['teamRecipient' . $i]))
				{
					// user requested removal of a recipient -> do not send now
					if (isset($_POST['removeTeamRecipient' . $i]))
					{
						$confirmed = 0;
					}
					
					// exclude team recipients that are requested to be removed
					if (isset($_POST['teamRecipient' . $i]) && !(isset($_POST['removeTeamRecipient' . $i])))
					{
						$this->pm->addTeamName($_POST['teamRecipient' . $i]
											   , $confirmed > 0
											   && !isset($_POST['addTeamRecipient'])
											   && !isset($_POST['addPlayerRecipient'])
											   && !isset($_POST['editPageAgain']));
					}
					$i++;
				}
				
				// add new team recipient if requested explicitly or implicitly
				if (isset($_POST['teamRecipient']))
				{
					$this->pm->addTeamName($_POST['teamRecipient'], $confirmed > 0);
				}
				// do not send the message if adding team was explicitly requested
				if (isset($_POST['addTeamRecipient']))
				{
					$confirmed = 0;
				}
				
				
				// add all set player recipients
				$i = 0;
				while (isset($_POST['playerRecipient' . $i]))
				{
					// user requested removal of a recipient -> do not send now
					if (isset($_POST['removePlayerRecipient' . $i]))
					{
						$confirmed = 0;
					}
					
					// exclude player recipients that are requested to be removed
					if (isset($_POST['playerRecipient' . $i]) && !(isset($_POST['removePlayerRecipient' . $i])))
					{
						$this->pm->addUserName($_POST['playerRecipient' . $i]
											   , $confirmed > 0
											   && !isset($_POST['addPlayerRecipient'])
											   && !isset($_POST['editPageAgain']));
					}
					$i++;
				}
				
				// add new player recipient if requested explicitly or implicitly
				if (isset($_POST['playerRecipient']))
				{
					$this->pm->addUserName($_POST['playerRecipient'], $confirmed > 0);
				}
				// do not send the message if adding player was explicitly requested
				if (isset($_POST['addPlayerRecipient']))
				{
					$confirmed = 0;
				}
			}
			
			
			if ($confirmed > 0 && $this->pm->countUsers() < 1 && $this->pm->countTeams() < 1)
			{
				$tmpl->assign('MSG', 'A PM can not be sent without any recipients set.');
				$confirmed = 0;
			}
			
			
			// check for too long or too short message
			// first ask config on database 
			$dbCharset = $config->getValue('db.userInputFieldCharset');
			if ($dbCharset === true)
			{
				$tmpl->assign('MSG', 'FATAL ERROR: Config value db.userInputFieldCharset returned true.');
				$db->logError('FATAL ERROR: Config value db.userInputFieldCharset returned true. '
							  . 'Check if it is set in settings file. '
							  . 'It must return a string containing DB field charset for user input.');
				$confirmed = 0;
			}
			// fallback to UTF-8 if not set
			if ($dbCharset === false)
			{
				$dbCharset = 'UTF-8';
			}
			
			// do the actual message length check
			$len = mb_strlen($this->pm->getContent(), $dbCharset);
			if ($len < 1)
			{
				$tmpl->assign('MSG', 'A message must not be empty.');
				$confirmed = 0;
			}
			if ($len > 4000)
			{
				$tmpl->assign('MSG', 'A message must not be longer than 4000 characters.');
				$confirmed = 0;
			}
			
			
			return true;
		}
		
		
		private function successMessage()
		{
			global $tmpl;
			
			$tmpl->assign('showMsgSentSuccessfully', true);
			$tmpl->clearAssign('showSendForm');
		}
		
		
		function writeContent()
		{
			$result = false;
			if (isset($_GET['id']) && intval($_GET['id']) > 0)
			{
				// TODO: use further reaching validation than just intval
				$result = $this->pm->send(user::getCurrentUserId(), intval($_GET['id']));
			} else
			{
				$result = $this->pm->send(user::getCurrentUserId());
			}
			
			if ($result === true)
			{
				$this->successMessage();
			}
			
			return $result;
		}
	}
?>
