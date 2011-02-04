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
