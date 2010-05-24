<?php
if ( (isset($_GET['bzbbauth'])) && ($_GET['bzbbauth']) )
{
	require '../CMS/bzbb_login/checkToken.php';
	require_once '../CMS/permissions.php';
	
	// initialise permissions
	no_permissions();

	// groups used for permissions
	// each group can use the fine grained permission system
	$groups = Array ('VERIFIED','GU-LEAGUE.ADMINS', 'TS.ADMIN');
	$args = explode (',', urldecode($_GET['bzbbauth']));
	// $args[0] is token, $args[1] is callsign
	$info = validate_token ($args[0], $args[1], $groups);
	// print_r($info);
	// $info set -> list server was reached
	
	// invalid bzid should be -1
	if (login_successful($info, $args[1]))
	{
		// VERIFIED group
		
		// since we use a global login for auth any user should be in that group
		$_SESSION['username'] = $args[1];
		$external_login_id = bzid($info, $args[1]);
		$_SESSION['bzid'] = $external_login_id;
		$_SESSION['user_logged_in'] = true;
		
		// permissions for private messages
		allow_add_messages();
		allow_delete_messages();
	}
	
	$reply = (member_of_groups($info, $args[1], $groups));
	if ((isset($reply)) & ($reply === true))
	{
		// GU-LEAGUE.ADMINS group
		$_SESSION['username'] = $args[1];
		$_SESSION['bzid'] = $external_login_id;
		$_SESSION['user_logged_in'] = true;
		$_SESSION['IsAdmin'] = true;
		
		// permissions for news page
		allow_add_news();
		allow_edit_news();
		allow_delete_news();
		
		// permissions for bans page
		allow_add_bans();
		allow_edit_bans();
		allow_delete_bans();
		
		// user permissions
		allow_edit_any_user_profile();
		allow_add_admin_comments_to_user_profile();
		allow_ban_any_user();
		
		// visits log permissions
		allow_view_user_visits();
		
		// match permissions
		allow_any_match_action();		
	}
	
	// remove first group
	$groups = array_slice($groups, 1);
	$reply = (member_of_groups($info, $args[1], $groups));
	if ((isset($reply)) & ($reply === true))
	{
		// TS.ADMIN group
		$_SESSION['username'] = $args[1];
		$_SESSION['bzid'] = bzid($info, $args[1]);
		$_SESSION['user_logged_in'] = true;
		$_SESSION['IsAdmin'] = true;
		
		// permissions for news page
		allow_set_different_news_author();
		allow_add_news();
		allow_edit_news();
		allow_delete_news();
		
		// permissions for all static pages
		allow_edit_static_pages();
		
		// permissions for bans page
		allow_set_different_bans_author();
		allow_add_bans();
		allow_edit_bans();
		allow_delete_bans();
		
		// permissions for team page
		allow_kick_any_team_members();
		allow_edit_any_team_profile();
		allow_invite_in_any_team();
		allow_delete_any_team();
		
		// user permissions
		allow_edit_any_user_profile();
		allow_add_admin_comments_to_user_profile();
		allow_ban_any_user();
		
		// visits log permissions
		allow_view_user_visits();
		
		// match permissions
		allow_any_match_action();
	}
	
	if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'])
	{
		require_once '../CMS/navi.inc';;
		
		if (isset($_SESSION['bzid']) && ((!strcmp($_SESSION['bzid'], '-1') == 0) || !(strcmp($_SESSION['bzid'], '0') == 0)))
		{
			$_SESSION['external_id'] = $_SESSION['bzid'];
		} else
		{
			// getting bzid failed
			// or conflicts with reserved values
			// thus take away all permissions again
			no_permissions();
			unset($external_login_id);
			
			$error_msg = '';
			if (isset($_SESSION['bzid']))
			{
				$error_msg = 'Your bzid ' . htmlentities($_SESSION['bzid']) . 'conflicted with a reserved value and thus login failed!';
			} else
			{
				$error_msg = 'Login worked but no bzid could be retrieved for your account and thus login failed!';
			}
			
			if (isset($site))
			{
				$site->dieAndEndPage($error_msg);
			} else
			{
				die($error_msg);
			}
		}
		
		$_SESSION['external_login'] = true;
		$external_login_id = $_SESSION['external_id'];
		echo '<p>Login was successful!</p>' . "\n";
	} else
	{
		// login did not work, removing permissions not necessary as additional permissions where never granted
		// after permissions were removed at the beginning of the file
		require_once '../CMS/navi.inc';
		echo '<p>Error: The returned values could not be validated!</p>' . "\n";
	}
}
?>