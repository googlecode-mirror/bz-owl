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
			if ($this->getMobile())
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
			
			if (!(file_exists(dirname(dirname(dirname(__FILE__))) . '/styles/' . $style . '/' . $style . '.css')))
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
