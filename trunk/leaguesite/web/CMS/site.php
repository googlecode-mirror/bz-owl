<?php
	include('classes/smarty/Smarty.class.php');
	
	// this file is supposed to load and init all common classes
	class site
	{
		function __construct()
		{
			global $tmplHelper;
			global $config;
			global $db;
			global $user;
			global $tmpl;
			
			// setup session
			ini_set ('session.use_trans_sid', 0);
			ini_set ('session.name', 'SID');
			ini_set('session.gc_maxlifetime', '7200');
			session_start();
			
			// database connectivity
			include dirname(__FILE__) . '/classes/config.php';
			$config = new config();
			
			// database connectivity
			include dirname(__FILE__) . '/classes/db.php';
			$db = new database();
			
			// user information
			require dirname(__FILE__) . '/classes/user.php';
			$user = new user();
			
			// template builder
			// do not init it, as db information is needed
			// to find out what template should be used
/* 				include('classes/smarty/Smarty.class.php'); */
			$smarty = new Smarty();
			$tmpl = new tmpl($smarty);
/*
			} else
			{
				require dirname(__FILE__) . '/classes/tmpl.php';
				$tmpl = new template();
			}	
*/
		}
		
		function magic_quotes_on()
		{
			if (function_exists('get_magic_quotes_gpc') && (get_magic_quotes_gpc() === 1))
			{
				return true;
			}
			
			return false;
		}
		
		function basename()
		{
			$path = (pathinfo(realpath('./')));
			$name = $path['basename'];
			return $name;
		}
		
		function setKey($randomkey_name)
		{
			// this should be good enough as all we need is something that can not be guessed without many tries
			return $_SESSION[$randomkey_name] = rand(0, getrandmax());
		}
		
		function validateKey($key, $value)
		{
			if (isset($_SESSION[$key]))
			{
				$randomkeysmatch = (strcmp(html_entity_decode(urldecode($_SESSION[$key])), $value) === 0);
				
				// invalidate key & value to prevent allowing sending stuff more than once
				if (!(strcmp($value, '') === 0))
				{
					unset ($_SESSION[$key]);
					unset ($_SESSION[$value]);
				} else
				{
					// never return true on empty key & value combination
					return false;
				}
				
				return $randomkeysmatch;
			}
			
			return false;
		}
	}
	
	class tmpl extends Smarty
	{
		private $templateFile;
		private $engine;
		private $theme;
		
		function __construct($engine)
		{
			global $config;
			
			
			$this->engine = ($engine);
			$this->debugging = false;
			$engine->caching = true;
			$engine->cache_lifetime = 120;
			
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
			
			// set the date and time
			date_default_timezone_set($config->value('timezone'));
			include($menuFile);
			$menu = new menu();
			parent::assign('menu', $menu->createMenu());
			unset($menu);
			
			parent::assign('date', date('Y-m-d H:i:s T'));
/*
			if ($config->value('debugSQL'))
			{
				$this->addMSG('Used menu: ' . $menuFile);
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
		
		
		function getEngine()
		{
			return $this->engine;
		}
		
		function existsTemplate($template, $theme='')
		{
			global $config;
			global $user;
			
			
			if (strlen($theme) === 0)
			{
				$theme = $user->getTheme();
			}
			
			return file_exists(dirname(dirname(__FILE__)) . '/themes/'
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
			parent::setTemplate('NoPerm');
/* 			$this->done('Template file not found. This is an installation problem.'); */
			die();
		}
		
		public function display()
		{
			// build menu
			$this->buildMenu();
			
			parent::display($this->templateFile);
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
			$themeFolder = dirname(dirname(__FILE__)) .'/themes/' . htmlspecialchars($customTheme);
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
//				$this->addMSG('Used template: ' . $themeFolder
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
	
	// add a few functions to global namespace
	// only add very frequently used functions
	
	// shortcut for utf-8 aware htmlentities
	function htmlent($string)
	{
		return htmlentities($string, ENT_COMPAT, 'UTF-8');
	}
	
	function htmlent_decode($string)
	{
		return html_entity_decode($string, ENT_COMPAT, 'UTF-8');
	}
?>