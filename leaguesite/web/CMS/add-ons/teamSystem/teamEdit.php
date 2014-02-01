<?php
	class teamEdit
	{
		private $team;
		
		public function __construct($teamid)
		{
			global $tmpl;
			global $user;
			
			if (!$tmpl->setTemplate('teamSystemEditTeam'))
			{
				$tmpl->noTemplateFound();
				die();
			}
			$tmpl->assign('title', 'Edit team');
			
			$this->team = new team($teamid);
			
			
			$tmpl->assign('teamid', $teamid);
			$tmpl->assign('teamName', $this->team->getName());
			
			$editPermission = $user->getPermission('allow_edit_any_team_profile') || 
							  $this->team->getPermission('edit', user::getCurrentUserId());
			
			$tmpl->assign('canEditTeam', $editPermission);
			
			// user has no permission to edit team
			// do not proceed with request
			if (!$editPermission)
			{
				return;
			}
			
			$tmpl->assign('leaderId', $this->team->getLeaderId());
			
			$userids = $this->team->getUserIds();
			$members = array();
			foreach($userids AS $userid)
			{
				$members[] = array('id' => $userid,
								   'name' => (new user($userid))->getName());
			}
			
			$tmpl->assign('members', $members);
			
			if (!isset($_POST['confirmed']) || (string) $_POST['confirmed'] === '0')
			{
				$this->showForm();
			}
			if (isset($_POST['confirmed']) && (string) $_POST['confirmed'] === '1')
			{
				// try to update team
				// show editing form on error
				if (($validation = $this->sanityCheck()) !== true && $this->updateTeam() !== true)
				{
					if ($validation !== true)
					{
						$tmpl->assign('form_error', $validation);
					}
					$this->showForm();
				} else
				{
					$tmpl->assign('teamEditSuccessful', true);
				}
			}
		}
		
		
		// check if user submitted data is valid
		// returns true on valid data, error message as string or false otherwise
		protected function sanityCheck()
		{
			global $site;
			
			
			// require random access key name
			if (!isset($_POST['key_name']))
			{
				return 'Submitted validation key name invalid.';
			}
			
			// require random access key value
			if (!isset($_POST[$_POST['key_name']]))
			{
				return 'Submitted validation key content invalid.';
			}
			
			// sanity check key
			if (!$site->validateKey($_POST['key_name'], $_POST[$_POST['key_name']]))
			{
				return false;
			}
			
			// validate team name
			$teamName = false;
			if (isset($_POST['team_name']))
			{
				$teamName = (string) $_POST['team_name'];
				// check for leading and trailing whitespace
				if (strlen(trim($teamName)) !== strlen($teamName))
				{
					return 'Check your team name, no leading or trailing whitespace allowed.';
				}
				
				
				// 3 characters min team name size
				if (strlen($teamName) < 3)
				{
					return 'Check your team name, at least 3 characters required.';
				}
				
				// 30 characters max team name size
				if (strlen($teamName) > 30)
				{
					return 'Check your team name, maximum length is 30 characters.';
				}
				
				$this->team->setName($teamName);
			}
			
			// validate team leader
			if (isset($_POST['team_leader']))
			{
				$leaderid = (int) $_POST['team_leader'];
				
				if ($leaderid === 0)
				{
					return 'Check your team leader, a team must have a leader.';
				}
				
				// build a user instance and ask if user (wanted leader) is member of team
				$user = new user($leaderid);
				if ($user->getMemberOfTeam($this->team->getID()) !== true)
				{
					return 'Check your team leader, a team leader must be member of the team.';
				}
			}
			
			// validate open team
			if (isset($_POST['team_open']))
			{
				if ($_POST['team_open'] === '0')
				{
					$this->team->setOpen(false);
				} else
				{
					$this->team->setOpen(true);
				}
			}
			
			// validate team description
			if (isset($_POST['team_description']))
			{
				if (strlen(trim($_POST['team_description'])) > 0)
				{
					$this->team->setDescription((string) $_POST['team_description']);
					$this->team->setRawDescription((string) $_POST['team_description']);
				}
			}
			
			return true;
		}
		
		protected function showForm()
		{
			global $site;
			global $tmpl;
			
			
			// protected against cross site injection attempts
			$randomKeyName = 'teamEdit_' . $this->team->getID() . '_' . microtime();
			// convert some special chars to underscores
			$randomKeyName = strtr($randomKeyName, array(' ' => '_', '.' => '_'));
			$randomkeyValue = $site->setKey($randomKeyName);
			$tmpl->assign('keyName', $randomKeyName);
			$tmpl->assign('keyValue', htmlent($randomkeyValue));
			
			// bbcode editor
			include_once(dirname(dirname(dirname(__FILE__))) . '/bbcode_buttons.php');
			$bbcode = new bbcode_buttons();
			// set up name of field to edit so javascript knows which element to manipulate
			$tmpl->assign('buttonsToFormat', $bbcode->showBBCodeButtons('team_description'));
			unset($bbcode);
			
			$tmpl->assign('teamDescription', $this->team->getRawDescription());
			$tmpl->assign('avatarURI', $this->team->getAvatarURI());
		}
		
		// does sanity checks on input values
		// returns true on success, error message as string on error
		protected function updateTeam()
		{
			global $tmpl;
			
			
			// update team using new data
			$result = $this->team->update();
			if ($result !== true)
			{
				return $result;
			}
			
			return true;
		}
	}
?>
