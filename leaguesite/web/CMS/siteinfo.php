<?php
	// register_globals turned on is a security nightmare
	if ((int) (ini_get('register_globals')) === 1)
	{
		die('WTF! Tell the hoster to set up a sane environment This message was presented to you by siteinfo configurator.');
	}
	function magic_quotes_on()
	{
		if (function_exists('get_magic_quotes_gpc') && (get_magic_quotes_gpc() === 1))
		{
			return true;
		}
		
		return false;
	}
	// we don't want magic quotes, do we?
	if (magic_quotes_on())
	{
		echo 'PHP magic quotes are supposed to be OFF for this site. Disable them please, they are gone in PHP 6 anyway.';
		die (' Please also read <a href="http://www.php.net/manual/en/info.configuration.php#ini.magic-quotes-gpc">the manual</a>.');
	}
	
	// find stylesheet even if magic quotes are on.
	if (magic_quotes_on())
	{
		stripslashes($_COOKIE);
	}
	
	function sqlSafeString($param)
	{
		// use MySQL function mysql_real_escape_string, alternative could be prepared statements
		return (NULL === $param ? "NULL" : mysql_real_escape_string ($param));
	}
	
	function sqlSafeStringQuotes($param)
	{
		// use sqlSafeString and append quotes before and after the result
		return ("'" . sqlSafeString($param) . "'");
	}
	
	// check siteoptions_path_example.php and follow the instructions there
	require_once('siteoptions_path.php');
	
	function baseaddress()
	{
		$www_required = www_required();
		if ($www_required)
		{
			$www = 'www.';
		} else
		{
			$www = '';
		}
		return 'http://' . $www . domain() . basepath();
	}
	
	// id > 0 means a user is logged in
	function getUserID()
	{
		$userid = 0;
		if (isset($_SESSION['user_logged_in']) && ($_SESSION['user_logged_in'] === true))
		{
			if (isset($_SESSION['viewerid']))
			{
				$userid = $_SESSION['viewerid'];
			}
		}
		return (int) $userid;
	}
	
	// shortcut for utf-8 aware htmlentities
	function htmlent($string)
	{
		return htmlentities($string, ENT_COMPAT, 'UTF-8');
	}
	
	function htmlent_decode($string)
	{
		return html_entity_decode($string, ENT_COMPAT, 'UTF-8');
	}
	
	class template
	{
		private $tpl;
		var $msg = array();
		
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
				
				$path .= '/' . implode('/', $pathinfo);
			}
		}
		
		function __construct($template, $customTheme='')
		{
			global $site;
			global $connection;
			
			require_once 'TemplateSystem/HTML/Template/IT.php';
			
			// need db connection
			$connection = $site->connect_to_db();
			
			if (strcmp($customTheme, '') === 0)
			{
				$customTheme = $site->getStyle();
			}
			
			// extract possible file paths out of $template and append it to $themeFolder
			$themeFolder = $customTheme;
			$this->findTemplate($template, $themeFolder);
			
			// init template system
			$this->tpl = new HTML_Template_IT(dirname(dirname(__FILE__)) .'/styles/' . str_replace(' ', '%20', htmlspecialchars($themeFolder)));
			
			// load the current template file
			if (strcmp($template, '') === 0)
			{
				$template = $site->base_name();
			}
			
			$this->tpl->loadTemplatefile($template . '.tmpl.html', true, true);
			
			// debug output used template
			if ($site->debug_sql())
			{
				$this->addMSG('Used template: ' . dirname(dirname(__FILE__)) .'/styles/'
							  . str_replace(' ', '%20', htmlspecialchars($themeFolder))
							  . '/' . $template . '.tmpl.html');
				$this->addMSG($site->return_self_closing_tag('br'));
			}

			
			// use favicon, if available
			if (!(strcmp($site->favicon_path(), '') === 0))
			{
				$this->tpl->setCurrentBlock('FAVICON');
				$this->tpl->setVariable('FAVICONURL', $site->favicon_path());
				$this->parseCurrentBlock();
			}
			
			$this->tpl->setCurrentBlock('MENU');
			include(dirname(dirname(__FILE__)) .'/styles/' . str_replace(' ', '%20', htmlspecialchars($customTheme)) . '/menu.php');
			
			$menuClass = new menu();
			$menu = $menuClass->createMenu();
			
			foreach ($menu as $oneMenuEntry)
			{
				$this->tpl->setVariable('MENU_STRUCTURE', $oneMenuEntry);
				$this->parseCurrentBlock();
			}
			
			$this->tpl->setCurrentBlock('MAIN');
			
			// links should point to current locations
			$this->tpl->setVariable('BASEURL', baseaddress());
			
			// point to currently used theme
			if (strcmp($customTheme, '') === 0)
			{
				$this->tpl->setVariable('CUR_THEME', str_replace(' ', '%20', htmlspecialchars($site->getStyle())));
			} else
			{
				$this->tpl->setVariable('CUR_THEME', $customTheme);
			}
			
			// set the date and time
			date_default_timezone_set($site->used_timezone());
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
			$result = $site->execute_silent_query('servertracker', $query, $connection, __FILE__);
			while ($row = mysql_fetch_array($result))
			{
				if (intval($row['cur_players_total']) === 1)
				{
					$this->tpl->setVariable('ONLINE_PLAYERS', '1 player');
				} else
				{
					$this->tpl->setVariable('ONLINE_PLAYERS', (strval($row['cur_players_total']) . ' players'));
				}
			}
			mysql_free_result($result);
			
			// remove expired sessions from the list of online users
			$query ='SELECT `playerid`, `last_activity` FROM `online_users`';
			$result = @$site->execute_silent_query('online_users', $query, $connection, __FILE__);
			if (((int) mysql_num_rows($result)) > 0)
			{
				while($row = mysql_fetch_array($result))
				{
					$saved_timestamp = $row['last_activity'];
					$old_timestamp = strtotime($saved_timestamp);
					$now = (int) strtotime("now");
					// is entry more than two hours old? (60*60*2)
					// FIXME: would need to set session expiration date directly inside code
					// FIXME: and not in the webserver setting
					if ($now - $old_timestamp > (60*60*2))
					{
						$query = 'DELETE LOW_PRIORITY FROM `online_users` WHERE `last_activity`=';
						$query .= sqlSafeStringQuotes($saved_timestamp);
						$result_delete = @$site->execute_silent_query('online_users', $query, $connection, __FILE__);
					}
				}
			}
			mysql_free_result($result);
			
			// count active sessions
			$query = 'SELECT count(`playerid`) AS `num_players` FROM `online_users`';
			$result = @$site->execute_silent_query('online_users', $query, $connection, __FILE__);
			
			$n_users = (mysql_fetch_array($result));
			if (intval($n_users['num_players']) === 1)
			{
				$this->tpl->setVariable('ONLINE_USERS', '1 user');
			} else
			{
				$this->tpl->setVariable('ONLINE_USERS', ($n_users['num_players'] . ' users'));
			}
			
			// user is logged in -> show logout option
			if (getUserID() > 0)
			{
				$this->tpl->setCurrentBlock('LOGOUT');
				$this->tpl->setVariable('LOGOUTURL', (baseaddress() . 'Logout/'));
				$this->parseCurrentBlock();
			}
		}
		
		function setCurrentBlock($block)
		{
			// Assign data to the inner block
			$this->tpl->setCurrentBlock($block);
		}
		
		function setVariable($data, $cell)
		{
			$this->tpl->setVariable($data, $cell);
		}
		
		function parseCurrentBlock()
		{
			$this->tpl->parseCurrentBlock();
		}
		
		function render()
		{
			global $site;
			
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
	}
		
	
	// set up a class for less frequently used functions
	class siteinfo
	{
		function use_mysql_news()
		{
			return $this->siteinfo_use_mysql_news;
		}
		
		function mysqlpw()
		{
			$pw = new pw_secret();
			return $pw->mysqlpw_secret();
		}
		
		function mysqluser()
		{
			$pw = new pw_secret();
			return $pw->mysqluser_secret();
		}
		
		// this function should be used to connect to a database
		function connect_to_db()
		{
			$link = $this->loudless_connect_to_db();
			
			if (!$link)
			{
				echo '<p>Could not connect to database.</p>' . "\n";
				if ($this->debug_sql())
				{
					echo 'Raw error: ' . mysql_error();
				}
			}
			
			$this->selectDB($this->db_used_name(), $link);
			return $link;
		}
		
		// maybe one day use PDO ( http://de.php.net/pdo )
		// major problem nowadays are the error messages that would be shown that include username and password if not handled
		function loudless_connect_to_db()
		{
			return $link = @mysql_connect('127.0.0.1', $this->mysqluser(), $this->mysqlpw());
		}
		
		function loudless_pconnect_to_db()
		{
			return $link = @mysql_pconnect('127.0.0.1', $this->mysqluser(), $this->mysqlpw());
		}
		
		function db_used_name()
		{
			return db_used_custom_name();
		}
		
		function debug_sql()
		{
			if (isset($_SESSION['debug_sql']))
			{
				return ($_SESSION['debug_sql']);
			} else
			{
				return debug_sql_custom();
			}
		}
		
		function selectDB($db, $connection)
		{
			// choose database
			if (!(mysql_select_db($db, $connection)))
			{
				die('<p>Could not select database!<p>');
			}
		}
		
		function execute_silent_query($table, $query, $connection, $file='', $errorUserMSG='')
		{
			$result = mysql_query($query, $connection);
			
			if (!$result)
			{
				// print out the raw error in debug mode
				if ($this->debug_sql())
				{
					echo('<p>Query ' . htmlent($query) . ' is probably not valid SQL. ');
					echo 'Updating: An error occurred while executing the query, ' . htmlentities($table);
					echo ' may be now completly broken.</p>' . "\n";
					echo mysql_error();
				}
				
				// log the error
				$site->log_error_query($file, 'SELECT `id` FROM `players` WHERE `teamid`=' . sqlSafeStringQuotes(intval($teamid)));
				
				if (strlen($errorUserMSG) > 0)
				{
					$site->dieAndEndPage($errorUserMSG);
				}
				
				$site->dieAndEndPage('Error: Could not process query.');
			}
		
		return $result;
		}
		
		function execute_query($table, $query, $connection, $file='', $errorUserMSG='')
		{
			// output the query in debug mode
			if ($this->debug_sql())
			{
				echo '<p class="first_p">executing query: ' . htmlent($query) . '</p>' . "\n";
			}
			$result = mysql_query($query, $connection);
			if (!$result)
			{
				// print out the raw error in debug mode
				if ($this->debug_sql())
				{
					echo('<p>Query ' . htmlent($query) . ' is probably not valid SQL. ');
					echo 'Updating: An error occurred while executing the query, ' . htmlentities($table);
					echo ' may be now completly broken.</p>' . "\n";
					echo mysql_error();
				}
				
				// log the error
				$site->log_error_query($file, 'SELECT `id` FROM `players` WHERE `teamid`=' . sqlSafeStringQuotes(intval($teamid)));
				
				if (strlen($errorUserMSG) > 0)
				{
					$site->dieAndEndPage($errorUserMSG);
				}
				
				$site->dieAndEndPage('Error: Could not process query.');
			}
			
			
			return $result;
		}
		
		function convert_users_to_external_login()
		{
			return convert_users_to_external_login_if_no_external_login_id_set();
		}
		
		function force_external_login_when_trying_local_login()
		{
			return force_external_login_only();
		}
		
		function forced_author($section)
		{
			return force_username($section);
		}
		
		function displayed_system_username()
		{
			return system_username();
		}
		
		function set_key($randomkey_name)
		{
			// this should be good enough as all we need is something that can not be guessed without many tries
			return $_SESSION[$randomkey_name] = rand(0, getrandmax());
		}
		
		
		// outdated function, do not use anymore!
		function compare_keys($key, $key2 = '')
		{
			$used_key2 = $key2;
			if (strcmp($key2, '') === 0)
			{
				$used_key2 = $key;
			}
			
			if ((isset($_POST[$key])) && (isset($_SESSION[$used_key2])))
			{
				$randomkeysmatch = (strcmp(html_entity_decode((urldecode($_POST[$key]))), ($_SESSION[$used_key2])) === 0);
				
				// invalidate key to prevent allowing sending stuff more than once
				if (!(strcmp($key2, '') === 0))
				{
					unset ($_SESSION[$key2]); 
				}
				
				return $randomkeysmatch;
			}
			
			return false;
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
		
		function mobile_version()
		{
			// this switch should be used sparingly and only in cases where content would not fit on the display
			if (isset($_SERVER['HTTP_USER_AGENT']))
			{
			$browser = $_SERVER['HTTP_USER_AGENT'];
				if (preg_match("/.(Mobile|mobile)/", $browser))
				{
					// mobile browser
					return true;
				} else {
					return false;
				}
			} else
			{
				return false;
			}
		}
		
		function used_timezone()
		{
			return timezone();
		}
		
		function use_xhtml()
		{
			// accidental misspelled version implemented before
			// the function called use_xtml() is deprecated and will be removed in the future
			return use_xtml();
		}
		
		function use_xtml()
		{
			// do we use xtml (->true) or html (->false)
			if (phpversion() >= ('5.3'))
			{
				return xhtml_on();
			}
			if (phpversion() >= ('4.0.5'))
			{
				return true;
			} else
			{
				// nl2br needs php newer or equal to 4.0.5 to support xhtml
				// see http://www.php.net/manual/en/function.nl2br.php
				return false;
			}
		}
		
		function write_self_closing_tag($tag)
		{
			echo $this->return_self_closing_tag($tag);
		}
		
		function return_self_closing_tag($tag)
		{
			$result = '<';
			$result .= $tag;
			// do we use xtml (->true) or html (->false)
			if ($this->use_xtml())
			{
				$result .= ' /';
			}
			$result .= '>';
			$result .= "\n";
			
			return $result;
		}
		
		function base_name()
		{
			$path = (pathinfo(realpath('./')));
			$name = $path['basename'];
			return $name;
		}
				
		function dieAndEndPage($message='')
		{
			if (!(strcmp($message, '') === 0))
			{
				$this->log_error($message);
				echo '<p>' . $message . '</p>';
			}
			die("\n" . '</div>' . "\n" . '</div>' . "\n" . '</body>' . "\n" . '</html>');
		}
		
		function dieAndEndPageNoBox($message='')
		{
			if (!(strcmp($message, '') === 0))
			{
				$this->log_error($message);
				echo '<p>' . $message . '</p>';
			}
			die("\n". '</div>' . "\n" . '</body>' . "\n" . '</html>');
		}
		
		function bbcode_lib_available()
		{
			return !(strcmp(bbcode_command(), '') === 0);
		}
		
		// give ability to use a limited custom style
		function bbcode($string)
		{
			if (strcmp(bbcode_lib_path(), '') === 0)
			{
				// no bbcode library specified
				return $this->linebreaks(htmlent($string));
			}
			// load the library
			require_once (bbcode_lib_path());
			
			if (strcmp(bbcode_command(), '') === 0)
			{
				// no command that starts the parser
				return $this->linebreaks(htmlent($string));
			} else
			{
				$parse_command = bbcode_command();
			}
			
			if (!(strcmp(bbcode_class(), '') === 0))
			{
				// no class specified
				// this is no error, it only means the library stuff isn't started by a command in a class
				$bbcode_class = bbcode_class();
				$bbcode_instance = new $bbcode_class;
			}
			
			// execute the bbcode algorithm
			if (isset($bbcode_class))
			{
				if (bbcode_sets_linebreaks())
				{
					return $bbcode_instance->$parse_command($string);
				} else
				{
					return $this->linebreaks($bbcode_instance->$parse_command($string));
				}
			} else
			{
				if (bbcode_sets_linebreaks())
				{
					return $parse_command($string);
				} else
				{
					return $this->linebreaks($parse_command($string));
				}
			}
		}
		
		// add linebreaks to input, thus enable usage of multiple lines
		function linebreaks($text)
		{
			if (phpversion() >= ('5.3'))
			{
				return nl2br($text, ($this->use_xtml()));
			} else
			{
				return nl2br($text);
			}
		}
		
		function log_error_query($file, $error)
		{
			log_error('Error: Query failed in ' . $file . ': ' . $error);
		}
		
		function log_error($error='')
		{
			if (!(strcmp($error, '') === 0))
			{
				// non functional at the moment
				// TODO: implement logging here!
			}
		}
		
		function getStyle()
		{
			global $site;
			
			if ($site->mobile_version())
			{
				$default_style = 'White';
			} else
			{
				$default_style = '42';
			}
			
			$style = $default_style;
			foreach ($_COOKIE as $key => $value)
			{
				if (strcasecmp($key, 'theme') == 0)
				{
					// cookies turned on
					$style = $value;
				}
			}
			
			if (isset($_SESSION['theme']))
			{
				$style = $_SESSION['theme'];
			}
			
			if (!(file_exists(dirname(dirname(__FILE__)) . '/styles/' . $style . '/' . $style . '.css')))
			{
				// stylesheet in question does not exist, go back to default
				$style = $default_style;
			}
			
			if (strcasecmp($style, '') == 0)
			{
				// nothing is set, go back to default
				$style = $default_style;
			}
			
			return $style;
		}
		
		function favicon_path()
		{
			return favicon();
		}
		
		function hasUnreadMail()
		{
			global $connection;
			
			require_once dirname(__FILE__) . '/permissions.php';
			
			$unread_messages = false;
			
			// set the date and time
			date_default_timezone_set($this->used_timezone());
			
			// remove expired sessions from the list of online users
			$query ='SELECT `playerid`, `last_activity` FROM `online_users`';
			$result = $this->execute_silent_query('online_users', $query, $connection, __FILE__, 'Could not get list of online users from database');
			
			if (((int) mysql_num_rows($result)) > 0)
			{
				while($row = mysql_fetch_array($result))
				{
					$saved_timestamp = $row['last_activity'];
					$old_timestamp = strtotime($saved_timestamp);
					$now = (int) strtotime("now");
					// is entry more than two hours old? (60*60*2)
					// FIXME: would need to set session expiration date directly inside code
					// FIXME: and not in the webserver setting
					if ($now - $old_timestamp > (60*60*2))
					{
						$query = 'DELETE LOW_PRIORITY FROM `online_users` WHERE `last_activity`=';
						$query .= sqlSafeStringQuotes($saved_timestamp);
						if (!($result_delete = $this->execute_silent_query('online_users', $query, $connection)))
						{
							$site->dieAndEndPage('<p>Could delete old online users from database.</p>');
						}
					}
				}
			}
			mysql_free_result($result);
			
			// update activity data
			$logged_in = true;
			if (getUserID() > 0)
			{
				// the execution of the query is not that time critical and it happens often -> LOW_PRIORITY
				$query = 'UPDATE LOW_PRIORITY `online_users` SET `last_activity`=';
				$query .= sqlSafeStringQuotes(date('Y-m-d H:i:s')) . ' WHERE `playerid`=' . sqlSafeStringQuotes(getUserID());
				@mysql_select_db($this->db_used_name(), $connection);
				@mysql_query($query, $connection);
				
				// are there unread messages?
				// are there unread messages?
				$query = ('SELECT `id` FROM `messages_users_connection` WHERE `msg_status`='
						  . sqlSafeStringQuotes('new')
						  . ' AND `playerid`=' . sqlSafeStringQuotes(getUserID())
						  . ' LIMIT 1');
				$result = @mysql_query($query, $connection);
				$rows = (int) @mysql_num_rows($result);
				if ($rows > 0)
				{
					$unread_messages = true;
				}
				mysql_free_result($result);
			}
			echo 'test';

			return $unread_messages;
		}
	}
	
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
	
	class maintenance_settings
	{
		function maintain_teams_not_matching_anymore()
		{
			return maintain_inactive_teams();
		}
		
		function maintain_teams_not_matching_anymore_players_still_loggin_in()
		{
			return maintain_inactive_teams_with_active_players();
		}
	}
?>
