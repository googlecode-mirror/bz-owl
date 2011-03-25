<?php
	class pmSystemAddPM extends pmSystemPM
	{
		private $editor;
		private $PMComposer;
		public $randomKeyName = 'pmSystemAddPM';
		
		function __construct()
		{
			global $user;
			global $tmpl;
			
			
			$this->PMComposer = new PMComposer();
			
			// FIXME: fallback to default permission name until add-on system is completly implemented
			$entry_add_permission = 'allow_add_news';
			
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
			global $page_title;
			global $author;
			global $last_modified;
			global $config;
			global $user;
			global $db;
			
			
			if ($readonly || isset($_POST['confirmationStep']))
			{
				// data passed to form -> use it
				
				// FIXME: check if POST variables are set
				$content = array();
				$content['raw_msg'] = $_POST['content'];
				
				$query = $db->prepare('SELECT `name` FROM `players` WHERE `id`=? LIMIT 1');
				$db->execute($query, $user->getID());
				$content['author'] = $db->fetchRow($query);
				$db->free($query);
				
				$content['recipientPlayers'] = $this->PMComposer->getRecipientNames();
				$content['subject'] = $_POST['subject'];
				$content['timestamp'] = date('Y-m-d H:i:s');
			} else
			{
				// new message -> no content yet
				$content['recipientPlayers'] = array();
				$content['subject'] = 'Enter subject here';
				$content['raw_msg'] = '';
			}
			
			
			switch($readonly)
			{
				case true:
					$tmpl->assign('subject',  htmlent($content['subject']));
					$tmpl->assign('authorName',  htmlent($content['author']['name']));
					$tmpl->assign('time',  htmlent($content['timestamp']));
					$tmpl->assign('rawContent', htmlent($content['raw_msg']));
					if ($config->value('bbcodeLibAvailable'))
					{
						$tmpl->assign('content',  $tmpl->encodeBBCode($content['raw_msg']));
					} else
					{
						$tmpl->assign('content',  htmlent($content['raw_msg']));
					}
					break;
				
				default:
					$tmpl->assign('recipientPlayers', $content['recipientPlayers']);
					$tmpl->assign('rawContent', htmlspecialchars($content['raw_msg']
																 , ENT_COMPAT, 'UTF-8'));
					$tmpl->assign('subject', htmlspecialchars($content['subject']
															   , ENT_COMPAT, 'UTF-8'));
					// display the formatting buttons addded by addFormatButtons
					$this->editor->showFormatButtons();
					break;
			}
		}
		
		function readContent($edit=false)
		{
			global $tmpl;
			global $user;
			global $db;
			
			
			// initialise return variable so any returned value will be always in a defined state
			$content = '';
			$offset = 0;
			
			if (!$edit)
			{
				// TODO: id only needed if user can edit or delete
				// TODO: meaning room for optimisation
				$query = $db->SQL('SELECT `id`,`title`,`timestamp`,`author`,`msg`'
								  . ' FROM `news` ORDER BY `timestamp` DESC'
								  . ' LIMIT ' . intval($offset) .', 21');
			} else
			{
				$query = $db->SQL('SELECT `title`,`timestamp`,`author`,`raw_msg`'
								  . ' FROM `news` ORDER BY `timestamp` DESC'
								  . ' LIMIT ' . intval($offset) .', 1');
			}
			$db->execute($query, $offset);
			$rows = $db->fetchAll($query);
			$db->free($query);
			
			if ($edit)
			{
				// let the edit insertion function pass it to the editor class
				return $rows[0];
			}
			
			// process query result array
			$n = count($rows);
			if ($n > 0)
			{
				// article box
				for($i = 1; $i < $n; $i++)
				{
					$content[$i]['id'] = $rows[$i]['id'];
					$content[$i]['title'] = (strcmp($rows[$i]['title'], '') === 0)?
									   'News' : $rows[$i]['title'];
					
					$content[$i]['author'] = $rows[$i]['author'];
					$content[$i]['time'] = $rows[$i]['timestamp'];
					
					$edit ? $content[$i]['content'] = $rows[$i]['raw_msg']
						  : $content[$i]['content'] = $rows[$i]['msg'];
					$author = $rows[$i]['author'];
				}
			}
			
			$tmpl->assign('entries', $content);
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
			global $user;
			global $tmpl;
			
			
			// no need to check for a key match if no content was supplied
			if (($confirmed > 0) && !$this->randomKeyMatch($confirmed))
			{
				// editing cancelled due to random key mismatch
				$confirmed = 0;
				return 'nokeymatch';
			}
			
			// do not send message if recipient add or remove was requested
			if (($confirmed > 0) && isset($_POST['recipientPlayer']) && isset($_POST['addPlayerRecipient']))
			{
				$this->PMComposer->addRecipientName($_POST['recipientPlayer']);
				$confirmed = 0;
			}
			
			return true;
		}
	}
	
	class PMComposer
	{
		private $recipients = array();
		
		function getRecipientNames()
		{
			return $this->recipients;
		}
		
		function addRecipientName($recipientName)
		{
			// FIXME: Sanity checks go here
			$this->recipients[] = $recipientName;
		}
	}
?>
