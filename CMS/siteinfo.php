<?php
	// register_globals turned on is a security nightmare
	if ((int) (ini_get('register_globals')) === 1)
	{
		die('WTF! Tell the hoster to set up a sane environment This message was presented to you by siteinfo configurator.');
	}
	
	// we don't want magic quotes, do we?
	if (get_magic_quotes_gpc() === 1)
	{
		echo 'PHP magic quotes are supposed to be OFF for this site. Disable them please, they are gone in PHP 6 anyway.';
		die (' Please also read <a href="http://www.php.net/manual/en/info.configuration.php#ini.magic-quotes-gpc">the manual</a>.');
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
	
	function domain()
	{
		// return 'my.bzflag.org';
		return '192.168.1.10';
	}
	
	function basepath()
	{
		return '/~spiele/league_svn/ts/';
	}
	
	function baseaddress()
	{
		$www_required = false;
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
	
	
	// give ability to use a limited custom style
	function bbcode($string)
	{
		// TODO: Parse bbcode!
		// TODO: strip_tags maybe useful for sanity check at the end
		return htmlentities($string);
	}
	
	// set up a class for less frequently used functions
    require_once('../../leaguesite_passwords.php');
	class siteinfo
	{
		var $siteinfo_use_mysql_news = false;
		var $xhtml_on = false;
        
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
		
		function connect_to_db()
		{
			$link = $this->loudless_connect_to_db();
			if (!$link)
			{
				echo '<p>Could not connect to database.</p>' . "\n";
				echo 'error: ' . mysql_error();
			}
			return $link;
		}
		
		// maybe one day use PDO ( http://de.php.net/pdo )
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
			return 'ts-CMS';
		}
		
		function debug_sql()
		{
			return true;
		}
		
		function execute_silent_query($db, $table, $query, $connection)
		{
			// choose database
			if (!(mysql_select_db($db, $connection)))
			{
				die('<p>Could not select database!<p>');
			}
			
			$result = mysql_query($query, $connection);
			if (!$result)
			{
				echo('<p>Query is probably not valid SQL. ');
				echo 'Updating: An error occurred while executing the query (' . htmlentities($query) . ') , ' . htmlentities($table) . ' may be now completly broken.</p>' . "\n";
				// print out the error in debug mode
				if ($this->debug_sql())
				{
					echo mysql_error();
				}
			}
			return $result;
		}
		
		function execute_query($db, $table, $query, $connection)
		{
			// choose database
			if (!(mysql_select_db($db, $connection)))
			{
				die('<p>Could not select database!<p>');
			}
				
			
			// output the query in debug mode
			if ($this->debug_sql())
			{
				echo '<p>executing query: ' . htmlentities($query) . '</p>' . "\n";
			}
			$result = mysql_query($query, $connection);
			if (!$result)
			{
				echo('<p>Query is probably not valid SQL. ');
				echo 'Updating: An error occurred while executing the query, ' . htmlentities($table) . ' may be now completly broken.</p>' . "\n";
				// print out the error in debug mode
				if ($this->debug_sql())
				{
					echo mysql_error();
				}
			}
			return $result;
		}
		
		function set_key($randomkey_name)
		{
			// this should be good enough as all we need is something that can not be guessed without much tries
			return $_SESSION[$randomkey_name] = rand(0, getrandmax());
		}
		
		function compare_keys($key, $key2 = '')
		{
			$used_key2 = $key2;
			if (strcmp($key2, '') === 0)
			{
				$used_key2 = $key;
			}
			
			if ((isset($_POST[$key])) && (isset($_SESSION[$used_key2])))
			{
				$randomkeysmatch = strcmp(html_entity_decode((urldecode($_POST[$key]))), ($_SESSION[$used_key2]) === 0);
				
				// invalidate key to prevent allowing sending stuff more than once
				if (!(strcmp($key2, '') === 0))
				{
					unset ($_SESSION[$key2]); 
				}
				
				return $randomkeysmatch;
			} else
			{
				// variables are not even set, they can't match
				return false;
			}
		}
		
		function mobile_version()
		{
			// this switch should be used sparingly and only in cases where content would not fit on the display
			$browser = $_SERVER['HTTP_USER_AGENT'];
			if (preg_match("/.(Mobile|mobile)/", $browser))
			{
				// mobile browser
				return true;
			} else {
				return false;
			}
		}
		
		function use_xtml()
		{
			// do we use xtml (->true) or html (->false)
			return $this->xhtml_on;
		}
		
		function write_self_closing_tag($tag)
		{
			echo '<';
			echo $tag;
			// do we use xtml (->true) or html (->false)
			if ($this->use_xtml())
			{
				echo ' /';
			}
			echo '>';
			echo "\n";
		}		
		
		function base_name()
		{
			$path = (pathinfo(realpath('./')));
			$name = $path['basename'];
			return $name;
		}
				
		function dieAndEndPage($message='')
		{
			// TODO: report this to admins
			if (strcmp($message, '') === 0)
			{
				echo htmlentities($message);
			} else
			{
				echo '<p>' . htmlentities($message) . '</p>';
			}
			die("\n" . '</div>' . "\n" . '</body>' . "\n" . '</html>');
		}
		
		// add linebreaks to input, thus enable usage of multiple lines
		function linebreaks($text)
		{
			echo nl2br($text, ($this->use_xtml()));
		}
	}
?>