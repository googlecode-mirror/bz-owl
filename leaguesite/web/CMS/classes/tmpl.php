<?php
	
	class template
	{
		private $tpl;
		private $title = '';
		private $msg = array();
		
		function addMSG($messageToAdd)
		{
			$this->msg[] = $messageToAdd;
		}
		
		function findTemplate(&$template, &$path)
		{
			// split the path in $template into an array
			// overwrite $template with the last part of the array
			// forget the last part of the array
			// append the pieces of the array to $path
			$pathinfo = explode('/', $template);
			
			if (count($pathinfo) > 0)
			{
				$template = $pathinfo[count($pathinfo) -1];
				unset($pathinfo[count($pathinfo) -1]);
				
				$path .= '/' . htmlspecialchars(implode('/', $pathinfo));
			}
		}
		
		function setTitle($title)
		{
			// set the title of the page
			if (is_string($title))
			{
				$this->title = $title;
			}
		}
		
		function setTemplate($template, $customTheme='')
		{
			global $db;
			global $site;
			global $config;
			global $user;
			global $connection;
			
			if (strcmp($customTheme, '') === 0)
			{
				$customTheme = $user->getStyle();
			}
			
			// extract possible file paths out of $template and append it to $themeFolder
			$themeFolder = dirname(dirname(dirname(__FILE__))) .'/styles/' . htmlspecialchars($customTheme);
			$this->findTemplate($template, $themeFolder);
			
			// init template system
			$this->tpl = new HTML_Template_IT($themeFolder);
			
			// load the current template file
			if (strcmp($template, '') === 0)
			{
				$template = $site->basename();
			}
			
			$this->tpl->loadTemplatefile($template . '.tmpl.html', true, true);
			
			// debug output used template
			if ($db->getDebugSQL())
			{
				if (file_exists($themeFolder . $template . '.tmpl.html'))
				{
					$this->addMSG('Used template: ' . $themeFolder
								  . $template . '.tmpl.html');
					$this->addMSG($this->return_self_closing_tag('br'));
				} else
				{
					echo 'Used template: ' . $themeFolder . $template . '.tmpl.html';
				}
			}
			
			
			// use favicon, if available
			if (!(strcmp($config->value('favicon'), '') === 0))
			{
				$this->tpl->setCurrentBlock('FAVICON');
				$this->tpl->setVariable('FAVICONURL', $config->value('favicon'));
				$this->parseCurrentBlock();
			}
			
			
			
			$this->tpl->setCurrentBlock('MAIN');
			
			// links should point to current locations
			$this->tpl->setVariable('BASEURL', $config->value('baseaddress'));
			
			// point to currently used theme
			if (strcmp($customTheme, '') === 0)
			{
				$this->tpl->setVariable('CUR_THEME', str_replace(' ', '%20', htmlspecialchars($site->getStyle())));
			} else
			{
				$this->tpl->setVariable('CUR_THEME', $customTheme);
			}
			
			// set the date and time
			date_default_timezone_set($config->value('timezone'));
			$this->tpl->setVariable('DATE', date('Y-m-d H:i:s T'));
			
			// count online players on match servers
			
			// run the update script:
			// >/dev/null pipes output to nowhere
			// & lets the script run in the background
			exec('php ' . dirname(__FILE__) . '/cli/servertracker_query_backend.php >/dev/null &');
			
			// build sum from list of servers
			$query = ('SELECT SUM(`cur_players_total`) AS `cur_players_total`'
					  . ' FROM `servertracker`'
					  . ' ORDER BY `id`');
			$result = $db->SQL($query, __FILE__);
			while ($row = $db->fetchRow($result))
			{
				if (intval($row['cur_players_total']) === 1)
				{
					$this->tpl->setVariable('ONLINE_PLAYERS', '1 player');
				} else
				{
					$this->tpl->setVariable('ONLINE_PLAYERS', (strval($row['cur_players_total']) . ' players'));
				}
			}
			
			// remove expired sessions from the list of online users
			$query = 'SELECT `playerid`, `last_activity` FROM `online_users`';
			$query = $db->SQL($query);
			$rows = $db->fetchAll($query);
			$n = count($rows);
			if ($n > 0)
			{
				for ($i = 0; $i < $n; $i++)
				{
					$saved_timestamp = $rows[$i]['last_activity'];
					$old_timestamp = strtotime($saved_timestamp);
					$now = (int) strtotime("now");
					// is entry more than two hours old? (60*60*2)
					// FIXME: would need to set session expiration date directly inside code
					// FIXME: and not in the webserver setting
					if ($now - $old_timestamp > (60*60*2))
					{
						$query = $db->prepare('DELETE LOW_PRIORITY FROM `online_users` WHERE `last_activity`=?');
						$db->execute($query, $saved_timestamp);
					}
				}
			}
			
			// count active sessions
			$query = 'SELECT count(`playerid`) AS `num_players` FROM `online_users`';
			$result = $db->SQL($query, __FILE__);
			
			$n_users = ($db->fetchRow($result));
			if (intval($n_users['num_players']) === 1)
			{
				$this->tpl->setVariable('ONLINE_USERS', '1 user');
			} else
			{
				$this->tpl->setVariable('ONLINE_USERS', ($n_users['num_players'] . ' users'));
			}
			
			// user is logged in -> show logout option
			if ($user->loggedIn())
			{
				$this->tpl->setCurrentBlock('LOGOUT');
				$this->tpl->setVariable('LOGOUTURL', ($config->value('baseaddress') . 'Logout/'));
				$this->parseCurrentBlock();
			}
		}
		
		function __construct($template='', $customTheme='')
		{
			global $db;
			global $config;
			global $connection;
			
			require_once dirname(dirname(__FILE__)) . '/TemplateSystem/HTML/Template/IT.php';
			
			$connection = $db->createConnection();
			
			if ((strcmp($template, '') !== 0) || (strcmp($customTheme, '') !== 0))
			{
				$this->setTemplate($template, $customTheme);
			}
		}
		
		function setCurrentBlock($block)
		{
			// Assign data to the inner block
			return $this->tpl->setCurrentBlock($block);
		}
		
		function setVariable($data, $cell)
		{
			$this->tpl->setVariable($data, $cell);
		}
		
		function parseCurrentBlock()
		{
			$this->tpl->parseCurrentBlock();
		}
		
		private function buildMenu()
		{
			global $user;
			
			$this->tpl->setCurrentBlock('MENU');
			include dirname(dirname(dirname(__FILE__))) .'/styles/' . $user->getStyle() . '/menu.php';
			
			$menuClass = new menu();
			$menu = $menuClass->createMenu();
			
			foreach ($menu as $oneMenuEntry)
			{
				$this->tpl->setVariable('MENU_STRUCTURE', $oneMenuEntry);
				$this->parseCurrentBlock();
			}
			unset($oneMenuEntry);
		}
		
		
		private function buildTitle()
		{
			// set a custom title, if title set
			if (strlen($this->title) > 0)
			{
				if ($this->tpl->setCurrentBlock('TITLE_AREA') === true)
				{
					$this->tpl->setVariable('TITLE', $this->title);
					$this->parseCurrentBlock();
				}
			}
		}
		
		function touchBlock($block)
		{
			$this->tpl->touchBlock($block);
		}
		
		function render()
		{
			// build title
			$this->buildTitle();
			
			// build menu at last to prevent undesired side effects when logging in or out
			$this->buildMenu();
			
			// include status message at the end
			// so we do not forget to display any message
			$this->tpl->setCurrentBlock('MISC');
			
			if (count($this->msg) > 0)
			{
				foreach ($this->msg as $curMSG)
				{
					$this->tpl->setVariable('MSG', $curMSG);
					$this->parseCurrentBlock();
				}
			}
			
			
			// show all
			$this->tpl->show();
		}
		
		// quick way to stop script and to output a message
		function done($msg)
		{
			$this->addMSG($msg);
			$this->render();
			die();
		}
		
		function write_self_closing_tag($tag)
		{
			echo $this->return_self_closing_tag($tag);
		}
		
		function return_self_closing_tag($tag)
		{
			global $config;
			
			$result = '<';
			$result .= $tag;
			// do we use xhtml (->true) or html (->false)
			if ($config->value('useXhtml'))
			{
				$result .= ' /';
			}
			$result .= '>';
			$result .= "\n";
			
			return $result;
		}
		
		// process bbcode
		function encodeBBCode($bbcode)
		{
			global $config;
			
			if (strcmp($config->value('bbcodeLibPath'), '') === 0)
			{
				// no bbcode library specified
				return $this->linebreaks(htmlent($bbcode));
			}
			// load the library
			require_once ($config->value('bbcodeLibPath'));
			
			if (strcmp($config->value('bbcodeCommand'), '') === 0)
			{
				// no command that starts the parser
				return $this->linebreaks(htmlent($bbcode));
			} else
			{
				$parse_command = $config->value('bbcodeCommand');
			}
			
			if (!(strcmp($config->value('bbcodeClass'), '') === 0))
			{
				// no class specified
				// this is no error, it only means the library stuff isn't started by a command in a class
				$bbcode_class = $config->value('bbcodeClass');
				$bbcode_instance = new $bbcode_class;
			}
			
			// execute the bbcode algorithm
			if (isset($bbcode_class))
			{
				if ($config->value('bbcodeSetsLinebreaks'))
				{
					return $bbcode_instance->$parse_command($bbcode);
				} else
				{
					return $this->linebreaks($bbcode_instance->$parse_command($bbcode));
				}
			} else
			{
				if ($config->value('bbcodeSetsLinebreaks'))
				{
					return $parse_command($bbcode);
				} else
				{
					return $this->linebreaks($parse_command($bbcode));
				}
			}
		}
		
		// add linebreaks to input, thus enable usage of multiple lines
		function linebreaks($text)
		{
			global $config;
			
			if (phpversion() >= ('5.3'))
			{
				return nl2br($text, ($config->value('useXhtml')));
			} else
			{
				return nl2br($text);
			}
		}
	}
	
?>