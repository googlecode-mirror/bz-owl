<?php
	// handle user related data
	class user
	{
		// id > 0 means a user is logged in
		function getID()
		{
			$userid = 0;
			
			if ($this->loggedIn())
			{
				if (isset($_SESSION['viewerid']))
				{
					$userid = $_SESSION['viewerid'];
				}
			}
			return (int) $userid;
		}
		
		
		function getStyle()
		{
			global $config;
			
			if ($this->getMobile())
			{
				$default_style = $config->value('defaultMobileTheme');
			} else
			{
				$default_style = $config->value('defaultTheme');
			}
			
			$theme = $default_style;
			if (isset($_SESSION['theme']))
			{
				// use theme chosen this session
				$theme = $_SESSION['theme'];
			} else
			{
				// otherwise use cookie
				foreach ($_COOKIE as $key => $value)
				{
					if (strcasecmp($key, 'theme') == 0)
					{
						// cookies turned on
						$theme = $value;
					}
				}
			}
			
			if (!(file_exists(dirname(dirname(dirname(__FILE__))) . '/styles/' . $theme . '/' . $theme . '.css')))
			{
				// stylesheet in question does not exist, go back to default
				$theme = $default_style;
				
				// save theme
				$this->saveTheme($theme);
			}
			
			if (strcasecmp($theme, '') == 0)
			{
				// nothing is set, go back to default
				$theme = $default_style;
			}
			
			if (!(isset($_SESSION['themeSaved'])) || !$_SESSION['themeSaved'])
			{
				$this->saveTheme($theme);
			}
			
			return $theme;
		}
		
		
		function hasPermission($permission)
		{
			if (isset($_SESSION[$permission]))
			{
				return $_SESSION[$permission];
			}
			
			return false;
		}
		
		
		function saveTheme($theme)
		{
			global $config;
			
			// save theme for two months
			setcookie('theme', $theme, time()+60*60*24*30*2, $config->value('basepath'), $config->value('domain'), 0);
			
			// save it in session based variable if setting cookie failed
			// it could fail because user did not accept cookie for instance
			$_SESSION['theme'] = $theme;
			
			// mark it saved
			$_SESSION['themeSaved'] = true;
		}
		
		
		function getMobile()
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
		
		
		function loggedIn()
		{
			return (isset($_SESSION['user_logged_in']) && ($_SESSION['user_logged_in'] === true));
		}
		
		
		// logout the user
		function logout()
		{
			$_SESSION['user_logged_in'] = false;
			$_SESSION['viewerid'] = -1;
		}
	}
?>
