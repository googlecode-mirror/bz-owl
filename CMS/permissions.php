<?php
	function no_permissions()
	{
		// delete bzid
		unset($_SESSION['bzid']);
		
		// no external id by default
		$_SESSION['external_id'] = 0;
		
		// assume local login by default
		$_SESSION['external_login'] = false;
		
		// set all permission to false by default
		// permissions for news page
		$_SESSION['allow_set_different_news_author'] = false;
		$_SESSION['allow_add_news'] = false;
		$_SESSION['allow_edit_news'] = false;
		$_SESSION['allow_delete_news'] = false;
		
		// permissions for bans page
		$_SESSION['allow_set_different_bans_author'] = false;
		$_SESSION['allow_add_bans'] = false;
		$_SESSION['allow_edit_bans'] = false;
		$_SESSION['allow_delete_bans'] = false;
		
		// permissions for private messages
		$_SESSION['allow_add_messages'] = false;
		// private messages are never supposed to be edited at all
		$_SESSION['allow_edit_messages'] = true;
		$_SESSION['allow_delete_messages'] = true;
		
		// team permissions
		$_SESSION['allow_kick_any_team_members'] = false;
		$_SESSION['allow_edit_any_team_profile'] = false;
		$_SESSION['allow_invite_in_any_team'] = false;
		$_SESSION['allow_delete_any_team'] = false;
		
		// user permissions
		$_SESSION['allow_edit_any_user_profile'] = false;
		$_SESSION['allow_add_admin_comments_to_user_profile'] = false;
		$_SESSION['allow_ban_any_user'] = false;
		
		// visits log permissions
		$_SESSION['allow_view_user_visits'] = false;
		
		// match permissions
		$_SESSION['allow_any_match_action'] = false;
	}
	
	function allow_set_different_news_author()
	{
		if (!($_SESSION['allow_set_different_news_author']))
		{
			$_SESSION['allow_set_different_news_author'] = true;
		}
	}
	
	function allow_add_news()
	{
		if (!($_SESSION['allow_add_news']))
		{
			$_SESSION['allow_add_news'] = true;
		}
	}
	
	function allow_edit_news()
	{
		if (!($_SESSION['allow_edit_news']))
		{
			$_SESSION['allow_edit_news'] = true;
		}
	}
	
	function allow_delete_news()
	{
		if (!($_SESSION['allow_delete_news']))
		{
			$_SESSION['allow_delete_news'] = true;
		}
	}
	
	function allow_set_different_bans_author()
	{
		if (!($_SESSION['allow_set_different_bans_author']))
		{
			$_SESSION['allow_set_different_bans_author'] = true;
		}
	}
	
	function allow_add_bans()
	{
		if (!($_SESSION['allow_add_bans']))
		{
			$_SESSION['allow_add_bans'] = true;
		}
	}
	
	function allow_edit_bans()
	{
		if (!($_SESSION['allow_edit_bans']))
		{
			$_SESSION['allow_edit_bans'] = true;
		}
	}
	
	function allow_delete_bans()
	{
		if (!($_SESSION['allow_delete_bans']))
		{
			$_SESSION['allow_delete_bans'] = true;
		}
	}
	
	function allow_add_messages()
	{
		if (!($_SESSION['allow_add_messages']))
		{
			$_SESSION['allow_add_messages'] = true;
		}
	}
	
	function allow_delete_messages()
	{
		if (!($_SESSION['allow_delete_messages']))
		{
			$_SESSION['allow_delete_messages'] = true;
		}
	}
	
	function allow_kick_any_team_members()
	{
		if (!($_SESSION['allow_kick_any_team_members']))
		{
			$_SESSION['allow_kick_any_team_members'] = true;
		}
	}
	
	function get_allow_kick_any_team_members()
	{
		$reply = false;
		if (isset($_SESSION['allow_kick_any_team_members']))
		{
			if ($_SESSION['allow_kick_any_team_members'] === true)
			{
				$reply = true;
			}
		}
		return $reply;
	}
	
	
	function allow_edit_any_team_profile()
	{
		if (!($_SESSION['allow_edit_any_team_profile']))
		{
			$_SESSION['allow_edit_any_team_profile'] = true;
		}
	}
	
	function allow_delete_any_team()
	{
		if (!($_SESSION['allow_delete_any_team']))
		{
			$_SESSION['allow_delete_any_team'] = true;
		}
	}	
	
	function allow_edit_any_user_profile()
	{
		if (!($_SESSION['allow_edit_any_user_profile']))
		{
			$_SESSION['allow_edit_any_user_profile'] = true;
		}
	}
	
	function allow_add_admin_comments_to_user_profile()
	{
		if (!($_SESSION['allow_add_admin_comments_to_user_profile']))
		{
			$_SESSION['allow_add_admin_comments_to_user_profile'] = true;
		}
	}
	
	function allow_ban_any_user()
	{
		if (!($_SESSION['allow_ban_any_user']))
		{
			$_SESSION['allow_ban_any_user'] = true;
		}
	}
	
	function allow_view_user_visits()
	{
		if (!($_SESSION['allow_view_user_visits']))
		{
			$_SESSION['allow_view_user_visits'] = true;
		}
	}
	
	function allow_any_match_action()
	{
		if (!($_SESSION['allow_any_match_action']))
		{
			$_SESSION['allow_any_match_action'] = true;
		}
	}
	
	function allow_invite_in_any_team()
	{
		if (!($_SESSION['allow_invite_in_any_team']))
		{
			$_SESSION['allow_invite_in_any_team'] = true;
		}
	}
?>