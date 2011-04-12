<?php
	include(dirname(__FILE__) . '/smarty/Smarty.class.php');
	
	class tmpl extends Smarty
	{
		private $templateFile='';
		private $theme;
		
		function __construct()
		{
			global $config;
			
			$this->debugging = false;
			// cache not helpful with dynamic pages
			// turn cache on in add-ons, if helpful to you
			$this->caching = false;
			$this->cache_lifetime = 120;
			
			parent::__construct();
			parent::assign('faviconURL', $config->value('favicon'));
			parent::assign('baseURL', $config->value('baseaddress'));
		}
		
		
		private function buildMenu()
		{
			global $config;
			global $user;
			global $db;
			
			
			$menuFile = $this->theme . 'templates/menu.php';
			
			if (!file_exists($menuFile))
			{
				parent::assign('menu', 'No menu file found.');
				return;
			}
			
			include($menuFile);
			$menu = new menu();
			parent::assign('menu', $menu->createMenu());
			unset($menu);
			
			parent::assign('date', date('Y-m-d H:i:s T'));
/*
			if ($config->value('debugSQL'))
			{
				$this->assign('MSG', 'Used menu: ' . $menuFile);
			}
*/
			
			
			/*
			 $menuClass = new menu();
			 $menu = $menuClass->createMenu();
			 
			 foreach ($menu as $oneMenuEntry)
			 {
			 $this->tpl->setVariable('MENU_STRUCTURE', $oneMenuEntry);
			 $this->parseCurrentBlock();
			 }
			 unset($oneMenuEntry);
			 */
			
			// count online players on match servers
			
			// run the update script:
			// >/dev/null pipes output to nowhere
			// & lets the script run in the background
			exec('php ' . dirname(dirname(__FILE__)) . '/cli/servertracker_query_backend.php >/dev/null &');
			
			// build sum from list of servers
			$query = ('SELECT SUM(`cur_players_total`) AS `cur_players_total`'
					  . ' FROM `servertracker`'
					  . ' ORDER BY `id`');
			$result = $db->SQL($query, __FILE__);
			
			while ($row = $db->fetchRow($result))
			{
				if (intval($row['cur_players_total']) === 1)
				{
					parent::assign('onlinePlayers', '1 player');
				} else
				{
					parent::assign('onlinePlayers', (strval($row['cur_players_total']) . ' players'));
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
				parent::assign('onlineUsers', '1 user');
			} else
			{
				parent::assign('onlineUsers', ($n_users['num_players'] . ' users'));
			}
			
			// user is logged in -> show logout option
			if ($user->loggedIn())
			{
				/* 				parent::assign('LOGOUT'); */
				parent::assign('logoutURL', ($config->value('baseaddress') . 'Logout/'));
			}
		}
		
		function existsTemplate($template, $theme='')
		{
			global $config;
			global $user;
			
			
			if (strlen($theme) === 0)
			{
				$theme = $user->getTheme();
			}
			
			return file_exists(dirname(dirname(dirname(__FILE__))) . '/themes/'
							   . $theme . '/templates/' . $template
							   . ($config->value('useXhtml') ? '.xhtml.tmpl' : '.html.tmpl'));
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
		
		function noTemplateFound()
		{
			global $db;
			
			$db->logError('FATAL ERROR: Template not found, request URI: ' . $_SERVER['REQUEST_URI']);
			$this->setTemplate('404');
			$this->assign('errorMsg', 'Template file not found. This is an installation problem.');
			$this->display();
			die();
		}
		
	    public function display($file='', $cache_id = null, $compile_id = null, $parent = null)
	    {
			global $user;
			
			
			if (strlen($file) > 0)
			{
				$this->setTemplate($file);
			}
			
			// build menu
			$this->buildMenu();
			
			// certain templates need special headers
			switch ($file)
			{
				case '404': header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found"); break;
			}
			
			// add userid to prevent cached output being served to other users
			if ($cache_id === null)
			{
				$cache_id = $user->getID();
			}
			parent::display($this->templateFile, $user->getID(), $compile_id, $parent);
		}
		
		function setTemplate($template, $customTheme='')
		{
			global $config;
			global $user;
			global $tmpl;
			global $db;
			
			
			if (strcmp($customTheme, '') === 0)
			{
				$customTheme = $user->getTheme();
			}
			
			// extract possible file paths out of $template and append it to $themeFolder
			$themeFolder = dirname(dirname(dirname(__FILE__))) .'/themes/' . htmlspecialchars($customTheme);
			$this->findTemplate($template, $themeFolder);
			
			// init template system
			parent::setTemplateDir($themeFolder . 'templates');
			parent::setCompileDir($themeFolder . 'templates_c');
			parent::setCacheDir($themeFolder . 'cache');
			parent::setConfigDir($themeFolder . 'config');
			
			$this->theme = $themeFolder;
			
			//			$this->tpl = new HTML_Template_IT($themeFolder);
			
			// fallback if template specified is empty
			if (strcmp($template, '') === 0)
			{
				$template = 'NoPerm';
			}
			
			//			$this->tpl->loadTemplatefile($template . '.tmpl.html', true, true);
			
			$template .= (($config->value('useXhtml')) ? '.xhtml' : '.html') . '.tmpl';
			
			if (!file_exists($themeFolder . 'templates/' . $template))
			{
				if ($config->value('debugSQL'))
				{
					echo 'Tried to use template: ' . $themeFolder . 'templates/' . $template
					. ' but failed: file does not exist.';
				}
				
				return false;
			} elseif ($config->value('debugSQL'))
			{
				// debug output used template
				//				$this->assign('MSG', 'Used template: ' . $themeFolder
				//							  . $template . '.tmpl.html' . $this->return_self_closing_tag('br'));
			}
			
			$this->templateFile = $template;
			
			// point to currently used theme
			if (strcmp($customTheme, '') === 0)
			{
				parent::assign('curTheme', str_replace(' ', '%20', htmlspecialchars($user->getStyle())));
			} else
			{
				parent::assign('curTheme', $customTheme);
			}
			return true;
		}
	}
?>
